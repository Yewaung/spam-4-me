<?php

defined( 'ABSPATH' ) || exit;

function spam4me_fluent_fields_html() {
	echo spam4me_hidden_inputs_html(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- escaped in s4m_hidden_inputs_html().
}
add_action( 'fluentform/form_element_start', 'spam4me_fluent_fields_html' );

function spam4me_fluent_value( array $data, $name ) {
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

function spam4me_fluent_posted_fields( $form_data = null, array $inputs = array() ) {
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

		$value = spam4me_fluent_value( (array) $form_data, $name );
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

		if ( spam4me_field_allows_links( $label, $name, $classes ) ) {
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

function spam4me_fluent_log_block( array $hit, $form, array $fields, array $emails ) {
	spam4me_log_hit(
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

function spam4me_fluent_validate_links( $errors, $form_data, $form, $inputs ) {
	list( $fields, $emails, $names, $links ) = spam4me_fluent_posted_fields( (array) $form_data, (array) $inputs );

	$settings = spam4me_settings();

	if ( ! $settings['block_links'] ) {
		return $errors;
	}

	$first = '';

	foreach ( $fields as $label => $value ) {
		if ( isset( $links[ $label ] ) || ! spam4me_has_link( $value ) ) {
			continue;
		}

		$name  = $names[ $label ];
		$parts = spam4me_fluent_value( (array) $form_data, $name );

		if ( is_array( $parts ) ) {
			foreach ( $parts as $part => $text ) {
				if ( is_string( $text ) && spam4me_has_link( $text ) ) {
					$name .= '[' . $part . ']';
					break;
				}
			}
		}

		$errors[ $name ]['s4m_link'] = esc_html( spam4me_link_message() );
		$first                       = '' === $first ? $label : $first;
	}

	if ( '' !== $first ) {
		spam4me_fluent_log_block(
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
add_filter( 'fluentform/validation_errors', 'spam4me_fluent_validate_links', 20, 4 );

function spam4me_fluent_check_spam( $insert_data, $request_data, $form ) {
	list( $fields, $emails ) = spam4me_fluent_posted_fields();

	$posted = array_intersect_key( (array) $request_data, array_flip( array( '_s4m_t', '_s4m_js', SPAM4ME_HONEYPOT ) ) );

	if ( isset( $form->id ) && is_callable( array( '\FluentForm\App\Helpers\Helper', 'isConversionForm' ) ) && \FluentForm\App\Helpers\Helper::isConversionForm( $form->id ) ) {
		$posted = false;
	}

	$hit = spam4me_check_submission( $fields, $emails, $posted );

	if ( $hit ) {
		spam4me_fluent_log_block( $hit, $form, $fields, $emails );

		wp_send_json( array( 'errors' => esc_html( spam4me_block_message() ) ), 422 );
	}
}
add_action( 'fluentform/before_insert_submission', 'spam4me_fluent_check_spam', 5, 3 );

function spam4me_fluent_fixed_autoreply_subject( $subject, $notification, $data ) {
	$send_to = isset( $notification['sendTo'] ) ? (array) $notification['sendTo'] : array();
	$typed   = array_filter( (array) $data, 'is_string' );

	if ( isset( $send_to['type'] ) && 'field' === $send_to['type'] ) {
		return spam4me_autoreply_subject();
	}

	$to = array(
		isset( $send_to['email'] ) ? $send_to['email'] : '',
		isset( $notification['cc'] ) ? $notification['cc'] : '',
		isset( $notification['bcc'] ) ? $notification['bcc'] : '',
	);

	if ( spam4me_sends_to_visitor( implode( ',', $to ), $typed ) ) {
		return spam4me_autoreply_subject();
	}

	return $subject;
}
add_filter( 'fluentform/email_subject', 'spam4me_fluent_fixed_autoreply_subject', 20, 3 );
