<?php
/**
 * Central registry of European countries (ISO code + Slovak/English names).
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_Country_Registry
 */
class Vintrica_Country_Registry {

	/**
	 * Cached country definitions.
	 *
	 * @var array<int, array{code: string, name_sk: string, name_en: string}>|null
	 */
	private static $definitions = null;

	/**
	 * Cached lowercase code index.
	 *
	 * @var array<string, array{code: string, name_sk: string, name_en: string}>|null
	 */
	private static $by_code = null;

	/**
	 * Get all registered European countries sorted by Slovak name.
	 *
	 * @return array<int, array{code: string, name_sk: string, name_en: string}>
	 */
	public static function get_all() {
		$countries = self::get_definitions();

		usort(
			$countries,
			static function ( $left, $right ) {
				return strcoll( $left['name_sk'], $right['name_sk'] );
			}
		);

		return $countries;
	}

	/**
	 * Get country definitions keyed by normalized ISO code.
	 *
	 * @return array<string, array{code: string, name_sk: string, name_en: string}>
	 */
	public static function get_by_code_index() {
		if ( null !== self::$by_code ) {
			return self::$by_code;
		}

		self::$by_code = array();

		foreach ( self::get_definitions() as $country ) {
			self::$by_code[ self::normalize_code( $country['code'] ) ] = $country;
		}

		return self::$by_code;
	}

	/**
	 * Get a single country by ISO code.
	 *
	 * @param string $code ISO 3166-1 alpha-2 code.
	 * @return array{code: string, name_sk: string, name_en: string}|null
	 */
	public static function get_by_code( $code ) {
		$index = self::get_by_code_index();

		return $index[ self::normalize_code( $code ) ] ?? null;
	}

	/**
	 * Normalize a country code for storage and lookup.
	 *
	 * @param string $code Raw country code.
	 * @return string Lowercase ISO code.
	 */
	public static function normalize_code( $code ) {
		return strtolower( sanitize_key( $code ) );
	}

	/**
	 * Check whether a value is a known registry ISO code.
	 *
	 * @param string $code Country code.
	 * @return bool
	 */
	public static function is_valid_code( $code ) {
		return null !== self::get_by_code( $code );
	}

	/**
	 * Get code => Slovak label map for dropdowns.
	 *
	 * @return array<string, string>
	 */
	public static function get_label_map() {
		$map = array();

		foreach ( self::get_all() as $country ) {
			$map[ self::normalize_code( $country['code'] ) ] = $country['name_sk'];
		}

		return $map;
	}

	/**
	 * Build frontend-friendly country options.
	 *
	 * @return array<int, array{code: string, label: string}>
	 */
	public static function get_frontend_options() {
		$options = array();

		foreach ( self::get_all() as $country ) {
			$options[] = array(
				'code'  => self::normalize_code( $country['code'] ),
				'label' => $country['name_sk'],
			);
		}

		return $options;
	}

	/**
	 * Resolve a stored country value to a Slovak display label.
	 *
	 * Supports legacy values that were stored as slugified Slovak names.
	 *
	 * @param string $code_or_legacy Stored country code or legacy value.
	 * @return string
	 */
	public static function resolve_label( $code_or_legacy ) {
		$value = trim( (string) $code_or_legacy );

		if ( '' === $value ) {
			return '';
		}

		$country = self::get_by_code( $value );

		if ( null !== $country ) {
			return $country['name_sk'];
		}

		$normalized = self::normalize_code( $value );

		foreach ( self::get_definitions() as $entry ) {
			if ( self::normalize_code( $entry['name_sk'] ) === $normalized ) {
				return $entry['name_sk'];
			}

			if ( 0 === strcasecmp( $entry['name_sk'], $value ) ) {
				return $entry['name_sk'];
			}
		}

		return $value;
	}

	/**
	 * Get localized country name by ISO code.
	 *
	 * @param string $code   ISO code.
	 * @param string $locale Locale key: sk or en.
	 * @return string
	 */
	public static function get_name( $code, $locale = 'sk' ) {
		$country = self::get_by_code( $code );

		if ( null === $country ) {
			return (string) $code;
		}

		return 'en' === $locale ? $country['name_en'] : $country['name_sk'];
	}

	/**
	 * Check whether a stored registration/billing value is valid (code or legacy name).
	 *
	 * @param string $value Stored value.
	 * @return bool
	 */
	public static function is_valid_registration_value( $value ) {
		if ( self::is_valid_code( $value ) ) {
			return true;
		}

		$resolved = self::resolve_label( $value );

		foreach ( self::get_definitions() as $entry ) {
			if ( $entry['name_sk'] === $resolved ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Normalize a registration/billing value to a registry ISO code when possible.
	 *
	 * @param string $value Stored or submitted value.
	 * @return string
	 */
	public static function normalize_registration_value( $value ) {
		if ( self::is_valid_code( $value ) ) {
			return self::normalize_code( $value );
		}

		$resolved = self::resolve_label( $value );

		foreach ( self::get_definitions() as $entry ) {
			if ( $entry['name_sk'] === $resolved ) {
				return self::normalize_code( $entry['code'] );
			}
		}

		return self::normalize_code( $value );
	}

	/**
	 * Get raw country definitions.
	 *
	 * @return array<int, array{code: string, name_sk: string, name_en: string}>
	 */
	private static function get_definitions() {
		if ( null !== self::$definitions ) {
			return self::$definitions;
		}

		self::$definitions = array(
			array( 'code' => 'AL', 'name_sk' => 'Albánsko', 'name_en' => 'Albania' ),
			array( 'code' => 'AD', 'name_sk' => 'Andorra', 'name_en' => 'Andorra' ),
			array( 'code' => 'BE', 'name_sk' => 'Belgicko', 'name_en' => 'Belgium' ),
			array( 'code' => 'BY', 'name_sk' => 'Bielorusko', 'name_en' => 'Belarus' ),
			array( 'code' => 'BA', 'name_sk' => 'Bosna a Hercegovina', 'name_en' => 'Bosnia and Herzegovina' ),
			array( 'code' => 'BG', 'name_sk' => 'Bulharsko', 'name_en' => 'Bulgaria' ),
			array( 'code' => 'ME', 'name_sk' => 'Čierna Hora', 'name_en' => 'Montenegro' ),
			array( 'code' => 'CZ', 'name_sk' => 'Česko', 'name_en' => 'Czech Republic' ),
			array( 'code' => 'DK', 'name_sk' => 'Dánsko', 'name_en' => 'Denmark' ),
			array( 'code' => 'EE', 'name_sk' => 'Estónsko', 'name_en' => 'Estonia' ),
			array( 'code' => 'FI', 'name_sk' => 'Fínsko', 'name_en' => 'Finland' ),
			array( 'code' => 'FR', 'name_sk' => 'Francúzsko', 'name_en' => 'France' ),
			array( 'code' => 'GR', 'name_sk' => 'Grécko', 'name_en' => 'Greece' ),
			array( 'code' => 'NL', 'name_sk' => 'Holandsko', 'name_en' => 'Netherlands' ),
			array( 'code' => 'HR', 'name_sk' => 'Chorvátsko', 'name_en' => 'Croatia' ),
			array( 'code' => 'IE', 'name_sk' => 'Írsko', 'name_en' => 'Ireland' ),
			array( 'code' => 'IS', 'name_sk' => 'Island', 'name_en' => 'Iceland' ),
			array( 'code' => 'XK', 'name_sk' => 'Kosovo', 'name_en' => 'Kosovo' ),
			array( 'code' => 'LI', 'name_sk' => 'Lichtenštajnsko', 'name_en' => 'Liechtenstein' ),
			array( 'code' => 'LT', 'name_sk' => 'Litva', 'name_en' => 'Lithuania' ),
			array( 'code' => 'LV', 'name_sk' => 'Lotyšsko', 'name_en' => 'Latvia' ),
			array( 'code' => 'LU', 'name_sk' => 'Luxembursko', 'name_en' => 'Luxembourg' ),
			array( 'code' => 'HU', 'name_sk' => 'Maďarsko', 'name_en' => 'Hungary' ),
			array( 'code' => 'MT', 'name_sk' => 'Malta', 'name_en' => 'Malta' ),
			array( 'code' => 'MD', 'name_sk' => 'Moldavsko', 'name_en' => 'Moldova' ),
			array( 'code' => 'MC', 'name_sk' => 'Monako', 'name_en' => 'Monaco' ),
			array( 'code' => 'DE', 'name_sk' => 'Nemecko', 'name_en' => 'Germany' ),
			array( 'code' => 'NO', 'name_sk' => 'Nórsko', 'name_en' => 'Norway' ),
			array( 'code' => 'PL', 'name_sk' => 'Poľsko', 'name_en' => 'Poland' ),
			array( 'code' => 'PT', 'name_sk' => 'Portugalsko', 'name_en' => 'Portugal' ),
			array( 'code' => 'AT', 'name_sk' => 'Rakúsko', 'name_en' => 'Austria' ),
			array( 'code' => 'RO', 'name_sk' => 'Rumunsko', 'name_en' => 'Romania' ),
			array( 'code' => 'SM', 'name_sk' => 'San Maríno', 'name_en' => 'San Marino' ),
			array( 'code' => 'MK', 'name_sk' => 'Severné Macedónsko', 'name_en' => 'North Macedonia' ),
			array( 'code' => 'RS', 'name_sk' => 'Srbsko', 'name_en' => 'Serbia' ),
			array( 'code' => 'SK', 'name_sk' => 'Slovensko', 'name_en' => 'Slovakia' ),
			array( 'code' => 'SI', 'name_sk' => 'Slovinsko', 'name_en' => 'Slovenia' ),
			array( 'code' => 'ES', 'name_sk' => 'Španielsko', 'name_en' => 'Spain' ),
			array( 'code' => 'CH', 'name_sk' => 'Švajčiarsko', 'name_en' => 'Switzerland' ),
			array( 'code' => 'SE', 'name_sk' => 'Švédsko', 'name_en' => 'Sweden' ),
			array( 'code' => 'IT', 'name_sk' => 'Taliansko', 'name_en' => 'Italy' ),
			array( 'code' => 'UA', 'name_sk' => 'Ukrajina', 'name_en' => 'Ukraine' ),
			array( 'code' => 'VA', 'name_sk' => 'Vatikán', 'name_en' => 'Vatican City' ),
			array( 'code' => 'GB', 'name_sk' => 'Veľká Británia', 'name_en' => 'United Kingdom' ),
		);

		return self::$definitions;
	}
}
