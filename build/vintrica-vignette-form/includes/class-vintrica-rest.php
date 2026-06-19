<?php
/**
 * REST API routes for checkout and Stripe webhooks.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Rest
 */
class Vintrica_Rest {

	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'vintrica/v1';

	/**
	 * Checkout handler.
	 *
	 * @var Vintrica_Checkout
	 */
	private $checkout;

	/**
	 * Stripe handler.
	 *
	 * @var Vintrica_Stripe
	 */
	private $stripe;

	/**
	 * Constructor.
	 *
	 * @param Vintrica_Checkout $checkout Checkout handler.
	 * @param Vintrica_Stripe   $stripe   Stripe handler.
	 */
	public function __construct( Vintrica_Checkout $checkout, Vintrica_Stripe $stripe ) {
		$this->checkout = $checkout;
		$this->stripe   = $stripe;

		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/checkout',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_checkout' ),
				'permission_callback' => array( $this, 'verify_checkout_request' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/stripe-webhook',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'handle_stripe_webhook' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Verify checkout REST request nonce.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool
	 */
	public function verify_checkout_request( WP_REST_Request $request ) {
		$nonce = $request->get_header( 'X-WP-Nonce' );

		if ( empty( $nonce ) ) {
			$nonce = $request->get_param( '_wpnonce' );
		}

		return ! empty( $nonce ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $nonce ) ), 'wp_rest' );
	}

	/**
	 * Handle checkout session creation via REST.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_checkout( WP_REST_Request $request ) {
		$params = $request->get_body_params();

		if ( empty( $params ) ) {
			$params = $request->get_params();
		}

		$result = $this->checkout->process_submission( wp_unslash( $params ) );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				400
			);
		}

		return new WP_REST_Response(
			array(
				'success'      => true,
				'checkout_url' => $result['checkout_url'],
				'order_number' => $result['order_number'],
			),
			200
		);
	}

	/**
	 * Handle Stripe webhook events.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response
	 */
	public function handle_stripe_webhook( WP_REST_Request $request ) {
		$payload    = $request->get_body();
		$sig_header = isset( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_STRIPE_SIGNATURE'] ) ) : '';

		if ( ! $this->stripe->verify_webhook_signature( $payload, $sig_header ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Neplatný Stripe webhook podpis.', 'vintrica-vignette-form' ),
				),
				400
			);
		}

		$event = json_decode( $payload, true );

		if ( ! is_array( $event ) || empty( $event['type'] ) ) {
			return new WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Neplatný Stripe webhook payload.', 'vintrica-vignette-form' ),
				),
				400
			);
		}

		$this->stripe->handle_webhook_event( $event );

		return new WP_REST_Response(
			array(
				'received' => true,
			),
			200
		);
	}
}
