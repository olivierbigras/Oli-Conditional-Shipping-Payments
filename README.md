# Oli Conditional Shipping Payments

[![CI](https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments/actions/workflows/ci.yml/badge.svg)](https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments/actions)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)
[![WordPress](https://img.shields.io/badge/WordPress-6.5%2B-blue)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-9.0%2B-purple)](https://woocommerce.com)

A WordPress plugin to restrict shipping methods, payment gateways, and shipping destinations in WooCommerce based on conditional rules.

## Features

**Three Restriction Types**
- Shipping Methods (exclude methods/instances)
- Payment Gateways (exclude gateways)
- Shipping Destinations (block countries/states/postcodes)

**22 Condition Types**
- Cart/Order totals, products, categories, tags
- Shipping classes, package weight, item quantities
- Location-based (countries, states, postcodes with wildcards)
- Customer roles, login status, coupons
- Backorders, currency, selected methods/gateways
- OR logic between condition groups; AND logic within a group
- Per-zone shipping method restrictions
- Admin rules UI under WooCommerce > Settings > Restrictions

**Global & Per-Product Restrictions**
- Set site-wide restrictions
- Override with product-specific rules

**Modern WooCommerce Support**
- Classic checkout
- Block checkout (Store API)
- HPOS compatible
- Multicurrency support

**Import/Export**
- JSON import/export
- Import from official WooCommerce extension
- Dry-run mode with comparison report

## Installation

1. Upload the plugin to `/wp-content/plugins/oli-conditional-shipping-payments/`
2. Activate through WordPress admin
3. Go to **WooCommerce > Settings > Restrictions**

## Requirements

- WordPress 6.5+
- WooCommerce 9.0+
- PHP 7.4+

## Documentation

See [readme.txt](readme.txt) for full documentation, including the External services section.

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test

# Check coding standards
composer lint
```

## Testing

Unit tests use PHPUnit with WordPress test framework:

```bash
# Set up test environment
bash tests/bin/install-wp-tests.sh wordpress_test root root localhost latest

# Run tests
vendor/bin/phpunit
```

## Migration from Official Extension

The plugin can import restrictions from **WooCommerce Conditional Shipping and Payments 3.x**:

1. Go to **WooCommerce > Settings > Restrictions**
2. Click **Import from Official Extension**
3. Run a dry-run first to preview
4. Import to migrate all rules

## Known Gaps vs Official Extension

- Restriction scheduling (date/time-based)
- Customer history conditions (previous orders, lifetime value)

See [CHANGELOG.md](CHANGELOG.md) for the roadmap.

## License

GPL v2 or later — see [LICENSE](LICENSE)

## Author

**Olivier Bigras**
- Website: [olivierbigras.com](https://olivierbigras.com/)
- WordPress.org: [@bigrat95](https://profiles.wordpress.org/bigrat95/)
- GitHub: [olivierbigras](https://github.com/olivierbigras/Oli-Conditional-Shipping-Payments)

## Credits

Written independently. Not affiliated with WooCommerce.
