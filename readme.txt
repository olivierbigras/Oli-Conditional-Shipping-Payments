=== Oli Conditional Shipping Payments ===
Contributors: bigrat95
Tags: woocommerce, shipping, payment, conditional, restrictions
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 0.2.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 9.0
WC tested up to: 11.2

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

The plugin supports 22 different condition types. Build rules in WooCommerce > Settings > Restrictions. Conditions inside a group use AND logic; multiple groups use OR logic:

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

== External services ==

This plugin does not connect to any third-party or external service. Restriction rules are stored in the WordPress database and evaluated locally at cart and checkout. There are no remote HTTP calls, no tracking, and no third-party scripts or fonts.

GitHub is linked only as the public source-code repository (https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments). The plugin itself never contacts GitHub.

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

= Does this plugin send data to external services? =

No. All rules are stored and evaluated on your site. See the External services section.

== Screenshots ==

1. General settings for shipping and payment restrictions.
2. A shipping rule with its conditions (e.g. hide a method for a postcode pattern).
3. A payment rule (e.g. hide Cash on delivery above a cart total).
4. The restriction notice shown to the customer at checkout.

== Changelog ==

= 0.2.8 - 2026-10-28 =
* Renamed for wp.org slug match; Tested up to only in readme.

= 0.2.7 =
* Fix: on the checkout block, notices containing < or & were shown encoded (for example "&lt;24h &amp;") in the banner and the screen reader announcement. They are now sent to the block script as plain text, which it renders as text only. Saving and the classic checkout are unchanged.

= 0.2.6 - 2026-10-27 =
* Security: settings-screen notices (import result, import report, errors) are shown only with a nonce and the manage_woocommerce capability.
* Security: the admin asset loader uses the settings tab resolved by WooCommerce instead of reading the URL.
* Classic cart and checkout notice containers are escaped at output with wp_kses() and a strict whitelist.
* The JSON export is sent with wp_send_json() instead of a raw echo.
* The import "dry run" flag is unslashed and sanitized.
* The product meta import query uses $wpdb->prepare() directly with a %i table placeholder.
* Saved rules (global and per product) are unslashed and sanitized where they are read, with sanitize_textarea_field() so multi-line postcode lists keep their line breaks.

= 0.2.5 - 2026-10-26 =
* Render checkout-block restriction notices inline instead of WooCommerce's notice banner
* Announce each restriction once after the cart store settles (assertive for shipping, polite for payments)

= 0.2.4 - 2026-10-26 =
* Announce each checkout-block notice once (createNotice speak false); shipping uses assertive
* Add Screenshots section captions for settings, shipping rule, payment rule, and checkout notice

= 0.2.3 - 2026-10-26 =
* Announce each new checkout-block restriction notice once to screen readers

= 0.2.2 - 2026-10-26 =
* Show shipping restriction notices on the checkout block in wc/checkout/shipping-methods (fallback wc/checkout)

= 0.2.1 - 2026-10-26 =
* Show restriction notices on the checkout block via Store API cart extensions
* Render classic checkout notices once in a replaceable update_order_review fragment
* Do not store restriction notices in the WooCommerce session

= 0.2.0 - 2026-10-26 =
* Rules UI under WooCommerce > Settings > Restrictions with AND/OR condition groups
* Disable shipping methods or zone methods, payment gateways, and destinations
* Per-rule checkout notices on classic and block checkout
* Operators for location, role, coupon, and catalog conditions
* Standalone PHPUnit coverage for rule evaluation
* Plugin URI and Author URI are now distinct; Author URI is https://github.com/olivierbigras

= 0.1.2 - 2026-10-21 =
* WordPress.org readiness: External services section, GPLv2 LICENSE, uninstall cleanup
* Move remaining inline CSS widths into the enqueued admin stylesheet
* Sanitize condition groups and zone IDs; capability check on product save
* Destination restrictions now evaluate condition groups (OR) like shipping and payments
* Import report is shown after an official-extension import
* GitHub links updated to github.com/olivierbigras
* Tested up to WooCommerce 11.2
* Exclude development files from the distributable ZIP (.distignore / export-ignore)

= 0.1.1 - 2026-10-19 =
* OR logic between condition groups (AND within a group)
* Official operators: is, is_not, in, not_in, greater, less, contains
* Per-zone shipping method restrictions
* Importer maps official conditions/operators and never partially imports a rule
* Official-semantics unit tests

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

= 0.2.7 =
Checkout block notices containing < or & now display correctly.

= 0.2.6 =
Security hardening of admin notices, output escaping, JSON export and the import query. No data changes.

= 0.2.5 =
Shows checkout-block restriction notices inline and announces each one once after shipping rates settle.

= 0.2.4 =
Announces each checkout-block restriction notice once. Shipping notices use assertive so they are not overwritten.

= 0.2.3 =
Announces new checkout-block restriction notices to screen readers once.

= 0.2.2 =
Shows shipping restriction notices on the checkout block in the shipping-methods notice area.

= 0.2.1 =
Fixes checkout notices: the block checkout now shows custom restriction messages, and classic checkout no longer duplicates them.

= 0.2.0 =
Adds the AND/OR rules UI, per-rule checkout notices, and zone-method targeting.

= 0.1.2 =
WordPress.org readiness: no external services, uninstall cleanup, and safer admin sanitization. Existing oli_csp_* settings are kept.

= 0.1.1 =
Adds OR groups, official operators, and per-zone shipping restrictions. Existing rules keep working.

= 0.1.0 =
Initial release of Oli Conditional Shipping and Payments for WooCommerce.

== Privacy ==

This plugin does not collect, store, or transmit personal data to any third party. Restriction rules and settings are stored in your WordPress database (`oli_csp_*` options and optional `_oli_csp_restrictions` product meta). Uninstalling the plugin removes those options, the version flag, and product meta.

== Additional Info ==

Author: Olivier Bigras
Author URI: https://github.com/olivierbigras
Plugin URI: https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments
Email: oli@olivierbigras.com

**Compatibility:**

This plugin is designed to provide feature parity with the official WooCommerce Conditional Shipping and Payments extension 3.x, but is written independently. It can import restrictions from the official extension for easy migration.

**Support:**

For support, feature requests, or bug reports, please visit https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments
