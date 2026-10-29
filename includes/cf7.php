<?php

defined( 'ABSPATH' ) || exit;

function s4m_hidden_fields( $fields ) {
	$fields['_s4m_t']  = s4m_make_token();
	$fields['_s4m_js'] = '';

	return $fields;
}
add_filter( 'wpcf7_form_hidden_fields', 's4m_hidden_fields' );

function s4m_honeypot_field( $html ) {
	return $html . s4m_honeypot_html();
}
add_filter( 'wpcf7_form_elements', 's4m_honeypot_field' );

function s4m_posted_fields( $contact_form ) {
	static $cache = array();

	$id = $contact_form->id();

	if ( isset( $cache[ $id ] ) ) {
		return $cache[ $id ];
	}

	$fields = array();
	$emails = array();

	foreach ( $contact_form->scan_form_tags() as $tag ) {
		if ( '' === $tag->name ) {
			continue;
		}

		if ( 'text' === $tag->basetype || 'textarea' === $tag->basetype ) {
			$fields[ $tag->name ] = s4m_posted( $tag->name );
		} elseif ( 'email' === $tag->basetype ) {
			$emails[ $tag->name ] = s4m_posted( $tag->name );
		}
	}

	$cache[ $id ] = array( $fields, $emails );

	return $cache[ $id ];
}

function s4m_log_block( array $hit, $contact_form, array $fields, array $emails ) {
	$submission = WPCF7_Submission::get_instance();

	s4m_log_hit(
		$hit,
		array(
			'id'    => $contact_form ? $contact_form->id() : 0,
			'title' => $contact_form ? $contact_form->title() : '',
			'url'   => $submission ? (string) $submission->get_meta( 'url' ) : '',
		),
		$fields,
		$emails
	);
}

function s4m_validate_links( $result, $tags ) {
	$settings     = s4m_settings();
	$contact_form = wpcf7_get_current_contact_form();

	if ( ! $settings['block_links'] || ! $contact_form ) {
		return $result;
	}

	list( $fields, $emails ) = s4m_posted_fields( $contact_form );

	$message = s4m_link_message();
	$first   = '';

	foreach ( $tags as $tag ) {
		$classes = method_exists( $tag, 'get_class_option' ) ? (string) $tag->get_class_option() : '';

		if ( isset( $fields[ $tag->name ] ) && s4m_has_link( $fields[ $tag->name ] ) && ! s4m_field_allows_links( '', $tag->name, $classes ) ) {
			$result->invalidate( $tag, $message );
			$first = '' === $first ? $tag->name : $first;
		}
	}

	if ( '' !== $first ) {
		s4m_log_block(
			array(
				'reason' => 'link',
				'field'  => $first,
			),
			$contact_form,
			$fields,
			$emails
		);
	}

	return $result;
}
add_filter( 'wpcf7_validate', 's4m_validate_links', 20, 2 );

function s4m_check_spam( $spam, $submission ) {
	if ( $spam ) {
		return $spam;
	}

	$contact_form = $submission->get_contact_form();

	list( $fields, $emails ) = s4m_posted_fields( $contact_form );

	$hit = s4m_check_submission( $fields, $emails );

	if ( ! $hit ) {
		return false;
	}

	$reasons = S4M_Log::reasons();
	$detail  = S4M_Log::describe( $hit );

	$submission->add_spam_log(
		array(
			'agent'  => 'spam-4-me',
			'reason' => $reasons[ $hit['reason'] ]['label'] . ( '' !== $detail ? ': ' . $detail : '' ),
		)
	);

	s4m_log_block( $hit, $contact_form, $fields, $emails );

	return true;
}
add_filter( 'wpcf7_spam', 's4m_check_spam', 5, 2 );

function s4m_fixed_autoreply_subject( $components, $form, $mail ) {
	if ( ! is_object( $mail ) || 'mail_2' !== $mail->name() ) {
		return $components;
	}

	$components['subject'] = s4m_autoreply_subject();

	return $components;
}
add_filter( 'wpcf7_mail_components', 's4m_fixed_autoreply_subject', 20, 3 );
