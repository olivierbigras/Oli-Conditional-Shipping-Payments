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
	 * Constructor.
	 */
	public function __construct() {
		$this->settings_page = new Oli_CSP_Admin_Settings();
		$this->product_meta  = new Oli_CSP_Admin_Product();

		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_filter( 'plugin_action_links_' . OLI_CSP_BASENAME, array( $this, 'plugin_action_links' ) );
	}

	/**
	 * Enqueue admin scripts and styles.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		$screen = get_current_screen();

		// Only load on relevant pages.
		if ( ! $screen || ( 'woocommerce_page_wc-settings' !== $hook && 'product' !== $screen->id ) ) {
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

		wp_localize_script(
			'oli-csp-admin',
			'oliCspAdmin',
			array(
				'nonce' => wp_create_nonce( 'oli_csp_admin' ),
				'i18n'  => array(
					'addCondition'     => __( 'Add Condition', 'oli-conditional-shipping-payments' ),
					'removeCondition'  => __( 'Remove Condition', 'oli-conditional-shipping-payments' ),
					'addRule'          => __( 'Add Rule', 'oli-conditional-shipping-payments' ),
					'removeRule'       => __( 'Remove Rule', 'oli-conditional-shipping-payments' ),
					'confirmDelete'    => __( 'Are you sure you want to delete this restriction?', 'oli-conditional-shipping-payments' ),
					/* translators: %d: restriction number */
					'restrictionLabel' => __( 'Restriction #%d', 'oli-conditional-shipping-payments' ),
					'saveToConfigure'  => __( 'Save settings to keep this restriction.', 'oli-conditional-shipping-payments' ),
				),
			)
		);
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
