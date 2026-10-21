<?php
/**
 * PHPUnit bootstrap file.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

$_tests_dir = getenv( 'WP_TESTS_DIR' );

if ( ! $_tests_dir ) {
	$_tests_dir = rtrim( sys_get_temp_dir(), '/\\' ) . '/wordpress-tests-lib';
}

if ( ! file_exists( $_tests_dir . '/includes/functions.php' ) ) {
	echo "Could not find {$_tests_dir}/includes/functions.php\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
	exit( 1 );
}

require_once $_tests_dir . '/includes/functions.php';

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
