<?php
/**
 * Admin Product class.
 *
 * Handles product-specific restriction meta boxes.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Oli_CSP_Admin_Product
 */
class Oli_CSP_Admin_Product {

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

		add_action( 'woocommerce_product_options_general_product_data', array( $this, 'output_product_restrictions' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_restrictions' ) );
	}

	/**
	 * Output product restrictions meta box.
	 */
	public function output_product_restrictions() {
		global $post;

		$restrictions = get_post_meta( $post->ID, '_oli_csp_restrictions', true );
		if ( ! is_array( $restrictions ) ) {
			$restrictions = array();
		}

		$types = Oli_CSP_Utils::get_restriction_types();
		unset( $types['checkout_notices'] );
		?>
		<div class="options_group oli-csp-product-restrictions">
			<p class="form-field">
				<label><?php esc_html_e( 'Product-Specific Restrictions', 'oli-conditional-shipping-payments' ); ?></label>
				<span class="description">
					<?php esc_html_e( 'These rules apply only when this product is in the cart. Conditions in a group use AND; groups use OR.', 'oli-conditional-shipping-payments' ); ?>
				</span>
			</p>

			<div class="oli-csp-product-restriction-sections oli-csp-restrictions-wrapper">
				<?php foreach ( $types as $type => $title ) : ?>
					<div class="oli-csp-restriction-section" data-type="<?php echo esc_attr( $type ); ?>">
						<h4><?php echo esc_html( $title ); ?></h4>
						<div class="oli-csp-restrictions-list">
							<?php
							$type_restrictions = isset( $restrictions[ $type ] ) && is_array( $restrictions[ $type ] ) ? $restrictions[ $type ] : array();
							foreach ( $type_restrictions as $index => $restriction ) {
								$this->rule_ui->render_restriction_row( $type, $index, $restriction, '_oli_csp_restrictions' );
							}
							?>
						</div>
						<button type="button" class="button oli-csp-add-restriction" data-type="<?php echo esc_attr( $type ); ?>" data-name-base="_oli_csp_restrictions">
							<?php esc_html_e( 'Add Rule', 'oli-conditional-shipping-payments' ); ?>
						</button>
					</div>
				<?php endforeach; ?>
			</div>
			<?php $this->rule_ui->render_templates( '_oli_csp_restrictions' ); ?>
		</div>
		<?php
	}

	/**
	 * Save product restrictions.
	 *
	 * @param int $post_id Product ID.
	 */
	public function save_product_restrictions( $post_id ) {
		if ( ! current_user_can( 'edit_product', $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		if ( ! isset( $_POST['_oli_csp_restrictions'] ) ) {
			delete_post_meta( $post_id, '_oli_csp_restrictions' );
			return;
		}

		$restrictions = Oli_CSP_Utils::sanitize_restriction_data( wp_unslash( $_POST['_oli_csp_restrictions'] ) ); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- sanitized in Oli_CSP_Utils::sanitize_restriction_data().

		if ( ! empty( $restrictions ) ) {
			update_post_meta( $post_id, '_oli_csp_restrictions', $restrictions );
		} else {
			delete_post_meta( $post_id, '_oli_csp_restrictions' );
		}
	}
}
