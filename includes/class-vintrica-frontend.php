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
	 * Security handler instance.
	 *
	 * @var Vintrica_Security
	 */
	private $security;

	/**
	 * Constructor.
	 *
	 * @param Vintrica_Security $security Security handler.
	 */
	public function __construct( Vintrica_Security $security ) {
		$this->security = $security;

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
	 * Enqueue frontend assets.
	 *
	 * @return void
	 */
	private function enqueue_assets() {
		wp_enqueue_style( 'vintrica-frontend' );
		wp_enqueue_script( 'vintrica-frontend' );
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

		if ( ! $this->security->verify_form_request() ) {
			$this->set_notice( 'error', __( 'Security verification failed. Please try again.', 'vintrica-vignette-form' ) );
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Verified above.
		$form_data = $this->security->sanitize_form_data( $_POST );

		if ( empty( $form_data ) ) {
			$this->set_notice( 'error', __( 'No valid form data was submitted.', 'vintrica-vignette-form' ) );
			return;
		}

		/**
		 * Fires after the vignette form is validated and sanitized.
		 *
		 * WooCommerce integration will hook into this action in a future release.
		 *
		 * @param array $form_data Sanitized form data.
		 */
		do_action( 'vintrica_vignette_form_submitted', $form_data );

		$this->set_notice( 'success', __( 'Your vignette request has been received.', 'vintrica-vignette-form' ) );
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
	 * Render the vignette order form.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render_form( $atts = array() ) {
		$this->enqueue_assets();

		$field_options = $this->get_field_options();
		$notices       = $this->get_notices();

		ob_start();
		?>
		<div class="vintrica-vignette-form-wrapper">
			<?php $this->render_notices( $notices ); ?>

			<form class="vintrica-vignette-form" method="post" action="<?php echo esc_url( get_permalink() ); ?>">
				<?php $this->security->render_nonce_field(); ?>

				<div class="vintrica-field">
					<label for="vintrica-country"><?php echo esc_html__( 'Country', 'vintrica-vignette-form' ); ?></label>
					<select id="vintrica-country" name="country" required>
						<option value=""><?php echo esc_html__( 'Select country', 'vintrica-vignette-form' ); ?></option>
						<?php foreach ( $field_options['countries'] as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="vintrica-field">
					<label for="vintrica-vehicle-type"><?php echo esc_html__( 'Vehicle type', 'vintrica-vignette-form' ); ?></label>
					<select id="vintrica-vehicle-type" name="vehicle_type" required>
						<option value=""><?php echo esc_html__( 'Select vehicle type', 'vintrica-vignette-form' ); ?></option>
						<?php foreach ( $field_options['vehicle_types'] as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="vintrica-field">
					<label for="vintrica-vignette-validity"><?php echo esc_html__( 'Vignette validity', 'vintrica-vignette-form' ); ?></label>
					<select id="vintrica-vignette-validity" name="vignette_validity" required>
						<option value=""><?php echo esc_html__( 'Select validity', 'vintrica-vignette-form' ); ?></option>
						<?php foreach ( $field_options['vignette_validities'] as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="vintrica-field">
					<label for="vintrica-start-date"><?php echo esc_html__( 'Start date', 'vintrica-vignette-form' ); ?></label>
					<input type="date" id="vintrica-start-date" name="start_date" required />
				</div>

				<div class="vintrica-field">
					<label for="vintrica-license-plate"><?php echo esc_html__( 'License plate', 'vintrica-vignette-form' ); ?></label>
					<input type="text" id="vintrica-license-plate" name="license_plate" maxlength="20" required />
				</div>

				<div class="vintrica-field">
					<label for="vintrica-registration-country"><?php echo esc_html__( 'Registration country', 'vintrica-vignette-form' ); ?></label>
					<select id="vintrica-registration-country" name="registration_country" required>
						<option value=""><?php echo esc_html__( 'Select registration country', 'vintrica-vignette-form' ); ?></option>
						<?php foreach ( $field_options['countries'] as $value => $label ) : ?>
							<option value="<?php echo esc_attr( $value ); ?>"><?php echo esc_html( $label ); ?></option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="vintrica-field vintrica-field--submit">
					<button type="submit" name="vintrica_vignette_submit" value="1" class="vintrica-submit">
						<?php echo esc_html__( 'Submit', 'vintrica-vignette-form' ); ?>
					</button>
				</div>
			</form>
		</div>
		<?php
		return ob_get_clean();
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

	/**
	 * Get selectable field options.
	 *
	 * Options will be configurable via admin settings in a future release.
	 *
	 * @return array<string, array<string, string>>
	 */
	private function get_field_options() {
		/**
		 * Filter vignette form field options.
		 *
		 * @param array<string, array<string, string>> $options Field option groups.
		 */
		return apply_filters(
			'vintrica_vignette_form_field_options',
			array(
				'countries'           => array(
					'at' => __( 'Austria', 'vintrica-vignette-form' ),
					'ch' => __( 'Switzerland', 'vintrica-vignette-form' ),
					'de' => __( 'Germany', 'vintrica-vignette-form' ),
					'cz' => __( 'Czech Republic', 'vintrica-vignette-form' ),
					'sk' => __( 'Slovakia', 'vintrica-vignette-form' ),
					'hu' => __( 'Hungary', 'vintrica-vignette-form' ),
					'si' => __( 'Slovenia', 'vintrica-vignette-form' ),
				),
				'vehicle_types'       => array(
					'car'       => __( 'Car', 'vintrica-vignette-form' ),
					'motorcycle'=> __( 'Motorcycle', 'vintrica-vignette-form' ),
					'van'       => __( 'Van', 'vintrica-vignette-form' ),
					'trailer'   => __( 'Trailer', 'vintrica-vignette-form' ),
				),
				'vignette_validities' => array(
					'10d'  => __( '10 days', 'vintrica-vignette-form' ),
					'2m'   => __( '2 months', 'vintrica-vignette-form' ),
					'1y'   => __( '1 year', 'vintrica-vignette-form' ),
				),
			)
		);
	}
}
