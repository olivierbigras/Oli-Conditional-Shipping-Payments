<?php
/**
 * Admin Settings class.
 *
 * Handles the WooCommerce settings tab for restrictions.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Oli_CSP_Admin_Settings
 */
class Oli_CSP_Admin_Settings {

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_filter( 'woocommerce_settings_tabs_array', array( $this, 'add_settings_tab' ), 50 );
		add_action( 'woocommerce_settings_tabs_restrictions', array( $this, 'output_settings' ) );
		add_action( 'woocommerce_settings_save_restrictions', array( $this, 'save_settings' ) );
		add_action( 'admin_post_oli_csp_export', array( $this, 'export_restrictions' ) );
		add_action( 'admin_post_oli_csp_import', array( $this, 'import_restrictions' ) );
		add_action( 'admin_post_oli_csp_import_official', array( $this, 'import_from_official' ) );
	}

	/**
	 * Add settings tab to WooCommerce.
	 *
	 * @param array $tabs WooCommerce settings tabs.
	 * @return array Modified tabs.
	 */
	public function add_settings_tab( $tabs ) {
		$tabs['restrictions'] = __( 'Restrictions', 'oli-conditional-shipping-payments' );
		return $tabs;
	}

	/**
	 * Output settings page.
	 */
	public function output_settings() {
		global $current_section;

		if ( '' === $current_section ) {
			$this->output_import_report();
			$this->output_restrictions_settings();
		}
	}

	/**
	 * Output the official-extension import report stored in a transient.
	 */
	private function output_import_report() {
		if ( empty( $_GET['import_report'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			return;
		}

		$report = get_transient( 'oli_csp_import_report' );
		if ( ! is_array( $report ) ) {
			return;
		}

		delete_transient( 'oli_csp_import_report' );

		$message = isset( $report['message'] ) ? (string) $report['message'] : '';
		$errors  = isset( $report['errors'] ) && is_array( $report['errors'] ) ? $report['errors'] : array();
		?>
		<div class="notice notice-info oli-csp-import-report">
			<p><?php echo esc_html( $message ); ?></p>
			<?php if ( ! empty( $errors ) ) : ?>
				<ul>
					<?php foreach ( $errors as $error ) : ?>
						<li><?php echo esc_html( (string) $error ); ?></li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Output restrictions settings.
	 */
	private function output_restrictions_settings() {
		$restrictions = get_option( 'oli_csp_global_restrictions', array() );
		$settings     = get_option( 'oli_csp_settings', array() );

		?>
		<h2><?php esc_html_e( 'Conditional Shipping and Payments', 'oli-conditional-shipping-payments' ); ?></h2>
		
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="oli_csp_debug_mode"><?php esc_html_e( 'Debug Mode', 'oli-conditional-shipping-payments' ); ?></label>
					</th>
					<td class="forminp">
						<input type="checkbox" id="oli_csp_debug_mode" name="oli_csp_settings[debug_mode]" value="yes" <?php checked( isset( $settings['debug_mode'] ) && 'yes' === $settings['debug_mode'] ); ?> />
						<span class="description"><?php esc_html_e( 'Show debug information to administrators.', 'oli-conditional-shipping-payments' ); ?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="oli_csp_show_excluded"><?php esc_html_e( 'Show Excluded Methods', 'oli-conditional-shipping-payments' ); ?></label>
					</th>
					<td class="forminp">
						<input type="checkbox" id="oli_csp_show_excluded" name="oli_csp_settings[show_excluded_as_disabled]" value="yes" <?php checked( isset( $settings['show_excluded_as_disabled'] ) && 'yes' === $settings['show_excluded_as_disabled'] ); ?> />
						<span class="description"><?php esc_html_e( 'Show excluded shipping methods as disabled instead of hiding them.', 'oli-conditional-shipping-payments' ); ?></span>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="oli_csp_notice_cart"><?php esc_html_e( 'Cart Notice', 'oli-conditional-shipping-payments' ); ?></label>
					</th>
					<td class="forminp">
						<textarea id="oli_csp_notice_cart" name="oli_csp_settings[restriction_notice_cart]" rows="3" cols="50" class="input-text wide-input"><?php echo esc_textarea( isset( $settings['restriction_notice_cart'] ) ? $settings['restriction_notice_cart'] : '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Notice shown on cart page when restrictions apply.', 'oli-conditional-shipping-payments' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="oli_csp_notice_checkout"><?php esc_html_e( 'Checkout Notice', 'oli-conditional-shipping-payments' ); ?></label>
					</th>
					<td class="forminp">
						<textarea id="oli_csp_notice_checkout" name="oli_csp_settings[restriction_notice_checkout]" rows="3" cols="50" class="input-text wide-input"><?php echo esc_textarea( isset( $settings['restriction_notice_checkout'] ) ? $settings['restriction_notice_checkout'] : '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Notice shown on checkout page when restrictions apply.', 'oli-conditional-shipping-payments' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Global Restrictions', 'oli-conditional-shipping-payments' ); ?></h3>
		
		<div class="oli-csp-restrictions-wrapper">
			<?php $this->output_restriction_section( 'shipping_methods', __( 'Shipping Method Restrictions', 'oli-conditional-shipping-payments' ), $restrictions ); ?>
			<?php $this->output_restriction_section( 'payment_gateways', __( 'Payment Gateway Restrictions', 'oli-conditional-shipping-payments' ), $restrictions ); ?>
			<?php $this->output_restriction_section( 'shipping_destinations', __( 'Shipping Destination Restrictions', 'oli-conditional-shipping-payments' ), $restrictions ); ?>
		</div>

		<h3><?php esc_html_e( 'Import/Export', 'oli-conditional-shipping-payments' ); ?></h3>
		
		<table class="form-table">
			<tbody>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label><?php esc_html_e( 'Export Restrictions', 'oli-conditional-shipping-payments' ); ?></label>
					</th>
					<td class="forminp">
						<a class="button" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=oli_csp_export' ), 'oli_csp_export', 'oli_csp_export_nonce' ) ); ?>">
							<?php esc_html_e( 'Export as JSON', 'oli-conditional-shipping-payments' ); ?>
						</a>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label><?php esc_html_e( 'Import Restrictions', 'oli-conditional-shipping-payments' ); ?></label>
					</th>
					<td class="forminp">
						<input type="file" name="oli_csp_import_file" accept=".json" />
						<?php wp_nonce_field( 'oli_csp_import', 'oli_csp_import_nonce' ); ?>
						<button type="submit" class="button" name="action" value="oli_csp_import" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" formmethod="post" formenctype="multipart/form-data">
							<?php esc_html_e( 'Import from JSON', 'oli-conditional-shipping-payments' ); ?>
						</button>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label><?php esc_html_e( 'Import from Official Extension', 'oli-conditional-shipping-payments' ); ?></label>
					</th>
					<td class="forminp">
						<?php wp_nonce_field( 'oli_csp_import_official', 'oli_csp_import_official_nonce' ); ?>
						<label>
							<input type="checkbox" name="dry_run" value="1" checked />
							<?php esc_html_e( 'Dry run (preview only)', 'oli-conditional-shipping-payments' ); ?>
						</label>
						<br />
						<button type="submit" class="button" name="action" value="oli_csp_import_official" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" formmethod="post">
							<?php esc_html_e( 'Import from Official Extension', 'oli-conditional-shipping-payments' ); ?>
						</button>
						<p class="description"><?php esc_html_e( 'Import restrictions from WooCommerce Conditional Shipping and Payments extension.', 'oli-conditional-shipping-payments' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Output a restriction section.
	 *
	 * @param string $type Restriction type.
	 * @param string $title Section title.
	 * @param array  $restrictions All restrictions.
	 */
	private function output_restriction_section( $type, $title, $restrictions ) {
		$type_restrictions = isset( $restrictions[ $type ] ) ? $restrictions[ $type ] : array();
		?>
		<div class="oli-csp-restriction-section" data-type="<?php echo esc_attr( $type ); ?>">
			<h4><?php echo esc_html( $title ); ?></h4>
			<div class="oli-csp-restrictions-list">
				<?php
				if ( ! empty( $type_restrictions ) ) {
					foreach ( $type_restrictions as $index => $restriction ) {
						$this->output_restriction_row( $type, $index, $restriction );
					}
				}
				?>
			</div>
			<button type="button" class="button oli-csp-add-restriction" data-type="<?php echo esc_attr( $type ); ?>">
				<?php esc_html_e( 'Add Restriction', 'oli-conditional-shipping-payments' ); ?>
			</button>
			<template class="oli-csp-restriction-template">
				<?php $this->output_restriction_row( $type, '__index__', array() ); ?>
			</template>
		</div>
		<?php
	}

	/**
	 * Output a restriction row.
	 *
	 * @param string $type Restriction type.
	 * @param int    $index Restriction index.
	 * @param array  $restriction Restriction data.
	 */
	private function output_restriction_row( $type, $index, $restriction ) {
		?>
		<div class="oli-csp-restriction-row">
			<h5>
				<?php
				echo esc_html(
					sprintf(
						/* translators: %d: restriction number */
						__( 'Restriction #%d', 'oli-conditional-shipping-payments' ),
						(int) $index + 1
					)
				);
				?>
			</h5>
			
			<?php
			// Output excluded items based on type.
			switch ( $type ) {
				case 'shipping_methods':
					$this->output_shipping_method_selector( $type, $index, $restriction );
					break;
				case 'payment_gateways':
					$this->output_payment_gateway_selector( $type, $index, $restriction );
					break;
				case 'shipping_destinations':
					$this->output_destination_selector( $type, $index, $restriction );
					break;
			}
			?>

			<h6><?php esc_html_e( 'Conditions (all must match)', 'oli-conditional-shipping-payments' ); ?></h6>
			<p class="description"><?php esc_html_e( 'Leave empty to apply restriction always.', 'oli-conditional-shipping-payments' ); ?></p>
			
			<button type="button" class="button button-secondary oli-csp-remove-restriction">
				<?php esc_html_e( 'Remove Restriction', 'oli-conditional-shipping-payments' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Output shipping method selector.
	 *
	 * @param string $type Restriction type.
	 * @param int    $index Restriction index.
	 * @param array  $restriction Restriction data.
	 */
	private function output_shipping_method_selector( $type, $index, $restriction ) {
		$excluded_methods = isset( $restriction['excluded_methods'] ) ? $restriction['excluded_methods'] : array();
		$shipping_methods = WC()->shipping()->get_shipping_methods();
		?>
		<label><?php esc_html_e( 'Exclude Shipping Methods', 'oli-conditional-shipping-payments' ); ?></label>
		<select name="oli_csp_restrictions[<?php echo esc_attr( $type ); ?>][<?php echo esc_attr( $index ); ?>][excluded_methods][]" multiple class="wc-enhanced-select oli-csp-select-wide">
			<?php foreach ( $shipping_methods as $method_id => $method ) : ?>
				<option value="<?php echo esc_attr( $method_id ); ?>" <?php selected( in_array( $method_id, $excluded_methods, true ) ); ?>>
					<?php echo esc_html( $method->get_method_title() ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Output payment gateway selector.
	 *
	 * @param string $type Restriction type.
	 * @param int    $index Restriction index.
	 * @param array  $restriction Restriction data.
	 */
	private function output_payment_gateway_selector( $type, $index, $restriction ) {
		$excluded_gateways = isset( $restriction['excluded_gateways'] ) ? $restriction['excluded_gateways'] : array();
		$payment_gateways  = WC()->payment_gateways()->payment_gateways();
		?>
		<label><?php esc_html_e( 'Exclude Payment Gateways', 'oli-conditional-shipping-payments' ); ?></label>
		<select name="oli_csp_restrictions[<?php echo esc_attr( $type ); ?>][<?php echo esc_attr( $index ); ?>][excluded_gateways][]" multiple class="wc-enhanced-select oli-csp-select-wide">
			<?php foreach ( $payment_gateways as $gateway_id => $gateway ) : ?>
				<option value="<?php echo esc_attr( $gateway_id ); ?>" <?php selected( in_array( $gateway_id, $excluded_gateways, true ) ); ?>>
					<?php echo esc_html( $gateway->get_title() ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Output destination selector.
	 *
	 * @param string $type Restriction type.
	 * @param int    $index Restriction index.
	 * @param array  $restriction Restriction data.
	 */
	private function output_destination_selector( $type, $index, $restriction ) {
		$excluded_countries = isset( $restriction['excluded_countries'] ) ? $restriction['excluded_countries'] : array();
		$message            = isset( $restriction['message'] ) ? $restriction['message'] : '';
		$countries          = WC()->countries->get_countries();
		?>
		<label><?php esc_html_e( 'Exclude Countries', 'oli-conditional-shipping-payments' ); ?></label>
		<select name="oli_csp_restrictions[<?php echo esc_attr( $type ); ?>][<?php echo esc_attr( $index ); ?>][excluded_countries][]" multiple class="wc-enhanced-select oli-csp-select-wide">
			<?php foreach ( $countries as $country_code => $country_name ) : ?>
				<option value="<?php echo esc_attr( $country_code ); ?>" <?php selected( in_array( $country_code, $excluded_countries, true ) ); ?>>
					<?php echo esc_html( $country_name ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<br /><br />
		<label><?php esc_html_e( 'Restriction Message', 'oli-conditional-shipping-payments' ); ?></label>
		<textarea name="oli_csp_restrictions[<?php echo esc_attr( $type ); ?>][<?php echo esc_attr( $index ); ?>][message]" rows="2" class="large-text"><?php echo esc_textarea( $message ); ?></textarea>
		<?php
	}

	/**
	 * Save settings.
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		// Verify nonce.
		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
			return;
		}

		// Save general settings.
		if ( isset( $_POST['oli_csp_settings'] ) ) {
			$settings = array();

			$settings['debug_mode']                  = isset( $_POST['oli_csp_settings']['debug_mode'] ) ? 'yes' : 'no';
			$settings['show_excluded_as_disabled']   = isset( $_POST['oli_csp_settings']['show_excluded_as_disabled'] ) ? 'yes' : 'no';
			$settings['restriction_notice_cart']     = isset( $_POST['oli_csp_settings']['restriction_notice_cart'] ) ? sanitize_textarea_field( wp_unslash( $_POST['oli_csp_settings']['restriction_notice_cart'] ) ) : '';
			$settings['restriction_notice_checkout'] = isset( $_POST['oli_csp_settings']['restriction_notice_checkout'] ) ? sanitize_textarea_field( wp_unslash( $_POST['oli_csp_settings']['restriction_notice_checkout'] ) ) : '';

			update_option( 'oli_csp_settings', $settings );
		}

		// Save restrictions.
		if ( isset( $_POST['oli_csp_restrictions'] ) ) {
			$restrictions = Oli_CSP_Utils::sanitize_restriction_data( wp_unslash( $_POST['oli_csp_restrictions'] ) );
			update_option( 'oli_csp_global_restrictions', $restrictions );
		}
	}

	/**
	 * Export restrictions as JSON.
	 */
	public function export_restrictions() {
		$nonce = isset( $_REQUEST['oli_csp_export_nonce'] ) ? sanitize_text_field( wp_unslash( $_REQUEST['oli_csp_export_nonce'] ) ) : '';
		if ( ! current_user_can( 'manage_woocommerce' ) || ! wp_verify_nonce( $nonce, 'oli_csp_export' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'oli-conditional-shipping-payments' ) );
		}

		$restrictions = get_option( 'oli_csp_global_restrictions', array() );
		$json         = Oli_CSP_Utils::export_to_json( $restrictions );

		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename="oli-csp-restrictions-' . gmdate( 'Y-m-d' ) . '.json"' );
		header( 'Pragma: no-cache' );

		echo $json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		exit;
	}

	/**
	 * Import restrictions from JSON.
	 */
	public function import_restrictions() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['oli_csp_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oli_csp_import_nonce'] ) ), 'oli_csp_import' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'oli-conditional-shipping-payments' ) );
		}

		$tmp_name = isset( $_FILES['oli_csp_import_file']['tmp_name'] ) ? $_FILES['oli_csp_import_file']['tmp_name'] : '';
		$filename = isset( $_FILES['oli_csp_import_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['oli_csp_import_file']['name'] ) ) : '';

		if ( empty( $tmp_name ) || ! is_uploaded_file( $tmp_name ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'wc-settings',
						'tab'   => 'restrictions',
						'error' => 'no_file',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		$filetype = wp_check_filetype( $filename, array( 'json' => 'application/json' ) );
		if ( empty( $filetype['ext'] ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'wc-settings',
						'tab'   => 'restrictions',
						'error' => 'invalid_json',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- validated uploaded temp file.
		$file_content = file_get_contents( $tmp_name );
		$restrictions = Oli_CSP_Utils::import_from_json( $file_content );

		if ( is_wp_error( $restrictions ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'  => 'wc-settings',
						'tab'   => 'restrictions',
						'error' => 'invalid_json',
					),
					admin_url( 'admin.php' )
				)
			);
			exit;
		}

		update_option( 'oli_csp_global_restrictions', $restrictions );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'     => 'wc-settings',
					'tab'      => 'restrictions',
					'imported' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}

	/**
	 * Import from official extension.
	 */
	public function import_from_official() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['oli_csp_import_official_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oli_csp_import_official_nonce'] ) ), 'oli_csp_import_official' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'oli-conditional-shipping-payments' ) );
		}

		$dry_run  = isset( $_POST['dry_run'] ) && '1' === $_POST['dry_run'];
		$importer = new Oli_CSP_Importer();
		$report   = $importer->import_from_official_extension( $dry_run );

		// Store report in transient for display.
		set_transient( 'oli_csp_import_report', $report, 60 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'          => 'wc-settings',
					'tab'           => 'restrictions',
					'import_report' => '1',
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
