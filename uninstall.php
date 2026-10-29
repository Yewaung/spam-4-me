<?php

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

global $wpdb;

$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}s4m_log" );

foreach ( array( 'spam_4_me', 'spam_4_me_db', 'spam_4_me_log', 's4m_activated' ) as $option ) {
	delete_option( $option );
}

wp_clear_scheduled_hook( 's4m_daily' );

delete_metadata( 'user', 0, 's4m_logs_per_page', '', true );

$wpdb->query( "DELETE FROM {$wpdb->options} WHERE option_name LIKE '\\_transient\\_s4m\\_rl\\_%' OR option_name LIKE '\\_transient\\_timeout\\_s4m\\_rl\\_%'" );
