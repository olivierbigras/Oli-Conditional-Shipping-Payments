<?php
/**
 * Utility functions.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Oli_CSP_Utils
 */
class Oli_CSP_Utils {

	/**
	 * Get all available condition types.
	 *
	 * @return array
	 */
	public static function get_condition_types() {
		return array(
			'cart_subtotal'          => __( 'Cart Subtotal', 'oli-conditional-shipping-payments' ),
			'cart_total'             => __( 'Cart Total', 'oli-conditional-shipping-payments' ),
			'order_total'            => __( 'Order Total', 'oli-conditional-shipping-payments' ),
			'package_weight'         => __( 'Cart Weight', 'oli-conditional-shipping-payments' ),
			'item_quantity'          => __( 'Item Quantity', 'oli-conditional-shipping-payments' ),
			'product_in_cart'        => __( 'Product in Cart', 'oli-conditional-shipping-payments' ),
			'category_in_cart'       => __( 'Category in Cart', 'oli-conditional-shipping-payments' ),
			'tag_in_cart'            => __( 'Tag in Cart', 'oli-conditional-shipping-payments' ),
			'shipping_class_in_cart' => __( 'Shipping Class in Cart', 'oli-conditional-shipping-payments' ),
			'billing_country'        => __( 'Billing Country', 'oli-conditional-shipping-payments' ),
			'billing_state'          => __( 'Billing State', 'oli-conditional-shipping-payments' ),
			'billing_postcode'       => __( 'Billing Postcode', 'oli-conditional-shipping-payments' ),
			'shipping_country'       => __( 'Shipping Country', 'oli-conditional-shipping-payments' ),
			'shipping_state'         => __( 'Shipping State', 'oli-conditional-shipping-payments' ),
			'shipping_postcode'      => __( 'Shipping Postcode', 'oli-conditional-shipping-payments' ),
			'customer_role'          => __( 'Customer Role', 'oli-conditional-shipping-payments' ),
			'user_logged_in'         => __( 'User Logged In', 'oli-conditional-shipping-payments' ),
			'coupon_used'            => __( 'Coupon Code Used', 'oli-conditional-shipping-payments' ),
			'backorder_in_cart'      => __( 'Backorder in Cart', 'oli-conditional-shipping-payments' ),
			'currency'               => __( 'Currency', 'oli-conditional-shipping-payments' ),
			'shipping_method'        => __( 'Shipping Method Selected', 'oli-conditional-shipping-payments' ),
			'payment_gateway'        => __( 'Payment Gateway Selected', 'oli-conditional-shipping-payments' ),
		);
	}

	/**
	 * Input and operator metadata for each condition type.
	 *
	 * @return array
	 */
	public static function get_condition_schema() {
		$numeric = array(
			'input'     => 'number',
			'operators' => 'numeric',
			'multiple'  => false,
		);
		$list    = array(
			'input'     => 'text',
			'operators' => 'list',
			'multiple'  => true,
		);

		return array(
			'cart_subtotal'          => $numeric,
			'cart_total'             => $numeric,
			'order_total'            => $numeric,
			'package_weight'         => $numeric,
			'item_quantity'          => $numeric,
			'product_in_cart'        => array(
				'input'     => 'product',
				'operators' => 'list',
				'multiple'  => true,
			),
			'category_in_cart'       => array(
				'input'     => 'category',
				'operators' => 'list',
				'multiple'  => true,
			),
			'tag_in_cart'            => array(
				'input'     => 'tag',
				'operators' => 'list',
				'multiple'  => true,
			),
			'shipping_class_in_cart' => array(
				'input'     => 'shipping_class',
				'operators' => 'list',
				'multiple'  => true,
			),
			'billing_country'        => array(
				'input'     => 'country',
				'operators' => 'list',
				'multiple'  => true,
			),
			'shipping_country'       => array(
				'input'     => 'country',
				'operators' => 'list',
				'multiple'  => true,
			),
			'billing_state'          => $list,
			'shipping_state'         => $list,
			'billing_postcode'       => array(
				'input'     => 'textarea',
				'operators' => 'list',
				'multiple'  => true,
			),
			'shipping_postcode'      => array(
				'input'     => 'textarea',
				'operators' => 'list',
				'multiple'  => true,
			),
			'customer_role'          => array(
				'input'     => 'role',
				'operators' => 'list',
				'multiple'  => true,
			),
			'user_logged_in'         => array(
				'input'     => 'yesno',
				'operators' => 'none',
				'multiple'  => false,
			),
			'coupon_used'            => $list,
			'backorder_in_cart'      => array(
				'input'     => 'yesno',
				'operators' => 'none',
				'multiple'  => false,
			),
			'currency'               => $list,
			'shipping_method'        => array(
				'input'     => 'shipping_method',
				'operators' => 'list',
				'multiple'  => true,
			),
			'payment_gateway'        => array(
				'input'     => 'payment_gateway',
				'operators' => 'list',
				'multiple'  => true,
			),
		);
	}

	/**
	 * Get numeric operators.
	 *
	 * @return array
	 */
	public static function get_numeric_operators() {
		return array(
			'>=' => __( 'Greater than or equal to', 'oli-conditional-shipping-payments' ),
			'>'  => __( 'Greater than', 'oli-conditional-shipping-payments' ),
			'<=' => __( 'Less than or equal to', 'oli-conditional-shipping-payments' ),
			'<'  => __( 'Less than', 'oli-conditional-shipping-payments' ),
			'==' => __( 'Equal to', 'oli-conditional-shipping-payments' ),
			'!=' => __( 'Not equal to', 'oli-conditional-shipping-payments' ),
		);
	}

	/**
	 * Get list operators.
	 *
	 * @return array
	 */
	public static function get_list_operators() {
		return array(
			'in'     => __( 'Is one of', 'oli-conditional-shipping-payments' ),
			'not_in' => __( 'Is none of', 'oli-conditional-shipping-payments' ),
		);
	}

	/**
	 * Restriction type labels.
	 *
	 * @return array
	 */
	public static function get_restriction_types() {
		return array(
			'shipping_methods'      => __( 'Shipping Methods', 'oli-conditional-shipping-payments' ),
			'payment_gateways'      => __( 'Payment Gateways', 'oli-conditional-shipping-payments' ),
			'shipping_destinations' => __( 'Shipping Destinations', 'oli-conditional-shipping-payments' ),
			'checkout_notices'      => __( 'Checkout Notices', 'oli-conditional-shipping-payments' ),
		);
	}

	/**
	 * Condition types that store list values.
	 *
	 * @return array
	 */
	public static function get_list_condition_types() {
		$schema = self::get_condition_schema();
		$types  = array();

		foreach ( $schema as $type => $config ) {
			if ( ! empty( $config['multiple'] ) ) {
				$types[] = $type;
			}
		}

		return $types;
	}

	/**
	 * Sanitize restriction data.
	 *
	 * @param array $data Restriction data.
	 * @return array Sanitized data.
	 */
	public static function sanitize_restriction_data( $data ) {
		if ( ! is_array( $data ) ) {
			return array();
		}

		$allowed_types = array_keys( self::get_restriction_types() );
		$sanitized     = array();

		foreach ( $data as $type => $restrictions ) {
			$type = sanitize_key( $type );
			if ( ! in_array( $type, $allowed_types, true ) ) {
				continue;
			}

			$sanitized[ $type ] = array();

			if ( ! is_array( $restrictions ) ) {
				continue;
			}

			foreach ( $restrictions as $restriction ) {
				if ( ! is_array( $restriction ) ) {
					continue;
				}

				$sanitized_restriction = self::sanitize_single_restriction( $restriction );
				if ( ! empty( $sanitized_restriction ) ) {
					$sanitized[ $type ][] = $sanitized_restriction;
				}
			}
		}

		return $sanitized;
	}

	/**
	 * Sanitize one restriction.
	 *
	 * @param array $restriction Restriction data.
	 * @return array
	 */
	public static function sanitize_single_restriction( $restriction ) {
		$sanitized = array();

		if ( isset( $restriction['title'] ) ) {
			$sanitized['title'] = sanitize_text_field( $restriction['title'] );
		}

		$sanitized['enabled'] = ( ! isset( $restriction['enabled'] ) || 'no' !== $restriction['enabled'] ) ? 'yes' : 'no';

		if ( isset( $restriction['conditions'] ) && is_array( $restriction['conditions'] ) ) {
			$sanitized['conditions'] = self::sanitize_conditions( $restriction['conditions'] );
		}

		if ( isset( $restriction['condition_groups'] ) && is_array( $restriction['condition_groups'] ) ) {
			$sanitized['condition_groups'] = array();
			foreach ( $restriction['condition_groups'] as $group ) {
				$clean_group = self::sanitize_conditions( $group );
				if ( ! empty( $clean_group ) ) {
					$sanitized['condition_groups'][] = $clean_group;
				}
			}
		}

		if ( isset( $restriction['zones'] ) ) {
			$sanitized['zones'] = array_values( array_unique( array_map( 'absint', (array) $restriction['zones'] ) ) );
		}

		if ( isset( $restriction['excluded_methods'] ) ) {
			$sanitized['excluded_methods'] = self::sanitize_id_list( $restriction['excluded_methods'] );
		}

		if ( isset( $restriction['excluded_gateways'] ) ) {
			$sanitized['excluded_gateways'] = self::sanitize_id_list( $restriction['excluded_gateways'] );
		}

		if ( isset( $restriction['excluded_countries'] ) ) {
			$sanitized['excluded_countries'] = self::sanitize_id_list( $restriction['excluded_countries'] );
		}

		if ( isset( $restriction['notice'] ) ) {
			$sanitized['notice'] = sanitize_textarea_field( $restriction['notice'] );
		}

		if ( isset( $restriction['message'] ) ) {
			$sanitized['message'] = sanitize_textarea_field( $restriction['message'] );
		}

		if ( empty( $sanitized['message'] ) && ! empty( $sanitized['notice'] ) ) {
			$sanitized['message'] = $sanitized['notice'];
		}

		$has_action = ! empty( $sanitized['excluded_methods'] )
			|| ! empty( $sanitized['excluded_gateways'] )
			|| ! empty( $sanitized['excluded_countries'] )
			|| ! empty( $sanitized['notice'] )
			|| ! empty( $sanitized['message'] );

		if ( ! $has_action && empty( $sanitized['condition_groups'] ) && empty( $sanitized['conditions'] ) ) {
			return array();
		}

		return $sanitized;
	}

	/**
	 * Sanitize a list of identifiers.
	 *
	 * @param mixed $items Raw items.
	 * @return array
	 */
	public static function sanitize_id_list( $items ) {
		$clean = array();

		foreach ( (array) $items as $item ) {
			$item = sanitize_text_field( (string) $item );
			if ( '' !== $item ) {
				$clean[] = $item;
			}
		}

		return array_values( array_unique( $clean ) );
	}

	/**
	 * Sanitize conditions array.
	 *
	 * @param array $conditions Conditions array.
	 * @return array Sanitized conditions.
	 */
	public static function sanitize_conditions( $conditions ) {
		if ( ! is_array( $conditions ) ) {
			return array();
		}

		$allowed_types = array_keys( self::get_condition_types() );
		$schema        = self::get_condition_schema();
		$sanitized     = array();

		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) || empty( $condition['type'] ) ) {
				continue;
			}

			$type = sanitize_key( $condition['type'] );
			if ( ! in_array( $type, $allowed_types, true ) ) {
				continue;
			}

			$is_list   = ! empty( $schema[ $type ]['multiple'] );
			$raw_value = isset( $condition['value'] ) ? $condition['value'] : '';
			$value     = self::sanitize_condition_value( $raw_value, $is_list );
			$valueless = in_array( $type, array( 'user_logged_in', 'backorder_in_cart' ), true );

			if ( ! $valueless && ( ( is_array( $value ) && empty( $value ) ) || '' === $value ) ) {
				continue;
			}

			$sanitized_condition = array(
				'type'  => $type,
				'value' => $valueless && '' === $value ? 'yes' : $value,
			);

			if ( isset( $condition['operator'] ) ) {
				$sanitized_condition['operator'] = sanitize_text_field( $condition['operator'] );
			}

			$sanitized[] = $sanitized_condition;
		}

		return $sanitized;
	}

	/**
	 * Sanitize a condition value.
	 *
	 * @param mixed $value   Raw value.
	 * @param bool  $is_list Whether the value is a list.
	 * @return array|string
	 */
	public static function sanitize_condition_value( $value, $is_list ) {
		if ( $is_list ) {
			if ( ! is_array( $value ) ) {
				$value = preg_split( '/[\n,]+/', (string) $value );
			}

			return self::sanitize_id_list( $value );
		}

		if ( is_array( $value ) ) {
			$value = reset( $value );
		}

		return sanitize_text_field( (string) $value );
	}

	/**
	 * Export restrictions to JSON.
	 *
	 * @param array $restrictions Restrictions data.
	 * @return string JSON string.
	 */
	public static function export_to_json( $restrictions ) {
		return wp_json_encode( $restrictions, JSON_PRETTY_PRINT );
	}

	/**
	 * Import restrictions from JSON.
	 *
	 * @param string $json JSON string.
	 * @return array|WP_Error Restrictions data or error.
	 */
	public static function import_from_json( $json ) {
		$data = json_decode( $json, true );

		if ( json_last_error() !== JSON_ERROR_NONE ) {
			return new WP_Error( 'invalid_json', __( 'Invalid JSON data.', 'oli-conditional-shipping-payments' ) );
		}

		return self::sanitize_restriction_data( $data );
	}

	/**
	 * Whether a restriction is enabled.
	 *
	 * @param array $restriction Restriction data.
	 * @return bool
	 */
	public static function is_restriction_enabled( $restriction ) {
		if ( ! is_array( $restriction ) ) {
			return false;
		}

		if ( ! isset( $restriction['enabled'] ) ) {
			return true;
		}

		return 'no' !== (string) $restriction['enabled'] && '0' !== (string) $restriction['enabled'] && false !== $restriction['enabled'];
	}
}
