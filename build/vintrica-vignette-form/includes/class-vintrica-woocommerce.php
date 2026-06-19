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
	 * Handle validated order submissions for WooCommerce processing.
	 *
	 * @param array $order_data Validated order with vignettes and totals.
	 * @return void
	 */
	public function handle_form_submission( array $order_data ) {
		/**
		 * Fires when a vignette order is ready for WooCommerce processing.
		 *
		 * @param array $order_data Validated order with vignettes and totals.
		 */
		do_action( 'vintrica_before_add_to_cart', $order_data );

		// WooCommerce cart and checkout integration will be implemented here.
	}

	/**
	 * Map a vignette item to WooCommerce cart item meta.
	 *
	 * @param array $vignette Sanitized vignette item.
	 * @return array<string, string>
	 */
	public function map_vignette_to_cart_meta( array $vignette ) {
		/**
		 * Filter cart item meta mapped from vignette form data.
		 *
		 * @param array<string, string> $meta      Cart item meta.
		 * @param array                 $form_data Sanitized form data.
		 */
		return apply_filters(
			'vintrica_vignette_cart_item_meta',
			array(
				'vintrica_country'              => isset( $vignette['country'] ) ? $vignette['country'] : '',
				'vintrica_vehicle_type'         => isset( $vignette['vehicle_type'] ) ? $vignette['vehicle_type'] : '',
				'vintrica_vignette_validity'    => isset( $vignette['vignette_validity'] ) ? $vignette['vignette_validity'] : '',
				'vintrica_start_date'           => isset( $vignette['start_date'] ) ? $vignette['start_date'] : '',
				'vintrica_license_plate'        => isset( $vignette['license_plate'] ) ? $vignette['license_plate'] : '',
				'vintrica_registration_country' => isset( $vignette['registration_country'] ) ? $vignette['registration_country'] : '',
			),
			$vignette
		);
	}
}
