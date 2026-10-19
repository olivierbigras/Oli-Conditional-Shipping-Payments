<?php
/**
 * Condition Engine class.
 *
 * Evaluates conditions for restrictions.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Oli_CSP_Condition_Engine
 */
class Oli_CSP_Condition_Engine {

	/**
	 * Evaluate condition groups (OR logic between groups, AND logic within groups).
	 *
	 * @param array  $condition_groups Array of condition groups.
	 * @param string $context Context: 'cart' or 'checkout'.
	 * @return bool True if any group matches (OR logic).
	 */
	public function evaluate_condition_groups( $condition_groups, $context = 'checkout' ) {
		if ( empty( $condition_groups ) || ! is_array( $condition_groups ) ) {
			return true;
		}

		// OR logic: at least one group must match.
		foreach ( $condition_groups as $group ) {
			if ( $this->evaluate_condition_group( $group, $context ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Evaluate a single condition group (AND logic within group).
	 *
	 * @param array  $conditions Array of conditions in the group.
	 * @param string $context Context: 'cart' or 'checkout'.
	 * @return bool True if all conditions in group match (AND logic).
	 */
	public function evaluate_condition_group( $conditions, $context = 'checkout' ) {
		if ( empty( $conditions ) || ! is_array( $conditions ) ) {
			return true;
		}

		// AND logic: all conditions must match.
		foreach ( $conditions as $condition ) {
			if ( ! $this->evaluate_single_condition( $condition, $context ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Legacy method for backward compatibility (AND logic only).
	 *
	 * @param array  $conditions Array of conditions.
	 * @param string $context Context: 'cart' or 'checkout'.
	 * @return bool True if all conditions match.
	 */
	public function evaluate_conditions( $conditions, $context = 'checkout' ) {
		return $this->evaluate_condition_group( $conditions, $context );
	}

	/**
	 * Evaluate a single condition.
	 *
	 * @param array  $condition Condition configuration.
	 * @param string $context Context: 'cart' or 'checkout'.
	 * @return bool True if condition matches.
	 */
	private function evaluate_single_condition( $condition, $context ) {
		if ( empty( $condition['type'] ) ) {
			return true;
		}

		$type = $condition['type'];

		switch ( $type ) {
			case 'cart_total':
				return $this->check_cart_total( $condition );
			case 'cart_subtotal':
				return $this->check_cart_subtotal( $condition );
			case 'order_total':
				return $this->check_order_total( $condition );
			case 'product_in_cart':
				return $this->check_product_in_cart( $condition );
			case 'category_in_cart':
				return $this->check_category_in_cart( $condition );
			case 'tag_in_cart':
				return $this->check_tag_in_cart( $condition );
			case 'shipping_class_in_cart':
				return $this->check_shipping_class_in_cart( $condition );
			case 'package_weight':
				return $this->check_package_weight( $condition );
			case 'item_quantity':
				return $this->check_item_quantity( $condition );
			case 'billing_country':
				return $this->check_billing_country( $condition );
			case 'billing_state':
				return $this->check_billing_state( $condition );
			case 'billing_postcode':
				return $this->check_billing_postcode( $condition );
			case 'shipping_country':
				return $this->check_shipping_country( $condition );
			case 'shipping_state':
				return $this->check_shipping_state( $condition );
			case 'shipping_postcode':
				return $this->check_shipping_postcode( $condition );
			case 'customer_role':
				return $this->check_customer_role( $condition );
			case 'user_logged_in':
				return $this->check_user_logged_in( $condition );
			case 'coupon_used':
				return $this->check_coupon_used( $condition );
			case 'backorder_in_cart':
				return $this->check_backorder_in_cart( $condition );
			case 'currency':
				return $this->check_currency( $condition );
			case 'shipping_method':
				return $this->check_shipping_method_selected( $condition );
			case 'payment_gateway':
				return $this->check_payment_gateway_selected( $condition );
			default:
				return apply_filters( 'oli_csp_evaluate_condition', true, $condition, $context );
		}
	}

	/**
	 * Check cart total condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_cart_total( $condition ) {
		if ( ! WC()->cart ) {
			return false;
		}

		$cart_total = (float) WC()->cart->get_cart_contents_total() + (float) WC()->cart->get_shipping_total();
		return $this->compare_numeric_value( $cart_total, $condition );
	}

	/**
	 * Check cart subtotal condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_cart_subtotal( $condition ) {
		if ( ! WC()->cart ) {
			return false;
		}

		$cart_subtotal = (float) WC()->cart->get_subtotal();
		return $this->compare_numeric_value( $cart_subtotal, $condition );
	}

	/**
	 * Check order total condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_order_total( $condition ) {
		if ( ! WC()->cart ) {
			return false;
		}

		$order_total = (float) WC()->cart->get_total( 'edit' );
		return $this->compare_numeric_value( $order_total, $condition );
	}

	/**
	 * Check if specific product is in cart.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_product_in_cart( $condition ) {
		if ( ! WC()->cart || empty( $condition['value'] ) ) {
			return false;
		}

		$product_ids = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );
		$operator    = isset( $condition['operator'] ) ? $condition['operator'] : 'in';

		$found = false;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( in_array( (string) $cart_item['product_id'], $product_ids, true ) ||
				in_array( (string) $cart_item['variation_id'], $product_ids, true ) ) {
				$found = true;
				break;
			}
		}

		// Apply operator.
		switch ( $operator ) {
			case 'in':
			case 'is':
				return $found;
			case 'not_in':
			case 'is_not':
				return ! $found;
			default:
				return $found;
		}
	}

	/**
	 * Check if product from category is in cart.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_category_in_cart( $condition ) {
		if ( ! WC()->cart || empty( $condition['value'] ) ) {
			return false;
		}

		$category_ids = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );
		$operator     = isset( $condition['operator'] ) ? $condition['operator'] : 'in';

		$found = false;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = $cart_item['product_id'];
			$terms      = get_the_terms( $product_id, 'product_cat' );

			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( in_array( (string) $term->term_id, $category_ids, true ) ) {
						$found = true;
						break 2;
					}
				}
			}
		}

		// Apply operator.
		switch ( $operator ) {
			case 'in':
			case 'is':
				return $found;
			case 'not_in':
			case 'is_not':
				return ! $found;
			default:
				return $found;
		}
	}

	/**
	 * Check if product with tag is in cart.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_tag_in_cart( $condition ) {
		if ( ! WC()->cart || empty( $condition['value'] ) ) {
			return false;
		}

		$tag_ids  = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );
		$operator = isset( $condition['operator'] ) ? $condition['operator'] : 'in';

		$found = false;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product_id = $cart_item['product_id'];
			$terms      = get_the_terms( $product_id, 'product_tag' );

			if ( $terms && ! is_wp_error( $terms ) ) {
				foreach ( $terms as $term ) {
					if ( in_array( (string) $term->term_id, $tag_ids, true ) ) {
						$found = true;
						break 2;
					}
				}
			}
		}

		// Apply operator.
		switch ( $operator ) {
			case 'in':
			case 'is':
				return $found;
			case 'not_in':
			case 'is_not':
				return ! $found;
			default:
				return $found;
		}
	}

	/**
	 * Check if product with shipping class is in cart.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_shipping_class_in_cart( $condition ) {
		if ( ! WC()->cart || empty( $condition['value'] ) ) {
			return false;
		}

		$shipping_class_ids = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );
		$operator           = isset( $condition['operator'] ) ? $condition['operator'] : 'in';

		$found = false;
		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product           = $cart_item['data'];
			$shipping_class_id = $product->get_shipping_class_id();

			if ( in_array( (string) $shipping_class_id, $shipping_class_ids, true ) ) {
				$found = true;
				break;
			}
		}

		// Apply operator.
		switch ( $operator ) {
			case 'in':
			case 'is':
				return $found;
			case 'not_in':
			case 'is_not':
				return ! $found;
			default:
				return $found;
		}
	}

	/**
	 * Check package weight condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_package_weight( $condition ) {
		if ( ! WC()->cart ) {
			return false;
		}

		$cart_weight = (float) WC()->cart->get_cart_contents_weight();
		return $this->compare_numeric_value( $cart_weight, $condition );
	}

	/**
	 * Check item quantity condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_item_quantity( $condition ) {
		if ( ! WC()->cart ) {
			return false;
		}

		$cart_quantity = WC()->cart->get_cart_contents_count();
		return $this->compare_numeric_value( $cart_quantity, $condition );
	}

	/**
	 * Check billing country condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_billing_country( $condition ) {
		if ( empty( $condition['value'] ) ) {
			return false;
		}

		$customer = WC()->customer;
		if ( ! $customer ) {
			return false;
		}

		$billing_country = $customer->get_billing_country();
		$countries       = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );

		return in_array( $billing_country, $countries, true );
	}

	/**
	 * Check billing state condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_billing_state( $condition ) {
		if ( empty( $condition['value'] ) ) {
			return false;
		}

		$customer = WC()->customer;
		if ( ! $customer ) {
			return false;
		}

		$billing_state = $customer->get_billing_state();
		$states        = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );

		return in_array( $billing_state, $states, true );
	}

	/**
	 * Check billing postcode condition (supports wildcards and ranges).
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_billing_postcode( $condition ) {
		if ( empty( $condition['value'] ) ) {
			return false;
		}

		$customer = WC()->customer;
		if ( ! $customer ) {
			return false;
		}

		$billing_postcode = $customer->get_billing_postcode();
		$patterns         = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );

		return $this->match_postcode( $billing_postcode, $patterns );
	}

	/**
	 * Check shipping country condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_shipping_country( $condition ) {
		if ( empty( $condition['value'] ) ) {
			return false;
		}

		$customer = WC()->customer;
		if ( ! $customer ) {
			return false;
		}

		$shipping_country = $customer->get_shipping_country();
		$countries        = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );

		return in_array( $shipping_country, $countries, true );
	}

	/**
	 * Check shipping state condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_shipping_state( $condition ) {
		if ( empty( $condition['value'] ) ) {
			return false;
		}

		$customer = WC()->customer;
		if ( ! $customer ) {
			return false;
		}

		$shipping_state = $customer->get_shipping_state();
		$states         = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );

		return in_array( $shipping_state, $states, true );
	}

	/**
	 * Check shipping postcode condition (supports wildcards and ranges).
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_shipping_postcode( $condition ) {
		if ( empty( $condition['value'] ) ) {
			return false;
		}

		$customer = WC()->customer;
		if ( ! $customer ) {
			return false;
		}

		$shipping_postcode = $customer->get_shipping_postcode();
		$patterns          = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );

		return $this->match_postcode( $shipping_postcode, $patterns );
	}

	/**
	 * Check customer role condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_customer_role( $condition ) {
		if ( empty( $condition['value'] ) ) {
			return false;
		}

		$user = wp_get_current_user();
		if ( ! $user->ID ) {
			return false;
		}

		$roles      = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );
		$user_roles = (array) $user->roles;

		return ! empty( array_intersect( $user_roles, $roles ) );
	}

	/**
	 * Check user logged in condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_user_logged_in( $condition ) {
		$is_logged_in = is_user_logged_in();
		$value        = isset( $condition['value'] ) ? $condition['value'] : 'yes';

		return ( 'yes' === $value ) === $is_logged_in;
	}

	/**
	 * Check coupon used condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_coupon_used( $condition ) {
		if ( ! WC()->cart || empty( $condition['value'] ) ) {
			return false;
		}

		$applied_coupons = WC()->cart->get_applied_coupons();
		$coupon_codes    = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );

		return ! empty( array_intersect( $applied_coupons, $coupon_codes ) );
	}

	/**
	 * Check backorder in cart condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_backorder_in_cart( $condition ) {
		if ( ! WC()->cart ) {
			return false;
		}

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			$product = $cart_item['data'];
			if ( $product->is_on_backorder( $cart_item['quantity'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Check currency condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_currency( $condition ) {
		if ( empty( $condition['value'] ) ) {
			return false;
		}

		$current_currency = get_woocommerce_currency();
		$currencies       = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );

		return in_array( $current_currency, $currencies, true );
	}

	/**
	 * Check shipping method selected condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_shipping_method_selected( $condition ) {
		if ( empty( $condition['value'] ) ) {
			return false;
		}

		$chosen_methods = WC()->session ? WC()->session->get( 'chosen_shipping_methods', array() ) : array();
		$methods        = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );

		foreach ( $chosen_methods as $chosen_method ) {
			foreach ( $methods as $method ) {
				if ( strpos( $chosen_method, $method ) === 0 ) {
					return true;
				}
			}
		}

		return false;
	}

	/**
	 * Check payment gateway selected condition.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_payment_gateway_selected( $condition ) {
		if ( empty( $condition['value'] ) ) {
			return false;
		}

		$chosen_gateway = WC()->session ? WC()->session->get( 'chosen_payment_method' ) : '';
		$gateways       = is_array( $condition['value'] ) ? $condition['value'] : array( $condition['value'] );

		return in_array( $chosen_gateway, $gateways, true );
	}

	/**
	 * Compare numeric value with condition operator.
	 *
	 * @param float $value Current value.
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function compare_numeric_value( $value, $condition ) {
		$compare_value = isset( $condition['value'] ) ? (float) $condition['value'] : 0;
		$operator      = isset( $condition['operator'] ) ? $condition['operator'] : '>=';

		switch ( $operator ) {
			case '>':
			case 'greater':
				return $value > $compare_value;
			case '>=':
			case 'gte':
				return $value >= $compare_value;
			case '<':
			case 'less':
				return $value < $compare_value;
			case '<=':
			case 'lte':
				return $value <= $compare_value;
			case '==':
			case 'eq':
			case 'is':
				return abs( $value - $compare_value ) < 0.01;
			case '!=':
			case 'neq':
			case 'is_not':
				return abs( $value - $compare_value ) >= 0.01;
			default:
				return false;
		}
	}

	/**
	 * Compare value with list operator (in/not in).
	 *
	 * @param mixed $value Current value.
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function compare_list_value( $value, $condition ) {
		$compare_values = isset( $condition['value'] ) ? (array) $condition['value'] : array();
		$operator       = isset( $condition['operator'] ) ? $condition['operator'] : 'in';

		$is_in_list = in_array( $value, $compare_values, true );

		switch ( $operator ) {
			case 'in':
			case 'is':
				return $is_in_list;
			case 'not_in':
			case 'is_not':
				return ! $is_in_list;
			default:
				return false;
		}
	}

	/**
	 * Compare string value with operator (contains/not contains).
	 *
	 * @param string $value Current value.
	 * @param array  $condition Condition configuration.
	 * @return bool
	 */
	private function compare_string_value( $value, $condition ) {
		$compare_value = isset( $condition['value'] ) ? (string) $condition['value'] : '';
		$operator      = isset( $condition['operator'] ) ? $condition['operator'] : 'contains';

		switch ( $operator ) {
			case 'contains':
				return false !== stripos( $value, $compare_value );
			case 'not_contains':
				return false === stripos( $value, $compare_value );
			case 'is':
			case '==':
				return strtolower( $value ) === strtolower( $compare_value );
			case 'is_not':
			case '!=':
				return strtolower( $value ) !== strtolower( $compare_value );
			default:
				return false;
		}
	}

	/**
	 * Match postcode against patterns (supports wildcards * and ranges).
	 *
	 * @param string $postcode Postcode to check.
	 * @param array  $patterns Patterns to match against.
	 * @return bool
	 */
	private function match_postcode( $postcode, $patterns ) {
		$postcode = strtoupper( str_replace( ' ', '', $postcode ) );

		foreach ( $patterns as $pattern ) {
			$pattern = strtoupper( str_replace( ' ', '', $pattern ) );

			// Check for range (e.g., "90210-90220").
			if ( strpos( $pattern, '-' ) !== false ) {
				list( $start, $end ) = explode( '-', $pattern, 2 );
				if ( $postcode >= $start && $postcode <= $end ) {
					return true;
				}
			}

			// Check for wildcard (e.g., "902*").
			if ( strpos( $pattern, '*' ) !== false ) {
				$regex = '/^' . str_replace( '*', '.*', preg_quote( $pattern, '/' ) ) . '$/';
				if ( preg_match( $regex, $postcode ) ) {
					return true;
				}
			}

			// Exact match.
			if ( $postcode === $pattern ) {
				return true;
			}
		}

		return false;
	}
}
