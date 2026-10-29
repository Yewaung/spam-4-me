<?php

defined( 'ABSPATH' ) || exit;

function s4m_default_settings() {
	return array(
		'honeypot'          => 1,
		'require_js'        => 1,
		'min_seconds'       => 3,
		'rate_limit'        => 5,
		'blocked_ips'       => '',
		'block_links'       => 1,
		'link_message'      => '',
		'link_fields'       => "link*\nurl\nwebsite*\nweb site\nhomepage\nportfolio\ngithub\nfacebook\ninstagram\ntwitter\nsocial*",
		'link_fields'       => "link*\nurl\nwebsite*\nweb site\nhomepage\nportfolio\ngithub\nfacebook\ninstagram\ntwitter\nsocial*",
		'scripts'           => array( 'cyrillic' ),
		'categories'        => array_keys( s4m_keyword_lists() ),
		'keywords'          => '',
		'allowlist'         => '',
		'block_disposable'  => 1,
		'autoreply_subject' => '',
		'retention_days'    => 30,
		'store_content'     => 1,
	);
}

function s4m_settings( $refresh = false ) {
	static $settings = null;

	if ( null === $settings || $refresh ) {
		$saved = get_option( S4M_OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();

		if ( isset( $saved['block_cyrillic'] ) && ! isset( $saved['scripts'] ) ) {
			$saved['scripts'] = $saved['block_cyrillic'] ? array( 'cyrillic' ) : array();
		}

		$settings = wp_parse_args( $saved, s4m_default_settings() );
	}

	return apply_filters( 's4m_settings', $settings );
}

function s4m_flush_settings() {
	s4m_settings( true );
}
add_action( 'add_option_' . S4M_OPTION, 's4m_flush_settings' );
add_action( 'update_option_' . S4M_OPTION, 's4m_flush_settings' );

function s4m_lower( $text ) {
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
}

function s4m_lines( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $text ) ), 'strlen' ) );
}

function s4m_add_setting_line( $key, $line ) {
	$saved = get_option( S4M_OPTION, array() );
	$saved = wp_parse_args( is_array( $saved ) ? $saved : array(), s4m_default_settings() );
	$lines = s4m_lines( $saved[ $key ] );

	if ( ! in_array( s4m_lower( $line ), array_map( 's4m_lower', $lines ), true ) ) {
		$lines[]       = $line;
		$saved[ $key ] = implode( "\n", $lines );
		update_option( S4M_OPTION, $saved );
	}
}
