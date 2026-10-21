<?php
/**
 * Condition Engine Tests.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

/**
 * Class Test_Oli_CSP_Condition_Engine
 */
class Test_Oli_CSP_Condition_Engine extends WP_UnitTestCase {

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
	 * Test cart total condition.
	 */
	public function test_cart_total_condition() {
		// Mock cart with specific total.
		WC()->cart->empty_cart();
		$product = WC_Helper_Product::create_simple_product();
		$product->set_price( 100 );
		$product->save();
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		// Test >= operator.
		$condition = array(
			'type'     => 'cart_total',
			'operator' => '>=',
			'value'    => 100,
		);
		$this->assertTrue( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );

		$condition['value'] = 200;
		$this->assertFalse( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );

		// Test < operator.
		$condition['operator'] = '<';
		$this->assertTrue( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );
	}

	/**
	 * Test product in cart condition.
	 */
	public function test_product_in_cart_condition() {
		WC()->cart->empty_cart();
		$product1 = WC_Helper_Product::create_simple_product();
		$product2 = WC_Helper_Product::create_simple_product();
		
		WC()->cart->add_to_cart( $product1->get_id(), 1 );

		// Test with product in cart.
		$condition = array(
			'type'  => 'product_in_cart',
			'value' => array( (string) $product1->get_id() ),
		);
		$this->assertTrue( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );

		// Test with product not in cart.
		$condition['value'] = array( (string) $product2->get_id() );
		$this->assertFalse( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );
	}

	/**
	 * Test customer role condition.
	 */
	public function test_customer_role_condition() {
		$user_id = $this->factory->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $user_id );

		// Test matching role.
		$condition = array(
			'type'  => 'customer_role',
			'value' => array( 'customer' ),
		);
		$this->assertTrue( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );

		// Test non-matching role.
		$condition['value'] = array( 'administrator' );
		$this->assertFalse( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );
	}

	/**
	 * Test user logged in condition.
	 */
	public function test_user_logged_in_condition() {
		// Test logged out.
		wp_set_current_user( 0 );
		$condition = array(
			'type'  => 'user_logged_in',
			'value' => 'yes',
		);
		$this->assertFalse( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );

		// Test logged in.
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );
		$this->assertTrue( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );

		// Test inverted condition.
		$condition['value'] = 'no';
		$this->assertFalse( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );
	}

	/**
	 * Test postcode matching with wildcards.
	 */
	public function test_postcode_wildcard_matching() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$customer = WC()->customer;
		$customer->set_shipping_postcode( '90210' );

		// Test exact match.
		$condition = array(
			'type'  => 'shipping_postcode',
			'value' => array( '90210' ),
		);
		$this->assertTrue( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );

		// Test wildcard match.
		$condition['value'] = array( '902*' );
		$this->assertTrue( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );

		// Test non-match.
		$condition['value'] = array( '903*' );
		$this->assertFalse( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );
	}

	/**
	 * Test postcode range matching.
	 */
	public function test_postcode_range_matching() {
		$user_id = $this->factory->user->create();
		wp_set_current_user( $user_id );

		$customer = WC()->customer;
		$customer->set_shipping_postcode( '90215' );

		// Test range match.
		$condition = array(
			'type'  => 'shipping_postcode',
			'value' => array( '90210-90220' ),
		);
		$this->assertTrue( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );

		// Test range non-match.
		$customer->set_shipping_postcode( '90225' );
		$this->assertFalse( $this->engine->evaluate_conditions( array( $condition ), 'checkout' ) );
	}

	/**
	 * Test multiple conditions with AND logic.
	 */
	public function test_multiple_conditions_and_logic() {
		WC()->cart->empty_cart();
		$product = WC_Helper_Product::create_simple_product();
		$product->set_price( 100 );
		$product->save();
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		$user_id = $this->factory->user->create( array( 'role' => 'customer' ) );
		wp_set_current_user( $user_id );

		// Test all conditions matching.
		$conditions = array(
			array(
				'type'     => 'cart_total',
				'operator' => '>=',
				'value'    => 50,
			),
			array(
				'type'  => 'customer_role',
				'value' => array( 'customer' ),
			),
			array(
				'type'  => 'user_logged_in',
				'value' => 'yes',
			),
		);
		$this->assertTrue( $this->engine->evaluate_conditions( $conditions, 'checkout' ) );

		// Test one condition not matching (should fail all).
		$conditions[1]['value'] = array( 'administrator' );
		$this->assertFalse( $this->engine->evaluate_conditions( $conditions, 'checkout' ) );
	}

	/**
	 * Test empty conditions (should always pass).
	 */
	public function test_empty_conditions() {
		$this->assertTrue( $this->engine->evaluate_conditions( array(), 'checkout' ) );
		$this->assertTrue( $this->engine->evaluate_conditions( null, 'checkout' ) );
	}

	/**
	 * Test numeric operators.
	 */
	public function test_numeric_operators() {
		WC()->cart->empty_cart();
		$product = WC_Helper_Product::create_simple_product();
		$product->set_price( 100 );
		$product->save();
		WC()->cart->add_to_cart( $product->get_id(), 1 );

		$base_condition = array(
			'type'  => 'cart_total',
			'value' => 100,
		);

		// Test each operator.
		$operators = array( '>=', '>', '<=', '<', '==', '!=' );
		$expected_results = array( true, false, true, false, true, false );

		foreach ( $operators as $index => $operator ) {
			$condition = $base_condition;
			$condition['operator'] = $operator;
			$result = $this->engine->evaluate_conditions( array( $condition ), 'checkout' );
			$this->assertEquals( $expected_results[ $index ], $result, "Operator $operator failed" );
		}
	}
}
