<?php
/**
 * Admin rule field renderer.
 *
 * Shared AND/OR condition builder for global and product restrictions.
 *
 * @package Oli_Conditional_Shipping_Payments
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class Oli_CSP_Admin_Rule_UI
 */
class Oli_CSP_Admin_Rule_UI {

	/**
	 * Render a full restriction row.
	 *
	 * @param string $type        Restriction type.
	 * @param mixed  $index       Restriction index or placeholder.
	 * @param array  $restriction Restriction data.
	 * @param string $name_base   Field name prefix (oli_csp_restrictions or _oli_csp_restrictions).
	 */
	public function render_restriction_row( $type, $index, $restriction, $name_base = 'oli_csp_restrictions' ) {
		$restriction = is_array( $restriction ) ? $restriction : array();
		$title       = isset( $restriction['title'] ) ? $restriction['title'] : '';
		$enabled     = Oli_CSP_Utils::is_restriction_enabled( $restriction );
		$notice      = isset( $restriction['notice'] ) ? $restriction['notice'] : ( isset( $restriction['message'] ) ? $restriction['message'] : '' );
		$prefix      = $name_base . '[' . $type . '][' . $index . ']';
		$label       = $title;
		if ( '' === $label ) {
			$label = sprintf(
				/* translators: %d: restriction number */
				__( 'Restriction #%d', 'oli-conditional-shipping-payments' ),
				is_numeric( $index ) ? ( (int) $index + 1 ) : 1
			);
		}
		?>
		<details class="oli-csp-restriction-row" open data-type="<?php echo esc_attr( $type ); ?>">
			<summary class="oli-csp-restriction-summary">
				<span class="oli-csp-restriction-summary-title"><?php echo esc_html( $label ); ?></span>
			</summary>

			<p class="oli-csp-field">
				<label>
					<input type="hidden" name="<?php echo esc_attr( $prefix ); ?>[enabled]" value="no" />
					<input type="checkbox" name="<?php echo esc_attr( $prefix ); ?>[enabled]" value="yes" <?php checked( $enabled ); ?> />
					<?php esc_html_e( 'Enabled', 'oli-conditional-shipping-payments' ); ?>
				</label>
			</p>

			<p class="oli-csp-field">
				<label for="<?php echo esc_attr( $prefix ); ?>_title"><?php esc_html_e( 'Label', 'oli-conditional-shipping-payments' ); ?></label>
				<input type="text" id="<?php echo esc_attr( $prefix ); ?>_title" class="regular-text" name="<?php echo esc_attr( $prefix ); ?>[title]" value="<?php echo esc_attr( $title ); ?>" />
			</p>

			<?php $this->render_action_fields( $type, $prefix, $restriction ); ?>

			<p class="oli-csp-field">
				<label for="<?php echo esc_attr( $prefix ); ?>_notice"><?php esc_html_e( 'Checkout notice', 'oli-conditional-shipping-payments' ); ?></label>
				<textarea id="<?php echo esc_attr( $prefix ); ?>_notice" name="<?php echo esc_attr( $prefix ); ?>[notice]" rows="2" class="large-text"><?php echo esc_textarea( $notice ); ?></textarea>
				<span class="description"><?php esc_html_e( 'Optional message shown at classic and block checkout when this rule matches.', 'oli-conditional-shipping-payments' ); ?></span>
			</p>

			<?php $this->render_condition_builder( $prefix, $restriction ); ?>

			<p>
				<button type="button" class="button button-secondary oli-csp-remove-restriction">
					<?php esc_html_e( 'Remove Rule', 'oli-conditional-shipping-payments' ); ?>
				</button>
			</p>
		</details>
		<?php
	}

	/**
	 * Render type-specific action fields.
	 *
	 * @param string $type        Restriction type.
	 * @param string $prefix      Field name prefix.
	 * @param array  $restriction Restriction data.
	 */
	private function render_action_fields( $type, $prefix, $restriction ) {
		switch ( $type ) {
			case 'shipping_methods':
				$this->render_shipping_action_fields( $prefix, $restriction );
				break;
			case 'payment_gateways':
				$this->render_multiselect(
					$prefix . '[excluded_gateways][]',
					__( 'Disable payment gateways', 'oli-conditional-shipping-payments' ),
					$this->get_payment_gateway_choices(),
					isset( $restriction['excluded_gateways'] ) ? (array) $restriction['excluded_gateways'] : array()
				);
				break;
			case 'shipping_destinations':
				$this->render_multiselect(
					$prefix . '[excluded_countries][]',
					__( 'Disable shipping to countries', 'oli-conditional-shipping-payments' ),
					$this->get_country_choices(),
					isset( $restriction['excluded_countries'] ) ? (array) $restriction['excluded_countries'] : array()
				);
				break;
			case 'checkout_notices':
				echo '<p class="description">' . esc_html__( 'This rule only shows the checkout notice below. It does not hide methods or gateways.', 'oli-conditional-shipping-payments' ) . '</p>';
				break;
		}
	}

	/**
	 * Shipping method and zone selectors.
	 *
	 * @param string $prefix      Field name prefix.
	 * @param array  $restriction Restriction data.
	 */
	private function render_shipping_action_fields( $prefix, $restriction ) {
		$this->render_multiselect(
			$prefix . '[excluded_methods][]',
			__( 'Disable shipping methods', 'oli-conditional-shipping-payments' ),
			$this->get_shipping_method_choices(),
			isset( $restriction['excluded_methods'] ) ? (array) $restriction['excluded_methods'] : array()
		);
		echo '<p class="description">' . esc_html__( 'Choose a method type (all instances) or a specific zone method.', 'oli-conditional-shipping-payments' ) . '</p>';

		$this->render_multiselect(
			$prefix . '[zones][]',
			__( 'Limit to shipping zones', 'oli-conditional-shipping-payments' ),
			$this->get_shipping_zone_choices(),
			isset( $restriction['zones'] ) ? array_map( 'strval', (array) $restriction['zones'] ) : array()
		);
		echo '<p class="description">' . esc_html__( 'Leave empty to apply in every zone.', 'oli-conditional-shipping-payments' ) . '</p>';
	}

	/**
	 * Render the AND/OR condition builder.
	 *
	 * @param string $prefix      Field name prefix.
	 * @param array  $restriction Restriction data.
	 */
	public function render_condition_builder( $prefix, $restriction ) {
		$groups = array();
		if ( ! empty( $restriction['condition_groups'] ) && is_array( $restriction['condition_groups'] ) ) {
			$groups = $restriction['condition_groups'];
		} elseif ( ! empty( $restriction['conditions'] ) && is_array( $restriction['conditions'] ) ) {
			$groups = array( $restriction['conditions'] );
		}

		if ( empty( $groups ) ) {
			$groups = array(
				array(
					array(
						'type'     => 'cart_subtotal',
						'operator' => '>=',
						'value'    => '',
					),
				),
			);
			$empty  = true;
		} else {
			$empty = false;
		}
		?>
		<div class="oli-csp-condition-builder">
			<h6><?php esc_html_e( 'Conditions', 'oli-conditional-shipping-payments' ); ?></h6>
			<p class="description">
				<?php esc_html_e( 'Conditions in a group use AND. Separate groups use OR. Clear every condition to apply the rule always.', 'oli-conditional-shipping-payments' ); ?>
			</p>
			<div class="oli-csp-condition-groups" data-empty="<?php echo $empty ? '1' : '0'; ?>">
				<?php foreach ( $groups as $group_index => $conditions ) : ?>
					<?php if ( $group_index > 0 ) : ?>
						<p class="oli-csp-or-separator" aria-hidden="true"><?php esc_html_e( 'OR', 'oli-conditional-shipping-payments' ); ?></p>
					<?php endif; ?>
					<?php $this->render_condition_group( $prefix, $group_index, $conditions ); ?>
				<?php endforeach; ?>
			</div>
			<button type="button" class="button oli-csp-add-group">
				<?php esc_html_e( 'Add OR group', 'oli-conditional-shipping-payments' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render one AND group.
	 *
	 * @param string $prefix      Field name prefix.
	 * @param int    $group_index Group index.
	 * @param array  $conditions  Conditions.
	 */
	public function render_condition_group( $prefix, $group_index, $conditions ) {
		if ( empty( $conditions ) || ! is_array( $conditions ) ) {
			$conditions = array(
				array(
					'type'     => 'cart_subtotal',
					'operator' => '>=',
					'value'    => '',
				),
			);
		}
		?>
		<div class="oli-csp-condition-group" data-group="<?php echo esc_attr( (string) $group_index ); ?>">
			<p class="oli-csp-and-label"><?php esc_html_e( 'AND group', 'oli-conditional-shipping-payments' ); ?></p>
			<div class="oli-csp-conditions">
				<?php foreach ( $conditions as $cond_index => $condition ) : ?>
					<?php $this->render_condition_row( $prefix, $group_index, $cond_index, $condition ); ?>
				<?php endforeach; ?>
			</div>
			<p class="oli-csp-group-actions">
				<button type="button" class="button oli-csp-add-condition"><?php esc_html_e( 'Add condition', 'oli-conditional-shipping-payments' ); ?></button>
				<button type="button" class="button-link oli-csp-remove-group"><?php esc_html_e( 'Remove group', 'oli-conditional-shipping-payments' ); ?></button>
			</p>
		</div>
		<?php
	}

	/**
	 * Render one condition row.
	 *
	 * @param string $prefix      Field name prefix.
	 * @param int    $group_index Group index.
	 * @param int    $cond_index  Condition index.
	 * @param array  $condition   Condition data.
	 */
	public function render_condition_row( $prefix, $group_index, $cond_index, $condition ) {
		$condition = is_array( $condition ) ? $condition : array();
		$type      = isset( $condition['type'] ) ? $condition['type'] : 'cart_subtotal';
		$operator  = isset( $condition['operator'] ) ? $condition['operator'] : '';
		$value     = isset( $condition['value'] ) ? $condition['value'] : '';
		$schema    = Oli_CSP_Utils::get_condition_schema();
		$config    = isset( $schema[ $type ] ) ? $schema[ $type ] : array(
			'input'     => 'number',
			'operators' => 'numeric',
			'multiple'  => false,
		);
		$name      = $prefix . '[condition_groups][' . $group_index . '][' . $cond_index . ']';
		?>
		<div class="oli-csp-condition" data-cond="<?php echo esc_attr( (string) $cond_index ); ?>">
			<select class="oli-csp-condition-type" name="<?php echo esc_attr( $name ); ?>[type]" data-field="type">
				<?php foreach ( Oli_CSP_Utils::get_condition_types() as $type_key => $type_label ) : ?>
					<option value="<?php echo esc_attr( $type_key ); ?>" <?php selected( $type, $type_key ); ?>>
						<?php echo esc_html( $type_label ); ?>
					</option>
				<?php endforeach; ?>
			</select>

			<?php $this->render_operator_select( $name . '[operator]', $config['operators'], $operator ); ?>

			<span class="oli-csp-condition-value">
				<?php $this->render_value_input( $name, $config, $value ); ?>
			</span>

			<button type="button" class="button-link oli-csp-remove-condition">
				<?php esc_html_e( 'Remove', 'oli-conditional-shipping-payments' ); ?>
			</button>
		</div>
		<?php
	}

	/**
	 * Render an operator select, or a hidden default.
	 *
	 * @param string $name          Field name.
	 * @param string $operator_type numeric, list, or none.
	 * @param string $selected      Selected operator.
	 */
	private function render_operator_select( $name, $operator_type, $selected ) {
		if ( 'none' === $operator_type ) {
			echo '<input type="hidden" class="oli-csp-condition-operator" name="' . esc_attr( $name ) . '" value="" data-field="operator" />';
			return;
		}

		$operators = ( 'list' === $operator_type ) ? Oli_CSP_Utils::get_list_operators() : Oli_CSP_Utils::get_numeric_operators();
		if ( '' === $selected ) {
			$keys     = array_keys( $operators );
			$selected = isset( $keys[0] ) ? $keys[0] : '';
		}
		?>
		<select class="oli-csp-condition-operator" name="<?php echo esc_attr( $name ); ?>" data-field="operator">
			<?php foreach ( $operators as $op_key => $op_label ) : ?>
				<option value="<?php echo esc_attr( $op_key ); ?>" <?php selected( $selected, $op_key ); ?>>
					<?php echo esc_html( $op_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Render the value control for a condition type.
	 *
	 * @param string $name   Field name prefix.
	 * @param array  $config Schema config.
	 * @param mixed  $value  Saved value.
	 */
	public function render_value_input( $name, $config, $value ) {
		$input    = isset( $config['input'] ) ? $config['input'] : 'text';
		$multiple = ! empty( $config['multiple'] );
		$values   = is_array( $value ) ? $value : ( '' === $value || null === $value ? array() : array( $value ) );

		switch ( $input ) {
			case 'number':
				$number = is_array( $value ) ? reset( $value ) : $value;
				echo '<input type="number" step="0.01" class="oli-csp-input-number" name="' . esc_attr( $name ) . '[value]" value="' . esc_attr( (string) $number ) . '" data-field="value" />';
				break;
			case 'yesno':
				$yesno = is_array( $value ) ? reset( $value ) : $value;
				if ( '' === $yesno || null === $yesno ) {
					$yesno = 'yes';
				}
				echo '<select class="oli-csp-input-yesno" name="' . esc_attr( $name ) . '[value]" data-field="value">';
				echo '<option value="yes" ' . selected( $yesno, 'yes', false ) . '>' . esc_html__( 'Yes', 'oli-conditional-shipping-payments' ) . '</option>';
				echo '<option value="no" ' . selected( $yesno, 'no', false ) . '>' . esc_html__( 'No', 'oli-conditional-shipping-payments' ) . '</option>';
				echo '</select>';
				break;
			case 'textarea':
				$text = is_array( $value ) ? implode( "\n", $value ) : (string) $value;
				echo '<textarea class="oli-csp-input-textarea" name="' . esc_attr( $name ) . '[value]" rows="2" data-field="value" data-multiple="1" placeholder="' . esc_attr__( 'One value per line. Wildcards (*) and ranges (1000-1099) allowed.', 'oli-conditional-shipping-payments' ) . '">' . esc_textarea( $text ) . '</textarea>';
				break;
			case 'product':
				$this->render_product_search( $name . '[value][]', $values );
				break;
			case 'category':
				$this->render_multiselect( $name . '[value][]', '', $this->get_term_choices( 'product_cat' ), $values, true );
				break;
			case 'tag':
				$this->render_multiselect( $name . '[value][]', '', $this->get_term_choices( 'product_tag' ), $values, true );
				break;
			case 'shipping_class':
				$this->render_multiselect( $name . '[value][]', '', $this->get_shipping_class_choices(), $values, true );
				break;
			case 'country':
				$this->render_multiselect( $name . '[value][]', '', $this->get_country_choices(), $values, true );
				break;
			case 'role':
				$this->render_multiselect( $name . '[value][]', '', $this->get_role_choices(), $values, true );
				break;
			case 'shipping_method':
				$this->render_multiselect( $name . '[value][]', '', $this->get_shipping_method_choices(), $values, true );
				break;
			case 'payment_gateway':
				$this->render_multiselect( $name . '[value][]', '', $this->get_payment_gateway_choices(), $values, true );
				break;
			default:
				$text = is_array( $value ) ? implode( ', ', $value ) : (string) $value;
				echo '<input type="text" class="oli-csp-input-text regular-text" name="' . esc_attr( $name ) . '[value]" value="' . esc_attr( $text ) . '" data-field="value" data-multiple="' . ( $multiple ? '1' : '0' ) . '" />';
				break;
		}
	}

	/**
	 * WooCommerce product search select.
	 *
	 * @param string $name   Field name.
	 * @param array  $values Selected product IDs.
	 */
	private function render_product_search( $name, $values ) {
		?>
		<select class="wc-product-search oli-csp-input-product" multiple="multiple" name="<?php echo esc_attr( $name ); ?>" data-field="value" data-multiple="1" data-placeholder="<?php esc_attr_e( 'Search for a product…', 'oli-conditional-shipping-payments' ); ?>" data-action="woocommerce_json_search_products_and_variations">
			<?php foreach ( $values as $product_id ) : ?>
				<?php
				$product_id = absint( $product_id );
				$product    = $product_id ? wc_get_product( $product_id ) : false;
				if ( ! $product ) {
					continue;
				}
				?>
				<option value="<?php echo esc_attr( (string) $product_id ); ?>" selected="selected">
					<?php echo esc_html( $product->get_formatted_name() ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Shared enhanced-select control.
	 *
	 * @param string $name     Field name.
	 * @param string $label    Optional label.
	 * @param array  $choices  Value => label.
	 * @param array  $selected Selected values.
	 * @param bool   $is_condition Whether this is a condition value field.
	 */
	private function render_multiselect( $name, $label, $choices, $selected, $is_condition = false ) {
		$selected = array_map( 'strval', (array) $selected );
		if ( '' !== $label ) {
			echo '<label>' . esc_html( $label ) . '</label>';
		}
		?>
		<select name="<?php echo esc_attr( $name ); ?>" multiple="multiple" class="wc-enhanced-select oli-csp-select-wide<?php echo $is_condition ? ' oli-csp-input-select' : ''; ?>"<?php echo $is_condition ? ' data-field="value" data-multiple="1"' : ''; ?>>
			<?php foreach ( $choices as $choice_value => $choice_label ) : ?>
				<option value="<?php echo esc_attr( (string) $choice_value ); ?>" <?php selected( in_array( (string) $choice_value, $selected, true ) ); ?>>
					<?php echo esc_html( $choice_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/**
	 * Hidden templates used by admin.js.
	 *
	 * @param string $name_base Field name prefix.
	 */
	public function render_templates( $name_base = 'oli_csp_restrictions' ) {
		?>
		<div class="oli-csp-templates" hidden>
			<?php foreach ( array_keys( Oli_CSP_Utils::get_restriction_types() ) as $type ) : ?>
				<template class="oli-csp-restriction-template" data-type="<?php echo esc_attr( $type ); ?>">
					<?php $this->render_restriction_row( $type, '__index__', array(), $name_base ); ?>
				</template>
			<?php endforeach; ?>
			<template class="oli-csp-group-template">
				<?php $this->render_condition_group( $name_base . '[__type__][__index__]', '__group__', array() ); ?>
			</template>
			<template class="oli-csp-condition-template">
				<?php $this->render_condition_row( $name_base . '[__type__][__index__]', '__group__', '__cond__', array() ); ?>
			</template>
		</div>
		<?php
	}

	/**
	 * Localized data for the condition builder script.
	 *
	 * @return array
	 */
	public function get_script_data() {
		return array(
			'types'     => Oli_CSP_Utils::get_condition_types(),
			'schema'    => Oli_CSP_Utils::get_condition_schema(),
			'operators' => array(
				'numeric' => Oli_CSP_Utils::get_numeric_operators(),
				'list'    => Oli_CSP_Utils::get_list_operators(),
			),
			'choices'   => array(
				'category'        => $this->get_term_choices( 'product_cat' ),
				'tag'             => $this->get_term_choices( 'product_tag' ),
				'shipping_class'  => $this->get_shipping_class_choices(),
				'country'         => $this->get_country_choices(),
				'role'            => $this->get_role_choices(),
				'shipping_method' => $this->get_shipping_method_choices(),
				'payment_gateway' => $this->get_payment_gateway_choices(),
			),
			'i18n'      => array(
				'yes'              => __( 'Yes', 'oli-conditional-shipping-payments' ),
				'no'               => __( 'No', 'oli-conditional-shipping-payments' ),
				'or'               => __( 'OR', 'oli-conditional-shipping-payments' ),
				'searchProduct'    => __( 'Search for a product…', 'oli-conditional-shipping-payments' ),
				'postcodeHint'     => __( 'One value per line. Wildcards (*) and ranges (1000-1099) allowed.', 'oli-conditional-shipping-payments' ),
				/* translators: %d: restriction number */
				'restrictionLabel' => __( 'Restriction #%d', 'oli-conditional-shipping-payments' ),
			),
		);
	}

	/**
	 * Shipping method type and zone instance choices.
	 *
	 * @return array
	 */
	public function get_shipping_method_choices() {
		$choices = array();

		if ( function_exists( 'WC' ) && WC() && WC()->shipping() ) {
			foreach ( WC()->shipping()->get_shipping_methods() as $method_id => $method ) {
				$choices[ $method_id ] = sprintf(
					/* translators: %s: shipping method title */
					__( '%s (all instances)', 'oli-conditional-shipping-payments' ),
					$method->get_method_title()
				);
			}
		}

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return $choices;
		}

		$zones    = WC_Shipping_Zones::get_zones();
		$raw      = new WC_Shipping_Zone( 0 );
		$zones[0] = array(
			'id'               => 0,
			'zone_name'        => $raw->get_zone_name(),
			'shipping_methods' => $raw->get_shipping_methods(),
		);

		foreach ( $zones as $zone ) {
			$zone_name = isset( $zone['zone_name'] ) ? $zone['zone_name'] : '';
			$methods   = isset( $zone['shipping_methods'] ) ? $zone['shipping_methods'] : array();
			foreach ( $methods as $instance ) {
				if ( ! is_object( $instance ) ) {
					continue;
				}
				$key             = $instance->id . ':' . $instance->instance_id;
				$choices[ $key ] = sprintf(
					/* translators: 1: method title, 2: zone name */
					__( '%1$s (%2$s)', 'oli-conditional-shipping-payments' ),
					$instance->get_title(),
					$zone_name
				);
			}
		}

		return $choices;
	}

	/**
	 * Shipping zone choices.
	 *
	 * @return array
	 */
	public function get_shipping_zone_choices() {
		$choices = array();

		if ( ! class_exists( 'WC_Shipping_Zones' ) ) {
			return $choices;
		}

		$choices['0'] = __( 'Locations not covered by your other zones', 'oli-conditional-shipping-payments' );

		foreach ( WC_Shipping_Zones::get_zones() as $zone ) {
			if ( isset( $zone['id'] ) ) {
				$choices[ (string) $zone['id'] ] = isset( $zone['zone_name'] ) ? $zone['zone_name'] : (string) $zone['id'];
			}
		}

		return $choices;
	}

	/**
	 * Payment gateway choices.
	 *
	 * @return array
	 */
	public function get_payment_gateway_choices() {
		$choices = array();

		if ( function_exists( 'WC' ) && WC() && WC()->payment_gateways() ) {
			foreach ( WC()->payment_gateways()->payment_gateways() as $gateway_id => $gateway ) {
				$choices[ $gateway_id ] = $gateway->get_title();
			}
		}

		return $choices;
	}

	/**
	 * Country choices.
	 *
	 * @return array
	 */
	public function get_country_choices() {
		if ( function_exists( 'WC' ) && WC() && WC()->countries ) {
			return WC()->countries->get_countries();
		}

		return array();
	}

	/**
	 * Role choices.
	 *
	 * @return array
	 */
	public function get_role_choices() {
		$choices = array();
		$roles   = function_exists( 'wp_roles' ) ? wp_roles()->get_names() : array();

		foreach ( $roles as $role_key => $role_name ) {
			$choices[ $role_key ] = translate_user_role( $role_name );
		}

		return $choices;
	}

	/**
	 * Taxonomy term choices.
	 *
	 * @param string $taxonomy Taxonomy name.
	 * @return array
	 */
	public function get_term_choices( $taxonomy ) {
		$choices = array();
		$terms   = get_terms(
			array(
				'taxonomy'   => $taxonomy,
				'hide_empty' => false,
				'number'     => 300,
			)
		);

		if ( is_wp_error( $terms ) || empty( $terms ) ) {
			return $choices;
		}

		foreach ( $terms as $term ) {
			$choices[ (string) $term->term_id ] = $term->name;
		}

		return $choices;
	}

	/**
	 * Shipping class choices.
	 *
	 * @return array
	 */
	public function get_shipping_class_choices() {
		$choices = array();

		if ( function_exists( 'WC' ) && WC() && WC()->shipping() ) {
			foreach ( WC()->shipping()->get_shipping_classes() as $class ) {
				$choices[ (string) $class->term_id ] = $class->name;
			}
		}

		return $choices;
	}
}
