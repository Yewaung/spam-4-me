<?php
/**
 * TheStickPress Client IP 1: the visitor's real IP address, shared by the
 * 4 Me plugins that rate limit or check visitors (Form 4 Me, Map 4 Me,
 * Spam 4 Me, Tune 4 Me).
 *
 * Behind Cloudflare every visitor reaches the site from a few Cloudflare
 * addresses, so REMOTE_ADDR alone would make all of them share one limit.
 * The CF-Connecting-IP header is trusted only when the request really comes
 * from a Cloudflare address; anyone connecting to the server directly cannot
 * fake it. Same rule as Spam 4 Me.
 *
 * Other proxies: add their addresses or ranges with the filter
 * `thestickpress_trusted_proxies` (array of IPs or CIDR ranges). The header
 * they send is filtered with `thestickpress_client_ip_header` (a $_SERVER
 * key, default HTTP_CF_CONNECTING_IP). For a list header such as
 * X-Forwarded-For the visitor is the rightmost address that is not a trusted
 * proxy: the leftmost entries are whatever the visitor chose to send.
 *
 * Rate limits use key(), which counts an IPv6 visitor by its /64 network:
 * one connection usually owns a whole /64, so counting single addresses
 * would give an attacker endless fresh tries.
 *
 * Every copy of this file must stay identical. The first copy loaded wins;
 * a change in behaviour needs a new class name (_2).
 *
 * @package thestickpress
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'TheStickPress_Client_IP_1', false ) ) :

	/**
	 * Visitor IP with trusted proxy support.
	 */
	class TheStickPress_Client_IP_1 {

		/**
		 * Cached answer for this request.
		 *
		 * @var string|null
		 */
		private static $ip = null;

		/**
		 * The visitor's IP, or '' when it is not a valid address.
		 *
		 * @return string
		 */
		public static function get() {
			if ( null !== self::$ip ) {
				return self::$ip;
			}
			$remote  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
			$ip      = filter_var( $remote, FILTER_VALIDATE_IP ) ? $remote : '';
			$header  = (string) apply_filters( 'thestickpress_client_ip_header', 'HTTP_CF_CONNECTING_IP' );
			$proxies = (array) apply_filters( 'thestickpress_trusted_proxies', self::cloudflare() );
			if ( $ip && '' !== $header && ! empty( $_SERVER[ $header ] ) && is_string( $_SERVER[ $header ] ) && self::match( $ip, $proxies ) ) {
				// Walk from the right: each trusted proxy added the address in
				// front of it, so the first untrusted one is the visitor.
				$list = array_reverse( array_map( 'trim', explode( ',', sanitize_text_field( wp_unslash( $_SERVER[ $header ] ) ) ) ) );
				foreach ( $list as $entry ) {
					if ( ! filter_var( $entry, FILTER_VALIDATE_IP ) ) {
						break;
					}
					$ip = $entry;
					if ( ! self::match( $entry, $proxies ) ) {
						break;
					}
				}
			}
			self::$ip = $ip;
			return $ip;
		}

		/**
		 * The visitor for rate limits: an IPv4 address, or the /64 network of an
		 * IPv6 address. '' when there is no valid address.
		 *
		 * @return string
		 */
		public static function key() {
			$ip = self::get();
			if ( '' === $ip || false === strpos( $ip, ':' ) ) {
				return $ip;
			}
			$bin = inet_pton( $ip );
			if ( false === $bin || 16 !== strlen( $bin ) ) {
				return $ip;
			}
			return inet_ntop( substr( $bin, 0, 8 ) . str_repeat( "\0", 8 ) ) . '/64';
		}

		/**
		 * Cloudflare's published ranges (https://www.cloudflare.com/ips/, checked 2026-10-21).
		 *
		 * @return string[]
		 */
		public static function cloudflare() {
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

		/**
		 * Is the IP inside one of the ranges?
		 *
		 * @param string $ip     Address.
		 * @param array  $ranges IPs or CIDR ranges.
		 * @return bool
		 */
		public static function match( $ip, array $ranges ) {
			foreach ( $ranges as $range ) {
				if ( is_string( $range ) && self::in_range( $ip, $range ) ) {
					return true;
				}
			}
			return false;
		}

		/**
		 * Is the IP inside one range (IPv4 or IPv6)?
		 *
		 * @param string $ip    Address.
		 * @param string $range IP or CIDR range.
		 * @return bool
		 */
		public static function in_range( $ip, $range ) {
			$range = trim( $range );
			if ( ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
				return false;
			}
			if ( false === strpos( $range, '/' ) ) {
				return filter_var( $range, FILTER_VALIDATE_IP ) && inet_pton( $ip ) === inet_pton( $range );
			}
			list( $subnet, $bits ) = explode( '/', $range, 2 );
			if ( ! filter_var( $subnet, FILTER_VALIDATE_IP ) || ! ctype_digit( $bits ) ) {
				return false;
			}
			$ip_bin     = inet_pton( $ip );
			$subnet_bin = inet_pton( $subnet );
			$bits       = (int) $bits;
			if ( strlen( $ip_bin ) !== strlen( $subnet_bin ) || $bits > 8 * strlen( $ip_bin ) ) {
				return false;
			}
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
	}

endif;
