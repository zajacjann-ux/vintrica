<?php
/**
 * General plugin settings.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Settings
 */
class Vintrica_Settings {

	/**
	 * Option key for order notification email.
	 */
	const OPTION_NOTIFICATION_EMAIL = 'vintrica_notification_email';

	/**
	 * Option key for customer email after order creation.
	 */
	const OPTION_CUSTOMER_EMAIL_CREATED = 'vintrica_customer_email_created';

	/**
	 * Option key for customer email after successful payment.
	 */
	const OPTION_CUSTOMER_EMAIL_PAID = 'vintrica_customer_email_paid';

	/**
	 * Get configured notification email (may be empty).
	 *
	 * @return string
	 */
	public function get_configured_notification_email() {
		return sanitize_email( (string) get_option( self::OPTION_NOTIFICATION_EMAIL, '' ) );
	}

	/**
	 * Get notification email with fallback to WordPress admin email.
	 *
	 * @return string
	 */
	public function get_notification_email() {
		$email = $this->get_configured_notification_email();

		if ( '' !== $email && is_email( $email ) ) {
			return $email;
		}

		return sanitize_email( (string) get_option( 'admin_email' ) );
	}

	/**
	 * Check whether customer email after order creation is enabled.
	 *
	 * @return bool
	 */
	public function is_customer_email_on_created_enabled() {
		return (bool) get_option( self::OPTION_CUSTOMER_EMAIL_CREATED, true );
	}

	/**
	 * Check whether customer email after successful payment is enabled.
	 *
	 * @return bool
	 */
	public function is_customer_email_on_paid_enabled() {
		return (bool) get_option( self::OPTION_CUSTOMER_EMAIL_PAID, true );
	}

	/**
	 * Get configured success redirect URL (empty when fallback is used).
	 *
	 * @return string
	 */
	public function get_configured_success_redirect_url() {
		return vintrica_vignette_form()->stripe->get_configured_success_redirect_url();
	}

	/**
	 * Get success redirect base URL with fallback.
	 *
	 * @return string
	 */
	public function get_success_redirect_base_url() {
		return vintrica_vignette_form()->stripe->get_success_redirect_base_url();
	}

	/**
	 * Save general plugin settings.
	 *
	 * @param array<string, mixed> $settings Settings payload.
	 * @return true|WP_Error
	 */
	public function save_settings( array $settings ) {
		$notification_email = isset( $settings['notification_email'] ) ? sanitize_email( wp_unslash( $settings['notification_email'] ) ) : '';

		if ( '' !== trim( (string) ( $settings['notification_email'] ?? '' ) ) && ! is_email( $notification_email ) ) {
			return new WP_Error(
				'vintrica_invalid_notification_email',
				__( 'Zadajte platnú e-mailovú adresu pre notifikácie objednávok.', 'vintrica-vignette-form' )
			);
		}

		$success_redirect_url = vintrica_vignette_form()->stripe->sanitize_redirect_url_setting(
			$settings['success_redirect_url'] ?? '',
			__( 'URL ďakovnej stránky po úspešnej platbe', 'vintrica-vignette-form' )
		);

		if ( is_wp_error( $success_redirect_url ) ) {
			return $success_redirect_url;
		}

		update_option( self::OPTION_NOTIFICATION_EMAIL, $notification_email );
		update_option( Vintrica_Stripe::OPTION_SUCCESS_REDIRECT_URL, $success_redirect_url );
		update_option(
			self::OPTION_CUSTOMER_EMAIL_CREATED,
			! empty( $settings['customer_email_created'] ) ? 1 : 0
		);
		update_option(
			self::OPTION_CUSTOMER_EMAIL_PAID,
			! empty( $settings['customer_email_paid'] ) ? 1 : 0
		);

		return true;
	}
}
