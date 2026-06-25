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
	 * Option key for success redirect URL.
	 */
	const OPTION_SUCCESS_REDIRECT_URL = 'vintrica_stripe_success_redirect_url';

	/**
	 * Option key for cancel redirect URL.
	 */
	const OPTION_CANCEL_REDIRECT_URL = 'vintrica_stripe_cancel_redirect_url';

	/**
	 * Stripe Checkout product name.
	 */
	const CHECKOUT_PRODUCT_NAME = 'Diaľničné známky VINTRICA';

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
	 * Get configured success redirect URL (empty when fallback is used).
	 *
	 * @return string
	 */
	public function get_configured_success_redirect_url() {
		return (string) get_option( self::OPTION_SUCCESS_REDIRECT_URL, '' );
	}

	/**
	 * Get configured cancel redirect URL (empty when fallback is used).
	 *
	 * @return string
	 */
	public function get_configured_cancel_redirect_url() {
		return (string) get_option( self::OPTION_CANCEL_REDIRECT_URL, '' );
	}

	/**
	 * Get base success redirect URL with fallback.
	 *
	 * @return string
	 */
	public function get_success_redirect_base_url() {
		$url = esc_url_raw( trim( $this->get_configured_success_redirect_url() ) );

		if ( '' === $url ) {
			return home_url( '/dakujeme/' );
		}

		return $url;
	}

	/**
	 * Get base cancel redirect URL with fallback.
	 *
	 * @return string
	 */
	public function get_cancel_redirect_base_url() {
		$url = esc_url_raw( trim( $this->get_configured_cancel_redirect_url() ) );

		if ( '' === $url ) {
			return home_url( '/platba-neuspesna/' );
		}

		return $url;
	}

	/**
	 * Sanitize and validate a redirect URL setting.
	 *
	 * @param string $url         Raw URL.
	 * @param string $field_label Admin field label for errors.
	 * @return string|WP_Error Sanitized URL or empty string.
	 */
	public function sanitize_redirect_url_setting( $url, $field_label ) {
		$url = trim( (string) $url );

		if ( '' === $url ) {
			return '';
		}

		$sanitized = esc_url_raw( $url );

		if ( '' === $sanitized || ! wp_http_validate_url( $sanitized ) ) {
			return new WP_Error(
				'vintrica_invalid_redirect_url',
				sprintf(
					/* translators: %s: admin field label */
					__( '%s musí byť platná URL adresa.', 'vintrica-vignette-form' ),
					$field_label
				)
			);
		}

		return $sanitized;
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
	 * Detect secret key prefix for diagnostics.
	 *
	 * @return string One of: sk_test, sk_live, missing, invalid.
	 */
	public function get_secret_key_prefix() {
		$key = trim( $this->get_secret_key() );

		if ( '' === $key ) {
			return 'missing';
		}

		if ( 0 === strpos( $key, 'sk_test_' ) ) {
			return 'sk_test';
		}

		if ( 0 === strpos( $key, 'sk_live_' ) ) {
			return 'sk_live';
		}

		return 'invalid';
	}

	/**
	 * Validate secret key matches selected test/live mode.
	 *
	 * @return true|WP_Error
	 */
	public function validate_secret_key_mode() {
		$prefix = $this->get_secret_key_prefix();

		if ( 'missing' === $prefix ) {
			return new WP_Error(
				'vintrica_stripe_missing_key',
				__( 'Secret Key nie je nastavený.', 'vintrica-vignette-form' )
			);
		}

		if ( 'invalid' === $prefix ) {
			return new WP_Error(
				'vintrica_stripe_invalid_key',
				__( 'Secret Key má neplatný formát. Musí začínať na sk_test_ alebo sk_live_.', 'vintrica-vignette-form' )
			);
		}

		if ( $this->is_test_mode() && 'sk_test' !== $prefix ) {
			return new WP_Error(
				'vintrica_stripe_key_mode_mismatch',
				__( 'Testovací režim je zapnutý, ale Secret Key nezačína na sk_test_.', 'vintrica-vignette-form' )
			);
		}

		if ( ! $this->is_test_mode() && 'sk_live' !== $prefix ) {
			return new WP_Error(
				'vintrica_stripe_key_mode_mismatch',
				__( 'Testovací režim je vypnutý, ale Secret Key nezačína na sk_live_.', 'vintrica-vignette-form' )
			);
		}

		return true;
	}

	/**
	 * Get Slovak admin warning when keys do not match mode.
	 *
	 * @return string
	 */
	public function get_key_mode_warning() {
		$validation = $this->validate_secret_key_mode();

		if ( true === $validation ) {
			return '';
		}

		return $validation->get_error_message();
	}

	/**
	 * Build diagnostic data for the admin settings panel.
	 *
	 * @param string $sample_order_number Sample order number for URL preview.
	 * @return array<string, mixed>
	 */
	public function get_diagnostics( $sample_order_id = 123, $sample_token = 'abc' ) {
		return array(
			'test_mode'                 => $this->is_test_mode(),
			'has_secret_key'            => '' !== trim( $this->get_secret_key() ),
			'has_publishable_key'       => '' !== trim( $this->get_publishable_key() ),
			'has_webhook_secret'        => '' !== trim( $this->get_webhook_secret() ),
			'key_prefix'                => $this->get_secret_key_prefix(),
			'success_redirect_base_url' => $this->get_success_redirect_base_url(),
			'cancel_redirect_base_url'  => $this->get_cancel_redirect_base_url(),
			'success_url'               => $this->get_success_url( $sample_order_id, $sample_token ),
			'cancel_url'                => $this->get_cancel_url( $sample_order_id, $sample_token ),
			'key_warning'               => $this->get_key_mode_warning(),
		);
	}

	/**
	 * Test Stripe API connectivity using the configured secret key.
	 *
	 * @return true|WP_Error
	 */
	public function test_connection() {
		$validation = $this->validate_secret_key_mode();

		if ( is_wp_error( $validation ) ) {
			$this->log_error( 'Stripe connection test failed (key validation)', $validation->get_error_message() );
			return $validation;
		}

		$response = wp_remote_get(
			'https://api.stripe.com/v1/balance',
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->get_secret_key(),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Stripe connection test request failed', $response->get_error_message() );

			return new WP_Error(
				'vintrica_stripe_test_request_failed',
				__( 'Nepodarilo sa kontaktovať Stripe API. Skontrolujte sieťovú konektivitu servera.', 'vintrica-vignette-form' )
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $status_code >= 200 && $status_code < 300 ) {
			$this->log_debug(
				'Stripe connection test succeeded',
				array(
					'status_code' => $status_code,
					'key_prefix'  => $this->get_secret_key_prefix(),
					'test_mode'   => $this->is_test_mode(),
				)
			);

			return true;
		}

		$this->log_error(
			'Stripe connection test failed',
			array(
				'status_code'    => $status_code,
				'stripe_message' => $this->extract_stripe_error_message( $body ),
				'body'           => $body,
			)
		);

		return new WP_Error(
			'vintrica_stripe_test_failed',
			$this->get_admin_stripe_error_message( $body, __( 'Stripe pripojenie zlyhalo. Skontrolujte Secret Key a režim test/live.', 'vintrica-vignette-form' ) )
		);
	}

	/**
	 * Save Stripe settings.
	 *
	 * @param array $settings Settings payload.
	 * @return true|WP_Error
	 */
	public function save_settings( array $settings ) {
		update_option( self::OPTION_SECRET_KEY, sanitize_text_field( $settings['secret_key'] ?? '' ) );
		update_option( self::OPTION_PUBLISHABLE_KEY, sanitize_text_field( $settings['publishable_key'] ?? '' ) );
		update_option( self::OPTION_WEBHOOK_SECRET, sanitize_text_field( $settings['webhook_secret'] ?? '' ) );
		update_option( self::OPTION_TEST_MODE, ! empty( $settings['test_mode'] ) ? 1 : 0 );

		if ( array_key_exists( 'success_redirect_url', $settings ) ) {
			$success_redirect_url = $this->sanitize_redirect_url_setting(
				$settings['success_redirect_url'],
				__( 'URL ďakovnej stránky po úspešnej platbe', 'vintrica-vignette-form' )
			);

			if ( is_wp_error( $success_redirect_url ) ) {
				return $success_redirect_url;
			}

			update_option( self::OPTION_SUCCESS_REDIRECT_URL, $success_redirect_url );
		}

		if ( array_key_exists( 'cancel_redirect_url', $settings ) ) {
			$cancel_redirect_url = $this->sanitize_redirect_url_setting(
				$settings['cancel_redirect_url'],
				__( 'URL stránky po neúspešnej alebo zrušenej platbe', 'vintrica-vignette-form' )
			);

			if ( is_wp_error( $cancel_redirect_url ) ) {
				return $cancel_redirect_url;
			}

			update_option( self::OPTION_CANCEL_REDIRECT_URL, $cancel_redirect_url );
		}

		return true;
	}

	/**
	 * Create a Stripe Checkout Session for a stored order.
	 *
	 * @param object $order  Order row from database.
	 * @param array  $totals Calculated totals.
	 * @return array{session_id: string, checkout_url: string, payload: array<string, mixed>}|WP_Error
	 */
	public function create_checkout_session( $order, array $totals ) {
		$key_validation = $this->validate_secret_key_mode();

		if ( is_wp_error( $key_validation ) ) {
			$this->log_error( 'Stripe checkout blocked by key validation', $key_validation->get_error_message() );

			return new WP_Error(
				'vintrica_stripe_not_configured',
				__( 'Stripe nie je správne nastavený.', 'vintrica-vignette-form' )
			);
		}

		$unit_amount = $this->get_total_amount_cents( $totals );

		if ( $unit_amount < 50 ) {
			$this->log_error(
				'Stripe checkout amount below minimum',
				array(
					'order_id'     => (int) $order->id,
					'order_number' => $order->order_number,
					'unit_amount'  => $unit_amount,
					'totals'       => $totals,
				)
			);

			return new WP_Error(
				'vintrica_stripe_invalid_amount',
				__( 'Stripe platbu sa nepodarilo inicializovať. Skúste to prosím neskôr.', 'vintrica-vignette-form' )
			);
		}

		$payload = $this->build_session_payload( $order, $totals, $unit_amount );
		$body    = $this->encode_session_body( $payload );

		$this->log_debug(
			'Creating Stripe Checkout Session',
			array(
				'order_id'     => (int) $order->id,
				'order_number' => $order->order_number,
				'unit_amount'  => $unit_amount,
				'currency'     => $payload['line_items'][0]['price_data']['currency'] ?? 'eur',
				'success_url'  => $payload['success_url'],
				'cancel_url'   => $payload['cancel_url'],
				'test_mode'    => $this->is_test_mode(),
				'key_prefix'   => $this->get_secret_key_prefix(),
				'request_body' => $body,
			)
		);

		$response = wp_remote_post(
			'https://api.stripe.com/v1/checkout/sessions',
			array(
				'timeout' => 20,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->get_secret_key(),
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_error( 'Stripe checkout request failed', $response->get_error_message() );

			return new WP_Error(
				'vintrica_stripe_request_failed',
				__( 'Stripe platbu sa nepodarilo inicializovať. Skúste to prosím neskôr.', 'vintrica-vignette-form' )
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$raw_body    = (string) wp_remote_retrieve_body( $response );
		$body        = json_decode( $raw_body, true );

		if ( $status_code < 200 || $status_code >= 300 || ! is_array( $body ) ) {
			$this->log_error(
				'Stripe checkout API error',
				array(
					'status_code'    => $status_code,
					'stripe_message' => $this->extract_stripe_error_message( $body ),
					'raw_body'       => $raw_body,
					'request_body'   => $this->encode_session_body( $payload ),
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

		$this->log_debug(
			'Stripe Checkout Session created',
			array(
				'order_id'     => (int) $order->id,
				'order_number' => $order->order_number,
				'session_id'   => isset( $body['id'] ) ? (string) $body['id'] : '',
			)
		);

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
	 * @param object $order       Order row.
	 * @param array  $totals      Calculated totals.
	 * @param int    $unit_amount Total amount in cents.
	 * @return array<string, mixed>
	 */
	private function build_session_payload( $order, array $totals, $unit_amount ) {
		$billing  = json_decode( (string) $order->billing, true );
		$email    = is_array( $billing ) && ! empty( $billing['email'] ) ? sanitize_email( $billing['email'] ) : '';
		$currency = strtolower( (string) $order->currency );
		$orders   = vintrica_vignette_form()->orders;
		$token    = $orders->ensure_redirect_token( (int) $order->id );

		if ( '' === $currency ) {
			$currency = 'eur';
		}

		$payload = array(
			'mode'                => 'payment',
			'client_reference_id' => $order->order_number,
			'customer_email'      => $email,
			'metadata'            => array(
				'vintrica_order_id'     => (string) (int) $order->id,
				'vintrica_order_number' => $order->order_number,
			),
			'line_items'          => array(
				array(
					'price_data' => array(
						'currency'     => $currency,
						'unit_amount'  => $unit_amount,
						'product_data' => array(
							'name' => self::CHECKOUT_PRODUCT_NAME,
						),
					),
					'quantity'   => 1,
				),
			),
			'success_url'         => $this->get_success_url( (int) $order->id, $token ),
			'cancel_url'          => $this->get_cancel_url( (int) $order->id, $token ),
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
	 * Convert order totals to Stripe cents.
	 *
	 * @param array $totals Calculated totals.
	 * @return int
	 */
	private function get_total_amount_cents( array $totals ) {
		$total = isset( $totals['total'] ) ? (float) $totals['total'] : 0.0;

		return (int) round( $total * 100 );
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
	 * Build Stripe success URL with order ID and secure token.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $token    Redirect token.
	 * @return string
	 */
	public function get_success_url( $order_id, $token ) {
		return add_query_arg(
			array(
				'vintrica_order_id' => (int) $order_id,
				'token'             => sanitize_text_field( (string) $token ),
			),
			$this->get_success_redirect_base_url()
		);
	}

	/**
	 * Build Stripe cancel URL with order ID and secure token.
	 *
	 * @param int    $order_id Order ID.
	 * @param string $token    Redirect token.
	 * @return string
	 */
	public function get_cancel_url( $order_id, $token ) {
		return add_query_arg(
			array(
				'vintrica_order_id' => (int) $order_id,
				'token'             => sanitize_text_field( (string) $token ),
			),
			$this->get_cancel_redirect_base_url()
		);
	}

	/**
	 * Extract Stripe error message from API response body.
	 *
	 * @param mixed $body Decoded Stripe response.
	 * @return string
	 */
	private function extract_stripe_error_message( $body ) {
		if ( is_array( $body ) && ! empty( $body['error']['message'] ) ) {
			return (string) $body['error']['message'];
		}

		return '';
	}

	/**
	 * Build an admin-safe Stripe error message including API detail.
	 *
	 * @param mixed  $body           Decoded Stripe response.
	 * @param string $fallback_message Fallback message.
	 * @return string
	 */
	private function get_admin_stripe_error_message( $body, $fallback_message ) {
		$stripe_message = $this->extract_stripe_error_message( $body );

		if ( '' === $stripe_message ) {
			return $fallback_message;
		}

		return sprintf(
			/* translators: %s: Stripe API error message */
			__( 'Stripe API: %s', 'vintrica-vignette-form' ),
			$stripe_message
		);
	}

	/**
	 * Log Stripe debug details for administrators.
	 *
	 * @param string       $message Log message.
	 * @param array|string $context Additional context.
	 * @return void
	 */
	private function log_debug( $message, $context = '' ) {
		if ( ! defined( 'WP_DEBUG' ) || ! WP_DEBUG ) {
			return;
		}

		$this->write_log( $message, $context );
	}

	/**
	 * Log Stripe errors for administrators (always written to error_log).
	 *
	 * @param string       $message Log message.
	 * @param array|string $context Additional context.
	 * @return void
	 */
	private function log_error( $message, $context = '' ) {
		$this->write_log( $message, $context );
	}

	/**
	 * Write a log line to PHP error_log.
	 *
	 * @param string       $message Log message.
	 * @param array|string $context Additional context.
	 * @return void
	 */
	private function write_log( $message, $context = '' ) {
		if ( is_array( $context ) || is_object( $context ) ) {
			$context = wp_json_encode( $context );
		}

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( '[VINTRICA Stripe] ' . $message . ( $context ? ': ' . $context : '' ) );
	}
}
