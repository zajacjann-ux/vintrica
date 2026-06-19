<?php
/**
 * Plugin deactivation routines.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Deactivator
 */
class Vintrica_Deactivator {

	/**
	 * Run plugin deactivation tasks.
	 *
	 * @return void
	 */
	public static function deactivate() {
		self::clear_notice_transients();
		flush_rewrite_rules();
	}

	/**
	 * Remove frontend notice transients created by the plugin.
	 *
	 * @return void
	 */
	private static function clear_notice_transients() {
		global $wpdb;

		if ( ! isset( $wpdb ) ) {
			return;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
				$wpdb->esc_like( '_transient_vintrica_notices_' ) . '%',
				$wpdb->esc_like( '_transient_timeout_vintrica_notices_' ) . '%'
			)
		);
	}
}
