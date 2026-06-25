<?php
/**
 * Admin order notification emails.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Notifications
 */
class Vintrica_Notifications {

	/**
	 * Settings handler.
	 *
	 * @var Vintrica_Settings
	 */
	private $settings;

	/**
	 * Orders handler.
	 *
	 * @var Vintrica_Orders
	 */
	private $orders;

	/**
	 * Pricing handler.
	 *
	 * @var Vintrica_Pricing
	 */
	private $pricing;

	/**
	 * Constructor.
	 *
	 * @param Vintrica_Settings $settings Settings handler.
	 * @param Vintrica_Orders   $orders   Orders handler.
	 * @param Vintrica_Pricing  $pricing  Pricing handler.
	 */
	public function __construct( Vintrica_Settings $settings, Vintrica_Orders $orders, Vintrica_Pricing $pricing ) {
		$this->settings = $settings;
		$this->orders   = $orders;
		$this->pricing  = $pricing;

		add_action( 'vintrica_order_paid', array( $this, 'send_admin_order_notification' ), 10, 2 );
	}

	/**
	 * Send admin notification after successful payment.
	 *
	 * @param int    $order_id          Order ID.
	 * @param string $payment_intent_id Stripe payment intent ID.
	 * @return void
	 */
	public function send_admin_order_notification( $order_id, $payment_intent_id = '' ) {
		unset( $payment_intent_id );

		$order = $this->orders->get_order( (int) $order_id );

		if ( ! $order ) {
			return;
		}

		$recipient = $this->settings->get_notification_email();

		if ( '' === $recipient || ! is_email( $recipient ) ) {
			return;
		}

		$subject = __( 'Nová objednávka VINTRICA', 'vintrica-vignette-form' );
		$body    = $this->build_admin_order_email_body( $order );
		$headers = array( 'Content-Type: text/plain; charset=UTF-8' );

		wp_mail( $recipient, $subject, $body, $headers );
	}

	/**
	 * Build plain-text admin notification body.
	 *
	 * @param object $order Order row.
	 * @return string
	 */
	private function build_admin_order_email_body( $order ) {
		$billing      = $this->orders->decode_billing( $order );
		$vignettes    = $this->orders->decode_vignettes( $order );
		$countries    = $this->pricing->get_countries();
		$vehicles     = $this->pricing->get_vehicle_types();
		$statuses     = $this->orders->get_statuses();
		$status       = $this->orders->normalize_status( $order->status );
		$status_label = $statuses[ $status ] ?? $status;
		$detail_url   = admin_url( 'admin.php?page=' . Vintrica_Admin::ORDERS_SLUG . '&order_id=' . (int) $order->id );
		$lines        = array(
			__( 'Bola prijatá nová uhradená objednávka VINTRICA.', 'vintrica-vignette-form' ),
			'',
			__( 'Číslo objednávky:', 'vintrica-vignette-form' ) . ' ' . $order->order_number,
			__( 'Stav objednávky:', 'vintrica-vignette-form' ) . ' ' . $status_label,
			'',
			__( 'Meno zákazníka:', 'vintrica-vignette-form' ) . ' ' . $this->orders->get_customer_name( $order ),
			__( 'E-mail zákazníka:', 'vintrica-vignette-form' ) . ' ' . ( $billing['email'] ?? '' ),
			__( 'Telefón:', 'vintrica-vignette-form' ) . ' ' . ( $billing['phone'] ?? '' ),
			'',
			__( 'Vybrané známky:', 'vintrica-vignette-form' ),
		);

		foreach ( $vignettes as $index => $vignette ) {
			$country_code  = $vignette['country'] ?? '';
			$validity_code = $vignette['vignette_validity'] ?? '';
			$vehicle_type  = $vignette['vehicle_type'] ?? '';
			$price         = $this->pricing->get_vignette_price( $country_code, $validity_code, $vehicle_type );
			$price         = null !== $price ? $price : 0;

			$lines[] = sprintf(
				/* translators: %d: vignette index */
				__( 'Známka %d', 'vintrica-vignette-form' ),
				(int) $index + 1
			);
			$lines[] = '- ' . __( 'Krajina:', 'vintrica-vignette-form' ) . ' ' . ( $countries[ $country_code ] ?? $country_code );
			$lines[] = '- ' . __( 'Platnosť:', 'vintrica-vignette-form' ) . ' ' . $this->pricing->get_validity_label( $country_code, $validity_code, $vehicle_type );
			$lines[] = '- ' . __( 'Typ vozidla:', 'vintrica-vignette-form' ) . ' ' . ( $vehicles[ $vehicle_type ] ?? $vehicle_type );
			$lines[] = '- ' . __( 'ŠPZ:', 'vintrica-vignette-form' ) . ' ' . ( $vignette['license_plate'] ?? '' );
			$lines[] = '- ' . __( 'Krajina registrácie:', 'vintrica-vignette-form' ) . ' ' . ( $countries[ $vignette['registration_country'] ?? '' ] ?? ( $vignette['registration_country'] ?? '' ) );
			$lines[] = '- ' . __( 'Dátum začiatku platnosti:', 'vintrica-vignette-form' ) . ' ' . ( $vignette['start_date'] ?? '' );
			$lines[] = '- ' . __( 'Cena:', 'vintrica-vignette-form' ) . ' ' . $order->currency . ' ' . number_format_i18n( $price, 2 );
			$lines[] = '';
		}

		$lines[] = __( 'Celková suma:', 'vintrica-vignette-form' ) . ' ' . $order->currency . ' ' . number_format_i18n( (float) $order->total, 2 );
		$lines[] = '';
		$lines[] = __( 'Detail objednávky v administrácii:', 'vintrica-vignette-form' );
		$lines[] = $detail_url;

		return implode( "\n", $lines );
	}
}
