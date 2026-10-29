<?php

defined( 'ABSPATH' ) || exit;

define( 'S4M_HONEYPOT', 's4m_website' );
define( 'S4M_CACHE_GRACE', 2 * DAY_IN_SECONDS );

function s4m_honeypot_html() {
	$settings = s4m_settings();

	if ( ! $settings['honeypot'] ) {
		return '';
	}

	return sprintf(
		'<span class="s4m-field" aria-hidden="true" style="position:absolute!important;left:-9999px!important;width:1px;height:1px;overflow:hidden"><label>%1$s <input type="text" name="%2$s" value="" tabindex="-1" autocomplete="off"></label></span>',
		esc_html__( 'Leave this field empty', 'spam-4-me' ),
		esc_attr( S4M_HONEYPOT )
	);
}

function s4m_hidden_inputs_html() {
	return sprintf(
		'<input type="hidden" name="_s4m_t" value="%s"><input type="hidden" name="_s4m_js" value="">',
		esc_attr( s4m_make_token() )
	) . s4m_honeypot_html();
}

function s4m_enqueue_script() {
	$settings = s4m_settings();

	if ( $settings['require_js'] ) {
		wp_enqueue_script(
			'spam-4-me',
			S4M_URL . 'assets/s4m.js',
			array(),
			S4M_VERSION,
			array(
				'in_footer' => true,
				'strategy'  => 'defer',
			)
		);
	}
}
add_action( 'wp_enqueue_scripts', 's4m_enqueue_script' );

function s4m_posted( $name ) {
	$value = isset( $_POST[ $name ] ) ? wp_unslash( $_POST[ $name ] ) : '';

	return is_string( $value ) ? $value : '';
}

function s4m_check_request( $settings, $posted = null ) {
	$read = function ( $name ) use ( $posted ) {
		if ( null === $posted ) {
			return s4m_posted( $name );
		}

		return isset( $posted[ $name ] ) && is_string( $posted[ $name ] ) ? $posted[ $name ] : '';
	};

	$ip = s4m_client_ip();

	if ( $ip ) {
		$range = s4m_ip_match( $ip, s4m_lines( $settings['blocked_ips'] ) );

		if ( $range ) {
			return array(
				'reason' => 'blocked_ip',
				'detail' => $range,
			);
		}
	}

	if ( false === $posted ) {
		return s4m_check_rate_limit( $settings, $ip );
	}

	$trap = $read( S4M_HONEYPOT );

	if ( $settings['honeypot'] && '' !== trim( $trap ) ) {
		return array(
			'reason' => 'honeypot',
			'detail' => mb_substr( $trap, 0, 100 ),
		);
	}

	$token     = $read( '_s4m_t' );
	$time      = s4m_token_time( $token );
	$pre_cache = '' === $token && time() - (int) get_option( 's4m_activated', 0 ) < S4M_CACHE_GRACE;

	if ( $settings['require_js'] && ! $pre_cache && ( false === $time || ! hash_equals( $token, $read( '_s4m_js' ) ) ) ) {
		return array(
			'reason' => 'no_js',
			'detail' => '',
		);
	}

	if ( $settings['min_seconds'] > 0 && false !== $time ) {
		$elapsed = max( 0, time() - $time );

		if ( $elapsed < (int) $settings['min_seconds'] ) {
			return array(
				'reason' => 'too_fast',
				'detail' => sprintf( _n( '%d second', '%d seconds', $elapsed, 'spam-4-me' ), $elapsed ),
			);
		}
	}

	return s4m_check_rate_limit( $settings, $ip );
}

function s4m_check_rate_limit( $settings, $ip ) {
	if ( $settings['rate_limit'] > 0 && $ip && ! s4m_ip_match( $ip, s4m_cloudflare_ranges() ) ) {
		$key   = 's4m_rl_' . md5( $ip );
		$count = (int) get_transient( $key ) + 1;

		set_transient( $key, $count, HOUR_IN_SECONDS );

		if ( $count > (int) $settings['rate_limit'] ) {
			return array(
				'reason' => 'rate_limit',
				'detail' => sprintf( __( '%d in the last hour', 'spam-4-me' ), $count ),
			);
		}
	}

	return null;
}

function s4m_check_submission( array $fields, array $emails, $posted = null ) {
	$settings = s4m_settings();
	$hit      = s4m_check_request( $settings, $posted );

	if ( ! $hit ) {
		$hits = s4m_check_content( $fields, $emails, $settings );
		$hit  = $hits ? $hits[0] : null;
	}

	return $hit;
}

function s4m_log_hit( array $hit, array $form, array $fields, array $emails ) {
	$settings = s4m_settings();
	$content  = null;

	if ( $settings['store_content'] ) {
		$content = wp_json_encode(
			array_map(
				function ( $value ) {
					return mb_substr( (string) $value, 0, 1000 );
				},
				$fields + $emails
			),
			JSON_UNESCAPED_UNICODE
		);
	}

	S4M_Log::add(
		array(
			'form_id'    => isset( $form['id'] ) ? $form['id'] : 0,
			'form_title' => isset( $form['title'] ) ? $form['title'] : '',
			'reason'     => $hit['reason'],
			'detail'     => isset( $hit['detail'] ) ? $hit['detail'] : '',
			'category'   => isset( $hit['category'] ) ? $hit['category'] : '',
			'field'      => isset( $hit['field'] ) ? $hit['field'] : '',
			'ip'         => s4m_client_ip(),
			'email'      => $emails ? sanitize_email( reset( $emails ) ) : '',
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) ) : '',
			'page_url'   => isset( $form['url'] ) ? esc_url_raw( (string) $form['url'] ) : '',
			'content'    => $content,
		)
	);
}

function s4m_autoreply_subject() {
	$settings = s4m_settings();
	$subject  = trim( $settings['autoreply_subject'] );

	if ( '' === $subject ) {
		$subject = sprintf( __( 'Thank you for contacting %s', 'spam-4-me' ), get_bloginfo( 'name' ) );
	}

	return $subject;
}

function s4m_link_message() {
	$settings = s4m_settings();

	return '' !== trim( $settings['link_message'] ) ? $settings['link_message'] : __( 'Links are not allowed in this field.', 'spam-4-me' );
}

function s4m_block_message() {
	return __( 'There was an error trying to send your message. Please try again later.', 'spam-4-me' );
}

function s4m_sends_to_visitor( $recipients, array $typed ) {
	$recipients = is_array( $recipients ) ? $recipients : explode( ',', (string) $recipients );
	$recipients = array_filter( array_map( 'strtolower', array_map( 'trim', $recipients ) ) );
	$typed      = array_filter( array_map( 'strtolower', array_map( 'trim', array_filter( $typed, 'is_string' ) ) ) );

	return (bool) array_intersect( $recipients, $typed );
}

function s4m_field_allows_links( $label, $name = '', $classes = '' ) {
	if ( preg_match( '/(?:^|\s)s4m-allow-links(?:\s|$)/', (string) $classes ) ) {
		return true;
	}

	static $cache = array();

	$settings = s4m_settings();
	$key      = md5( (string) $settings['link_fields'] );

	if ( ! isset( $cache[ $key ] ) ) {
		$cache[ $key ] = array();

		foreach ( s4m_lines( $settings['link_fields'] ) as $term ) {
			$regex = s4m_term_regex( $term );

			if ( '' !== $regex ) {
				$cache[ $key ][] = '/' . $regex . '/u';
			}
		}
	}

	$text = s4m_normalize( $label . ' ' . $name );

	foreach ( $cache[ $key ] as $pattern ) {
		if ( preg_match( $pattern, $text ) ) {
			return true;
		}
	}

	return false;
}
