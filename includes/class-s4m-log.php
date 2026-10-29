<?php

defined( 'ABSPATH' ) || exit;

class S4M_Log {
	const DB_VERSION = '1';

	public static function init() {
		add_action( 'plugins_loaded', array( __CLASS__, 'maybe_upgrade' ) );
		add_action( 's4m_daily', array( __CLASS__, 'purge' ) );
		add_filter( 'wp_privacy_personal_data_exporters', array( __CLASS__, 'register_exporter' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( __CLASS__, 'register_eraser' ) );
	}

	public static function table() {
		global $wpdb;

		return $wpdb->prefix . 's4m_log';
	}

	public static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = self::table();
		$charset = $wpdb->get_charset_collate();

		dbDelta(
			"CREATE TABLE {$table} (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				created_at datetime NOT NULL,
				form_id bigint(20) unsigned NOT NULL DEFAULT 0,
				form_title varchar(200) NOT NULL DEFAULT '',
				reason varchar(32) NOT NULL DEFAULT '',
				detail varchar(255) NOT NULL DEFAULT '',
				category varchar(32) NOT NULL DEFAULT '',
				field varchar(100) NOT NULL DEFAULT '',
				ip varchar(45) NOT NULL DEFAULT '',
				email varchar(190) NOT NULL DEFAULT '',
				user_agent varchar(255) NOT NULL DEFAULT '',
				page_url varchar(255) NOT NULL DEFAULT '',
				content text NULL,
				PRIMARY KEY  (id),
				KEY created_at (created_at),
				KEY reason (reason),
				KEY ip (ip),
				KEY email (email)
			) {$charset};"
		);

		update_option( 'spam_4_me_db', self::DB_VERSION );
		add_option( 's4m_activated', time() );
		delete_option( 'spam_4_me_log' );

		if ( ! wp_next_scheduled( 's4m_daily' ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', 's4m_daily' );
		}
	}

	public static function maybe_upgrade() {
		if ( self::DB_VERSION !== get_option( 'spam_4_me_db' ) ) {
			self::install();
		}
	}

	public static function reasons() {
		static $reasons = null;

		if ( null !== $reasons ) {
			return $reasons;
		}

		$reasons = array(
			'blocked_ip'       => array(
				'label' => __( 'Blocked IP', 'spam-4-me' ),
				'help'  => __( 'The sender\'s IP address is on your block list.', 'spam-4-me' ),
			),
			'honeypot'         => array(
				'label' => __( 'Bot trap', 'spam-4-me' ),
				'help'  => __( 'A hidden field that people cannot see was filled in. Only bots do this.', 'spam-4-me' ),
			),
			'no_js'            => array(
				'label' => __( 'Browser check failed', 'spam-4-me' ),
				'help'  => __( 'The form was sent without running JavaScript, as bots that post forms directly do.', 'spam-4-me' ),
			),
			'too_fast'         => array(
				'label' => __( 'Sent too fast', 'spam-4-me' ),
				'help'  => __( 'The form was sent faster than a person can fill it in.', 'spam-4-me' ),
			),
			'rate_limit'       => array(
				'label' => __( 'Too many messages', 'spam-4-me' ),
				'help'  => __( 'Too many messages were sent from this IP address within an hour.', 'spam-4-me' ),
			),
			'disposable_email' => array(
				'label' => __( 'Throwaway email', 'spam-4-me' ),
				'help'  => __( 'The email address uses a temporary, throwaway email service.', 'spam-4-me' ),
			),
			'script'           => array(
				'label' => __( 'Blocked language', 'spam-4-me' ),
				'help'  => __( 'The message is written in a script (alphabet) you chose to block.', 'spam-4-me' ),
			),
			'keyword'          => array(
				'label' => __( 'Banned word', 'spam-4-me' ),
				'help'  => __( 'The message contains a banned word or phrase.', 'spam-4-me' ),
			),
			'link'             => array(
				'label' => __( 'Link in message', 'spam-4-me' ),
				'help'  => __( 'The message contains a web link. The visitor was asked to remove it.', 'spam-4-me' ),
			),
		);

		return $reasons;
	}

	public static function describe( $row ) {
		$row    = (array) $row;
		$detail = isset( $row['detail'] ) ? (string) $row['detail'] : '';

		if ( 'script' === $row['reason'] ) {
			$labels = s4m_script_labels();

			return isset( $labels[ $detail ] ) ? $labels[ $detail ] : $detail;
		}

		if ( 'keyword' === $row['reason'] ) {
			$labels   = s4m_keyword_labels();
			$category = isset( $row['category'] ) ? $row['category'] : '';
			$detail   = rtrim( $detail, '*' );

			return isset( $labels[ $category ] ) ? sprintf( '"%s" (%s)', $detail, $labels[ $category ] ) : sprintf( '"%s"', $detail );
		}

		return $detail;
	}

	public static function add( array $entry ) {
		global $wpdb;

		$entry  = wp_parse_args(
			$entry,
			array(
				'form_id'    => 0,
				'form_title' => '',
				'reason'     => '',
				'detail'     => '',
				'category'   => '',
				'field'      => '',
				'ip'         => '',
				'email'      => '',
				'user_agent' => '',
				'page_url'   => '',
				'content'    => null,
			)
		);
		$limits = array(
			'form_title' => 200,
			'reason'     => 32,
			'detail'     => 255,
			'category'   => 32,
			'field'      => 100,
			'ip'         => 45,
			'email'      => 190,
			'user_agent' => 255,
			'page_url'   => 255,
		);

		$data = array(
			'created_at' => current_time( 'mysql', true ),
			'form_id'    => absint( $entry['form_id'] ),
		);

		foreach ( $limits as $column => $length ) {
			$data[ $column ] = mb_substr( (string) $entry[ $column ], 0, $length );
		}

		$data['content'] = $entry['content'];

		$wpdb->insert( self::table(), $data, array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s' ) );
	}

	public static function get( $id ) {
		global $wpdb;

		$table = self::table();

		return $wpdb->get_row( $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $id ) );
	}

	public static function delete( array $ids ) {
		global $wpdb;

		$ids = array_filter( array_map( 'absint', $ids ) );

		if ( ! $ids ) {
			return 0;
		}

		$table        = self::table();
		$placeholders = implode( ',', array_fill( 0, count( $ids ), '%d' ) );

		return (int) $wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE id IN ({$placeholders})", $ids ) );
	}

	public static function clear() {
		global $wpdb;

		$table = self::table();

		$wpdb->query( "TRUNCATE TABLE {$table}" );
	}

	public static function purge() {
		global $wpdb;

		$settings = s4m_settings();
		$table    = self::table();
		$cutoff   = gmdate( 'Y-m-d H:i:s', time() - max( 1, absint( $settings['retention_days'] ) ) * DAY_IN_SECONDS );

		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}

	public static function summary() {
		global $wpdb;

		$table = self::table();
		$now   = time();

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(created_at >= %s), 0) AS day, COALESCE(SUM(created_at >= %s), 0) AS week, COUNT(*) AS month FROM {$table} WHERE created_at >= %s",
				gmdate( 'Y-m-d H:i:s', $now - DAY_IN_SECONDS ),
				gmdate( 'Y-m-d H:i:s', $now - 7 * DAY_IN_SECONDS ),
				gmdate( 'Y-m-d H:i:s', $now - 30 * DAY_IN_SECONDS )
			)
		);

		return array(
			'day'   => (int) $row->day,
			'week'  => (int) $row->week,
			'month' => (int) $row->month,
		);
	}

	public static function reason_counts() {
		global $wpdb;

		$table = self::table();

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT reason, COUNT(*) AS total FROM {$table} WHERE created_at >= %s GROUP BY reason ORDER BY total DESC", gmdate( 'Y-m-d H:i:s', time() - 30 * DAY_IN_SECONDS ) ) );

		return wp_list_pluck( $rows, 'total', 'reason' );
	}

	public static function register_exporter( $exporters ) {
		$exporters['spam-4-me'] = array(
			'exporter_friendly_name' => __( 'Spam 4 Me blocked messages', 'spam-4-me' ),
			'callback'               => array( __CLASS__, 'export' ),
		);

		return $exporters;
	}

	public static function register_eraser( $erasers ) {
		$erasers['spam-4-me'] = array(
			'eraser_friendly_name' => __( 'Spam 4 Me blocked messages', 'spam-4-me' ),
			'callback'             => array( __CLASS__, 'erase' ),
		);

		return $erasers;
	}

	public static function export( $email ) {
		global $wpdb;

		$table = self::table();

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE email = %s", $email ) );
		$data = array();

		foreach ( $rows as $row ) {
			$data[] = array(
				'group_id'    => 'spam-4-me',
				'group_label' => __( 'Blocked form messages', 'spam-4-me' ),
				'item_id'     => 's4m-' . $row->id,
				'data'        => array(
					array(
						'name'  => __( 'Date', 'spam-4-me' ),
						'value' => $row->created_at,
					),
					array(
						'name'  => __( 'Form', 'spam-4-me' ),
						'value' => $row->form_title,
					),
					array(
						'name'  => __( 'IP address', 'spam-4-me' ),
						'value' => $row->ip,
					),
					array(
						'name'  => __( 'Message', 'spam-4-me' ),
						'value' => (string) $row->content,
					),
				),
			);
		}

		return array(
			'data' => $data,
			'done' => true,
		);
	}

	public static function erase( $email ) {
		global $wpdb;

		$deleted = (int) $wpdb->delete( self::table(), array( 'email' => $email ), array( '%s' ) );

		return array(
			'items_removed'  => $deleted > 0,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}
}
