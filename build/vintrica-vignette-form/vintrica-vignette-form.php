<?php
/**
 * Plugin Name:       VINTRICA Vignette Form
 * Plugin URI:        https://github.com/zajacjann-ux/vintrica
 * Description:       Vignette order form for WooCommerce integration.
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            VINTRICA
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       vintrica-vignette-form
 * Domain Path:       /languages
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

define( 'VINTRICA_VERSION', '1.0.0' );
define( 'VINTRICA_PLUGIN_VERSION', VINTRICA_VERSION );
define( 'VINTRICA_PLUGIN_FILE', __FILE__ );
define( 'VINTRICA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'VINTRICA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VINTRICA_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin bootstrap class.
 */
final class Vintrica_Vignette_Form {

	/**
	 * Plugin instance.
	 *
	 * @var Vintrica_Vignette_Form|null
	 */
	private static $instance = null;

	/**
	 * Pricing handler.
	 *
	 * @var Vintrica_Pricing
	 */
	public $pricing;

	/**
	 * Security handler.
	 *
	 * @var Vintrica_Security
	 */
	public $security;

	/**
	 * Admin handler.
	 *
	 * @var Vintrica_Admin
	 */
	public $admin;

	/**
	 * Frontend handler.
	 *
	 * @var Vintrica_Frontend
	 */
	public $frontend;

	/**
	 * WooCommerce integration handler.
	 *
	 * @var Vintrica_WooCommerce
	 */
	public $woocommerce;

	/**
	 * Get plugin instance.
	 *
	 * @return Vintrica_Vignette_Form
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->load_dependencies();
		$this->init_components();
		$this->init_hooks();
	}

	/**
	 * Load required class files.
	 *
	 * @return void
	 */
	private function load_dependencies() {
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-activator.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-deactivator.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-pricing.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-security.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-admin.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-frontend.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-woocommerce.php';
	}

	/**
	 * Initialize plugin components.
	 *
	 * @return void
	 */
	private function init_components() {
		$this->pricing     = new Vintrica_Pricing();
		$this->security    = new Vintrica_Security( $this->pricing );
		$this->admin       = new Vintrica_Admin();
		$this->frontend    = new Vintrica_Frontend( $this->security, $this->pricing );
		$this->woocommerce = new Vintrica_WooCommerce();
	}

	/**
	 * Register core hooks.
	 *
	 * @return void
	 */
	private function init_hooks() {
		register_activation_hook( VINTRICA_PLUGIN_FILE, array( 'Vintrica_Activator', 'activate' ) );
		register_deactivation_hook( VINTRICA_PLUGIN_FILE, array( 'Vintrica_Deactivator', 'deactivate' ) );

		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin text domain.
	 *
	 * @return void
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'vintrica-vignette-form',
			false,
			dirname( VINTRICA_PLUGIN_BASENAME ) . '/languages'
		);
	}
}

/**
 * Returns the main plugin instance.
 *
 * @return Vintrica_Vignette_Form
 */
function vintrica_vignette_form() {
	return Vintrica_Vignette_Form::instance();
}

vintrica_vignette_form();
