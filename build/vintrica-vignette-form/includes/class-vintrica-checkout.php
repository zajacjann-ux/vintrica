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
	}

	/**
	 * Process a full checkout submission after review confirmation.
	 *
	 * @param array $post_data Raw POST data.
	 * @return array{redirect: string, order_number: string}|WP_Error
	 */
	public function process_submission( array $post_data ) {
		if ( ! $this->security->verify_form_request_from_post( $post_data ) ) {
			return new WP_Error(
				'vintrica_security_failed',
				__( 'Overenie bezpečnosti zlyhalo. Skúste to znova.', 'vintrica-vignette-form' )
			);
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

		$stripe_session = $this->stripe->create_checkout_session( $order, $totals );

		if ( is_wp_error( $stripe_session ) ) {
			return $stripe_session;
		}

		$this->orders->update_stripe_session(
			$order_id,
			$stripe_session['session_id'],
			$stripe_session['payload']
		);

		/**
		 * Fires after a VINTRICA order is stored and Stripe session is created.
		 *
		 * @param object $order          Order row.
		 * @param array  $stripe_session Stripe session result.
		 */
		do_action( 'vintrica_order_created', $order, $stripe_session );

		return array(
			'redirect'     => $stripe_session['checkout_url'],
			'order_number' => $order->order_number,
		);
	}
}
