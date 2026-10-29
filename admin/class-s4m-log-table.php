<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

class S4M_Log_Table extends WP_List_Table {
	public function __construct() {
		parent::__construct(
			array(
				'singular' => 's4m_log',
				'plural'   => 's4m_logs',
				'ajax'     => false,
			)
		);
	}

	public function get_columns() {
		return array(
			'cb'         => '<input type="checkbox" />',
			'created_at' => __( 'Date', 'spam-4-me' ),
			'reason'     => __( 'Why it was blocked', 'spam-4-me' ),
			'form_title' => __( 'Form', 'spam-4-me' ),
			'email'      => __( 'Email', 'spam-4-me' ),
			'ip'         => __( 'IP address', 'spam-4-me' ),
			'content'    => __( 'Message', 'spam-4-me' ),
		);
	}

	protected function get_sortable_columns() {
		return array(
			'created_at' => array( 'created_at', true ),
			'reason'     => array( 'reason', false ),
			'ip'         => array( 'ip', false ),
		);
	}

	protected function get_bulk_actions() {
		return array(
			'delete' => __( 'Delete', 'spam-4-me' ),
		);
	}

	protected function column_cb( $item ) {
		return sprintf( '<input type="checkbox" name="ids[]" value="%d" />', $item->id );
	}

	protected function column_created_at( $item ) {
		$actions = array(
			'view' => sprintf( '<a href="%s">%s</a>', esc_url( S4M_Admin::log_url( array( 'view' => $item->id ) ) ), esc_html__( 'View', 'spam-4-me' ) ),
		);

		if ( '' !== $item->ip ) {
			$actions['block_ip'] = sprintf( '<a href="%s">%s</a>', esc_url( S4M_Admin::action_url( 'block_ip', $item->id ) ), esc_html__( 'Block IP', 'spam-4-me' ) );
		}

		if ( 'keyword' === $item->reason ) {
			$actions['allow'] = sprintf( '<a href="%s">%s</a>', esc_url( S4M_Admin::action_url( 'allow_term', $item->id ) ), esc_html__( 'Allow this word', 'spam-4-me' ) );
		}

		$actions['delete'] = sprintf( '<a href="%s" class="submitdelete">%s</a>', esc_url( S4M_Admin::action_url( 'delete', $item->id ) ), esc_html__( 'Delete', 'spam-4-me' ) );

		$date = get_date_from_gmt( $item->created_at, get_option( 'date_format' ) . ' ' . get_option( 'time_format' ) );

		return sprintf( '<a href="%s"><strong>%s</strong></a>', esc_url( S4M_Admin::log_url( array( 'view' => $item->id ) ) ), esc_html( $date ) ) . $this->row_actions( $actions );
	}

	protected function column_reason( $item ) {
		return S4M_Admin::reason_badge( $item );
	}

	protected function column_content( $item ) {
		$values = json_decode( (string) $item->content, true );

		if ( ! is_array( $values ) ) {
			return '<span class="s4m-muted">&mdash;</span>';
		}

		$text = implode( ' · ', array_filter( array_map( 'strval', $values ), 'strlen' ) );

		return '<span class="s4m-excerpt">' . esc_html( wp_html_excerpt( $text, 120, '…' ) ) . '</span>';
	}

	protected function column_default( $item, $column_name ) {
		$value = isset( $item->$column_name ) ? (string) $item->$column_name : '';

		return '' === $value ? '<span class="s4m-muted">&mdash;</span>' : esc_html( $value );
	}

	protected function extra_tablenav( $which ) {
		if ( 'top' !== $which ) {
			return;
		}

		$current = isset( $_GET['reason'] ) ? sanitize_key( wp_unslash( $_GET['reason'] ) ) : '';
		?>
		<div class="alignleft actions">
			<label class="screen-reader-text" for="s4m-reason-filter"><?php esc_html_e( 'Filter by reason', 'spam-4-me' ); ?></label>
			<select name="reason" id="s4m-reason-filter">
				<option value=""><?php esc_html_e( 'All reasons', 'spam-4-me' ); ?></option>
				<?php foreach ( S4M_Log::reasons() as $key => $reason ) : ?>
					<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $current, $key ); ?>><?php echo esc_html( $reason['label'] ); ?></option>
				<?php endforeach; ?>
			</select>
			<?php submit_button( __( 'Filter', 'spam-4-me' ), '', 'filter_action', false ); ?>
		</div>
		<?php
	}

	public function no_items() {
		esc_html_e( 'Nothing has been blocked yet.', 'spam-4-me' );
	}

	public function prepare_items() {
		global $wpdb;

		$table    = S4M_Log::table();
		$per_page = $this->get_items_per_page( 's4m_logs_per_page', 20 );
		$where    = array( '1=1' );
		$args     = array();

		$reason  = isset( $_GET['reason'] ) ? sanitize_key( wp_unslash( $_GET['reason'] ) ) : '';
		$search  = isset( $_GET['s'] ) ? sanitize_text_field( wp_unslash( $_GET['s'] ) ) : '';
		$orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : 'created_at';
		$order   = isset( $_GET['order'] ) && 'asc' === strtolower( sanitize_key( wp_unslash( $_GET['order'] ) ) ) ? 'ASC' : 'DESC';

		if ( '' !== $reason ) {
			$where[] = 'reason = %s';
			$args[]  = $reason;
		}

		if ( '' !== $search ) {
			$like    = '%' . $wpdb->esc_like( $search ) . '%';
			$where[] = '(email LIKE %s OR ip LIKE %s OR detail LIKE %s OR form_title LIKE %s OR content LIKE %s)';
			array_push( $args, $like, $like, $like, $like, $like );
		}

		if ( ! in_array( $orderby, array( 'created_at', 'reason', 'ip' ), true ) ) {
			$orderby = 'created_at';
		}

		$where_sql = implode( ' AND ', $where );
		$offset    = ( $this->get_pagenum() - 1 ) * $per_page;

		$count_sql = "SELECT COUNT(*) FROM {$table} WHERE {$where_sql}";
		$total     = (int) ( $args ? $wpdb->get_var( $wpdb->prepare( $count_sql, $args ) ) : $wpdb->get_var( $count_sql ) );

		$this->items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_sql} ORDER BY {$orderby} {$order}, id DESC LIMIT %d OFFSET %d",
				array_merge( $args, array( $per_page, $offset ) )
			)
		);

		$this->_column_headers = array( $this->get_columns(), array(), $this->get_sortable_columns(), 'created_at' );

		$this->set_pagination_args(
			array(
				'total_items' => $total,
				'per_page'    => $per_page,
				'total_pages' => (int) ceil( $total / $per_page ),
			)
		);
	}
}
