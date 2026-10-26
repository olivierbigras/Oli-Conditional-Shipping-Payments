<?php
/**
 * PHPUnit bootstrap file.
 *
 * Loads the WordPress test suite when present. Otherwise loads plugin classes
 * so rule-evaluation tests can run standalone.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

$oli_csp_autoload = dirname( __DIR__ ) . '/vendor/autoload.php';
if ( file_exists( $oli_csp_autoload ) ) {
	require_once $oli_csp_autoload;
}

$oli_csp_wp_functions = $_tests_dir . '/includes/functions.php';

if ( ! file_exists( $oli_csp_wp_functions ) ) {
	if ( ! defined( 'ABSPATH' ) ) {
		define( 'ABSPATH', dirname( __DIR__ ) . '/' );
	}

	if ( ! class_exists( 'WP_UnitTestCase', false ) && class_exists( 'PHPUnit\\Framework\\TestCase' ) ) {
		/**
		 * Skip WordPress-dependent tests when the test suite is not installed.
		 */
		class WP_UnitTestCase extends PHPUnit\Framework\TestCase {
			/**
			 * Skip the case when WordPress is unavailable.
			 */
			protected function setUp(): void {
				$this->markTestSkipped( 'WordPress test suite is not installed.' );
			}
		}
	}

	require_once dirname( __DIR__ ) . '/includes/class-oli-csp-utils.php';
	require_once dirname( __DIR__ ) . '/includes/class-oli-csp-condition-engine.php';
	return;
}

require_once $oli_csp_wp_functions;

/**
 * Load WooCommerce when present, then the plugin (or its class files).
 */
function oli_csp_manually_load_plugin() {
	$wc_candidates = array(
		getenv( 'WC_DIR' ),
		dirname( __DIR__ ) . '/woocommerce',
		sys_get_temp_dir() . '/wordpress/wp-content/plugins/woocommerce',
		'/tmp/wordpress/wp-content/plugins/woocommerce',
	);

	foreach ( $wc_candidates as $wc_dir ) {
		if ( $wc_dir && file_exists( $wc_dir . '/woocommerce.php' ) ) {
			require_once $wc_dir . '/woocommerce.php';
			$helper = $wc_dir . '/tests/legacy/framework/helpers/class-wc-helper-product.php';
			if ( file_exists( $helper ) ) {
				require_once $helper;
			}
			break;
		}
	}

	require dirname( __DIR__ ) . '/oli-conditional-shipping-payments.php';

	if ( ! class_exists( 'Oli_CSP_Utils' ) ) {
		require_once dirname( __DIR__ ) . '/includes/class-oli-csp-utils.php';
		require_once dirname( __DIR__ ) . '/includes/class-oli-csp-condition-engine.php';
	}
}
tests_add_filter( 'muplugins_loaded', 'oli_csp_manually_load_plugin' );

require $_tests_dir . '/includes/bootstrap.php';
