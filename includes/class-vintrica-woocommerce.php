<?php
/**
 * WooCommerce integration placeholder.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_WooCommerce
 */
class Vintrica_WooCommerce {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'maybe_bootstrap' ), 20 );
	}

	/**
	 * Bootstrap WooCommerce integration when WooCommerce is available.
	 *
	 * @return void
	 */
	public function maybe_bootstrap() {
		if ( ! $this->is_woocommerce_active() ) {
			return;
		}

		$this->register_hooks();
	}

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Register WooCommerce integration hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_action( 'vintrica_vignette_form_submitted', array( $this, 'handle_form_submission' ), 10, 1 );
	}

	/**
	 * Handle validated form submissions for WooCommerce processing.
	 *
	 * @param array $form_data Sanitized form data.
	 * @return void
	 */
	public function handle_form_submission( array $form_data ) {
		/**
		 * Fires when a vignette form submission is ready for WooCommerce processing.
		 *
		 * @param array $form_data Sanitized form data.
		 */
		do_action( 'vintrica_before_add_to_cart', $form_data );

		// WooCommerce cart and checkout integration will be implemented here.
	}

	/**
	 * Map form data to WooCommerce cart item meta.
	 *
	 * @param array $form_data Sanitized form data.
	 * @return array<string, string>
	 */
	public function map_form_data_to_cart_meta( array $form_data ) {
		/**
		 * Filter cart item meta mapped from vignette form data.
		 *
		 * @param array<string, string> $meta      Cart item meta.
		 * @param array                 $form_data Sanitized form data.
		 */
		return apply_filters(
			'vintrica_vignette_cart_item_meta',
			array(
				'vintrica_country'              => isset( $form_data['country'] ) ? $form_data['country'] : '',
				'vintrica_vehicle_type'         => isset( $form_data['vehicle_type'] ) ? $form_data['vehicle_type'] : '',
				'vintrica_vignette_validity'    => isset( $form_data['vignette_validity'] ) ? $form_data['vignette_validity'] : '',
				'vintrica_start_date'           => isset( $form_data['start_date'] ) ? $form_data['start_date'] : '',
				'vintrica_license_plate'        => isset( $form_data['license_plate'] ) ? $form_data['license_plate'] : '',
				'vintrica_registration_country' => isset( $form_data['registration_country'] ) ? $form_data['registration_country'] : '',
			),
			$form_data
		);
	}
}
