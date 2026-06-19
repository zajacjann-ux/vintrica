<?php
/**
 * Stripe Checkout Session integration placeholder.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Stripe
 */
class Vintrica_Stripe {

	/**
	 * Prepare a Stripe Checkout Session payload for a stored order.
	 *
	 * Live Stripe API calls will be implemented in a future release.
	 *
	 * @param object $order    Order row from database.
	 * @param array  $totals   Calculated totals.
	 * @return array{session_id: string, payload: array<string, mixed>}
	 */
	public function prepare_checkout_session( $order, array $totals ) {
		$payload = array(
			'mode'                => 'payment',
			'client_reference_id' => $order->order_number,
			'metadata'            => array(
				'vintrica_order_id'     => (int) $order->id,
				'vintrica_order_number' => $order->order_number,
			),
			'line_items'          => $this->build_line_items( $order, $totals ),
			'success_url'         => $this->get_success_url( $order->order_number ),
			'cancel_url'          => $this->get_cancel_url(),
		);

		/**
		 * Filter the prepared Stripe Checkout Session payload.
		 *
		 * @param array  $payload Prepared session payload.
		 * @param object $order   Order row.
		 */
		$payload = apply_filters( 'vintrica_stripe_checkout_session_payload', $payload, $order );

		return array(
			'session_id' => '',
			'payload'    => $payload,
		);
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
	 * Build the future Stripe success URL.
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
	 * Build the future Stripe cancel URL.
	 *
	 * @return string
	 */
	private function get_cancel_url() {
		return wp_get_referer() ? wp_get_referer() : home_url( '/' );
	}
}
