<?php

defined( 'ABSPATH' ) || exit;

function s4m_ninja_after_form( $form_id ) {
	printf(
		'<div class="s4m-nf" data-s4m-form="%1$s"><input type="hidden" name="_s4m_t" value="%2$s">%3$s</div>',
		esc_attr( strtok( (string) $form_id, '_' ) ),
		esc_attr( s4m_make_token() ),
		s4m_honeypot_html()
	);
}
add_action( 'ninja_forms_after_form_display', 's4m_ninja_after_form' );

function s4m_ninja_posted_fields( $form_data = null ) {
	static $last = array( array(), array(), array(), array() );

	if ( null === $form_data ) {
		return $last;
	}

	$fields = array();
	$emails = array();
	$ids    = array();
	$links  = array();
	$form   = Ninja_Forms()->form( (int) $form_data['id'] );

	foreach ( $form->get_fields() as $id => $model ) {
		$type  = $model->get_setting( 'type' );
		$value = isset( $form_data['fields'][ $id ]['value'] ) ? $form_data['fields'][ $id ]['value'] : null;

		if ( ! is_string( $value ) || ! in_array( $type, array( 'textbox', 'textarea', 'firstname', 'lastname', 'email' ), true ) ) {
			continue;
		}

		$label = trim( (string) $model->get_setting( 'label' ) );
		$label = '' !== $label ? $label : sprintf( 'Field #%s', $id );
		$label = isset( $ids[ $label ] ) ? sprintf( '%s (#%s)', $label, $id ) : $label;

		$ids[ $label ] = $id;

		if ( s4m_field_allows_links( $label, (string) $model->get_setting( 'key' ), $model->get_setting( 'element_class' ) . ' ' . $model->get_setting( 'container_class' ) ) ) {
			$links[ $label ] = true;
		}

		if ( 'email' === $type ) {
			$emails[ $label ] = $value;
		} else {
			$fields[ $label ] = $value;
		}
	}

	$last = array( $fields, $emails, $ids, $links );

	return $last;
}

function s4m_ninja_log_block( array $hit, $form_data, array $fields, array $emails ) {
	$id    = (int) $form_data['id'];
	$title = (string) Ninja_Forms()->form( $id )->get()->get_setting( 'title' );

	s4m_log_hit(
		$hit,
		array(
			'id'    => $id,
			'title' => '' !== $title ? $title : sprintf( __( 'Ninja Forms #%d', 'spam-4-me' ), $id ),
			'url'   => (string) wp_get_raw_referer(),
		),
		$fields,
		$emails
	);
}

function s4m_ninja_check( $form_data ) {
	if ( empty( $form_data['id'] ) ) {
		return $form_data;
	}

	$extra  = isset( $form_data['extra'] ) && is_array( $form_data['extra'] ) ? $form_data['extra'] : array();
	$posted = array_intersect_key( $extra, array_flip( array( '_s4m_t', '_s4m_js', S4M_HONEYPOT ) ) );

	$form_data['extra'] = array_diff_key( $extra, $posted );

	list( $fields, $emails, $ids, $links ) = s4m_ninja_posted_fields( $form_data );

	$settings = s4m_settings();
	$first    = '';

	if ( $settings['block_links'] ) {
		foreach ( $fields as $label => $value ) {
			if ( ! isset( $links[ $label ] ) && s4m_has_link( $value ) ) {
				$form_data['errors']['fields'][ $ids[ $label ] ] = esc_html( s4m_link_message() );
				$first = '' === $first ? $label : $first;
			}
		}
	}

	if ( '' !== $first ) {
		s4m_ninja_log_block(
			array(
				'reason' => 'link',
				'field'  => $first,
			),
			$form_data,
			$fields,
			$emails
		);

		return $form_data;
	}

	$hit = s4m_check_submission( $fields, $emails, $posted );

	if ( $hit ) {
		s4m_ninja_log_block( $hit, $form_data, $fields, $emails );

		wp_send_json(
			array(
				'data'   => array( 'form_id' => $form_data['id'] ),
				'errors' => array( 'form' => array( 'spam' => esc_html( s4m_block_message() ) ) ),
				'debug'  => array(),
			)
		);
	}

	return $form_data;
}
add_filter( 'ninja_forms_submit_data', 's4m_ninja_check', 20 );

function s4m_ninja_fixed_autoreply_subject( $action ) {
	if ( ! isset( $action['type'], $action['to'] ) || 'email' !== $action['type'] ) {
		return $action;
	}

	list( , $emails ) = s4m_ninja_posted_fields();

	$to = array( $action['to'], isset( $action['cc'] ) ? $action['cc'] : '', isset( $action['bcc'] ) ? $action['bcc'] : '' );

	if ( s4m_sends_to_visitor( implode( ',', $to ), array_values( $emails ) ) ) {
		$action['email_subject'] = s4m_autoreply_subject();
	}

	return $action;
}
add_filter( 'ninja_forms_run_action_settings', 's4m_ninja_fixed_autoreply_subject', 20 );
