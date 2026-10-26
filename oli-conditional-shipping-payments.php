<?php
/**
 * Plugin Name: Oli Conditional Shipping and Payments for WooCommerce
 * Plugin URI: https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments
 * Description: Restrict shipping methods, payment gateways, and shipping destinations based on conditional rules. Supports classic and block checkout, HPOS, and multicurrency.
 * Version: 0.2.1
 * Author: Olivier Bigras
 * Author URI: https://github.com/olivierbigras
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: oli-conditional-shipping-payments
 * Domain Path: /languages
 * Requires at least: 6.5
 * Tested up to: 7.1
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * WC requires at least: 9.0
 * WC tested up to: 11.2
 *
 * @package Oli_Conditional_Shipping_Payments
 */

defined( 'ABSPATH' ) || exit;

// Define plugin constants.
define( 'OLI_CSP_VERSION', '0.2.1' );
define( 'OLI_CSP_FILE', __FILE__ );
define( 'OLI_CSP_PATH', plugin_dir_path( __FILE__ ) );
define( 'OLI_CSP_URL', plugin_dir_url( __FILE__ ) );
define( 'OLI_CSP_BASENAME', plugin_basename( __FILE__ ) );

/**
 * Main plugin class.
 */
class Oli_CSP_Plugin {

	/**
	 * Plugin instance.
	 *
	 * @var Oli_CSP_Plugin
	 */
	private static $instance = null;

	/**
	 * Condition engine instance.
	 *
	 * @var Oli_CSP_Condition_Engine
	 */
	public $condition_engine = null;

	/**
	 * Restriction handler instance.
	 *
	 * @var Oli_CSP_Restriction_Handler
	 */
	public $restriction_handler = null;

	/**
	 * Admin instance.
	 *
	 * @var Oli_CSP_Admin
	 */
	public $admin = null;

	/**
	 * Get plugin instance.
	 *
	 * @return Oli_CSP_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 10 );
		add_action( 'before_woocommerce_init', array( $this, 'declare_compatibility' ) );
	}

	/**
	 * Initialize plugin.
	 */
	public function init() {
		// Check WooCommerce dependency.
		if ( ! $this->check_dependencies() ) {
			return;
		}

		// Include core files.
		$this->includes();

		oli_csp_maybe_upgrade();

		// Initialize components.
		$this->condition_engine    = new Oli_CSP_Condition_Engine();
		$this->restriction_handler = new Oli_CSP_Restriction_Handler( $this->condition_engine );

		if ( is_admin() ) {
			$this->admin = new Oli_CSP_Admin();
		}

		// Initialize Store API support for block checkout.
		if ( class_exists( '\Automattic\WooCommerce\StoreApi\StoreApi' ) ) {
			new Oli_CSP_Store_API_Integration( $this->condition_engine );
		}

		do_action( 'oli_csp_loaded' );
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		// Core classes.
		require_once OLI_CSP_PATH . 'includes/class-oli-csp-condition-engine.php';
		require_once OLI_CSP_PATH . 'includes/class-oli-csp-restriction-handler.php';
		require_once OLI_CSP_PATH . 'includes/class-oli-csp-store-api-integration.php';
		require_once OLI_CSP_PATH . 'includes/class-oli-csp-utils.php';
		require_once OLI_CSP_PATH . 'includes/class-oli-csp-importer.php';

		// Admin classes.
		if ( is_admin() ) {
			require_once OLI_CSP_PATH . 'admin/class-oli-csp-admin-rule-ui.php';
			require_once OLI_CSP_PATH . 'admin/class-oli-csp-admin.php';
			require_once OLI_CSP_PATH . 'admin/class-oli-csp-admin-settings.php';
			require_once OLI_CSP_PATH . 'admin/class-oli-csp-admin-product.php';
		}
	}

	/**
	 * Check plugin dependencies.
	 *
	 * @return bool
	 */
	private function check_dependencies() {
		if ( ! class_exists( 'WooCommerce' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
			return false;
		}

		if ( version_compare( WC_VERSION, '9.0', '<' ) ) {
			add_action( 'admin_notices', array( $this, 'woocommerce_version_notice' ) );
			return false;
		}

		return true;
	}

	/**
	 * WooCommerce missing notice.
	 */
	public function woocommerce_missing_notice() {
		if ( ! $this->should_display_dependency_notice() ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: WooCommerce plugin link */
						__( '<strong>Oli Conditional Shipping and Payments</strong> requires WooCommerce to be installed and active. You can download %s here.', 'oli-conditional-shipping-payments' ),
						'<a href="https://wordpress.org/plugins/woocommerce/" target="_blank">WooCommerce</a>'
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * WooCommerce version notice.
	 */
	public function woocommerce_version_notice() {
		if ( ! $this->should_display_dependency_notice() ) {
			return;
		}
		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: Required WooCommerce version */
						__( '<strong>Oli Conditional Shipping and Payments</strong> requires WooCommerce version %s or higher.', 'oli-conditional-shipping-payments' ),
						'9.0'
					)
				);
				?>
			</p>
		</div>
		<?php
	}

	/**
	 * Limit dependency notices to the Plugins screen.
	 *
	 * @return bool
	 */
	private function should_display_dependency_notice() {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();
		if ( ! $screen ) {
			return false;
		}

		return in_array( $screen->id, array( 'plugins', 'plugins-network' ), true );
	}

	/**
	 * Declare HPOS and checkout-block compatibility.
	 */
	public function declare_compatibility() {
		if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', OLI_CSP_FILE, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', OLI_CSP_FILE, true );
		}
	}
}

/**
 * Get plugin instance.
 *
 * @return Oli_CSP_Plugin
 */
function oli_csp() {
	return Oli_CSP_Plugin::instance();
}

// Initialize plugin.
oli_csp();

/**
 * Copy legacy option keys to the oli_csp_ prefix, then store the current version.
 */
function oli_csp_maybe_upgrade() {
	$installed = get_option( 'oli_csp_version', '' );

	if ( version_compare( (string) $installed, OLI_CSP_VERSION, '>=' ) ) {
		return;
	}

	// Copy any legacy unprefixed / short-prefix options, then delete the old keys.
	$legacy_map = array(
		'csp_global_restrictions' => 'oli_csp_global_restrictions',
		'csp_settings'            => 'oli_csp_settings',
	);

	foreach ( $legacy_map as $old_key => $new_key ) {
		if ( false !== get_option( $new_key, false ) ) {
			continue;
		}

		$old_value = get_option( $old_key, false );
		if ( false === $old_value ) {
			continue;
		}

		update_option( $new_key, $old_value );
		delete_option( $old_key );
	}

	update_option( 'oli_csp_version', OLI_CSP_VERSION );
}

/**
 * Activation hook.
 */
function oli_csp_activate() {
	// Set default options.
	if ( false === get_option( 'oli_csp_global_restrictions', false ) ) {
		update_option( 'oli_csp_global_restrictions', array() );
	}
	if ( false === get_option( 'oli_csp_settings', false ) ) {
		update_option(
			'oli_csp_settings',
			array(
				'debug_mode'                  => 'no',
				'show_excluded_as_disabled'   => 'no',
				'restriction_notice_cart'     => __( 'Some shipping methods or payment gateways are not available based on your cart contents.', 'oli-conditional-shipping-payments' ),
				'restriction_notice_checkout' => __( 'Some shipping methods or payment gateways are not available based on your cart contents.', 'oli-conditional-shipping-payments' ),
			)
		);
	}

	oli_csp_maybe_upgrade();
	flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'oli_csp_activate' );

/**
 * Deactivation hook.
 */
function oli_csp_deactivate() {
	flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'oli_csp_deactivate' );
