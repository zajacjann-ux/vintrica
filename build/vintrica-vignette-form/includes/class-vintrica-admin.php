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
	 * Register admin menus.
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

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Objednávky', 'vintrica-vignette-form' ),
			__( 'Objednávky', 'vintrica-vignette-form' ),
			'manage_options',
			self::MENU_SLUG . '-orders',
			array( $this, 'render_orders_page' )
		);
	}

	/**
	 * Render the main admin page.
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
			<p><?php echo esc_html__( 'Objednávkový formulár diaľničných známok s dvojkrokovým checkoutom a prípravou Stripe platby.', 'vintrica-vignette-form' ); ?></p>
			<p>
				<code>[vintrica_vignette_form]</code>
			</p>
			<p><?php echo esc_html__( 'Krok 1: zostavenie známok. Krok 2: fakturačné údaje a odoslanie objednávky.', 'vintrica-vignette-form' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Render the orders admin page.
	 *
	 * @return void
	 */
	public function render_orders_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnenie na prístup k tejto stránke.', 'vintrica-vignette-form' ) );
		}

		$orders = vintrica_vignette_form()->orders->get_recent_orders( 50 );

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Objednávky VINTRICA', 'vintrica-vignette-form' ); ?></h1>
			<table class="widefat striped">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Číslo objednávky', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'Stav', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'E-mail', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'Suma', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'Vytvorené', 'vintrica-vignette-form' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $orders ) ) : ?>
						<tr>
							<td colspan="5"><?php echo esc_html__( 'Zatiaľ nie sú žiadne objednávky.', 'vintrica-vignette-form' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $orders as $order ) : ?>
							<?php
							$billing = json_decode( (string) $order->billing, true );
							$email   = is_array( $billing ) && isset( $billing['email'] ) ? $billing['email'] : '';
							?>
							<tr>
								<td><?php echo esc_html( $order->order_number ); ?></td>
								<td><?php echo esc_html( $order->status ); ?></td>
								<td><?php echo esc_html( $email ); ?></td>
								<td><?php echo esc_html( $order->currency . ' ' . number_format_i18n( (float) $order->total, 2 ) ); ?></td>
								<td><?php echo esc_html( get_date_from_gmt( $order->created_at, 'd.m.Y H:i' ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}
