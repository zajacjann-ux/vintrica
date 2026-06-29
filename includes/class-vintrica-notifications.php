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
	 * Notification context: new unpaid order.
	 */
	const CONTEXT_CREATED = 'created';

	/**
	 * Notification context: paid order.
	 */
	const CONTEXT_PAID = 'paid';

	/**
	 * Notification context: admin test email.
	 */
	const CONTEXT_TEST = 'test';

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

		add_action( 'vintrica_order_created', array( $this, 'send_admin_created_notification' ), 10, 1 );
		add_action( 'vintrica_order_paid', array( $this, 'send_admin_paid_notification' ), 10, 2 );
	}

	/**
	 * Send admin notification when a new unpaid order is created.
	 *
	 * @param object $order Order row.
	 * @return bool
	 */
	public function send_admin_created_notification( $order ) {
		if ( ! $order ) {
			return false;
		}

		return $this->send_order_notification( $order, self::CONTEXT_CREATED );
	}

	/**
	 * Send admin notification after successful payment.
	 *
	 * @param int    $order_id          Order ID.
	 * @param string $payment_intent_id Stripe payment intent ID.
	 * @return bool
	 */
	public function send_admin_paid_notification( $order_id, $payment_intent_id = '' ) {
		unset( $payment_intent_id );

		$order = $this->orders->get_order( (int) $order_id );

		if ( ! $order ) {
			return false;
		}

		return $this->send_order_notification( $order, self::CONTEXT_PAID );
	}

	/**
	 * Send a test notification email to the configured recipient.
	 *
	 * @return true|WP_Error
	 */
	public function send_test_notification() {
		$recipient = $this->settings->get_notification_email();

		if ( '' === $recipient || ! is_email( $recipient ) ) {
			return new WP_Error(
				'vintrica_notification_test_invalid_email',
				__( 'Nie je nastavená platná e-mailová adresa pre notifikácie.', 'vintrica-vignette-form' )
			);
		}

		$subject = __( 'e-vignetta.eu – testovací e-mail notifikácií', 'vintrica-vignette-form' );
		$body    = Vintrica_Email_Template::render_document(
			__( 'Testovací e-mail notifikácií e-vignetta.eu.', 'vintrica-vignette-form' ),
			Vintrica_Email_Template::render_heading( __( 'Testovací e-mail', 'vintrica-vignette-form' ) )
			. Vintrica_Email_Template::render_paragraph( __( 'Toto je testovací e-mail z e-vignetta.eu – Elektronické diaľničné známky.', 'vintrica-vignette-form' ) )
			. Vintrica_Email_Template::render_paragraph( __( 'Ak ste tento e-mail dostali, notifikačný kanál je správne nakonfigurovaný.', 'vintrica-vignette-form' ) )
			. Vintrica_Email_Template::render_info_card(
				__( 'Detaily testu', 'vintrica-vignette-form' ),
				array(
					__( 'Web', 'vintrica-vignette-form' )       => home_url( '/' ),
					__( 'Príjemca', 'vintrica-vignette-form' )  => $recipient,
					__( 'Odoslané', 'vintrica-vignette-form' )  => wp_date( 'd.m.Y H:i' ),
				)
			)
		);

		if ( ! $this->dispatch_mail( $recipient, $subject, $body, 'test' ) ) {
			return new WP_Error(
				'vintrica_notification_test_failed',
				__( 'Testovací e-mail sa nepodarilo odoslať. Skontrolujte nastavenie odosielania e-mailov na serveri.', 'vintrica-vignette-form' )
			);
		}

		return true;
	}

	/**
	 * Send an order notification email.
	 *
	 * @param object $order   Order row.
	 * @param string $context Notification context.
	 * @return bool
	 */
	private function send_order_notification( $order, $context ) {
		$recipient = $this->settings->get_notification_email();

		if ( '' === $recipient || ! is_email( $recipient ) ) {
			$this->log_mail_failure( $context, $order, 'missing_recipient' );
			return false;
		}

		$subject = $this->get_subject_for_context( $context );
		$body    = $this->build_admin_order_email_body( $order, $context );

		return $this->dispatch_mail( $recipient, $subject, $body, $context, $order );
	}

	/**
	 * Get email subject for a notification context.
	 *
	 * @param string $context Notification context.
	 * @return string
	 */
	private function get_subject_for_context( $context ) {
		if ( self::CONTEXT_PAID === $context ) {
			return __( 'Nová objednávka e-vignetta.eu – uhradená', 'vintrica-vignette-form' );
		}

		return __( 'Nová objednávka e-vignetta.eu – čaká na platbu', 'vintrica-vignette-form' );
	}

	/**
	 * Dispatch email via wp_mail with safe failure logging.
	 *
	 * @param string      $recipient Recipient email.
	 * @param string      $subject   Email subject.
	 * @param string      $body      Email body.
	 * @param string      $context   Notification context.
	 * @param object|null $order     Optional order row.
	 * @return bool
	 */
	private function dispatch_mail( $recipient, $subject, $body, $context, $order = null ) {
		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		$sent    = wp_mail( $recipient, $subject, $body, $headers );

		if ( ! $sent ) {
			$this->log_mail_failure( $context, $order, 'wp_mail_failed' );
		}

		return $sent;
	}

	/**
	 * Log email delivery failures without exposing sensitive data.
	 *
	 * @param string      $context Failure context.
	 * @param object|null $order   Optional order row.
	 * @param string      $reason  Failure reason code.
	 * @return void
	 */
	private function log_mail_failure( $context, $order, $reason ) {
		$order_ref = ( $order && ! empty( $order->order_number ) )
			? sanitize_text_field( (string) $order->order_number )
			: 'n/a';

		error_log(
			sprintf(
				'[VINTRICA] Admin notification failed (%1$s/%2$s) for order %3$s.',
				sanitize_key( $context ),
				sanitize_key( $reason ),
				$order_ref
			)
		);
	}

	/**
	 * Build HTML admin notification body.
	 *
	 * @param object $order   Order row.
	 * @param string $context Notification context.
	 * @return string
	 */
	private function build_admin_order_email_body( $order, $context ) {
		$billing    = $this->orders->decode_billing( $order );
		$vignettes  = $this->orders->decode_vignettes( $order );
		$vehicles   = $this->pricing->get_vehicle_types();
		$statuses   = $this->orders->get_statuses();
		$status     = $this->orders->normalize_status( $order->status );
		$detail_url = admin_url( 'admin.php?page=' . Vintrica_Admin::ORDERS_SLUG . '&order_id=' . (int) $order->id );

		if ( self::CONTEXT_PAID === $context ) {
			$heading      = __( 'Nová uhradená objednávka', 'vintrica-vignette-form' );
			$intro        = __( 'Bola prijatá nová uhradená objednávka e-vignetta.eu.', 'vintrica-vignette-form' );
			$status_label = $statuses[ $status ] ?? $status;
			$preheader    = __( 'Nová uhradená objednávka e-vignetta.eu.', 'vintrica-vignette-form' );
		} else {
			$heading      = __( 'Nová objednávka čaká na platbu', 'vintrica-vignette-form' );
			$intro        = __( 'Bola vytvorená nová objednávka e-vignetta.eu, ktorá čaká na platbu.', 'vintrica-vignette-form' );
			$status_label = __( 'Neuhradená / čaká na platbu', 'vintrica-vignette-form' );
			$preheader    = __( 'Nová objednávka e-vignetta.eu čaká na platbu.', 'vintrica-vignette-form' );
		}

		$content  = Vintrica_Email_Template::render_heading( $heading );
		$content .= Vintrica_Email_Template::render_paragraph( $intro );
		$content .= Vintrica_Email_Template::render_status_card(
			__( 'Stav objednávky', 'vintrica-vignette-form' ),
			$status_label
		);
		$content .= Vintrica_Email_Template::render_info_card(
			__( 'Informácie o objednávke', 'vintrica-vignette-form' ),
			array(
				__( 'Číslo objednávky', 'vintrica-vignette-form' ) => (string) $order->order_number,
				__( 'Meno', 'vintrica-vignette-form' )             => (string) ( $billing['first_name'] ?? '' ),
				__( 'Priezvisko', 'vintrica-vignette-form' )       => (string) ( $billing['last_name'] ?? '' ),
				__( 'E-mail zákazníka', 'vintrica-vignette-form' ) => (string) ( $billing['email'] ?? '' ),
				__( 'Telefón', 'vintrica-vignette-form' )          => (string) ( $billing['phone'] ?? '' ),
			)
		);

		$content .= Vintrica_Email_Template::render_section_title( __( 'Vybrané známky', 'vintrica-vignette-form' ) );

		foreach ( $vignettes as $index => $vignette ) {
			$country_code  = $vignette['country'] ?? '';
			$validity_code = $vignette['vignette_validity'] ?? '';
			$vehicle_type  = $vignette['vehicle_type'] ?? '';
			$price         = $this->pricing->get_vignette_price( $country_code, $validity_code, $vehicle_type );
			$price         = null !== $price ? $price : 0;

			$content .= Vintrica_Email_Template::render_vignette_card(
				sprintf(
					/* translators: %d: vignette index */
					__( 'Známka %d', 'vintrica-vignette-form' ),
					(int) $index + 1
				),
				array(
					__( 'Krajina', 'vintrica-vignette-form' )             => $this->pricing->get_country_label( $country_code ),
					__( 'Platnosť', 'vintrica-vignette-form' )            => $this->pricing->get_validity_label( $country_code, $validity_code, $vehicle_type ),
					__( 'Typ vozidla', 'vintrica-vignette-form' )         => $vehicles[ $vehicle_type ] ?? $vehicle_type,
					__( 'ŠPZ', 'vintrica-vignette-form' )                 => (string) ( $vignette['license_plate'] ?? '' ),
					__( 'Krajina registrácie', 'vintrica-vignette-form' ) => Vintrica_Country_Registry::resolve_label( $vignette['registration_country'] ?? '' ),
					__( 'Dátum začiatku platnosti', 'vintrica-vignette-form' ) => (string) ( $vignette['start_date'] ?? '' ),
				),
				Vintrica_Email_Template::format_money( (string) $order->currency, $price )
			);
		}

		$content .= Vintrica_Email_Template::render_totals_summary(
			(string) $order->currency,
			(float) $order->subtotal,
			(float) $order->service_fee,
			(float) $order->total
		);
		$content .= Vintrica_Email_Template::render_button(
			$detail_url,
			__( 'Detail objednávky v administrácii', 'vintrica-vignette-form' )
		);

		return Vintrica_Email_Template::render_document( $preheader, $content );
	}
}
