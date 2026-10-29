<?php

defined( 'ABSPATH' ) || exit;

class S4M_Admin {
	const CAP = 'manage_options';

	private static $log_hook = '';

	public static function init() {
		add_action( 'admin_menu', array( __CLASS__, 'menu' ) );
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_init', array( __CLASS__, 'privacy_policy' ) );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'assets' ) );
		add_filter( 'set_screen_option_s4m_logs_per_page', array( __CLASS__, 'save_per_page' ), 10, 3 );
		add_filter( 'plugin_action_links_' . plugin_basename( S4M_FILE ), array( __CLASS__, 'action_links' ) );
	}

	public static function menu() {
		self::$log_hook = add_menu_page(
			__( 'Spam 4 Me', 'spam-4-me' ),
			__( 'Spam 4 Me', 'spam-4-me' ),
			self::CAP,
			'spam-4-me',
			array( __CLASS__, 'render_log' ),
			'dashicons-shield',
			31
		);

		add_submenu_page( 'spam-4-me', __( 'Blocked Log', 'spam-4-me' ), __( 'Blocked Log', 'spam-4-me' ), self::CAP, 'spam-4-me', array( __CLASS__, 'render_log' ) );
		add_submenu_page( 'spam-4-me', __( 'Spam 4 Me Settings', 'spam-4-me' ), __( 'Settings', 'spam-4-me' ), self::CAP, 'spam-4-me-settings', array( __CLASS__, 'render_settings' ) );
		add_submenu_page( 'spam-4-me', __( 'Test a Message', 'spam-4-me' ), __( 'Test a Message', 'spam-4-me' ), self::CAP, 'spam-4-me-test', array( __CLASS__, 'render_test' ) );

		add_action( 'load-' . self::$log_hook, array( __CLASS__, 'load_log_page' ) );
	}

	public static function assets( $hook ) {
		if ( false !== strpos( $hook, 'spam-4-me' ) ) {
			wp_enqueue_style( 'spam-4-me-admin', S4M_URL . 'assets/admin.css', array(), S4M_VERSION );
		}
	}

	public static function action_links( $links ) {
		array_unshift( $links, sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=spam-4-me-settings' ) ), esc_html__( 'Settings', 'spam-4-me' ) ) );

		return $links;
	}

	public static function privacy_policy() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}

		$settings = s4m_settings();

		wp_add_privacy_policy_content(
			__( 'Spam 4 Me', 'spam-4-me' ),
			wp_kses_post(
				wpautop(
					sprintf(
						__( 'When a message sent through a form on this website is blocked as spam, we store the date, the form used, the IP address, the email address, the browser identification and the text entered, so that we can check whether it was blocked by mistake. This information is deleted automatically after %d days and is not shared with anyone.', 'spam-4-me' ),
						max( 1, absint( $settings['retention_days'] ) )
					)
				)
			)
		);
	}

	public static function save_per_page( $status, $option, $value ) {
		return min( 200, max( 1, absint( $value ) ) );
	}

	public static function log_url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => 'spam-4-me' ), $args ), admin_url( 'admin.php' ) );
	}

	public static function action_url( $op, $id ) {
		return wp_nonce_url( self::log_url( array( 's4m_action' => $op, 'id' => absint( $id ) ) ), 's4m_' . $op . '_' . absint( $id ) );
	}

	public static function reason_badge( $row ) {
		$row     = (array) $row;
		$reasons = S4M_Log::reasons();
		$reason  = isset( $reasons[ $row['reason'] ] ) ? $reasons[ $row['reason'] ] : array(
			'label' => $row['reason'],
			'help'  => '',
		);
		$detail  = S4M_Log::describe( $row );
		$field   = isset( $row['field'] ) ? (string) $row['field'] : '';

		$html = sprintf( '<span class="s4m-badge s4m-badge--%1$s" title="%2$s">%3$s</span>', esc_attr( $row['reason'] ), esc_attr( $reason['help'] ), esc_html( $reason['label'] ) );

		if ( '' !== $detail ) {
			$html .= ' <span class="s4m-detail">' . esc_html( $detail ) . '</span>';
		}

		if ( '' !== $field ) {
			$html .= '<br><span class="s4m-muted">' . esc_html( sprintf( __( 'in field: %s', 'spam-4-me' ), $field ) ) . '</span>';
		}

		return $html;
	}

	public static function load_log_page() {
		if ( ! current_user_can( self::CAP ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'spam-4-me' ) );
		}

		add_screen_option(
			'per_page',
			array(
				'default' => 20,
				'option'  => 's4m_logs_per_page',
			)
		);

		$notice = '';

		if ( isset( $_GET['s4m_action'], $_GET['id'] ) ) {
			$op  = sanitize_key( wp_unslash( $_GET['s4m_action'] ) );
			$id  = absint( $_GET['id'] );
			check_admin_referer( 's4m_' . $op . '_' . $id );
			$row = S4M_Log::get( $id );

			if ( $row ) {
				switch ( $op ) {
					case 'delete':
						S4M_Log::delete( array( $id ) );
						$notice = 'deleted';
						break;

					case 'block_ip':
						if ( '' !== $row->ip ) {
							s4m_add_setting_line( 'blocked_ips', $row->ip );
							$notice = 'ip_blocked';
						}
						break;

					case 'allow_term':
						if ( 'keyword' === $row->reason && '' !== $row->detail ) {
							s4m_add_setting_line( 'allowlist', rtrim( $row->detail, '*' ) );
							$notice = 'allowed';
						}
						break;
				}
			}
		}

		$bulk = '';
		foreach ( array( 'action', 'action2' ) as $key ) {
			if ( isset( $_REQUEST[ $key ] ) && 'delete' === $_REQUEST[ $key ] ) {
				$bulk = 'delete';
			}
		}

		if ( 'delete' === $bulk && ! empty( $_REQUEST['ids'] ) ) {
			check_admin_referer( 'bulk-s4m_logs' );
			S4M_Log::delete( array_map( 'absint', (array) wp_unslash( $_REQUEST['ids'] ) ) );
			$notice = 'deleted';
		}

		if ( isset( $_POST['s4m_clear_log'] ) ) {
			check_admin_referer( 's4m_clear_log' );
			S4M_Log::clear();
			$notice = 'cleared';
		}

		if ( '' !== $notice ) {
			wp_safe_redirect( self::log_url( array( 's4m_notice' => $notice ) ) );
			exit;
		}
	}

	private static function action_notice() {
		$notice   = isset( $_GET['s4m_notice'] ) ? sanitize_key( wp_unslash( $_GET['s4m_notice'] ) ) : '';
		$messages = array(
			'deleted'    => __( 'Log entries deleted.', 'spam-4-me' ),
			'cleared'    => __( 'The log has been cleared.', 'spam-4-me' ),
			'ip_blocked' => __( 'IP address added to the block list. All future messages from it will be rejected.', 'spam-4-me' ),
			'allowed'    => __( 'Word added to the allow list. It will no longer block messages.', 'spam-4-me' ),
		);

		if ( isset( $messages[ $notice ] ) ) {
			printf( '<div class="notice notice-success is-dismissible"><p>%s</p></div>', esc_html( $messages[ $notice ] ) );
		}
	}

	public static function render_log() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$view = isset( $_GET['view'] ) ? absint( $_GET['view'] ) : 0;

		if ( $view ) {
			self::render_detail( $view );
			return;
		}

		require_once S4M_DIR . 'admin/class-s4m-log-table.php';

		$table = new S4M_Log_Table();
		$table->prepare_items();

		$summary = S4M_Log::summary();
		$counts  = S4M_Log::reason_counts();
		$reasons = S4M_Log::reasons();
		?>
		<div class="wrap s4m-wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Spam 4 Me — Blocked Log', 'spam-4-me' ); ?></h1>
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=spam-4-me-test' ) ); ?>" class="page-title-action"><?php esc_html_e( 'Test a Message', 'spam-4-me' ); ?></a>
			<hr class="wp-header-end">
			<?php self::action_notice(); ?>

			<div class="s4m-cards">
				<div class="s4m-card"><span class="s4m-card__num"><?php echo esc_html( number_format_i18n( $summary['day'] ) ); ?></span><span class="s4m-card__label"><?php esc_html_e( 'Blocked in the last 24 hours', 'spam-4-me' ); ?></span></div>
				<div class="s4m-card"><span class="s4m-card__num"><?php echo esc_html( number_format_i18n( $summary['week'] ) ); ?></span><span class="s4m-card__label"><?php esc_html_e( 'Last 7 days', 'spam-4-me' ); ?></span></div>
				<div class="s4m-card"><span class="s4m-card__num"><?php echo esc_html( number_format_i18n( $summary['month'] ) ); ?></span><span class="s4m-card__label"><?php esc_html_e( 'Last 30 days', 'spam-4-me' ); ?></span></div>
				<div class="s4m-card s4m-card--wide">
					<span class="s4m-card__label"><?php esc_html_e( 'Top reasons (30 days)', 'spam-4-me' ); ?></span>
					<?php if ( $counts ) : ?>
						<ul class="s4m-reasons">
							<?php foreach ( array_slice( $counts, 0, 5, true ) as $key => $total ) : ?>
								<li><a href="<?php echo esc_url( self::log_url( array( 'reason' => $key ) ) ); ?>"><?php echo esc_html( isset( $reasons[ $key ] ) ? $reasons[ $key ]['label'] : $key ); ?></a> <strong><?php echo esc_html( number_format_i18n( $total ) ); ?></strong></li>
							<?php endforeach; ?>
						</ul>
					<?php else : ?>
						<p class="s4m-muted"><?php esc_html_e( 'No blocks yet.', 'spam-4-me' ); ?></p>
					<?php endif; ?>
				</div>
			</div>

			<p class="description"><?php esc_html_e( 'Every message stopped by Spam 4 Me is listed here with the exact reason. If a real person was blocked, open the entry and choose "Allow this word".', 'spam-4-me' ); ?></p>

			<form method="get">
				<input type="hidden" name="page" value="spam-4-me">
				<?php
				$table->search_box( __( 'Search log', 'spam-4-me' ), 's4m-search' );
				$table->display();
				?>
			</form>

			<?php if ( $table->has_items() ) : ?>
				<form method="post" class="s4m-clear">
					<?php wp_nonce_field( 's4m_clear_log' ); ?>
					<button type="submit" name="s4m_clear_log" value="1" class="button button-link-delete" onclick="return confirm( this.dataset.confirm );" data-confirm="<?php esc_attr_e( 'Delete every log entry?', 'spam-4-me' ); ?>"><?php esc_html_e( 'Clear entire log', 'spam-4-me' ); ?></button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	private static function render_detail( $id ) {
		$row     = S4M_Log::get( $id );
		$reasons = S4M_Log::reasons();
		?>
		<div class="wrap s4m-wrap">
			<h1><?php esc_html_e( 'Blocked message', 'spam-4-me' ); ?></h1>
			<p><a href="<?php echo esc_url( self::log_url() ); ?>">&larr; <?php esc_html_e( 'Back to log', 'spam-4-me' ); ?></a></p>

			<?php if ( ! $row ) : ?>
				<p><?php esc_html_e( 'This entry no longer exists.', 'spam-4-me' ); ?></p>
			<?php else : ?>
				<div class="s4m-why">
					<?php echo self::reason_badge( $row ); ?>
					<p><?php echo esc_html( isset( $reasons[ $row->reason ] ) ? $reasons[ $row->reason ]['help'] : '' ); ?></p>
				</div>

				<table class="widefat striped s4m-detail-table">
					<tbody>
						<tr><th><?php esc_html_e( 'Date', 'spam-4-me' ); ?></th><td><?php echo esc_html( get_date_from_gmt( $row->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) ) ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Form', 'spam-4-me' ); ?></th><td><?php echo esc_html( $row->form_title ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Email', 'spam-4-me' ); ?></th><td><?php echo esc_html( $row->email ); ?></td></tr>
						<tr><th><?php esc_html_e( 'IP address', 'spam-4-me' ); ?></th><td><?php echo esc_html( $row->ip ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Page', 'spam-4-me' ); ?></th><td><?php echo esc_html( $row->page_url ); ?></td></tr>
						<tr><th><?php esc_html_e( 'Browser', 'spam-4-me' ); ?></th><td><?php echo esc_html( $row->user_agent ); ?></td></tr>
					</tbody>
				</table>

				<h2><?php esc_html_e( 'What was submitted', 'spam-4-me' ); ?></h2>
				<?php $values = json_decode( (string) $row->content, true ); ?>
				<?php if ( is_array( $values ) && $values ) : ?>
					<table class="widefat striped s4m-detail-table">
						<tbody>
							<?php foreach ( $values as $name => $value ) : ?>
								<tr<?php echo $name === $row->field ? ' class="s4m-hit"' : ''; ?>>
									<th><?php echo esc_html( $name ); ?></th>
									<td><div class="s4m-value"><?php echo esc_html( (string) $value ); ?></div></td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php else : ?>
					<p class="s4m-muted"><?php esc_html_e( 'Message text was not stored (see Settings → Log).', 'spam-4-me' ); ?></p>
				<?php endif; ?>

				<p class="s4m-actions">
					<?php if ( '' !== $row->ip ) : ?>
						<a class="button" href="<?php echo esc_url( self::action_url( 'block_ip', $row->id ) ); ?>"><?php esc_html_e( 'Block this IP', 'spam-4-me' ); ?></a>
					<?php endif; ?>
					<?php if ( 'keyword' === $row->reason ) : ?>
						<a class="button" href="<?php echo esc_url( self::action_url( 'allow_term', $row->id ) ); ?>"><?php esc_html_e( 'Not spam — allow this word', 'spam-4-me' ); ?></a>
					<?php endif; ?>
					<a class="button button-link-delete" href="<?php echo esc_url( self::action_url( 'delete', $row->id ) ); ?>"><?php esc_html_e( 'Delete entry', 'spam-4-me' ); ?></a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public static function register_settings() {
		register_setting(
			's4m_settings',
			S4M_OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => s4m_default_settings(),
			)
		);
	}

	public static function sanitize( $input ) {
		$input = is_array( $input ) ? $input : array();

		$ips     = array();
		$invalid = array();

		foreach ( s4m_lines( isset( $input['blocked_ips'] ) ? $input['blocked_ips'] : '' ) as $line ) {
			if ( s4m_is_valid_range( $line ) ) {
				$ips[] = $line;
			} else {
				$invalid[] = $line;
			}
		}

		if ( $invalid ) {
			add_settings_error(
				S4M_OPTION,
				's4m_invalid_ip',
				sprintf( __( 'These blocked IP entries were not valid and were removed: %s', 'spam-4-me' ), esc_html( implode( ', ', $invalid ) ) )
			);
		}

		$text = function ( $key ) use ( $input ) {
			return isset( $input[ $key ] ) ? sanitize_textarea_field( $input[ $key ] ) : '';
		};

		return array(
			'honeypot'          => empty( $input['honeypot'] ) ? 0 : 1,
			'require_js'        => empty( $input['require_js'] ) ? 0 : 1,
			'min_seconds'       => isset( $input['min_seconds'] ) ? min( 60, absint( $input['min_seconds'] ) ) : 3,
			'rate_limit'        => isset( $input['rate_limit'] ) ? min( 1000, absint( $input['rate_limit'] ) ) : 5,
			'blocked_ips'       => implode( "\n", $ips ),
			'block_links'       => empty( $input['block_links'] ) ? 0 : 1,
			'link_message'      => isset( $input['link_message'] ) ? sanitize_text_field( $input['link_message'] ) : '',
			'link_fields'       => $text( 'link_fields' ),
			'scripts'           => array_values( array_intersect( array_keys( s4m_script_patterns() ), isset( $input['scripts'] ) ? (array) $input['scripts'] : array() ) ),
			'categories'        => array_values( array_intersect( array_keys( s4m_keyword_lists() ), isset( $input['categories'] ) ? (array) $input['categories'] : array() ) ),
			'keywords'          => $text( 'keywords' ),
			'allowlist'         => $text( 'allowlist' ),
			'block_disposable'  => empty( $input['block_disposable'] ) ? 0 : 1,
			'autoreply_subject' => isset( $input['autoreply_subject'] ) ? sanitize_text_field( $input['autoreply_subject'] ) : '',
			'retention_days'    => isset( $input['retention_days'] ) ? min( 365, max( 1, absint( $input['retention_days'] ) ) ) : 30,
			'store_content'     => empty( $input['store_content'] ) ? 0 : 1,
		);
	}

	public static function render_settings() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$s     = s4m_settings();
		$name  = S4M_OPTION;
		$lists = s4m_keyword_lists();
		$cats  = s4m_keyword_labels();

		$checkbox = function ( $key, $label ) use ( $s, $name ) {
			printf(
				'<label><input type="checkbox" name="%1$s[%2$s]" value="1" %3$s> %4$s</label>',
				esc_attr( $name ),
				esc_attr( $key ),
				checked( ! empty( $s[ $key ] ), true, false ),
				esc_html( $label )
			);
		};
		?>
		<div class="wrap s4m-wrap">
			<h1><?php esc_html_e( 'Spam 4 Me — Settings', 'spam-4-me' ); ?></h1>
			<?php settings_errors( S4M_OPTION ); ?>

			<form method="post" action="options.php">
				<?php settings_fields( 's4m_settings' ); ?>

				<h2><?php esc_html_e( 'Bot protection', 'spam-4-me' ); ?></h2>
				<p class="description"><?php esc_html_e( 'Invisible checks. Real visitors never notice them.', 'spam-4-me' ); ?></p>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Bot trap', 'spam-4-me' ); ?></th>
						<td><?php $checkbox( 'honeypot', __( 'Add a hidden field that only bots fill in', 'spam-4-me' ) ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Browser check', 'spam-4-me' ); ?></th>
						<td>
							<?php $checkbox( 'require_js', __( 'Only accept forms filled in by a real browser (with JavaScript)', 'spam-4-me' ) ); ?>
							<p class="description"><?php esc_html_e( 'Stops bots that post your form directly without opening it in a browser. After turning this on, clear your page cache (for example Varnish or Cloudflare).', 'spam-4-me' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="s4m-min-seconds"><?php esc_html_e( 'Minimum time', 'spam-4-me' ); ?></label></th>
						<td>
							<input type="number" min="0" max="60" id="s4m-min-seconds" name="<?php echo esc_attr( $name ); ?>[min_seconds]" value="<?php echo esc_attr( $s['min_seconds'] ); ?>" class="small-text"> <?php esc_html_e( 'seconds', 'spam-4-me' ); ?>
							<p class="description"><?php esc_html_e( 'Reject forms sent faster than this after the page opened. 0 turns it off.', 'spam-4-me' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="s4m-rate-limit"><?php esc_html_e( 'Messages per hour', 'spam-4-me' ); ?></label></th>
						<td>
							<input type="number" min="0" max="1000" id="s4m-rate-limit" name="<?php echo esc_attr( $name ); ?>[rate_limit]" value="<?php echo esc_attr( $s['rate_limit'] ); ?>" class="small-text">
							<p class="description"><?php esc_html_e( 'Maximum messages from one IP address per hour. 0 turns it off.', 'spam-4-me' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="s4m-blocked-ips"><?php esc_html_e( 'Blocked IP addresses', 'spam-4-me' ); ?></label></th>
						<td>
							<textarea id="s4m-blocked-ips" class="large-text code" rows="4" name="<?php echo esc_attr( $name ); ?>[blocked_ips]" placeholder="193.124.20.0/24"><?php echo esc_textarea( $s['blocked_ips'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One per line. Single addresses (1.2.3.4) or ranges (1.2.3.0/24). Works behind Cloudflare.', 'spam-4-me' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Message content', 'spam-4-me' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Links', 'spam-4-me' ); ?></th>
						<td>
							<?php $checkbox( 'block_links', __( 'Do not allow web links in text and message fields', 'spam-4-me' ) ); ?>
							<p><label for="s4m-link-message"><?php esc_html_e( 'Message shown to the visitor:', 'spam-4-me' ); ?></label><br>
							<input type="text" id="s4m-link-message" class="regular-text" name="<?php echo esc_attr( $name ); ?>[link_message]" value="<?php echo esc_attr( $s['link_message'] ); ?>" placeholder="<?php esc_attr_e( 'Links are not allowed in this field.', 'spam-4-me' ); ?>"></p>
							<p class="description"><?php esc_html_e( 'Site names without a link, like "booking.com", are still allowed.', 'spam-4-me' ); ?></p>
							<p><label for="s4m-link-fields"><?php esc_html_e( 'Fields that may contain links:', 'spam-4-me' ); ?></label><br>
							<textarea id="s4m-link-fields" class="regular-text code" rows="5" name="<?php echo esc_attr( $name ); ?>[link_fields]"><?php echo esc_textarea( $s['link_fields'] ); ?></textarea></p>
							<p class="description"><?php esc_html_e( 'One word per line. A field whose label or name contains one of these words (for example "LinkedIn profile" or "Website") may contain links. Add * to match the start of a word. You can also give any field the CSS class s4m-allow-links in your form builder. Banned words and blocked languages are still checked in these fields. URL and Website field types are never checked for links.', 'spam-4-me' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Block languages', 'spam-4-me' ); ?></th>
						<td>
							<fieldset class="s4m-columns">
								<?php foreach ( s4m_script_labels() as $key => $label ) : ?>
									<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[scripts][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $s['scripts'], true ) ); ?>> <?php echo esc_html( $label ); ?></label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Block messages written in these alphabets. Only tick languages your visitors never write in — if your site is published in a language, leave it unticked. Latin-alphabet languages and Burmese are never blocked.', 'spam-4-me' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Banned word lists', 'spam-4-me' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( $lists as $key => $terms ) : ?>
									<div class="s4m-list">
										<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[categories][]" value="<?php echo esc_attr( $key ); ?>" <?php checked( in_array( $key, (array) $s['categories'], true ) ); ?>> <strong><?php echo esc_html( $cats[ $key ] ); ?></strong></label>
										<details><summary>
											<?php
											echo esc_html( sprintf( _n( 'Show %d word', 'Show %d words', count( $terms ), 'spam-4-me' ), count( $terms ) ) );
											?>
										</summary><p class="s4m-terms"><?php echo esc_html( implode( ', ', $terms ) ); ?></p></details>
									</div>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'Lists cover English, Chinese, Japanese, Korean, Thai, Vietnamese, Indonesian, Russian and more. Words match whole words only, so "sex" never blocks "Sussex". A * at the end matches word beginnings (porn* = porno, pornhub).', 'spam-4-me' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="s4m-keywords"><?php esc_html_e( 'Your own banned words', 'spam-4-me' ); ?></label></th>
						<td>
							<textarea id="s4m-keywords" class="large-text code" rows="5" name="<?php echo esc_attr( $name ); ?>[keywords]"><?php echo esc_textarea( $s['keywords'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'One word or phrase per line, in any language.', 'spam-4-me' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="s4m-allowlist"><?php esc_html_e( 'Always allow', 'spam-4-me' ); ?></label></th>
						<td>
							<textarea id="s4m-allowlist" class="large-text code" rows="4" name="<?php echo esc_attr( $name ); ?>[allowlist]"><?php echo esc_textarea( $s['allowlist'] ); ?></textarea>
							<p class="description"><?php esc_html_e( 'Words or phrases that must never block a message, even if they are on a list above. "Allow this word" in the log adds to this list.', 'spam-4-me' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Throwaway emails', 'spam-4-me' ); ?></th>
						<td><?php $checkbox( 'block_disposable', __( 'Block temporary email addresses (mailinator, yopmail, 10minutemail …)', 'spam-4-me' ) ); ?></td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Autoreply', 'spam-4-me' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="s4m-subject"><?php esc_html_e( 'Autoreply subject', 'spam-4-me' ); ?></label></th>
						<td>
							<input type="text" id="s4m-subject" class="regular-text" name="<?php echo esc_attr( $name ); ?>[autoreply_subject]" value="<?php echo esc_attr( $s['autoreply_subject'] ); ?>" placeholder="<?php echo esc_attr( sprintf( __( 'Thank you for contacting %s', 'spam-4-me' ), get_bloginfo( 'name' ) ) ); ?>">
							<p class="description"><?php esc_html_e( 'Used for every autoreply: Contact Form 7 Mail 2, and any WPForms, Gravity Forms, Ninja Forms or Fluent Forms email sent to the address the visitor typed. So spammers can never put their text in it. Leave empty for the default shown.', 'spam-4-me' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Log', 'spam-4-me' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="s4m-retention"><?php esc_html_e( 'Keep log for', 'spam-4-me' ); ?></label></th>
						<td><input type="number" min="1" max="365" id="s4m-retention" class="small-text" name="<?php echo esc_attr( $name ); ?>[retention_days]" value="<?php echo esc_attr( $s['retention_days'] ); ?>"> <?php esc_html_e( 'days', 'spam-4-me' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Message text', 'spam-4-me' ); ?></th>
						<td><?php $checkbox( 'store_content', __( 'Save what was typed, so you can check blocked messages', 'spam-4-me' ) ); ?></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	public static function render_test() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$input = array(
			'name'    => '',
			'email'   => '',
			'subject' => '',
			'message' => '',
		);
		$hits  = null;

		if ( isset( $_POST['s4m_test'] ) ) {
			check_admin_referer( 's4m_test' );

			foreach ( array_keys( $input ) as $key ) {
				$input[ $key ] = isset( $_POST[ $key ] ) ? sanitize_textarea_field( wp_unslash( $_POST[ $key ] ) ) : '';
			}

			$hits = s4m_check_content(
				array(
					'name'    => $input['name'],
					'subject' => $input['subject'],
					'message' => $input['message'],
				),
				array( 'email' => $input['email'] ),
				s4m_settings(),
				true,
				true
			);
		}
		?>
		<div class="wrap s4m-wrap">
			<h1><?php esc_html_e( 'Spam 4 Me — Test a Message', 'spam-4-me' ); ?></h1>
			<p class="description"><?php esc_html_e( 'Check whether a message would get through, using your current settings. Nothing is sent and nothing is logged.', 'spam-4-me' ); ?></p>

			<?php if ( null !== $hits ) : ?>
				<?php if ( ! $hits ) : ?>
					<div class="notice notice-success inline s4m-result"><p><strong><?php esc_html_e( 'Allowed — this message would be delivered.', 'spam-4-me' ); ?></strong></p></div>
				<?php else : ?>
					<div class="notice notice-error inline s4m-result">
						<p><strong><?php esc_html_e( 'Blocked — this message would not be delivered.', 'spam-4-me' ); ?></strong></p>
						<ul>
							<?php foreach ( $hits as $hit ) : ?>
								<li><?php echo self::reason_badge( $hit ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
			<?php endif; ?>

			<form method="post">
				<?php wp_nonce_field( 's4m_test' ); ?>
				<table class="form-table" role="presentation">
					<tr><th scope="row"><label for="s4m-t-name"><?php esc_html_e( 'Name', 'spam-4-me' ); ?></label></th><td><input type="text" id="s4m-t-name" name="name" class="regular-text" value="<?php echo esc_attr( $input['name'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="s4m-t-email"><?php esc_html_e( 'Email', 'spam-4-me' ); ?></label></th><td><input type="text" id="s4m-t-email" name="email" class="regular-text" value="<?php echo esc_attr( $input['email'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="s4m-t-subject"><?php esc_html_e( 'Subject', 'spam-4-me' ); ?></label></th><td><input type="text" id="s4m-t-subject" name="subject" class="large-text" value="<?php echo esc_attr( $input['subject'] ); ?>"></td></tr>
					<tr><th scope="row"><label for="s4m-t-message"><?php esc_html_e( 'Message', 'spam-4-me' ); ?></label></th><td><textarea id="s4m-t-message" name="message" class="large-text" rows="6"><?php echo esc_textarea( $input['message'] ); ?></textarea></td></tr>
				</table>
				<p class="description"><?php esc_html_e( 'The bot trap, browser check, minimum time, IP block and rate limit only apply to real form submissions and are not tested here.', 'spam-4-me' ); ?></p>
				<?php submit_button( __( 'Test message', 'spam-4-me' ), 'primary', 's4m_test' ); ?>
			</form>
		</div>
		<?php
	}
}
