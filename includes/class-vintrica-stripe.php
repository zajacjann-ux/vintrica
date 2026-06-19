<?php
/**
 * Stripe Checkout Session integration and webhooks.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Stripe
 */
class Vintrica_Stripe {

	/**
	 * Option key for Stripe secret key.
	 */
	const OPTION_SECRET_KEY = 'vintrica_stripe_secret_key';

	/**
	 * Option key for Stripe publishable key.
	 */
	const OPTION_PUBLISHABLE_KEY = 'vintrica_stripe_publishable_key';

	/**
	 * Option key for Stripe webhook secret.
	 */
	const OPTION_WEBHOOK_SECRET = 'vintrica_stripe_webhook_secret';

	/**
	 * Option key for Stripe test mode flag.
	 */
	const OPTION_TEST_MODE = 'vintrica_stripe_test_mode';

	/**
	 * Get Stripe secret key.
	 *
	 * @return string
	 */
	public function get_secret_key() {
		return (string) get_option( self::OPTION_SECRET_KEY, '' );
	}

	/**
	 * Get Stripe publishable key.
	 *
	 * @return string
	 */
	public function get_publishable_key() {
		return (string) get_option( self::OPTION_PUBLISHABLE_KEY, '' );
	}

	/**
	 * Get Stripe webhook secret.
	 *
	 * @return string
	 */
	public function get_webhook_secret() {
		return (string) get_option( self::OPTION_WEBHOOK_SECRET, '' );
	}

	/**
	 * Check whether Stripe test mode is enabled.
	 *
	 * @return bool
	 */
	public function is_test_mode() {
		return (bool) get_option( self::OPTION_TEST_MODE, true );
	}

	/**
	 * Check whether live Stripe checkout can be created.
	 *
	 * @return bool
	 */
	public function is_configured() {
		return '' !== trim( $this->get_secret_key() );
	}

	/**
	 * Get the Stripe webhook endpoint URL.
	 *
	 * @return string
	 */
	public function get_webhook_url() {
		return rest_url( 'vintrica/v1/stripe-webhook' );
	}

	/**
	 * Save Stripe settings.
	 *
	 * @param array $settings Settings payload.
	 * @return void
	 */
	public function save_settings( array $settings ) {
		update_option( self::OPTION_SECRET_KEY, sanitize_text_field( $settings['secret_key'] ?? '' ) );
		update_option( self::OPTION_PUBLISHABLE_KEY, sanitize_text_field( $settings['publishable_key'] ?? '' ) );
		update_option( self::OPTION_WEBHOOK_SECRET, sanitize_text_field( $settings['webhook_secret'] ?? '' ) );
		update_option( self::OPTION_TEST_MODE, ! empty( $settings['test_mode'] ) ? 1 : 0 );
	}

	/**
	 * Create a Stripe Checkout Session for a stored order.
	 *
	 * @param object $order  Order row from database.
	 * @param array  $totals Calculated totals.
	 * @return array{session_id: string, checkout_url: string, payload: array<string, mixed>}|WP_Error
	 */
	public function create_checkout_session( $order, array $totals ) {
		$payload = $this->build_session_payload( $order, $totals );

		if ( ! $this->is_configured() ) {
			return new WP_Error(
				'vintrica_stripe_not_configured',
				__( 'Stripe platba nie je nakonfigurovaná. Kontaktujte prevádzkovateľa webu.', 'vintrica-vignette-form' )
			);
		}

		$response = wp_remote_post(
			'https://api.stripe.com/v1/checkout/sessions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->get_secret_key(),
				),
				'body'    => $this->encode_session_body( $payload ),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Stripe request failed', $response->get_error_message() );

			return new WP_Error(
				'vintrica_stripe_request_failed',
				__( 'Stripe platbu sa nepodarilo inicializovať. Skúste to prosím neskôr.', 'vintrica-vignette-form' )
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 || ! is_array( $body ) ) {
			$this->log_error(
				'Stripe API error',
				array(
					'status_code' => $status_code,
					'body'        => $body,
				)
			);

			return new WP_Error(
				'vintrica_stripe_api_error',
				__( 'Stripe platbu sa nepodarilo inicializovať. Skúste to prosím neskôr.', 'vintrica-vignette-form' )
			);
		}

		if ( empty( $body['url'] ) ) {
			$this->log_error( 'Stripe session missing checkout URL', $body );

			return new WP_Error(
				'vintrica_stripe_missing_url',
				__( 'Stripe nevrátilo platobnú adresu. Skúste to prosím neskôr.', 'vintrica-vignette-form' )
			);
		}

		return array(
			'session_id'   => isset( $body['id'] ) ? (string) $body['id'] : '',
			'checkout_url' => (string) $body['url'],
			'payload'      => $payload,
		);
	}

	/**
	 * Verify Stripe webhook signature.
	 *
	 * @param string $payload    Raw request body.
	 * @param string $sig_header Stripe-Signature header.
	 * @return bool
	 */
	public function verify_webhook_signature( $payload, $sig_header ) {
		$secret = $this->get_webhook_secret();

		if ( '' === trim( $secret ) || '' === trim( $sig_header ) ) {
			return false;
		}

		$timestamp = null;
		$signatures  = array();
		$parts       = explode( ',', $sig_header );

		foreach ( $parts as $part ) {
			$pair = explode( '=', trim( $part ), 2 );

			if ( 2 !== count( $pair ) ) {
				continue;
			}

			if ( 't' === $pair[0] ) {
				$timestamp = $pair[1];
			}

			if ( 'v1' === $pair[0] ) {
				$signatures[] = $pair[1];
			}
		}

		if ( null === $timestamp || empty( $signatures ) ) {
			return false;
		}

		if ( abs( time() - (int) $timestamp ) > 300 ) {
			return false;
		}

		$signed_payload = $timestamp . '.' . $payload;
		$expected       = hash_hmac( 'sha256', $signed_payload, $secret );

		foreach ( $signatures as $signature ) {
			if ( hash_equals( $expected, $signature ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Handle a verified Stripe webhook event.
	 *
	 * @param array $event Stripe event payload.
	 * @return void
	 */
	public function handle_webhook_event( array $event ) {
		$type    = sanitize_text_field( $event['type'] );
		$object  = isset( $event['data']['object'] ) && is_array( $event['data']['object'] ) ? $event['data']['object'] : array();
		$orders  = vintrica_vignette_form()->orders;
		$order   = $this->find_order_from_event_object( $object );

		switch ( $type ) {
			case 'checkout.session.completed':
				if ( $order && 'paid' === ( $object['payment_status'] ?? '' ) ) {
					$orders->mark_as_paid(
						(int) $order->id,
						isset( $object['payment_intent'] ) ? (string) $object['payment_intent'] : ''
					);
				}
				break;

			case 'checkout.session.expired':
				if ( $order && Vintrica_Orders::STATUS_PAID !== $orders->normalize_status( $order->status ) ) {
					$orders->update_status( (int) $order->id, Vintrica_Orders::STATUS_CANCELLED );
				}
				break;

			case 'payment_intent.payment_failed':
				if ( $order && Vintrica_Orders::STATUS_PAID !== $orders->normalize_status( $order->status ) ) {
					$orders->update_status( (int) $order->id, Vintrica_Orders::STATUS_UNPAID );
				}
				break;
		}

		/**
		 * Fires after a Stripe webhook event is processed.
		 *
		 * @param array       $event Stripe event payload.
		 * @param object|null $order Matching VINTRICA order if found.
		 */
		do_action( 'vintrica_stripe_webhook_processed', $event, $order );
	}

	/**
	 * Find an order from a Stripe event object.
	 *
	 * @param array $object Stripe object payload.
	 * @return object|null
	 */
	private function find_order_from_event_object( array $object ) {
		$orders = vintrica_vignette_form()->orders;

		if ( ! empty( $object['id'] ) && 0 === strpos( (string) $object['id'], 'cs_' ) ) {
			$order = $orders->get_order_by_stripe_session_id( (string) $object['id'] );

			if ( $order ) {
				return $order;
			}
		}

		if ( ! empty( $object['metadata']['vintrica_order_id'] ) ) {
			return $orders->get_order( (int) $object['metadata']['vintrica_order_id'] );
		}

		if ( ! empty( $object['client_reference_id'] ) ) {
			return $orders->get_order_by_number( (string) $object['client_reference_id'] );
		}

		return null;
	}

	/**
	 * Build Stripe Checkout Session payload.
	 *
	 * @param object $order  Order row.
	 * @param array  $totals Calculated totals.
	 * @return array<string, mixed>
	 */
	private function build_session_payload( $order, array $totals ) {
		$billing = json_decode( (string) $order->billing, true );
		$email   = is_array( $billing ) && ! empty( $billing['email'] ) ? sanitize_email( $billing['email'] ) : '';

		$payload = array(
			'mode'                => 'payment',
			'client_reference_id' => $order->order_number,
			'customer_email'      => $email,
			'metadata'            => array(
				'vintrica_order_id'     => (string) (int) $order->id,
				'vintrica_order_number' => $order->order_number,
			),
			'line_items'          => $this->build_line_items( $order, $totals ),
			'success_url'         => $this->get_success_url( $order->order_number ),
			'cancel_url'          => $this->get_cancel_url( $order->order_number ),
		);

		/**
		 * Filter the prepared Stripe Checkout Session payload.
		 *
		 * @param array  $payload Prepared session payload.
		 * @param object $order   Order row.
		 */
		return apply_filters( 'vintrica_stripe_checkout_session_payload', $payload, $order );
	}

	/**
	 * Build Stripe line items from server-side totals.
	 *
	 * @param object $order  Order row.
	 * @param array  $totals Calculated totals.
	 * @return array<int, array<string, mixed>>
	 */
	private function build_line_items( $order, array $totals ) {
		$items = array(
			array(
				'price_data' => array(
					'currency'     => strtolower( $order->currency ),
					'unit_amount'  => (int) round( (float) $totals['subtotal'] * 100 ),
					'product_data' => array(
						'name' => __( 'Diaľničné známky', 'vintrica-vignette-form' ),
					),
				),
				'quantity'   => 1,
			),
		);

		if ( (float) $totals['service_fee'] > 0 ) {
			$items[] = array(
				'price_data' => array(
					'currency'     => strtolower( $order->currency ),
					'unit_amount'  => (int) round( (float) $totals['service_fee'] * 100 ),
					'product_data' => array(
						'name' => __( 'Servisný poplatok', 'vintrica-vignette-form' ),
					),
				),
				'quantity'   => 1,
			);
		}

		return $items;
	}

	/**
	 * Encode nested payload for Stripe form body.
	 *
	 * @param array $payload Session payload.
	 * @return array<string, string>
	 */
	private function encode_session_body( array $payload ) {
		$body = array(
			'mode'                => $payload['mode'],
			'client_reference_id' => $payload['client_reference_id'],
			'success_url'         => $payload['success_url'],
			'cancel_url'          => $payload['cancel_url'],
		);

		if ( ! empty( $payload['customer_email'] ) ) {
			$body['customer_email'] = $payload['customer_email'];
		}

		if ( ! empty( $payload['metadata'] ) && is_array( $payload['metadata'] ) ) {
			foreach ( $payload['metadata'] as $key => $value ) {
				$body[ 'metadata[' . $key . ']' ] = (string) $value;
			}
		}

		if ( ! empty( $payload['line_items'] ) && is_array( $payload['line_items'] ) ) {
			foreach ( $payload['line_items'] as $index => $item ) {
				$body[ 'line_items[' . $index . '][quantity]' ] = (string) (int) ( $item['quantity'] ?? 1 );

				if ( ! empty( $item['price_data'] ) && is_array( $item['price_data'] ) ) {
					$price = $item['price_data'];
					$body[ 'line_items[' . $index . '][price_data][currency]' ]    = (string) $price['currency'];
					$body[ 'line_items[' . $index . '][price_data][unit_amount]' ] = (string) (int) $price['unit_amount'];

					if ( ! empty( $price['product_data']['name'] ) ) {
						$body[ 'line_items[' . $index . '][price_data][product_data][name]' ] = (string) $price['product_data']['name'];
					}
				}
			}
		}

		return $body;
	}

	/**
	 * Build Stripe success URL.
	 *
	 * @param string $order_number Order number.
	 * @return string
	 */
	private function get_success_url( $order_number ) {
		return add_query_arg(
			array(
				'vintrica_order' => rawurlencode( $order_number ),
				'vintrica_paid'  => '1',
			),
			home_url( '/' )
		);
	}

	/**
	 * Build Stripe cancel URL.
	 *
	 * @param string $order_number Order number.
	 * @return string
	 */
	private function get_cancel_url( $order_number ) {
		return add_query_arg(
			array(
				'vintrica_order'     => rawurlencode( $order_number ),
				'vintrica_cancelled' => '1',
			),
			wp_get_referer() ? wp_get_referer() : home_url( '/' )
		);
	}

	/**
	 * Log Stripe errors for administrators and debug mode.
	 *
	 * @param string       $message Log message.
	 * @param array|string $context Additional context.
	 * @return void
	 */
	private function log_error( $message, $context = '' ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		if ( is_array( $context ) || is_object( $context ) ) {
			$context = wp_json_encode( $context );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[VINTRICA Stripe] ' . $message . ( $context ? ': ' . $context : '' ) );
	}
}
