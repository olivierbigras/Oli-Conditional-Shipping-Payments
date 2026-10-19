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
			'cart_total'             => __( 'Cart Total', 'oli-conditional-shipping-payments' ),
			'cart_subtotal'          => __( 'Cart Subtotal', 'oli-conditional-shipping-payments' ),
			'order_total'            => __( 'Order Total', 'oli-conditional-shipping-payments' ),
			'product_in_cart'        => __( 'Product in Cart', 'oli-conditional-shipping-payments' ),
			'category_in_cart'       => __( 'Category in Cart', 'oli-conditional-shipping-payments' ),
			'tag_in_cart'            => __( 'Tag in Cart', 'oli-conditional-shipping-payments' ),
			'shipping_class_in_cart' => __( 'Shipping Class in Cart', 'oli-conditional-shipping-payments' ),
			'package_weight'         => __( 'Package Weight', 'oli-conditional-shipping-payments' ),
			'item_quantity'          => __( 'Item Quantity', 'oli-conditional-shipping-payments' ),
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
	 * Get numeric operators.
	 *
	 * @return array
	 */
	public static function get_numeric_operators() {
		return array(
			'>='  => __( 'Greater than or equal to', 'oli-conditional-shipping-payments' ),
			'>'   => __( 'Greater than', 'oli-conditional-shipping-payments' ),
			'<='  => __( 'Less than or equal to', 'oli-conditional-shipping-payments' ),
			'<'   => __( 'Less than', 'oli-conditional-shipping-payments' ),
			'=='  => __( 'Equal to', 'oli-conditional-shipping-payments' ),
			'!='  => __( 'Not equal to', 'oli-conditional-shipping-payments' ),
		);
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

		$sanitized = array();

		foreach ( $data as $type => $restrictions ) {
			$type = sanitize_key( $type );
			$sanitized[ $type ] = array();

			if ( ! is_array( $restrictions ) ) {
				continue;
			}

			foreach ( $restrictions as $restriction ) {
				$sanitized_restriction = array();

				if ( isset( $restriction['conditions'] ) && is_array( $restriction['conditions'] ) ) {
					$sanitized_restriction['conditions'] = self::sanitize_conditions( $restriction['conditions'] );
				}

				if ( isset( $restriction['excluded_methods'] ) ) {
					$sanitized_restriction['excluded_methods'] = array_map( 'sanitize_text_field', (array) $restriction['excluded_methods'] );
				}

				if ( isset( $restriction['excluded_gateways'] ) ) {
					$sanitized_restriction['excluded_gateways'] = array_map( 'sanitize_text_field', (array) $restriction['excluded_gateways'] );
				}

				if ( isset( $restriction['excluded_countries'] ) ) {
					$sanitized_restriction['excluded_countries'] = array_map( 'sanitize_text_field', (array) $restriction['excluded_countries'] );
				}

				if ( isset( $restriction['message'] ) ) {
					$sanitized_restriction['message'] = sanitize_textarea_field( $restriction['message'] );
				}

				$sanitized[ $type ][] = $sanitized_restriction;
			}
		}

		return $sanitized;
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

		$sanitized = array();

		foreach ( $conditions as $condition ) {
			$sanitized_condition = array();

			if ( isset( $condition['type'] ) ) {
				$sanitized_condition['type'] = sanitize_key( $condition['type'] );
			}

			if ( isset( $condition['operator'] ) ) {
				$sanitized_condition['operator'] = sanitize_text_field( $condition['operator'] );
			}

			if ( isset( $condition['value'] ) ) {
				if ( is_array( $condition['value'] ) ) {
					$sanitized_condition['value'] = array_map( 'sanitize_text_field', $condition['value'] );
				} else {
					$sanitized_condition['value'] = sanitize_text_field( $condition['value'] );
				}
			}

			$sanitized[] = $sanitized_condition;
		}

		return $sanitized;
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
}
