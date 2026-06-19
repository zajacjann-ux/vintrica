<?php
/**
 * Admin area functionality.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Admin
 */
class Vintrica_Admin {

	/**
	 * Admin menu slug.
	 */
	const MENU_SLUG = 'vintrica-form';

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
	}

	/**
	 * Register the VINTRICA FORM admin menu.
	 *
	 * @return void
	 */
	public function register_admin_menu() {
		add_menu_page(
			__( 'VINTRICA FORM', 'vintrica-vignette-form' ),
			__( 'VINTRICA FORM', 'vintrica-vignette-form' ),
			'manage_options',
			self::MENU_SLUG,
			array( $this, 'render_admin_page' ),
			'dashicons-car',
			56
		);
	}

	/**
	 * Render the admin settings page.
	 *
	 * @return void
	 */
	public function render_admin_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnenie na prístup k tejto stránke.', 'vintrica-vignette-form' ) );
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'VINTRICA FORM', 'vintrica-vignette-form' ); ?></h1>
			<p><?php echo esc_html__( 'Nastavte objednávkový formulár diaľničných známok a zobrazte ho pomocou shortcode nižšie.', 'vintrica-vignette-form' ); ?></p>
			<p>
				<code>[vintrica_vignette_form]</code>
			</p>
			<p><?php echo esc_html__( 'Nastavenia prepojenia s WooCommerce budú dostupné v budúcej verzii.', 'vintrica-vignette-form' ); ?></p>
		</div>
		<?php
	}
}
