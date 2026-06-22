<?php
/**
 * Plugin Name:       VINTRICA Vignette Form
 * Plugin URI:        https://github.com/zajacjann-ux/vintrica
 * Description:       Objednávkový formulár diaľničných známok s vlastným checkoutom a prípravou Stripe platby.
 * Version:           1.5.0
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

define( 'VINTRICA_VERSION', '1.5.0' );
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
	 * Catalog handler.
	 *
	 * @var Vintrica_Catalog
	 */
	public $catalog;

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
	 * Orders handler.
	 *
	 * @var Vintrica_Orders
	 */
	public $orders;

	/**
	 * Stripe handler.
	 *
	 * @var Vintrica_Stripe
	 */
	public $stripe;

	/**
	 * Checkout handler.
	 *
	 * @var Vintrica_Checkout
	 */
	public $checkout;

	/**
	 * REST handler.
	 *
	 * @var Vintrica_Rest
	 */
	public $rest;

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
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-catalog.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-pricing.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-security.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-orders.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-stripe.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-checkout.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-rest.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-admin-catalog.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-admin.php';
		require_once VINTRICA_PLUGIN_DIR . 'includes/class-vintrica-frontend.php';
	}

	/**
	 * Initialize plugin components.
	 *
	 * @return void
	 */
	private function init_components() {
		$this->catalog = new Vintrica_Catalog();
		$this->pricing = new Vintrica_Pricing( $this->catalog );
		$this->security = new Vintrica_Security( $this->pricing );
		$this->orders   = new Vintrica_Orders();
		$this->stripe   = new Vintrica_Stripe();
		$this->checkout = new Vintrica_Checkout( $this->security, $this->pricing, $this->orders, $this->stripe );
		$this->rest     = new Vintrica_Rest( $this->checkout, $this->stripe );

		$catalog_admin  = new Vintrica_Admin_Catalog( $this->catalog );
		$this->admin    = new Vintrica_Admin( $catalog_admin );
		$this->frontend = new Vintrica_Frontend( $this->security, $this->pricing, $this->checkout );
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
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade_database' ), 15 );
	}

	/**
	 * Ensure database tables exist after updates.
	 *
	 * @return void
	 */
	public function maybe_upgrade_database() {
		$orders_installed = get_option( Vintrica_Orders::DB_VERSION_OPTION );

		if ( Vintrica_Orders::DB_VERSION !== $orders_installed ) {
			$this->orders->create_table();
		}

		$catalog_installed = get_option( Vintrica_Catalog::DB_VERSION_OPTION );

		if ( Vintrica_Catalog::DB_VERSION !== $catalog_installed ) {
			$this->catalog->create_tables();
			$this->catalog->maybe_seed_defaults();
		}
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
