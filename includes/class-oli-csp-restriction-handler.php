<?php
/**
 * Restriction Handler class.
 *
 * Applies restrictions to shipping methods, payment gateways, and destinations.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Oli_CSP_Restriction_Handler
 */
class Oli_CSP_Restriction_Handler {

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

		// Classic checkout hooks.
		add_filter( 'woocommerce_package_rates', array( $this, 'filter_shipping_methods' ), 100, 2 );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_payment_gateways' ), 100 );
		add_filter( 'woocommerce_countries_allowed_countries', array( $this, 'filter_allowed_countries' ), 100 );
		add_filter( 'woocommerce_countries_shipping_countries', array( $this, 'filter_shipping_countries' ), 100 );

		// Notice hooks.
		add_action( 'woocommerce_before_cart', array( $this, 'show_cart_notice' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'show_checkout_notice' ) );
	}

	/**
	 * Filter shipping methods based on restrictions.
	 *
	 * @param array $rates Available rates.
	 * @param array $package Package data.
	 * @return array Filtered rates.
	 */
	public function filter_shipping_methods( $rates, $package ) {
		if ( empty( $rates ) ) {
			return $rates;
		}

		$restrictions = $this->get_applicable_restrictions( 'shipping_methods' );
		if ( empty( $restrictions ) ) {
			return $rates;
		}

		$excluded_methods = array();

		foreach ( $restrictions as $restriction ) {
			if ( empty( $restriction['conditions'] ) || $this->condition_engine->evaluate_conditions( $restriction['conditions'], 'checkout' ) ) {
				if ( ! empty( $restriction['excluded_methods'] ) ) {
					$excluded_methods = array_merge( $excluded_methods, $restriction['excluded_methods'] );
				}
			}
		}

		if ( empty( $excluded_methods ) ) {
			return $rates;
		}

		$settings = get_option( 'oli_csp_settings', array() );
		$show_disabled = isset( $settings['show_excluded_as_disabled'] ) && 'yes' === $settings['show_excluded_as_disabled'];

		foreach ( $rates as $rate_id => $rate ) {
			$method_id = $rate->get_method_id();

			// Check if method or instance is excluded.
			if ( in_array( $method_id, $excluded_methods, true ) || in_array( $rate_id, $excluded_methods, true ) ) {
				if ( $show_disabled ) {
					$rates[ $rate_id ]->label .= ' ' . __( '(Not available)', 'oli-conditional-shipping-payments' );
					$rates[ $rate_id ]->cost  = '';
				} else {
					unset( $rates[ $rate_id ] );
				}
			}
		}

		return $rates;
	}

	/**
	 * Filter payment gateways based on restrictions.
	 *
	 * @param array $gateways Available gateways.
	 * @return array Filtered gateways.
	 */
	public function filter_payment_gateways( $gateways ) {
		if ( empty( $gateways ) ) {
			return $gateways;
		}

		$restrictions = $this->get_applicable_restrictions( 'payment_gateways' );
		if ( empty( $restrictions ) ) {
			return $gateways;
		}

		$excluded_gateways = array();

		foreach ( $restrictions as $restriction ) {
			if ( empty( $restriction['conditions'] ) || $this->condition_engine->evaluate_conditions( $restriction['conditions'], 'checkout' ) ) {
				if ( ! empty( $restriction['excluded_gateways'] ) ) {
					$excluded_gateways = array_merge( $excluded_gateways, $restriction['excluded_gateways'] );
				}
			}
		}

		if ( empty( $excluded_gateways ) ) {
			return $gateways;
		}

		foreach ( $excluded_gateways as $gateway_id ) {
			unset( $gateways[ $gateway_id ] );
		}

		return $gateways;
	}

	/**
	 * Filter allowed countries based on restrictions.
	 *
	 * @param array $countries Allowed countries.
	 * @return array Filtered countries.
	 */
	public function filter_allowed_countries( $countries ) {
		$restrictions = $this->get_applicable_restrictions( 'shipping_destinations' );
		if ( empty( $restrictions ) ) {
			return $countries;
		}

		$excluded_countries = array();

		foreach ( $restrictions as $restriction ) {
			if ( empty( $restriction['conditions'] ) || $this->condition_engine->evaluate_conditions( $restriction['conditions'], 'checkout' ) ) {
				if ( ! empty( $restriction['excluded_countries'] ) ) {
					$excluded_countries = array_merge( $excluded_countries, $restriction['excluded_countries'] );
				}
			}
		}

		if ( empty( $excluded_countries ) ) {
			return $countries;
		}

		foreach ( $excluded_countries as $country_code ) {
			unset( $countries[ $country_code ] );
		}

		return $countries;
	}

	/**
	 * Filter shipping countries based on restrictions.
	 *
	 * @param array $countries Shipping countries.
	 * @return array Filtered countries.
	 */
	public function filter_shipping_countries( $countries ) {
		return $this->filter_allowed_countries( $countries );
	}

	/**
	 * Get applicable restrictions for a type.
	 *
	 * @param string $type Restriction type.
	 * @return array Applicable restrictions.
	 */
	private function get_applicable_restrictions( $type ) {
		$restrictions = array();

		// Global restrictions.
		$global_restrictions = get_option( 'oli_csp_global_restrictions', array() );
		if ( ! empty( $global_restrictions[ $type ] ) ) {
			$restrictions = array_merge( $restrictions, $global_restrictions[ $type ] );
		}

		// Product-specific restrictions.
		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product_id = $cart_item['product_id'];
				$product_restrictions = get_post_meta( $product_id, '_oli_csp_restrictions', true );

				if ( ! empty( $product_restrictions[ $type ] ) ) {
					$restrictions = array_merge( $restrictions, $product_restrictions[ $type ] );
				}
			}
		}

		return $restrictions;
	}

	/**
	 * Show notice on cart page if restrictions apply.
	 */
	public function show_cart_notice() {
		if ( $this->should_show_notice() ) {
			$settings = get_option( 'oli_csp_settings', array() );
			$message  = isset( $settings['restriction_notice_cart'] ) ? $settings['restriction_notice_cart'] : '';

			if ( ! empty( $message ) ) {
				wc_print_notice( esc_html( $message ), 'notice' );
			}
		}
	}

	/**
	 * Show notice on checkout page if restrictions apply.
	 */
	public function show_checkout_notice() {
		if ( $this->should_show_notice() ) {
			$settings = get_option( 'oli_csp_settings', array() );
			$message  = isset( $settings['restriction_notice_checkout'] ) ? $settings['restriction_notice_checkout'] : '';

			if ( ! empty( $message ) ) {
				wc_print_notice( esc_html( $message ), 'notice' );
			}
		}
	}

	/**
	 * Check if notice should be shown.
	 *
	 * @return bool
	 */
	private function should_show_notice() {
		$types = array( 'shipping_methods', 'payment_gateways', 'shipping_destinations' );

		foreach ( $types as $type ) {
			$restrictions = $this->get_applicable_restrictions( $type );
			if ( ! empty( $restrictions ) ) {
				foreach ( $restrictions as $restriction ) {
					if ( empty( $restriction['conditions'] ) || $this->condition_engine->evaluate_conditions( $restriction['conditions'], 'checkout' ) ) {
						return true;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Get excluded shipping methods for Store API.
	 *
	 * @return array
	 */
	public function get_excluded_shipping_methods() {
		$restrictions = $this->get_applicable_restrictions( 'shipping_methods' );
		if ( empty( $restrictions ) ) {
			return array();
		}

		$excluded_methods = array();

		foreach ( $restrictions as $restriction ) {
			if ( empty( $restriction['conditions'] ) || $this->condition_engine->evaluate_conditions( $restriction['conditions'], 'checkout' ) ) {
				if ( ! empty( $restriction['excluded_methods'] ) ) {
					$excluded_methods = array_merge( $excluded_methods, $restriction['excluded_methods'] );
				}
			}
		}

		return array_unique( $excluded_methods );
	}

	/**
	 * Get excluded payment gateways for Store API.
	 *
	 * @return array
	 */
	public function get_excluded_payment_gateways() {
		$restrictions = $this->get_applicable_restrictions( 'payment_gateways' );
		if ( empty( $restrictions ) ) {
			return array();
		}

		$excluded_gateways = array();

		foreach ( $restrictions as $restriction ) {
			if ( empty( $restriction['conditions'] ) || $this->condition_engine->evaluate_conditions( $restriction['conditions'], 'checkout' ) ) {
				if ( ! empty( $restriction['excluded_gateways'] ) ) {
					$excluded_gateways = array_merge( $excluded_gateways, $restriction['excluded_gateways'] );
				}
			}
		}

		return array_unique( $excluded_gateways );
	}
}
