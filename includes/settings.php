<?php

defined( 'ABSPATH' ) || exit;

function spam4me_default_settings() {
	return array(
		'honeypot'          => 1,
		'require_js'        => 1,
		'min_seconds'       => 3,
		'rate_limit'        => 5,
		'blocked_ips'       => '',
		'block_links'       => 1,
		'link_message'      => '',
		'link_fields'       => "link*\nurl\nwebsite*\nweb site\nhomepage\nportfolio\ngithub\nfacebook\ninstagram\ntwitter\nsocial*",
		'scripts'           => array( 'cyrillic' ),
		'categories'        => array_keys( spam4me_keyword_lists() ),
		'keywords'          => '',
		'allowlist'         => '',
		'block_disposable'  => 1,
		'autoreply_subject' => '',
		'retention_days'    => 30,
		'store_content'     => 1,
	);
}

function spam4me_settings( $refresh = false ) {
	static $settings = null;

	if ( null === $settings || $refresh ) {
		$saved = get_option( SPAM4ME_OPTION, array() );
		$saved = is_array( $saved ) ? $saved : array();

		if ( isset( $saved['block_cyrillic'] ) && ! isset( $saved['scripts'] ) ) {
			$saved['scripts'] = $saved['block_cyrillic'] ? array( 'cyrillic' ) : array();
		}

		$settings = wp_parse_args( $saved, spam4me_default_settings() );
	}

	return apply_filters( 'spam4me_settings', $settings );
}

function spam4me_flush_settings() {
	spam4me_settings( true );
}
add_action( 'add_option_' . SPAM4ME_OPTION, 'spam4me_flush_settings' );
add_action( 'update_option_' . SPAM4ME_OPTION, 'spam4me_flush_settings' );

function spam4me_lower( $text ) {
	return function_exists( 'mb_strtolower' ) ? mb_strtolower( (string) $text, 'UTF-8' ) : strtolower( (string) $text );
}

function spam4me_lines( $text ) {
	return array_values( array_filter( array_map( 'trim', preg_split( '/\R/', (string) $text ) ), 'strlen' ) );
}

function spam4me_add_setting_line( $key, $line ) {
	$saved = get_option( SPAM4ME_OPTION, array() );
	$saved = wp_parse_args( is_array( $saved ) ? $saved : array(), spam4me_default_settings() );
	$lines = spam4me_lines( $saved[ $key ] );

	if ( ! in_array( spam4me_lower( $line ), array_map( 'spam4me_lower', $lines ), true ) ) {
		$lines[]       = $line;
		$saved[ $key ] = implode( "\n", $lines );
		update_option( SPAM4ME_OPTION, $saved );
	}
}
