<?php
/**
 * Admin class.
 *
 * Handles admin functionality.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Oli_CSP_Admin
 */
class Oli_CSP_Admin {

	/**
	 * Settings page instance.
	 *
	 * @var Oli_CSP_Admin_Settings
	 */
	private $settings_page;

	/**
	 * Product meta instance.
	 *
	 * @var Oli_CSP_Admin_Product
	 */
	private $product_meta;

	/**
	 * Rule UI renderer.
	 *
	 * @var Oli_CSP_Admin_Rule_UI
	 */
	private $rule_ui;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->rule_ui       = new Oli_CSP_Admin_Rule_UI();
		$this->settings_page = new Oli_CSP_Admin_Settings( $this->rule_ui );
		$this->product_meta  = new Oli_CSP_Admin_Product( $this->rule_ui );

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'plugin_action_links_' . OLI_CSP_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		if ( ! $this->is_plugin_admin_screen( $hook ) ) {
			return;
		}

		wp_enqueue_style(
			'oli-csp-admin',
			OLI_CSP_URL . 'assets/css/admin.css',
			array( 'woocommerce_admin_styles' ),
			OLI_CSP_VERSION
		);

		wp_enqueue_script(
			'oli-csp-admin',
			OLI_CSP_URL . 'assets/js/admin.js',
			array( 'jquery', 'wc-enhanced-select' ),
			OLI_CSP_VERSION,
			true
		);

		$script_data          = $this->rule_ui->get_script_data();
		$script_data['nonce'] = wp_create_nonce( 'oli_csp_admin' );
		$script_data['i18n']  = array_merge(
			$script_data['i18n'],
			array(
				'addCondition'    => __( 'Add Condition', 'oli-conditional-shipping-payments' ),
				'removeCondition' => __( 'Remove Condition', 'oli-conditional-shipping-payments' ),
				'addRule'         => __( 'Add Rule', 'oli-conditional-shipping-payments' ),
				'removeRule'      => __( 'Remove Rule', 'oli-conditional-shipping-payments' ),
				'confirmDelete'   => __( 'Are you sure you want to delete this restriction?', 'oli-conditional-shipping-payments' ),
			)
		);

		wp_localize_script( 'oli-csp-admin', 'oliCspAdmin', $script_data );
	}

	/**
	 * Whether the current screen is this plugin's UI.
	 *
	 * @param string $hook Current admin page hook.
	 * @return bool
	 */
	private function is_plugin_admin_screen( $hook ) {
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen ) {
			return false;
		}

		if ( 'product' === $screen->id ) {
			return true;
		}

		if ( 'woocommerce_page_wc-settings' === $hook ) {
			// WooCommerce resolves the current settings tab on load-woocommerce_page_wc-settings; no request input is read here.
			global $current_tab;
			return 'restrictions' === $current_tab;
		}

		return false;
	}

	/**
	 * Add plugin action links.
	 *
	 * @param array $links Plugin action links.
	 * @return array Modified links.
	 */
	public function plugin_action_links( $links ) {
		$settings_link = sprintf(
			'<a href="%s">%s</a>',
			esc_url( admin_url( 'admin.php?page=wc-settings&tab=restrictions' ) ),
			esc_html__( 'Settings', 'oli-conditional-shipping-payments' )
		);

		array_unshift( $links, $settings_link );

		return $links;
	}
}
