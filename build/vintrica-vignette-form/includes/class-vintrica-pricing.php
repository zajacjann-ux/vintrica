<?php
/**
 * Server-side pricing backed by the editable catalog.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Pricing
 */
class Vintrica_Pricing {

	/**
	 * Catalog handler.
	 *
	 * @var Vintrica_Catalog
	 */
	private $catalog;

	/**
	 * Constructor.
	 *
	 * @param Vintrica_Catalog $catalog Catalog handler.
	 */
	public function __construct( Vintrica_Catalog $catalog ) {
		$this->catalog = $catalog;
	}

	/**
	 * Get currency code.
	 *
	 * @return string
	 */
	public function get_currency() {
		/**
		 * Filter the vignette pricing currency code.
		 *
		 * @param string $currency ISO 4217 currency code.
		 */
		return apply_filters( 'vintrica_vignette_currency', 'EUR' );
	}

	/**
	 * Get active country labels from catalog.
	 *
	 * @return array<string, string>
	 */
	public function get_countries() {
		/**
		 * Filter available vignette countries.
		 *
		 * @param array<string, string> $countries Country code => label.
		 */
		return apply_filters( 'vintrica_vignette_countries', $this->catalog->get_country_map() );
	}

	/**
	 * Get vehicle type labels.
	 *
	 * @return array<string, string>
	 */
	public function get_vehicle_types() {
		/**
		 * Filter available vehicle types.
		 *
		 * @param array<string, string> $types Vehicle type code => label.
		 */
		return apply_filters( 'vintrica_vignette_vehicle_types', $this->catalog->get_vehicle_types() );
	}

	/**
	 * Get nested country validities grouped by vehicle type.
	 *
	 * @return array<string, array<string, array<string, array<string, mixed>>>>
	 */
	public function get_country_validities() {
		$catalog = $this->catalog->get_frontend_catalog();
		$nested  = array();

		foreach ( $catalog['validities'] as $country_code => $by_vehicle ) {
			$nested[ $country_code ] = array();

			foreach ( $by_vehicle as $vehicle_type => $items ) {
				foreach ( $items as $item ) {
					$nested[ $country_code ][ $item['code'] ] = array(
						'label'        => $item['label'],
						'price'        => (float) $item['price'],
						'vehicle_type' => $vehicle_type,
						'name'         => $item['name'],
					);
				}
			}
		}

		/**
		 * Filter country-specific validity options and prices.
		 *
		 * @param array<string, array<string, array<string, mixed>>> $validities Country => validity => config.
		 */
		return apply_filters( 'vintrica_vignette_country_validities', $nested );
	}

	/**
	 * Get price for a country, vehicle type and validity combination.
	 *
	 * @param string $country       Country code.
	 * @param string $validity      Validity code.
	 * @param string $vehicle_type  Vehicle type code.
	 * @return float|null
	 */
	public function get_vignette_price( $country, $validity, $vehicle_type = '' ) {
		if ( '' === $vehicle_type ) {
			$match = $this->catalog->find_active_vignette( $country, 'car', $validity );

			if ( $match ) {
				return round( (float) $match->price, 2 );
			}

			return null;
		}

		return $this->catalog->get_price( $country, $vehicle_type, $validity );
	}

	/**
	 * Check whether an active catalog vignette exists.
	 *
	 * @param string $country       Country code.
	 * @param string $vehicle_type  Vehicle type code.
	 * @param string $validity      Validity code.
	 * @return bool
	 */
	public function vignette_exists( $country, $vehicle_type, $validity ) {
		return null !== $this->catalog->get_price( $country, $vehicle_type, $validity );
	}

	/**
	 * Get localized country label by code.
	 *
	 * @param string $code Country code.
	 * @return string
	 */
	public function get_country_label( $code ) {
		$countries = $this->get_countries();

		return isset( $countries[ $code ] ) ? $countries[ $code ] : $code;
	}

	/**
	 * Get localized vehicle type label by code.
	 *
	 * @param string $code Vehicle type code.
	 * @return string
	 */
	public function get_vehicle_type_label( $code ) {
		return $this->catalog->get_vehicle_type_label( $code );
	}

	/**
	 * Get localized validity label by country, vehicle and validity code.
	 *
	 * @param string $country      Country code.
	 * @param string $validity     Validity code.
	 * @param string $vehicle_type Vehicle type code.
	 * @return string
	 */
	public function get_validity_label( $country, $validity, $vehicle_type = '' ) {
		if ( '' !== $vehicle_type ) {
			$match = $this->catalog->find_active_vignette( $country, $vehicle_type, $validity );

			if ( $match ) {
				return $match->validity_label;
			}
		}

		$validities = $this->get_country_validities();

		if ( isset( $validities[ $country ][ $validity ]['label'] ) ) {
			return $validities[ $country ][ $validity ]['label'];
		}

		return $validity;
	}

	/**
	 * Build a cart line title for a vignette.
	 *
	 * @param array $vignette Sanitized vignette data.
	 * @return string
	 */
	public function get_vignette_line_title( array $vignette ) {
		return sprintf(
			/* translators: 1: country label, 2: validity label */
			__( 'Diaľničná známka – %1$s (%2$s)', 'vintrica-vignette-form' ),
			$this->get_country_label( $vignette['country'] ),
			$this->get_validity_label(
				$vignette['country'],
				$vignette['vignette_validity'],
				isset( $vignette['vehicle_type'] ) ? $vignette['vehicle_type'] : ''
			)
		);
	}

	/**
	 * Build frontend-safe configuration for JavaScript.
	 *
	 * @return array<string, mixed>
	 */
	public function get_frontend_config() {
		$config = $this->catalog->get_frontend_catalog();

		$config['currency'] = $this->get_currency();

		/**
		 * Filter frontend pricing configuration.
		 *
		 * @param array<string, mixed> $config Frontend config.
		 */
		return apply_filters( 'vintrica_vignette_frontend_config', $config );
	}

	/**
	 * Calculate order totals from validated vignettes.
	 *
	 * @param array<int, array<string, string>> $vignettes Validated vignettes.
	 * @return array<string, float|int>
	 */
	public function calculate_totals( array $vignettes ) {
		$subtotal = 0.0;

		foreach ( $vignettes as $vignette ) {
			$price = $this->get_vignette_price(
				$vignette['country'],
				$vignette['vignette_validity'],
				$vignette['vehicle_type']
			);

			if ( null !== $price ) {
				$subtotal += $price;
			}
		}

		$subtotal = round( $subtotal, 2 );

		return array(
			'count'       => count( $vignettes ),
			'subtotal'    => $subtotal,
			'service_fee' => 0.0,
			'total'       => $subtotal,
		);
	}
}
