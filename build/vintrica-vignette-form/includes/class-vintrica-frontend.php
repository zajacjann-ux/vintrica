<?php
/**
 * Frontend form rendering and asset handling.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Frontend
 */
class Vintrica_Frontend {

	/**
	 * Shortcode tag.
	 */
	const SHORTCODE = 'vintrica_vignette_form';

	/**
	 * Hidden field name for vignette order payload.
	 */
	const VIGNETTES_FIELD = 'vintrica_vignettes';

	/**
	 * Security handler instance.
	 *
	 * @var Vintrica_Security
	 */
	private $security;

	/**
	 * Pricing handler instance.
	 *
	 * @var Vintrica_Pricing
	 */
	private $pricing;

	/**
	 * Constructor.
	 *
	 * @param Vintrica_Security $security Security handler.
	 * @param Vintrica_Pricing  $pricing  Pricing handler.
	 */
	public function __construct( Vintrica_Security $security, Vintrica_Pricing $pricing ) {
		$this->security = $security;
		$this->pricing  = $pricing;

		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
		add_action( 'template_redirect', array( $this, 'handle_form_submission' ) );
	}

	/**
	 * Register the vignette form shortcode.
	 *
	 * @return void
	 */
	public function register_shortcode() {
		add_shortcode( self::SHORTCODE, array( $this, 'render_form' ) );
	}

	/**
	 * Register frontend assets.
	 *
	 * @return void
	 */
	public function register_assets() {
		wp_register_style(
			'vintrica-frontend',
			VINTRICA_PLUGIN_URL . 'assets/css/frontend.css',
			array(),
			VINTRICA_VERSION
		);

		wp_register_script(
			'vintrica-frontend',
			VINTRICA_PLUGIN_URL . 'assets/js/frontend.js',
			array(),
			VINTRICA_VERSION,
			true
		);
	}

	/**
	 * Enqueue frontend assets and pass server-side config to JavaScript.
	 *
	 * @param string $storage_key Persistent storage key for the current form instance.
	 * @return void
	 */
	private function enqueue_assets( $storage_key ) {
		wp_enqueue_style( 'vintrica-frontend' );
		wp_enqueue_script( 'vintrica-frontend' );

		$woocommerce = vintrica_vignette_form()->woocommerce;

		wp_localize_script(
			'vintrica-frontend',
			'vintricaConfig',
			array(
				'storageKey'    => $storage_key,
				'checkoutReady' => $woocommerce->is_checkout_ready(),
				'config'        => $this->pricing->get_frontend_config(),
				'strings'       => $this->get_js_strings(),
			)
		);
	}

	/**
	 * Get localized JavaScript strings.
	 *
	 * @return array<string, string>
	 */
	private function get_js_strings() {
		return array(
			'selectCountry'             => __( 'Vyberte krajinu', 'vintrica-vignette-form' ),
			'selectValidity'            => __( 'Vyberte platnosť známky', 'vintrica-vignette-form' ),
			'selectVehicleType'         => __( 'Vyberte typ vozidla', 'vintrica-vignette-form' ),
			'selectRegistrationCountry' => __( 'Vyberte krajinu registrácie vozidla', 'vintrica-vignette-form' ),
			'selectCountryFirst'        => __( 'Najprv vyberte krajinu', 'vintrica-vignette-form' ),
			'addVignette'               => __( 'Pridať známku', 'vintrica-vignette-form' ),
			'updateVignette'            => __( 'Upraviť známku', 'vintrica-vignette-form' ),
			'cancelEdit'                => __( 'Zrušiť úpravu', 'vintrica-vignette-form' ),
			'edit'                      => __( 'Upraviť', 'vintrica-vignette-form' ),
			'remove'                    => __( 'Odstrániť', 'vintrica-vignette-form' ),
			'emptySummary'              => __( 'Zatiaľ nebola pridaná žiadna známka.', 'vintrica-vignette-form' ),
			'vignetteCount'             => __( 'Počet známok', 'vintrica-vignette-form' ),
			'subtotal'                  => __( 'Medzisúčet', 'vintrica-vignette-form' ),
			'serviceFee'                => __( 'Servisný poplatok', 'vintrica-vignette-form' ),
			'total'                     => __( 'Celková suma', 'vintrica-vignette-form' ),
			'validationRequired'          => __( 'Neplatné údaje.', 'vintrica-vignette-form' ),
			'validationOrderEmpty'      => __( 'Pridajte aspoň jednu známku.', 'vintrica-vignette-form' ),
			'validationInvalidData'     => __( 'Neplatné údaje.', 'vintrica-vignette-form' ),
			'vignetteAdded'             => __( 'Známka bola úspešne pridaná.', 'vintrica-vignette-form' ),
			'vignetteUpdated'           => __( 'Známka bola úspešne upravená.', 'vintrica-vignette-form' ),
			'vignetteRemoved'           => __( 'Známka bola odstránená.', 'vintrica-vignette-form' ),
			'woocommerceNotReady'       => __( 'Prepojenie s WooCommerce košíkom ešte nie je implementované.', 'vintrica-vignette-form' ),
			'plateLabel'                => __( 'ŠPZ', 'vintrica-vignette-form' ),
			'startsLabel'               => __( 'Začiatok', 'vintrica-vignette-form' ),
		);
	}

	/**
	 * Handle form submission on template redirect.
	 *
	 * @return void
	 */
	public function handle_form_submission() {
		if ( 'POST' !== sanitize_text_field( wp_unslash( $_SERVER['REQUEST_METHOD'] ?? '' ) ) ) {
			return;
		}

		if ( ! isset( $_POST['vintrica_vignette_submit'] ) ) {
			return;
		}

		if ( ! vintrica_vignette_form()->woocommerce->is_checkout_ready() ) {
			return;
		}

		if ( ! $this->security->verify_form_request() ) {
			$this->set_notice( 'error', __( 'Overenie bezpečnosti zlyhalo. Skúste to znova.', 'vintrica-vignette-form' ) );
			return;
		}

		if ( ! isset( $_POST[ self::VIGNETTES_FIELD ] ) ) {
			$this->set_notice( 'error', __( 'Nebola odoslaná žiadna objednávka známok.', 'vintrica-vignette-form' ) );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$raw_order = wp_unslash( $_POST[ self::VIGNETTES_FIELD ] );
		$vignettes = $this->security->sanitize_vignette_order( $raw_order );

		if ( is_wp_error( $vignettes ) ) {
			$this->set_notice( 'error', $vignettes->get_error_message() );
			return;
		}

		$totals = $this->pricing->calculate_totals( $vignettes );

		$order_data = array(
			'vignettes' => $vignettes,
			'totals'    => $totals,
		);

		/**
		 * Fires after the vignette order is validated and priced server-side.
		 *
		 * @param array $order_data Validated order with vignettes and totals.
		 */
		do_action( 'vintrica_vignette_form_submitted', $order_data );

		$this->set_notice(
			'success',
			sprintf(
				/* translators: %d: number of vignettes */
				_n(
					'Vaša objednávka s %d známkou bola prijatá.',
					'Vaša objednávka s %d známkami bola prijatá.',
					$totals['count'],
					'vintrica-vignette-form'
				),
				(int) $totals['count']
			)
		);
	}

	/**
	 * Store a frontend notice in a transient keyed by user session.
	 *
	 * @param string $type    Notice type (success|error).
	 * @param string $message Notice message.
	 * @return void
	 */
	private function set_notice( $type, $message ) {
		$notices   = get_transient( $this->get_notice_key() );
		$notices   = is_array( $notices ) ? $notices : array();
		$notices[] = array(
			'type'    => sanitize_key( $type ),
			'message' => sanitize_text_field( $message ),
		);

		set_transient( $this->get_notice_key(), $notices, MINUTE_IN_SECONDS );
	}

	/**
	 * Get and clear stored notices.
	 *
	 * @return array<int, array<string, string>>
	 */
	private function get_notices() {
		$notices = get_transient( $this->get_notice_key() );
		delete_transient( $this->get_notice_key() );

		return is_array( $notices ) ? $notices : array();
	}

	/**
	 * Build a unique notice key for the current visitor.
	 *
	 * @return string
	 */
	private function get_notice_key() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';

		return 'vintrica_notices_' . md5( $ip . wp_salt( 'auth' ) );
	}

	/**
	 * Build a storage key for client-side persistence.
	 *
	 * @return string
	 */
	private function get_storage_key() {
		$post_id = get_the_ID();

		return 'vintrica_vignettes_' . ( $post_id ? (int) $post_id : 'default' );
	}

	/**
	 * Render the vignette order form.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_form( $atts = array() ) {
		$storage_key = $this->get_storage_key();

		$this->enqueue_assets( $storage_key );

		$countries     = $this->pricing->get_countries();
		$vehicle_types = $this->pricing->get_vehicle_types();
		$notices       = $this->get_notices();

		ob_start();
		?>
		<div class="vintrica-vignette-form-wrapper" data-storage-key="<?php echo esc_attr( $storage_key ); ?>">
			<?php $this->render_notices( $notices ); ?>

			<form class="vintrica-vignette-form" method="post" action="<?php echo esc_url( get_permalink() ); ?>" novalidate>
				<?php $this->security->render_nonce_field(); ?>

				<input type="hidden" name="<?php echo esc_attr( self::VIGNETTES_FIELD ); ?>" id="vintrica-vignettes-data" value="" />

				<div class="vintrica-builder">
					<section class="vintrica-builder__panel vintrica-builder__panel--form" aria-labelledby="vintrica-builder-title">
						<h2 id="vintrica-builder-title" class="vintrica-builder__title">
							<?php echo esc_html__( 'Pridať známku', 'vintrica-vignette-form' ); ?>
						</h2>

						<div class="vintrica-builder__fields">
							<div class="vintrica-field">
								<label for="vintrica-country"><?php echo esc_html__( 'Krajina', 'vintrica-vignette-form' ); ?></label>
								<select id="vintrica-country" data-vintrica-field="country">
									<option value=""><?php echo esc_html__( 'Vyberte krajinu', 'vintrica-vignette-form' ); ?></option>
									<?php foreach ( $countries as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="vintrica-field">
								<label for="vintrica-vehicle-type"><?php echo esc_html__( 'Typ vozidla', 'vintrica-vignette-form' ); ?></label>
								<select id="vintrica-vehicle-type" data-vintrica-field="vehicle_type">
									<option value=""><?php echo esc_html__( 'Vyberte typ vozidla', 'vintrica-vignette-form' ); ?></option>
									<?php foreach ( $vehicle_types as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>

							<div class="vintrica-field">
								<label for="vintrica-vignette-validity"><?php echo esc_html__( 'Platnosť známky', 'vintrica-vignette-form' ); ?></label>
								<select id="vintrica-vignette-validity" data-vintrica-field="vignette_validity" disabled>
									<option value=""><?php echo esc_html__( 'Najprv vyberte krajinu', 'vintrica-vignette-form' ); ?></option>
								</select>
							</div>

							<div class="vintrica-field">
								<label for="vintrica-start-date"><?php echo esc_html__( 'Dátum začiatku platnosti', 'vintrica-vignette-form' ); ?></label>
								<input type="date" id="vintrica-start-date" data-vintrica-field="start_date" />
							</div>

							<div class="vintrica-field">
								<label for="vintrica-license-plate"><?php echo esc_html__( 'ŠPZ', 'vintrica-vignette-form' ); ?></label>
								<input type="text" id="vintrica-license-plate" data-vintrica-field="license_plate" maxlength="20" />
							</div>

							<div class="vintrica-field">
								<label for="vintrica-registration-country"><?php echo esc_html__( 'Krajina registrácie vozidla', 'vintrica-vignette-form' ); ?></label>
								<select id="vintrica-registration-country" data-vintrica-field="registration_country">
									<option value=""><?php echo esc_html__( 'Vyberte krajinu registrácie vozidla', 'vintrica-vignette-form' ); ?></option>
									<?php foreach ( $countries as $value => $label ) : ?>
										<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
									<?php endforeach; ?>
								</select>
							</div>
						</div>

						<div class="vintrica-builder__actions">
							<button type="button" class="vintrica-button vintrica-button--secondary vintrica-cancel-edit" hidden>
								<?php echo esc_html__( 'Zrušiť úpravu', 'vintrica-vignette-form' ); ?>
							</button>
							<button type="button" class="vintrica-button vintrica-button--primary vintrica-add-vignette">
								<?php echo esc_html__( 'Pridať známku', 'vintrica-vignette-form' ); ?>
							</button>
						</div>

						<div class="vintrica-form-success" role="status" hidden></div>
						<div class="vintrica-form-error" role="alert" hidden></div>
					</section>

					<section class="vintrica-builder__panel vintrica-builder__panel--summary" aria-labelledby="vintrica-summary-title">
						<h2 id="vintrica-summary-title" class="vintrica-builder__title">
							<?php echo esc_html__( 'Vaša objednávka', 'vintrica-vignette-form' ); ?>
						</h2>

						<ul class="vintrica-summary-list" aria-live="polite"></ul>

						<div class="vintrica-summary-empty">
							<?php echo esc_html__( 'Zatiaľ nebola pridaná žiadna známka.', 'vintrica-vignette-form' ); ?>
						</div>

						<dl class="vintrica-summary-totals">
							<div class="vintrica-summary-totals__row">
								<dt><?php echo esc_html__( 'Počet známok', 'vintrica-vignette-form' ); ?></dt>
								<dd class="vintrica-total-count">0</dd>
							</div>
							<div class="vintrica-summary-totals__row">
								<dt><?php echo esc_html__( 'Medzisúčet', 'vintrica-vignette-form' ); ?></dt>
								<dd class="vintrica-total-subtotal"><?php echo esc_html( $this->format_price( 0 ) ); ?></dd>
							</div>
							<div class="vintrica-summary-totals__row">
								<dt><?php echo esc_html__( 'Servisný poplatok', 'vintrica-vignette-form' ); ?></dt>
								<dd class="vintrica-total-service-fee"><?php echo esc_html( $this->format_price( 0 ) ); ?></dd>
							</div>
							<div class="vintrica-summary-totals__row vintrica-summary-totals__row--total">
								<dt><?php echo esc_html__( 'Celková suma', 'vintrica-vignette-form' ); ?></dt>
								<dd class="vintrica-total-amount"><?php echo esc_html( $this->format_price( 0 ) ); ?></dd>
							</div>
						</dl>
					</section>
				</div>

				<div class="vintrica-continue-notice" role="status" hidden></div>

				<div class="vintrica-field vintrica-field--submit">
					<button type="submit" name="vintrica_vignette_submit" value="1" class="vintrica-submit" disabled>
						<?php echo esc_html__( 'Pokračovať k platbe', 'vintrica-vignette-form' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Format a price for display.
	 *
	 * @param float $amount Amount to format.
	 * @return string
	 */
	private function format_price( $amount ) {
		return sprintf(
			'%s %s',
			esc_html( $this->pricing->get_currency() ),
			esc_html( number_format_i18n( (float) $amount, 2 ) )
		);
	}

	/**
	 * Render frontend notices.
	 *
	 * @param array<int, array<string, string>> $notices Notice list.
	 * @return void
	 */
	private function render_notices( array $notices ) {
		if ( empty( $notices ) ) {
			return;
		}

		foreach ( $notices as $notice ) {
			$type    = isset( $notice['type'] ) ? sanitize_key( $notice['type'] ) : 'info';
			$message = isset( $notice['message'] ) ? $notice['message'] : '';

			if ( empty( $message ) ) {
				continue;
			}

			printf(
				'<div class="vintrica-notice vintrica-notice--%1$s" role="alert"><p>%2$s</p></div>',
				esc_attr( $type ),
				esc_html( $message )
			);
		}
	}
}
