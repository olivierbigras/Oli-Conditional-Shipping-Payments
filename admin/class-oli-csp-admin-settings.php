<?php
/**
 * Admin Settings class.
 *
 * WooCommerce settings tab for restriction rules.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Oli_CSP_Admin_Settings
 */
class Oli_CSP_Admin_Settings {

	/**
	 * Rule UI renderer.
	 *
	 * @var Oli_CSP_Admin_Rule_UI
	 */
	private $rule_ui;

	/**
	 * Constructor.
	 *
	 * @param Oli_CSP_Admin_Rule_UI $rule_ui Rule UI renderer.
	 */
	public function __construct( $rule_ui ) {
		$this->rule_ui = $rule_ui;

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
		$this->output_import_report();
		$this->output_admin_notices();
		$this->output_restrictions_settings();
	}

	/**
	 * Notices that belong on this plugin screen only.
	 */
	private function output_admin_notices() {
		if ( ! current_user_can( 'manage_woocommerce' )
			|| ! isset( $_GET['oli_csp_notice_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['oli_csp_notice_nonce'] ) ), 'oli_csp_notice' ) ) {
			return;
		}

		$error = isset( $_GET['error'] ) ? sanitize_key( wp_unslash( $_GET['error'] ) ) : '';

		if ( 'no_file' === $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Please choose a JSON file to import.', 'oli-conditional-shipping-payments' ) . '</p></div>';
			return;
		}

		if ( 'invalid_json' === $error ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'The uploaded file is not valid JSON.', 'oli-conditional-shipping-payments' ) . '</p></div>';
			return;
		}

		if ( isset( $_GET['imported'] ) && '1' === sanitize_key( wp_unslash( $_GET['imported'] ) ) ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Restrictions imported.', 'oli-conditional-shipping-payments' ) . '</p></div>';
		}
	}

	/**
	 * Output the official-extension import report stored in a transient.
	 */
	private function output_import_report() {
		if ( ! current_user_can( 'manage_woocommerce' )
			|| ! isset( $_GET['oli_csp_notice_nonce'], $_GET['import_report'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['oli_csp_notice_nonce'] ) ), 'oli_csp_notice' ) ) {
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
		$types        = Oli_CSP_Utils::get_restriction_types();

		if ( ! is_array( $restrictions ) ) {
			$restrictions = array();
		}
		if ( ! is_array( $settings ) ) {
			$settings = array();
		}
		?>
		<h2><?php esc_html_e( 'Conditional Shipping and Payments', 'oli-conditional-shipping-payments' ); ?></h2>
		<p><?php esc_html_e( 'Hide shipping methods, payment gateways, or destinations when cart, customer, or location conditions match. Conditions in a group use AND; groups use OR.', 'oli-conditional-shipping-payments' ); ?></p>

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
						<p class="description"><?php esc_html_e( 'Fallback notice on the cart when a rule matches and that rule has no custom notice.', 'oli-conditional-shipping-payments' ); ?></p>
					</td>
				</tr>
				<tr valign="top">
					<th scope="row" class="titledesc">
						<label for="oli_csp_notice_checkout"><?php esc_html_e( 'Checkout Notice', 'oli-conditional-shipping-payments' ); ?></label>
					</th>
					<td class="forminp">
						<textarea id="oli_csp_notice_checkout" name="oli_csp_settings[restriction_notice_checkout]" rows="3" cols="50" class="input-text wide-input"><?php echo esc_textarea( isset( $settings['restriction_notice_checkout'] ) ? $settings['restriction_notice_checkout'] : '' ); ?></textarea>
						<p class="description"><?php esc_html_e( 'Fallback notice on classic and block checkout when a rule matches and that rule has no custom notice.', 'oli-conditional-shipping-payments' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>

		<h3><?php esc_html_e( 'Rules', 'oli-conditional-shipping-payments' ); ?></h3>

		<div class="oli-csp-restrictions-wrapper">
			<?php foreach ( $types as $type => $title ) : ?>
				<?php $this->output_restriction_section( $type, $title, $restrictions ); ?>
			<?php endforeach; ?>
		</div>

		<?php $this->rule_ui->render_templates( 'oli_csp_restrictions' ); ?>

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
						<p class="description"><?php esc_html_e( 'Import restrictions from WooCommerce Conditional Shipping and Payments.', 'oli-conditional-shipping-payments' ); ?></p>
					</td>
				</tr>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Output a restriction section.
	 *
	 * @param string $type         Restriction type.
	 * @param string $title        Section title.
	 * @param array  $restrictions All restrictions.
	 */
	private function output_restriction_section( $type, $title, $restrictions ) {
		$type_restrictions = isset( $restrictions[ $type ] ) ? $restrictions[ $type ] : array();
		?>
		<div class="oli-csp-restriction-section" data-type="<?php echo esc_attr( $type ); ?>">
			<h4><?php echo esc_html( $title ); ?></h4>
			<div class="oli-csp-restrictions-list">
				<?php
				if ( ! empty( $type_restrictions ) && is_array( $type_restrictions ) ) {
					foreach ( $type_restrictions as $index => $restriction ) {
						$this->rule_ui->render_restriction_row( $type, $index, $restriction, 'oli_csp_restrictions' );
					}
				}
				?>
			</div>
			<button type="button" class="button oli-csp-add-restriction" data-type="<?php echo esc_attr( $type ); ?>">
				<?php esc_html_e( 'Add Rule', 'oli-conditional-shipping-payments' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Save settings.
	 */
	public function save_settings() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}

		if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'woocommerce-settings' ) ) {
			return;
		}

		if ( isset( $_POST['oli_csp_settings'] ) ) {
			$settings = array();

			$settings['debug_mode']                  = isset( $_POST['oli_csp_settings']['debug_mode'] ) ? 'yes' : 'no';
			$settings['show_excluded_as_disabled']   = isset( $_POST['oli_csp_settings']['show_excluded_as_disabled'] ) ? 'yes' : 'no';
			$settings['restriction_notice_cart']     = isset( $_POST['oli_csp_settings']['restriction_notice_cart'] ) ? sanitize_textarea_field( wp_unslash( $_POST['oli_csp_settings']['restriction_notice_cart'] ) ) : '';
			$settings['restriction_notice_checkout'] = isset( $_POST['oli_csp_settings']['restriction_notice_checkout'] ) ? sanitize_textarea_field( wp_unslash( $_POST['oli_csp_settings']['restriction_notice_checkout'] ) ) : '';

			update_option( 'oli_csp_settings', $settings );
		}

		if ( isset( $_POST['oli_csp_restrictions'] ) ) {
			$restrictions = Oli_CSP_Utils::sanitize_restriction_data( wp_unslash( $_POST['oli_csp_restrictions'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Oli_CSP_Utils::sanitize_restriction_data().
			update_option( 'oli_csp_global_restrictions', $restrictions );
		} else {
			update_option( 'oli_csp_global_restrictions', array() );
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

		header( 'Content-Disposition: attachment; filename="oli-csp-restrictions-' . gmdate( 'Y-m-d' ) . '.json"' );
		header( 'Pragma: no-cache' );

		// wp_send_json() encodes with wp_json_encode(), sends the JSON content type and exits.
		wp_send_json( $restrictions, null, JSON_PRETTY_PRINT );
	}

	/**
	 * Import restrictions from JSON.
	 */
	public function import_restrictions() {
		if ( ! current_user_can( 'manage_woocommerce' ) || ! isset( $_POST['oli_csp_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['oli_csp_import_nonce'] ) ), 'oli_csp_import' ) ) {
			wp_die( esc_html__( 'Permission denied.', 'oli-conditional-shipping-payments' ) );
		}

		$tmp_name = isset( $_FILES['oli_csp_import_file']['tmp_name'] ) ? sanitize_text_field( wp_unslash( $_FILES['oli_csp_import_file']['tmp_name'] ) ) : '';
		$filename = isset( $_FILES['oli_csp_import_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['oli_csp_import_file']['name'] ) ) : '';

		if ( empty( $tmp_name ) || ! is_uploaded_file( $tmp_name ) ) {
			wp_safe_redirect(
				add_query_arg(
					array(
						'page'                 => 'wc-settings',
						'tab'                  => 'restrictions',
						'error'                => 'no_file',
						'oli_csp_notice_nonce' => wp_create_nonce( 'oli_csp_notice' ),
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
						'page'                 => 'wc-settings',
						'tab'                  => 'restrictions',
						'error'                => 'invalid_json',
						'oli_csp_notice_nonce' => wp_create_nonce( 'oli_csp_notice' ),
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
						'page'                 => 'wc-settings',
						'tab'                  => 'restrictions',
						'error'                => 'invalid_json',
						'oli_csp_notice_nonce' => wp_create_nonce( 'oli_csp_notice' ),
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
					'page'                 => 'wc-settings',
					'tab'                  => 'restrictions',
					'imported'             => '1',
					'oli_csp_notice_nonce' => wp_create_nonce( 'oli_csp_notice' ),
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

		$dry_run  = isset( $_POST['dry_run'] ) && '1' === sanitize_text_field( wp_unslash( $_POST['dry_run'] ) );
		$importer = new Oli_CSP_Importer();
		$report   = $importer->import_from_official_extension( $dry_run );

		set_transient( 'oli_csp_import_report', $report, 60 );

		wp_safe_redirect(
			add_query_arg(
				array(
					'page'                 => 'wc-settings',
					'tab'                  => 'restrictions',
					'import_report'        => '1',
					'oli_csp_notice_nonce' => wp_create_nonce( 'oli_csp_notice' ),
				),
				admin_url( 'admin.php' )
			)
		);
		exit;
	}
}
