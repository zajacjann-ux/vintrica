<?php
/**
 * Admin catalog management UI.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Admin_Catalog
 */
class Vintrica_Admin_Catalog {

	/**
	 * Submenu slug.
	 */
	const MENU_SLUG = 'vintrica-form-catalog';

	/**
	 * Nonce action.
	 */
	const NONCE_ACTION = 'vintrica_admin_catalog';

	/**
	 * Catalog handler.
	 *
	 * @var Vintrica_Catalog
	 */
	private $catalog;

	/**
	 * Constructor.
	 *
	 * @param Vintrica_Catalog $catalog Catalog handler.
	 */
	public function __construct( Vintrica_Catalog $catalog ) {
		$this->catalog = $catalog;

		add_action( 'admin_init', array( $this, 'handle_actions' ) );
	}

	/**
	 * Handle catalog admin POST actions.
	 *
	 * @return void
	 */
	public function handle_actions() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( empty( $_POST['vintrica_catalog_action'] ) ) {
			return;
		}

		if ( empty( $_POST['vintrica_catalog_nonce'] ) ) {
			return;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['vintrica_catalog_nonce'] ) );

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) ) {
			add_settings_error( 'vintrica_catalog', 'vintrica_catalog_nonce', __( 'Overenie bezpečnosti zlyhalo.', 'vintrica-vignette-form' ), 'error' );
			return;
		}

		$action = sanitize_key( wp_unslash( $_POST['vintrica_catalog_action'] ) );
		$result = null;

		switch ( $action ) {
			case 'save_country':
				$result = $this->catalog->save_country( wp_unslash( $_POST ) );
				break;
			case 'delete_country':
				$result = $this->catalog->delete_country( isset( $_POST['country_id'] ) ? absint( $_POST['country_id'] ) : 0 );
				break;
			case 'save_vignette':
				$result = $this->catalog->save_vignette( wp_unslash( $_POST ) );
				break;
			case 'delete_vignette':
				$result = $this->catalog->delete_vignette( isset( $_POST['vignette_id'] ) ? absint( $_POST['vignette_id'] ) : 0 );
				break;
		}

		if ( is_wp_error( $result ) ) {
			add_settings_error( 'vintrica_catalog', 'vintrica_catalog_error', $result->get_error_message(), 'error' );
			return;
		}

		add_settings_error( 'vintrica_catalog', 'vintrica_catalog_saved', __( 'Cenník bol úspešne uložený.', 'vintrica-vignette-form' ), 'updated' );

		wp_safe_redirect( admin_url( 'admin.php?page=' . self::MENU_SLUG ) );
		exit;
	}

	/**
	 * Render catalog admin page.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Nemáte oprávnenie na správu cenníka.', 'vintrica-vignette-form' ) );
		}

		$countries     = $this->catalog->get_countries();
		$vehicle_types = $this->catalog->get_vehicle_types();
		$edit_country  = isset( $_GET['edit_country'] ) ? $this->catalog->get_country( absint( $_GET['edit_country'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$edit_vignette = isset( $_GET['edit_vignette'] ) ? $this->catalog->get_vignette( absint( $_GET['edit_vignette'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		settings_errors( 'vintrica_catalog' );
		?>
		<div class="wrap vintrica-catalog-admin">
			<h1><?php echo esc_html__( 'Cenník známok', 'vintrica-vignette-form' ); ?></h1>
			<p><?php echo esc_html__( 'Spravujte krajiny, typy vozidiel a ceny diaľničných známok zobrazené vo formulári.', 'vintrica-vignette-form' ); ?></p>

			<div class="vintrica-catalog-admin__grid">
				<section class="vintrica-catalog-panel">
					<h2><?php echo esc_html( $edit_country ? __( 'Upraviť krajinu', 'vintrica-vignette-form' ) : __( 'Pridať krajinu', 'vintrica-vignette-form' ) ); ?></h2>
					<form method="post" action="">
						<?php wp_nonce_field( self::NONCE_ACTION, 'vintrica_catalog_nonce' ); ?>
						<input type="hidden" name="vintrica_catalog_action" value="save_country" />
						<?php if ( $edit_country ) : ?>
							<input type="hidden" name="id" value="<?php echo esc_attr( (string) $edit_country->id ); ?>" />
						<?php endif; ?>

						<table class="form-table vintrica-admin-table" role="presentation">
							<tr>
								<th scope="row"><label for="vintrica-country-name"><?php echo esc_html__( 'Názov krajiny', 'vintrica-vignette-form' ); ?></label></th>
								<td><input name="name" id="vintrica-country-name" type="text" class="regular-text" value="<?php echo esc_attr( $edit_country ? $edit_country->name : '' ); ?>" required /></td>
							</tr>
							<tr>
								<th scope="row"><label for="vintrica-country-code"><?php echo esc_html__( 'Kód krajiny', 'vintrica-vignette-form' ); ?></label></th>
								<td><input name="code" id="vintrica-country-code" type="text" class="regular-text" value="<?php echo esc_attr( $edit_country ? strtoupper( $edit_country->code ) : '' ); ?>" maxlength="10" required /></td>
							</tr>
							<tr>
								<th scope="row"><label for="vintrica-country-sort"><?php echo esc_html__( 'Poradie', 'vintrica-vignette-form' ); ?></label></th>
								<td><input name="sort_order" id="vintrica-country-sort" type="number" class="small-text" value="<?php echo esc_attr( $edit_country ? (string) $edit_country->sort_order : '0' ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Aktívna', 'vintrica-vignette-form' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="active" value="1" <?php checked( ! $edit_country || (int) $edit_country->active ); ?> />
										<?php echo esc_html__( 'Zobraziť vo formulári', 'vintrica-vignette-form' ); ?>
									</label>
								</td>
							</tr>
						</table>

						<?php submit_button( $edit_country ? __( 'Uložiť krajinu', 'vintrica-vignette-form' ) : __( 'Pridať krajinu', 'vintrica-vignette-form' ) ); ?>
						<?php if ( $edit_country ) : ?>
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>"><?php echo esc_html__( 'Zrušiť úpravu', 'vintrica-vignette-form' ); ?></a>
						<?php endif; ?>
					</form>
				</section>

				<section class="vintrica-catalog-panel">
					<h2><?php echo esc_html( $edit_vignette ? __( 'Upraviť známku', 'vintrica-vignette-form' ) : __( 'Pridať typ známky', 'vintrica-vignette-form' ) ); ?></h2>
					<form method="post" action="">
						<?php wp_nonce_field( self::NONCE_ACTION, 'vintrica_catalog_nonce' ); ?>
						<input type="hidden" name="vintrica_catalog_action" value="save_vignette" />
						<?php if ( $edit_vignette ) : ?>
							<input type="hidden" name="id" value="<?php echo esc_attr( (string) $edit_vignette->id ); ?>" />
						<?php endif; ?>

						<table class="form-table vintrica-admin-table" role="presentation">
							<tr>
								<th scope="row"><label for="vintrica-vignette-country"><?php echo esc_html__( 'Krajina', 'vintrica-vignette-form' ); ?></label></th>
								<td>
									<select name="country_id" id="vintrica-vignette-country" required>
										<option value=""><?php echo esc_html__( 'Vyberte krajinu', 'vintrica-vignette-form' ); ?></option>
										<?php foreach ( $countries as $country ) : ?>
											<option value="<?php echo esc_attr( (string) $country->id ); ?>" <?php selected( $edit_vignette ? (int) $edit_vignette->country_id : 0, (int) $country->id ); ?>>
												<?php echo esc_html( $country->name ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="vintrica-vignette-vehicle"><?php echo esc_html__( 'Typ vozidla', 'vintrica-vignette-form' ); ?></label></th>
								<td>
									<select name="vehicle_type" id="vintrica-vignette-vehicle" required>
										<?php foreach ( $vehicle_types as $code => $label ) : ?>
											<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $edit_vignette ? $edit_vignette->vehicle_type : '', $code ); ?>>
												<?php echo esc_html( $label ); ?>
											</option>
										<?php endforeach; ?>
									</select>
								</td>
							</tr>
							<tr>
								<th scope="row"><label for="vintrica-vignette-code"><?php echo esc_html__( 'Kód typu známky', 'vintrica-vignette-form' ); ?></label></th>
								<td><input name="vignette_code" id="vintrica-vignette-code" type="text" class="regular-text" value="<?php echo esc_attr( $edit_vignette ? $edit_vignette->vignette_code : '' ); ?>" required /></td>
							</tr>
							<tr>
								<th scope="row"><label for="vintrica-vignette-name"><?php echo esc_html__( 'Názov známky', 'vintrica-vignette-form' ); ?></label></th>
								<td><input name="name" id="vintrica-vignette-name" type="text" class="regular-text" value="<?php echo esc_attr( $edit_vignette ? $edit_vignette->name : '' ); ?>" required /></td>
							</tr>
							<tr>
								<th scope="row"><label for="vintrica-vignette-validity"><?php echo esc_html__( 'Popis platnosti', 'vintrica-vignette-form' ); ?></label></th>
								<td><input name="validity_label" id="vintrica-vignette-validity" type="text" class="regular-text" value="<?php echo esc_attr( $edit_vignette ? $edit_vignette->validity_label : '' ); ?>" required /></td>
							</tr>
							<tr>
								<th scope="row"><label for="vintrica-vignette-price"><?php echo esc_html__( 'Cena (EUR)', 'vintrica-vignette-form' ); ?></label></th>
								<td><input name="price" id="vintrica-vignette-price" type="text" inputmode="decimal" class="regular-text" value="<?php echo esc_attr( $edit_vignette ? number_format( (float) $edit_vignette->price, 2, '.', '' ) : '' ); ?>" required /></td>
							</tr>
							<tr>
								<th scope="row"><label for="vintrica-vignette-sort"><?php echo esc_html__( 'Poradie', 'vintrica-vignette-form' ); ?></label></th>
								<td><input name="sort_order" id="vintrica-vignette-sort" type="number" class="small-text" value="<?php echo esc_attr( $edit_vignette ? (string) $edit_vignette->sort_order : '0' ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><?php echo esc_html__( 'Aktívna', 'vintrica-vignette-form' ); ?></th>
								<td>
									<label>
										<input type="checkbox" name="active" value="1" <?php checked( ! $edit_vignette || (int) $edit_vignette->active ); ?> />
										<?php echo esc_html__( 'Zobraziť vo formulári', 'vintrica-vignette-form' ); ?>
									</label>
								</td>
							</tr>
						</table>

						<?php submit_button( $edit_vignette ? __( 'Uložiť známku', 'vintrica-vignette-form' ) : __( 'Pridať známku', 'vintrica-vignette-form' ) ); ?>
						<?php if ( $edit_vignette ) : ?>
							<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG ) ); ?>"><?php echo esc_html__( 'Zrušiť úpravu', 'vintrica-vignette-form' ); ?></a>
						<?php endif; ?>
					</form>
				</section>
			</div>

			<section class="vintrica-catalog-panel vintrica-catalog-panel--wide">
				<h2><?php echo esc_html__( 'Prehľad cenníka', 'vintrica-vignette-form' ); ?></h2>

				<?php if ( empty( $countries ) ) : ?>
					<p><?php echo esc_html__( 'Zatiaľ nie sú pridané žiadne krajiny.', 'vintrica-vignette-form' ); ?></p>
				<?php else : ?>
					<?php foreach ( $countries as $country ) : ?>
						<?php $vignettes = $this->catalog->get_vignettes( (int) $country->id ); ?>
						<div class="vintrica-catalog-country">
							<div class="vintrica-catalog-country__header">
								<h3>
									<?php echo esc_html( $country->name ); ?>
									<span class="description">(<?php echo esc_html( strtoupper( $country->code ) ); ?>)</span>
									<?php if ( ! (int) $country->active ) : ?>
										<span class="vintrica-status-badge vintrica-status-badge--cancelled"><?php echo esc_html__( 'Neaktívna', 'vintrica-vignette-form' ); ?></span>
									<?php endif; ?>
								</h3>
								<div class="vintrica-catalog-country__actions">
									<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&edit_country=' . (int) $country->id ) ); ?>">
										<?php echo esc_html__( 'Upraviť', 'vintrica-vignette-form' ); ?>
									</a>
									<form method="post" action="" class="vintrica-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Naozaj chcete odstrániť krajinu a všetky jej známky?', 'vintrica-vignette-form' ) ); ?>');">
										<?php wp_nonce_field( self::NONCE_ACTION, 'vintrica_catalog_nonce' ); ?>
										<input type="hidden" name="vintrica_catalog_action" value="delete_country" />
										<input type="hidden" name="country_id" value="<?php echo esc_attr( (string) $country->id ); ?>" />
										<button type="submit" class="button button-small button-link-delete"><?php echo esc_html__( 'Odstrániť', 'vintrica-vignette-form' ); ?></button>
									</form>
								</div>
							</div>

							<?php if ( empty( $vignettes ) ) : ?>
								<p class="description"><?php echo esc_html__( 'Pre túto krajinu zatiaľ nie sú definované známky.', 'vintrica-vignette-form' ); ?></p>
							<?php else : ?>
								<table class="widefat striped">
									<thead>
										<tr>
											<th><?php echo esc_html__( 'Typ vozidla', 'vintrica-vignette-form' ); ?></th>
											<th><?php echo esc_html__( 'Názov známky', 'vintrica-vignette-form' ); ?></th>
											<th><?php echo esc_html__( 'Platnosť', 'vintrica-vignette-form' ); ?></th>
											<th><?php echo esc_html__( 'Kód', 'vintrica-vignette-form' ); ?></th>
											<th><?php echo esc_html__( 'Cena', 'vintrica-vignette-form' ); ?></th>
											<th><?php echo esc_html__( 'Stav', 'vintrica-vignette-form' ); ?></th>
											<th><?php echo esc_html__( 'Akcie', 'vintrica-vignette-form' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php foreach ( $vignettes as $vignette ) : ?>
											<tr>
												<td><?php echo esc_html( $vehicle_types[ $vignette->vehicle_type ] ?? $vignette->vehicle_type ); ?></td>
												<td><?php echo esc_html( $vignette->name ); ?></td>
												<td><?php echo esc_html( $vignette->validity_label ); ?></td>
												<td><code><?php echo esc_html( $vignette->vignette_code ); ?></code></td>
												<td><?php echo esc_html( number_format_i18n( (float) $vignette->price, 2 ) ); ?> EUR</td>
												<td>
													<?php if ( (int) $vignette->active ) : ?>
														<span class="vintrica-status-badge vintrica-status-badge--paid"><?php echo esc_html__( 'Aktívna', 'vintrica-vignette-form' ); ?></span>
													<?php else : ?>
														<span class="vintrica-status-badge vintrica-status-badge--cancelled"><?php echo esc_html__( 'Neaktívna', 'vintrica-vignette-form' ); ?></span>
													<?php endif; ?>
												</td>
												<td>
													<a class="button button-small" href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::MENU_SLUG . '&edit_vignette=' . (int) $vignette->id ) ); ?>">
														<?php echo esc_html__( 'Upraviť', 'vintrica-vignette-form' ); ?>
													</a>
													<form method="post" action="" class="vintrica-inline-form" onsubmit="return confirm('<?php echo esc_js( __( 'Naozaj chcete odstrániť túto známku?', 'vintrica-vignette-form' ) ); ?>');">
														<?php wp_nonce_field( self::NONCE_ACTION, 'vintrica_catalog_nonce' ); ?>
														<input type="hidden" name="vintrica_catalog_action" value="delete_vignette" />
														<input type="hidden" name="vignette_id" value="<?php echo esc_attr( (string) $vignette->id ); ?>" />
														<button type="submit" class="button button-small button-link-delete"><?php echo esc_html__( 'Odstrániť', 'vintrica-vignette-form' ); ?></button>
													</form>
												</td>
											</tr>
										<?php endforeach; ?>
									</tbody>
								</table>
							<?php endif; ?>
						</div>
					<?php endforeach; ?>
				<?php endif; ?>
			</section>
		</div>
		<?php
	}
}
