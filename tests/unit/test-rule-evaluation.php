<?php
/**
 * Standalone rule-evaluation tests (no WooCommerce required).
 *
 * @package Oli_Conditional_Shipping_Payments
 */

use PHPUnit\Framework\TestCase;

/**
 * Class Test_Oli_CSP_Rule_Evaluation
 */
class Test_Oli_CSP_Rule_Evaluation extends TestCase {

	/**
	 * Condition engine.
	 *
	 * @var Oli_CSP_Condition_Engine
	 */
	private $engine;

	/**
	 * Create the engine.
	 */
	protected function setUp(): void {
		if ( ! defined( 'ABSPATH' ) ) {
			define( 'ABSPATH', dirname( __DIR__, 2 ) . '/' );
		}

		if ( ! class_exists( 'Oli_CSP_Condition_Engine' ) ) {
			require_once dirname( __DIR__, 2 ) . '/includes/class-oli-csp-condition-engine.php';
		}

		$this->engine = new Oli_CSP_Condition_Engine();
	}

	/**
	 * Empty groups always match.
	 */
	public function test_empty_groups_always_match() {
		$this->assertTrue( $this->engine->evaluate_condition_groups( array(), 'checkout' ) );
		$this->assertTrue( $this->engine->evaluate_conditions( array(), 'checkout' ) );
	}

	/**
	 * AND logic inside a group.
	 */
	public function test_and_logic_inside_group() {
		$data = array(
			'cart_subtotal' => 80,
			'cart_weight'   => 2.5,
		);

		$group = array(
			array(
				'type'     => 'cart_subtotal',
				'operator' => '>=',
				'value'    => 50,
			),
			array(
				'type'     => 'package_weight',
				'operator' => '<',
				'value'    => 5,
			),
		);

		$this->assertTrue( $this->engine->evaluate_with_data( array( $group ), $data ) );

		$group[1]['value'] = 1;
		$this->assertFalse( $this->engine->evaluate_with_data( array( $group ), $data ) );
	}

	/**
	 * OR logic between groups.
	 */
	public function test_or_logic_between_groups() {
		$data = array(
			'cart_subtotal'  => 20,
			'customer_roles' => array( 'customer' ),
		);

		$groups = array(
			array(
				array(
					'type'     => 'cart_subtotal',
					'operator' => '>=',
					'value'    => 100,
				),
			),
			array(
				array(
					'type'     => 'customer_role',
					'operator' => 'in',
					'value'    => array( 'customer' ),
				),
			),
		);

		$this->assertTrue( $this->engine->evaluate_with_data( $groups, $data ) );

		$data['customer_roles'] = array( 'subscriber' );
		$this->assertFalse( $this->engine->evaluate_with_data( $groups, $data ) );
	}

	/**
	 * Cart subtotal, weight, and quantity.
	 */
	public function test_cart_totals_weight_and_quantity() {
		$data = array(
			'cart_subtotal' => 49.99,
			'cart_weight'   => 10,
			'cart_quantity' => 3,
		);

		$this->assertTrue(
			$this->engine->evaluate_with_data(
				array(
					array(
						array(
							'type'     => 'cart_subtotal',
							'operator' => '<',
							'value'    => 50,
						),
					),
				),
				$data
			)
		);

		$this->assertTrue(
			$this->engine->evaluate_with_data(
				array(
					array(
						array(
							'type'     => 'package_weight',
							'operator' => '>=',
							'value'    => 10,
						),
					),
				),
				$data
			)
		);

		$this->assertFalse(
			$this->engine->evaluate_with_data(
				array(
					array(
						array(
							'type'     => 'item_quantity',
							'operator' => '>',
							'value'    => 3,
						),
					),
				),
				$data
			)
		);
	}

	/**
	 * Product, category, tag, and shipping class presence.
	 */
	public function test_catalog_conditions() {
		$data = array(
			'cart_product_ids'        => array( '11', '22' ),
			'cart_category_ids'       => array( '5' ),
			'cart_tag_ids'            => array( '9' ),
			'cart_shipping_class_ids' => array( '3' ),
		);

		$this->assertTrue(
			$this->engine->evaluate_with_data(
				array(
					array(
						array(
							'type'     => 'product_in_cart',
							'operator' => 'in',
							'value'    => array( '22' ),
						),
					),
				),
				$data
			)
		);

		$this->assertFalse(
			$this->engine->evaluate_with_data(
				array(
					array(
						array(
							'type'     => 'category_in_cart',
							'operator' => 'in',
							'value'    => array( '8' ),
						),
					),
				),
				$data
			)
		);

		$this->assertTrue(
			$this->engine->evaluate_with_data(
				array(
					array(
						array(
							'type'     => 'tag_in_cart',
							'operator' => 'not_in',
							'value'    => array( '4' ),
						),
					),
				),
				$data
			)
		);

		$this->assertTrue(
			$this->engine->evaluate_with_data(
				array(
					array(
						array(
							'type'     => 'shipping_class_in_cart',
							'operator' => 'is',
							'value'    => array( '3' ),
						),
					),
				),
				$data
			)
		);
	}

	/**
	 * Location conditions, including postcode wildcards and ranges.
	 */
	public function test_location_conditions() {
		$data = array(
			'billing_country'   => 'US',
			'billing_state'     => 'CA',
			'billing_postcode'  => '90210',
			'shipping_country'  => 'CA',
			'shipping_state'    => 'QC',
			'shipping_postcode' => 'H2X1Y4',
		);

		$this->assertTrue(
			$this->engine->evaluate_with_data(
				array(
					array(
						array(
							'type'     => 'billing_country',
							'operator' => 'in',
							'value'    => array( 'US', 'MX' ),
						),
						array(
							'type'     => 'billing_state',
							'operator' => 'in',
							'value'    => 'CA, NY',
						),
						array(
							'type'     => 'billing_postcode',
							'operator' => 'in',
							'value'    => array( '902*' ),
						),
					),
				),
				$data
			)
		);

		$this->assertTrue(
			$this->engine->evaluate_with_data(
				array(
					array(
						array(
							'type'     => 'shipping_country',
							'operator' => 'not_in',
							'value'    => array( 'US' ),
						),
						array(
							'type'     => 'shipping_postcode',
							'operator' => 'in',
							'value'    => array( 'H2X1Y1-H2X1Y9' ),
						),
					),
				),
				$data
			)
		);

		$this->assertFalse(
			$this->engine->evaluate_with_data(
				array(
					array(
						array(
							'type'     => 'billing_postcode',
							'operator' => 'in',
							'value'    => array( '90310-90320' ),
						),
					),
				),
				$data
			)
		);
	}

	/**
	 * Customer role, login, and coupon.
	 */
	public function test_customer_and_coupon_conditions() {
		$data = array(
			'customer_roles' => array( 'wholesale' ),
			'user_logged_in' => true,
			'applied_coupons' => array( 'SAVE10' ),
		);

		$this->assertTrue(
			$this->engine->evaluate_with_data(
				array(
					array(
						array(
							'type'     => 'customer_role',
							'operator' => 'in',
							'value'    => array( 'wholesale' ),
						),
						array(
							'type'  => 'user_logged_in',
							'value' => 'yes',
						),
						array(
							'type'     => 'coupon_used',
							'operator' => 'in',
							'value'    => array( 'save10' ),
						),
					),
				),
				$data
			)
		);

		$this->assertFalse(
			$this->engine->evaluate_with_data(
				array(
					array(
						array(
							'type'  => 'user_logged_in',
							'value' => 'no',
						),
					),
				),
				$data
			)
		);
	}

	/**
	 * Public comparison helpers.
	 */
	public function test_numeric_and_postcode_helpers() {
		$this->assertTrue(
			$this->engine->compare_numeric_value(
				100,
				array(
					'operator' => 'greater',
					'value'    => 50,
				)
			)
		);
		$this->assertFalse(
			$this->engine->compare_numeric_value(
				100,
				array(
					'operator' => 'less',
					'value'    => 50,
				)
			)
		);
		$this->assertTrue( $this->engine->match_postcode( 'SW1A 1AA', array( 'SW1A*' ) ) );
		$this->assertTrue( $this->engine->match_postcode( '90215', array( '90210-90220' ) ) );
		$this->assertFalse( $this->engine->match_postcode( '10001', array( '902*' ) ) );
		$this->assertTrue(
			$this->engine->compare_list_value(
				'US',
				array(
					'operator' => 'in',
					'value'    => array( 'US', 'CA' ),
				)
			)
		);
		$this->assertTrue(
			$this->engine->compare_list_value(
				'FR',
				array(
					'operator' => 'not_in',
					'value'    => array( 'US', 'CA' ),
				)
			)
		);
	}
}
