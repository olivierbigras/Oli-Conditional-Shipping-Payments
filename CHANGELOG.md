# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.0] - 2026-10-19

### Added
- Initial release of Oli Conditional Shipping and Payments for WooCommerce
- Three restriction types:
  - Shipping Methods (exclude methods/instances)
  - Payment Gateways (exclude gateways)
  - Shipping Destinations (block countries/states/postcodes with custom message)
- 22 condition types with AND logic per rule:
  - Cart Total, Cart Subtotal, Order Total
  - Product in Cart, Category in Cart, Tag in Cart
  - Shipping Class in Cart
  - Package Weight, Item Quantity
  - Billing/Shipping Country, State, Postcode (with wildcards and ranges)
  - Customer Role, User Logged In/Out
  - Coupon Code Used
  - Backorder in Cart
  - Currency (multicurrency support)
  - Shipping Method Selected, Payment Gateway Selected
- Global restrictions (WooCommerce > Settings > Restrictions tab)
- Per-product restrictions (Product Data meta box)
- Multiple rules support with independent condition sets
- Full support for classic checkout
- Full support for block-based checkout (Store API integration)
- HPOS (High-Performance Order Storage) compatibility
- Multicurrency support via Currency condition
- Custom notices shown at cart/checkout
- "Show excluded methods as disabled" option
- Debug/Test mode for administrators
- Import/Export restrictions as JSON
- Importer from official WooCommerce Conditional Shipping and Payments extension
  - Dry-run mode with comparison report
  - Automatic data conversion and mapping
  - Product-specific restriction import
- Admin settings UI with intuitive restriction management
- Unit tests for condition engine
- GitHub Actions CI workflow (PHP lint, PHPCS, PHPUnit)
- Full internationalization support with .pot file
- Complete WordPress.org plugin guidelines compliance
- Plugin Check (PCP) compatibility

### Technical Details
- Minimum WordPress version: 6.5
- Minimum WooCommerce version: 9.0
- Minimum PHP version: 7.4
- Tested up to WordPress 7.1
- Tested with WooCommerce 11.0

### Developer Features
- Extensible condition engine via filters
- Sanitization and escaping throughout
- Proper nonces and capability checks
- Prefixed functions and classes (oli_csp_ / Oli_CSP)
- No external API calls or tracking
- Clean, documented code following WordPress coding standards

## [Unreleased]

### Known Gaps vs Official Extension
- Advanced condition grouping (OR logic between rule groups) - planned for future release
- Per-zone shipping method restrictions - planned for future release
- Restriction scheduling (date/time-based) - planned for future release
- Customer history conditions (previous orders, lifetime value) - planned for future release

### Planned Features
- Admin UI improvements with live preview
- Bulk import/export for product restrictions
- Restriction templates/presets
- Enhanced debug logging
- More granular state/postcode controls
- Integration with popular subscription and membership plugins

[0.1.0]: https://github.com/bigrat95/oli-conditional-shipping-payments/releases/tag/0.1.0
