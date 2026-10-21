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
	 * Constructor.
	 */
	public function __construct() {
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

		?>
		<div class="options_group oli-csp-product-restrictions">
			<p class="form-field">
				<label><?php esc_html_e( 'Product-Specific Restrictions', 'oli-conditional-shipping-payments' ); ?></label>
				<span class="description">
					<?php esc_html_e( 'Set restrictions that apply only when this product is in the cart.', 'oli-conditional-shipping-payments' ); ?>
				</span>
			</p>

			<div class="oli-csp-product-restriction-sections">
				<?php
				$this->output_product_restriction_section( $post->ID, 'shipping_methods', __( 'Shipping Methods', 'oli-conditional-shipping-payments' ), $restrictions );
				$this->output_product_restriction_section( $post->ID, 'payment_gateways', __( 'Payment Gateways', 'oli-conditional-shipping-payments' ), $restrictions );
				$this->output_product_restriction_section( $post->ID, 'shipping_destinations', __( 'Shipping Destinations', 'oli-conditional-shipping-payments' ), $restrictions );
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Output product restriction section.
	 *
	 * @param int    $product_id Product ID.
	 * @param string $type Restriction type.
	 * @param string $title Section title.
	 * @param array  $restrictions Restrictions data.
	 */
	private function output_product_restriction_section( $product_id, $type, $title, $restrictions ) {
		$type_restrictions = isset( $restrictions[ $type ] ) ? $restrictions[ $type ] : array();
		?>
		<p class="form-field">
			<label><strong><?php echo esc_html( $title ); ?></strong></label>
		</p>
		<?php

		switch ( $type ) {
			case 'shipping_methods':
				$this->output_shipping_methods_field( $product_id, $type_restrictions );
				break;
			case 'payment_gateways':
				$this->output_payment_gateways_field( $product_id, $type_restrictions );
				break;
			case 'shipping_destinations':
				$this->output_destinations_field( $product_id, $type_restrictions );
				break;
		}
	}

	/**
	 * Output shipping methods field.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $restrictions Restrictions data.
	 */
	private function output_shipping_methods_field( $product_id, $restrictions ) {
		$excluded_methods = array();
		if ( ! empty( $restrictions[0]['excluded_methods'] ) ) {
			$excluded_methods = $restrictions[0]['excluded_methods'];
		}

		$shipping_methods = WC()->shipping()->get_shipping_methods();
		?>
		<p class="form-field">
			<label><?php esc_html_e( 'Exclude Shipping Methods', 'oli-conditional-shipping-payments' ); ?></label>
			<select name="_oli_csp_restrictions[shipping_methods][0][excluded_methods][]" multiple class="wc-enhanced-select oli-csp-select-half">
				<?php foreach ( $shipping_methods as $method_id => $method ) : ?>
					<option value="<?php echo esc_attr( $method_id ); ?>" <?php selected( in_array( $method_id, $excluded_methods, true ) ); ?>>
						<?php echo esc_html( $method->get_method_title() ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="description"><?php esc_html_e( 'These shipping methods will not be available when this product is in cart.', 'oli-conditional-shipping-payments' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Output payment gateways field.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $restrictions Restrictions data.
	 */
	private function output_payment_gateways_field( $product_id, $restrictions ) {
		$excluded_gateways = array();
		if ( ! empty( $restrictions[0]['excluded_gateways'] ) ) {
			$excluded_gateways = $restrictions[0]['excluded_gateways'];
		}

		$payment_gateways = WC()->payment_gateways()->payment_gateways();
		?>
		<p class="form-field">
			<label><?php esc_html_e( 'Exclude Payment Gateways', 'oli-conditional-shipping-payments' ); ?></label>
			<select name="_oli_csp_restrictions[payment_gateways][0][excluded_gateways][]" multiple class="wc-enhanced-select oli-csp-select-half">
				<?php foreach ( $payment_gateways as $gateway_id => $gateway ) : ?>
					<option value="<?php echo esc_attr( $gateway_id ); ?>" <?php selected( in_array( $gateway_id, $excluded_gateways, true ) ); ?>>
						<?php echo esc_html( $gateway->get_title() ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="description"><?php esc_html_e( 'These payment gateways will not be available when this product is in cart.', 'oli-conditional-shipping-payments' ); ?></span>
		</p>
		<?php
	}

	/**
	 * Output destinations field.
	 *
	 * @param int   $product_id Product ID.
	 * @param array $restrictions Restrictions data.
	 */
	private function output_destinations_field( $product_id, $restrictions ) {
		$excluded_countries = array();
		$message            = '';
		if ( ! empty( $restrictions[0] ) ) {
			$excluded_countries = isset( $restrictions[0]['excluded_countries'] ) ? $restrictions[0]['excluded_countries'] : array();
			$message            = isset( $restrictions[0]['message'] ) ? $restrictions[0]['message'] : '';
		}

		$countries = WC()->countries->get_countries();
		?>
		<p class="form-field">
			<label><?php esc_html_e( 'Exclude Countries', 'oli-conditional-shipping-payments' ); ?></label>
			<select name="_oli_csp_restrictions[shipping_destinations][0][excluded_countries][]" multiple class="wc-enhanced-select oli-csp-select-half">
				<?php foreach ( $countries as $country_code => $country_name ) : ?>
					<option value="<?php echo esc_attr( $country_code ); ?>" <?php selected( in_array( $country_code, $excluded_countries, true ) ); ?>>
						<?php echo esc_html( $country_name ); ?>
					</option>
				<?php endforeach; ?>
			</select>
			<span class="description"><?php esc_html_e( 'Shipping to these countries will not be available when this product is in cart.', 'oli-conditional-shipping-payments' ); ?></span>
		</p>
		<p class="form-field">
			<label><?php esc_html_e( 'Restriction Message', 'oli-conditional-shipping-payments' ); ?></label>
			<textarea name="_oli_csp_restrictions[shipping_destinations][0][message]" rows="2" class="oli-csp-select-half"><?php echo esc_textarea( $message ); ?></textarea>
			<span class="description"><?php esc_html_e( 'Custom message shown when shipping to excluded countries.', 'oli-conditional-shipping-payments' ); ?></span>
		</p>
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

		// Verify nonce - WooCommerce handles this in woocommerce_process_product_meta action.
		if ( ! isset( $_POST['woocommerce_meta_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ), 'woocommerce_save_data' ) ) {
			return;
		}

		if ( ! isset( $_POST['_oli_csp_restrictions'] ) ) {
			delete_post_meta( $post_id, '_oli_csp_restrictions' );
			return;
		}

		$restrictions = Oli_CSP_Utils::sanitize_restriction_data( wp_unslash( $_POST['_oli_csp_restrictions'] ) );

		// Remove empty restrictions.
		foreach ( $restrictions as $type => $type_restrictions ) {
			if ( empty( $type_restrictions[0]['excluded_methods'] ) &&
				 empty( $type_restrictions[0]['excluded_gateways'] ) &&
				 empty( $type_restrictions[0]['excluded_countries'] ) ) {
				unset( $restrictions[ $type ] );
			}
		}

		if ( ! empty( $restrictions ) ) {
			update_post_meta( $post_id, '_oli_csp_restrictions', $restrictions );
		} else {
			delete_post_meta( $post_id, '_oli_csp_restrictions' );
		}
	}
}
