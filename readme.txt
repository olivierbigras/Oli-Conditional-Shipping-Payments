=== Oli Conditional Shipping and Payments for WooCommerce ===
Contributors: bigrat95
Tags: woocommerce, shipping, payment, conditional, restrictions
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.1.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 9.0
WC tested up to: 11.0

Restrict shipping methods, payment gateways, and shipping destinations based on conditional rules for WooCommerce.

== Description ==

Oli Conditional Shipping and Payments for WooCommerce allows you to create powerful conditional restrictions for your WooCommerce store. Control which shipping methods, payment gateways, and shipping destinations are available to customers based on a wide variety of conditions.

= Features =

**Three Restriction Types:**

* **Shipping Methods** - Exclude specific shipping methods or instances
* **Payment Gateways** - Exclude specific payment gateways
* **Shipping Destinations** - Block shipping to specific countries, states, or postcodes with custom messages

**Global and Product-Specific Restrictions:**

* Create global restrictions that apply to all products
* Set per-product restrictions that apply only when specific products are in the cart
* Combine global and product-specific restrictions for maximum flexibility

**Powerful Condition Engine:**

The plugin supports 22 different condition types with AND logic for precise control:

* Cart Total, Cart Subtotal, Order Total
* Product in Cart, Category in Cart, Tag in Cart
* Shipping Class in Cart
* Package Weight, Item Quantity
* Billing Country, State, Postcode (with wildcards and ranges)
* Shipping Country, State, Postcode (with wildcards and ranges)
* Customer Role
* User Logged In/Out
* Coupon Code Used
* Backorder in Cart
* Currency (multicurrency support)
* Shipping Method Selected
* Payment Gateway Selected

**Modern WooCommerce Support:**

* Full support for classic checkout
* Full support for block-based checkout (Store API)
* HPOS (High-Performance Order Storage) compatible
* Multicurrency support

**Developer-Friendly:**

* Import/Export restrictions as JSON
* Import from WooCommerce Conditional Shipping and Payments extension (official)
* Debug mode for administrators
* Extensible via filters and actions

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/oli-conditional-shipping-payments/` or install through WordPress plugins screen
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to WooCommerce > Settings > Restrictions to configure global restrictions
4. Edit individual products to set product-specific restrictions

== Frequently Asked Questions ==

= Does this work with the block checkout? =

Yes! The plugin fully supports both classic and block-based checkout.

= Can I import from the official WooCommerce extension? =

Yes! Go to WooCommerce > Settings > Restrictions and use the "Import from Official Extension" feature. You can run a dry-run first to preview the import.

= How do postcode wildcards and ranges work? =

For wildcards, use `*` as a placeholder. For example, `902*` matches any postcode starting with 902.
For ranges, use a hyphen. For example, `90210-90220` matches any postcode in that range.

= Is this compatible with HPOS? =

Yes, the plugin declares full compatibility with WooCommerce's High-Performance Order Storage.

= Can I set restrictions per product? =

Yes! Edit any product and scroll down to the "Product-Specific Restrictions" section in the General tab.

== Screenshots ==

1. Global restrictions settings page
2. Shipping method restrictions with conditions
3. Product-specific restrictions
4. Import/Export interface

== Changelog ==

= 0.1.0 - 2026-10-19 =
* Initial release
* Three restriction types: Shipping Methods, Payment Gateways, Shipping Destinations
* 22 condition types with AND logic
* Global and per-product restrictions
* Classic and block checkout support
* HPOS compatibility
* Import/Export as JSON
* Importer from official WooCommerce extension

== Upgrade Notice ==

= 0.1.0 =
Initial release of Oli Conditional Shipping and Payments for WooCommerce.

== Additional Info ==

**Compatibility:**

This plugin is designed to provide feature parity with the official WooCommerce Conditional Shipping and Payments extension 3.x, but is written independently. It can import restrictions from the official extension for easy migration.

**Support:**

For support, feature requests, or bug reports, please visit the plugin's GitHub repository.

**Privacy:**

This plugin does not collect, store, or transmit any user data. All restriction rules are stored locally in your WordPress database.
