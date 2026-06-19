<?php
/**
 * Security utilities for nonce, CSRF, sanitization, and escaping.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Security
 */
class Vintrica_Security {

	/**
	 * Nonce action for form submissions.
	 */
	const FORM_NONCE_ACTION = 'vintrica_vignette_form_submit';

	/**
	 * Nonce field name.
	 */
	const FORM_NONCE_NAME = 'vintrica_vignette_nonce';

	/**
	 * Allowed form field keys.
	 *
	 * @var string[]
	 */
	private $allowed_fields = array(
		'country',
		'vehicle_type',
		'vignette_validity',
		'start_date',
		'license_plate',
		'registration_country',
	);

	/**
	 * Render the form nonce field.
	 *
	 * @return void
	 */
	public function render_nonce_field() {
		wp_nonce_field( self::FORM_NONCE_ACTION, self::FORM_NONCE_NAME );
	}

	/**
	 * Verify form request integrity (nonce and CSRF referer).
	 *
	 * @return bool
	 */
	public function verify_form_request() {
		if ( ! isset( $_POST[ self::FORM_NONCE_NAME ] ) ) {
			return false;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST[ self::FORM_NONCE_NAME ] ) );

		if ( ! wp_verify_nonce( $nonce, self::FORM_NONCE_ACTION ) ) {
			return false;
		}

		if ( ! $this->verify_referrer() ) {
			return false;
		}

		return true;
	}

	/**
	 * Verify HTTP referer for CSRF protection.
	 *
	 * @return bool
	 */
	public function verify_referrer() {
		$referer = wp_get_referer();

		if ( false === $referer ) {
			return false;
		}

		$home_host     = wp_parse_url( home_url(), PHP_URL_HOST );
		$referer_host  = wp_parse_url( $referer, PHP_URL_HOST );

		return ! empty( $home_host ) && $home_host === $referer_host;
	}

	/**
	 * Sanitize submitted form data.
	 *
	 * @param array $raw_data Raw POST data.
	 * @return array
	 */
	public function sanitize_form_data( array $raw_data ) {
		$sanitized = array();

		foreach ( $this->allowed_fields as $field_key ) {
			if ( ! isset( $raw_data[ $field_key ] ) ) {
				continue;
			}

			$value = wp_unslash( $raw_data[ $field_key ] );

			switch ( $field_key ) {
				case 'start_date':
					$sanitized[ $field_key ] = $this->sanitize_date( $value );
					break;

				case 'license_plate':
					$sanitized[ $field_key ] = $this->sanitize_license_plate( $value );
					break;

				default:
					$sanitized[ $field_key ] = sanitize_text_field( $value );
					break;
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize a date value (Y-m-d).
	 *
	 * @param string $value Raw date string.
	 * @return string
	 */
	public function sanitize_date( $value ) {
		$value = sanitize_text_field( $value );

		if ( empty( $value ) ) {
			return '';
		}

		$date = DateTime::createFromFormat( 'Y-m-d', $value );

		if ( false === $date || $date->format( 'Y-m-d' ) !== $value ) {
			return '';
		}

		return $value;
	}

	/**
	 * Sanitize a license plate value.
	 *
	 * @param string $value Raw license plate.
	 * @return string
	 */
	public function sanitize_license_plate( $value ) {
		$value = sanitize_text_field( $value );
		$value = preg_replace( '/[^A-Za-z0-9\- ]/', '', $value );

		return strtoupper( trim( $value ) );
	}

	/**
	 * Escape attribute value for output.
	 *
	 * @param string $value Value to escape.
	 * @return string
	 */
	public function escape_attr( $value ) {
		return esc_attr( $value );
	}

	/**
	 * Escape HTML content for output.
	 *
	 * @param string $value Value to escape.
	 * @return string
	 */
	public function escape_html( $value ) {
		return esc_html( $value );
	}

	/**
	 * Escape URL for output.
	 *
	 * @param string $value Value to escape.
	 * @return string
	 */
	public function escape_url( $value ) {
		return esc_url( $value );
	}
}
