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
	 * Single vignette field keys.
	 *
	 * @var string[]
	 */
	private $vignette_fields = array(
		'country',
		'vehicle_type',
		'vignette_validity',
		'start_date',
		'license_plate',
		'registration_country',
	);

	/**
	 * Pricing handler.
	 *
	 * @var Vintrica_Pricing
	 */
	private $pricing;

	/**
	 * Constructor.
	 *
	 * @param Vintrica_Pricing $pricing Pricing handler.
	 */
	public function __construct( Vintrica_Pricing $pricing ) {
		$this->pricing = $pricing;
	}

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

		$home_host    = wp_parse_url( home_url(), PHP_URL_HOST );
		$referer_host = wp_parse_url( $referer, PHP_URL_HOST );

		return ! empty( $home_host ) && $home_host === $referer_host;
	}

	/**
	 * Sanitize and validate submitted vignette order payload.
	 *
	 * @param string $raw_json Raw JSON string from POST.
	 * @return array<int, array<string, string>>|WP_Error
	 */
	public function sanitize_vignette_order( $raw_json ) {
		$raw_json = wp_unslash( $raw_json );

		if ( ! is_string( $raw_json ) || '' === trim( $raw_json ) ) {
			return new WP_Error( 'vintrica_empty_order', __( 'Neboli odoslané žiadne známky.', 'vintrica-vignette-form' ) );
		}

		$decoded = json_decode( $raw_json, true );

		if ( ! is_array( $decoded ) || empty( $decoded ) ) {
			return new WP_Error( 'vintrica_invalid_order', __( 'Neplatné údaje objednávky známok.', 'vintrica-vignette-form' ) );
		}

		$countries     = $this->pricing->get_countries();
		$vehicle_types = $this->pricing->get_vehicle_types();
		$validities    = $this->pricing->get_country_validities();
		$sanitized     = array();

		foreach ( $decoded as $index => $item ) {
			if ( ! is_array( $item ) ) {
				return new WP_Error(
					'vintrica_invalid_item',
					sprintf(
						/* translators: %d: vignette index */
						__( 'Neplatná známka na pozícii %d.', 'vintrica-vignette-form' ),
						(int) $index + 1
					)
				);
			}

			$vignette = $this->sanitize_vignette_item( $item );

			if ( is_wp_error( $vignette ) ) {
				return $vignette;
			}

			if ( ! isset( $countries[ $vignette['country'] ] ) ) {
				return new WP_Error( 'vintrica_invalid_country', __( 'Neplatná krajina.', 'vintrica-vignette-form' ) );
			}

			if ( ! isset( $vehicle_types[ $vignette['vehicle_type'] ] ) ) {
				return new WP_Error( 'vintrica_invalid_vehicle', __( 'Neplatný typ vozidla.', 'vintrica-vignette-form' ) );
			}

			if ( ! isset( $validities[ $vignette['country'] ][ $vignette['vignette_validity'] ] ) ) {
				return new WP_Error( 'vintrica_invalid_validity', __( 'Neplatná platnosť známky pre zvolenú krajinu.', 'vintrica-vignette-form' ) );
			}

			if ( ! isset( $countries[ $vignette['registration_country'] ] ) ) {
				return new WP_Error( 'vintrica_invalid_registration', __( 'Neplatná krajina registrácie vozidla.', 'vintrica-vignette-form' ) );
			}

			if ( empty( $vignette['start_date'] ) || empty( $vignette['license_plate'] ) ) {
				return new WP_Error( 'vintrica_missing_fields', __( 'Každá známka musí obsahovať dátum začiatku platnosti a ŠPZ.', 'vintrica-vignette-form' ) );
			}

			$sanitized[] = $vignette;
		}

		return $sanitized;
	}

	/**
	 * Sanitize a single vignette item.
	 *
	 * @param array $item Raw vignette item.
	 * @return array<string, string>|WP_Error
	 */
	private function sanitize_vignette_item( array $item ) {
		$sanitized = array();

		foreach ( $this->vignette_fields as $field_key ) {
			if ( ! isset( $item[ $field_key ] ) ) {
				return new WP_Error(
					'vintrica_missing_field',
					sprintf(
						/* translators: %s: field name */
						__( 'Chýba povinné pole: %s', 'vintrica-vignette-form' ),
						esc_html( $field_key )
					)
				);
			}

			$value = $item[ $field_key ];

			switch ( $field_key ) {
				case 'start_date':
					$sanitized[ $field_key ] = $this->sanitize_date( $value );
					break;

				case 'license_plate':
					$sanitized[ $field_key ] = $this->sanitize_license_plate( $value );
					break;

				default:
					$sanitized[ $field_key ] = sanitize_key( $value );
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
