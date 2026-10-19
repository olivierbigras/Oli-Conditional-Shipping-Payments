<?php
/**
 * Importer class.
 *
 * Imports restrictions from the official WooCommerce Conditional Shipping and Payments extension.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Oli_CSP_Importer
 */
class Oli_CSP_Importer {

	/**
	 * Official extension option name.
	 */
	const OFFICIAL_OPTION_KEY = 'wc_csp_restrictions';

	/**
	 * Official extension meta key.
	 */
	const OFFICIAL_META_KEY = '_wccsp_restrictions';

	/**
	 * Import restrictions from official extension.
	 *
	 * @param bool $dry_run Whether to perform a dry run without saving.
	 * @return array Import report.
	 */
	public function import_from_official_extension( $dry_run = false ) {
		$report = array(
			'success' => false,
			'message' => '',
			'global_restrictions' => array(),
			'product_restrictions' => array(),
			'comparison' => array(),
		);

		// Check if official extension data exists.
		$official_data = get_option( self::OFFICIAL_OPTION_KEY );
		if ( empty( $official_data ) ) {
			$report['message'] = __( 'No data found from the official extension.', 'oli-conditional-shipping-payments' );
			return $report;
		}

		// Import global restrictions.
		$imported_global = $this->convert_global_restrictions( $official_data );
		$report['global_restrictions'] = $imported_global;

		// Import product-specific restrictions.
		$imported_products = $this->import_product_restrictions( $dry_run );
		$report['product_restrictions'] = $imported_products;

		// Generate comparison report.
		$report['comparison'] = $this->generate_comparison_report( $official_data, $imported_global );

		if ( ! $dry_run ) {
			update_option( 'oli_csp_global_restrictions', $imported_global );
			$report['success'] = true;
			$report['message'] = sprintf(
				/* translators: 1: Number of global restrictions, 2: Number of product restrictions */
				__( 'Successfully imported %1$d global restrictions and %2$d product restrictions.', 'oli-conditional-shipping-payments' ),
				count( $imported_global ),
				count( $imported_products )
			);
		} else {
			$report['success'] = true;
			$report['message'] = sprintf(
				/* translators: 1: Number of global restrictions, 2: Number of product restrictions */
				__( 'Dry run: Found %1$d global restrictions and %2$d product restrictions ready to import.', 'oli-conditional-shipping-payments' ),
				count( $imported_global ),
				count( $imported_products )
			);
		}

		return $report;
	}

	/**
	 * Convert global restrictions from official format.
	 *
	 * @param array $official_data Official extension data.
	 * @return array Converted restrictions.
	 */
	private function convert_global_restrictions( $official_data ) {
		$converted = array(
			'shipping_methods' => array(),
			'payment_gateways' => array(),
			'shipping_destinations' => array(),
		);

		if ( ! is_array( $official_data ) ) {
			return $converted;
		}

		foreach ( $official_data as $restriction_id => $restriction_data ) {
			if ( empty( $restriction_data['type'] ) ) {
				continue;
			}

			$type = $restriction_data['type'];
			$converted_restriction = array(
				'conditions' => $this->convert_conditions( $restriction_data ),
			);

			switch ( $type ) {
				case 'shipping_method':
					$converted_restriction['excluded_methods'] = $this->extract_excluded_items( $restriction_data, 'shipping_methods' );
					if ( ! empty( $converted_restriction['excluded_methods'] ) ) {
						$converted['shipping_methods'][] = $converted_restriction;
					}
					break;

				case 'payment_gateway':
					$converted_restriction['excluded_gateways'] = $this->extract_excluded_items( $restriction_data, 'payment_gateways' );
					if ( ! empty( $converted_restriction['excluded_gateways'] ) ) {
						$converted['payment_gateways'][] = $converted_restriction;
					}
					break;

				case 'shipping_country':
					$converted_restriction['excluded_countries'] = $this->extract_excluded_items( $restriction_data, 'countries' );
					$converted_restriction['message'] = ! empty( $restriction_data['message'] ) ? $restriction_data['message'] : '';
					if ( ! empty( $converted_restriction['excluded_countries'] ) ) {
						$converted['shipping_destinations'][] = $converted_restriction;
					}
					break;
			}
		}

		return $converted;
	}

	/**
	 * Convert conditions from official format.
	 *
	 * @param array $restriction_data Restriction data.
	 * @return array Converted conditions.
	 */
	private function convert_conditions( $restriction_data ) {
		$conditions = array();

		if ( empty( $restriction_data['conditions'] ) || ! is_array( $restriction_data['conditions'] ) ) {
			return $conditions;
		}

		foreach ( $restriction_data['conditions'] as $condition ) {
			if ( empty( $condition['type'] ) ) {
				continue;
			}

			$converted_condition = array(
				'type' => $this->map_condition_type( $condition['type'] ),
			);

			// Add operator for numeric conditions.
			if ( isset( $condition['modifier'] ) ) {
				$converted_condition['operator'] = $this->map_operator( $condition['modifier'] );
			}

			// Add value.
			if ( isset( $condition['value'] ) ) {
				$converted_condition['value'] = $condition['value'];
			}

			$conditions[] = $converted_condition;
		}

		return $conditions;
	}

	/**
	 * Map condition type from official to our format.
	 *
	 * @param string $official_type Official condition type.
	 * @return string Our condition type.
	 */
	private function map_condition_type( $official_type ) {
		$mapping = array(
			'order_total'       => 'order_total',
			'cart_total'        => 'cart_total',
			'cart_subtotal'     => 'cart_subtotal',
			'package_weight'    => 'package_weight',
			'package_item_quantity' => 'item_quantity',
			'product'           => 'product_in_cart',
			'category'          => 'category_in_cart',
			'tag'               => 'tag_in_cart',
			'shipping_class'    => 'shipping_class_in_cart',
			'billing_country'   => 'billing_country',
			'shipping_country'  => 'shipping_country',
			'billing_state'     => 'billing_state',
			'shipping_state'    => 'shipping_state',
			'billing_postcode'  => 'billing_postcode',
			'shipping_postcode' => 'shipping_postcode',
			'customer_role'     => 'customer_role',
			'customer_logged_in' => 'user_logged_in',
			'coupon'            => 'coupon_used',
			'shipping_method'   => 'shipping_method',
			'payment_gateway'   => 'payment_gateway',
		);

		return isset( $mapping[ $official_type ] ) ? $mapping[ $official_type ] : $official_type;
	}

	/**
	 * Map operator from official to our format.
	 *
	 * @param string $official_operator Official operator.
	 * @return string Our operator.
	 */
	private function map_operator( $official_operator ) {
		$mapping = array(
			'gte' => '>=',
			'gt'  => '>',
			'lte' => '<=',
			'lt'  => '<',
			'eq'  => '==',
			'neq' => '!=',
		);

		return isset( $mapping[ $official_operator ] ) ? $mapping[ $official_operator ] : '>=';
	}

	/**
	 * Extract excluded items from restriction data.
	 *
	 * @param array  $restriction_data Restriction data.
	 * @param string $key Key to extract.
	 * @return array Excluded items.
	 */
	private function extract_excluded_items( $restriction_data, $key ) {
		$items = array();

		if ( ! empty( $restriction_data[ $key ] ) && is_array( $restriction_data[ $key ] ) ) {
			$items = array_values( $restriction_data[ $key ] );
		}

		return $items;
	}

	/**
	 * Import product-specific restrictions.
	 *
	 * @param bool $dry_run Whether to perform a dry run.
	 * @return array Imported product restrictions.
	 */
	private function import_product_restrictions( $dry_run = false ) {
		global $wpdb;

		$product_restrictions = array();

		// Find all products with official extension meta.
		$query = $wpdb->prepare(
			"SELECT post_id, meta_value FROM {$wpdb->postmeta} WHERE meta_key = %s",
			self::OFFICIAL_META_KEY
		);

		$results = $wpdb->get_results( $query ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared

		if ( empty( $results ) ) {
			return $product_restrictions;
		}

		foreach ( $results as $row ) {
			$product_id = (int) $row->post_id;
			$meta_value = maybe_unserialize( $row->meta_value );

			if ( ! is_array( $meta_value ) ) {
				continue;
			}

			$converted = $this->convert_global_restrictions( $meta_value );

			if ( ! $dry_run ) {
				update_post_meta( $product_id, '_oli_csp_restrictions', $converted );
			}

			$product_restrictions[ $product_id ] = $converted;
		}

		return $product_restrictions;
	}

	/**
	 * Generate comparison report.
	 *
	 * @param array $official_data Official data.
	 * @param array $imported_data Imported data.
	 * @return array Comparison report.
	 */
	private function generate_comparison_report( $official_data, $imported_data ) {
		$report = array(
			'official_count' => is_array( $official_data ) ? count( $official_data ) : 0,
			'imported_count' => 0,
			'types' => array(
				'shipping_methods' => array(
					'imported' => count( $imported_data['shipping_methods'] ),
				),
				'payment_gateways' => array(
					'imported' => count( $imported_data['payment_gateways'] ),
				),
				'shipping_destinations' => array(
					'imported' => count( $imported_data['shipping_destinations'] ),
				),
			),
			'warnings' => array(),
		);

		$report['imported_count'] = $report['types']['shipping_methods']['imported'] +
									$report['types']['payment_gateways']['imported'] +
									$report['types']['shipping_destinations']['imported'];

		// Check for discrepancies.
		if ( $report['official_count'] !== $report['imported_count'] ) {
			$report['warnings'][] = sprintf(
				/* translators: 1: Official count, 2: Imported count */
				__( 'The official extension had %1$d restrictions, but only %2$d were imported. Some restriction types may not be supported.', 'oli-conditional-shipping-payments' ),
				$report['official_count'],
				$report['imported_count']
			);
		}

		return $report;
	}
}
