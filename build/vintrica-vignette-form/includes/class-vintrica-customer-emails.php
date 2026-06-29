<?php
/**
 * Customer-facing HTML order emails.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Customer_Emails
 */
class Vintrica_Customer_Emails {

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
	 * Order IDs queued for post-checkout customer email delivery.
	 *
	 * @var array<int, int>
	 */
	private $queued_created_orders = array();

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

		add_action( 'vintrica_order_created', array( $this, 'queue_created_order_email' ), 10, 1 );
		add_action( 'shutdown', array( $this, 'send_queued_created_order_emails' ), 20 );
		add_action( 'vintrica_order_paid', array( $this, 'send_paid_order_email' ), 10, 2 );
	}

	/**
	 * Queue customer email after order creation so Stripe URL can be resolved on shutdown.
	 *
	 * @param object $order Order row.
	 * @return void
	 */
	public function queue_created_order_email( $order ) {
		if ( ! $order || empty( $order->id ) ) {
			return;
		}

		if ( ! $this->settings->is_customer_email_on_created_enabled() ) {
			return;
		}

		$this->queued_created_orders[ (int) $order->id ] = (int) $order->id;
	}

	/**
	 * Send queued order-created customer emails after checkout finishes in the same request.
	 *
	 * @return void
	 */
	public function send_queued_created_order_emails() {
		if ( empty( $this->queued_created_orders ) ) {
			return;
		}

		foreach ( $this->queued_created_orders as $order_id ) {
			$order = $this->orders->get_order( (int) $order_id );

			if ( ! $order ) {
				continue;
			}

			$this->send_created_order_email( $order );
		}
	}

	/**
	 * Send customer email after successful payment.
	 *
	 * @param int    $order_id          Order ID.
	 * @param string $payment_intent_id Stripe payment intent ID.
	 * @return bool
	 */
	public function send_paid_order_email( $order_id, $payment_intent_id = '' ) {
		unset( $payment_intent_id );

		if ( ! $this->settings->is_customer_email_on_paid_enabled() ) {
			return false;
		}

		$order = $this->orders->get_order( (int) $order_id );

		if ( ! $order ) {
			return false;
		}

		if ( $this->was_email_sent( 'paid', (int) $order->id ) ) {
			return true;
		}

		$recipient = $this->get_customer_email( $order );

		if ( '' === $recipient ) {
			$this->log_mail_failure( 'customer_paid', $order, 'missing_recipient' );
			return false;
		}

		$subject = __( 'Vaša platba bola úspešne prijatá', 'vintrica-vignette-form' );
		$body    = $this->build_paid_email_html( $order );
		$sent    = $this->dispatch_mail( $recipient, $subject, $body, 'customer_paid', $order );

		if ( $sent ) {
			$this->mark_email_sent( 'paid', (int) $order->id );
		}

		return $sent;
	}

	/**
	 * Send customer email after order creation.
	 *
	 * @param object $order Order row.
	 * @return bool
	 */
	private function send_created_order_email( $order ) {
		if ( $this->was_email_sent( 'created', (int) $order->id ) ) {
			return true;
		}

		$recipient = $this->get_customer_email( $order );

		if ( '' === $recipient ) {
			$this->log_mail_failure( 'customer_created', $order, 'missing_recipient' );
			return false;
		}

		$checkout_url = $this->resolve_checkout_url( $order );
		$subject      = __( 'Vaša objednávka bola prijatá', 'vintrica-vignette-form' );
		$body         = $this->build_created_email_html( $order, $checkout_url );
		$sent         = $this->dispatch_mail( $recipient, $subject, $body, 'customer_created', $order );

		if ( $sent ) {
			$this->mark_email_sent( 'created', (int) $order->id );
		}

		return $sent;
	}

	/**
	 * Build HTML body for order-created email.
	 *
	 * @param object $order        Order row.
	 * @param string $checkout_url Stripe Checkout URL.
	 * @return string
	 */
	private function build_created_email_html( $order, $checkout_url ) {
		$billing     = $this->orders->decode_billing( $order );
		$first_name  = isset( $billing['first_name'] ) ? trim( (string) $billing['first_name'] ) : '';
		$greeting    = '' !== $first_name
			? sprintf(
				/* translators: %s: customer first name */
				__( 'Dobrý deň, %s,', 'vintrica-vignette-form' ),
				$first_name
			)
			: __( 'Dobrý deň,', 'vintrica-vignette-form' );

		$content  = Vintrica_Email_Template::render_heading( __( 'Vaša objednávka bola prijatá', 'vintrica-vignette-form' ) );
		$content .= Vintrica_Email_Template::render_paragraph( $greeting );
		$content .= Vintrica_Email_Template::render_paragraph( __( 'Ďakujeme za vašu objednávku diaľničných známok. Nižšie nájdete prehľad objednaných položiek.', 'vintrica-vignette-form' ) );
		$content .= Vintrica_Email_Template::render_status_card(
			__( 'Stav objednávky', 'vintrica-vignette-form' ),
			__( 'Čaká na úhradu', 'vintrica-vignette-form' )
		);
		$content .= Vintrica_Email_Template::render_info_card(
			__( 'Informácie o objednávke', 'vintrica-vignette-form' ),
			array(
				__( 'Číslo objednávky', 'vintrica-vignette-form' ) => (string) $order->order_number,
				__( 'Dátum objednávky', 'vintrica-vignette-form' ) => $this->format_order_date( $order->created_at ),
			)
		);
		$content .= $this->build_vignettes_section_html( $order );
		$content .= Vintrica_Email_Template::render_totals_summary(
			(string) $order->currency,
			(float) $order->subtotal,
			(float) $order->service_fee,
			(float) $order->total
		);

		if ( '' !== $checkout_url ) {
			$content .= Vintrica_Email_Template::render_button(
				$checkout_url,
				__( 'Dokončiť platbu', 'vintrica-vignette-form' )
			);
			$content .= Vintrica_Email_Template::render_payment_url( $checkout_url );
		}

		return Vintrica_Email_Template::render_document(
			__( 'Vaša objednávka bola prijatá a čaká na úhradu.', 'vintrica-vignette-form' ),
			$content
		);
	}

	/**
	 * Build HTML body for paid-order email.
	 *
	 * @param object $order Order row.
	 * @return string
	 */
	private function build_paid_email_html( $order ) {
		$billing    = $this->orders->decode_billing( $order );
		$first_name = isset( $billing['first_name'] ) ? trim( (string) $billing['first_name'] ) : '';
		$greeting   = '' !== $first_name
			? sprintf(
				/* translators: %s: customer first name */
				__( 'Dobrý deň, %s,', 'vintrica-vignette-form' ),
				$first_name
			)
			: __( 'Dobrý deň,', 'vintrica-vignette-form' );

		$content  = Vintrica_Email_Template::render_heading( __( 'Vaša platba bola úspešne prijatá', 'vintrica-vignette-form' ) );
		$content .= Vintrica_Email_Template::render_paragraph( $greeting );
		$content .= Vintrica_Email_Template::render_paragraph( __( 'Vaša platba bola úspešne prijatá. Ďakujeme za dôveru.', 'vintrica-vignette-form' ) );
		$content .= Vintrica_Email_Template::render_paragraph( __( 'Vaša objednávka bola úspešne uhradená a bude spracovaná.', 'vintrica-vignette-form' ) );
		$content .= Vintrica_Email_Template::render_info_card(
			__( 'Informácie o platbe', 'vintrica-vignette-form' ),
			array(
				__( 'Číslo objednávky', 'vintrica-vignette-form' ) => (string) $order->order_number,
				__( 'Dátum úhrady', 'vintrica-vignette-form' )     => $this->format_order_date( $order->paid_at ? $order->paid_at : current_time( 'mysql', true ) ),
				__( 'Uhradená suma', 'vintrica-vignette-form' )    => Vintrica_Email_Template::format_money( (string) $order->currency, (float) $order->total ),
			)
		);
		$content .= $this->build_vignettes_section_html( $order );

		return Vintrica_Email_Template::render_document(
			__( 'Vaša platba bola úspešne prijatá.', 'vintrica-vignette-form' ),
			$content
		);
	}

	/**
	 * Build vignette cards section.
	 *
	 * @param object $order Order row.
	 * @return string
	 */
	private function build_vignettes_section_html( $order ) {
		$vignettes = $this->orders->decode_vignettes( $order );
		$vehicles  = $this->pricing->get_vehicle_types();
		$html      = '<p style="margin:0 0 12px;font-size:14px;font-weight:700;color:#0f172a;">' . esc_html__( 'Objednané známky', 'vintrica-vignette-form' ) . '</p>';

		foreach ( $vignettes as $index => $vignette ) {
			$country_code  = $vignette['country'] ?? '';
			$validity_code = $vignette['vignette_validity'] ?? '';
			$vehicle_type  = $vignette['vehicle_type'] ?? '';
			$price         = $this->pricing->get_vignette_price( $country_code, $validity_code, $vehicle_type );
			$price         = null !== $price ? $price : 0;

			$html .= Vintrica_Email_Template::render_vignette_card(
				sprintf(
					/* translators: %d: vignette index */
					__( 'Známka %d', 'vintrica-vignette-form' ),
					(int) $index + 1
				),
				array(
					__( 'Krajina', 'vintrica-vignette-form' )             => $this->pricing->get_country_label( $country_code ),
					__( 'Typ vozidla', 'vintrica-vignette-form' )         => $vehicles[ $vehicle_type ] ?? $vehicle_type,
					__( 'Platnosť', 'vintrica-vignette-form' )            => $this->pricing->get_validity_label( $country_code, $validity_code, $vehicle_type ),
					__( 'ŠPZ', 'vintrica-vignette-form' )                 => (string) ( $vignette['license_plate'] ?? '' ),
					__( 'Krajina registrácie', 'vintrica-vignette-form' ) => Vintrica_Country_Registry::resolve_label( $vignette['registration_country'] ?? '' ),
					__( 'Dátum začiatku', 'vintrica-vignette-form' )      => (string) ( $vignette['start_date'] ?? '' ),
				),
				Vintrica_Email_Template::format_money( (string) $order->currency, $price )
			);
		}

		return $html;
	}

	/**
	 * Resolve Stripe Checkout URL for an order.
	 *
	 * @param object $order Order row.
	 * @return string
	 */
	private function resolve_checkout_url( $order ) {
		if ( empty( $order->stripe_session_id ) ) {
			return '';
		}

		$secret_key = trim( vintrica_vignette_form()->stripe->get_secret_key() );

		if ( '' === $secret_key ) {
			return '';
		}

		$response = wp_remote_get(
			'https://api.stripe.com/v1/checkout/sessions/' . rawurlencode( (string) $order->stripe_session_id ),
			array(
				'timeout' => 15,
				'headers' => array(
					'Authorization' => 'Bearer ' . $secret_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			$this->log_mail_failure( 'customer_created', $order, 'checkout_url_lookup_failed' );
			return '';
		}

		$status_code = (int) wp_remote_retrieve_response_code( $response );
		$body        = json_decode( (string) wp_remote_retrieve_body( $response ), true );

		if ( $status_code < 200 || $status_code >= 300 || ! is_array( $body ) || empty( $body['url'] ) ) {
			$this->log_mail_failure( 'customer_created', $order, 'checkout_url_missing' );
			return '';
		}

		return esc_url_raw( (string) $body['url'] );
	}

	/**
	 * Get customer email from order billing data.
	 *
	 * @param object $order Order row.
	 * @return string
	 */
	private function get_customer_email( $order ) {
		$billing = $this->orders->decode_billing( $order );
		$email   = isset( $billing['email'] ) ? sanitize_email( (string) $billing['email'] ) : '';

		return is_email( $email ) ? $email : '';
	}

	/**
	 * Format an order datetime for email display.
	 *
	 * @param string $datetime GMT datetime string.
	 * @return string
	 */
	private function format_order_date( $datetime ) {
		if ( empty( $datetime ) ) {
			return '';
		}

		return wp_date( 'd.m.Y H:i', strtotime( $datetime ) );
	}

	/**
	 * Dispatch HTML email via wp_mail with safe failure logging.
	 *
	 * @param string $recipient Recipient email.
	 * @param string $subject   Email subject.
	 * @param string $body      Email HTML body.
	 * @param string $context   Notification context.
	 * @param object $order     Order row.
	 * @return bool
	 */
	private function dispatch_mail( $recipient, $subject, $body, $context, $order ) {
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
	 * @param string $context Failure context.
	 * @param object $order   Order row.
	 * @param string $reason  Failure reason code.
	 * @return void
	 */
	private function log_mail_failure( $context, $order, $reason ) {
		$order_ref = ! empty( $order->order_number )
			? sanitize_text_field( (string) $order->order_number )
			: 'n/a';

		error_log(
			sprintf(
				'[VINTRICA] Customer email failed (%1$s/%2$s) for order %3$s.',
				sanitize_key( $context ),
				sanitize_key( $reason ),
				$order_ref
			)
		);
	}

	/**
	 * Check whether a customer email was already sent for an order.
	 *
	 * @param string $type     Email type.
	 * @param int    $order_id Order ID.
	 * @return bool
	 */
	private function was_email_sent( $type, $order_id ) {
		return (bool) get_transient( $this->get_sent_transient_key( $type, $order_id ) );
	}

	/**
	 * Mark a customer email as sent for an order.
	 *
	 * @param string $type     Email type.
	 * @param int    $order_id Order ID.
	 * @return void
	 */
	private function mark_email_sent( $type, $order_id ) {
		set_transient( $this->get_sent_transient_key( $type, $order_id ), 1, WEEK_IN_SECONDS );
	}

	/**
	 * Build transient key for sent-email tracking.
	 *
	 * @param string $type     Email type.
	 * @param int    $order_id Order ID.
	 * @return string
	 */
	private function get_sent_transient_key( $type, $order_id ) {
		return 'vintrica_customer_email_' . sanitize_key( $type ) . '_' . (int) $order_id;
	}
}
