<?php
/**
 * Editable vignette catalog stored in custom database tables.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Catalog
 */
class Vintrica_Catalog {

	/**
	 * Database schema version.
	 */
	const DB_VERSION = '1.0.0';

	/**
	 * Option key for stored DB version.
	 */
	const DB_VERSION_OPTION = 'vintrica_catalog_db_version';

	/**
	 * Get countries table name.
	 *
	 * @return string
	 */
	public function get_countries_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'vintrica_catalog_countries';
	}

	/**
	 * Get vignettes table name.
	 *
	 * @return string
	 */
	public function get_vignettes_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'vintrica_catalog_vignettes';
	}

	/**
	 * Create or upgrade catalog tables.
	 *
	 * @return void
	 */
	public function create_tables() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate   = $wpdb->get_charset_collate();
		$countries_table   = $this->get_countries_table_name();
		$vignettes_table   = $this->get_vignettes_table_name();

		$countries_sql = "CREATE TABLE {$countries_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			code varchar(10) NOT NULL,
			name varchar(255) NOT NULL,
			active tinyint(1) NOT NULL DEFAULT 1,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code)
		) {$charset_collate};";

		$vignettes_sql = "CREATE TABLE {$vignettes_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			country_id bigint(20) unsigned NOT NULL,
			vehicle_type varchar(50) NOT NULL,
			vignette_code varchar(50) NOT NULL,
			name varchar(255) NOT NULL,
			validity_label varchar(255) NOT NULL,
			price decimal(10,2) NOT NULL DEFAULT 0.00,
			active tinyint(1) NOT NULL DEFAULT 1,
			sort_order int(11) NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY country_vehicle_code (country_id, vehicle_type, vignette_code),
			KEY country_id (country_id)
		) {$charset_collate};";

		dbDelta( $countries_sql );
		dbDelta( $vignettes_sql );

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Seed default catalog when empty.
	 *
	 * @return void
	 */
	public function maybe_seed_defaults() {
		global $wpdb;

		$countries_table = $this->get_countries_table_name();
		$count           = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$countries_table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		if ( $count > 0 ) {
			return;
		}

		foreach ( $this->get_default_seed_data() as $country_data ) {
			$country_id = $this->insert_country(
				array(
					'code'       => $country_data['code'],
					'name'       => $country_data['name'],
					'active'     => 1,
					'sort_order' => $country_data['sort_order'],
				)
			);

			if ( ! $country_id ) {
				continue;
			}

			foreach ( $country_data['vignettes'] as $index => $vignette ) {
				$this->insert_vignette(
					array(
						'country_id'     => $country_id,
						'vehicle_type'   => $vignette['vehicle_type'],
						'vignette_code'  => $vignette['vignette_code'],
						'name'           => $vignette['name'],
						'validity_label' => $vignette['validity_label'],
						'price'          => $vignette['price'],
						'active'         => 1,
						'sort_order'     => $index,
					)
				);
			}
		}
	}

	/**
	 * Default seed countries and vignettes.
	 *
	 * @return array<int, array<string, mixed>>
	 */
	private function get_default_seed_data() {
		return array(
			array(
				'code'       => 'at',
				'name'       => 'Rakúsko',
				'sort_order' => 10,
				'vignettes'  => array(
					array( 'vehicle_type' => 'car', 'vignette_code' => '10d', 'name' => '10 dní', 'validity_label' => '10 dní', 'price' => 9.90 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '2m', 'name' => '2 mesiace', 'validity_label' => '2 mesiace', 'price' => 28.90 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '1y', 'name' => '1 rok', 'validity_label' => '1 rok', 'price' => 96.40 ),
				),
			),
			array(
				'code'       => 'ch',
				'name'       => 'Švajčiarsko',
				'sort_order' => 20,
				'vignettes'  => array(
					array( 'vehicle_type' => 'car', 'vignette_code' => '1d', 'name' => '1 deň', 'validity_label' => '1 deň', 'price' => 8.30 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '7d', 'name' => '7 dní', 'validity_label' => '7 dní', 'price' => 32.00 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '1y', 'name' => '1 rok', 'validity_label' => '1 rok', 'price' => 40.00 ),
				),
			),
			array(
				'code'       => 'cz',
				'name'       => 'Česko',
				'sort_order' => 30,
				'vignettes'  => array(
					array( 'vehicle_type' => 'car', 'vignette_code' => '10d', 'name' => '10 dní', 'validity_label' => '10 dní', 'price' => 12.00 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '1m', 'name' => '1 mesiac', 'validity_label' => '1 mesiac', 'price' => 16.00 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '1y', 'name' => '1 rok', 'validity_label' => '1 rok', 'price' => 150.00 ),
				),
			),
			array(
				'code'       => 'hu',
				'name'       => 'Maďarsko',
				'sort_order' => 40,
				'vignettes'  => array(
					array( 'vehicle_type' => 'car', 'vignette_code' => '10d', 'name' => '10 dní', 'validity_label' => '10 dní', 'price' => 15.00 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '1m', 'name' => '1 mesiac', 'validity_label' => '1 mesiac', 'price' => 22.00 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '1y', 'name' => '1 rok', 'validity_label' => '1 rok', 'price' => 155.00 ),
				),
			),
			array(
				'code'       => 'si',
				'name'       => 'Slovinsko',
				'sort_order' => 50,
				'vignettes'  => array(
					array( 'vehicle_type' => 'car', 'vignette_code' => '7d', 'name' => '7 dní', 'validity_label' => '7 dní', 'price' => 16.00 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '1m', 'name' => '1 mesiac', 'validity_label' => '1 mesiac', 'price' => 32.00 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '1y', 'name' => '1 rok', 'validity_label' => '1 rok', 'price' => 110.00 ),
				),
			),
			array(
				'code'       => 'sk',
				'name'       => 'Slovensko',
				'sort_order' => 60,
				'vignettes'  => array(
					array( 'vehicle_type' => 'car', 'vignette_code' => '1d', 'name' => '1 dňová', 'validity_label' => '1 dňová', 'price' => 8.90 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => 'weekend', 'name' => 'Víkendová', 'validity_label' => 'Víkendová', 'price' => 10.80 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '7d', 'name' => '7 dňová', 'validity_label' => '7 dňová', 'price' => 10.80 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '1m', 'name' => 'Mesačná', 'validity_label' => 'Mesačná', 'price' => 17.00 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '1y', 'name' => 'Ročná', 'validity_label' => 'Ročná', 'price' => 60.00 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '30d', 'name' => '30 dní', 'validity_label' => '30 dní', 'price' => 17.00 ),
					array( 'vehicle_type' => 'car', 'vignette_code' => '60d', 'name' => '60 dní', 'validity_label' => '60 dní', 'price' => 28.00 ),
				),
			),
		);
	}

	/**
	 * Get fixed vehicle type labels.
	 *
	 * @return array<string, string>
	 */
	public function get_vehicle_types() {
		return array(
			'car'        => __( 'Osobné auto', 'vintrica-vignette-form' ),
			'truck'      => __( 'Nákladné auto', 'vintrica-vignette-form' ),
			'motorcycle' => __( 'Motocykel', 'vintrica-vignette-form' ),
			'trailer'    => __( 'Príves', 'vintrica-vignette-form' ),
			'van'        => __( 'Dodávka', 'vintrica-vignette-form' ),
		);
	}

	/**
	 * Get vehicle type label.
	 *
	 * @param string $code Vehicle type code.
	 * @return string
	 */
	public function get_vehicle_type_label( $code ) {
		$types = $this->get_vehicle_types();

		return isset( $types[ $code ] ) ? $types[ $code ] : $code;
	}

	/**
	 * Get all countries.
	 *
	 * @param bool $active_only Return only active countries.
	 * @return array<int, object>
	 */
	public function get_countries( $active_only = false ) {
		global $wpdb;

		$table = $this->get_countries_table_name();

		if ( $active_only ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM {$table} WHERE active = %d ORDER BY sort_order ASC, name ASC",
					1
				)
			);
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} ORDER BY sort_order ASC, name ASC"
			)
		);
	}

	/**
	 * Get country by ID.
	 *
	 * @param int $country_id Country ID.
	 * @return object|null
	 */
	public function get_country( $country_id ) {
		global $wpdb;

		$table = $this->get_countries_table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d",
				(int) $country_id
			)
		);
	}

	/**
	 * Get country by code.
	 *
	 * @param string $code Country code.
	 * @return object|null
	 */
	public function get_country_by_code( $code ) {
		global $wpdb;

		$table = $this->get_countries_table_name();
		$code  = $this->normalize_country_code( $code );

		if ( '' === $code ) {
			return null;
		}

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE code = %s",
				$code
			)
		);
	}

	/**
	 * Get countries as code => label map for active entries.
	 *
	 * @return array<string, string>
	 */
	public function get_country_map() {
		$map       = array();
		$countries = $this->get_countries( true );

		foreach ( $countries as $country ) {
			$map[ $country->code ] = Vintrica_Country_Registry::resolve_label( $country->code );
		}

		return $map;
	}

	/**
	 * Insert a country row.
	 *
	 * @param array<string, mixed> $data Country data.
	 * @return int|false
	 */
	private function insert_country( array $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->get_countries_table_name(),
			array(
				'code'       => $data['code'],
				'name'       => $data['name'],
				'active'     => (int) ! empty( $data['active'] ),
				'sort_order' => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
			),
			array( '%s', '%s', '%d', '%d' )
		);

		return false === $inserted ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Save country from admin input.
	 *
	 * @param array<string, mixed> $data Raw input.
	 * @return int|WP_Error
	 */
	public function save_country( array $data ) {
		global $wpdb;

		$country_id = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$code       = isset( $data['code'] ) ? $this->normalize_country_code( $data['code'] ) : '';
		$active     = ! empty( $data['active'] ) ? 1 : 0;
		$sort_order = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;

		if ( '' === $code ) {
			return new WP_Error( 'vintrica_catalog_country_code', __( 'Zadajte platný kód krajiny.', 'vintrica-vignette-form' ) );
		}

		$registry_country = Vintrica_Country_Registry::get_by_code( $code );

		if ( null === $registry_country ) {
			return new WP_Error( 'vintrica_catalog_country_code', __( 'Zadajte platný kód krajiny z registra.', 'vintrica-vignette-form' ) );
		}

		$name = Vintrica_Country_Registry::get_name( $code, 'sk' );

		$existing = $this->get_country_by_code( $code );

		if ( $existing && (int) $existing->id !== $country_id ) {
			return new WP_Error( 'vintrica_catalog_country_duplicate', __( 'Krajina s týmto kódom už existuje.', 'vintrica-vignette-form' ) );
		}

		$row = array(
			'name'       => $name,
			'code'       => $code,
			'active'     => $active,
			'sort_order' => $sort_order,
		);

		if ( $country_id > 0 ) {
			$updated = $wpdb->update(
				$this->get_countries_table_name(),
				$row,
				array( 'id' => $country_id ),
				array( '%s', '%s', '%d', '%d' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				return new WP_Error( 'vintrica_catalog_country_update', __( 'Krajinu sa nepodarilo uložiť.', 'vintrica-vignette-form' ) );
			}

			return $country_id;
		}

		$inserted = $this->insert_country( $row );

		if ( ! $inserted ) {
			return new WP_Error( 'vintrica_catalog_country_insert', __( 'Krajinu sa nepodarilo pridať.', 'vintrica-vignette-form' ) );
		}

		return $inserted;
	}

	/**
	 * Ensure a catalog country row exists for a registry ISO code.
	 *
	 * @param string $code Registry ISO code.
	 * @return int|WP_Error
	 */
	public function ensure_catalog_country( $code ) {
		$code = $this->normalize_country_code( $code );

		if ( '' === $code ) {
			return new WP_Error( 'vintrica_catalog_country_code', __( 'Zadajte platný kód krajiny.', 'vintrica-vignette-form' ) );
		}

		if ( ! Vintrica_Country_Registry::is_valid_code( $code ) ) {
			return new WP_Error( 'vintrica_catalog_country_code', __( 'Zadajte platný kód krajiny z registra.', 'vintrica-vignette-form' ) );
		}

		$existing = $this->get_country_by_code( $code );

		if ( $existing ) {
			return (int) $existing->id;
		}

		$inserted = $this->insert_country(
			array(
				'code'       => $code,
				'name'       => Vintrica_Country_Registry::get_name( $code, 'sk' ),
				'active'     => 0,
				'sort_order' => 0,
			)
		);

		if ( ! $inserted ) {
			return new WP_Error( 'vintrica_catalog_country_insert', __( 'Krajinu sa nepodarilo pridať.', 'vintrica-vignette-form' ) );
		}

		return (int) $inserted;
	}

	/**
	 * Delete country and its vignettes.
	 *
	 * @param int $country_id Country ID.
	 * @return bool|WP_Error
	 */
	public function delete_country( $country_id ) {
		global $wpdb;

		$country_id = absint( $country_id );

		if ( $country_id <= 0 ) {
			return new WP_Error( 'vintrica_catalog_country_missing', __( 'Krajina neexistuje.', 'vintrica-vignette-form' ) );
		}

		$wpdb->delete(
			$this->get_vignettes_table_name(),
			array( 'country_id' => $country_id ),
			array( '%d' )
		);

		$deleted = $wpdb->delete(
			$this->get_countries_table_name(),
			array( 'id' => $country_id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			return new WP_Error( 'vintrica_catalog_country_delete', __( 'Krajinu sa nepodarilo odstrániť.', 'vintrica-vignette-form' ) );
		}

		return true;
	}

	/**
	 * Get vignettes, optionally filtered by country.
	 *
	 * @param int|null $country_id  Country ID.
	 * @param bool     $active_only Return only active vignettes.
	 * @return array<int, object>
	 */
	public function get_vignettes( $country_id = null, $active_only = false ) {
		global $wpdb;

		$countries_table = $this->get_countries_table_name();
		$vignettes_table = $this->get_vignettes_table_name();
		$where           = array();
		$params          = array();

		if ( null !== $country_id ) {
			$where[]  = 'v.country_id = %d';
			$params[] = (int) $country_id;
		}

		if ( $active_only ) {
			$where[] = 'v.active = 1';
			$where[] = 'c.active = 1';
		}

		$sql = "SELECT v.*, c.code AS country_code, c.name AS country_name
			FROM {$vignettes_table} v
			INNER JOIN {$countries_table} c ON c.id = v.country_id";

		if ( ! empty( $where ) ) {
			$sql .= ' WHERE ' . implode( ' AND ', $where );
		}

		$sql .= ' ORDER BY c.sort_order ASC, c.name ASC, v.sort_order ASC, v.name ASC';

		if ( ! empty( $params ) ) {
			$sql = $wpdb->prepare( $sql, $params ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		}

		return $wpdb->get_results( $sql ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
	}

	/**
	 * Get vignette by ID.
	 *
	 * @param int $vignette_id Vignette ID.
	 * @return object|null
	 */
	public function get_vignette( $vignette_id ) {
		global $wpdb;

		$countries_table = $this->get_countries_table_name();
		$vignettes_table = $this->get_vignettes_table_name();

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT v.*, c.code AS country_code, c.name AS country_name
				FROM {$vignettes_table} v
				INNER JOIN {$countries_table} c ON c.id = v.country_id
				WHERE v.id = %d",
				(int) $vignette_id
			)
		);
	}

	/**
	 * Insert vignette row.
	 *
	 * @param array<string, mixed> $data Vignette data.
	 * @return int|false
	 */
	private function insert_vignette( array $data ) {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->get_vignettes_table_name(),
			array(
				'country_id'     => (int) $data['country_id'],
				'vehicle_type'   => $data['vehicle_type'],
				'vignette_code'  => $data['vignette_code'],
				'name'           => $data['name'],
				'validity_label' => $data['validity_label'],
				'price'          => $data['price'],
				'active'         => (int) ! empty( $data['active'] ),
				'sort_order'     => isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0,
			),
			array( '%d', '%s', '%s', '%s', '%s', '%f', '%d', '%d' )
		);

		return false === $inserted ? false : (int) $wpdb->insert_id;
	}

	/**
	 * Save vignette from admin input.
	 *
	 * @param array<string, mixed> $data Raw input.
	 * @return int|WP_Error
	 */
	public function save_vignette( array $data ) {
		global $wpdb;

		$vignette_id    = isset( $data['id'] ) ? absint( $data['id'] ) : 0;
		$country_id     = isset( $data['country_id'] ) ? absint( $data['country_id'] ) : 0;
		$country_code   = isset( $data['country_code'] ) ? $this->normalize_country_code( $data['country_code'] ) : '';
		$vehicle_type   = isset( $data['vehicle_type'] ) ? sanitize_key( $data['vehicle_type'] ) : '';
		$vignette_code  = isset( $data['vignette_code'] ) ? sanitize_key( $data['vignette_code'] ) : '';
		$name           = isset( $data['name'] ) ? sanitize_text_field( wp_unslash( $data['name'] ) ) : '';
		$validity_label = isset( $data['validity_label'] ) ? sanitize_text_field( wp_unslash( $data['validity_label'] ) ) : '';
		$price          = $this->sanitize_price( isset( $data['price'] ) ? $data['price'] : '' );
		$active         = ! empty( $data['active'] ) ? 1 : 0;
		$sort_order     = isset( $data['sort_order'] ) ? (int) $data['sort_order'] : 0;

		if ( '' !== $country_code ) {
			$ensured_country = $this->ensure_catalog_country( $country_code );

			if ( is_wp_error( $ensured_country ) ) {
				return $ensured_country;
			}

			$country_id = (int) $ensured_country;
		}

		if ( $country_id <= 0 || ! $this->get_country( $country_id ) ) {
			return new WP_Error( 'vintrica_catalog_vignette_country', __( 'Vyberte platnú krajinu.', 'vintrica-vignette-form' ) );
		}

		if ( '' === $vehicle_type || ! isset( $this->get_vehicle_types()[ $vehicle_type ] ) ) {
			return new WP_Error( 'vintrica_catalog_vignette_vehicle', __( 'Vyberte platný typ vozidla.', 'vintrica-vignette-form' ) );
		}

		if ( '' === $vignette_code ) {
			return new WP_Error( 'vintrica_catalog_vignette_code', __( 'Zadajte kód typu známky.', 'vintrica-vignette-form' ) );
		}

		if ( '' === $name || '' === $validity_label ) {
			return new WP_Error( 'vintrica_catalog_vignette_name', __( 'Zadajte názov a popis platnosti známky.', 'vintrica-vignette-form' ) );
		}

		if ( is_wp_error( $price ) ) {
			return $price;
		}

		$duplicate = $this->find_vignette_by_keys( $country_id, $vehicle_type, $vignette_code );

		if ( $duplicate && (int) $duplicate->id !== $vignette_id ) {
			return new WP_Error( 'vintrica_catalog_vignette_duplicate', __( 'Tento typ známky už pre zvolenú krajinu a vozidlo existuje.', 'vintrica-vignette-form' ) );
		}

		$row = array(
			'country_id'     => $country_id,
			'vehicle_type'   => $vehicle_type,
			'vignette_code'  => $vignette_code,
			'name'           => $name,
			'validity_label' => $validity_label,
			'price'          => $price,
			'active'         => $active,
			'sort_order'     => $sort_order,
		);

		if ( $vignette_id > 0 ) {
			$updated = $wpdb->update(
				$this->get_vignettes_table_name(),
				$row,
				array( 'id' => $vignette_id ),
				array( '%d', '%s', '%s', '%s', '%s', '%f', '%d', '%d' ),
				array( '%d' )
			);

			if ( false === $updated ) {
				return new WP_Error( 'vintrica_catalog_vignette_update', __( 'Známku sa nepodarilo uložiť.', 'vintrica-vignette-form' ) );
			}

			return $vignette_id;
		}

		$inserted = $this->insert_vignette( $row );

		if ( ! $inserted ) {
			return new WP_Error( 'vintrica_catalog_vignette_insert', __( 'Známku sa nepodarilo pridať.', 'vintrica-vignette-form' ) );
		}

		return $inserted;
	}

	/**
	 * Delete vignette.
	 *
	 * @param int $vignette_id Vignette ID.
	 * @return bool|WP_Error
	 */
	public function delete_vignette( $vignette_id ) {
		global $wpdb;

		$vignette_id = absint( $vignette_id );

		if ( $vignette_id <= 0 ) {
			return new WP_Error( 'vintrica_catalog_vignette_missing', __( 'Známka neexistuje.', 'vintrica-vignette-form' ) );
		}

		$deleted = $wpdb->delete(
			$this->get_vignettes_table_name(),
			array( 'id' => $vignette_id ),
			array( '%d' )
		);

		if ( false === $deleted ) {
			return new WP_Error( 'vintrica_catalog_vignette_delete', __( 'Známku sa nepodarilo odstrániť.', 'vintrica-vignette-form' ) );
		}

		return true;
	}

	/**
	 * Find vignette by country, vehicle and code.
	 *
	 * @param int    $country_id    Country ID.
	 * @param string $vehicle_type  Vehicle type code.
	 * @param string $vignette_code Vignette code.
	 * @return object|null
	 */
	public function find_vignette_by_keys( $country_id, $vehicle_type, $vignette_code ) {
		global $wpdb;

		return $wpdb->get_row(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->get_vignettes_table_name() . ' WHERE country_id = %d AND vehicle_type = %s AND vignette_code = %s',
				(int) $country_id,
				sanitize_key( $vehicle_type ),
				sanitize_key( $vignette_code )
			)
		);
	}

	/**
	 * Find active vignette by public codes.
	 *
	 * @param string $country_code    Country code.
	 * @param string $vehicle_type    Vehicle type code.
	 * @param string $vignette_code   Vignette code.
	 * @return object|null
	 */
	public function find_active_vignette( $country_code, $vehicle_type, $vignette_code ) {
		$country = $this->get_country_by_code( $country_code );

		if ( ! $country || ! (int) $country->active ) {
			return null;
		}

		$vignette = $this->find_vignette_by_keys( (int) $country->id, $vehicle_type, $vignette_code );

		if ( ! $vignette || ! (int) $vignette->active ) {
			return null;
		}

		$vignette->country_code = $country->code;
		$vignette->country_name = $country->name;

		return $vignette;
	}

	/**
	 * Get price for a catalog entry.
	 *
	 * @param string $country_code  Country code.
	 * @param string $vehicle_type  Vehicle type code.
	 * @param string $vignette_code Vignette code.
	 * @return float|null
	 */
	public function get_price( $country_code, $vehicle_type, $vignette_code ) {
		$vignette = $this->find_active_vignette( $country_code, $vehicle_type, $vignette_code );

		if ( ! $vignette ) {
			return null;
		}

		return round( (float) $vignette->price, 2 );
	}

	/**
	 * Build nested frontend catalog config.
	 *
	 * @return array<string, mixed>
	 */
	public function get_frontend_catalog() {
		$vehicle_types = $this->get_vehicle_types();
		$countries     = $this->get_countries( true );
		$vignettes     = $this->get_vignettes( null, true );
		$config        = array(
			'countries'             => array(),
			'vehicleTypes'          => array(),
			'vehicleTypesByCountry' => array(),
			'validities'            => array(),
		);

		foreach ( $vehicle_types as $code => $label ) {
			$config['vehicleTypes'][] = array(
				'code'  => $code,
				'label' => $label,
			);
		}

		foreach ( $countries as $country ) {
			$config['countries'][] = array(
				'code'  => $country->code,
				'label' => Vintrica_Country_Registry::resolve_label( $country->code ),
			);

			$config['validities'][ $country->code ]             = array();
			$config['vehicleTypesByCountry'][ $country->code ] = array();
		}

		foreach ( $vignettes as $vignette ) {
			$country_code = $vignette->country_code;

			if ( ! isset( $config['validities'][ $country_code ] ) ) {
				continue;
			}

			if ( ! isset( $config['validities'][ $country_code ][ $vignette->vehicle_type ] ) ) {
				$config['validities'][ $country_code ][ $vignette->vehicle_type ] = array();
			}

			$config['validities'][ $country_code ][ $vignette->vehicle_type ][] = array(
				'code'          => $vignette->vignette_code,
				'label'         => $vignette->validity_label,
				'name'          => $vignette->name,
				'validityLabel' => $vignette->validity_label,
				'price'         => round( (float) $vignette->price, 2 ),
			);

			if ( ! in_array( $vignette->vehicle_type, $config['vehicleTypesByCountry'][ $country_code ], true ) ) {
				$config['vehicleTypesByCountry'][ $country_code ][] = $vignette->vehicle_type;
			}
		}

		return $config;
	}

	/**
	 * Sync stored catalog country names from the central registry.
	 *
	 * @return void
	 */
	public function sync_country_names_from_registry() {
		global $wpdb;

		foreach ( $this->get_countries() as $country ) {
			$name = Vintrica_Country_Registry::get_name( $country->code, 'sk' );

			if ( $name === $country->name ) {
				continue;
			}

			if ( ! Vintrica_Country_Registry::is_valid_code( $country->code ) ) {
				continue;
			}

			$wpdb->update(
				$this->get_countries_table_name(),
				array( 'name' => $name ),
				array( 'id' => (int) $country->id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/**
	 * Normalize country code.
	 *
	 * @param string $code Raw code.
	 * @return string
	 */
	public function normalize_country_code( $code ) {
		$code = sanitize_key( $code );

		return strtolower( $code );
	}

	/**
	 * Sanitize decimal price.
	 *
	 * @param mixed $value Raw price.
	 * @return float|WP_Error
	 */
	public function sanitize_price( $value ) {
		if ( is_string( $value ) ) {
			$value = str_replace( ',', '.', trim( wp_unslash( $value ) ) );
		}

		if ( ! is_numeric( $value ) ) {
			return new WP_Error( 'vintrica_catalog_invalid_price', __( 'Zadajte platnú cenu.', 'vintrica-vignette-form' ) );
		}

		$price = round( (float) $value, 2 );

		if ( $price < 0 ) {
			return new WP_Error( 'vintrica_catalog_negative_price', __( 'Cena nemôže byť záporná.', 'vintrica-vignette-form' ) );
		}

		return $price;
	}
}
