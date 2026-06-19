<?php
/**
 * WooCommerce cart and checkout integration.
 *
 * @package Vintrica_Vignette_Form
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Vintrica_WooCommerce
 */
class Vintrica_WooCommerce {

	/**
	 * Option key for the base vignette product ID.
	 */
	const PRODUCT_OPTION = 'vintrica_wc_product_id';

	/**
	 * Cart item data flag.
	 */
	const CART_ITEM_FLAG = 'vintrica_vignette';

	/**
	 * Pricing handler.
	 *
	 * @var Vintrica_Pricing|null
	 */
	private $pricing;

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'plugins_loaded', array( $this, 'maybe_bootstrap' ), 20 );
		add_action( 'admin_notices', array( $this, 'render_missing_woocommerce_notice' ) );
	}

	/**
	 * Bootstrap WooCommerce integration when WooCommerce is available.
	 *
	 * @return void
	 */
	public function maybe_bootstrap() {
		if ( ! $this->is_woocommerce_active() ) {
			return;
		}

		$this->pricing = vintrica_vignette_form()->pricing;
		$this->register_hooks();
		$this->ensure_product_exists();
	}

	/**
	 * Check whether WooCommerce is active.
	 *
	 * @return bool
	 */
	public function is_woocommerce_active() {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * Check whether cart checkout integration is ready for form submission.
	 *
	 * @return bool
	 */
	public function is_checkout_ready() {
		if ( ! $this->is_woocommerce_active() ) {
			return false;
		}

		$ready = $this->get_product_id() > 0;

		/**
		 * Filter whether WooCommerce cart checkout integration is ready.
		 *
		 * @param bool $ready Whether checkout handoff is implemented.
		 */
		return (bool) apply_filters( 'vintrica_is_checkout_ready', $ready );
	}

	/**
	 * Process a validated vignette order and add items to the cart.
	 *
	 * @param array $order_data Validated order with vignettes and totals.
	 * @return string|WP_Error Checkout redirect URL on success.
	 */
	public function process_order( array $order_data ) {
		if ( ! $this->is_checkout_ready() ) {
			return new WP_Error(
				'vintrica_wc_not_ready',
				__( 'WooCommerce nie je pripravený na spracovanie objednávky známok.', 'vintrica-vignette-form' )
			);
		}

		if ( empty( $order_data['vignettes'] ) || ! is_array( $order_data['vignettes'] ) ) {
			return new WP_Error(
				'vintrica_wc_empty_order',
				__( 'Nebola odoslaná žiadna objednávka známok.', 'vintrica-vignette-form' )
			);
		}

		if ( null === WC()->cart ) {
			wc_load_cart();
		}

		if ( null === WC()->cart ) {
			return new WP_Error(
				'vintrica_wc_cart_unavailable',
				__( 'Košík WooCommerce nie je dostupný.', 'vintrica-vignette-form' )
			);
		}

		$this->remove_existing_vignette_cart_items();

		$product_id = $this->get_product_id();

		foreach ( $order_data['vignettes'] as $vignette ) {
			$price = $this->pricing->get_vignette_price( $vignette['country'], $vignette['vignette_validity'] );

			if ( null === $price ) {
				return new WP_Error(
					'vintrica_wc_invalid_price',
					__( 'Neplatná cena známky. Skúste to znova.', 'vintrica-vignette-form' )
				);
			}

			$cart_item_data = array(
				self::CART_ITEM_FLAG     => true,
				'vintrica_unique_key'    => wp_generate_password( 12, false ),
				'vintrica_price'         => $price,
				'vintrica_country'       => $vignette['country'],
				'vintrica_vehicle_type'  => $vignette['vehicle_type'],
				'vintrica_validity'      => $vignette['vignette_validity'],
				'vintrica_start_date'    => $vignette['start_date'],
				'vintrica_license_plate' => $vignette['license_plate'],
				'vintrica_registration'  => $vignette['registration_country'],
			);

			/**
			 * Filter cart item data before adding a vignette to the cart.
			 *
			 * @param array $cart_item_data Cart item data.
			 * @param array $vignette       Sanitized vignette.
			 * @param float $price          Server-side price.
			 */
			$cart_item_data = apply_filters( 'vintrica_add_cart_item_data', $cart_item_data, $vignette, $price );

			$added = WC()->cart->add_to_cart( $product_id, 1, 0, array(), $cart_item_data );

			if ( false === $added ) {
				return new WP_Error(
					'vintrica_wc_add_failed',
					__( 'Známku sa nepodarilo pridať do košíka.', 'vintrica-vignette-form' )
				);
			}
		}

		WC()->cart->calculate_totals();

		/**
		 * Fires after vignettes are added to the WooCommerce cart.
		 *
		 * @param array $order_data Validated order data.
		 */
		do_action( 'vintrica_vignettes_added_to_cart', $order_data );

		return wc_get_checkout_url();
	}

	/**
	 * Register WooCommerce integration hooks.
	 *
	 * @return void
	 */
	private function register_hooks() {
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'validate_add_to_cart' ), 10, 6 );
		add_filter( 'woocommerce_cart_id', array( $this, 'filter_cart_id' ), 10, 5 );
		add_filter( 'woocommerce_get_cart_item_from_session', array( $this, 'restore_cart_item_from_session' ), 10, 3 );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_cart_item_prices' ), 20 );
		add_filter( 'woocommerce_cart_item_name', array( $this, 'filter_cart_item_name' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_price', array( $this, 'filter_cart_item_price' ), 10, 3 );
		add_filter( 'woocommerce_cart_item_subtotal', array( $this, 'filter_cart_item_subtotal' ), 10, 3 );
		add_filter( 'woocommerce_get_item_data', array( $this, 'display_cart_item_data' ), 10, 2 );
		add_action( 'woocommerce_checkout_create_order_line_item', array( $this, 'add_order_item_meta' ), 10, 4 );
		add_action( 'woocommerce_cart_calculate_fees', array( $this, 'add_service_fee' ) );
	}

	/**
	 * Prevent direct add-to-cart of the base vignette product without form data.
	 *
	 * @param bool  $passed         Validation result.
	 * @param int   $product_id     Product ID.
	 * @param int   $quantity       Quantity.
	 * @param int   $variation_id   Variation ID.
	 * @param array $variations     Variation attributes.
	 * @param array $cart_item_data Cart item data.
	 * @return bool
	 */
	public function validate_add_to_cart( $passed, $product_id, $quantity, $variation_id, $variations, $cart_item_data ) {
		unset( $quantity, $variation_id, $variations );

		if ( (int) $product_id !== $this->get_product_id() ) {
			return $passed;
		}

		if ( ! empty( $cart_item_data[ self::CART_ITEM_FLAG ] ) ) {
			return $passed;
		}

		wc_add_notice( __( 'Tento produkt je dostupný len cez formulár diaľničných známok.', 'vintrica-vignette-form' ), 'error' );

		return false;
	}

	/**
	 * Ensure each vignette remains a separate cart line.
	 *
	 * @param string $cart_id         Cart ID.
	 * @param int    $product_id      Product ID.
	 * @param int    $variation_id    Variation ID.
	 * @param array  $variation       Variation data.
	 * @param array  $cart_item_data  Cart item data.
	 * @return string
	 */
	public function filter_cart_id( $cart_id, $product_id, $variation_id, $variation, $cart_item_data ) {
		if ( ! empty( $cart_item_data['vintrica_unique_key'] ) ) {
			$cart_id = md5( $cart_id . $cart_item_data['vintrica_unique_key'] );
		}

		return $cart_id;
	}

	/**
	 * Restore vignette cart item data from session.
	 *
	 * @param array $cart_item Cart item.
	 * @param array $values    Session values.
	 * @param string $key      Cart item key.
	 * @return array
	 */
	public function restore_cart_item_from_session( $cart_item, $values, $key ) {
		unset( $key );

		$keys = array(
			self::CART_ITEM_FLAG,
			'vintrica_unique_key',
			'vintrica_price',
			'vintrica_country',
			'vintrica_vehicle_type',
			'vintrica_validity',
			'vintrica_start_date',
			'vintrica_license_plate',
			'vintrica_registration',
		);

		foreach ( $keys as $item_key ) {
			if ( isset( $values[ $item_key ] ) ) {
				$cart_item[ $item_key ] = $values[ $item_key ];
			}
		}

		return $cart_item;
	}

	/**
	 * Apply server-side prices to vignette cart items.
	 *
	 * @param WC_Cart $cart Cart object.
	 * @return void
	 */
	public function apply_cart_item_prices( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item[ self::CART_ITEM_FLAG ] ) || ! isset( $cart_item['data'], $cart_item['vintrica_price'] ) ) {
				continue;
			}

			$cart_item['data']->set_price( (float) $cart_item['vintrica_price'] );
		}
	}

	/**
	 * Customize vignette cart line title.
	 *
	 * @param string $name        Product name.
	 * @param array  $cart_item   Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function filter_cart_item_name( $name, $cart_item, $cart_item_key ) {
		unset( $cart_item_key );

		if ( empty( $cart_item[ self::CART_ITEM_FLAG ] ) ) {
			return $name;
		}

		return esc_html(
			$this->pricing->get_vignette_line_title(
				array(
					'country'            => $cart_item['vintrica_country'],
					'vignette_validity'  => $cart_item['vintrica_validity'],
				)
			)
		);
	}

	/**
	 * Format vignette cart item price HTML.
	 *
	 * @param string $price_html    Price HTML.
	 * @param array  $cart_item     Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function filter_cart_item_price( $price_html, $cart_item, $cart_item_key ) {
		unset( $cart_item_key );

		if ( empty( $cart_item[ self::CART_ITEM_FLAG ] ) || ! isset( $cart_item['vintrica_price'] ) ) {
			return $price_html;
		}

		return wc_price( (float) $cart_item['vintrica_price'] );
	}

	/**
	 * Format vignette cart item subtotal HTML.
	 *
	 * @param string $subtotal_html Subtotal HTML.
	 * @param array  $cart_item     Cart item.
	 * @param string $cart_item_key Cart item key.
	 * @return string
	 */
	public function filter_cart_item_subtotal( $subtotal_html, $cart_item, $cart_item_key ) {
		return $this->filter_cart_item_price( $subtotal_html, $cart_item, $cart_item_key );
	}

	/**
	 * Display vignette meta in cart and checkout.
	 *
	 * @param array $item_data Item data rows.
	 * @param array $cart_item Cart item.
	 * @return array
	 */
	public function display_cart_item_data( $item_data, $cart_item ) {
		if ( empty( $cart_item[ self::CART_ITEM_FLAG ] ) ) {
			return $item_data;
		}

		foreach ( $this->get_display_meta_rows( $cart_item ) as $row ) {
			$item_data[] = $row;
		}

		return $item_data;
	}

	/**
	 * Persist vignette meta on order line items.
	 *
	 * @param WC_Order_Item_Product $item          Order item.
	 * @param string                $cart_item_key Cart item key.
	 * @param array                 $values        Cart item values.
	 * @param WC_Order              $order         Order object.
	 * @return void
	 */
	public function add_order_item_meta( $item, $cart_item_key, $values, $order ) {
		unset( $cart_item_key, $order );

		if ( empty( $values[ self::CART_ITEM_FLAG ] ) ) {
			return;
		}

		foreach ( $this->get_display_meta_rows( $values, false ) as $row ) {
			$item->add_meta_data( $row['key'], $row['value'], true );
		}

		$item->add_meta_data( '_vintrica_vignette', 'yes', false );
	}

	/**
	 * Add a single service fee when the cart contains vignettes.
	 *
	 * @param WC_Cart $cart Cart object.
	 * @return void
	 */
	public function add_service_fee( $cart ) {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return;
		}

		if ( did_action( 'woocommerce_cart_calculate_fees' ) > 1 ) {
			return;
		}

		if ( ! $this->cart_has_vignettes( $cart ) ) {
			return;
		}

		$fee_label = __( 'Servisný poplatok', 'vintrica-vignette-form' );

		foreach ( $cart->get_fees() as $fee ) {
			if ( isset( $fee->name ) && $fee->name === $fee_label ) {
				return;
			}
		}

		$cart->add_fee( $fee_label, $this->pricing->get_service_fee(), false );
	}

	/**
	 * Build meta rows for cart, checkout, orders, and emails.
	 *
	 * @param array $item   Cart or order item values.
	 * @param bool  $for_cart Whether formatting for cart display.
	 * @return array<int, array<string, string>>
	 */
	private function get_display_meta_rows( array $item, $for_cart = true ) {
		$rows = array(
			array(
				'key'     => __( 'ŠPZ', 'vintrica-vignette-form' ),
				'value'   => isset( $item['vintrica_license_plate'] ) ? $item['vintrica_license_plate'] : '',
				'display' => '',
			),
			array(
				'key'     => __( 'Krajina registrácie', 'vintrica-vignette-form' ),
				'value'   => $this->pricing->get_country_label( $item['vintrica_registration'] ?? '' ),
				'display' => '',
			),
			array(
				'key'     => __( 'Dátum začiatku platnosti', 'vintrica-vignette-form' ),
				'value'   => isset( $item['vintrica_start_date'] ) ? $item['vintrica_start_date'] : '',
				'display' => '',
			),
			array(
				'key'     => __( 'Typ vozidla', 'vintrica-vignette-form' ),
				'value'   => $this->pricing->get_vehicle_type_label( $item['vintrica_vehicle_type'] ?? '' ),
				'display' => '',
			),
			array(
				'key'     => __( 'Krajina známky', 'vintrica-vignette-form' ),
				'value'   => $this->pricing->get_country_label( $item['vintrica_country'] ?? '' ),
				'display' => '',
			),
			array(
				'key'     => __( 'Platnosť známky', 'vintrica-vignette-form' ),
				'value'   => $this->pricing->get_validity_label(
					$item['vintrica_country'] ?? '',
					$item['vintrica_validity'] ?? ''
				),
				'display' => '',
			),
		);

		if ( ! $for_cart ) {
			return array_map(
				static function ( $row ) {
					return array(
						'key'   => $row['key'],
						'value' => $row['value'],
					);
				},
				$rows
			);
		}

		foreach ( $rows as $index => $row ) {
			$rows[ $index ]['display'] = esc_html( $row['value'] );
		}

		return $rows;
	}

	/**
	 * Determine whether a cart contains vignette items.
	 *
	 * @param WC_Cart $cart Cart object.
	 * @return bool
	 */
	private function cart_has_vignettes( $cart ) {
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item[ self::CART_ITEM_FLAG ] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Remove existing vignette items before adding a new order batch.
	 *
	 * @return void
	 */
	private function remove_existing_vignette_cart_items() {
		foreach ( WC()->cart->get_cart() as $cart_item_key => $cart_item ) {
			if ( ! empty( $cart_item[ self::CART_ITEM_FLAG ] ) ) {
				WC()->cart->remove_cart_item( $cart_item_key );
			}
		}
	}

	/**
	 * Get or create the base vignette product ID.
	 *
	 * @return int
	 */
	public function get_product_id() {
		$product_id = (int) get_option( self::PRODUCT_OPTION, 0 );

		if ( $product_id > 0 && wc_get_product( $product_id ) ) {
			return $product_id;
		}

		return $this->create_product();
	}

	/**
	 * Ensure the base product exists.
	 *
	 * @return void
	 */
	public function ensure_product_exists() {
		$this->get_product_id();
	}

	/**
	 * Create the hidden base WooCommerce product for vignette lines.
	 *
	 * @return int
	 */
	public function create_product() {
		if ( ! class_exists( 'WC_Product_Simple' ) ) {
			return 0;
		}

		$product = new WC_Product_Simple();
		$product->set_name( __( 'Diaľničná známka', 'vintrica-vignette-form' ) );
		$product->set_status( 'publish' );
		$product->set_catalog_visibility( 'hidden' );
		$product->set_virtual( true );
		$product->set_sold_individually( false );
		$product->set_price( 0 );
		$product->set_regular_price( 0 );
		$product->set_sku( 'vintrica-vignette' );

		$product_id = $product->save();

		if ( $product_id ) {
			update_option( self::PRODUCT_OPTION, $product_id );
		}

		return (int) $product_id;
	}

	/**
	 * Show an admin notice when WooCommerce is missing.
	 *
	 * @return void
	 */
	public function render_missing_woocommerce_notice() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( $this->is_woocommerce_active() ) {
			return;
		}

		printf(
			'<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
			esc_html__( 'Plugin VINTRICA Vignette Form vyžaduje WooCommerce na spracovanie objednávok známok. Nainštalujte a aktivujte WooCommerce.', 'vintrica-vignette-form' )
		);
	}

	/**
	 * Map a vignette item to WooCommerce cart item meta.
	 *
	 * @param array $vignette Sanitized vignette item.
	 * @return array<string, string>
	 */
	public function map_vignette_to_cart_meta( array $vignette ) {
		/**
		 * Filter cart item meta mapped from vignette form data.
		 *
		 * @param array<string, string> $meta     Cart item meta.
		 * @param array                 $vignette Sanitized vignette.
		 */
		return apply_filters(
			'vintrica_vignette_cart_item_meta',
			array(
				'vintrica_country'              => isset( $vignette['country'] ) ? $vignette['country'] : '',
				'vintrica_vehicle_type'         => isset( $vignette['vehicle_type'] ) ? $vignette['vehicle_type'] : '',
				'vintrica_vignette_validity'    => isset( $vignette['vignette_validity'] ) ? $vignette['vignette_validity'] : '',
				'vintrica_start_date'           => isset( $vignette['start_date'] ) ? $vignette['start_date'] : '',
				'vintrica_license_plate'        => isset( $vignette['license_plate'] ) ? $vignette['license_plate'] : '',
				'vintrica_registration_country' => isset( $vignette['registration_country'] ) ? $vignette['registration_country'] : '',
			),
			$vignette
		);
	}
}
