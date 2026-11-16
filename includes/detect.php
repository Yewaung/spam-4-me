<?php

defined( 'ABSPATH' ) || exit;

function spam4me_script_patterns() {
	return array(
		'cyrillic'   => '\p{Cyrillic}',
		'han'        => '\p{Han}',
		'japanese'   => '[\p{Hiragana}\p{Katakana}]',
		'hangul'     => '\p{Hangul}',
		'arabic'     => '\p{Arabic}',
		'hebrew'     => '\p{Hebrew}',
		'thai'       => '\p{Thai}',
		'devanagari' => '\p{Devanagari}',
		'greek'      => '\p{Greek}',
	);
}

function spam4me_script_labels() {
	return array(
		'cyrillic'   => __( 'Cyrillic (Russian, Ukrainian …)', 'spam4me' ),
		'han'        => __( 'Chinese characters (also Japanese kanji)', 'spam4me' ),
		'japanese'   => __( 'Japanese kana', 'spam4me' ),
		'hangul'     => __( 'Korean', 'spam4me' ),
		'arabic'     => __( 'Arabic, Persian, Urdu', 'spam4me' ),
		'hebrew'     => __( 'Hebrew', 'spam4me' ),
		'thai'       => __( 'Thai', 'spam4me' ),
		'devanagari' => __( 'Hindi (Devanagari)', 'spam4me' ),
		'greek'      => __( 'Greek', 'spam4me' ),
	);
}

function spam4me_normalize( $text ) {
	$text = wp_check_invalid_utf8( (string) $text, true );

	if ( class_exists( 'Normalizer' ) ) {
		$normalized = Normalizer::normalize( $text, Normalizer::FORM_KC );
		$text       = false === $normalized ? $text : $normalized;
	}

	$text = (string) preg_replace( '/[\x{00AD}\x{200B}-\x{200F}\x{2060}-\x{2064}\x{FEFF}]/u', '', $text );

	return spam4me_lower( $text );
}

function spam4me_text_variants( $text ) {
	$joined = (string) preg_replace_callback(
		'/(?<![\p{L}\p{N}])(?:\p{L}[\s.\-_*+]){2,}\p{L}(?![\p{L}\p{N}])/u',
		function ( $m ) {
			return preg_replace( '/[\s.\-_*+]/u', '', $m[0] );
		},
		$text
	);

	$leet = strtr(
		$joined,
		array(
			'0' => 'o',
			'1' => 'i',
			'3' => 'e',
			'4' => 'a',
			'5' => 's',
			'7' => 't',
			'@' => 'a',
			'$' => 's',
		)
	);

	return array_values( array_unique( array( $text, $joined, $leet ) ) );
}

function spam4me_term_regex( $term ) {
	$term = spam4me_normalize( trim( $term ) );

	if ( '' === $term || '#' === $term[0] ) {
		return '';
	}

	$prefix = '*' === substr( $term, -1 );
	$term   = rtrim( $term, '*' );

	if ( '' === $term ) {
		return '';
	}

	$words  = preg_split( '/\s+/u', $term );
	$quoted = implode( '(?:\s+|[-_])', array_map( 'preg_quote', $words, array_fill( 0, count( $words ), '/' ) ) );

	if ( preg_match( '/[\p{Han}\p{Hiragana}\p{Katakana}\p{Hangul}\p{Thai}\p{Lao}\p{Khmer}\p{Myanmar}]/u', $term ) ) {
		return $quoted;
	}

	return '(?<![\p{L}\p{N}])' . $quoted . ( $prefix ? '[\p{L}\p{N}]*' : '' ) . '(?![\p{L}\p{N}])';
}

function spam4me_compiled_rules( $settings ) {
	static $cache = array();

	$key = md5( wp_json_encode( array( $settings['categories'], $settings['keywords'], $settings['allowlist'] ) ) );

	if ( isset( $cache[ $key ] ) ) {
		return $cache[ $key ];
	}

	$allow_terms = array_map( 'spam4me_normalize', spam4me_lines( $settings['allowlist'] ) );
	$lists       = array_intersect_key( spam4me_keyword_lists(), array_flip( (array) $settings['categories'] ) );

	$lists['custom'] = spam4me_lines( $settings['keywords'] );

	$rules = array();

	foreach ( $lists as $category => $terms ) {
		foreach ( $terms as $term ) {
			$regex = spam4me_term_regex( $term );

			if ( '' !== $regex && ! in_array( spam4me_normalize( $term ), $allow_terms, true ) ) {
				$rules[] = array(
					'term'     => $term,
					'category' => $category,
					'pattern'  => '/' . $regex . '/u',
				);
			}
		}
	}

	$allow = array();

	foreach ( spam4me_lines( $settings['allowlist'] ) as $phrase ) {
		$regex = spam4me_term_regex( $phrase );

		if ( '' !== $regex ) {
			$allow[] = '/' . $regex . '/u';
		}
	}

	$cache[ $key ] = array(
		'rules' => $rules,
		'allow' => $allow,
	);

	return $cache[ $key ];
}

function spam4me_active_terms( $settings ) {
	$compiled = spam4me_compiled_rules( $settings );

	return $compiled['rules'];
}

function spam4me_match_rules( array $variants, array $compiled, $all ) {
	$matches = array();

	foreach ( $compiled['rules'] as $rule ) {
		foreach ( $variants as $variant ) {
			if ( preg_match( $rule['pattern'], $variant ) ) {
				$matches[] = $rule;

				if ( ! $all ) {
					return $matches;
				}

				break;
			}
		}
	}

	return $matches;
}

function spam4me_has_link( $text ) {
	return (bool) preg_match( '~https?://|hxxps?://|www\.|\[url|<a\s|[a-z0-9-]+\.[a-z]{2,}/~iu', $text );
}

function spam4me_disposable_domain( $email ) {
	$at = strrpos( $email, '@' );

	if ( false === $at ) {
		return false;
	}

	$domains = spam4me_disposable_domains();
	$labels  = explode( '.', strtolower( trim( substr( $email, $at + 1 ) ) ) );

	while ( count( $labels ) > 1 ) { // phpcs:ignore Squiz.PHP.DisallowSizeFunctionsInLoops.Found -- the list shrinks on every pass.
		$domain = implode( '.', $labels );

		if ( isset( $domains[ $domain ] ) ) {
			return $domain;
		}

		array_shift( $labels );
	}

	return false;
}

function spam4me_check_content( array $fields, array $emails, $settings, $all = false, $check_links = false ) {
	$hits = array();

	$add = function ( $reason, $detail, $field, $category = '' ) use ( &$hits ) {
		$hits[] = array(
			'reason'   => $reason,
			'detail'   => $detail,
			'field'    => $field,
			'category' => $category,
		);
	};

	if ( $settings['block_disposable'] ) {
		foreach ( $emails as $name => $email ) {
			$domain = spam4me_disposable_domain( (string) $email );

			if ( $domain ) {
				$add( 'disposable_email', $domain, $name );

				if ( ! $all ) {
					return $hits;
				}
			}
		}
	}

	$scripts  = array_intersect_key( spam4me_script_patterns(), array_flip( (array) $settings['scripts'] ) );
	$compiled = spam4me_compiled_rules( $settings );

	foreach ( $fields as $name => $value ) {
		$value = (string) $value;

		if ( '' === trim( $value ) ) {
			continue;
		}

		if ( $check_links && $settings['block_links'] && spam4me_has_link( $value ) ) {
			$add( 'link', '', $name );

			if ( ! $all ) {
				return $hits;
			}
		}

		$text = spam4me_normalize( $value );

		foreach ( $compiled['allow'] as $pattern ) {
			$text = (string) preg_replace( $pattern, ' ', $text );
		}

		foreach ( $scripts as $script => $class ) {
			if ( preg_match( '/' . $class . '/u', $text ) ) {
				$add( 'script', $script, $name );

				if ( ! $all ) {
					return $hits;
				}
			}
		}

		foreach ( spam4me_match_rules( spam4me_text_variants( $text ), $compiled, $all ) as $rule ) {
			$add( 'keyword', $rule['term'], $name, $rule['category'] );

			if ( ! $all ) {
				return $hits;
			}
		}
	}

	return $hits;
}

function spam4me_cloudflare_ranges() {
	return array(
		'173.245.48.0/20',
		'103.21.244.0/22',
		'103.22.200.0/22',
		'103.31.4.0/22',
		'141.101.64.0/18',
		'108.162.192.0/18',
		'190.93.240.0/20',
		'188.114.96.0/20',
		'197.234.240.0/22',
		'198.41.128.0/17',
		'162.158.0.0/15',
		'104.16.0.0/13',
		'104.24.0.0/14',
		'172.64.0.0/13',
		'131.0.72.0/22',
		'2400:cb00::/32',
		'2606:4700::/32',
		'2803:f800::/32',
		'2405:b500::/32',
		'2405:8100::/32',
		'2a06:98c0::/29',
		'2c0f:f248::/32',
	);
}

function spam4me_is_valid_range( $range ) {
	if ( false === strpos( $range, '/' ) ) {
		return (bool) filter_var( $range, FILTER_VALIDATE_IP );
	}

	list( $subnet, $bits ) = explode( '/', $range, 2 );

	if ( ! filter_var( $subnet, FILTER_VALIDATE_IP ) || ! ctype_digit( $bits ) ) {
		return false;
	}

	return (int) $bits <= ( false === strpos( $subnet, ':' ) ? 32 : 128 );
}

function spam4me_ip_in_range( $ip, $range ) {
	$range = trim( $range );

	if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) || ! spam4me_is_valid_range( $range ) ) {
		return false;
	}

	if ( false === strpos( $range, '/' ) ) {
		return inet_pton( $ip ) === inet_pton( $range );
	}

	list( $subnet, $bits ) = explode( '/', $range, 2 );

	$ip_bin     = inet_pton( $ip );
	$subnet_bin = inet_pton( $subnet );

	if ( strlen( $ip_bin ) !== strlen( $subnet_bin ) ) {
		return false;
	}

	$bits  = (int) $bits;
	$bytes = intdiv( $bits, 8 );
	$rest  = $bits % 8;

	if ( substr( $ip_bin, 0, $bytes ) !== substr( $subnet_bin, 0, $bytes ) ) {
		return false;
	}

	if ( 0 === $rest ) {
		return true;
	}

	$mask = ( 0xFF << ( 8 - $rest ) ) & 0xFF;

	return ( ord( $ip_bin[ $bytes ] ) & $mask ) === ( ord( $subnet_bin[ $bytes ] ) & $mask );
}

function spam4me_ip_match( $ip, array $ranges ) {
	foreach ( $ranges as $range ) {
		if ( spam4me_ip_in_range( $ip, $range ) ) {
			return $range;
		}
	}

	return false;
}

function spam4me_client_ip() {
	static $ip = null;

	if ( null !== $ip ) {
		return $ip;
	}

	$remote = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
	$ip     = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';

	$proxies = apply_filters( 'spam4me_trusted_proxies', spam4me_cloudflare_ranges() );

	if ( $ip && ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) && spam4me_ip_match( $ip, $proxies ) ) {
		$real = sanitize_text_field( wp_unslash( $_SERVER['HTTP_CF_CONNECTING_IP'] ) );

		if ( filter_var( $real, FILTER_VALIDATE_IP ) ) {
			$ip = $real;
		}
	}

	return $ip;
}

function spam4me_make_token( $time = null ) {
	$time = null === $time ? time() : (int) $time;

	return $time . '.' . substr( wp_hash( 's4m-token|' . $time, 'nonce' ), 0, 20 );
}

function spam4me_token_time( $token ) {
	if ( ! is_string( $token ) || ! preg_match( '/^(\d{9,11})\.([a-f0-9]{20})$/', $token, $m ) ) {
		return false;
	}

	return hash_equals( substr( wp_hash( 's4m-token|' . $m[1], 'nonce' ), 0, 20 ), $m[2] ) ? (int) $m[1] : false;
}
