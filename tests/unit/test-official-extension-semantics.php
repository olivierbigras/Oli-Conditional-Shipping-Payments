<?php
/**
 * Condition Engine Tests - Official Extension Semantics Comparison.
 *
 * These tests verify our engine produces identical results to the official
 * WooCommerce Conditional Shipping and Payments extension.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

/**
 * Class Test_Oli_CSP_Engine_Official_Semantics
 */
class Test_Oli_CSP_Engine_Official_Semantics extends WP_UnitTestCase {

	/**
	 * Condition engine instance.
	 *
	 * @var Oli_CSP_Condition_Engine
	 */
	private $engine;

	/**
	 * Set up test fixtures.
	 */
	public function setUp(): void {
		parent::setUp();

		if ( ! class_exists( 'WooCommerce' ) || ! class_exists( 'WC_Helper_Product' ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers are not available.' );
		}

		$this->engine = new Oli_CSP_Condition_Engine();

		WC()->initialize_cart();
		WC()->initialize_session();
	}

	/**
	 * Test OR logic between condition groups (official extension behavior).
	 */
	public function test_or_logic_between_condition_groups() {
		if ( ! class_exists( 'WC_Helper_Product' ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers not available' );
		}

		WC()->cart->empty_cart();
		$product = WC_Helper_Product::create_simple_product();
		$product->set_price( 50 );
		$product->save();
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		$user_id = $this->factory->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $user_id );

		// Official extension: (cart_total >= 100) OR (customer_role = customer).
		// Group 1: cart_total >= 100 (false).
		// Group 2: customer_role = customer (true).
		// Result: true (because group 2 matches).
		$condition_groups = array(
			array(
				array(
					'type'     => 'cart_total',
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

		$this->assertTrue( $this->engine->evaluate_condition_groups( $condition_groups, 'checkout' ) );
	}

	/**
	 * Test AND logic within a condition group (official extension behavior).
	 */
	public function test_and_logic_within_group() {
		if ( ! class_exists( 'WC_Helper_Product' ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers not available' );
		}

		WC()->cart->empty_cart();
		$product = WC_Helper_Product::create_simple_product();
		$product->set_price( 50 );
		$product->save();
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		$user_id = $this->factory->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $user_id );

		// Official extension: (cart_total >= 40) AND (customer_role = customer).
		// Both must match.
		$condition_group = array(
			array(
				'type'     => 'cart_total',
				'operator' => '>=',
				'value'    => 40,
			),
			array(
				'type'     => 'customer_role',
				'operator' => 'in',
				'value'    => array( 'customer' ),
			),
		);

		$this->assertTrue( $this->engine->evaluate_condition_group( $condition_group, 'checkout' ) );

		// Change cart total condition to fail.
		$condition_group[0]['value'] = 100;
		$this->assertFalse( $this->engine->evaluate_condition_group( $condition_group, 'checkout' ) );
	}

	/**
	 * Test 'in' operator (official extension behavior).
	 */
	public function test_in_operator() {
		if ( ! class_exists( 'WC_Helper_Product' ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers not available' );
		}

		WC()->cart->empty_cart();
		$product1 = WC_Helper_Product::create_simple_product();
		$product2 = WC_Helper_Product::create_simple_product();
		
		WC()->cart->add_to_cart( $product1->get_id(), 1 );

		// Official extension: product IN [product1, product2].
		$condition = array(
			'type'     => 'product_in_cart',
			'operator' => 'in',
			'value'    => array( (string) $product1->get_id(), (string) $product2->get_id() ),
		);
		$this->assertTrue( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );

		// Product not in list.
		$condition['value'] = array( (string) $product2->get_id() );
		$this->assertFalse( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );
	}

	/**
	 * Test 'not_in' operator (official extension behavior).
	 */
	public function test_not_in_operator() {
		if ( ! class_exists( 'WC_Helper_Product' ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers not available' );
		}

		WC()->cart->empty_cart();
		$product1 = WC_Helper_Product::create_simple_product();
		$product2 = WC_Helper_Product::create_simple_product();
		
		WC()->cart->add_to_cart( $product1->get_id(), 1 );

		// Official extension: product NOT IN [product2].
		$condition = array(
			'type'     => 'product_in_cart',
			'operator' => 'not_in',
			'value'    => array( (string) $product2->get_id() ),
		);
		$this->assertTrue( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );

		// Product IS in exclusion list.
		$condition['value'] = array( (string) $product1->get_id() );
		$this->assertFalse( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );
	}

	/**
	 * Test 'is_not' operator (official extension behavior).
	 */
	public function test_is_not_operator() {
		if ( ! class_exists( 'WC_Helper_Product' ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers not available' );
		}

		WC()->cart->empty_cart();
		$product = WC_Helper_Product::create_simple_product();
		$product->set_price( 100 );
		$product->save();
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		// Official extension: cart_total IS NOT 50.
		$condition = array(
			'type'     => 'cart_total',
			'operator' => '!=',
			'value'    => 50,
		);
		$this->assertTrue( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );

		// Cart total IS 100.
		$condition['value'] = 100;
		$this->assertFalse( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );
	}

	/**
	 * Test 'greater' operator (official extension behavior).
	 */
	public function test_greater_operator() {
		if ( ! class_exists( 'WC_Helper_Product' ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers not available' );
		}

		WC()->cart->empty_cart();
		$product = WC_Helper_Product::create_simple_product();
		$product->set_price( 100 );
		$product->save();
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		// Official extension: cart_total GREATER 50.
		$condition = array(
			'type'     => 'cart_total',
			'operator' => '>',
			'value'    => 50,
		);
		$this->assertTrue( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );

		// Not greater than 100.
		$condition['value'] = 100;
		$this->assertFalse( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );

		// Not greater than 150.
		$condition['value'] = 150;
		$this->assertFalse( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );
	}

	/**
	 * Test 'less' operator (official extension behavior).
	 */
	public function test_less_operator() {
		if ( ! class_exists( 'WC_Helper_Product' ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers not available' );
		}

		WC()->cart->empty_cart();
		$product = WC_Helper_Product::create_simple_product();
		$product->set_price( 50 );
		$product->save();
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		// Official extension: cart_total LESS 100.
		$condition = array(
			'type'     => 'cart_total',
			'operator' => '<',
			'value'    => 100,
		);
		$this->assertTrue( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );

		// Not less than 50.
		$condition['value'] = 50;
		$this->assertFalse( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );

		// Not less than 10.
		$condition['value'] = 10;
		$this->assertFalse( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );
	}

	/**
	 * Test postcode wildcard (official extension behavior).
	 */
	public function test_postcode_wildcard_official_semantics() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$customer = WC()->customer;
		$customer->set_shipping_postcode( '90210' );

		// Official extension: postcode CONTAINS '902*'.
		$condition = array(
			'type'  => 'shipping_postcode',
			'value' => array( '902*' ),
		);
		$this->assertTrue( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );

		// Doesn't match.
		$condition['value'] = array( '903*' );
		$this->assertFalse( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );

		// Multiple patterns (OR within patterns).
		$condition['value'] = array( '903*', '902*' );
		$this->assertTrue( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );
	}

	/**
	 * Test postcode range (official extension behavior).
	 */
	public function test_postcode_range_official_semantics() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$customer = WC()->customer;
		$customer->set_shipping_postcode( '90215' );

		// Official extension: postcode IN RANGE 90210-90220.
		$condition = array(
			'type'  => 'shipping_postcode',
			'value' => array( '90210-90220' ),
		);
		$this->assertTrue( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );

		// Out of range.
		$customer->set_shipping_postcode( '90225' );
		$this->assertFalse( $this->engine->evaluate_condition_group( array( $condition ), 'checkout' ) );
	}

	/**
	 * Test complex scenario (official extension behavior).
	 */
	public function test_complex_official_scenario() {
		if ( ! class_exists( 'WC_Helper_Product' ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers not available' );
		}

		WC()->cart->empty_cart();
		$product = WC_Helper_Product::create_simple_product();
		$product->set_price( 75 );
		$product->save();
		WC()->cart->add_to_cart( $product->get_id(), 2 );

		$user_id = $this->factory->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $user_id );

		$customer = WC()->customer;
		$customer->set_shipping_country( 'US' );

		// Official extension: 
		// (cart_total >= 100 AND country = US) OR (customer_role = administrator).
		// Group 1: cart_total >= 100 (true, cart is 150) AND country = US (true) => true.
		// Group 2: customer_role = administrator (false).
		// Result: true (because group 1 matches).
		$condition_groups = array(
			array(
				array(
					'type'     => 'cart_total',
					'operator' => '>=',
					'value'    => 100,
				),
				array(
					'type'     => 'shipping_country',
					'operator' => 'in',
					'value'    => array( 'US' ),
				),
			),
			array(
				array(
					'type'     => 'customer_role',
					'operator' => 'in',
					'value'    => array( 'administrator' ),
				),
			),
		);

		$this->assertTrue( $this->engine->evaluate_condition_groups( $condition_groups, 'checkout' ) );

		// Change country to CA - should fail group 1, but still true if group 2 passed.
		$customer->set_shipping_country( 'CA' );
		$this->assertFalse( $this->engine->evaluate_condition_groups( $condition_groups, 'checkout' ) );
	}

	/**
	 * Test empty conditions (official extension behavior: always pass).
	 */
	public function test_empty_conditions_official_semantics() {
		// Official extension: No conditions = always apply.
		$this->assertTrue( $this->engine->evaluate_condition_groups( array(), 'checkout' ) );
		$this->assertTrue( $this->engine->evaluate_condition_group( array(), 'checkout' ) );
	}

	/**
	 * Test backward compatibility with old format (no groups).
	 */
	public function test_backward_compatibility_no_groups() {
		if ( ! class_exists( 'WC_Helper_Product' ) ) {
			$this->markTestSkipped( 'WooCommerce test helpers not available' );
		}

		WC()->cart->empty_cart();
		$product = WC_Helper_Product::create_simple_product();
		$product->set_price( 100 );
		$product->save();
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		// Old format: Just conditions array (AND logic).
		$conditions = array(
			array(
				'type'     => 'cart_total',
				'operator' => '>=',
				'value'    => 50,
			),
		);

		$this->assertTrue( $this->engine->evaluate_conditions( $conditions, 'checkout' ) );
	}
}
