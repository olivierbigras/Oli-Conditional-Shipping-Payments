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
	 * Condition engine instance.
	 *
	 * @var Oli_CSP_Condition_Engine
	 */
	private $condition_engine;

	/**
	 * Constructor.
	 *
	 * @param Oli_CSP_Condition_Engine $condition_engine Condition engine instance.
	 */
	public function __construct( $condition_engine ) {
		$this->condition_engine = $condition_engine;

		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'validate_restrictions' ), 10, 2 );
		add_action( 'woocommerce_store_api_cart_update_customer_from_request', array( $this, 'add_checkout_notices' ), 20, 2 );
	}

	/**
	 * Surface matching restriction notices on the checkout block.
	 *
	 * @param WC_Cart|mixed   $cart    Cart object.
	 * @param WP_REST_Request $request Request object.
	 */
	public function add_checkout_notices( $cart, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		$handler = oli_csp()->restriction_handler;
		if ( $handler ) {
			$handler->maybe_add_runtime_notices();
		}
	}

	/**
	 * Validate restrictions on Store API checkout.
	 *
	 * @param WC_Order        $order Order object.
	 * @param WP_REST_Request $request Request object.
	 * @throws Exception If restrictions are violated.
	 */
	public function validate_restrictions( $order, $request ) { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.FoundAfterLastUsed
		// Validate shipping method restrictions.
		$this->validate_shipping_methods( $order );

		// Validate payment gateway restrictions.
		$this->validate_payment_gateways( $order );

		// Validate destination restrictions.
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

		// Global restrictions.
		$global_restrictions = get_option( 'oli_csp_global_restrictions', array() );
		if ( ! empty( $global_restrictions['shipping_destinations'] ) ) {
			$restrictions = array_merge( $restrictions, $global_restrictions['shipping_destinations'] );
		}

		// Product-specific restrictions.
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
