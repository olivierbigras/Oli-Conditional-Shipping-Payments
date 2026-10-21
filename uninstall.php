<?php
/**
 * Uninstall cleanup.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

$oli_csp_options = array(
	'oli_csp_global_restrictions',
	'oli_csp_settings',
	'oli_csp_version',
);

foreach ( $oli_csp_options as $oli_csp_option ) {
	delete_option( $oli_csp_option );
	delete_site_option( $oli_csp_option );
}

delete_transient( 'oli_csp_import_report' );
delete_metadata( 'post', 0, '_oli_csp_restrictions', '', true );
