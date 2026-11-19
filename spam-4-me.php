<?php
/**
 * Plugin Name: Spam 4 Me
 * Plugin URI: https://www.thestickpress.com/plugins/spam4me/
 * Description: Spam protection for Contact Form 7, WPForms, Gravity Forms, Ninja Forms and Fluent Forms: invisible bot checks, banned words in many languages, link and language blocking, a locked autoreply subject, and a log that shows exactly why each message was blocked.
 * Version: 2.5.1
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: TheStickPress
 * Author URI: https://www.thestickpress.com/
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: spam4me
 */

defined( 'ABSPATH' ) || exit;

define( 'SPAM4ME_VERSION', '2.5.1' );
define( 'SPAM4ME_FILE', __FILE__ );
define( 'SPAM4ME_DIR', plugin_dir_path( __FILE__ ) );
define( 'SPAM4ME_URL', plugin_dir_url( __FILE__ ) );
define( 'SPAM4ME_OPTION', 'spam_4_me' );

/**
 * Version 2.5.0 renamed the short "s4m_" prefix to "spam4me_", so names stay unique next to other
 * plugins. Saved settings move to the new names once; nothing else changes for the site.
 */
function spam4me_move_old_options() {
	if ( get_option( 'spam4me_options_moved' ) ) {
		return;
	}
	$autoloaded = wp_load_alloptions();
	$moves      = array(
		's4m_activated' => 'spam4me_activated',
	);
	foreach ( $moves as $old_name => $new_name ) {
		$value = get_option( $old_name, null );
		if ( null === $value ) {
			continue;
		}
		if ( null === get_option( $new_name, null ) ) {
			add_option( $new_name, $value, '', isset( $autoloaded[ $old_name ] ) ? 'yes' : 'no' );
		}
		delete_option( $old_name );
	}
	add_option( 'spam4me_options_moved', 1 );
}
spam4me_move_old_options();

require_once SPAM4ME_DIR . 'includes/keywords.php';
require_once SPAM4ME_DIR . 'includes/settings.php';
require_once SPAM4ME_DIR . 'includes/class-thestickpress-client-ip-1.php';
require_once SPAM4ME_DIR . 'includes/detect.php';
require_once SPAM4ME_DIR . 'includes/class-spam4me-log.php';
require_once SPAM4ME_DIR . 'includes/forms.php';
require_once SPAM4ME_DIR . 'includes/cf7.php';
require_once SPAM4ME_DIR . 'includes/wpforms.php';
require_once SPAM4ME_DIR . 'includes/gravityforms.php';
require_once SPAM4ME_DIR . 'includes/ninja-forms.php';
require_once SPAM4ME_DIR . 'includes/fluent-forms.php';

Spam4me_Log::init();

if ( is_admin() ) {
	if ( ! class_exists( 'TheStickPress_Hub_1', false ) ) {
		require_once SPAM4ME_DIR . 'hub/class-thestickpress-hub-1.php';
	}
	require_once SPAM4ME_DIR . 'admin/class-spam4me-admin.php';
	require_once SPAM4ME_DIR . 'admin/hub.php';
	Spam4me_Admin::init();
}

register_activation_hook( __FILE__, array( 'Spam4me_Log', 'install' ) );

function spam4me_deactivate() {
	wp_clear_scheduled_hook( 's4m_daily' );
}
register_deactivation_hook( __FILE__, 'spam4me_deactivate' );
