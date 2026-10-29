<?php

defined( 'ABSPATH' ) || exit;

function s4m_gf_form_tag( $form_tag ) {
	return $form_tag . s4m_hidden_inputs_html();
}
add_filter( 'gform_form_tag', 's4m_gf_form_tag' );

function s4m_gf_posted_fields( $form ) {
	$fields  = array();
	$emails  = array();
	$objects = array();

	foreach ( isset( $form['fields'] ) ? (array) $form['fields'] : array() as $field ) {
		$type = is_object( $field ) ? $field->type : '';

		if ( ! in_array( $type, array( 'text', 'textarea', 'name', 'email' ), true ) ) {
			continue;
		}

		if ( 'name' === $type && is_array( $field->inputs ) && $field->inputs ) {
			$parts = array();

			foreach ( $field->inputs as $input ) {
				$parts[] = s4m_posted( 'input_' . str_replace( '.', '_', $input['id'] ) );
			}

			$value = implode( ' ', array_filter( $parts, 'strlen' ) );
		} else {
			$value = s4m_posted( 'input_' . $field->id );
		}

		$label = '' !== trim( (string) $field->label ) ? trim( $field->label ) : sprintf( 'Field #%s', $field->id );
		$label = isset( $objects[ $label ] ) ? sprintf( '%s (#%s)', $label, $field->id ) : $label;

		$objects[ $label ] = $field;

		if ( 'email' === $type ) {
			$emails[ $label ] = $value;
		} else {
			$fields[ $label ] = $value;
		}
	}

	return array( $fields, $emails, $objects );
}

function s4m_gf_log_block( array $hit, $form, array $fields, array $emails, $url = '' ) {
	s4m_log_hit(
		$hit,
		array(
			'id'    => isset( $form['id'] ) ? $form['id'] : 0,
			'title' => isset( $form['title'] ) ? $form['title'] : '',
			'url'   => '' !== $url ? $url : (string) wp_get_raw_referer(),
		),
		$fields,
		$emails
	);
}

function s4m_gf_validate_links( $result ) {
	$settings = s4m_settings();
	$form     = isset( $result['form'] ) ? $result['form'] : array();

	if ( ! $settings['block_links'] || empty( $form['id'] ) ) {
		return $result;
	}

	list( $fields, $emails, $objects ) = s4m_gf_posted_fields( $form );

	$final  = 0 === (int) s4m_posted( 'gform_target_page_number_' . $form['id'] );
	$source = max( 1, (int) s4m_posted( 'gform_source_page_number_' . $form['id'] ) );
	$first  = '';

	foreach ( $fields as $label => $value ) {
		$field = $objects[ $label ];

		if ( ( ! $final && (int) $field->pageNumber > 1 && (int) $field->pageNumber !== $source ) || ! s4m_has_link( $value ) || s4m_field_allows_links( $label, isset( $field->adminLabel ) ? (string) $field->adminLabel : '', isset( $field->cssClass ) ? (string) $field->cssClass : '' ) ) {
			continue;
		}

		if ( ! $field->failed_validation ) {
			$field->failed_validation  = true;
			$field->validation_message = esc_html( s4m_link_message() );
		}

		if ( '' === $first ) {
			$first                            = $label;
			$result['is_valid']               = false;
			$result['failed_validation_page'] = max( 1, (int) $field->pageNumber );
		}
	}

	if ( '' !== $first ) {
		s4m_gf_log_block(
			array(
				'reason' => 'link',
				'field'  => $first,
			),
			$form,
			$fields,
			$emails
		);
	}

	$result['form'] = $form;

	return $result;
}
add_filter( 'gform_validation', 's4m_gf_validate_links', 20 );

function s4m_gf_check_spam( $is_spam, $form, $entry ) {
	if ( $is_spam ) {
		return $is_spam;
	}

	list( $fields, $emails ) = s4m_gf_posted_fields( $form );

	$hit = s4m_check_submission( $fields, $emails );

	if ( ! $hit ) {
		return false;
	}

	if ( class_exists( 'GFCommon' ) && method_exists( 'GFCommon', 'set_spam_filter' ) ) {
		$reasons = S4M_Log::reasons();
		$detail  = S4M_Log::describe( $hit );

		GFCommon::set_spam_filter( rgar( $form, 'id' ), 'Spam 4 Me', $reasons[ $hit['reason'] ]['label'] . ( '' !== $detail ? ': ' . $detail : '' ) );
	}

	s4m_gf_log_block( $hit, $form, $fields, $emails, isset( $entry['source_url'] ) ? $entry['source_url'] : '' );

	return true;
}
add_filter( 'gform_entry_is_spam', 's4m_gf_check_spam', 5, 3 );

function s4m_gf_fixed_autoreply_subject( $notification, $form, $entry ) {
	$typed = array();

	foreach ( isset( $form['fields'] ) ? (array) $form['fields'] : array() as $field ) {
		if ( is_object( $field ) && 'email' === $field->type && isset( $entry[ (string) $field->id ] ) ) {
			$typed[] = $entry[ (string) $field->id ];
		}
	}

	$to = isset( $notification['to'] ) ? (string) $notification['to'] : '';

	if ( isset( $notification['toType'] ) && 'field' === $notification['toType'] ) {
		$to = isset( $entry[ $to ] ) ? $entry[ $to ] : '';
	}

	$to = implode( ',', array( $to, isset( $notification['cc'] ) ? $notification['cc'] : '', isset( $notification['bcc'] ) ? $notification['bcc'] : '' ) );

	if ( class_exists( 'GFCommon' ) && false !== strpos( $to, '{' ) ) {
		$to = GFCommon::replace_variables( $to, $form, $entry, false, false, false, 'text' );
	}

	if ( s4m_sends_to_visitor( $to, $typed ) ) {
		$notification['subject'] = s4m_autoreply_subject();
	}

	return $notification;
}
add_filter( 'gform_notification', 's4m_gf_fixed_autoreply_subject', 20, 3 );
