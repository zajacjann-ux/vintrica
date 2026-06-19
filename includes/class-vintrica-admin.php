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
		add_action( 'admin_init', array( $this, 'handle_product_setup_action' ) );
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
	 * Handle explicit admin action to create or repair the base product.
	 *
	 * @return void
	 */
	public function handle_product_setup_action() {
		if ( ! isset( $_GET['vintrica_setup_product'] ) ) {
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		check_admin_referer( 'vintrica_setup_product' );

		$product_id = vintrica_vignette_form()->woocommerce->setup_product();
		$redirect   = add_query_arg(
			array(
				'page'                    => self::MENU_SLUG,
				'vintrica_product_setup'  => $product_id > 0 ? 'success' : 'error',
			),
			admin_url( 'admin.php' )
		);

		wp_safe_redirect( $redirect );
		exit;
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

		$woocommerce = vintrica_vignette_form()->woocommerce;
		$product_id  = $woocommerce->get_product_id();
		$setup_url   = wp_nonce_url(
			admin_url( 'admin.php?page=' . self::MENU_SLUG . '&vintrica_setup_product=1' ),
			'vintrica_setup_product'
		);

		if ( isset( $_GET['vintrica_product_setup'] ) && 'success' === sanitize_key( wp_unslash( $_GET['vintrica_product_setup'] ) ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'WooCommerce produkt pre diaľničné známky bol úspešne pripravený.', 'vintrica-vignette-form' ) . '</p></div>';
		}

		if ( isset( $_GET['vintrica_product_setup'] ) && 'error' === sanitize_key( wp_unslash( $_GET['vintrica_product_setup'] ) ) ) {
			$error = $woocommerce->get_product_error();

			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html(
				$error ? $error : __( 'Produkt sa nepodarilo vytvoriť.', 'vintrica-vignette-form' )
			) . '</p></div>';
		}

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'VINTRICA FORM', 'vintrica-vignette-form' ); ?></h1>
			<p><?php echo esc_html__( 'Nastavte objednávkový formulár diaľničných známok a zobrazte ho pomocou shortcode nižšie.', 'vintrica-vignette-form' ); ?></p>
			<p>
				<code>[vintrica_vignette_form]</code>
			</p>
			<p><?php echo esc_html__( 'Po odoslaní formulára sa známky pridajú do WooCommerce košíka a zákazník bude presmerovaný na pokladňu.', 'vintrica-vignette-form' ); ?></p>

			<h2><?php echo esc_html__( 'WooCommerce produkt', 'vintrica-vignette-form' ); ?></h2>
			<?php if ( ! $woocommerce->is_woocommerce_active() ) : ?>
				<div class="notice notice-warning inline">
					<p><?php echo esc_html__( 'WooCommerce nie je nainštalovaný alebo aktivovaný. Objednávky známok nie je možné spracovať.', 'vintrica-vignette-form' ); ?></p>
				</div>
			<?php elseif ( $product_id > 0 ) : ?>
				<p>
					<?php
					printf(
						/* translators: %d: WooCommerce product ID */
						esc_html__( 'Základný produkt je pripravený (ID: %d).', 'vintrica-vignette-form' ),
						(int) $product_id
					);
					?>
				</p>
			<?php else : ?>
				<p><?php echo esc_html__( 'Základný WooCommerce produkt ešte nebol vytvorený.', 'vintrica-vignette-form' ); ?></p>
				<p>
					<a class="button button-primary" href="<?php echo esc_url( $setup_url ); ?>">
						<?php echo esc_html__( 'Vytvoriť produkt', 'vintrica-vignette-form' ); ?>
					</a>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
}
