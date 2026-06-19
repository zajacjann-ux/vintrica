<?php
/**
 * Custom VINTRICA orders database handler.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Orders
 */
class Vintrica_Orders {

	/**
	 * Database version for schema upgrades.
	 */
	const DB_VERSION = '1.3.0';

	/**
	 * Option key for stored DB version.
	 */
	const DB_VERSION_OPTION = 'vintrica_orders_db_version';

	/**
	 * Order status: paid.
	 */
	const STATUS_PAID = 'paid';

	/**
	 * Order status: unpaid.
	 */
	const STATUS_UNPAID = 'unpaid';

	/**
	 * Order status: cancelled.
	 */
	const STATUS_CANCELLED = 'cancelled';

	/**
	 * Get orders table name.
	 *
	 * @return string
	 */
	public function get_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'vintrica_orders';
	}

	/**
	 * Get allowed order statuses.
	 *
	 * @return array<string, string>
	 */
	public function get_statuses() {
		return array(
			self::STATUS_PAID      => __( 'Uhradená', 'vintrica-vignette-form' ),
			self::STATUS_UNPAID    => __( 'Neuhradená', 'vintrica-vignette-form' ),
			self::STATUS_CANCELLED => __( 'Zrušená', 'vintrica-vignette-form' ),
		);
	}

	/**
	 * Normalize a stored status value.
	 *
	 * @param string $status Raw status.
	 * @return string
	 */
	public function normalize_status( $status ) {
		$status = sanitize_key( $status );

		if ( 'pending_payment' === $status ) {
			return self::STATUS_UNPAID;
		}

		$statuses = $this->get_statuses();

		return isset( $statuses[ $status ] ) ? $status : self::STATUS_UNPAID;
	}

	/**
	 * Get localized status label.
	 *
	 * @param string $status Order status.
	 * @return string
	 */
	public function get_status_label( $status ) {
		$status   = $this->normalize_status( $status );
		$statuses = $this->get_statuses();

		return $statuses[ $status ] ?? $status;
	}

	/**
	 * Create or upgrade the orders table.
	 *
	 * @return void
	 */
	public function create_table() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table_name      = $this->get_table_name();
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			order_number varchar(32) NOT NULL,
			status varchar(32) NOT NULL DEFAULT 'unpaid',
			vignettes longtext NOT NULL,
			billing longtext NOT NULL,
			subtotal decimal(10,2) NOT NULL DEFAULT 0.00,
			service_fee decimal(10,2) NOT NULL DEFAULT 0.00,
			total decimal(10,2) NOT NULL DEFAULT 0.00,
			currency varchar(8) NOT NULL DEFAULT 'EUR',
			stripe_session_id varchar(255) DEFAULT NULL,
			stripe_session_payload longtext DEFAULT NULL,
			stripe_payment_intent_id varchar(255) DEFAULT NULL,
			redirect_token varchar(64) DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			created_at datetime NOT NULL,
			paid_at datetime DEFAULT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_number (order_number),
			KEY status (status),
			KEY created_at (created_at),
			KEY stripe_session_id (stripe_session_id)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Insert a new order.
	 *
	 * @param array $order_data Prepared order data.
	 * @return int|WP_Error Order ID or error.
	 */
	public function create_order( array $order_data ) {
		global $wpdb;

		$order_number = $this->generate_order_number();

		if ( is_wp_error( $order_number ) ) {
			return $order_number;
		}

		$status = $this->normalize_status( $order_data['status'] ?? self::STATUS_UNPAID );
		$redirect_token = $this->generate_redirect_token();

		$inserted = $wpdb->insert(
			$this->get_table_name(),
			array(
				'order_number'           => $order_number,
				'status'                 => $status,
				'vignettes'              => wp_json_encode( $order_data['vignettes'] ),
				'billing'                => wp_json_encode( $order_data['billing'] ),
				'subtotal'               => (float) $order_data['totals']['subtotal'],
				'service_fee'            => (float) $order_data['totals']['service_fee'],
				'total'                  => (float) $order_data['totals']['total'],
				'currency'               => sanitize_text_field( $order_data['currency'] ?? 'EUR' ),
				'stripe_session_id'      => isset( $order_data['stripe_session_id'] ) ? sanitize_text_field( $order_data['stripe_session_id'] ) : null,
				'stripe_session_payload' => isset( $order_data['stripe_session_payload'] ) ? wp_json_encode( $order_data['stripe_session_payload'] ) : null,
				'redirect_token'         => $redirect_token,
				'ip_address'             => isset( $order_data['ip_address'] ) ? sanitize_text_field( $order_data['ip_address'] ) : '',
				'created_at'             => current_time( 'mysql', true ),
			),
			array(
				'%s',
				'%s',
				'%s',
				'%s',
				'%f',
				'%f',
				'%f',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
				'%s',
			)
		);

		if ( false === $inserted ) {
			return new WP_Error(
				'vintrica_order_insert_failed',
				__( 'Objednávku sa nepodarilo uložiť.', 'vintrica-vignette-form' )
			);
		}

		return (int) $wpdb->insert_id;
	}

	/**
	 * Update order status.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $status   New status.
	 * @return bool|WP_Error
	 */
	public function update_status( $order_id, $status ) {
		global $wpdb;

		$status = $this->normalize_status( $status );

		if ( ! isset( $this->get_statuses()[ $status ] ) ) {
			return new WP_Error(
				'vintrica_invalid_status',
				__( 'Neplatný stav objednávky.', 'vintrica-vignette-form' )
			);
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$this->get_table_name(),
			array( 'status' => $status ),
			array( 'id' => (int) $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Mark an order as paid.
	 *
	 * @param int    $order_id          Order ID.
	 * @param string $payment_intent_id Stripe payment intent ID.
	 * @return bool|WP_Error
	 */
	public function mark_as_paid( $order_id, $payment_intent_id = '' ) {
		global $wpdb;

		$data = array(
			'status'  => self::STATUS_PAID,
			'paid_at' => current_time( 'mysql', true ),
		);
		$format = array( '%s', '%s' );

		if ( '' !== $payment_intent_id ) {
			$data['stripe_payment_intent_id'] = sanitize_text_field( $payment_intent_id );
			$format[]                         = '%s';
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$updated = $wpdb->update(
			$this->get_table_name(),
			$data,
			array( 'id' => (int) $order_id ),
			$format,
			array( '%d' )
		);

		return false !== $updated;
	}

	/**
	 * Get order by Stripe session ID.
	 *
	 * @param string $session_id Stripe session ID.
	 * @return object|null
	 */
	public function get_order_by_stripe_session_id( $session_id ) {
		global $wpdb;

		$table_name = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE stripe_session_id = %s",
				sanitize_text_field( $session_id )
			)
		);
	}

	/**
	 * Get order by ID.
	 *
	 * @param int $order_id Order ID.
	 * @return object|null
	 */
	public function get_order( $order_id ) {
		global $wpdb;

		$table_name = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				(int) $order_id
			)
		);
	}

	/**
	 * Get order by order number.
	 *
	 * @param string $order_number Order number.
	 * @return object|null
	 */
	public function get_order_by_number( $order_number ) {
		global $wpdb;

		$table_name = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE order_number = %s",
				sanitize_text_field( $order_number )
			)
		);
	}

	/**
	 * Update Stripe session metadata on an order.
	 *
	 * @param int    $order_id   Order ID.
	 * @param string $session_id Stripe session ID.
	 * @param array  $payload    Prepared Stripe payload.
	 * @return bool
	 */
	public function update_stripe_session( $order_id, $session_id, array $payload ) {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		return false !== $wpdb->update(
			$this->get_table_name(),
			array(
				'stripe_session_id'      => sanitize_text_field( $session_id ),
				'stripe_session_payload' => wp_json_encode( $payload ),
			),
			array( 'id' => (int) $order_id ),
			array( '%s', '%s' ),
			array( '%d' )
		);
	}

	/**
	 * Generate a unique redirect token for an order.
	 *
	 * @return string
	 */
	public function generate_redirect_token() {
		return wp_generate_password( 32, false, false );
	}

	/**
	 * Ensure an order has a redirect token and return it.
	 *
	 * @param int $order_id Order ID.
	 * @return string
	 */
	public function ensure_redirect_token( $order_id ) {
		global $wpdb;

		$order = $this->get_order( $order_id );

		if ( ! $order ) {
			return '';
		}

		if ( ! empty( $order->redirect_token ) ) {
			return (string) $order->redirect_token;
		}

		$token = $this->generate_redirect_token();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->update(
			$this->get_table_name(),
			array( 'redirect_token' => $token ),
			array( 'id' => (int) $order_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $token;
	}

	/**
	 * Verify a redirect token for an order.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $token    Redirect token from query string.
	 * @return bool
	 */
	public function verify_redirect_token( $order_id, $token ) {
		$order = $this->get_order( $order_id );

		if ( ! $order || empty( $order->redirect_token ) || '' === trim( (string) $token ) ) {
			return false;
		}

		return hash_equals( (string) $order->redirect_token, sanitize_text_field( $token ) );
	}

	/**
	 * Get recent orders for admin list.
	 *
	 * @param int $limit Result limit.
	 * @return array<int, object>
	 */
	public function get_recent_orders( $limit = 50 ) {
		global $wpdb;

		$table_name = $this->get_table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$results = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} ORDER BY created_at DESC LIMIT %d",
				(int) $limit
			)
		);

		return is_array( $results ) ? $results : array();
	}

	/**
	 * Decode vignettes JSON from an order row.
	 *
	 * @param object $order Order row.
	 * @return array<int, array<string, string>>
	 */
	public function decode_vignettes( $order ) {
		$decoded = json_decode( (string) $order->vignettes, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Decode billing JSON from an order row.
	 *
	 * @param object $order Order row.
	 * @return array<string, mixed>
	 */
	public function decode_billing( $order ) {
		$decoded = json_decode( (string) $order->billing, true );

		return is_array( $decoded ) ? $decoded : array();
	}

	/**
	 * Count vignettes in an order.
	 *
	 * @param object $order Order row.
	 * @return int
	 */
	public function count_vignettes( $order ) {
		return count( $this->decode_vignettes( $order ) );
	}

	/**
	 * Get customer full name from billing data.
	 *
	 * @param object $order Order row.
	 * @return string
	 */
	public function get_customer_name( $order ) {
		$billing = $this->decode_billing( $order );

		$first = isset( $billing['first_name'] ) ? trim( (string) $billing['first_name'] ) : '';
		$last  = isset( $billing['last_name'] ) ? trim( (string) $billing['last_name'] ) : '';

		return trim( $first . ' ' . $last );
	}

	/**
	 * Generate a unique order number.
	 *
	 * @return string|WP_Error
	 */
	private function generate_order_number() {
		global $wpdb;

		$table_name = $this->get_table_name();

		for ( $attempt = 0; $attempt < 5; $attempt++ ) {
			$order_number = 'VIN-' . gmdate( 'Ymd' ) . '-' . strtoupper( wp_generate_password( 6, false, false ) );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$exists = $wpdb->get_var(
				$wpdb->prepare(
					"SELECT id FROM {$table_name} WHERE order_number = %s",
					$order_number
				)
			);

			if ( empty( $exists ) ) {
				return $order_number;
			}
		}

		return new WP_Error(
			'vintrica_order_number_failed',
			__( 'Nepodarilo sa vygenerovať číslo objednávky.', 'vintrica-vignette-form' )
		);
	}
}
