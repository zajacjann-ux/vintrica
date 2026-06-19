<?php
/**
 * Plugin activation routines.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Activator
 */
class Vintrica_Activator {

	/**
	 * Minimum WordPress version.
	 */
	const MIN_WP_VERSION = '6.0';

	/**
	 * Minimum PHP version.
	 */
	const MIN_PHP_VERSION = '7.4';

	/**
	 * Run plugin activation tasks.
	 *
	 * @return void
	 */
	public static function activate() {
		self::assert_requirements();

		if ( false === get_option( 'vintrica_vignette_form_version', false ) ) {
			add_option( 'vintrica_vignette_form_version', VINTRICA_VERSION, '', false );
		} else {
			update_option( 'vintrica_vignette_form_version', VINTRICA_VERSION );
		}

		flush_rewrite_rules();

		if ( class_exists( 'WooCommerce' ) && function_exists( 'vintrica_vignette_form' ) ) {
			vintrica_vignette_form()->woocommerce->setup_product();
		}
	}

	/**
	 * Assert runtime requirements before activation.
	 *
	 * @return void
	 */
	private static function assert_requirements() {
		global $wp_version;

		if ( version_compare( PHP_VERSION, self::MIN_PHP_VERSION, '<' ) ) {
			deactivate_plugins( VINTRICA_PLUGIN_BASENAME );
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: required PHP version */
						__( 'Plugin VINTRICA Vignette Form vyžaduje PHP %s alebo novšie.', 'vintrica-vignette-form' ),
						self::MIN_PHP_VERSION
					)
				)
			);
		}

		if ( isset( $wp_version ) && version_compare( $wp_version, self::MIN_WP_VERSION, '<' ) ) {
			deactivate_plugins( VINTRICA_PLUGIN_BASENAME );
			wp_die(
				esc_html(
					sprintf(
						/* translators: %s: required WordPress version */
						__( 'Plugin VINTRICA Vignette Form vyžaduje WordPress %s alebo novší.', 'vintrica-vignette-form' ),
						self::MIN_WP_VERSION
					)
				)
			);
		}
	}
}
