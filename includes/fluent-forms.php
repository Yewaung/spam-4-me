<?php

defined( 'ABSPATH' ) || exit;

function s4m_fluent_fields_html() {
	echo s4m_hidden_inputs_html();
}
add_action( 'fluentform/form_element_start', 's4m_fluent_fields_html' );

function s4m_fluent_value( array $data, $name ) {
	$path  = preg_split( '/[\[\]]+/', $name, -1, PREG_SPLIT_NO_EMPTY );
	$value = $data;

	foreach ( $path as $key ) {
		if ( ! is_array( $value ) || ! isset( $value[ $key ] ) ) {
			return null;
		}

		$value = $value[ $key ];
	}

	return $value;
}

function s4m_fluent_posted_fields( $form_data = null, array $inputs = array() ) {
	static $last = array( array(), array(), array(), array() );

	if ( null === $form_data ) {
		return $last;
	}

	$fields = array();
	$emails = array();
	$names  = array();
	$links  = array();

	foreach ( $inputs as $name => $input ) {
		$element = isset( $input['element'] ) ? $input['element'] : '';

		if ( ! in_array( $element, array( 'input_text', 'textarea', 'input_name', 'input_email' ), true ) ) {
			continue;
		}

		$value = s4m_fluent_value( (array) $form_data, $name );
		$value = is_array( $value ) ? implode( ' ', array_filter( $value, 'is_string' ) ) : $value;

		if ( ! is_string( $value ) ) {
			continue;
		}

		$label = '';

		foreach ( array( 'settings.label', 'settings.admin_field_label' ) as $key ) {
			list( $group, $setting ) = explode( '.', $key );

			if ( '' === $label && ! empty( $input['raw'][ $group ][ $setting ] ) && is_string( $input['raw'][ $group ][ $setting ] ) ) {
				$label = trim( wp_strip_all_tags( $input['raw'][ $group ][ $setting ] ) );
			}
		}

		$label = '' !== $label ? $label : $name;
		$label = isset( $names[ $label ] ) ? sprintf( '%s (%s)', $label, $name ) : $label;

		$names[ $label ] = $name;

		$classes = ( isset( $input['raw']['attributes']['class'] ) ? $input['raw']['attributes']['class'] : '' ) . ' ' . ( isset( $input['raw']['settings']['container_class'] ) ? $input['raw']['settings']['container_class'] : '' );

		if ( s4m_field_allows_links( $label, $name, $classes ) ) {
			$links[ $label ] = true;
		}

		if ( 'input_email' === $element ) {
			$emails[ $label ] = $value;
		} else {
			$fields[ $label ] = $value;
		}
	}

	$last = array( $fields, $emails, $names, $links );

	return $last;
}

function s4m_fluent_log_block( array $hit, $form, array $fields, array $emails ) {
	s4m_log_hit(
		$hit,
		array(
			'id'    => isset( $form->id ) ? $form->id : 0,
			'title' => isset( $form->title ) ? $form->title : '',
			'url'   => (string) wp_get_raw_referer(),
		),
		$fields,
		$emails
	);
}

function s4m_fluent_validate_links( $errors, $form_data, $form, $inputs ) {
	list( $fields, $emails, $names, $links ) = s4m_fluent_posted_fields( (array) $form_data, (array) $inputs );

	$settings = s4m_settings();

	if ( ! $settings['block_links'] ) {
		return $errors;
	}

	$first = '';

	foreach ( $fields as $label => $value ) {
		if ( isset( $links[ $label ] ) || ! s4m_has_link( $value ) ) {
			continue;
		}

		$name  = $names[ $label ];
		$parts = s4m_fluent_value( (array) $form_data, $name );

		if ( is_array( $parts ) ) {
			foreach ( $parts as $part => $text ) {
				if ( is_string( $text ) && s4m_has_link( $text ) ) {
					$name .= '[' . $part . ']';
					break;
				}
			}
		}

		$errors[ $name ]['s4m_link'] = esc_html( s4m_link_message() );
		$first                       = '' === $first ? $label : $first;
	}

	if ( '' !== $first ) {
		s4m_fluent_log_block(
			array(
				'reason' => 'link',
				'field'  => $first,
			),
			$form,
			$fields,
			$emails
		);
	}

	return $errors;
}
add_filter( 'fluentform/validation_errors', 's4m_fluent_validate_links', 20, 4 );

function s4m_fluent_check_spam( $insert_data, $request_data, $form ) {
	list( $fields, $emails ) = s4m_fluent_posted_fields();

	$posted = array_intersect_key( (array) $request_data, array_flip( array( '_s4m_t', '_s4m_js', S4M_HONEYPOT ) ) );

	if ( isset( $form->id ) && is_callable( array( '\FluentForm\App\Helpers\Helper', 'isConversionForm' ) ) && \FluentForm\App\Helpers\Helper::isConversionForm( $form->id ) ) {
		$posted = false;
	}

	$hit = s4m_check_submission( $fields, $emails, $posted );

	if ( $hit ) {
		s4m_fluent_log_block( $hit, $form, $fields, $emails );

		wp_send_json( array( 'errors' => esc_html( s4m_block_message() ) ), 422 );
	}
}
add_action( 'fluentform/before_insert_submission', 's4m_fluent_check_spam', 5, 3 );

function s4m_fluent_fixed_autoreply_subject( $subject, $notification, $data ) {
	$send_to = isset( $notification['sendTo'] ) ? (array) $notification['sendTo'] : array();
	$typed   = array_filter( (array) $data, 'is_string' );

	if ( isset( $send_to['type'] ) && 'field' === $send_to['type'] ) {
		return s4m_autoreply_subject();
	}

	$to = array(
		isset( $send_to['email'] ) ? $send_to['email'] : '',
		isset( $notification['cc'] ) ? $notification['cc'] : '',
		isset( $notification['bcc'] ) ? $notification['bcc'] : '',
	);

	if ( s4m_sends_to_visitor( implode( ',', $to ), $typed ) ) {
		return s4m_autoreply_subject();
	}

	return $subject;
}
add_filter( 'fluentform/email_subject', 's4m_fluent_fixed_autoreply_subject', 20, 3 );
