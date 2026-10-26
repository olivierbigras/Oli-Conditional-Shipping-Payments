<?php
/**
 * Condition Engine class.
 *
 * Evaluates restriction conditions. Optional evaluation data lets unit tests
 * run without a live WooCommerce cart.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Oli_CSP_Condition_Engine
 */
class Oli_CSP_Condition_Engine {

	/**
	 * Optional evaluation snapshot (cart, customer, session).
	 *
	 * @var array<string,mixed>|null
	 */
	private $evaluation_data = null;

	/**
	 * Provide cart/customer values instead of reading WooCommerce.
	 *
	 * @param array<string,mixed>|null $data Evaluation snapshot.
	 */
	public function set_evaluation_data( $data ) {
		$this->evaluation_data = is_array( $data ) ? $data : null;
	}

	/**
	 * Clear injected evaluation data.
	 */
	public function clear_evaluation_data() {
		$this->evaluation_data = null;
	}

	/**
	 * Evaluate groups against injected data, then restore the previous snapshot.
	 *
	 * @param array  $condition_groups Condition groups.
	 * @param array  $data             Evaluation snapshot.
	 * @param string $context          Context: cart or checkout.
	 * @return bool
	 */
	public function evaluate_with_data( $condition_groups, $data, $context = 'checkout' ) {
		$previous                = $this->evaluation_data;
		$this->evaluation_data   = is_array( $data ) ? $data : array();
		$result                  = $this->evaluate_condition_groups( $condition_groups, $context );
		$this->evaluation_data   = $previous;
		return $result;
	}

	/**
	 * Evaluate condition groups (OR between groups, AND within a group).
	 *
	 * Empty groups are skipped. No groups means the restriction always applies.
	 *
	 * @param array  $condition_groups Array of condition groups.
	 * @param string $context          Context: cart or checkout.
	 * @return bool
	 */
	public function evaluate_condition_groups( $condition_groups, $context = 'checkout' ) {
		if ( empty( $condition_groups ) || ! is_array( $condition_groups ) ) {
			return true;
		}

		$has_group = false;

		foreach ( $condition_groups as $group ) {
			if ( empty( $group ) || ! is_array( $group ) ) {
				continue;
			}

			$has_group = true;

			if ( $this->evaluate_condition_group( $group, $context ) ) {
				return true;
			}
		}

		return ! $has_group;
	}

	/**
	 * Evaluate a single group (AND).
	 *
	 * @param array  $conditions Conditions in the group.
	 * @param string $context    Context: cart or checkout.
	 * @return bool
	 */
	public function evaluate_condition_group( $conditions, $context = 'checkout' ) {
		if ( empty( $conditions ) || ! is_array( $conditions ) ) {
			return true;
		}

		foreach ( $conditions as $condition ) {
			if ( ! $this->evaluate_single_condition( $condition, $context ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Legacy AND-only evaluator.
	 *
	 * @param array  $conditions Conditions.
	 * @param string $context    Context: cart or checkout.
	 * @return bool
	 */
	public function evaluate_conditions( $conditions, $context = 'checkout' ) {
		return $this->evaluate_condition_group( $conditions, $context );
	}

	/**
	 * Evaluate one condition.
	 *
	 * @param array  $condition Condition configuration.
	 * @param string $context   Context: cart or checkout.
	 * @return bool
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
			case 'cart_weight':
				return $this->check_package_weight( $condition );
			case 'item_quantity':
			case 'quantity':
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
				if ( function_exists( 'apply_filters' ) ) {
					return (bool) apply_filters( 'oli_csp_evaluate_condition', true, $condition, $context );
				}
				return true;
		}
	}

	/**
	 * Cart total (contents + shipping).
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_cart_total( $condition ) {
		$value = $this->snapshot_value( 'cart_total' );
		if ( null !== $value ) {
			return $this->compare_numeric_value( (float) $value, $condition );
		}

		$cart = $this->get_cart();
		if ( ! $cart ) {
			return false;
		}

		$cart_total = (float) $cart->get_cart_contents_total() + (float) $cart->get_shipping_total();
		return $this->compare_numeric_value( $cart_total, $condition );
	}

	/**
	 * Cart subtotal.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_cart_subtotal( $condition ) {
		$value = $this->snapshot_value( 'cart_subtotal' );
		if ( null !== $value ) {
			return $this->compare_numeric_value( (float) $value, $condition );
		}

		$cart = $this->get_cart();
		if ( ! $cart ) {
			return false;
		}

		return $this->compare_numeric_value( (float) $cart->get_subtotal(), $condition );
	}

	/**
	 * Order / checkout total.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_order_total( $condition ) {
		$value = $this->snapshot_value( 'order_total' );
		if ( null !== $value ) {
			return $this->compare_numeric_value( (float) $value, $condition );
		}

		$cart = $this->get_cart();
		if ( ! $cart ) {
			return false;
		}

		return $this->compare_numeric_value( (float) $cart->get_total( 'edit' ), $condition );
	}

	/**
	 * Product IDs in the cart.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_product_in_cart( $condition ) {
		$needles = $this->normalize_list( isset( $condition['value'] ) ? $condition['value'] : array() );
		if ( empty( $needles ) ) {
			return false;
		}

		$ids   = $this->get_cart_product_ids();
		$found = ! empty( array_intersect( $needles, $ids ) );

		return $this->apply_presence_operator( $found, $condition );
	}

	/**
	 * Product categories in the cart.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_category_in_cart( $condition ) {
		$needles = $this->normalize_list( isset( $condition['value'] ) ? $condition['value'] : array() );
		if ( empty( $needles ) ) {
			return false;
		}

		$ids   = $this->get_cart_term_ids( 'cart_category_ids', 'product_cat' );
		$found = ! empty( array_intersect( $needles, $ids ) );

		return $this->apply_presence_operator( $found, $condition );
	}

	/**
	 * Product tags in the cart.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_tag_in_cart( $condition ) {
		$needles = $this->normalize_list( isset( $condition['value'] ) ? $condition['value'] : array() );
		if ( empty( $needles ) ) {
			return false;
		}

		$ids   = $this->get_cart_term_ids( 'cart_tag_ids', 'product_tag' );
		$found = ! empty( array_intersect( $needles, $ids ) );

		return $this->apply_presence_operator( $found, $condition );
	}

	/**
	 * Shipping classes in the cart.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_shipping_class_in_cart( $condition ) {
		$needles = $this->normalize_list( isset( $condition['value'] ) ? $condition['value'] : array() );
		if ( empty( $needles ) ) {
			return false;
		}

		$snapshot = $this->snapshot_value( 'cart_shipping_class_ids' );
		if ( null !== $snapshot ) {
			$ids   = $this->normalize_list( $snapshot );
			$found = ! empty( array_intersect( $needles, $ids ) );
			return $this->apply_presence_operator( $found, $condition );
		}

		$cart = $this->get_cart();
		if ( ! $cart ) {
			return false;
		}

		$found = false;
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
				continue;
			}

			$class_id = (string) $cart_item['data']->get_shipping_class_id();
			if ( in_array( $class_id, $needles, true ) ) {
				$found = true;
				break;
			}
		}

		return $this->apply_presence_operator( $found, $condition );
	}

	/**
	 * Package / cart weight.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_package_weight( $condition ) {
		$value = $this->snapshot_value( 'cart_weight' );
		if ( null !== $value ) {
			return $this->compare_numeric_value( (float) $value, $condition );
		}

		$cart = $this->get_cart();
		if ( ! $cart ) {
			return false;
		}

		return $this->compare_numeric_value( (float) $cart->get_cart_contents_weight(), $condition );
	}

	/**
	 * Item quantity.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_item_quantity( $condition ) {
		$value = $this->snapshot_value( 'cart_quantity' );
		if ( null !== $value ) {
			return $this->compare_numeric_value( (float) $value, $condition );
		}

		$cart = $this->get_cart();
		if ( ! $cart ) {
			return false;
		}

		return $this->compare_numeric_value( (float) $cart->get_cart_contents_count(), $condition );
	}

	/**
	 * Billing country.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_billing_country( $condition ) {
		return $this->compare_list_value( $this->get_customer_field( 'billing_country' ), $condition );
	}

	/**
	 * Billing state.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_billing_state( $condition ) {
		return $this->compare_list_value( $this->get_customer_field( 'billing_state' ), $condition );
	}

	/**
	 * Billing postcode (wildcards and ranges).
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_billing_postcode( $condition ) {
		return $this->check_postcode_field( $this->get_customer_field( 'billing_postcode' ), $condition );
	}

	/**
	 * Shipping country.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_shipping_country( $condition ) {
		return $this->compare_list_value( $this->get_customer_field( 'shipping_country' ), $condition );
	}

	/**
	 * Shipping state.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_shipping_state( $condition ) {
		return $this->compare_list_value( $this->get_customer_field( 'shipping_state' ), $condition );
	}

	/**
	 * Shipping postcode (wildcards and ranges).
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_shipping_postcode( $condition ) {
		return $this->check_postcode_field( $this->get_customer_field( 'shipping_postcode' ), $condition );
	}

	/**
	 * Customer role.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_customer_role( $condition ) {
		$roles = $this->snapshot_value( 'customer_roles' );
		if ( null === $roles ) {
			if ( ! function_exists( 'wp_get_current_user' ) ) {
				return false;
			}

			$user = wp_get_current_user();
			if ( ! $user || ! $user->ID ) {
				$roles = array();
			} else {
				$roles = (array) $user->roles;
			}
		}

		$needles = $this->normalize_list( isset( $condition['value'] ) ? $condition['value'] : array() );
		$found   = ! empty( array_intersect( $this->normalize_list( $roles ), $needles ) );

		return $this->apply_presence_operator( $found, $condition );
	}

	/**
	 * Logged-in status.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_user_logged_in( $condition ) {
		$snapshot = $this->snapshot_value( 'user_logged_in' );
		if ( null !== $snapshot ) {
			$is_logged_in = (bool) $snapshot;
		} elseif ( function_exists( 'is_user_logged_in' ) ) {
			$is_logged_in = is_user_logged_in();
		} else {
			$is_logged_in = false;
		}

		$value = isset( $condition['value'] ) ? $condition['value'] : 'yes';

		return ( 'yes' === $value || '1' === (string) $value || true === $value ) === $is_logged_in;
	}

	/**
	 * Applied coupon codes (case-insensitive).
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_coupon_used( $condition ) {
		$needles = array_map( 'strtolower', $this->normalize_list( isset( $condition['value'] ) ? $condition['value'] : array() ) );
		if ( empty( $needles ) ) {
			return false;
		}

		$applied = $this->snapshot_value( 'applied_coupons' );
		if ( null === $applied ) {
			$cart = $this->get_cart();
			if ( ! $cart ) {
				return false;
			}
			$applied = $cart->get_applied_coupons();
		}

		$applied = array_map( 'strtolower', $this->normalize_list( $applied ) );
		$found   = ! empty( array_intersect( $applied, $needles ) );

		return $this->apply_presence_operator( $found, $condition );
	}

	/**
	 * Backorder in cart.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_backorder_in_cart( $condition ) {
		$snapshot = $this->snapshot_value( 'has_backorder' );
		if ( null !== $snapshot ) {
			$has_backorder = (bool) $snapshot;
		} else {
			$cart          = $this->get_cart();
			$has_backorder = false;

			if ( $cart ) {
				foreach ( $cart->get_cart() as $cart_item ) {
					if ( empty( $cart_item['data'] ) || ! is_object( $cart_item['data'] ) ) {
						continue;
					}
					if ( $cart_item['data']->is_on_backorder( $cart_item['quantity'] ) ) {
						$has_backorder = true;
						break;
					}
				}
			}
		}

		$value = isset( $condition['value'] ) ? $condition['value'] : 'yes';

		return ( 'yes' === $value || '1' === (string) $value || true === $value ) === $has_backorder;
	}

	/**
	 * Store currency.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_currency( $condition ) {
		$currency = $this->snapshot_value( 'currency' );
		if ( null === $currency ) {
			$currency = function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
		}

		return $this->compare_list_value( (string) $currency, $condition );
	}

	/**
	 * Chosen shipping method (prefix match on rate IDs).
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_shipping_method_selected( $condition ) {
		$needles = $this->normalize_list( isset( $condition['value'] ) ? $condition['value'] : array() );
		if ( empty( $needles ) ) {
			return false;
		}

		$chosen = $this->snapshot_value( 'chosen_shipping_methods' );
		if ( null === $chosen ) {
			$wc     = $this->get_wc();
			$chosen = ( $wc && $wc->session ) ? $wc->session->get( 'chosen_shipping_methods', array() ) : array();
		}

		$found = false;
		foreach ( (array) $chosen as $chosen_method ) {
			foreach ( $needles as $method ) {
				if ( 0 === strpos( (string) $chosen_method, (string) $method ) ) {
					$found = true;
					break 2;
				}
			}
		}

		return $this->apply_presence_operator( $found, $condition );
	}

	/**
	 * Chosen payment gateway.
	 *
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function check_payment_gateway_selected( $condition ) {
		$chosen = $this->snapshot_value( 'chosen_payment_method' );
		if ( null === $chosen ) {
			$wc     = $this->get_wc();
			$chosen = ( $wc && $wc->session ) ? (string) $wc->session->get( 'chosen_payment_method' ) : '';
		}

		return $this->compare_list_value( (string) $chosen, $condition );
	}

	/**
	 * Compare a numeric value with the condition operator.
	 *
	 * @param float $value     Current value.
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	public function compare_numeric_value( $value, $condition ) {
		$compare_value = isset( $condition['value'] ) ? (float) $condition['value'] : 0;
		$operator      = isset( $condition['operator'] ) ? $condition['operator'] : '>=';

		switch ( $operator ) {
			case '>':
			case 'greater':
			case 'gt':
				return $value > $compare_value;
			case '>=':
			case 'gte':
				return $value >= $compare_value;
			case '<':
			case 'less':
			case 'lt':
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
	 * Compare a scalar against a list (in / not in).
	 *
	 * @param mixed $value     Current value.
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	public function compare_list_value( $value, $condition ) {
		$compare_values = $this->normalize_list( isset( $condition['value'] ) ? $condition['value'] : array() );
		if ( empty( $compare_values ) ) {
			return false;
		}

		if ( null === $value || '' === $value ) {
			return $this->apply_presence_operator( false, $condition );
		}

		$is_in_list = in_array( (string) $value, $compare_values, true );

		return $this->apply_presence_operator( $is_in_list, $condition );
	}

	/**
	 * Match a postcode against patterns (wildcards * and hyphen ranges).
	 *
	 * @param string $postcode Postcode to check.
	 * @param array  $patterns Patterns to match against.
	 * @return bool
	 */
	public function match_postcode( $postcode, $patterns ) {
		$postcode = strtoupper( str_replace( ' ', '', (string) $postcode ) );
		if ( '' === $postcode ) {
			return false;
		}

		foreach ( (array) $patterns as $pattern ) {
			$pattern = strtoupper( str_replace( ' ', '', (string) $pattern ) );
			if ( '' === $pattern ) {
				continue;
			}

			if ( false !== strpos( $pattern, '-' ) && false === strpos( $pattern, '*' ) ) {
				$parts = explode( '-', $pattern, 2 );
				if ( 2 === count( $parts ) && '' !== $parts[0] && '' !== $parts[1] && $postcode >= $parts[0] && $postcode <= $parts[1] ) {
					return true;
				}
			}

			if ( false !== strpos( $pattern, '*' ) ) {
				$regex = '/^' . str_replace( '\*', '.*', preg_quote( $pattern, '/' ) ) . '$/';
				if ( preg_match( $regex, $postcode ) ) {
					return true;
				}
			}

			if ( $postcode === $pattern ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Evaluate a postcode field with in / not_in.
	 *
	 * @param string $postcode  Current postcode.
	 * @param array  $condition Condition configuration.
	 * @return bool
	 */
	private function check_postcode_field( $postcode, $condition ) {
		$patterns = $this->normalize_list( isset( $condition['value'] ) ? $condition['value'] : array() );
		if ( empty( $patterns ) || '' === (string) $postcode ) {
			return $this->apply_presence_operator( false, $condition );
		}

		return $this->apply_presence_operator( $this->match_postcode( $postcode, $patterns ), $condition );
	}

	/**
	 * Apply in / not_in (and aliases) to a boolean presence result.
	 *
	 * @param bool  $found     Whether the value was present.
	 * @param array $condition Condition configuration.
	 * @return bool
	 */
	private function apply_presence_operator( $found, $condition ) {
		$operator = isset( $condition['operator'] ) ? $condition['operator'] : 'in';

		switch ( $operator ) {
			case 'not_in':
			case 'is_not':
			case 'not_contains':
				return ! $found;
			case 'in':
			case 'is':
			case 'contains':
			default:
				return (bool) $found;
		}
	}

	/**
	 * Normalize a condition value into a list of trimmed strings.
	 *
	 * @param mixed $value Raw value.
	 * @return array
	 */
	private function normalize_list( $value ) {
		if ( is_array( $value ) ) {
			$items = $value;
		} else {
			$items = preg_split( '/[\n,]+/', (string) $value );
		}

		$normalized = array();
		foreach ( (array) $items as $item ) {
			$item = trim( (string) $item );
			if ( '' !== $item ) {
				$normalized[] = $item;
			}
		}

		return $normalized;
	}

	/**
	 * Read a key from the injected snapshot.
	 *
	 * @param string $key Snapshot key.
	 * @return mixed|null
	 */
	private function snapshot_value( $key ) {
		if ( ! is_array( $this->evaluation_data ) || ! array_key_exists( $key, $this->evaluation_data ) ) {
			return null;
		}

		return $this->evaluation_data[ $key ];
	}

	/**
	 * Product IDs currently in the cart.
	 *
	 * @return array
	 */
	private function get_cart_product_ids() {
		$snapshot = $this->snapshot_value( 'cart_product_ids' );
		if ( null !== $snapshot ) {
			return $this->normalize_list( $snapshot );
		}

		$cart = $this->get_cart();
		if ( ! $cart ) {
			return array();
		}

		$ids = array();
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['product_id'] ) ) {
				$ids[] = (string) $cart_item['product_id'];
			}
			if ( ! empty( $cart_item['variation_id'] ) ) {
				$ids[] = (string) $cart_item['variation_id'];
			}
		}

		return $ids;
	}

	/**
	 * Term IDs for products in the cart.
	 *
	 * @param string $snapshot_key Evaluation data key.
	 * @param string $taxonomy     Product taxonomy.
	 * @return array
	 */
	private function get_cart_term_ids( $snapshot_key, $taxonomy ) {
		$snapshot = $this->snapshot_value( $snapshot_key );
		if ( null !== $snapshot ) {
			return $this->normalize_list( $snapshot );
		}

		$cart = $this->get_cart();
		if ( ! $cart || ! function_exists( 'get_the_terms' ) ) {
			return array();
		}

		$ids = array();
		foreach ( $cart->get_cart() as $cart_item ) {
			if ( empty( $cart_item['product_id'] ) ) {
				continue;
			}

			$terms = get_the_terms( (int) $cart_item['product_id'], $taxonomy );
			if ( ! $terms || ( function_exists( 'is_wp_error' ) && is_wp_error( $terms ) ) ) {
				continue;
			}

			foreach ( $terms as $term ) {
				if ( isset( $term->term_id ) ) {
					$ids[] = (string) $term->term_id;
				}
			}
		}

		return $ids;
	}

	/**
	 * Customer address field from the snapshot or WooCommerce customer.
	 *
	 * @param string $field Field name (billing_country, shipping_postcode, …).
	 * @return string
	 */
	private function get_customer_field( $field ) {
		$snapshot = $this->snapshot_value( $field );
		if ( null !== $snapshot ) {
			return (string) $snapshot;
		}

		$wc = $this->get_wc();
		if ( ! $wc || ! $wc->customer || ! is_object( $wc->customer ) ) {
			return '';
		}

		$methods = array(
			'billing_country'   => 'get_billing_country',
			'billing_state'     => 'get_billing_state',
			'billing_postcode'  => 'get_billing_postcode',
			'shipping_country'  => 'get_shipping_country',
			'shipping_state'    => 'get_shipping_state',
			'shipping_postcode' => 'get_shipping_postcode',
		);

		if ( ! isset( $methods[ $field ] ) || ! method_exists( $wc->customer, $methods[ $field ] ) ) {
			return '';
		}

		return (string) $wc->customer->{$methods[ $field ]}();
	}

	/**
	 * WooCommerce cart instance when available.
	 *
	 * @return object|null
	 */
	private function get_cart() {
		$wc = $this->get_wc();
		return ( $wc && $wc->cart ) ? $wc->cart : null;
	}

	/**
	 * WooCommerce instance when available.
	 *
	 * @return object|null
	 */
	private function get_wc() {
		if ( function_exists( 'WC' ) ) {
			return WC();
		}

		return null;
	}
}
