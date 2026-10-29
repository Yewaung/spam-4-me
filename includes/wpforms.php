<?php

defined( 'ABSPATH' ) || exit;

function s4m_wpforms_fields_html() {
	echo s4m_hidden_inputs_html();
}
add_action( 'wpforms_display_submit_before', 's4m_wpforms_fields_html' );

function s4m_wpforms_posted_fields( $form_data ) {
	$posted = isset( $_POST['wpforms']['fields'] ) && is_array( $_POST['wpforms']['fields'] ) ? wp_unslash( $_POST['wpforms']['fields'] ) : array();
	$fields = array();
	$emails = array();
	$ids    = array();
	$links  = array();

	foreach ( (array) $form_data['fields'] as $field ) {
		$id   = isset( $field['id'] ) ? $field['id'] : '';
		$type = isset( $field['type'] ) ? $field['type'] : '';

		if ( ! isset( $posted[ $id ] ) || ! in_array( $type, array( 'text', 'textarea', 'name', 'email' ), true ) ) {
			continue;
		}

		$value = $posted[ $id ];

		if ( 'email' === $type ) {
			$value = is_array( $value ) ? ( isset( $value['primary'] ) ? $value['primary'] : '' ) : $value;
		} elseif ( is_array( $value ) ) {
			$value = implode( ' ', array_filter( $value, 'is_string' ) );
		}

		if ( ! is_string( $value ) ) {
			continue;
		}

		$label = isset( $field['label'] ) && '' !== trim( $field['label'] ) ? trim( $field['label'] ) : sprintf( 'Field #%s', $id );
		$label = isset( $ids[ $label ] ) ? sprintf( '%s (#%s)', $label, $id ) : $label;

		$ids[ $label ] = $id;

		if ( s4m_field_allows_links( $label, '', isset( $field['css'] ) ? $field['css'] : '' ) ) {
			$links[ $label ] = true;
		}

		if ( 'email' === $type ) {
			$emails[ $label ] = $value;
		} else {
			$fields[ $label ] = $value;
		}
	}

	return array( $fields, $emails, $ids, $links );
}

function s4m_wpforms_log_block( array $hit, $form_data, array $fields, array $emails ) {
	s4m_log_hit(
		$hit,
		array(
			'id'    => isset( $form_data['id'] ) ? $form_data['id'] : 0,
			'title' => isset( $form_data['settings']['form_title'] ) ? $form_data['settings']['form_title'] : '',
			'url'   => s4m_posted( 'page_url' ),
		),
		$fields,
		$emails
	);
}

function s4m_wpforms_check( $errors, $form_data ) {
	if ( empty( $form_data['id'] ) ) {
		return $errors;
	}

	$settings = s4m_settings();
	$form_id  = $form_data['id'];

	list( $fields, $emails, $ids, $links ) = s4m_wpforms_posted_fields( $form_data );

	if ( $settings['block_links'] ) {
		$message = esc_html( s4m_link_message() );
		$first   = '';

		foreach ( $fields as $label => $value ) {
			if ( ! isset( $links[ $label ] ) && s4m_has_link( $value ) ) {
				$id = $ids[ $label ];

				if ( empty( $errors[ $form_id ][ $id ] ) ) {
					$errors[ $form_id ][ $id ] = s4m_wpforms_field_error( $id, $message );
				}

				$first = '' === $first ? $label : $first;
			}
		}

		if ( '' !== $first ) {
			s4m_wpforms_log_block(
				array(
					'reason' => 'link',
					'field'  => $first,
				),
				$form_data,
				$fields,
				$emails
			);
		}
	}

	if ( ! empty( $errors[ $form_id ] ) ) {
		return $errors;
	}

	$hit = s4m_check_submission( $fields, $emails );

	if ( $hit ) {
		s4m_wpforms_log_block( $hit, $form_data, $fields, $emails );

		$errors[ $form_id ]['header'] = esc_html( s4m_block_message() );
	}

	return $errors;
}
add_filter( 'wpforms_process_initial_errors', 's4m_wpforms_check', 20, 2 );

function s4m_wpforms_field_error( $id, $message ) {
	$value = isset( $_POST['wpforms']['fields'][ $id ] ) ? wp_unslash( $_POST['wpforms']['fields'][ $id ] ) : '';

	if ( ! is_array( $value ) ) {
		return $message;
	}

	foreach ( $value as $part => $text ) {
		if ( is_string( $text ) && s4m_has_link( $text ) ) {
			return array( $part => $message );
		}
	}

	return array( (string) key( $value ) => $message );
}

function s4m_wpforms_fixed_autoreply_subject( $email, $fields, $entry, $form_data ) {
	$typed = array();

	foreach ( (array) $fields as $field ) {
		if ( isset( $field['type'], $field['value'] ) && 'email' === $field['type'] && is_string( $field['value'] ) ) {
			$typed[] = $field['value'];
		}
	}

	$to = array_merge(
		isset( $email['address'] ) ? (array) $email['address'] : array(),
		isset( $email['carboncopy'] ) ? (array) $email['carboncopy'] : array()
	);

	if ( s4m_sends_to_visitor( $to, $typed ) ) {
		$email['subject'] = s4m_autoreply_subject();
	}

	return $email;
}
add_filter( 'wpforms_entry_email_atts', 's4m_wpforms_fixed_autoreply_subject', 20, 4 );
