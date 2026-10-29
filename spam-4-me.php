<?php
/**
 * Plugin Name: Spam 4 Me
 * Description: Spam protection for Contact Form 7, WPForms, Gravity Forms, Ninja Forms and Fluent Forms: invisible bot checks, banned words in many languages, link and language blocking, a locked autoreply subject, and a log that shows exactly why each message was blocked.
 * Version: 2.2.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: 4 Me
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: spam-4-me
 */

defined( 'ABSPATH' ) || exit;

define( 'S4M_VERSION', '2.2.0' );
define( 'S4M_FILE', __FILE__ );
define( 'S4M_DIR', plugin_dir_path( __FILE__ ) );
define( 'S4M_URL', plugin_dir_url( __FILE__ ) );
define( 'S4M_OPTION', 'spam_4_me' );

require_once S4M_DIR . 'includes/keywords.php';
require_once S4M_DIR . 'includes/settings.php';
require_once S4M_DIR . 'includes/detect.php';
require_once S4M_DIR . 'includes/class-s4m-log.php';
require_once S4M_DIR . 'includes/forms.php';
require_once S4M_DIR . 'includes/cf7.php';
require_once S4M_DIR . 'includes/wpforms.php';
require_once S4M_DIR . 'includes/gravityforms.php';
require_once S4M_DIR . 'includes/ninja-forms.php';
require_once S4M_DIR . 'includes/fluent-forms.php';

S4M_Log::init();

if ( is_admin() ) {
	require_once S4M_DIR . 'admin/class-s4m-admin.php';
	S4M_Admin::init();
}

register_activation_hook( __FILE__, array( 'S4M_Log', 'install' ) );

function s4m_deactivate() {
	wp_clear_scheduled_hook( 's4m_daily' );
}
register_deactivation_hook( __FILE__, 's4m_deactivate' );
