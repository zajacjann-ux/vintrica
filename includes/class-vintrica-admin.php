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
	 * Orders submenu slug.
	 */
	const ORDERS_SLUG = 'vintrica-form-orders';

	/**
	 * Stripe settings submenu slug.
	 */
	const STRIPE_SLUG = 'vintrica-form-stripe';

	/**
	 * Catalog submenu slug.
	 */
	const CATALOG_SLUG = 'vintrica-form-catalog';

	/**
	 * Settings submenu slug.
	 */
	const SETTINGS_SLUG = 'vintrica-form-settings';

	/**
	 * Nonce action for general settings.
	 */
	const SETTINGS_NONCE = 'vintrica_admin_settings';

	/**
	 * Admin catalog handler.
	 *
	 * @var Vintrica_Admin_Catalog|null
	 */
	private $catalog_admin;

	/**
	 * Nonce action for admin order actions.
	 */
	const ORDER_ACTION_NONCE = 'vintrica_admin_order_action';

	/**
	 * Nonce action for Stripe settings.
	 */
	const STRIPE_SETTINGS_NONCE = 'vintrica_admin_stripe_settings';

	/**
	 * Nonce action for Stripe connection test.
	 */
	const STRIPE_TEST_NONCE = 'vintrica_admin_stripe_test';

	/**
	 * Constructor.
	 *
	 * @param Vintrica_Admin_Catalog|null $catalog_admin Catalog admin handler.
	 */
	public function __construct( Vintrica_Admin_Catalog $catalog_admin = null ) {
		$this->catalog_admin = $catalog_admin;

		add_action( 'admin_menu', array( $this, 'register_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'admin_init', array( $this, 'handle_admin_actions' ) );
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
			self::ORDERS_SLUG,
			array( $this, 'render_orders_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Nastavenia', 'vintrica-vignette-form' ),
			__( 'Nastavenia', 'vintrica-vignette-form' ),
			'manage_options',
			self::SETTINGS_SLUG,
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Cenník známok', 'vintrica-vignette-form' ),
			__( 'Cenník známok', 'vintrica-vignette-form' ),
			'manage_options',
			self::CATALOG_SLUG,
			array( $this, 'render_catalog_page' )
		);

		add_submenu_page(
			self::MENU_SLUG,
			__( 'Stripe', 'vintrica-vignette-form' ),
			__( 'Stripe', 'vintrica-vignette-form' ),
			'manage_options',
			self::STRIPE_SLUG,
			array( $this, 'render_stripe_settings_page' )
		);
	}

	/**
	 * Enqueue admin assets on plugin pages.
	 *
	 * @param string $hook Current admin page hook.
	 * @return void
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( $hook, self::MENU_SLUG ) ) {
			return;
		}

		wp_enqueue_style(
			'vintrica-admin',
			VINTRICA_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			VINTRICA_VERSION
		);
	}

	/**
	 * Handle admin POST actions.
	 *
	 * @return void
	 */
	public function handle_admin_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( isset( $_POST['vintrica_settings_submit'] ) ) {
			$this->handle_settings_save();
			return;
		}

		if ( isset( $_POST['vintrica_stripe_settings_submit'] ) ) {
			$this->handle_stripe_settings_save();
			return;
		}

		if ( isset( $_POST['vintrica_stripe_test_submit'] ) ) {
			$this->handle_stripe_test_connection();
			return;
		}

		if ( isset( $_POST['vintrica_order_status_submit'], $_POST['order_id'], $_POST['order_status'] ) ) {
			$this->handle_order_status_update();
		}
	}

	/**
	 * Save Stripe settings.
	 *
	 * @return void
	 */
	private function handle_stripe_settings_save() {
		if ( ! isset( $_POST['vintrica_stripe_settings_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['vintrica_stripe_settings_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, self::STRIPE_SETTINGS_NONCE ) ) {
			add_settings_error( 'vintrica_stripe', 'vintrica_stripe_nonce', __( 'Overenie bezpečnosti zlyhalo.', 'vintrica-vignette-form' ), 'error' );
			return;
		}

		$result = vintrica_vignette_form()->stripe->save_settings(
			array(
				'secret_key'            => isset( $_POST['vintrica_stripe_secret_key'] ) ? sanitize_text_field( wp_unslash( $_POST['vintrica_stripe_secret_key'] ) ) : '',
				'publishable_key'       => isset( $_POST['vintrica_stripe_publishable_key'] ) ? sanitize_text_field( wp_unslash( $_POST['vintrica_stripe_publishable_key'] ) ) : '',
				'webhook_secret'        => isset( $_POST['vintrica_stripe_webhook_secret'] ) ? sanitize_text_field( wp_unslash( $_POST['vintrica_stripe_webhook_secret'] ) ) : '',
				'test_mode'             => ! empty( $_POST['vintrica_stripe_test_mode'] ),
				'cancel_redirect_url'   => isset( $_POST['vintrica_stripe_cancel_redirect_url'] ) ? wp_unslash( $_POST['vintrica_stripe_cancel_redirect_url'] ) : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			add_settings_error( 'vintrica_stripe', 'vintrica_stripe_save_failed', $result->get_error_message(), 'error' );
			return;
		}

		add_settings_error( 'vintrica_stripe', 'vintrica_stripe_saved', __( 'Nastavenia Stripe boli uložené.', 'vintrica-vignette-form' ), 'updated' );

		$key_warning = vintrica_vignette_form()->stripe->get_key_mode_warning();

		if ( '' !== $key_warning ) {
			add_settings_error( 'vintrica_stripe', 'vintrica_stripe_key_warning', $key_warning, 'warning' );
		}
	}

	/**
	 * Save general plugin settings.
	 *
	 * @return void
	 */
	private function handle_settings_save() {
		if ( ! isset( $_POST['vintrica_settings_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['vintrica_settings_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, self::SETTINGS_NONCE ) ) {
			add_settings_error( 'vintrica_settings', 'vintrica_settings_nonce', __( 'Overenie bezpečnosti zlyhalo.', 'vintrica-vignette-form' ), 'error' );
			return;
		}

		$result = vintrica_vignette_form()->settings->save_settings(
			array(
				'notification_email'   => isset( $_POST['vintrica_notification_email'] ) ? wp_unslash( $_POST['vintrica_notification_email'] ) : '',
				'success_redirect_url' => isset( $_POST['vintrica_success_redirect_url'] ) ? wp_unslash( $_POST['vintrica_success_redirect_url'] ) : '',
			)
		);

		if ( is_wp_error( $result ) ) {
			add_settings_error( 'vintrica_settings', 'vintrica_settings_save_failed', $result->get_error_message(), 'error' );
			return;
		}

		add_settings_error( 'vintrica_settings', 'vintrica_settings_saved', __( 'Nastavenia boli uložené.', 'vintrica-vignette-form' ), 'updated' );
	}

	/**
	 * Run Stripe API connectivity test from admin.
	 *
	 * @return void
	 */
	private function handle_stripe_test_connection() {
		if ( ! isset( $_POST['vintrica_stripe_test_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['vintrica_stripe_test_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, self::STRIPE_TEST_NONCE ) ) {
			add_settings_error( 'vintrica_stripe', 'vintrica_stripe_test_nonce', __( 'Overenie bezpečnosti zlyhalo.', 'vintrica-vignette-form' ), 'error' );
			return;
		}

		$result = vintrica_vignette_form()->stripe->test_connection();

		if ( is_wp_error( $result ) ) {
			add_settings_error( 'vintrica_stripe', 'vintrica_stripe_test_failed', $result->get_error_message(), 'error' );
			return;
		}

		add_settings_error( 'vintrica_stripe', 'vintrica_stripe_test_ok', __( 'Stripe pripojenie je funkčné.', 'vintrica-vignette-form' ), 'updated' );
	}

	/**
	 * Update order status from admin detail page.
	 *
	 * @return void
	 */
	private function handle_order_status_update() {
		if ( ! isset( $_POST['vintrica_order_action_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['vintrica_order_action_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, self::ORDER_ACTION_NONCE ) ) {
			add_settings_error( 'vintrica_orders', 'vintrica_order_nonce', __( 'Overenie bezpečnosti zlyhalo.', 'vintrica-vignette-form' ), 'error' );
			return;
		}

		$order_id = (int) $_POST['order_id'];
		$status   = sanitize_key( wp_unslash( $_POST['order_status'] ) );
		$result   = vintrica_vignette_form()->orders->update_status( $order_id, $status );

		if ( is_wp_error( $result ) ) {
			add_settings_error( 'vintrica_orders', 'vintrica_order_status', $result->get_error_message(), 'error' );
			return;
		}

		add_settings_error( 'vintrica_orders', 'vintrica_order_status', __( 'Stav objednávky bol aktualizovaný.', 'vintrica-vignette-form' ), 'updated' );
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
			<p><?php echo esc_html__( 'Objednávkový formulár diaľničných známok so štvorkrokovým checkoutom a Stripe platbou.', 'vintrica-vignette-form' ); ?></p>
			<p><code>[vintrica_vignette_form]</code></p>
			<ol>
				<li><?php echo esc_html__( 'Výber známok', 'vintrica-vignette-form' ); ?></li>
				<li><?php echo esc_html__( 'Fakturačné údaje', 'vintrica-vignette-form' ); ?></li>
				<li><?php echo esc_html__( 'Kontrola objednávky', 'vintrica-vignette-form' ); ?></li>
				<li><?php echo esc_html__( 'Stripe platba', 'vintrica-vignette-form' ); ?></li>
			</ol>
			<div class="notice notice-info inline">
				<p><?php echo esc_html__( 'Ak používate cache plugin (WP Super Cache, LiteSpeed, Cloudflare a pod.), vylúčte stránku s formulárom z cache. Inak môže zlyhať overenie bezpečnosti pri platbe.', 'vintrica-vignette-form' ); ?></p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render orders list or order detail.
	 *
	 * @return void
	 */
	public function render_orders_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnenie na prístup k tejto stránke.', 'vintrica-vignette-form' ) );
		}

		settings_errors( 'vintrica_orders' );

		if ( isset( $_GET['order_id'] ) ) {
			$this->render_order_detail( (int) $_GET['order_id'] );
			return;
		}

		$this->render_orders_list();
	}

	/**
	 * Render orders list table.
	 *
	 * @return void
	 */
	private function render_orders_list() {
		$orders = vintrica_vignette_form()->orders->get_recent_orders( 100 );
		$orders_handler = vintrica_vignette_form()->orders;

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Objednávky VINTRICA', 'vintrica-vignette-form' ); ?></h1>
			<table class="widefat striped vintrica-admin-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Číslo objednávky', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'Dátum', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'Meno zákazníka', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'E-mail', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'Počet známok', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'Celková suma', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'Stav objednávky', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'Akcie', 'vintrica-vignette-form' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $orders ) ) : ?>
						<tr>
							<td colspan="8"><?php echo esc_html__( 'Zatiaľ nie sú žiadne objednávky.', 'vintrica-vignette-form' ); ?></td>
						</tr>
					<?php else : ?>
						<?php foreach ( $orders as $order ) : ?>
							<?php
							$billing = $orders_handler->decode_billing( $order );
							$email   = isset( $billing['email'] ) ? $billing['email'] : '';
							$detail_url = add_query_arg(
								array(
									'page'     => self::ORDERS_SLUG,
									'order_id' => (int) $order->id,
								),
								admin_url( 'admin.php' )
							);
							?>
							<tr>
								<td>
									<a href="<?php echo esc_url( $detail_url ); ?>"><?php echo esc_html( $order->order_number ); ?></a>
								</td>
								<td><?php echo esc_html( get_date_from_gmt( $order->created_at, 'd.m.Y H:i' ) ); ?></td>
								<td><?php echo esc_html( $orders_handler->get_customer_name( $order ) ); ?></td>
								<td><?php echo esc_html( $email ); ?></td>
								<td><?php echo esc_html( (string) $orders_handler->count_vignettes( $order ) ); ?></td>
								<td><?php echo esc_html( $order->currency . ' ' . number_format_i18n( (float) $order->total, 2 ) ); ?></td>
								<td><?php $this->render_status_badge( $order->status ); ?></td>
								<td>
									<a class="button button-small" href="<?php echo esc_url( $detail_url ); ?>">
										<?php echo esc_html__( 'Detail', 'vintrica-vignette-form' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Render single order detail page.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	private function render_order_detail( $order_id ) {
		$orders_handler = vintrica_vignette_form()->orders;
		$pricing        = vintrica_vignette_form()->pricing;
		$order          = $orders_handler->get_order( $order_id );

		if ( ! $order ) {
			echo '<div class="wrap"><p>' . esc_html__( 'Objednávka nebola nájdená.', 'vintrica-vignette-form' ) . '</p></div>';
			return;
		}

		$billing   = $orders_handler->decode_billing( $order );
		$vignettes = $orders_handler->decode_vignettes( $order );
		$countries = $pricing->get_countries();
		$vehicles  = $pricing->get_vehicle_types();
		$list_url  = admin_url( 'admin.php?page=' . self::ORDERS_SLUG );

		?>
		<div class="wrap vintrica-order-detail">
			<p><a href="<?php echo esc_url( $list_url ); ?>">&larr; <?php echo esc_html__( 'Späť na zoznam objednávok', 'vintrica-vignette-form' ); ?></a></p>
			<h1><?php echo esc_html( $order->order_number ); ?></h1>

			<div class="vintrica-order-detail__meta">
				<p>
					<strong><?php echo esc_html__( 'Stav:', 'vintrica-vignette-form' ); ?></strong>
					<?php $this->render_status_badge( $order->status ); ?>
				</p>
				<p>
					<strong><?php echo esc_html__( 'Vytvorené:', 'vintrica-vignette-form' ); ?></strong>
					<?php echo esc_html( get_date_from_gmt( $order->created_at, 'd.m.Y H:i' ) ); ?>
				</p>
				<?php if ( ! empty( $order->stripe_session_id ) ) : ?>
					<p>
						<strong><?php echo esc_html__( 'Stripe session ID:', 'vintrica-vignette-form' ); ?></strong>
						<code><?php echo esc_html( $order->stripe_session_id ); ?></code>
					</p>
				<?php endif; ?>
				<?php if ( ! empty( $order->stripe_payment_intent_id ) ) : ?>
					<p>
						<strong><?php echo esc_html__( 'Stripe payment intent ID:', 'vintrica-vignette-form' ); ?></strong>
						<code><?php echo esc_html( $order->stripe_payment_intent_id ); ?></code>
					</p>
				<?php endif; ?>
				<?php if ( ! empty( $order->paid_at ) ) : ?>
					<p>
						<strong><?php echo esc_html__( 'Dátum úhrady:', 'vintrica-vignette-form' ); ?></strong>
						<?php echo esc_html( get_date_from_gmt( $order->paid_at, 'd.m.Y H:i' ) ); ?>
					</p>
				<?php endif; ?>
			</div>

			<h2><?php echo esc_html__( 'Fakturačné údaje', 'vintrica-vignette-form' ); ?></h2>
			<table class="widefat striped vintrica-admin-table">
				<tbody>
					<tr><th><?php echo esc_html__( 'Meno', 'vintrica-vignette-form' ); ?></th><td><?php echo esc_html( $billing['first_name'] ?? '' ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Priezvisko', 'vintrica-vignette-form' ); ?></th><td><?php echo esc_html( $billing['last_name'] ?? '' ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'E-mail', 'vintrica-vignette-form' ); ?></th><td><?php echo esc_html( $billing['email'] ?? '' ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Telefón', 'vintrica-vignette-form' ); ?></th><td><?php echo esc_html( $billing['phone'] ?? '' ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Firma', 'vintrica-vignette-form' ); ?></th><td><?php echo esc_html( $billing['company'] ?? '' ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'IČO', 'vintrica-vignette-form' ); ?></th><td><?php echo esc_html( $billing['ico'] ?? '' ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'DIČ', 'vintrica-vignette-form' ); ?></th><td><?php echo esc_html( $billing['dic'] ?? '' ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'IČ DPH', 'vintrica-vignette-form' ); ?></th><td><?php echo esc_html( $billing['ic_dph'] ?? '' ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Adresa', 'vintrica-vignette-form' ); ?></th><td><?php echo esc_html( $this->format_address( $billing, $countries ) ); ?></td></tr>
				</tbody>
			</table>

			<h2><?php echo esc_html__( 'Známky', 'vintrica-vignette-form' ); ?></h2>
			<table class="widefat striped vintrica-admin-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Krajina známky', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'Platnosť', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'Typ vozidla', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'ŠPZ', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'Krajina registrácie', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'Začiatok platnosti', 'vintrica-vignette-form' ); ?></th>
						<th><?php echo esc_html__( 'Cena', 'vintrica-vignette-form' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $vignettes as $vignette ) : ?>
						<?php
						$country_code = $vignette['country'] ?? '';
						$validity_code = $vignette['vignette_validity'] ?? '';
						$vehicle_type  = $vignette['vehicle_type'] ?? '';
						$price         = $pricing->get_vignette_price( $country_code, $validity_code, $vehicle_type );
						$price         = null !== $price ? $price : 0;
						?>
						<tr>
							<td><?php echo esc_html( $countries[ $country_code ] ?? $country_code ); ?></td>
							<td><?php echo esc_html( $pricing->get_validity_label( $country_code, $validity_code, $vehicle_type ) ); ?></td>
							<td><?php echo esc_html( $vehicles[ $vignette['vehicle_type'] ?? '' ] ?? ( $vignette['vehicle_type'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( $vignette['license_plate'] ?? '' ); ?></td>
							<td><?php echo esc_html( $countries[ $vignette['registration_country'] ?? '' ] ?? ( $vignette['registration_country'] ?? '' ) ); ?></td>
							<td><?php echo esc_html( $vignette['start_date'] ?? '' ); ?></td>
							<td><?php echo esc_html( $order->currency . ' ' . number_format_i18n( $price, 2 ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>

			<h2><?php echo esc_html__( 'Rozpis ceny', 'vintrica-vignette-form' ); ?></h2>
			<table class="widefat striped vintrica-admin-table">
				<tbody>
					<tr><th><?php echo esc_html__( 'Medzisúčet', 'vintrica-vignette-form' ); ?></th><td><?php echo esc_html( $order->currency . ' ' . number_format_i18n( (float) $order->subtotal, 2 ) ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Servisný poplatok', 'vintrica-vignette-form' ); ?></th><td><?php echo esc_html( $order->currency . ' ' . number_format_i18n( (float) $order->service_fee, 2 ) ); ?></td></tr>
					<tr><th><?php echo esc_html__( 'Celková suma', 'vintrica-vignette-form' ); ?></th><td><strong><?php echo esc_html( $order->currency . ' ' . number_format_i18n( (float) $order->total, 2 ) ); ?></strong></td></tr>
				</tbody>
			</table>

			<h2><?php echo esc_html__( 'Interné poznámky', 'vintrica-vignette-form' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Miesto pre budúce interné poznámky k objednávke.', 'vintrica-vignette-form' ); ?></p>
			<textarea class="large-text" rows="4" readonly placeholder="<?php echo esc_attr__( 'Interné poznámky budú dostupné v ďalšej verzii.', 'vintrica-vignette-form' ); ?>"></textarea>

			<h2><?php echo esc_html__( 'Zmena stavu', 'vintrica-vignette-form' ); ?></h2>
			<form method="post">
				<?php wp_nonce_field( self::ORDER_ACTION_NONCE, 'vintrica_order_action_nonce' ); ?>
				<input type="hidden" name="order_id" value="<?php echo esc_attr( (string) $order->id ); ?>" />
				<select name="order_status">
					<?php foreach ( $orders_handler->get_statuses() as $status_key => $status_label ) : ?>
						<option value="<?php echo esc_attr( $status_key ); ?>" <?php selected( $orders_handler->normalize_status( $order->status ), $status_key ); ?>>
							<?php echo esc_html( $status_label ); ?>
						</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" name="vintrica_order_status_submit" value="1" class="button button-primary">
					<?php echo esc_html__( 'Uložiť stav', 'vintrica-vignette-form' ); ?>
				</button>
			</form>
		</div>
		<?php
	}

	/**
	 * Render general settings page.
	 *
	 * @return void
	 */
	public function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnenie na prístup k tejto stránke.', 'vintrica-vignette-form' ) );
		}

		$settings = vintrica_vignette_form()->settings;

		settings_errors( 'vintrica_settings' );
		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Nastavenia VINTRICA', 'vintrica-vignette-form' ); ?></h1>
			<form method="post">
				<?php wp_nonce_field( self::SETTINGS_NONCE, 'vintrica_settings_nonce' ); ?>
				<table class="form-table vintrica-admin-table" role="presentation">
					<tr>
						<th scope="row"><label for="vintrica-notification-email"><?php echo esc_html__( 'E-mail pre notifikácie objednávok', 'vintrica-vignette-form' ); ?></label></th>
						<td>
							<input type="email" class="regular-text" id="vintrica-notification-email" name="vintrica_notification_email" value="<?php echo esc_attr( $settings->get_configured_notification_email() ); ?>" autocomplete="email" />
							<p class="description"><?php echo esc_html__( 'Na túto e-mailovú adresu budú chodiť notifikácie o prijatej objednávke.', 'vintrica-vignette-form' ); ?></p>
							<p class="description">
								<?php
								printf(
									/* translators: %s: fallback admin email */
									esc_html__( 'Ak necháte prázdne, použije sa e-mail administrátora: %s', 'vintrica-vignette-form' ),
									esc_html( sanitize_email( (string) get_option( 'admin_email' ) ) )
								);
								?>
							</p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="vintrica-success-redirect-url"><?php echo esc_html__( 'URL ďakovnej stránky po úspešnej platbe', 'vintrica-vignette-form' ); ?></label></th>
						<td>
							<input type="url" class="regular-text code" id="vintrica-success-redirect-url" name="vintrica_success_redirect_url" value="<?php echo esc_attr( $settings->get_configured_success_redirect_url() ); ?>" placeholder="<?php echo esc_attr( home_url( '/dakujeme/' ) ); ?>" />
							<p class="description"><?php echo esc_html__( 'Sem vložte URL stránky, kam bude zákazník presmerovaný po úspešnej platbe.', 'vintrica-vignette-form' ); ?></p>
							<p class="description">
								<?php
								printf(
									/* translators: %s: default thank-you URL */
									esc_html__( 'Ak necháte prázdne, použije sa predvolená adresa: %s', 'vintrica-vignette-form' ),
									esc_url( home_url( '/dakujeme/' ) )
								);
								?>
							</p>
							<p class="description"><?php echo esc_html__( 'Po úspešnej platbe sa k adrese automaticky pripoja parametre vintrica_order_id a token.', 'vintrica-vignette-form' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Uložiť nastavenia', 'vintrica-vignette-form' ), 'primary', 'vintrica_settings_submit' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render catalog admin page.
	 *
	 * @return void
	 */
	public function render_catalog_page() {
		if ( $this->catalog_admin ) {
			$this->catalog_admin->render_page();
		}
	}

	/**
	 * Render Stripe settings page.
	 *
	 * @return void
	 */
	public function render_stripe_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnenie na prístup k tejto stránke.', 'vintrica-vignette-form' ) );
		}

		settings_errors( 'vintrica_stripe' );

		$stripe      = vintrica_vignette_form()->stripe;
		$diagnostics = $stripe->get_diagnostics();

		?>
		<div class="wrap">
			<h1><?php echo esc_html__( 'Nastavenia Stripe', 'vintrica-vignette-form' ); ?></h1>
			<p><?php echo esc_html__( 'Zadajte Stripe API kľúče pre presmerovanie zákazníkov na Stripe Checkout po potvrdení objednávky.', 'vintrica-vignette-form' ); ?></p>
			<p>
				<strong><?php echo esc_html__( 'VINTRICA webhook URL:', 'vintrica-vignette-form' ); ?></strong><br />
				<code><?php echo esc_html( $stripe->get_webhook_url() ); ?></code>
			</p>
			<div class="notice notice-info inline">
				<p><?php echo esc_html__( 'Stránka s checkout formulárom musí byť vylúčená z cache (WP Super Cache, LiteSpeed, Cloudflare a pod.), inak platobný nonce expiruje a tlačidlo Zaplatiť zlyhá.', 'vintrica-vignette-form' ); ?></p>
			</div>

			<?php if ( ! empty( $diagnostics['key_warning'] ) ) : ?>
				<div class="notice notice-warning">
					<p><?php echo esc_html( $diagnostics['key_warning'] ); ?></p>
				</div>
			<?php endif; ?>

			<div class="vintrica-stripe-diagnostics">
				<h2><?php echo esc_html__( 'Diagnostika Stripe', 'vintrica-vignette-form' ); ?></h2>
				<table class="widefat striped vintrica-admin-table">
					<tbody>
						<tr>
							<th><?php echo esc_html__( 'Testovací režim', 'vintrica-vignette-form' ); ?></th>
							<td><?php echo esc_html( $diagnostics['test_mode'] ? __( 'Zapnutý', 'vintrica-vignette-form' ) : __( 'Vypnutý (live)', 'vintrica-vignette-form' ) ); ?></td>
						</tr>
						<tr>
							<th><?php echo esc_html__( 'Secret Key', 'vintrica-vignette-form' ); ?></th>
							<td><?php echo esc_html( $diagnostics['has_secret_key'] ? __( 'Nastavený', 'vintrica-vignette-form' ) : __( 'Chýba', 'vintrica-vignette-form' ) ); ?></td>
						</tr>
						<tr>
							<th><?php echo esc_html__( 'Publishable Key', 'vintrica-vignette-form' ); ?></th>
							<td><?php echo esc_html( $diagnostics['has_publishable_key'] ? __( 'Nastavený', 'vintrica-vignette-form' ) : __( 'Chýba', 'vintrica-vignette-form' ) ); ?></td>
						</tr>
						<tr>
							<th><?php echo esc_html__( 'Webhook Secret', 'vintrica-vignette-form' ); ?></th>
							<td><?php echo esc_html( $diagnostics['has_webhook_secret'] ? __( 'Nastavený', 'vintrica-vignette-form' ) : __( 'Chýba', 'vintrica-vignette-form' ) ); ?></td>
						</tr>
						<tr>
							<th><?php echo esc_html__( 'Typ Secret Key', 'vintrica-vignette-form' ); ?></th>
							<td><code><?php echo esc_html( $diagnostics['key_prefix'] ); ?></code></td>
						</tr>
						<tr>
							<th><?php echo esc_html__( 'Základná success URL', 'vintrica-vignette-form' ); ?></th>
							<td><a href="<?php echo esc_url( $diagnostics['success_redirect_base_url'] ); ?>"><?php echo esc_html( $diagnostics['success_redirect_base_url'] ); ?></a></td>
						</tr>
						<tr>
							<th><?php echo esc_html__( 'Základná cancel URL', 'vintrica-vignette-form' ); ?></th>
							<td><a href="<?php echo esc_url( $diagnostics['cancel_redirect_base_url'] ); ?>"><?php echo esc_html( $diagnostics['cancel_redirect_base_url'] ); ?></a></td>
						</tr>
						<tr>
							<th><?php echo esc_html__( 'Success URL (ukážka)', 'vintrica-vignette-form' ); ?></th>
							<td><code><?php echo esc_html( $diagnostics['success_url'] ); ?></code></td>
						</tr>
						<tr>
							<th><?php echo esc_html__( 'Cancel URL (ukážka)', 'vintrica-vignette-form' ); ?></th>
							<td><code><?php echo esc_html( $diagnostics['cancel_url'] ); ?></code></td>
						</tr>
					</tbody>
				</table>
				<form method="post" class="vintrica-stripe-test-form">
					<?php wp_nonce_field( self::STRIPE_TEST_NONCE, 'vintrica_stripe_test_nonce' ); ?>
					<?php submit_button( __( 'Otestovať Stripe pripojenie', 'vintrica-vignette-form' ), 'secondary', 'vintrica_stripe_test_submit', false ); ?>
				</form>
			</div>
			<form method="post">
				<?php wp_nonce_field( self::STRIPE_SETTINGS_NONCE, 'vintrica_stripe_settings_nonce' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="vintrica-stripe-secret-key"><?php echo esc_html__( 'Secret Key', 'vintrica-vignette-form' ); ?></label></th>
						<td><input type="password" class="regular-text" id="vintrica-stripe-secret-key" name="vintrica_stripe_secret_key" value="<?php echo esc_attr( $stripe->get_secret_key() ); ?>" autocomplete="off" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="vintrica-stripe-publishable-key"><?php echo esc_html__( 'Publishable Key', 'vintrica-vignette-form' ); ?></label></th>
						<td><input type="text" class="regular-text" id="vintrica-stripe-publishable-key" name="vintrica_stripe_publishable_key" value="<?php echo esc_attr( $stripe->get_publishable_key() ); ?>" autocomplete="off" /></td>
					</tr>
					<tr>
						<th scope="row"><label for="vintrica-stripe-webhook-secret"><?php echo esc_html__( 'Stripe Webhook Secret', 'vintrica-vignette-form' ); ?></label></th>
						<td>
							<input type="password" class="regular-text" id="vintrica-stripe-webhook-secret" name="vintrica_stripe_webhook_secret" value="<?php echo esc_attr( $stripe->get_webhook_secret() ); ?>" autocomplete="off" />
							<p class="description"><?php echo esc_html__( 'Signing secret z webhooku checkout.session.completed vo vašom Stripe dashboarde.', 'vintrica-vignette-form' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Testovací režim', 'vintrica-vignette-form' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="vintrica_stripe_test_mode" value="1" <?php checked( $stripe->is_test_mode() ); ?> />
								<?php echo esc_html__( 'Použiť testovacie Stripe kľúče', 'vintrica-vignette-form' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="vintrica-stripe-cancel-redirect-url"><?php echo esc_html__( 'URL stránky po neúspešnej alebo zrušenej platbe', 'vintrica-vignette-form' ); ?></label></th>
						<td>
							<input type="url" class="regular-text code" id="vintrica-stripe-cancel-redirect-url" name="vintrica_stripe_cancel_redirect_url" value="<?php echo esc_attr( $stripe->get_configured_cancel_redirect_url() ); ?>" placeholder="<?php echo esc_attr( home_url( '/platba-neuspesna/' ) ); ?>" />
							<p class="description"><?php echo esc_html__( 'Sem vložte URL stránky, kam bude zákazník presmerovaný po zrušenej alebo neúspešnej platbe.', 'vintrica-vignette-form' ); ?></p>
							<p class="description"><?php echo esc_html__( 'Ak necháte prázdne, použije sa predvolená adresa /platba-neuspesna/.', 'vintrica-vignette-form' ); ?></p>
						</td>
					</tr>
				</table>
				<?php submit_button( __( 'Uložiť nastavenia', 'vintrica-vignette-form' ), 'primary', 'vintrica_stripe_settings_submit' ); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render a status badge.
	 *
	 * @param string $status Order status.
	 * @return void
	 */
	private function render_status_badge( $status ) {
		$orders_handler = vintrica_vignette_form()->orders;
		$normalized     = $orders_handler->normalize_status( $status );
		$class          = 'vintrica-status-badge vintrica-status-badge--' . esc_attr( $normalized );

		printf(
			'<span class="%1$s">%2$s</span>',
			esc_attr( $class ),
			esc_html( $orders_handler->get_status_label( $status ) )
		);
	}

	/**
	 * Format billing address for display.
	 *
	 * @param array<string, mixed> $billing   Billing data.
	 * @param array<string, string> $countries Country labels.
	 * @return string
	 */
	private function format_address( array $billing, array $countries ) {
		$parts = array_filter(
			array(
				isset( $billing['street'] ) ? trim( (string) $billing['street'] ) : '',
				trim(
					( isset( $billing['zip'] ) ? trim( (string) $billing['zip'] ) : '' ) . ' ' .
					( isset( $billing['city'] ) ? trim( (string) $billing['city'] ) : '' )
				),
				isset( $billing['country'] ) ? ( $countries[ $billing['country'] ] ?? (string) $billing['country'] ) : '',
			)
		);

		return implode( ', ', $parts );
	}
}
