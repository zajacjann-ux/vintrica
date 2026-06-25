<?php
/**
 * Custom checkout processing.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Checkout
 */
class Vintrica_Checkout {

	/**
	 * Security handler.
	 *
	 * @var Vintrica_Security
	 */
	private $security;

	/**
	 * Pricing handler.
	 *
	 * @var Vintrica_Pricing
	 */
	private $pricing;

	/**
	 * Orders handler.
	 *
	 * @var Vintrica_Orders
	 */
	private $orders;

	/**
	 * Stripe handler.
	 *
	 * @var Vintrica_Stripe
	 */
	private $stripe;

	/**
	 * Constructor.
	 *
	 * @param Vintrica_Security $security Security handler.
	 * @param Vintrica_Pricing  $pricing  Pricing handler.
	 * @param Vintrica_Orders   $orders   Orders handler.
	 * @param Vintrica_Stripe   $stripe   Stripe handler.
	 */
	public function __construct( Vintrica_Security $security, Vintrica_Pricing $pricing, Vintrica_Orders $orders, Vintrica_Stripe $stripe ) {
		$this->security = $security;
		$this->pricing  = $pricing;
		$this->orders   = $orders;
		$this->stripe   = $stripe;

		add_action( 'wp_ajax_vintrica_create_checkout_session', array( $this, 'ajax_create_checkout_session' ) );
		add_action( 'wp_ajax_nopriv_vintrica_create_checkout_session', array( $this, 'ajax_create_checkout_session' ) );
	}

	/**
	 * AJAX handler for Stripe Checkout Session creation.
	 *
	 * @return void
	 */
	public function ajax_create_checkout_session() {
		if ( ! check_ajax_referer( Vintrica_Security::CHECKOUT_NONCE_ACTION, 'nonce', false ) ) {
			wp_send_json_error(
				array(
					'message' => __( 'Overenie bezpečnosti zlyhalo. Obnovte stránku a skúste to znova.', 'vintrica-vignette-form' ),
				)
			);
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above with check_ajax_referer().
		$post_data = wp_unslash( $_POST );
		$post_data = is_array( $post_data ) ? $post_data : array();

		if ( $this->security->is_honeypot_triggered( $post_data ) ) {
			wp_send_json_success(
				array(
					'checkout_url' => '',
					'order_number' => '',
				)
			);
		}

		$rate_limit = $this->security->check_checkout_rate_limit();

		if ( is_wp_error( $rate_limit ) ) {
			wp_send_json_error(
				array(
					'message' => $rate_limit->get_error_message(),
				)
			);
		}

		$this->security->record_checkout_attempt();

		$result = $this->process_submission( $post_data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error(
				array(
					'message' => $result->get_error_message(),
				)
			);
		}

		wp_send_json_success(
			array(
				'checkout_url' => $result['checkout_url'],
				'order_number' => $result['order_number'],
			)
		);
	}

	/**
	 * Process a full checkout submission after review confirmation.
	 *
	 * @param array $post_data Raw POST data.
	 * @return array{checkout_url: string, order_number: string}|WP_Error
	 */
	public function process_submission( array $post_data ) {
		$price_check = $this->security->reject_frontend_price_fields( $post_data );

		if ( is_wp_error( $price_check ) ) {
			return $price_check;
		}

		if ( empty( $post_data['vintrica_vignettes'] ) ) {
			return new WP_Error(
				'vintrica_missing_vignettes',
				__( 'Nebola odoslaná žiadna objednávka známok.', 'vintrica-vignette-form' )
			);
		}

		$vignettes = $this->security->sanitize_vignette_order( $post_data['vintrica_vignettes'] );

		if ( is_wp_error( $vignettes ) ) {
			return $vignettes;
		}

		$billing = $this->security->sanitize_billing_data( $post_data );

		if ( is_wp_error( $billing ) ) {
			return $billing;
		}

		$totals = $this->pricing->calculate_totals( $vignettes );

		if ( is_wp_error( $totals ) ) {
			return $totals;
		}

		$order_id = $this->orders->create_order(
			array(
				'status'     => Vintrica_Orders::STATUS_UNPAID,
				'vignettes'  => $vignettes,
				'billing'    => $billing,
				'totals'     => $totals,
				'currency'   => $this->pricing->get_currency(),
				'ip_address' => isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '',
			)
		);

		if ( is_wp_error( $order_id ) ) {
			return $order_id;
		}

		$order = $this->orders->get_order( $order_id );

		if ( ! $order ) {
			return new WP_Error(
				'vintrica_order_missing',
				__( 'Objednávku sa nepodarilo načítať.', 'vintrica-vignette-form' )
			);
		}

		/**
		 * Fires immediately after a new VINTRICA order is stored.
		 *
		 * @param object $order Order row.
		 */
		do_action( 'vintrica_order_created', $order );

		$stripe_session = $this->stripe->create_checkout_session( $order, $totals );

		if ( is_wp_error( $stripe_session ) ) {
			return $stripe_session;
		}

		$this->orders->update_stripe_session(
			$order_id,
			$stripe_session['session_id'],
			$stripe_session['payload']
		);

		return array(
			'checkout_url' => $stripe_session['checkout_url'],
			'order_number' => $order->order_number,
		);
	}
}
