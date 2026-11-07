<?php
/**
 * TheStickPress hub: one admin menu for all the 4 Me plugins.
 *
 * Every 4 Me plugin ships this same file; the first one loaded defines the
 * class and the others reuse it. Each plugin calls
 * TheStickPress_Hub_1::register() with its name, version, icon and pages, and
 * adds its pages under TheStickPress_Hub_1::SLUG. The hub adds the top menu and
 * an overview page, keeps one menu line per plugin (more pages become tabs) and
 * sends old addresses (Settings > Map 4 Me ...) to the new ones.
 *
 * Copy changes to every plugin unchanged; raise the class number for a change
 * that is not backward compatible.
 *
 * @package spam4me
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Every 4 Me plugin carries this file. The class is declared inside a
// condition so PHP never declares it while reading the file (early binding):
// a second copy, an opcode cache or files replaced by a restore cannot then
// cause "Cannot redeclare class".
if ( ! class_exists( 'TheStickPress_Hub_1', false ) ) :

	/**
	 * Shared admin menu of the 4 Me plugins.
	 */
	class TheStickPress_Hub_1 {

		/**
		 * Menu slug of the hub.
		 */
		const SLUG = 'thestickpress';

		/**
		 * Hub version.
		 */
		const VERSION = '1.0.0';

		/**
		 * Registered plugins: slug => details.
		 *
		 * @var array
		 */
		private static $plugins = array();

		/**
		 * Whether the hooks are added.
		 *
		 * @var bool
		 */
		private static $hooked = false;

		/**
		 * Register a plugin.
		 *
		 * @param array $plugin slug, name, version, description, color (hex), icon
		 *                      (path to an SVG file), pages (array of page slugs,
		 *                      first = menu entry), labels (page slug => tab label),
		 *                      file (main plugin file, for a "Settings" link),
		 *                      screens (post types whose edit screens use the
		 *                      TheStickPress look), help (page slug or "page:tab"
		 *                      => array( title, text, steps[] ) shown at the top).
		 */
		public static function register( $plugin ) {
			$plugin = wp_parse_args(
				$plugin,
				array(
					'slug'        => '',
					'name'        => '',
					'version'     => '',
					'description' => '',
					'color'       => '#1d2327',
					'icon'        => '',
					'pages'       => array(),
					'labels'      => array(),
					'file'        => '',
					'screens'     => array(),
					'help'        => array(),
				)
			);
			if ( '' === $plugin['slug'] || ! $plugin['pages'] ) {
				return;
			}
			self::$plugins[ $plugin['slug'] ] = $plugin;
			if ( $plugin['file'] ) {
				add_filter(
					'plugin_action_links_' . plugin_basename( $plugin['file'] ),
					function ( $links ) use ( $plugin ) {
						array_unshift( $links, '<a href="' . esc_url( admin_url( 'admin.php?page=' . $plugin['pages'][0] ) ) . '">' . esc_html__( 'Settings', 'spam4me' ) . '</a>' );
						return $links;
					}
				);
			}
			if ( self::$hooked ) {
				return;
			}
			self::$hooked = true;
			add_action( 'admin_menu', array( __CLASS__, 'menu' ), 5 );
			add_action( 'admin_menu', array( __CLASS__, 'tidy' ), 999 );
			add_filter( 'submenu_file', array( __CLASS__, 'submenu_file' ), 10, 2 );
			// WordPress refuses an unknown address before admin_init, so redirect there.
			add_action( 'admin_page_access_denied', array( __CLASS__, 'redirect_old' ), 1 );
			add_action( 'admin_head', array( __CLASS__, 'styles' ) );
			add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
			add_filter( 'admin_body_class', array( __CLASS__, 'body_class' ) );
			add_action( 'all_admin_notices', array( __CLASS__, 'intro' ), 1 );
		}

		/**
		 * Whether the current admin screen belongs to a 4 Me plugin.
		 *
		 * @return string Plugin slug or ''.
		 */
		public static function current_plugin() {
			$page = self::current_page();
			if ( self::SLUG === $page ) {
				return self::SLUG;
			}
			if ( $page && self::owner( $page ) ) {
				return self::owner( $page );
			}
			$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
			if ( $screen && $screen->post_type ) {
				foreach ( self::$plugins as $slug => $plugin ) {
					if ( in_array( $screen->post_type, (array) $plugin['screens'], true ) ) {
						return $slug;
					}
				}
			}
			return '';
		}

		/**
		 * The shared look and behaviour (about 13 KB of CSS and 10 KB of JS, 4 Me screens only).
		 */
		public static function assets() {
			if ( ! self::current_plugin() ) {
				return;
			}
			$dir = __DIR__;
			$url = plugin_dir_url( __FILE__ );
			wp_enqueue_style( 'tsp-hub', $url . 'hub.css', array(), self::VERSION . '.' . (string) filemtime( $dir . '/hub.css' ) );
			wp_enqueue_script(
				'tsp-hub',
				$url . 'hub.js',
				array(),
				self::VERSION . '.' . (string) filemtime( $dir . '/hub.js' ),
				array(
					'in_footer' => true,
					'strategy'  => 'defer',
				)
			);
			wp_localize_script(
				'tsp-hub',
				'tspHub',
				array(
					'unsaved'    => __( 'You have unsaved changes', 'spam4me' ),
					'save'       => __( 'Save changes', 'spam4me' ),
					'discard'    => __( 'Discard', 'spam4me' ),
					'remove'     => __( 'Remove', 'spam4me' ),
					'undo'       => __( 'Undo', 'spam4me' ),
					'removeNote' => __( 'Removed when you press Save.', 'spam4me' ),
					'locked'     => __( 'Built in', 'spam4me' ),
				)
			);
		}

		/**
		 * Body class for the 4 Me screens.
		 *
		 * @param string $classes Classes.
		 * @return string
		 */
		public static function body_class( $classes ) {
			$plugin = self::current_plugin();
			return $plugin ? $classes . ' tsp-admin tsp-admin--' . sanitize_html_class( $plugin ) : $classes;
		}

		/**
		 * A short plain-language card at the top of a page: what it is for and
		 * the steps to use it.
		 */
		public static function intro() {
			$slug = self::current_plugin();
			if ( ! $slug || self::SLUG === $slug ) {
				return;
			}
			$plugin = self::$plugins[ $slug ];
			$page   = self::current_page();
			$tab    = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
			$screen = get_current_screen();
			$keys   = array( $page . ':' . $tab, $page );
			if ( $screen ) {
				array_unshift( $keys, $screen->id );
			}
			if ( $page && '' === $tab ) {
				array_unshift( $keys, $page . ':' );
			}
			$help = null;
			foreach ( $keys as $key ) {
				if ( isset( $plugin['help'][ $key ] ) ) {
					$help = $plugin['help'][ $key ];
					break;
				}
			}
			if ( ! $help ) {
				return;
			}
			$help = wp_parse_args(
				$help,
				array(
					'title' => '',
					'text'  => '',
					'steps' => array(),
				)
			);
			echo '<div class="notice tsp-intro"><span class="tsp-intro__icon">' . self::icon( $plugin ) . '</span><div>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG passed through wp_kses() in icon().
			echo '<p class="tsp-intro__title">' . esc_html( $help['title'] ) . '</p>';
			echo '<p class="tsp-intro__text">' . esc_html( $help['text'] ) . '</p>';
			if ( $help['steps'] ) {
				echo '<ol class="tsp-intro__steps">';
				foreach ( (array) $help['steps'] as $step ) {
					echo '<li>' . esc_html( $step ) . '</li>';
				}
				echo '</ol>';
			}
			echo '</div></div>';
		}

		/**
		 * The plugin a page belongs to.
		 *
		 * @param string $page Page slug.
		 * @return string Plugin slug or ''.
		 */
		public static function owner( $page ) {
			foreach ( self::$plugins as $slug => $plugin ) {
				if ( in_array( $page, $plugin['pages'], true ) ) {
					return $slug;
				}
			}
			return '';
		}

		/**
		 * Current admin page slug.
		 *
		 * @return string
		 */
		private static function current_page() {
			return isset( $_GET['page'] ) ? sanitize_key( wp_unslash( $_GET['page'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only.
		}

		/**
		 * Top menu and overview page.
		 */
		public static function menu() {
			add_menu_page( 'TheStickPress', 'TheStickPress', 'manage_options', self::SLUG, array( __CLASS__, 'overview' ), self::menu_icon(), 58 );
			add_submenu_page( self::SLUG, __( 'TheStickPress plugins', 'spam4me' ), __( 'Overview', 'spam4me' ), 'manage_options', self::SLUG, array( __CLASS__, 'overview' ) );
		}

		/**
		 * Extra pages of the plugins (hidden from the menu, reached by tabs).
		 *
		 * @var array
		 */
		private static $hidden = array();

		/**
		 * One menu line per plugin, in name order, after the overview. Extra pages
		 * stay in the menu list (WordPress checks access against it) but are hidden
		 * with CSS and reached by tabs.
		 */
		public static function tidy() {
			global $submenu;
			if ( empty( $submenu[ self::SLUG ] ) ) {
				return;
			}
			$keep  = array();
			$first = array();
			foreach ( self::$plugins as $plugin ) {
				$first[ $plugin['pages'][0] ] = $plugin['name'];
			}
			foreach ( $submenu[ self::SLUG ] as $item ) {
				$page = $item[2];
				if ( self::SLUG === $page ) {
					$keep[' 0'] = $item;
				} elseif ( isset( $first[ $page ] ) ) {
					$item[0]                               = $first[ $page ];
					$keep[ strtolower( $first[ $page ] ) ] = $item;
				} elseif ( self::owner( $page ) ) {
					$owner = self::$plugins[ self::owner( $page ) ];
					$keep[ strtolower( $owner['name'] ) . ' ~' . $page ] = $item;
					self::$hidden[]                                      = $page;
				} else {
					$keep[ 'zz' . $page ] = $item;
				}
			}
			ksort( $keep );
			$submenu[ self::SLUG ] = array_values( $keep ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- reorder our own menu.
		}

		/**
		 * Highlight the plugin's menu line on its extra pages.
		 *
		 * @param string|null $submenu_file Current submenu file.
		 * @param string      $parent_file  Parent menu.
		 * @return string|null
		 */
		public static function submenu_file( $submenu_file, $parent_file ) {
			$owner = self::owner( self::current_page() );
			if ( self::SLUG === $parent_file && $owner ) {
				return self::$plugins[ $owner ]['pages'][0];
			}
			return $submenu_file;
		}

		/**
		 * Old addresses (options-general.php?page=..., edit.php?post_type=...&page=...)
		 * go to admin.php?page=... with the same query.
		 */
		public static function redirect_old() {
			global $pagenow;
			$page = self::current_page();
			if ( '' === $page || 'admin.php' === $pagenow || ! self::owner( $page ) || wp_doing_ajax() ) {
				return;
			}
			if ( ! in_array( $pagenow, array( 'options-general.php', 'tools.php', 'edit.php' ), true ) ) {
				return;
			}
			$args = array();
			foreach ( wp_unslash( $_GET ) as $key => $value ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- navigation only, values are re-encoded.
				if ( 'post_type' !== $key && is_scalar( $value ) ) {
					$args[ sanitize_key( $key ) ] = sanitize_text_field( (string) $value );
				}
			}
			wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
			exit;
		}

		/**
		 * Tabs for a plugin with more than one page. Call at the top of each page.
		 *
		 * @param string $slug Plugin slug.
		 */
		public static function tabs( $slug ) {
			if ( empty( self::$plugins[ $slug ] ) || count( self::$plugins[ $slug ]['pages'] ) < 2 ) {
				return;
			}
			$plugin  = self::$plugins[ $slug ];
			$current = self::current_page();
			echo '<nav class="nav-tab-wrapper tsp-tabs" aria-label="' . esc_attr( $plugin['name'] ) . '">';
			foreach ( $plugin['pages'] as $page ) {
				$label = isset( $plugin['labels'][ $page ] ) ? $plugin['labels'][ $page ] : $page;
				printf(
					'<a href="%1$s" class="nav-tab%2$s"%3$s>%4$s</a>',
					esc_url( admin_url( 'admin.php?page=' . $page ) ),
					$page === $current ? ' nav-tab-active' : '',
					$page === $current ? ' aria-current="page"' : '',
					esc_html( $label )
				);
			}
			echo '</nav>';
		}

		/**
		 * The 4ME mark as a data URI for the admin menu. WordPress recolours
		 * it to match the admin colour scheme.
		 *
		 * @return string
		 */
		public static function menu_icon() {
			$svg = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><g fill="black"><path d="M150.9 21.5 39.0 140.9 66.8 167.0 178.8 47.6Z"/><path d="M57.6 183.7 210.1 183.7 210.1 145.6 57.6 145.6Z"/><path d="M155.8 38.5 155.8 217.4 194.0 217.4 194.0 38.5Z"/><path fill-rule="evenodd" d="M144.1 23.8a30.8 30.8 0 1 0 61.6 0a30.8 30.8 0 1 0 -61.6 0ZM160.2 23.8a14.6 14.6 0 1 0 29.3 0a14.6 14.6 0 1 0 -29.3 0Z"/><path fill-rule="evenodd" d="M12.1 164.6a30.8 30.8 0 1 0 61.6 0a30.8 30.8 0 1 0 -61.6 0ZM28.2 164.6a14.6 14.6 0 1 0 29.3 0a14.6 14.6 0 1 0 -29.3 0Z"/><path fill-rule="evenodd" d="M194.0 164.6a30.8 30.8 0 1 0 61.6 0a30.8 30.8 0 1 0 -61.6 0ZM210.1 164.6a14.6 14.6 0 1 0 29.3 0a14.6 14.6 0 1 0 -29.3 0Z"/><path fill-rule="evenodd" d="M144.1 232.1a30.8 30.8 0 1 0 61.6 0a30.8 30.8 0 1 0 -61.6 0ZM160.2 232.1a14.6 14.6 0 1 0 29.3 0a14.6 14.6 0 1 0 -29.3 0Z"/></g></svg>';
			return 'data:image/svg+xml;base64,' . base64_encode( $svg ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- data URI for the menu icon.
		}

		/**
		 * Allowed SVG markup for plugin icons.
		 *
		 * @return array
		 */
		private static function svg_kses() {
			$common = array(
				'fill'      => true,
				'fill-rule' => true,
				'clip-rule' => true,
				'transform' => true,
				'opacity'   => true,
			);
			return array(
				'svg'            => array(
					'xmlns'       => true,
					'viewbox'     => true,
					'width'       => true,
					'height'      => true,
					'aria-hidden' => true,
					'focusable'   => true,
					'role'        => true,
				),
				'g'              => $common,
				'title'          => array(),
				'path'           => $common + array( 'd' => true ),
				'rect'           => $common + array(
					'x'      => true,
					'y'      => true,
					'width'  => true,
					'height' => true,
					'rx'     => true,
				),
				'circle'         => $common + array(
					'cx' => true,
					'cy' => true,
					'r'  => true,
				),
				// Gradients (the holographic icons).
				'defs'           => array(),
				'lineargradient' => array(
					'id'                => true,
					'x1'                => true,
					'y1'                => true,
					'x2'                => true,
					'y2'                => true,
					'gradientunits'     => true,
					'gradienttransform' => true,
				),
				'radialgradient' => array(
					'id'                => true,
					'cx'                => true,
					'cy'                => true,
					'r'                 => true,
					'fx'                => true,
					'fy'                => true,
					'gradientunits'     => true,
					'gradienttransform' => true,
				),
				'stop'           => array(
					'offset'       => true,
					'stop-color'   => true,
					'stop-opacity' => true,
				),
			);
		}

		/**
		 * A plugin's icon (its SVG file, or a coloured tile with the hub mark).
		 *
		 * @param array $plugin Plugin.
		 * @return string
		 */
		private static function icon( $plugin ) {
			if ( $plugin['icon'] && is_readable( $plugin['icon'] ) ) {
				$svg = (string) file_get_contents( $plugin['icon'] ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- local plugin file.
				$svg = preg_replace( '/<svg\b/', '<svg aria-hidden="true" focusable="false"', $svg, 1 );
				return wp_kses( $svg, self::svg_kses() );
			}
			return '<svg aria-hidden="true" focusable="false" viewBox="0 0 256 256"><rect width="256" height="256" rx="56" fill="' . esc_attr( $plugin['color'] ) . '"/><path fill="#fff" fill-rule="evenodd" transform="translate(32 32) scale(.75)" d="M51.3 168 116 56V200H96V168ZM76.4 148 96 114V148ZM116 56 164 139.1 212 56V200H192V130.5L164 179.1 116 96Z"/></svg>';
		}

		/**
		 * Overview: one card per plugin.
		 */
		public static function overview() {
			$plugins = self::$plugins;
			uasort(
				$plugins,
				function ( $a, $b ) {
					return strcasecmp( $a['name'], $b['name'] );
				}
			);
			echo '<div class="wrap tsp-hub"><h1>' . esc_html__( 'TheStickPress plugins', 'spam4me' ) . '</h1>';
			echo '<p class="tsp-lead">' . esc_html__( 'The 4 Me plugins installed on this site. Each one keeps its own settings; they share this menu.', 'spam4me' ) . '</p>';
			echo '<ul class="tsp-cards">';
			foreach ( $plugins as $plugin ) {
				echo '<li class="tsp-card">';
				echo '<span class="tsp-card__icon">' . self::icon( $plugin ) . '</span>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- SVG passed through wp_kses() or built from escaped values.
				echo '<span class="tsp-card__body"><strong class="tsp-card__name">' . esc_html( $plugin['name'] ) . '</strong>';
				/* translators: %s: version number. */
				echo '<span class="tsp-card__ver">' . esc_html( sprintf( __( 'Version %s', 'spam4me' ), $plugin['version'] ) ) . '</span>';
				echo '<span class="tsp-card__desc">' . esc_html( $plugin['description'] ) . '</span>';
				echo '<a class="button button-primary" href="' . esc_url( admin_url( 'admin.php?page=' . $plugin['pages'][0] ) ) . '">';
				/* translators: %s: plugin name. */
				echo esc_html( sprintf( __( 'Open %s', 'spam4me' ), $plugin['name'] ) ) . '</a></span>';
				echo '</li>';
			}
			echo '</ul></div>';
		}

		/**
		 * A few lines of CSS for the overview and tabs (hub pages only).
		 */
		public static function styles() {
			if ( self::$hidden ) {
				$rules = array();
				foreach ( self::$hidden as $page ) {
					$rules[] = '#toplevel_page_' . self::SLUG . ' .wp-submenu li:has(> a[href="admin.php?page=' . sanitize_key( $page ) . '"])';
				}
				echo '<style id="tsp-hub-menu">' . implode( ',', $rules ) . '{display:none}</style>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- selectors built from sanitize_key() slugs.
			}
			$page = self::current_page();
			if ( self::SLUG !== $page && ! self::owner( $page ) ) {
				return;
			}
			echo '<style id="tsp-hub-css">'
			. '.tsp-lead{max-width:680px;color:#50575e}'
			. '.tsp-cards{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:16px;margin:20px 0 0;padding:0;list-style:none}'
			. '.tsp-card{display:flex;gap:16px;align-items:flex-start;margin:0;padding:20px;background:#fff;border:1px solid #dcdcde;border-radius:12px}'
			. '.tsp-card__icon svg{display:block;width:56px;height:56px}'
			. '.tsp-card__body{display:flex;flex-direction:column;align-items:flex-start;gap:4px;min-width:0}'
			. '.tsp-card__name{font-size:15px}.tsp-card__ver{color:#646970;font-size:12px}'
			. '.tsp-card__desc{margin:2px 0 10px;color:#3c434a}'
			. '.tsp-tabs{margin:8px 0 16px}'
			. '</style>';
		}
	}

endif;
