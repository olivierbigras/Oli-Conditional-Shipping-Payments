<?php
/**
 * Store API Integration class.
 *
 * Provides support for WooCommerce block checkout.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Oli_CSP_Store_API_Integration
 */
class Oli_CSP_Store_API_Integration {

	/**
	 * Store API extension namespace.
	 */
	const EXTENSION_NAMESPACE = 'oli-csp';

	/**
	 * Condition engine instance.
	 *
	 * @var Oli_CSP_Condition_Engine
	 */
	private $condition_engine;

	/**
	 * Whether Store API extension data was registered.
	 *
	 * @var bool
	 */
	private $store_api_registered = false;

	/**
	 * Constructor.
	 *
	 * @param Oli_CSP_Condition_Engine $condition_engine Condition engine instance.
	 */
	public function __construct( $condition_engine ) {
		$this->condition_engine = $condition_engine;

		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'validate_restrictions' ), 10, 2 );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_store_api_data' ) );
		add_action( 'woocommerce_blocks_enqueue_checkout_block_scripts_after', array( $this, 'enqueue_checkout_scripts' ) );
		add_action( 'woocommerce_blocks_enqueue_cart_block_scripts_after', array( $this, 'enqueue_checkout_scripts' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'maybe_enqueue_checkout_scripts' ) );

		if ( did_action( 'woocommerce_blocks_loaded' ) ) {
			$this->register_store_api_data();
		}
	}

	/**
	 * Register cart extension data so the checkout block can read notices.
	 */
	public function register_store_api_data() {
		if ( $this->store_api_registered || ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}

		if ( ! class_exists( '\Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema' ) ) {
			return;
		}

		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => \Automattic\WooCommerce\StoreApi\Schemas\V1\CartSchema::IDENTIFIER,
				'namespace'       => self::EXTENSION_NAMESPACE,
				'data_callback'   => array( $this, 'get_cart_extension_data' ),
				'schema_callback' => array( $this, 'get_cart_extension_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

		$this->store_api_registered = true;
	}

	/**
	 * Cart Store API extension payload. Never writes session notices.
	 *
	 * @return array
	 */
	public function get_cart_extension_data() {
		$handler = function_exists( 'oli_csp' ) ? oli_csp()->restriction_handler : null;
		$items   = ( $handler && method_exists( $handler, 'collect_notice_items' ) )
			? $handler->collect_notice_items( 'checkout' )
			: array();

		return self::format_store_api_notices( $items );
	}

	/**
	 * Schema for the cart extension payload.
	 *
	 * @return array
	 */
	public function get_cart_extension_schema() {
		return array(
			'notices' => array(
				'description' => __( 'Restriction notices for the cart and checkout blocks.', 'oli-conditional-shipping-payments' ),
				'type'        => 'array',
				'context'     => array( 'view', 'edit' ),
				'readonly'    => true,
				'items'       => array(
					'type'       => 'object',
					'properties' => array(
						'id'      => array(
							'description' => __( 'Notice identifier.', 'oli-conditional-shipping-payments' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'message' => array(
							'description' => __( 'Notice text.', 'oli-conditional-shipping-payments' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
						'area'    => array(
							'description' => __( 'Checkout area: shipping, payment, checkout, or cart.', 'oli-conditional-shipping-payments' ),
							'type'        => 'string',
							'context'     => array( 'view', 'edit' ),
							'readonly'    => true,
						),
					),
				),
			),
		);
	}

	/**
	 * Normalize notice items for the Store API (no session writes).
	 *
	 * @param array $items Items with message and optional area.
	 * @return array
	 */
	public static function format_store_api_notices( $items ) {
		$notices = array();

		foreach ( (array) $items as $item ) {
			if ( is_string( $item ) ) {
				$item = array(
					'message' => $item,
					'area'    => 'checkout',
				);
			}

			if ( ! is_array( $item ) ) {
				continue;
			}

			$message = self::plain_text( isset( $item['message'] ) ? $item['message'] : '' );
			if ( '' === $message ) {
				continue;
			}

			$area = isset( $item['area'] ) ? $item['area'] : 'checkout';
			if ( ! in_array( $area, array( 'shipping', 'payment', 'checkout', 'cart' ), true ) ) {
				$area = 'checkout';
			}

			$notices[] = array(
				'id'      => 'oli-csp-' . md5( $area . '|' . $message ),
				'message' => $message,
				'area'    => $area,
			);
		}

		return array(
			'notices' => $notices,
		);
	}

	/**
	 * Enqueue the checkout-block notice renderer when a cart/checkout block is present.
	 */
	public function maybe_enqueue_checkout_scripts() {
		if ( ! function_exists( 'has_block' ) ) {
			return;
		}

		if ( has_block( 'woocommerce/checkout' ) || has_block( 'woocommerce/cart' ) ) {
			$this->enqueue_checkout_scripts();
		}
	}

	/**
	 * Enqueue the Store API notice renderer.
	 */
	public function enqueue_checkout_scripts() {
		$handle = 'oli-csp-checkout-blocks';

		if ( function_exists( 'wp_script_is' ) && wp_script_is( $handle, 'enqueued' ) ) {
			return;
		}

		wp_enqueue_script(
			$handle,
			OLI_CSP_URL . 'assets/js/checkout-blocks.js',
			self::checkout_script_dependencies(),
			OLI_CSP_VERSION,
			true
		);

		wp_localize_script(
			$handle,
			'oliCspCheckout',
			array(
				'namespace' => self::EXTENSION_NAMESPACE,
				'contexts'  => array(
					'shipping'         => self::notice_context_for_area( 'shipping', array( 'wc/checkout/shipping-methods' ) ),
					'shippingFallback' => self::notice_context_for_area( 'shipping', array() ),
					'payment'          => self::notice_context_for_area( 'payment' ),
					'cart'             => self::notice_context_for_area( 'cart' ),
					'checkout'         => self::notice_context_for_area( 'checkout' ),
				),
			)
		);
	}

	/**
	 * Script dependencies for the checkout-block notice renderer.
	 *
	 * @param bool $include_cart_store Whether to add wc-blocks-data-store when registered.
	 * @return array
	 */
	public static function checkout_script_dependencies( $include_cart_store = true ) {
		$deps = array( 'wp-data', 'wp-notices', 'wp-a11y' );

		if ( $include_cart_store && function_exists( 'wp_script_is' ) && wp_script_is( 'wc-blocks-data-store', 'registered' ) ) {
			$deps[] = 'wc-blocks-data-store';
		}

		return $deps;
	}

	/**
	 * Map a notice area to a rendered WooCommerce block notice context.
	 *
	 * Shipping uses wc/checkout/shipping-methods. If that container is not
	 * among $available_contexts, fall back to wc/checkout. Payments stay on
	 * wc/checkout/payments.
	 *
	 * @param string     $area                shipping, payment, cart, or checkout.
	 * @param array|null $available_contexts  Rendered notice contexts, or null to assume defaults.
	 * @return string
	 */
	public static function notice_context_for_area( $area, $available_contexts = null ) {
		$area = (string) $area;

		if ( 'payment' === $area ) {
			return 'wc/checkout/payments';
		}

		if ( 'cart' === $area ) {
			return 'wc/cart';
		}

		if ( 'shipping' === $area ) {
			$preferred = 'wc/checkout/shipping-methods';
			if ( is_array( $available_contexts ) && ! in_array( $preferred, $available_contexts, true ) ) {
				return 'wc/checkout';
			}

			return $preferred;
		}

		return 'wc/checkout';
	}

	/**
	 * Strip tags without requiring WordPress in unit tests.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	private static function plain_text( $value ) {
		$value = (string) $value;

		if ( function_exists( 'wp_strip_all_tags' ) ) {
			return wp_strip_all_tags( $value );
		}

		return trim( strip_tags( $value ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions.strip_tags_strip_tags -- fallback when wp_strip_all_tags is unavailable.
	}

	/**
	 * Validate restrictions on Store API checkout.
	 *
	 * @param WC_Order        $order Order object.
	 * @param WP_REST_Request $request Request object.
	 * @throws Exception If restrictions are violated.
	 */
	public function validate_restrictions( $order, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$this->validate_shipping_methods( $order );
		$this->validate_payment_gateways( $order );
		$this->validate_destinations( $order );
	}

	/**
	 * Validate shipping method restrictions.
	 *
	 * @param WC_Order $order Order object.
	 * @throws Exception If shipping method is restricted.
	 */
	private function validate_shipping_methods( $order ) {
		$shipping_methods = $order->get_shipping_methods();
		if ( empty( $shipping_methods ) ) {
			return;
		}

		$handler          = oli_csp()->restriction_handler;
		$excluded_methods = $handler->get_excluded_shipping_methods();

		if ( empty( $excluded_methods ) ) {
			return;
		}

		foreach ( $shipping_methods as $shipping_method ) {
			$method_id   = $shipping_method->get_method_id();
			$instance_id = $shipping_method->get_instance_id();
			$full_id     = $method_id . ':' . $instance_id;

			if ( in_array( $method_id, $excluded_methods, true ) || in_array( $full_id, $excluded_methods, true ) ) {
				throw new Exception(
					esc_html__( 'The selected shipping method is not available based on your cart contents.', 'oli-conditional-shipping-payments' )
				);
			}
		}
	}

	/**
	 * Validate payment gateway restrictions.
	 *
	 * @param WC_Order $order Order object.
	 * @throws Exception If payment gateway is restricted.
	 */
	private function validate_payment_gateways( $order ) {
		$payment_method = $order->get_payment_method();
		if ( empty( $payment_method ) ) {
			return;
		}

		$handler           = oli_csp()->restriction_handler;
		$excluded_gateways = $handler->get_excluded_payment_gateways();

		if ( empty( $excluded_gateways ) ) {
			return;
		}

		if ( in_array( $payment_method, $excluded_gateways, true ) ) {
			throw new Exception(
				esc_html__( 'The selected payment method is not available based on your cart contents.', 'oli-conditional-shipping-payments' )
			);
		}
	}

	/**
	 * Validate destination restrictions.
	 *
	 * @param WC_Order $order Order object.
	 * @throws Exception If destination is restricted.
	 */
	private function validate_destinations( $order ) {
		$shipping_country = $order->get_shipping_country();
		if ( empty( $shipping_country ) ) {
			$shipping_country = $order->get_billing_country();
		}

		if ( empty( $shipping_country ) ) {
			return;
		}

		$restrictions = $this->get_destination_restrictions();
		if ( empty( $restrictions ) ) {
			return;
		}

		foreach ( $restrictions as $restriction ) {
			$matches = false;
			if ( ! empty( $restriction['condition_groups'] ) ) {
				$matches = $this->condition_engine->evaluate_condition_groups( $restriction['condition_groups'], 'checkout' );
			} elseif ( ! empty( $restriction['conditions'] ) ) {
				$matches = $this->condition_engine->evaluate_conditions( $restriction['conditions'], 'checkout' );
			} else {
				$matches = true;
			}

			if ( $matches && ! empty( $restriction['excluded_countries'] ) && in_array( $shipping_country, $restriction['excluded_countries'], true ) ) {
				$message = ! empty( $restriction['message'] ) ? $restriction['message'] : __( 'Shipping to this country is not available.', 'oli-conditional-shipping-payments' );
				throw new Exception( esc_html( $message ) );
			}
		}
	}

	/**
	 * Get destination restrictions.
	 *
	 * @return array
	 */
	private function get_destination_restrictions() {
		$restrictions = array();

		$global_restrictions = get_option( 'oli_csp_global_restrictions', array() );
		if ( ! empty( $global_restrictions['shipping_destinations'] ) ) {
			$restrictions = array_merge( $restrictions, $global_restrictions['shipping_destinations'] );
		}

		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product_id           = $cart_item['product_id'];
				$product_restrictions = get_post_meta( $product_id, '_oli_csp_restrictions', true );

				if ( ! empty( $product_restrictions['shipping_destinations'] ) ) {
					$restrictions = array_merge( $restrictions, $product_restrictions['shipping_destinations'] );
				}
			}
		}

		return $restrictions;
	}
}
