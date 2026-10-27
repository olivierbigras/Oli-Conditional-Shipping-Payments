<?php
/**
 * Utils sanitization tests.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

/**
 * Class Test_Oli_CSP_Utils_Sanitization
 */
class Test_Oli_CSP_Utils_Sanitization extends WP_UnitTestCase {

	/**
	 * Condition groups and zones survive sanitization.
	 */
	public function test_sanitize_restriction_data_keeps_groups_and_zones() {
		$data = array(
			'shipping_methods' => array(
				array(
					'excluded_methods' => array( 'flat_rate' ),
					'zones'            => array( '1', '3', '0' ),
					'condition_groups' => array(
						array(
							array(
								'type'     => 'cart_total',
								'operator' => '>=',
								'value'    => '100',
							),
						),
					),
				),
			),
		);

		$sanitized = Oli_CSP_Utils::sanitize_restriction_data( $data );

		$this->assertSame( array( 'flat_rate' ), $sanitized['shipping_methods'][0]['excluded_methods'] );
		$this->assertSame( array( 1, 3, 0 ), $sanitized['shipping_methods'][0]['zones'] );
		$this->assertSame( 'cart_total', $sanitized['shipping_methods'][0]['condition_groups'][0][0]['type'] );
		$this->assertSame( '>=', $sanitized['shipping_methods'][0]['condition_groups'][0][0]['operator'] );
		$this->assertSame( '100', $sanitized['shipping_methods'][0]['condition_groups'][0][0]['value'] );
	}

	/**
	 * The read-time map_deep( sanitize_textarea_field ) pass leaves form rules unchanged.
	 *
	 * Mirrors the save handlers: multi-line postcode lists, numbers, nested
	 * condition groups, zones and messages must come out identical.
	 */
	public function test_read_time_textarea_pass_keeps_rules_identical() {
		$form = array(
			'shipping_methods' => array(
				array(
					'title'            => 'Remote areas',
					'enabled'          => 'yes',
					'excluded_methods' => array( 'flat_rate', 'local_pickup:2' ),
					'zones'            => array( '1', '3' ),
					'message'          => "Line one\nLine two",
					'condition_groups' => array(
						array(
							array(
								'type'     => 'shipping_postcode',
								'operator' => 'in',
								'value'    => "H2X*\nJ4B 1A1\n90210",
							),
							array(
								'type'     => 'cart_total',
								'operator' => '>=',
								'value'    => '49.95',
							),
						),
						array(
							array(
								'type'     => 'package_weight',
								'operator' => '<',
								'value'    => '5',
							),
						),
					),
				),
			),
			'payment_gateways' => array(
				array(
					'excluded_gateways' => array( 'cod' ),
					'condition_groups'  => array(
						array(
							array(
								'type'     => 'shipping_country',
								'operator' => 'in',
								'value'    => array( 'CA', 'US' ),
							),
						),
					),
				),
			),
		);

		$direct    = Oli_CSP_Utils::sanitize_restriction_data( $form );
		$read_time = Oli_CSP_Utils::sanitize_restriction_data( map_deep( wp_unslash( $form ), 'sanitize_textarea_field' ) );

		$this->assertSame( $direct, $read_time );
		$this->assertSame( "Line one\nLine two", $read_time['shipping_methods'][0]['message'] );
		$this->assertSame( array( 1, 3 ), $read_time['shipping_methods'][0]['zones'] );
		$this->assertCount( 2, $read_time['shipping_methods'][0]['condition_groups'] );
		$this->assertSame( array( 'CA', 'US' ), $read_time['payment_gateways'][0]['condition_groups'][0][0]['value'] );
	}

	/**
	 * Invalid JSON import returns a WP_Error.
	 */
	public function test_import_from_json_rejects_invalid_payload() {
		$result = Oli_CSP_Utils::import_from_json( '{not-json' );
		$this->assertInstanceOf( 'WP_Error', $result );
	}
}
