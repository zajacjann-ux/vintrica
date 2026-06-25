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
	 * Checkout submit field name.
	 */
	const CHECKOUT_SUBMIT_FIELD = 'vintrica_checkout_submit';

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
	 * Checkout handler instance.
	 *
	 * @var Vintrica_Checkout
	 */
	private $checkout;

	/**
	 * Constructor.
	 *
	 * @param Vintrica_Security $security Security handler.
	 * @param Vintrica_Pricing  $pricing  Pricing handler.
	 * @param Vintrica_Checkout $checkout Checkout handler.
	 */
	public function __construct( Vintrica_Security $security, Vintrica_Pricing $pricing, Vintrica_Checkout $checkout ) {
		$this->security = $security;
		$this->pricing  = $pricing;
		$this->checkout = $checkout;

		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
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
			'vintrica-choices',
			VINTRICA_PLUGIN_URL . 'assets/vendor/choices.min.css',
			array(),
			'10.2.0'
		);

		wp_register_style(
			'vintrica-frontend',
			VINTRICA_PLUGIN_URL . 'assets/css/frontend.css',
			array( 'vintrica-choices' ),
			VINTRICA_VERSION
		);

		wp_register_script(
			'vintrica-choices',
			VINTRICA_PLUGIN_URL . 'assets/vendor/choices.min.js',
			array(),
			'10.2.0',
			true
		);

		wp_register_script(
			'vintrica-frontend',
			VINTRICA_PLUGIN_URL . 'assets/js/frontend.js',
			array( 'vintrica-choices' ),
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
		wp_enqueue_style( 'vintrica-choices' );
		wp_enqueue_style( 'vintrica-frontend' );
		wp_enqueue_script( 'vintrica-choices' );
		wp_enqueue_script( 'vintrica-frontend' );

		wp_localize_script(
			'vintrica-frontend',
			'vintricaConfig',
			array(
				'storageKey' => $storage_key,
				'debug'      => defined( 'WP_DEBUG' ) && WP_DEBUG,
				'config'     => $this->pricing->get_frontend_config(),
				'strings'    => $this->get_js_strings(),
			)
		);

		wp_localize_script(
			'vintrica-frontend',
			'VintricaCheckout',
			array(
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'nonce'    => wp_create_nonce( Vintrica_Security::CHECKOUT_NONCE_ACTION ),
				'action'   => 'vintrica_create_checkout_session',
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
			'selectVehicleFirst'        => __( 'Najprv vyberte typ vozidla', 'vintrica-vignette-form' ),
			'addVignette'               => __( 'Pridať známku', 'vintrica-vignette-form' ),
			'updateVignette'            => __( 'Upraviť známku', 'vintrica-vignette-form' ),
			'cancelEdit'                => __( 'Zrušiť úpravu', 'vintrica-vignette-form' ),
			'edit'                      => __( 'Upraviť', 'vintrica-vignette-form' ),
			'remove'                    => __( 'Odstrániť', 'vintrica-vignette-form' ),
			'emptySummary'              => __( 'Zatiaľ nebola pridaná žiadna známka.', 'vintrica-vignette-form' ),
			'vignetteCount'             => __( 'Počet známok', 'vintrica-vignette-form' ),
			'subtotal'                  => __( 'Medzisúčet', 'vintrica-vignette-form' ),
			'total'                     => __( 'Celková suma', 'vintrica-vignette-form' ),
			'validationRequired'        => __( 'Neplatné údaje.', 'vintrica-vignette-form' ),
			'validationOrderEmpty'      => __( 'Pridajte aspoň jednu známku.', 'vintrica-vignette-form' ),
			'validationInvalidData'     => __( 'Neplatné údaje.', 'vintrica-vignette-form' ),
			'validationBillingRequired' => __( 'Vyplňte všetky povinné fakturačné údaje.', 'vintrica-vignette-form' ),
			'validationEmailInvalid'    => __( 'Zadajte platnú e-mailovú adresu.', 'vintrica-vignette-form' ),
			'validationConsentRequired' => __( 'Musíte súhlasiť so všetkými povinnými podmienkami.', 'vintrica-vignette-form' ),
			'vignetteAdded'             => __( 'Známka bola úspešne pridaná.', 'vintrica-vignette-form' ),
			'vignetteUpdated'           => __( 'Známka bola úspešne upravená.', 'vintrica-vignette-form' ),
			'vignetteRemoved'           => __( 'Známka bola odstránená.', 'vintrica-vignette-form' ),
			'plateLabel'                => __( 'ŠPZ', 'vintrica-vignette-form' ),
			'startsLabel'               => __( 'Začiatok', 'vintrica-vignette-form' ),
			'stepBuilder'               => __( 'Výber známok', 'vintrica-vignette-form' ),
			'stepBilling'               => __( 'Fakturačné údaje', 'vintrica-vignette-form' ),
			'stepReview'                => __( 'Kontrola objednávky', 'vintrica-vignette-form' ),
			'stepPayment'               => __( 'Stripe platba', 'vintrica-vignette-form' ),
			'backToBuilder'             => __( 'Späť na známky', 'vintrica-vignette-form' ),
			'backToBilling'             => __( 'Späť na fakturačné údaje', 'vintrica-vignette-form' ),
			'continueToBilling'         => __( 'Pokračovať k fakturačným údajom', 'vintrica-vignette-form' ),
			'continueToReview'          => __( 'Pokračovať na kontrolu', 'vintrica-vignette-form' ),
			'editVignettes'             => __( 'Upraviť známky', 'vintrica-vignette-form' ),
			'payOrder'                  => __( 'Zaplatiť', 'vintrica-vignette-form' ),
			'reviewTitle'               => __( 'Kontrola objednávky', 'vintrica-vignette-form' ),
			'reviewBillingTitle'        => __( 'Fakturačné údaje', 'vintrica-vignette-form' ),
			'reviewVignettesTitle'      => __( 'Vybrané známky', 'vintrica-vignette-form' ),
			'labelVignetteCountry'      => __( 'Krajina známky', 'vintrica-vignette-form' ),
			'labelValidity'             => __( 'Platnosť', 'vintrica-vignette-form' ),
			'labelVehicleType'          => __( 'Typ vozidla', 'vintrica-vignette-form' ),
			'labelRegistrationCountry'  => __( 'Krajina registrácie vozidla', 'vintrica-vignette-form' ),
			'labelStartDate'            => __( 'Dátum začiatku platnosti', 'vintrica-vignette-form' ),
			'labelPrice'                => __( 'Cena', 'vintrica-vignette-form' ),
			'labelAddress'              => __( 'Adresa', 'vintrica-vignette-form' ),
			'labelCompany'              => __( 'Firma', 'vintrica-vignette-form' ),
			'labelFirstName'            => __( 'Meno', 'vintrica-vignette-form' ),
			'labelLastName'             => __( 'Priezvisko', 'vintrica-vignette-form' ),
			'labelEmail'                => __( 'E-mail', 'vintrica-vignette-form' ),
			'labelPhone'                => __( 'Telefón', 'vintrica-vignette-form' ),
			'labelIco'                  => __( 'IČO', 'vintrica-vignette-form' ),
			'labelDic'                  => __( 'DIČ', 'vintrica-vignette-form' ),
			'labelIcDph'                => __( 'IČ DPH', 'vintrica-vignette-form' ),
			'paymentProcessing'         => __( 'Presmerovávam na platbu...', 'vintrica-vignette-form' ),
			'paymentFailed'             => __( 'Platbu sa nepodarilo spustiť. Skúste to prosím znova.', 'vintrica-vignette-form' ),
			'searchPlaceholder'         => __( 'Hľadať…', 'vintrica-vignette-form' ),
			'noResults'                 => __( 'Nenašli sa žiadne výsledky', 'vintrica-vignette-form' ),
			'noChoices'                 => __( 'Žiadne možnosti', 'vintrica-vignette-form' ),
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
	 * Get confirmed order number from query string.
	 *
	 * @return string
	 */
	private function get_confirmed_order_number() {
		if ( ! isset( $_GET['vintrica_order'] ) ) {
			return '';
		}

		return sanitize_text_field( wp_unslash( $_GET['vintrica_order'] ) );
	}

	/**
	 * Check whether the current request indicates a successful Stripe payment.
	 *
	 * @return bool
	 */
	private function is_payment_success() {
		return isset( $_GET['vintrica_paid'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['vintrica_paid'] ) );
	}

	/**
	 * Check whether the current request indicates a cancelled Stripe payment.
	 *
	 * @return bool
	 */
	private function is_payment_cancelled() {
		return isset( $_GET['vintrica_cancelled'] ) && '1' === sanitize_text_field( wp_unslash( $_GET['vintrica_cancelled'] ) );
	}

	/**
	 * Render an inline SVG icon for form fields.
	 *
	 * @param string $icon Icon key.
	 * @return string
	 */
	private function render_field_icon( $icon ) {
		$svg_attrs = 'width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"';

		switch ( $icon ) {
			case 'globe':
				return '<svg ' . $svg_attrs . '><circle cx="12" cy="12" r="10"/><path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"/></svg>';
			case 'vehicle':
				return '<svg ' . $svg_attrs . '><path d="M7 17h-.5a2.5 2.5 0 0 1 0-5h.9l1.2-3.6A2 2 0 0 1 10.5 7h3a2 2 0 0 1 1.9 1.4l1.2 3.6h.9a2.5 2.5 0 0 1 0 5H17"/><circle cx="7.5" cy="17.5" r="1.5"/><circle cx="16.5" cy="17.5" r="1.5"/></svg>';
			case 'validity':
				return '<svg ' . $svg_attrs . '><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/><path d="M8 14h.01M12 14h.01M16 14h.01M8 18h.01M12 18h.01"/></svg>';
			case 'plate':
				return '<svg ' . $svg_attrs . '><rect x="2" y="6" width="20" height="12" rx="2"/><path d="M6 10h.01M10 10h4M18 10h.01"/></svg>';
			case 'registration':
				return '<svg ' . $svg_attrs . '><path d="M12 21s7-4.5 7-11a7 7 0 1 0-14 0c0 6.5 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>';
			case 'calendar':
				return '<svg ' . $svg_attrs . '><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>';
			default:
				return '';
		}
	}

	/**
	 * Render the vignette order form.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_form( $atts = array() ) {
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true );
		}

		if ( ! headers_sent() ) {
			nocache_headers();
		}

		$storage_key  = $this->get_storage_key();
		$order_number = $this->get_confirmed_order_number();

		$this->enqueue_assets( $storage_key );

		$countries = $this->pricing->get_countries();
		$notices   = $this->get_notices();

		ob_start();
		?>
		<div class="vintrica-vignette-form-wrapper" data-storage-key="<?php echo esc_attr( $storage_key ); ?>">
			<?php $this->render_notices( $notices ); ?>

			<?php if ( $order_number ) : ?>
				<div class="vintrica-notice vintrica-notice--<?php echo $this->is_payment_cancelled() ? 'error' : 'success'; ?>" role="status">
					<p>
						<?php
						if ( $this->is_payment_success() ) {
							printf(
								/* translators: %s: order number */
								esc_html__( 'Objednávka %s bola úspešne uhradená. Ďakujeme za vašu platbu.', 'vintrica-vignette-form' ),
								esc_html( $order_number )
							);
						} elseif ( $this->is_payment_cancelled() ) {
							printf(
								/* translators: %s: order number */
								esc_html__( 'Platba objednávky %s bola zrušená. Môžete skúsiť znova.', 'vintrica-vignette-form' ),
								esc_html( $order_number )
							);
						} else {
							printf(
								/* translators: %s: order number */
								esc_html__( 'Objednávka %s bola prijatá. Stripe platba bude dostupná po nakonfigurovaní API kľúčov.', 'vintrica-vignette-form' ),
								esc_html( $order_number )
							);
						}
						?>
					</p>
				</div>
			<?php else : ?>
				<form class="vintrica-vignette-form" method="post" action="<?php echo esc_url( get_permalink() ); ?>" novalidate>
					<?php $this->security->render_nonce_field(); ?>
					<?php $this->security->render_honeypot_field(); ?>

					<input type="hidden" name="<?php echo esc_attr( self::VIGNETTES_FIELD ); ?>" id="vintrica-vignettes-data" value="" />

					<ol class="vintrica-steps" aria-label="<?php echo esc_attr__( 'Kroky objednávky', 'vintrica-vignette-form' ); ?>">
						<li class="vintrica-steps__item is-active is-clickable" data-vintrica-step-indicator="1">
							<button type="button" class="vintrica-steps__trigger" data-vintrica-step-nav="1">
								<span class="vintrica-steps__number">1</span>
								<span class="vintrica-steps__label"><?php echo esc_html__( 'Výber známok', 'vintrica-vignette-form' ); ?></span>
							</button>
						</li>
						<li class="vintrica-steps__item is-disabled" data-vintrica-step-indicator="2">
							<button type="button" class="vintrica-steps__trigger" data-vintrica-step-nav="2" disabled>
								<span class="vintrica-steps__number">2</span>
								<span class="vintrica-steps__label"><?php echo esc_html__( 'Fakturačné údaje', 'vintrica-vignette-form' ); ?></span>
							</button>
						</li>
						<li class="vintrica-steps__item is-disabled" data-vintrica-step-indicator="3">
							<button type="button" class="vintrica-steps__trigger" data-vintrica-step-nav="3" disabled>
								<span class="vintrica-steps__number">3</span>
								<span class="vintrica-steps__label"><?php echo esc_html__( 'Kontrola objednávky', 'vintrica-vignette-form' ); ?></span>
							</button>
						</li>
						<li class="vintrica-steps__item is-disabled" data-vintrica-step-indicator="4" aria-disabled="true">
							<span class="vintrica-steps__trigger vintrica-steps__trigger--static">
								<span class="vintrica-steps__number">4</span>
								<span class="vintrica-steps__label"><?php echo esc_html__( 'Stripe platba', 'vintrica-vignette-form' ); ?></span>
							</span>
						</li>
					</ol>

					<div class="vintrica-step vintrica-step--builder is-active" data-vintrica-step="1">
						<div class="vintrica-builder">
							<section class="vintrica-builder__panel vintrica-builder__panel--form" aria-labelledby="vintrica-builder-title">
								<h2 id="vintrica-builder-title" class="vintrica-builder__title">
									<?php echo esc_html__( 'Pridať známku', 'vintrica-vignette-form' ); ?>
								</h2>

								<div class="vintrica-builder__fields">
									<div class="vintrica-field vintrica-field--has-icon">
										<label for="vintrica-country"><?php echo esc_html__( 'Krajina', 'vintrica-vignette-form' ); ?></label>
										<div class="vintrica-control">
											<span class="vintrica-control__icon" aria-hidden="true"><?php echo $this->render_field_icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<select id="vintrica-country" class="vintrica-choices-select" data-vintrica-field="country">
												<option value=""><?php echo esc_html__( 'Vyberte krajinu', 'vintrica-vignette-form' ); ?></option>
												<?php foreach ( $countries as $value => $label ) : ?>
													<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
									</div>

									<div class="vintrica-field vintrica-field--has-icon">
										<label for="vintrica-vehicle-type"><?php echo esc_html__( 'Typ vozidla', 'vintrica-vignette-form' ); ?></label>
										<div class="vintrica-control">
											<span class="vintrica-control__icon" aria-hidden="true"><?php echo $this->render_field_icon( 'vehicle' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<select id="vintrica-vehicle-type" class="vintrica-choices-select" data-vintrica-field="vehicle_type" disabled>
												<option value=""><?php echo esc_html__( 'Najprv vyberte krajinu', 'vintrica-vignette-form' ); ?></option>
											</select>
										</div>
									</div>

									<div class="vintrica-field vintrica-field--has-icon">
										<label for="vintrica-vignette-validity"><?php echo esc_html__( 'Platnosť známky', 'vintrica-vignette-form' ); ?></label>
										<div class="vintrica-control">
											<span class="vintrica-control__icon" aria-hidden="true"><?php echo $this->render_field_icon( 'validity' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<select id="vintrica-vignette-validity" class="vintrica-choices-select" data-vintrica-field="vignette_validity" disabled>
												<option value=""><?php echo esc_html__( 'Najprv vyberte krajinu', 'vintrica-vignette-form' ); ?></option>
											</select>
										</div>
									</div>

									<div class="vintrica-field vintrica-field--has-icon">
										<label for="vintrica-start-date"><?php echo esc_html__( 'Dátum začiatku platnosti', 'vintrica-vignette-form' ); ?></label>
										<div class="vintrica-control">
											<span class="vintrica-control__icon" aria-hidden="true"><?php echo $this->render_field_icon( 'calendar' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<input type="date" id="vintrica-start-date" data-vintrica-field="start_date" />
										</div>
									</div>

									<div class="vintrica-field vintrica-field--has-icon">
										<label for="vintrica-license-plate"><?php echo esc_html__( 'ŠPZ', 'vintrica-vignette-form' ); ?></label>
										<div class="vintrica-control">
											<span class="vintrica-control__icon" aria-hidden="true"><?php echo $this->render_field_icon( 'plate' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<input type="text" id="vintrica-license-plate" data-vintrica-field="license_plate" maxlength="20" autocomplete="off" placeholder="AA000BB" />
										</div>
										<p class="vintrica-field__hint"><?php echo esc_html__( 'Zadajte ŠPZ bez medzier, napríklad AA000BB.', 'vintrica-vignette-form' ); ?></p>
									</div>

									<div class="vintrica-field vintrica-field--has-icon">
										<label for="vintrica-registration-country"><?php echo esc_html__( 'Krajina registrácie vozidla', 'vintrica-vignette-form' ); ?></label>
										<div class="vintrica-control">
											<span class="vintrica-control__icon" aria-hidden="true"><?php echo $this->render_field_icon( 'registration' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
											<select id="vintrica-registration-country" class="vintrica-choices-select" data-vintrica-field="registration_country">
												<option value=""><?php echo esc_html__( 'Vyberte krajinu registrácie vozidla', 'vintrica-vignette-form' ); ?></option>
												<?php foreach ( $countries as $value => $label ) : ?>
													<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
												<?php endforeach; ?>
											</select>
										</div>
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
									<div class="vintrica-summary-totals__row vintrica-summary-totals__row--total">
										<dt><?php echo esc_html__( 'Celková suma', 'vintrica-vignette-form' ); ?></dt>
										<dd class="vintrica-total-amount"><?php echo esc_html( $this->format_price( 0 ) ); ?></dd>
									</div>
								</dl>
							</section>
						</div>

						<div class="vintrica-field vintrica-field--submit">
							<button type="button" class="vintrica-submit vintrica-continue-billing" disabled>
								<?php echo esc_html__( 'Pokračovať k fakturačným údajom', 'vintrica-vignette-form' ); ?>
							</button>
						</div>
					</div>

					<div class="vintrica-step vintrica-step--billing" data-vintrica-step="2" hidden>
						<section class="vintrica-builder__panel" aria-labelledby="vintrica-billing-title">
							<h2 id="vintrica-billing-title" class="vintrica-builder__title">
								<?php echo esc_html__( 'Fakturačné údaje', 'vintrica-vignette-form' ); ?>
							</h2>

							<div class="vintrica-billing-fields">
								<div class="vintrica-field">
									<label for="vintrica-billing-first-name"><?php echo esc_html__( 'Meno', 'vintrica-vignette-form' ); ?> <span class="vintrica-required">*</span></label>
									<input type="text" id="vintrica-billing-first-name" name="vintrica_billing_first_name" autocomplete="given-name" required />
								</div>

								<div class="vintrica-field">
									<label for="vintrica-billing-last-name"><?php echo esc_html__( 'Priezvisko', 'vintrica-vignette-form' ); ?> <span class="vintrica-required">*</span></label>
									<input type="text" id="vintrica-billing-last-name" name="vintrica_billing_last_name" autocomplete="family-name" required />
								</div>

								<div class="vintrica-field">
									<label for="vintrica-billing-email"><?php echo esc_html__( 'E-mail', 'vintrica-vignette-form' ); ?> <span class="vintrica-required">*</span></label>
									<input type="email" id="vintrica-billing-email" name="vintrica_billing_email" autocomplete="email" required />
								</div>

								<div class="vintrica-field">
									<label for="vintrica-billing-phone"><?php echo esc_html__( 'Telefón', 'vintrica-vignette-form' ); ?> <span class="vintrica-required">*</span></label>
									<input type="tel" id="vintrica-billing-phone" name="vintrica_billing_phone" autocomplete="tel" required />
								</div>

								<div class="vintrica-field">
									<label for="vintrica-billing-company"><?php echo esc_html__( 'Firma', 'vintrica-vignette-form' ); ?></label>
									<input type="text" id="vintrica-billing-company" name="vintrica_billing_company" autocomplete="organization" />
								</div>

								<div class="vintrica-field">
									<label for="vintrica-billing-ico"><?php echo esc_html__( 'IČO', 'vintrica-vignette-form' ); ?></label>
									<input type="text" id="vintrica-billing-ico" name="vintrica_billing_ico" />
								</div>

								<div class="vintrica-field">
									<label for="vintrica-billing-dic"><?php echo esc_html__( 'DIČ', 'vintrica-vignette-form' ); ?></label>
									<input type="text" id="vintrica-billing-dic" name="vintrica_billing_dic" />
								</div>

								<div class="vintrica-field">
									<label for="vintrica-billing-ic-dph"><?php echo esc_html__( 'IČ DPH', 'vintrica-vignette-form' ); ?></label>
									<input type="text" id="vintrica-billing-ic-dph" name="vintrica_billing_ic_dph" />
								</div>

								<div class="vintrica-field">
									<label for="vintrica-billing-street"><?php echo esc_html__( 'Ulica', 'vintrica-vignette-form' ); ?> <span class="vintrica-required">*</span></label>
									<input type="text" id="vintrica-billing-street" name="vintrica_billing_street" autocomplete="street-address" required />
								</div>

								<div class="vintrica-field">
									<label for="vintrica-billing-city"><?php echo esc_html__( 'Mesto', 'vintrica-vignette-form' ); ?> <span class="vintrica-required">*</span></label>
									<input type="text" id="vintrica-billing-city" name="vintrica_billing_city" autocomplete="address-level2" required />
								</div>

								<div class="vintrica-field">
									<label for="vintrica-billing-zip"><?php echo esc_html__( 'PSČ', 'vintrica-vignette-form' ); ?> <span class="vintrica-required">*</span></label>
									<input type="text" id="vintrica-billing-zip" name="vintrica_billing_zip" autocomplete="postal-code" required />
								</div>

								<div class="vintrica-field vintrica-field--has-icon">
									<label for="vintrica-billing-country"><?php echo esc_html__( 'Krajina', 'vintrica-vignette-form' ); ?> <span class="vintrica-required">*</span></label>
									<div class="vintrica-control">
										<span class="vintrica-control__icon" aria-hidden="true"><?php echo $this->render_field_icon( 'globe' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span>
										<select id="vintrica-billing-country" class="vintrica-choices-select" name="vintrica_billing_country" autocomplete="country" required>
											<option value=""><?php echo esc_html__( 'Vyberte krajinu', 'vintrica-vignette-form' ); ?></option>
											<?php foreach ( $countries as $value => $label ) : ?>
												<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
											<?php endforeach; ?>
										</select>
									</div>
								</div>
							</div>

							<fieldset class="vintrica-consents">
								<legend><?php echo esc_html__( 'Súhlasy', 'vintrica-vignette-form' ); ?></legend>

								<label class="vintrica-checkbox">
									<input type="checkbox" name="vintrica_consent_terms" value="1" required />
									<span><?php echo esc_html__( 'Súhlasím s obchodnými podmienkami', 'vintrica-vignette-form' ); ?></span>
								</label>

								<label class="vintrica-checkbox">
									<input type="checkbox" name="vintrica_consent_privacy" value="1" required />
									<span><?php echo esc_html__( 'Súhlasím so spracovaním osobných údajov', 'vintrica-vignette-form' ); ?></span>
								</label>

								<label class="vintrica-checkbox">
									<input type="checkbox" name="vintrica_consent_service_start" value="1" required />
									<span><?php echo esc_html__( 'Súhlasím so začatím poskytovania služby pred uplynutím lehoty na odstúpenie od zmluvy', 'vintrica-vignette-form' ); ?></span>
								</label>
							</fieldset>

							<div class="vintrica-billing-error vintrica-form-error" role="alert" hidden></div>
						</section>

						<div class="vintrica-billing-actions">
							<button type="button" class="vintrica-button vintrica-button--secondary vintrica-back-builder">
								<?php echo esc_html__( 'Späť na známky', 'vintrica-vignette-form' ); ?>
							</button>
							<button type="button" class="vintrica-submit vintrica-continue-review">
								<?php echo esc_html__( 'Pokračovať na kontrolu', 'vintrica-vignette-form' ); ?>
							</button>
						</div>
					</div>

					<div class="vintrica-step vintrica-step--review" data-vintrica-step="3" hidden>
						<section class="vintrica-builder__panel" aria-labelledby="vintrica-review-title">
							<h2 id="vintrica-review-title" class="vintrica-builder__title">
								<?php echo esc_html__( 'Kontrola objednávky', 'vintrica-vignette-form' ); ?>
							</h2>

							<div class="vintrica-review-vignettes-section">
								<h3 class="vintrica-review__subtitle"><?php echo esc_html__( 'Vybrané známky', 'vintrica-vignette-form' ); ?></h3>
								<div class="vintrica-review-vignettes" aria-live="polite"></div>
							</div>

							<h3 class="vintrica-review__subtitle"><?php echo esc_html__( 'Súhrn objednávky', 'vintrica-vignette-form' ); ?></h3>
							<dl class="vintrica-summary-totals vintrica-review-totals">
								<div class="vintrica-summary-totals__row">
									<dt><?php echo esc_html__( 'Medzisúčet', 'vintrica-vignette-form' ); ?></dt>
									<dd class="vintrica-review-subtotal">0</dd>
								</div>
								<div class="vintrica-summary-totals__row vintrica-summary-totals__row--total">
									<dt><?php echo esc_html__( 'Celková suma', 'vintrica-vignette-form' ); ?></dt>
									<dd class="vintrica-review-total">0</dd>
								</div>
							</dl>

							<h3 class="vintrica-review__subtitle"><?php echo esc_html__( 'Fakturačné údaje', 'vintrica-vignette-form' ); ?></h3>
							<div class="vintrica-review-billing"></div>

							<div class="vintrica-review-error vintrica-form-error" role="alert" hidden></div>
						</section>

						<div class="vintrica-review-actions">
							<button type="button" class="vintrica-button vintrica-button--secondary vintrica-back-billing">
								<?php echo esc_html__( 'Späť na fakturačné údaje', 'vintrica-vignette-form' ); ?>
							</button>
							<button type="button" class="vintrica-button vintrica-button--secondary vintrica-edit-vignettes">
								<?php echo esc_html__( 'Upraviť známky', 'vintrica-vignette-form' ); ?>
							</button>
							<button type="button" id="vintrica-pay-submit" class="vintrica-submit vintrica-pay-submit">
								<?php echo esc_html__( 'Zaplatiť', 'vintrica-vignette-form' ); ?>
							</button>
						</div>
					</div>
				</form>
			<?php endif; ?>
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
