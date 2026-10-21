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
	 * Invalid JSON import returns a WP_Error.
	 */
	public function test_import_from_json_rejects_invalid_payload() {
		$result = Oli_CSP_Utils::import_from_json( '{not-json' );
		$this->assertInstanceOf( 'WP_Error', $result );
	}
}
