#!/usr/bin/env php
<?php
/**
 * Local build verification for VINTRICA Vignette Form.
 *
 * Usage: php bin/verify.php
 *
 * @package Vintrica_Vignette_Form
 */

if ( 'cli' !== php_sapi_name() ) {
	exit( 1 );
}

$plugin_root = dirname( __DIR__ );
$errors      = array();
$checks      = array();

/**
 * Record a passing check.
 *
 * @param string $message Check description.
 */
function vintrica_verify_pass( $message ) {
	global $checks;
	$checks[] = array(
		'status'  => 'pass',
		'message' => $message,
	);
}

/**
 * Record a failing check.
 *
 * @param string $message Error description.
 */
function vintrica_verify_fail( $message ) {
	global $errors, $checks;
	$errors[] = $message;
	$checks[] = array(
		'status'  => 'fail',
		'message' => $message,
	);
}

/**
 * Stub WordPress and plugin helpers.
 */
function vintrica_bootstrap_wordpress_stubs() {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	}

	if ( ! defined( 'VINTRICA_VERSION' ) ) {
		define( 'VINTRICA_VERSION', '1.5.0' );
	}

	if ( ! defined( 'VINTRICA_PLUGIN_FILE' ) ) {
		define( 'VINTRICA_PLUGIN_FILE', dirname( __DIR__ ) . '/vintrica-vignette-form.php' );
	}

	if ( ! defined( 'VINTRICA_PLUGIN_DIR' ) ) {
		define( 'VINTRICA_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
	}

	if ( ! defined( 'VINTRICA_PLUGIN_URL' ) ) {
		define( 'VINTRICA_PLUGIN_URL', 'https://example.test/wp-content/plugins/vintrica-vignette-form/' );
	}

	if ( ! defined( 'VINTRICA_PLUGIN_BASENAME' ) ) {
		define( 'VINTRICA_PLUGIN_BASENAME', 'vintrica-vignette-form/vintrica-vignette-form.php' );
	}

	if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
		define( 'MINUTE_IN_SECONDS', 60 );
	}

	$GLOBALS['wp_version']        = '6.5';
	$GLOBALS['shortcode_tags']    = array();
	$GLOBALS['wp_scripts']        = array(
		'registered' => array(),
		'enqueued'   => array(),
	);
	$GLOBALS['wp_styles']         = array(
		'registered' => array(),
		'enqueued'   => array(),
	);
	$GLOBALS['wp_actions']        = array();
	$GLOBALS['wp_filters']        = array();
	$GLOBALS['vintrica_transients'] = array();
	$GLOBALS['vintrica_options']  = array();

	if ( ! function_exists( 'add_action' ) ) {
		function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
			$GLOBALS['wp_actions'][ $hook ][] = $callback;
		}
	}

	if ( ! function_exists( 'add_filter' ) ) {
		function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
			$GLOBALS['wp_filters'][ $hook ][] = $callback;
		}
	}

	if ( ! function_exists( 'apply_filters' ) ) {
		function apply_filters( $hook, $value ) {
			return $value;
		}
	}

	if ( ! function_exists( 'do_action' ) ) {
		function do_action( $hook, ...$args ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
		}
	}

	if ( ! function_exists( 'add_shortcode' ) ) {
		function add_shortcode( $tag, $callback ) {
			$GLOBALS['shortcode_tags'][ $tag ] = $callback;
		}
	}

	if ( ! function_exists( 'register_activation_hook' ) ) {
		function register_activation_hook( $file, $callback ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			$GLOBALS['vintrica_activation_hook'] = $callback;
		}
	}

	if ( ! function_exists( 'register_deactivation_hook' ) ) {
		function register_deactivation_hook( $file, $callback ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			$GLOBALS['vintrica_deactivation_hook'] = $callback;
		}
	}

	if ( ! function_exists( 'load_plugin_textdomain' ) ) {
		function load_plugin_textdomain( $domain, $deprecated = false, $plugin_rel_path = false ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return true;
		}
	}

	if ( ! function_exists( 'plugin_dir_path' ) ) {
		function plugin_dir_path( $file ) {
			return trailingslashit( dirname( $file ) );
		}
	}

	if ( ! function_exists( 'plugin_dir_url' ) ) {
		function plugin_dir_url( $file ) {
			return 'https://example.test/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
		}
	}

	if ( ! function_exists( 'plugin_basename' ) ) {
		function plugin_basename( $file ) {
			return basename( dirname( $file ) ) . '/' . basename( $file );
		}
	}

	if ( ! function_exists( 'trailingslashit' ) ) {
		function trailingslashit( $string ) {
			return rtrim( $string, '/\\' ) . '/';
		}
	}

	if ( ! function_exists( 'wp_register_style' ) ) {
		function wp_register_style( $handle, $src, $deps = array(), $ver = false, $media = 'all' ) {
			$GLOBALS['wp_styles']['registered'][ $handle ] = compact( 'handle', 'src', 'deps', 'ver', 'media' );
		}
	}

	if ( ! function_exists( 'wp_register_script' ) ) {
		function wp_register_script( $handle, $src, $deps = array(), $ver = false, $in_footer = false ) {
			$GLOBALS['wp_scripts']['registered'][ $handle ] = compact( 'handle', 'src', 'deps', 'ver', 'in_footer' );
		}
	}

	if ( ! function_exists( 'wp_enqueue_style' ) ) {
		function wp_enqueue_style( $handle ) {
			$GLOBALS['wp_styles']['enqueued'][ $handle ] = true;
		}
	}

	if ( ! function_exists( 'wp_enqueue_script' ) ) {
		function wp_enqueue_script( $handle ) {
			$GLOBALS['wp_scripts']['enqueued'][ $handle ] = true;
		}
	}

	if ( ! function_exists( 'wp_localize_script' ) ) {
		function wp_localize_script( $handle, $object_name, $l10n ) {
			$GLOBALS['wp_scripts']['localized'][ $handle ][ $object_name ] = $l10n;
		}
	}

	if ( ! function_exists( 'wp_nonce_field' ) ) {
		function wp_nonce_field( $action, $name, $referer = true, $display = true ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return '<input type="hidden" name="' . esc_attr( $name ) . '" value="test-nonce" />';
		}
	}

	if ( ! function_exists( 'esc_attr' ) ) {
		function esc_attr( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_html' ) ) {
		function esc_html( $text ) {
			return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
		}
	}

	if ( ! function_exists( 'esc_url' ) ) {
		function esc_url( $url ) {
			return (string) $url;
		}
	}

	if ( ! function_exists( 'esc_html__' ) ) {
		function esc_html__( $text, $domain = 'default' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return $text;
		}
	}

	if ( ! function_exists( '__' ) ) {
		function __( $text, $domain = 'default' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return $text;
		}
	}

	if ( ! function_exists( '_n' ) ) {
		function _n( $single, $plural, $number, $domain = 'default' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return 1 === (int) $number ? $single : $plural;
		}
	}

	if ( ! function_exists( 'sanitize_text_field' ) ) {
		function sanitize_text_field( $str ) {
			return trim( (string) $str );
		}
	}

	if ( ! function_exists( 'sanitize_key' ) ) {
		function sanitize_key( $key ) {
			return strtolower( preg_replace( '/[^a-z0-9_\-]/', '', (string) $key ) );
		}
	}

	if ( ! function_exists( 'wp_unslash' ) ) {
		function wp_unslash( $value ) {
			return is_string( $value ) ? stripslashes( $value ) : $value;
		}
	}

	if ( ! function_exists( 'wp_verify_nonce' ) ) {
		function wp_verify_nonce( $nonce, $action ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return 'test-nonce' === $nonce;
		}
	}

	if ( ! function_exists( 'wp_get_referer' ) ) {
		function wp_get_referer() {
			return 'https://example.test/vignette-form/';
		}
	}

	if ( ! function_exists( 'wp_parse_url' ) ) {
		function wp_parse_url( $url, $component = -1 ) {
			return parse_url( $url, $component );
		}
	}

	if ( ! function_exists( 'home_url' ) ) {
		function home_url( $path = '' ) {
			return 'https://example.test' . $path;
		}
	}

	if ( ! function_exists( 'get_permalink' ) ) {
		function get_permalink() {
			return 'https://example.test/vignette-form/';
		}
	}

	if ( ! function_exists( 'get_transient' ) ) {
		function get_transient( $key ) {
			return $GLOBALS['vintrica_transients'][ $key ] ?? false;
		}
	}

	if ( ! function_exists( 'set_transient' ) ) {
		function set_transient( $key, $value, $expiration ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			$GLOBALS['vintrica_transients'][ $key ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'delete_transient' ) ) {
		function delete_transient( $key ) {
			unset( $GLOBALS['vintrica_transients'][ $key ] );
			return true;
		}
	}

	if ( ! function_exists( 'wp_salt' ) ) {
		function wp_salt( $scheme = 'auth' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return 'vintrica-test-salt';
		}
	}

	if ( ! function_exists( 'number_format_i18n' ) ) {
		function number_format_i18n( $number, $decimals = 0 ) {
			return number_format( (float) $number, $decimals, '.', ',' );
		}
	}

	if ( ! function_exists( 'flush_rewrite_rules' ) ) {
		function flush_rewrite_rules( $hard = true ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return true;
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		function get_option( $option, $default = false ) {
			return $GLOBALS['vintrica_options'][ $option ] ?? $default;
		}
	}

	if ( ! function_exists( 'add_option' ) ) {
		function add_option( $option, $value, $deprecated = '', $autoload = 'yes' ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			$GLOBALS['vintrica_options'][ $option ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'update_option' ) ) {
		function update_option( $option, $value ) {
			$GLOBALS['vintrica_options'][ $option ] = $value;
			return true;
		}
	}

	if ( ! function_exists( 'deactivate_plugins' ) ) {
		function deactivate_plugins( $plugins ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return true;
		}
	}

	if ( ! function_exists( 'dbDelta' ) ) {
		function dbDelta( $queries ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return array();
		}
	}

	if ( ! function_exists( 'current_user_can' ) ) {
		function current_user_can( $capability ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return true;
		}
	}

	if ( ! function_exists( 'get_the_ID' ) ) {
		function get_the_ID() {
			return 42;
		}
	}

	if ( ! function_exists( 'add_query_arg' ) ) {
		function add_query_arg( $args, $url ) {
			$query = http_build_query( $args );
			return strpos( $url, '?' ) !== false ? $url . '&' . $query : $url . '?' . $query;
		}
	}

	if ( ! function_exists( 'rest_url' ) ) {
		function rest_url( $path = '' ) {
			return 'https://example.test/wp-json/' . ltrim( (string) $path, '/' );
		}
	}

	if ( ! function_exists( 'wp_create_nonce' ) ) {
		function wp_create_nonce( $action ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return 'test-rest-nonce';
		}
	}

	if ( ! function_exists( 'esc_url_raw' ) ) {
		function esc_url_raw( $url ) {
			return (string) $url;
		}
	}

	if ( ! function_exists( 'wp_safe_redirect' ) ) {
		function wp_safe_redirect( $location ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
			return false;
		}
	}

	if ( ! function_exists( 'sanitize_email' ) ) {
		function sanitize_email( $email ) {
			return filter_var( (string) $email, FILTER_SANITIZE_EMAIL );
		}
	}

	if ( ! function_exists( 'is_email' ) ) {
		function is_email( $email ) {
			return false !== filter_var( (string) $email, FILTER_VALIDATE_EMAIL );
		}
	}

	if ( ! function_exists( 'wp_die' ) ) {
		function wp_die( $message ) {
			throw new RuntimeException( (string) $message );
		}
	}

	if ( ! class_exists( 'WP_Error' ) ) {
		class WP_Error {
			private $code;
			private $message;

			public function __construct( $code, $message ) {
				$this->code    = $code;
				$this->message = $message;
			}

			public function get_error_message() {
				return $this->message;
			}
		}
	}

	if ( ! function_exists( 'is_wp_error' ) ) {
		function is_wp_error( $thing ) {
			return $thing instanceof WP_Error;
		}
	}

	if ( ! isset( $GLOBALS['wpdb'] ) ) {
		$GLOBALS['wpdb'] = new class() {
			public $prefix = 'wp_';
			public $options = 'wp_options';
			public $queries = array();
			public $insert_id = 1;
			public $last_error = '';

			public function esc_like( $text ) {
				return addcslashes( (string) $text, '_%\\' );
			}

			public function prepare( $query, ...$args ) {
				return vsprintf( str_replace( '%s', "'%s'", $query ), $args );
			}

			public function query( $query ) {
				$this->queries[] = $query;
				return 0;
			}

			public function insert( $table, $data, $format = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				$this->queries[] = 'INSERT INTO ' . $table;
				return 1;
			}

			public function update( $table, $data, $where, $format = null, $where_format = null ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter
				$this->queries[] = 'UPDATE ' . $table;
				return 1;
			}

			public function get_row( $query ) {
				if ( false !== strpos( $query, 'order_number' ) ) {
					return (object) array(
						'id'            => 1,
						'order_number'  => 'VIN-20260618-000001',
						'status'        => 'pending_payment',
						'vignettes'     => '[]',
						'billing'       => '{}',
						'subtotal'      => 0,
						'service_fee'   => 0,
						'total'         => 0,
						'currency'      => 'EUR',
						'created_at'    => gmdate( 'Y-m-d H:i:s' ),
					);
				}

				return null;
			}

			public function get_results( $query ) {
				if ( false !== strpos( $query, 'vintrica_catalog_countries' ) && false === strpos( $query, 'vintrica_catalog_vignettes' ) ) {
					return array(
						(object) array(
							'id'         => 1,
							'code'       => 'sk',
							'name'       => 'Slovensko',
							'active'     => 1,
							'sort_order' => 60,
						),
					);
				}

				if ( false !== strpos( $query, 'vintrica_catalog_vignettes' ) ) {
					return array(
						(object) array(
							'id'             => 1,
							'country_id'     => 1,
							'vehicle_type'   => 'car',
							'vignette_code'  => '1d',
							'name'           => '1 dňová',
							'validity_label' => '1 dňová',
							'price'          => '8.90',
							'active'         => 1,
							'sort_order'     => 0,
							'country_code'   => 'sk',
							'country_name'   => 'Slovensko',
						),
					);
				}

				return array();
			}

			public function get_var( $query ) {
				if ( false !== strpos( $query, 'COUNT(*)' ) && false !== strpos( $query, 'vintrica_catalog_countries' ) ) {
					return '1';
				}

				return '0';
			}

			public function get_charset_collate() {
				return 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci';
			}
		};
	}
}

vintrica_bootstrap_wordpress_stubs();

$upgrade_stub = ABSPATH . 'wp-admin/includes/upgrade.php';

if ( ! file_exists( $upgrade_stub ) ) {
	$upgrade_dir = dirname( $upgrade_stub );

	if ( ! is_dir( $upgrade_dir ) ) {
		mkdir( $upgrade_dir, 0777, true );
	}

	file_put_contents(
		$upgrade_stub,
		"<?php\nif ( ! function_exists( 'dbDelta' ) ) {\n\tfunction dbDelta( \$queries ) {\n\t\treturn array();\n\t}\n}\n"
	);
}

$required_files = array(
	'vintrica-vignette-form.php',
	'index.php',
	'includes/index.php',
	'includes/class-vintrica-catalog.php',
	'includes/class-vintrica-pricing.php',
	'includes/class-vintrica-security.php',
	'includes/class-vintrica-orders.php',
	'includes/class-vintrica-stripe.php',
	'includes/class-vintrica-checkout.php',
	'includes/class-vintrica-rest.php',
	'includes/class-vintrica-admin-catalog.php',
	'includes/class-vintrica-admin.php',
	'includes/class-vintrica-frontend.php',
	'includes/class-vintrica-activator.php',
	'includes/class-vintrica-deactivator.php',
	'assets/index.php',
	'assets/css/frontend.css',
	'assets/css/admin.css',
	'assets/js/frontend.js',
);

foreach ( $required_files as $relative_path ) {
	$absolute_path = $plugin_root . '/' . $relative_path;

	if ( ! file_exists( $absolute_path ) ) {
		vintrica_verify_fail( 'Missing required file: ' . $relative_path );
		continue;
	}

	if ( str_ends_with( $relative_path, '.php' ) ) {
		$output     = array();
		$return_var = 0;
		exec( 'php -l ' . escapeshellarg( $absolute_path ) . ' 2>&1', $output, $return_var );

		if ( 0 !== $return_var ) {
			vintrica_verify_fail( 'PHP syntax error in ' . $relative_path . ': ' . implode( ' ', $output ) );
		} else {
			vintrica_verify_pass( 'PHP syntax valid: ' . $relative_path );
		}
	} else {
		vintrica_verify_pass( 'Asset present: ' . $relative_path );
	}
}

try {
	require $plugin_root . '/vintrica-vignette-form.php';
	vintrica_verify_pass( 'Main plugin file loaded without fatal error.' );
} catch ( Throwable $exception ) {
	vintrica_verify_fail( 'Fatal error loading plugin: ' . $exception->getMessage() );
}

if ( ! empty( $GLOBALS['wp_actions']['init'] ) ) {
	foreach ( $GLOBALS['wp_actions']['init'] as $callback ) {
		call_user_func( $callback );
	}
	vintrica_verify_pass( 'WordPress init hooks executed.' );
}

if ( ! empty( $GLOBALS['wp_actions']['wp_enqueue_scripts'] ) ) {
	foreach ( $GLOBALS['wp_actions']['wp_enqueue_scripts'] as $callback ) {
		call_user_func( $callback );
	}
	vintrica_verify_pass( 'Asset registration hooks executed.' );
}

if ( ! defined( 'VINTRICA_VERSION' ) || '' === VINTRICA_VERSION ) {
	vintrica_verify_fail( 'VINTRICA_VERSION constant is missing or empty.' );
} else {
	vintrica_verify_pass( 'VINTRICA_VERSION constant defined: ' . VINTRICA_VERSION );
}

if ( ! function_exists( 'vintrica_vignette_form' ) ) {
	vintrica_verify_fail( 'Bootstrap function vintrica_vignette_form() is missing.' );
} else {
	$plugin = vintrica_vignette_form();

	if ( ! $plugin instanceof Vintrica_Vignette_Form ) {
		vintrica_verify_fail( 'Plugin singleton did not initialize correctly.' );
	} else {
		vintrica_verify_pass( 'Plugin singleton initialized.' );
	}
}

if ( empty( $GLOBALS['vintrica_activation_hook'] ) || ! is_callable( $GLOBALS['vintrica_activation_hook'] ) ) {
	vintrica_verify_fail( 'Activation hook is not registered.' );
} else {
	call_user_func( $GLOBALS['vintrica_activation_hook'] );
	vintrica_verify_pass( 'Activation hook executed without fatal error.' );
}

if ( empty( $GLOBALS['vintrica_deactivation_hook'] ) || ! is_callable( $GLOBALS['vintrica_deactivation_hook'] ) ) {
	vintrica_verify_fail( 'Deactivation hook is not registered.' );
} else {
	call_user_func( $GLOBALS['vintrica_deactivation_hook'] );
	vintrica_verify_pass( 'Deactivation hook executed without fatal error.' );
}

if ( ! isset( $GLOBALS['shortcode_tags']['vintrica_vignette_form'] ) ) {
	vintrica_verify_fail( 'Shortcode [vintrica_vignette_form] is not registered.' );
} else {
	$shortcode_output = call_user_func( $GLOBALS['shortcode_tags']['vintrica_vignette_form'], array() );

	if ( ! is_string( $shortcode_output ) || '' === trim( $shortcode_output ) ) {
		vintrica_verify_fail( 'Shortcode [vintrica_vignette_form] returned empty output.' );
	} elseif ( false === strpos( $shortcode_output, 'vintrica-vignette-form' ) ) {
		vintrica_verify_fail( 'Shortcode output does not contain expected markup.' );
	} elseif ( false === strpos( $shortcode_output, 'vintrica-step--billing' ) ) {
		vintrica_verify_fail( 'Shortcode output does not contain billing step markup.' );
	} elseif ( false === strpos( $shortcode_output, 'vintrica-step--review' ) ) {
		vintrica_verify_fail( 'Shortcode output does not contain review step markup.' );
	} elseif ( false === strpos( $shortcode_output, 'vintrica_billing_first_name' ) ) {
		vintrica_verify_fail( 'Shortcode output does not contain billing form fields.' );
	} else {
		vintrica_verify_pass( 'Shortcode [vintrica_vignette_form] renders expected two-step checkout markup.' );
	}
}

$registered_script = $GLOBALS['wp_scripts']['registered']['vintrica-frontend'] ?? null;

if ( null === $registered_script ) {
	vintrica_verify_fail( 'Frontend script handle vintrica-frontend is not registered.' );
} else {
	if ( in_array( 'jquery', $registered_script['deps'], true ) ) {
		vintrica_verify_fail( 'Frontend script incorrectly depends on jQuery.' );
	} else {
		vintrica_verify_pass( 'Frontend script registered without jQuery dependency.' );
	}

	if ( false === strpos( $registered_script['src'], 'assets/js/frontend.js' ) ) {
		vintrica_verify_fail( 'Frontend script source path is incorrect.' );
	} else {
		vintrica_verify_pass( 'Frontend script source path is correct.' );
	}
}

$registered_style = $GLOBALS['wp_styles']['registered']['vintrica-frontend'] ?? null;

if ( null === $registered_style ) {
	vintrica_verify_fail( 'Frontend style handle vintrica-frontend is not registered.' );
} elseif ( false === strpos( $registered_style['src'], 'assets/css/frontend.css' ) ) {
	vintrica_verify_fail( 'Frontend style source path is incorrect.' );
} else {
	vintrica_verify_pass( 'Frontend style registered with correct source path.' );
}

if ( empty( $GLOBALS['wp_styles']['enqueued']['vintrica-frontend'] ) ) {
	vintrica_verify_fail( 'Frontend styles are not enqueued when shortcode renders.' );
} else {
	vintrica_verify_pass( 'Frontend styles enqueued when shortcode renders.' );
}

if ( empty( $GLOBALS['wp_scripts']['enqueued']['vintrica-frontend'] ) ) {
	vintrica_verify_fail( 'Frontend script is not enqueued when shortcode renders.' );
} else {
	vintrica_verify_pass( 'Frontend script enqueued when shortcode renders.' );
}

if ( empty( $GLOBALS['wp_scripts']['localized']['vintrica-frontend']['vintricaConfig']['config']['validities'] ) ) {
	vintrica_verify_fail( 'Pricing config was not localized to JavaScript.' );
} else {
	vintrica_verify_pass( 'Pricing config localized to JavaScript via wp_localize_script.' );
}

$frontend_js = file_get_contents( $plugin_root . '/assets/js/frontend.js' );

if ( false !== stripos( $frontend_js, 'jQuery' ) || false !== stripos( $frontend_js, 'jquery' ) ) {
	vintrica_verify_fail( 'frontend.js references jQuery.' );
} else {
	vintrica_verify_pass( 'frontend.js has no jQuery references.' );
}

if ( false !== stripos( $frontend_js, 'woocommerce' ) ) {
	vintrica_verify_fail( 'frontend.js still references WooCommerce.' );
} else {
	vintrica_verify_pass( 'frontend.js has no WooCommerce references.' );
}

$plugin_sources = implode(
	"\n",
	array(
		file_get_contents( $plugin_root . '/vintrica-vignette-form.php' ),
		file_get_contents( $plugin_root . '/includes/class-vintrica-frontend.php' ),
	)
);

if ( false !== stripos( $plugin_sources, 'woocommerce' ) || false !== stripos( $plugin_sources, 'WooCommerce' ) ) {
	vintrica_verify_fail( 'Plugin source still references WooCommerce.' );
} else {
	vintrica_verify_pass( 'Plugin source has no WooCommerce references.' );
}

if ( ! class_exists( 'Vintrica_Orders' ) || ! class_exists( 'Vintrica_Checkout' ) || ! class_exists( 'Vintrica_Stripe' ) || ! class_exists( 'Vintrica_Rest' ) ) {
	vintrica_verify_fail( 'Custom checkout classes are missing.' );
} else {
	vintrica_verify_pass( 'Custom checkout classes loaded.' );
}

echo PHP_EOL . 'VINTRICA build verification' . PHP_EOL;
echo str_repeat( '-', 32 ) . PHP_EOL;

foreach ( $checks as $check ) {
	$symbol = 'pass' === $check['status'] ? '[PASS]' : '[FAIL]';
	echo $symbol . ' ' . $check['message'] . PHP_EOL;
}

echo str_repeat( '-', 32 ) . PHP_EOL;

if ( ! empty( $errors ) ) {
	echo 'Verification failed with ' . count( $errors ) . ' error(s).' . PHP_EOL;
	exit( 1 );
}

echo 'All verification checks passed.' . PHP_EOL;
exit( 0 );
