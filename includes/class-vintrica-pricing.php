<?php
/**
 * Server-side pricing and validity configuration.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Pricing
 */
class Vintrica_Pricing {

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
	 * Get flat service fee amount.
	 *
	 * @return float
	 */
	public function get_service_fee() {
		/**
		 * Filter the vignette order service fee.
		 *
		 * @param float $fee Service fee amount.
		 */
		return (float) apply_filters( 'vintrica_vignette_service_fee', 2.50 );
	}

	/**
	 * Get country labels.
	 *
	 * @return array<string, string>
	 */
	public function get_countries() {
		/**
		 * Filter available vignette countries.
		 *
		 * @param array<string, string> $countries Country code => label.
		 */
		return apply_filters(
			'vintrica_vignette_countries',
			array(
				'at' => __( 'Rakúsko', 'vintrica-vignette-form' ),
				'ch' => __( 'Švajčiarsko', 'vintrica-vignette-form' ),
				'cz' => __( 'Česko', 'vintrica-vignette-form' ),
				'hu' => __( 'Maďarsko', 'vintrica-vignette-form' ),
				'si' => __( 'Slovinsko', 'vintrica-vignette-form' ),
				'sk' => __( 'Slovensko', 'vintrica-vignette-form' ),
			)
		);
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
		return apply_filters(
			'vintrica_vignette_vehicle_types',
			array(
				'car'        => __( 'Osobné vozidlo', 'vintrica-vignette-form' ),
				'motorcycle' => __( 'Motocykel', 'vintrica-vignette-form' ),
				'van'        => __( 'Dodávka', 'vintrica-vignette-form' ),
				'trailer'    => __( 'Príves', 'vintrica-vignette-form' ),
			)
		);
	}

	/**
	 * Get country-specific validity options with server-side prices.
	 *
	 * @return array<string, array<string, array<string, mixed>>>
	 */
	public function get_country_validities() {
		/**
		 * Filter country-specific validity options and prices.
		 *
		 * @param array<string, array<string, array<string, mixed>>> $validities Country => validity => config.
		 */
		return apply_filters(
			'vintrica_vignette_country_validities',
			array(
				'at' => array(
					'10d' => array(
						'label' => __( '10 dní', 'vintrica-vignette-form' ),
						'price' => 9.90,
					),
					'2m'  => array(
						'label' => __( '2 mesiace', 'vintrica-vignette-form' ),
						'price' => 28.90,
					),
					'1y'  => array(
						'label' => __( '1 rok', 'vintrica-vignette-form' ),
						'price' => 96.40,
					),
				),
				'ch' => array(
					'1d'  => array(
						'label' => __( '1 deň', 'vintrica-vignette-form' ),
						'price' => 8.30,
					),
					'7d'  => array(
						'label' => __( '7 dní', 'vintrica-vignette-form' ),
						'price' => 32.00,
					),
					'1y'  => array(
						'label' => __( '1 rok', 'vintrica-vignette-form' ),
						'price' => 40.00,
					),
				),
				'cz' => array(
					'10d' => array(
						'label' => __( '10 dní', 'vintrica-vignette-form' ),
						'price' => 12.00,
					),
					'1m'  => array(
						'label' => __( '1 mesiac', 'vintrica-vignette-form' ),
						'price' => 16.00,
					),
					'1y'  => array(
						'label' => __( '1 rok', 'vintrica-vignette-form' ),
						'price' => 150.00,
					),
				),
				'hu' => array(
					'10d' => array(
						'label' => __( '10 dní', 'vintrica-vignette-form' ),
						'price' => 15.00,
					),
					'1m'  => array(
						'label' => __( '1 mesiac', 'vintrica-vignette-form' ),
						'price' => 22.00,
					),
					'1y'  => array(
						'label' => __( '1 rok', 'vintrica-vignette-form' ),
						'price' => 155.00,
					),
				),
				'si' => array(
					'7d'  => array(
						'label' => __( '7 dní', 'vintrica-vignette-form' ),
						'price' => 16.00,
					),
					'1m'  => array(
						'label' => __( '1 mesiac', 'vintrica-vignette-form' ),
						'price' => 32.00,
					),
					'1y'  => array(
						'label' => __( '1 rok', 'vintrica-vignette-form' ),
						'price' => 110.00,
					),
				),
				'sk' => array(
					'10d' => array(
						'label' => __( '10 dní', 'vintrica-vignette-form' ),
						'price' => 12.00,
					),
					'1m'  => array(
						'label' => __( '1 mesiac', 'vintrica-vignette-form' ),
						'price' => 17.00,
					),
					'1y'  => array(
						'label' => __( '1 rok', 'vintrica-vignette-form' ),
						'price' => 60.00,
					),
				),
			)
		);
	}

	/**
	 * Get price for a country and validity combination.
	 *
	 * @param string $country   Country code.
	 * @param string $validity  Validity code.
	 * @return float|null
	 */
	public function get_vignette_price( $country, $validity ) {
		$validities = $this->get_country_validities();

		if ( ! isset( $validities[ $country ][ $validity ]['price'] ) ) {
			return null;
		}

		return (float) $validities[ $country ][ $validity ]['price'];
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
		$types = $this->get_vehicle_types();

		return isset( $types[ $code ] ) ? $types[ $code ] : $code;
	}

	/**
	 * Get localized validity label by country and validity code.
	 *
	 * @param string $country  Country code.
	 * @param string $validity Validity code.
	 * @return string
	 */
	public function get_validity_label( $country, $validity ) {
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
			$this->get_validity_label( $vignette['country'], $vignette['vignette_validity'] )
		);
	}

	/**
	 * Build frontend-safe configuration for JavaScript.
	 *
	 * @return array<string, mixed>
	 */
	public function get_frontend_config() {
		$countries  = $this->get_countries();
		$validities = $this->get_country_validities();
		$config     = array(
			'currency'     => $this->get_currency(),
			'serviceFee'   => $this->get_service_fee(),
			'countries'    => array(),
			'vehicleTypes' => array(),
			'validities'   => array(),
		);

		foreach ( $countries as $code => $label ) {
			$config['countries'][] = array(
				'code'  => $code,
				'label' => $label,
			);
		}

		foreach ( $this->get_vehicle_types() as $code => $label ) {
			$config['vehicleTypes'][] = array(
				'code'  => $code,
				'label' => $label,
			);
		}

		foreach ( $validities as $country_code => $country_validities ) {
			$config['validities'][ $country_code ] = array();

			foreach ( $country_validities as $validity_code => $validity_config ) {
				$config['validities'][ $country_code ][] = array(
					'code'  => $validity_code,
					'label' => $validity_config['label'],
					'price' => (float) $validity_config['price'],
				);
			}
		}

		return $config;
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
				$vignette['vignette_validity']
			);

			if ( null !== $price ) {
				$subtotal += $price;
			}
		}

		$service_fee = count( $vignettes ) > 0 ? $this->get_service_fee() : 0.0;

		return array(
			'count'       => count( $vignettes ),
			'subtotal'    => round( $subtotal, 2 ),
			'service_fee' => round( $service_fee, 2 ),
			'total'       => round( $subtotal + $service_fee, 2 ),
		);
	}
}
