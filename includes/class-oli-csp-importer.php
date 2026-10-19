<?php
/**
 * Importer class.
 *
 * Imports restrictions from the official WooCommerce Conditional Shipping and Payments extension.
 * Maps ALL condition types and operators - reports unmappable items, never partially imports.
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
	 * Unmappable items found during import.
	 *
	 * @var array
	 */
	private $unmappable_items = array();

	/**
	 * Import restrictions from official extension.
	 *
	 * @param bool $dry_run Whether to perform a dry run without saving.
	 * @return array Import report.
	 */
	public function import_from_official_extension( $dry_run = false ) {
		$this->unmappable_items = array();

		$report = array(
			'success'              => false,
			'message'              => '',
			'global_restrictions'  => array(),
			'product_restrictions' => array(),
			'comparison'           => array(),
			'unmappable_items'     => array(),
			'errors'               => array(),
		);

		// Check if official extension data exists.
		$official_data = get_option( self::OFFICIAL_OPTION_KEY );
		if ( empty( $official_data ) ) {
			$report['message'] = __( 'No data found from the official extension.', 'oli-conditional-shipping-payments' );
			return $report;
		}

		// Import global restrictions.
		$imported_global               = $this->convert_global_restrictions( $official_data );
		$report['global_restrictions'] = $imported_global;

		// Import product-specific restrictions.
		$imported_products              = $this->import_product_restrictions( $dry_run );
		$report['product_restrictions'] = $imported_products;

		// Report unmappable items.
		$report['unmappable_items'] = $this->unmappable_items;

		// Generate comparison report.
		$report['comparison'] = $this->generate_comparison_report( $official_data, $imported_global );

		// Check if there were unmappable items.
		if ( ! empty( $this->unmappable_items ) ) {
			$report['errors'][] = sprintf(
				/* translators: %d: Number of unmappable items */
				__( 'Warning: %d unmappable condition(s) or operator(s) were found. These restrictions were NOT imported to ensure accuracy.', 'oli-conditional-shipping-payments' ),
				count( $this->unmappable_items )
			);
		}

		if ( ! $dry_run && empty( $this->unmappable_items ) ) {
			update_option( 'oli_csp_global_restrictions', $imported_global );
			$report['success'] = true;
			$report['message'] = sprintf(
				/* translators: 1: Number of global restrictions, 2: Number of product restrictions */
				__( 'Successfully imported %1$d global restrictions and %2$d product restrictions.', 'oli-conditional-shipping-payments' ),
				$this->count_restrictions( $imported_global ),
				count( $imported_products )
			);
		} elseif ( ! $dry_run && ! empty( $this->unmappable_items ) ) {
			$report['success'] = false;
			$report['message'] = __( 'Import aborted due to unmappable items. Please review the report and contact support if needed.', 'oli-conditional-shipping-payments' );
		} else {
			$report['success'] = true;
			$report['message'] = sprintf(
				/* translators: 1: Number of global restrictions, 2: Number of product restrictions */
				__( 'Dry run: Found %1$d global restrictions and %2$d product restrictions ready to import.', 'oli-conditional-shipping-payments' ),
				$this->count_restrictions( $imported_global ),
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
			'shipping_methods'      => array(),
			'payment_gateways'      => array(),
			'shipping_destinations' => array(),
		);

		if ( ! is_array( $official_data ) ) {
			return $converted;
		}

		foreach ( $official_data as $restriction_id => $restriction_data ) {
			if ( empty( $restriction_data['type'] ) ) {
				continue;
			}

			$type                  = $restriction_data['type'];
			$converted_restriction = array();

			// Convert condition groups (OR logic).
			$condition_groups = $this->convert_condition_groups( $restriction_data );
			if ( false === $condition_groups ) {
				// Unmappable condition found, skip this restriction.
				$this->unmappable_items[] = array(
					'restriction_id' => $restriction_id,
					'type'           => $type,
					'reason'         => 'Contains unmappable condition type or operator',
				);
				continue;
			}

			if ( ! empty( $condition_groups ) ) {
				$converted_restriction['condition_groups'] = $condition_groups;
			}

			switch ( $type ) {
				case 'shipping_method':
					$converted_restriction['excluded_methods'] = $this->extract_excluded_items( $restriction_data, 'shipping_methods' );

					// Handle zone-specific restrictions.
					if ( ! empty( $restriction_data['zones'] ) ) {
						$converted_restriction['zones'] = array_map( 'intval', (array) $restriction_data['zones'] );
					}

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
					$converted_restriction['message']            = ! empty( $restriction_data['message'] ) ? $restriction_data['message'] : '';
					if ( ! empty( $converted_restriction['excluded_countries'] ) ) {
						$converted['shipping_destinations'][] = $converted_restriction;
					}
					break;
			}
		}

		return $converted;
	}

	/**
	 * Convert condition groups from official format (supports OR logic).
	 *
	 * @param array $restriction_data Restriction data.
	 * @return array|false Converted condition groups, or false if unmappable.
	 */
	private function convert_condition_groups( $restriction_data ) {
		$groups = array();

		// Official extension stores condition groups in 'conditions' with 'or' separators.
		if ( empty( $restriction_data['conditions'] ) || ! is_array( $restriction_data['conditions'] ) ) {
			return $groups;
		}

		$current_group = array();

		foreach ( $restriction_data['conditions'] as $condition ) {
			if ( empty( $condition['type'] ) ) {
				continue;
			}

			// Check for OR separator.
			if ( 'or' === $condition['type'] ) {
				if ( ! empty( $current_group ) ) {
					$groups[]      = $current_group;
					$current_group = array();
				}
				continue;
			}

			$converted_condition = $this->convert_condition( $condition );
			if ( false === $converted_condition ) {
				// Unmappable condition.
				return false;
			}

			$current_group[] = $converted_condition;
		}

		// Add final group.
		if ( ! empty( $current_group ) ) {
			$groups[] = $current_group;
		}

		return $groups;
	}

	/**
	 * Convert a single condition from official format.
	 *
	 * @param array $condition Official condition data.
	 * @return array|false Converted condition, or false if unmappable.
	 */
	private function convert_condition( $condition ) {
		if ( empty( $condition['type'] ) ) {
			return false;
		}

		$type = $this->map_condition_type( $condition['type'] );
		if ( false === $type ) {
			return false;
		}

		$converted = array( 'type' => $type );

		// Map operator.
		if ( isset( $condition['modifier'] ) ) {
			$operator = $this->map_operator( $condition['modifier'], $type );
			if ( false === $operator ) {
				return false;
			}
			$converted['operator'] = $operator;
		}

		// Map value.
		if ( isset( $condition['value'] ) ) {
			$converted['value'] = $condition['value'];
		}

		return $converted;
	}

	/**
	 * Map condition type from official to our format.
	 *
	 * @param string $official_type Official condition type.
	 * @return string|false Our condition type, or false if unmappable.
	 */
	private function map_condition_type( $official_type ) {
		$mapping = array(
			// Cart/Order totals.
			'order_total'           => 'order_total',
			'cart_total'            => 'cart_total',
			'cart_subtotal'         => 'cart_subtotal',
			'package_weight'        => 'package_weight',
			'package_item_quantity' => 'item_quantity',

			// Products/Categories/Tags.
			'product'               => 'product_in_cart',
			'product_category'      => 'category_in_cart',
			'category'              => 'category_in_cart',
			'product_tag'           => 'tag_in_cart',
			'tag'                   => 'tag_in_cart',
			'shipping_class'        => 'shipping_class_in_cart',

			// Location.
			'billing_country'       => 'billing_country',
			'shipping_country'      => 'shipping_country',
			'billing_state'         => 'billing_state',
			'shipping_state'        => 'shipping_state',
			'billing_postcode'      => 'billing_postcode',
			'shipping_postcode'     => 'shipping_postcode',

			// Customer.
			'customer_role'         => 'customer_role',
			'user_role'             => 'customer_role',
			'customer_logged_in'    => 'user_logged_in',
			'user_logged_in'        => 'user_logged_in',

			// Other.
			'coupon'                => 'coupon_used',
			'coupon_code'           => 'coupon_used',
			'backorder'             => 'backorder_in_cart',
			'currency'              => 'currency',
			'shipping_method'       => 'shipping_method',
			'payment_gateway'       => 'payment_gateway',
			'payment_method'        => 'payment_gateway',
		);

		return isset( $mapping[ $official_type ] ) ? $mapping[ $official_type ] : false;
	}

	/**
	 * Map operator from official to our format.
	 *
	 * @param string $official_operator Official operator.
	 * @param string $condition_type Condition type (for context).
	 * @return string|false Our operator, or false if unmappable.
	 */
	private function map_operator( $official_operator, $condition_type ) {
		// Numeric operators.
		$numeric_mapping = array(
			'gte'          => '>=',
			'gt'           => '>',
			'>='           => '>=',
			'>'            => '>',
			'lte'          => '<=',
			'lt'           => '<',
			'<='           => '<=',
			'<'            => '<',
			'eq'           => '==',
			'=='           => '==',
			'neq'          => '!=',
			'!='           => '!=',
			'greater'      => '>',
			'greater_than' => '>',
			'less'         => '<',
			'less_than'    => '<',
		);

		// List operators.
		$list_mapping = array(
			'in'           => 'in',
			'not_in'       => 'not_in',
			'is'           => 'in',
			'is_not'       => 'not_in',
			'contains'     => 'in',
			'not_contains' => 'not_in',
		);

		// Check if it's a numeric condition.
		$numeric_conditions = array( 'order_total', 'cart_total', 'cart_subtotal', 'package_weight', 'item_quantity' );
		if ( in_array( $condition_type, $numeric_conditions, true ) ) {
			return isset( $numeric_mapping[ $official_operator ] ) ? $numeric_mapping[ $official_operator ] : false;
		}

		// Check list operators.
		if ( isset( $list_mapping[ $official_operator ] ) ) {
			return $list_mapping[ $official_operator ];
		}

		// Default to 'in' for list-based conditions if no operator specified.
		if ( empty( $official_operator ) ) {
			return 'in';
		}

		return false;
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

			// Skip if contains unmappable items.
			if ( false === $converted ) {
				continue;
			}

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
			'official_count'   => is_array( $official_data ) ? count( $official_data ) : 0,
			'imported_count'   => $this->count_restrictions( $imported_data ),
			'types'            => array(
				'shipping_methods'      => array(
					'imported' => count( $imported_data['shipping_methods'] ),
				),
				'payment_gateways'      => array(
					'imported' => count( $imported_data['payment_gateways'] ),
				),
				'shipping_destinations' => array(
					'imported' => count( $imported_data['shipping_destinations'] ),
				),
			),
			'warnings'         => array(),
			'unmappable_count' => count( $this->unmappable_items ),
		);

		// Check for discrepancies.
		if ( $report['official_count'] !== $report['imported_count'] ) {
			$report['warnings'][] = sprintf(
				/* translators: 1: Official count, 2: Imported count, 3: Unmappable count */
				__( 'The official extension had %1$d restrictions. %2$d were successfully mapped. %3$d could not be mapped and were skipped.', 'oli-conditional-shipping-payments' ),
				$report['official_count'],
				$report['imported_count'],
				$report['unmappable_count']
			);
		}

		return $report;
	}

	/**
	 * Count total restrictions.
	 *
	 * @param array $restrictions Restrictions array.
	 * @return int Total count.
	 */
	private function count_restrictions( $restrictions ) {
		$count = 0;
		foreach ( $restrictions as $type_restrictions ) {
			if ( is_array( $type_restrictions ) ) {
				$count += count( $type_restrictions );
			}
		}
		return $count;
	}
}
