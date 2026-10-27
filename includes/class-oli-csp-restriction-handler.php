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

		add_filter( 'woocommerce_package_rates', array( $this, 'filter_shipping_methods' ), 100, 2 );
		add_filter( 'woocommerce_available_payment_gateways', array( $this, 'filter_payment_gateways' ), 100 );
		add_filter( 'woocommerce_countries_allowed_countries', array( $this, 'filter_allowed_countries' ), 100 );
		add_filter( 'woocommerce_countries_shipping_countries', array( $this, 'filter_shipping_countries' ), 100 );

		add_action( 'woocommerce_before_cart', array( $this, 'render_cart_notice_container' ), 15 );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'render_checkout_notice_container' ), 15 );
		add_filter( 'woocommerce_update_order_review_fragments', array( $this, 'add_checkout_notice_fragment' ) );
	}

	/**
	 * Filter shipping methods based on restrictions.
	 *
	 * @param array $rates   Available rates.
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
			if ( ! $this->restriction_matches( $restriction ) ) {
				continue;
			}

			if ( ! empty( $restriction['zones'] ) ) {
				$current_zone_id = (int) $this->get_package_zone_id( $package );
				$zone_ids        = array_map( 'absint', (array) $restriction['zones'] );
				if ( ! in_array( $current_zone_id, $zone_ids, true ) ) {
					continue;
				}
			}

			if ( ! empty( $restriction['excluded_methods'] ) ) {
				$excluded_methods = array_merge( $excluded_methods, $restriction['excluded_methods'] );
			}
		}

		if ( empty( $excluded_methods ) ) {
			return $rates;
		}

		$settings      = get_option( 'oli_csp_settings', array() );
		$show_disabled = isset( $settings['show_excluded_as_disabled'] ) && 'yes' === $settings['show_excluded_as_disabled'];

		foreach ( $rates as $rate_id => $rate ) {
			$method_id   = $rate->get_method_id();
			$instance_id = $rate->get_instance_id();

			$is_excluded = in_array( $method_id, $excluded_methods, true ) ||
							in_array( $rate_id, $excluded_methods, true ) ||
							in_array( $method_id . ':' . $instance_id, $excluded_methods, true );

			if ( $is_excluded ) {
				if ( $show_disabled ) {
					$rates[ $rate_id ]->label .= ' ' . __( '(Not available)', 'oli-conditional-shipping-payments' );
					$rates[ $rate_id ]->cost   = '';
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
			if ( $this->restriction_matches( $restriction ) && ! empty( $restriction['excluded_gateways'] ) ) {
				$excluded_gateways = array_merge( $excluded_gateways, $restriction['excluded_gateways'] );
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
			if ( $this->restriction_matches( $restriction ) && ! empty( $restriction['excluded_countries'] ) ) {
				$excluded_countries = array_merge( $excluded_countries, $restriction['excluded_countries'] );
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

		$global_restrictions = get_option( 'oli_csp_global_restrictions', array() );
		if ( ! empty( $global_restrictions[ $type ] ) && is_array( $global_restrictions[ $type ] ) ) {
			$restrictions = array_merge( $restrictions, $global_restrictions[ $type ] );
		}

		if ( WC()->cart ) {
			foreach ( WC()->cart->get_cart() as $cart_item ) {
				$product_id           = $cart_item['product_id'];
				$product_restrictions = get_post_meta( $product_id, '_oli_csp_restrictions', true );

				if ( ! empty( $product_restrictions[ $type ] ) && is_array( $product_restrictions[ $type ] ) ) {
					$restrictions = array_merge( $restrictions, $product_restrictions[ $type ] );
				}
			}
		}

		return array_values(
			array_filter(
				$restrictions,
				array( 'Oli_CSP_Utils', 'is_restriction_enabled' )
			)
		);
	}

	/**
	 * Render the replaceable cart notice container (classic cart).
	 */
	public function render_cart_notice_container() {
		echo wp_kses( $this->get_notice_container_html( 'cart' ), self::notice_allowed_html() );
	}

	/**
	 * Render the replaceable checkout notice container (classic checkout).
	 */
	public function render_checkout_notice_container() {
		echo wp_kses( $this->get_notice_container_html( 'checkout' ), self::notice_allowed_html() );
	}

	/**
	 * Replace classic checkout notices during update_order_review.
	 *
	 * @param array $fragments Checkout fragments.
	 * @return array
	 */
	public function add_checkout_notice_fragment( $fragments ) {
		if ( ! is_array( $fragments ) ) {
			$fragments = array();
		}

		$fragments['.oli-csp-checkout-notices'] = $this->get_notice_container_html( 'checkout' );

		return $fragments;
	}

	/**
	 * HTML for the classic notice container. Never writes WooCommerce session notices.
	 *
	 * @param string     $context  cart or checkout.
	 * @param array|null $messages Optional pre-collected messages.
	 * @return string
	 */
	public function get_notice_container_html( $context = 'checkout', $messages = null ) {
		if ( null === $messages ) {
			$messages = $this->collect_notice_messages( $context );
		}

		return self::build_notice_container_html( $messages, $context );
	}

	/**
	 * Build a replaceable notice container. Safe to call without WooCommerce.
	 *
	 * @param array  $messages Notice strings.
	 * @param string $context  cart or checkout.
	 * @return string
	 */
	public static function build_notice_container_html( $messages, $context = 'checkout' ) {
		$class = ( 'cart' === $context ) ? 'oli-csp-cart-notices' : 'oli-csp-checkout-notices';
		$html  = '<div class="' . self::escape_html_value( $class ) . '">';
		$seen  = array();

		foreach ( (array) $messages as $message ) {
			$message = (string) $message;
			if ( '' === $message || isset( $seen[ $message ] ) ) {
				continue;
			}

			$seen[ $message ] = true;
			$html            .= '<div class="woocommerce-info" role="status">' . self::escape_html_value( $message ) . '</div>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Tags and attributes allowed in the notice container output.
	 *
	 * @return array
	 */
	public static function notice_allowed_html() {
		return array(
			'div' => array(
				'class' => true,
				'role'  => true,
			),
		);
	}

	/**
	 * Escape HTML when WordPress helpers are available.
	 *
	 * @param string $value Raw value.
	 * @return string
	 */
	private static function escape_html_value( $value ) {
		if ( function_exists( 'esc_html' ) ) {
			return esc_html( $value );
		}

		return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
	}

	/**
	 * Restriction notices are never stored in the WooCommerce session.
	 *
	 * @return bool
	 */
	public static function persist_notices_in_session() {
		return false;
	}

	/**
	 * Collect unique notice messages from matching rules.
	 *
	 * @param string $context cart or checkout.
	 * @return array
	 */
	public function collect_notice_messages( $context = 'checkout' ) {
		$messages = array();

		foreach ( $this->collect_notice_items( $context ) as $item ) {
			if ( ! empty( $item['message'] ) ) {
				$messages[] = (string) $item['message'];
			}
		}

		$messages = array_values( array_unique( $messages ) );

		/**
		 * Filter checkout/cart restriction notices.
		 *
		 * @param array  $messages Notice strings.
		 * @param string $context  cart or checkout.
		 */
		return apply_filters( 'oli_csp_notice_messages', $messages, $context );
	}

	/**
	 * Collect notice items with a display area for the checkout block.
	 *
	 * @param string $context cart or checkout.
	 * @return array
	 */
	public function collect_notice_items( $context = 'checkout' ) {
		$items   = array();
		$matched = false;
		$types   = array(
			'shipping_methods'      => 'shipping',
			'payment_gateways'      => 'payment',
			'shipping_destinations' => 'shipping',
			'checkout_notices'      => 'checkout',
		);

		foreach ( $types as $type => $area ) {
			foreach ( $this->get_applicable_restrictions( $type ) as $restriction ) {
				if ( ! $this->restriction_matches( $restriction ) ) {
					continue;
				}

				$matched = true;
				$message = '';

				if ( ! empty( $restriction['notice'] ) ) {
					$message = (string) $restriction['notice'];
				} elseif ( ! empty( $restriction['message'] ) ) {
					$message = (string) $restriction['message'];
				}

				if ( '' !== $message ) {
					$items[] = array(
						'message' => $message,
						'area'    => $area,
					);
				}
			}
		}

		if ( empty( $items ) && $matched ) {
			$settings = get_option( 'oli_csp_settings', array() );
			$key      = ( 'cart' === $context ) ? 'restriction_notice_cart' : 'restriction_notice_checkout';
			$fallback = isset( $settings[ $key ] ) ? (string) $settings[ $key ] : '';

			if ( '' !== $fallback ) {
				$items[] = array(
					'message' => $fallback,
					'area'    => ( 'cart' === $context ) ? 'cart' : 'checkout',
				);
			}
		}

		$unique = array();
		foreach ( $items as $item ) {
			$key = $item['area'] . '|' . $item['message'];
			if ( ! isset( $unique[ $key ] ) ) {
				$unique[ $key ] = $item;
			}
		}

		$items = array_values( $unique );

		/**
		 * Filter structured checkout/cart restriction notices.
		 *
		 * @param array  $items   Notice items with message and area.
		 * @param string $context cart or checkout.
		 */
		return apply_filters( 'oli_csp_notice_items', $items, $context );
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
			if ( $this->restriction_matches( $restriction ) && ! empty( $restriction['excluded_methods'] ) ) {
				$excluded_methods = array_merge( $excluded_methods, $restriction['excluded_methods'] );
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
			if ( $this->restriction_matches( $restriction ) && ! empty( $restriction['excluded_gateways'] ) ) {
				$excluded_gateways = array_merge( $excluded_gateways, $restriction['excluded_gateways'] );
			}
		}

		return array_unique( $excluded_gateways );
	}

	/**
	 * Get package zone ID.
	 *
	 * @param array $package Package data.
	 * @return int Zone ID.
	 */
	private function get_package_zone_id( $package ) {
		$zone = wc_get_shipping_zone( $package );
		return $zone ? $zone->get_id() : 0;
	}

	/**
	 * Whether a restriction's conditions match (grouped OR, flat AND, or always).
	 *
	 * @param array $restriction Restriction data.
	 * @return bool
	 */
	public function restriction_matches( $restriction ) {
		if ( ! Oli_CSP_Utils::is_restriction_enabled( $restriction ) ) {
			return false;
		}

		if ( ! empty( $restriction['condition_groups'] ) ) {
			return $this->condition_engine->evaluate_condition_groups( $restriction['condition_groups'], 'checkout' );
		}

		if ( ! empty( $restriction['conditions'] ) ) {
			return $this->condition_engine->evaluate_conditions( $restriction['conditions'], 'checkout' );
		}

		return true;
	}
}
