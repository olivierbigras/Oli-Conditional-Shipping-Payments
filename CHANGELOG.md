# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.7] - 2026-10-27

### Corrigé
- Checkout en blocs : les avis contenant `<` ou `&` s'affichaient encodés (« &lt;24h &amp; ») dans la bannière et l'annonce lecteur d'écran. Le texte est décodé avec `wp_specialchars_decode( …, ENT_QUOTES )` au moment de l'envoi au script du bloc (données Store API), qui l'affiche via `textContent` / `wp.a11y.speak` (jamais en HTML). Enregistrement et checkout classique inchangés.
- Tests PHPUnit : « <24h & » rendu en texte littéral côté bloc, et `<script>` qui reste du texte.

## [0.2.6] - 2026-10-27

### Security
- Avis de l'écran de réglages (résultat d'import, rapport d'import, erreurs) affichés seulement avec un nonce et la capacité manage_woocommerce
- Chargement des assets admin : onglet résolu par WooCommerce (global $current_tab) au lieu de lire l'URL
- Conteneurs d'avis du panier et du checkout classiques échappés à la sortie avec wp_kses() et une liste blanche stricte
- Export JSON envoyé avec wp_send_json() au lieu d'un echo brut
- Option « dry run » de l'import lue avec wp_unslash et sanitize_text_field
- Requête d'import des métas produit : $wpdb->prepare() direct avec le placeholder %i pour la table
- Règles enregistrées (globales et produit) nettoyées dès la lecture avec map_deep( wp_unslash(), sanitize_textarea_field ) puis sanitize_restriction_data : les listes de codes postaux multi-lignes gardent leurs retours de ligne

## [0.2.5] - 2026-10-26

### Fixed
- Block checkout no longer uses WooCommerce's notice banner (`createNotice`), which always announces politely and doubled the restriction message
- Restriction notices render inline in the shipping and payment slots, styled like WooCommerce notices
- The plugin announces each restriction once with `wp.a11y.speak` after the cart store and shipping rates stay stable for 500 ms, so later WooCommerce speaks cannot clear it. Shipping is assertive; payments stay polite. Classic checkout is unchanged.

## [0.2.4] - 2026-10-26

### Added
- `== Screenshots ==` captions in readme.txt for the settings, shipping rule, payment rule, and checkout notice screens

### Fixed
- Block checkout creates restriction notices with `speak: false` so `createNotice` does not double-announce
- Shipping-area notices are spoken once with `wp.a11y.speak( message, 'assertive' )` so WooCommerce's later polite shipping-options message cannot replace them. Payments stay polite.

## [0.2.3] - 2026-10-26

### Fixed
- Block checkout restriction notices are announced once to screen readers with `wp.a11y.speak`. Classic checkout styling is unchanged.

## [0.2.2] - 2026-10-26

### Fixed
- Block checkout shipping notices now use the rendered `wc/checkout/shipping-methods` context (fallback `wc/checkout` when that container is absent). Payments stay on `wc/checkout/payments`.

## [0.2.1] - 2026-10-26

### Fixed
- Checkout block now shows custom restriction notices through Store API cart extension data and a `core/notices` renderer
- Classic checkout prints each restriction notice once inside a fragment replaced by `update_order_review`
- Restriction notices are no longer written to the WooCommerce session, so block requests cannot leak onto classic pages

## [0.2.0] - 2026-10-26

### Added
- Rules UI under WooCommerce > Settings > Restrictions with AND groups and OR between groups
- Condition fields for every supported type (cart totals, weight, quantity, catalog, location, customer, coupon)
- Disable a shipping method type or a specific zone method
- Per-rule checkout notices on classic checkout and the checkout block
- Dedicated checkout-notice rules that do not hide methods or gateways
- Evaluation snapshot API so PHPUnit can cover rule evaluation without WooCommerce
- Cart/checkout blocks compatibility declaration

### Changed
- Plugin URI is now the GitHub repository; Author URI is https://github.com/olivierbigras
- Dependency admin notices appear only on the Plugins screen
- Location, role, coupon, and catalog conditions honor in / not_in operators
- PHPCS ruleset uses WordPress-Extra
- Distributable ZIP includes CHANGELOG.md

### Fixed
- Empty condition groups no longer make an OR rule match by accident

## 2026-10-23

### Modifié
- composer.json : courriel de l’auteur remplacé par oli@olivierbigras.com.

## [0.1.2] - 2026-10-21

### Added
- `== External services ==` section in readme.txt (no remote calls)
- GPLv2 LICENSE, uninstall cleanup, and `phpcs.xml.dist`
- `.distignore` and `.gitattributes` `export-ignore` so the distributable ZIP omits tests, CI, Composer, and internal markdown
- Upgrade routine that copies any leftover `csp_*` options to `oli_csp_*` then deletes the old keys
- Import report notice after an official-extension import
- Hidden restriction-row template so "Add Restriction" creates real fields

### Changed
- Inline `style="width:…"` attributes moved to the enqueued admin stylesheet
- Destination restrictions evaluate `condition_groups` the same way as shipping and payments
- GitHub links and Composer package name use `github.com/olivierbigras`
- WC tested up to 11.2; PHPUnit config lives at the repository root

### Fixed
- Product restriction save requires `edit_product`
- JSON import accepts only an uploaded `.json` file
- `condition_groups` and `zones` are sanitized on save
- Nested import/export forms inside WooCommerce settings (invalid HTML)

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
- Admin settings UI with restriction management
- Unit tests for condition engine
- GitHub Actions CI workflow (PHP lint, PHPCS, PHPUnit)
- Full internationalization support with .pot file

### Technical Details
- Minimum WordPress version: 6.5
- Minimum WooCommerce version: 9.0
- Minimum PHP version: 7.4
- Tested up to WordPress 7.1
- Tested with WooCommerce 11.2

### Developer Features
- Extensible condition engine via filters
- Sanitization and escaping throughout
- Proper nonces and capability checks
- Prefixed functions and classes (oli_csp_ / Oli_CSP)
- No external API calls or tracking
- Clean, documented code following WordPress coding standards

[0.2.5]: https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments/compare/v0.2.4...v0.2.5
[0.2.4]: https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments/compare/v0.2.3...v0.2.4
[0.2.3]: https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments/compare/v0.2.2...v0.2.3
[0.2.2]: https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments/compare/v0.2.1...v0.2.2
[0.2.1]: https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments/compare/v0.2.0...v0.2.1
[0.2.0]: https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments/compare/v0.1.2...v0.2.0
[0.1.2]: https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments/compare/v0.1.1...v0.1.2
[0.1.1]: https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments/compare/v0.1.0...v0.1.1
[0.1.0]: https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments/releases/tag/v0.1.0
