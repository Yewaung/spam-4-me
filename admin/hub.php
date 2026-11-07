<?php
/**
 * Spam 4 Me in the shared TheStickPress admin menu.
 *
 * @package spam4me
 */

defined( 'ABSPATH' ) || exit;

/**
 * Join the TheStickPress admin menu.
 */
function s4m_hub() {
	if ( class_exists( 'TheStickPress_Hub_1' ) ) {
		TheStickPress_Hub_1::register(
			array(
				'slug'        => 'spam4me',
				'name'        => __( 'Spam 4 Me', 'spam4me' ),
				'version'     => S4M_VERSION,
				'description' => __( 'Blocks spam in comments and forms (Contact Form 7, WPForms, Gravity Forms, Ninja Forms, Fluent Forms) without captchas.', 'spam4me' ),
				'color'       => '#e11d48',
				'icon'        => S4M_DIR . 'assets/icon.svg',
				'pages'       => array( 'spam-4-me', 'spam-4-me-settings', 'spam-4-me-test' ),
				'screens'     => array(),
				'help'        => array(
					'spam-4-me'          => array(
						'title' => __( 'Messages that were stopped', 'spam4me' ),
						'text'  => __( 'Every message Spam 4 Me blocked, with the reason. If a real person was blocked by a word, open the entry and allow that word. Entries are deleted automatically after the number of days set in Settings.', 'spam4me' ),
						'steps' => array(
							__( 'Look at the reason', 'spam4me' ),
							__( 'Open an entry to see the message', 'spam4me' ),
							__( 'Allow the word, block the sender or delete the entry', 'spam4me' ),
						),
					),
					'spam-4-me-settings' => array(
						'title' => __( 'How strict the spam filter is', 'spam4me' ),
						'text'  => __( 'The defaults work for most sites. Each line explains what it stops. Nothing changes until you press Save.', 'spam4me' ),
						'steps' => array(
							__( 'Leave the defaults or adjust a line', 'spam4me' ),
							__( 'Press Save changes', 'spam4me' ),
							__( 'Try it on the "Test a message" tab', 'spam4me' ),
						),
					),
					'spam-4-me-test'     => array(
						'title' => __( 'Try a message before visitors do', 'spam4me' ),
						'text'  => __( 'Paste a message to see whether it would get through with your current settings. Nothing is sent or saved.', 'spam4me' ),
						'steps' => array(
							__( 'Type or paste a message', 'spam4me' ),
							__( 'Press Test message', 'spam4me' ),
							__( 'Adjust the settings if needed', 'spam4me' ),
						),
					),
				),
				'labels'      => array(
					'spam-4-me'          => __( 'Blocked log', 'spam4me' ),
					'spam-4-me-settings' => __( 'Settings', 'spam4me' ),
					'spam-4-me-test'     => __( 'Test a message', 'spam4me' ),
				),
			)
		);
	}
}
add_action( 'init', 's4m_hub' );
