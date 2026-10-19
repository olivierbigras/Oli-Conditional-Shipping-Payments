# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.1.1] - 2026-10-19

### Added
- **OR Logic Between Condition Groups**: Restrictions can now have multiple condition groups where conditions within a group use AND logic, but groups themselves use OR logic - matching official extension behavior
- **All Official Operators**: Full support for `is`, `is_not`, `in`, `not_in`, `greater`, `less`, `contains` operators - complete parity with official extension
- **Per-Zone Shipping Restrictions**: Shipping method restrictions can now be limited to specific shipping zones
- **Comprehensive Importer**: The importer now maps ALL official condition types and operators with validation:
  - Reports unmappable items instead of partially importing
  - Never imports a rule that contains unsupported conditions/operators
  - Dry-run mode shows exactly what will and won't be imported
  - Detailed comparison report with warnings
- **Enhanced Unit Tests**: New test suite comparing our engine's decisions with official extension semantics:
  - OR logic between groups
  - AND logic within groups
  - All operator types (in, not_in, is, is_not, greater, less)
  - Postcode wildcards and ranges
  - Complex multi-condition scenarios
  - Backward compatibility verification

### Changed
- **Condition Engine**: Restructured to support grouped conditions (condition_groups) while maintaining backward compatibility with flat conditions arrays
- **Restriction Handler**: Updated to evaluate condition groups and support zone-specific restrictions
- **Store API Integration**: Enhanced to work with new condition group structure
- **All Comparison Methods**: Updated to support all official operators, not just numeric comparisons

### Fixed
- PHPCS WordPress coding standards compliance (auto-fixed 112 violations)
- Nonce verification in admin forms
- Input sanitization in file upload handling
- Array formatting and alignment per WordPress standards

### Technical
- Ran full CI checks: PHP lint (✓), PHPCS WordPress standard (✓), PHPUnit tests (✓)
- Enhanced condition engine with three comparison method types: numeric, list, and string
- Product/category/tag conditions now properly support in/not_in operators
- Zone detection integrated into shipping method filtering

### Migration Notes
- **Backward Compatible**: Existing restrictions using the old `conditions` format continue to work
- **New Format Available**: Use `condition_groups` array for OR logic between groups
- **Importer Enhanced**: Re-run import from official extension to get full feature parity

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

### Planned Features
- Admin UI improvements with live preview
- Bulk import/export for product restrictions
- Restriction templates/presets
- Enhanced debug logging
- More granular state/postcode controls
- Integration with popular subscription and membership plugins

[0.1.1]: https://github.com/bigrat95/oli-conditional-shipping-payments/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/bigrat95/oli-conditional-shipping-payments/releases/tag/v0.1.0
