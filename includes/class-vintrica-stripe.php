<?php
/**
 * Stripe Checkout Session integration.
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
	 * Save Stripe settings.
	 *
	 * @param array $settings Settings payload.
	 * @return void
	 */
	public function save_settings( array $settings ) {
		update_option( self::OPTION_SECRET_KEY, sanitize_text_field( $settings['secret_key'] ?? '' ) );
		update_option( self::OPTION_PUBLISHABLE_KEY, sanitize_text_field( $settings['publishable_key'] ?? '' ) );
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
			return array(
				'session_id'   => '',
				'checkout_url' => '',
				'payload'      => $payload,
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
			return new WP_Error(
				'vintrica_stripe_request_failed',
				__( 'Stripe platbu sa nepodarilo inicializovať.', 'vintrica-vignette-form' )
			);
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 || ! is_array( $body ) ) {
			$message = isset( $body['error']['message'] ) ? (string) $body['error']['message'] : __( 'Stripe API vrátilo neočakávanú odpoveď.', 'vintrica-vignette-form' );

			return new WP_Error( 'vintrica_stripe_api_error', $message );
		}

		return array(
			'session_id'   => isset( $body['id'] ) ? (string) $body['id'] : '',
			'checkout_url' => isset( $body['url'] ) ? (string) $body['url'] : '',
			'payload'      => $payload,
		);
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
			'mode'                  => 'payment',
			'client_reference_id'   => $order->order_number,
			'customer_email'        => $email,
			'metadata'              => array(
				'vintrica_order_id'     => (string) (int) $order->id,
				'vintrica_order_number' => $order->order_number,
			),
			'line_items'            => $this->build_line_items( $order, $totals ),
			'success_url'           => $this->get_success_url( $order->order_number ),
			'cancel_url'            => $this->get_cancel_url( $order->order_number ),
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
				'vintrica_order'   => rawurlencode( $order_number ),
				'vintrica_cancelled' => '1',
			),
			wp_get_referer() ? wp_get_referer() : home_url( '/' )
		);
	}
}
