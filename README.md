# WooCommerce Collection Date

A WordPress plugin that adds advanced collection date functionality to WooCommerce, allowing customers to select a preferred collection date during checkout with configurable lead times, category-based rules, and flexible business logic.

## Description

WooCommerce Collection Date extends WooCommerce by adding a sophisticated collection date picker to the checkout process. Store administrators can configure global settings, category-specific rules, and manage complex bakery/production business requirements with working days, cutoff times, and date exclusions.

## Features

### Core Features
- **Collection Date Selection**: Customer-friendly date picker on checkout
- **Tiered Lead Time System**: Product > Category > Global priority hierarchy
- **Category-Based Rules**: Different lead times per product category
- **Lead Time Calculation Types**:
  - **Calendar Days**: Simple day counting (includes weekends)
  - **Working Days**: Production-aware counting (skips non-working days)
- **Separate Day Configuration**:
  - **Working Days**: Days when business produces/prepares orders
  - **Collection Days**: Days when customers can pickup orders
- **Cutoff Times**: Per-category order deadlines with automatic penalty days
- **Date Exclusions**: Block specific dates (holidays, closures)
- **Cart Analysis**: Automatically uses longest lead time from all cart products

### Admin Features
- **6 Admin Tabs**:
  1. Settings - Global configuration
  2. Category Rules - Manage category-specific lead times
  3. Collection Schedule - Calendar view
  4. Orders - Filtered order list with collection dates
  5. Date Exclusions - Holiday/closure management
  6. Instructions - Plain language guide with examples
- **CRUD Interface**: Easy add/edit/delete for category rules
- **Order Meta Integration**: Collection dates stored with orders
- **Admin Order Display**: Collection dates visible in admin
- **Email Integration**: Collection dates included in order emails

### Technical Features
- **HPOS Compatible**: Supports WooCommerce High-Performance Order Storage
- **Block Checkout Ready**: Works with both classic and block checkout
- **REST API Endpoints**: Modern AJAX architecture
- **Database Storage**: Dedicated table for exclusion management
- **Internationalization Ready**: Fully translatable
- **Security**: Nonce verification, capability checks, sanitized inputs

## Requirements

- WordPress 5.8 or higher
- WooCommerce 5.0 or higher
- PHP 7.4 or higher
- MySQL 5.6 or higher

## Installation

### Via WordPress Admin

1. Download the plugin zip file
2. Navigate to **Plugins > Add New** in your WordPress admin
3. Click **Upload Plugin** and select the zip file
4. Click **Install Now**
5. Activate the plugin through the **Plugins** menu

### Manual Installation

1. Upload the `wc-collection-date` folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Navigate to **WooCommerce > Collection Dates** to configure

### Via Composer

```bash
composer require tomaszlewandowski/wc-collection-date
```

## Configuration

After activation, configure the plugin through **WooCommerce > Collection Dates**:

### Settings Tab (Global Defaults)

1. **Working Days (Production)**: Select days when your business prepares orders
2. **Collection Days (Customer Availability)**: Select days when customers can pickup
3. **Lead Time**: Set default minimum days before collection (e.g., 2 days)
4. **Lead Time Calculation**:
   - **Calendar Days**: Counts every day including weekends
   - **Working Days**: Only counts your working days (skips weekends/holidays)
5. **Cutoff Time**: Daily order deadline (optional, e.g., 12:00)
6. **Max Booking Days**: How far in advance customers can book (default: 90 days)

### Category Rules Tab (Advanced)

Create category-specific rules that override global settings:

1. Click **"+ Add Category Rule"**
2. Select a product category
3. Configure:
   - Lead Time (days)
   - Lead Time Type (Calendar/Working)
   - Cutoff Time (optional)
   - Working Days (production)
   - Collection Days (customer pickup)
4. Save changes

**Priority System**: Category rules > Global settings

### Instructions Tab

Built-in guide with:
- Plain language explanations
- Real-world examples
- Troubleshooting help
- Setup tutorials

## Real-World Example: Bakery Setup

### Scenario
A bakery produces different types of cakes requiring different lead times:
- Standard Cakes: 3 days
- 3D Cakes: 4 days
- Wedding Cakes: 7 days

### Configuration

**Step 1: Global Settings**
- Working Days: Mon-Fri (production days)
- Collection Days: All 7 days (customers can pickup anytime)
- Lead Time: 2 days (fallback)
- Lead Time Type: Working Days
- Cutoff Time: 12:00 PM

**Step 2: Category Rules**
1. **Standard Cakes**: 3 working days, 12pm cutoff
2. **3D Cakes**: 4 working days, 12pm cutoff
3. **Wedding Cakes**: 7 working days, 5pm cutoff

**Step 3: Assign Products**
- Assign each product to its appropriate category
- Plugin automatically applies correct lead time

**Result**:
- Customer orders "Birthday Cake" (Standard) → 3 working days
- Customer orders "Wedding Cake" → 7 working days
- Cart with both → Uses 7 days (longest wins)

## REST API Endpoints

### Get Available Dates
```
GET /wp-json/wc-collection-date/v1/dates
Parameters:
  - limit (int, optional): Number of dates to return (default: 90)
Response:
  {
    "success": true,
    "dates": ["2025-12-15", "2025-12-16", ...],
    "count": 90
  }
```

### Check Date Availability
```
POST /wp-json/wc-collection-date/v1/dates/check
Body:
  {
    "date": "2025-12-20"
  }
Response:
  {
    "success": true,
    "date": "2025-12-20",
    "available": true,
    "message": "Date is available"
  }
```

### Get Date Range
```
GET /wp-json/wc-collection-date/v1/dates/range
Response:
  {
    "success": true,
    "min_date": "2025-12-13",
    "max_date": "2026-03-13"
  }
```

### Get Plugin Settings
```
GET /wp-json/wc-collection-date/v1/settings
Response:
  {
    "success": true,
    "settings": {
      "lead_time": 2,
      "max_booking_days": 90,
      "working_days": [1, 2, 3, 4, 5],
      "date_format": "F j, Y"
    }
  }
```

## Database Tables

The plugin creates the following table on activation:

```sql
wp_wc_collection_exclusions
- id (bigint) - Primary key
- exclusion_date (date) - Unique excluded date
- reason (varchar) - Optional reason for exclusion
- created_at (datetime) - Creation timestamp
- updated_at (datetime) - Last update timestamp
```

## How It Works

### Priority System

When calculating available dates, the system checks rules in this order:

1. **Product Override** (Phase 4 - not yet implemented)
2. **Category Rules** ← Currently active
3. **Global Settings** ← Fallback

### Working Days vs Collection Days

**Critical Distinction:**

- **Working Days**: Days when your business *produces* orders (affects lead time calculation)
- **Collection Days**: Days when customers *can pickup* orders (affects date picker display)

**Example:**
- Working Days: Mon-Fri
- Collection Days: All 7 days
- Result: System calculates 3 working days (skipping weekends), but customers can select weekend pickup dates

### Cart Behavior

When cart contains multiple products:
1. System analyzes all products
2. Finds product with longest lead time
3. Uses those settings for date calculation
4. Ensures all products can be prepared

### Cutoff Time

Orders placed after cutoff time get +1 day penalty:
- Order at 11:00 AM (before 12:00 cutoff) = 3 days
- Order at 2:00 PM (after 12:00 cutoff) = 4 days

## Development

### Setup Development Environment

```bash
# Clone repository
git clone https://github.com/yourusername/wc-collection-date.git
cd wc-collection-date

# Install dependencies
composer install
npm install

# Run tests
composer test

# Check coding standards
composer phpcs

# Fix coding standards
composer phpcbf
```

### Testing

```bash
# Run PHPUnit tests
composer test

# Run with coverage
phpunit --coverage-html coverage/
```

### Coding Standards

This plugin follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/wordpress-coding-standards/):

```bash
# Check code
composer phpcs

# Auto-fix issues
composer phpcbf
```

## File Structure

```
wc-collection-date/
├── assets/
│   ├── css/
│   │   ├── admin.css
│   │   └── checkout.css
│   └── js/
│       ├── admin.js
│       └── checkout.js
├── includes/
│   ├── admin/
│   │   ├── class-admin.php
│   │   └── class-settings.php
│   ├── class-wc-collection-date.php
│   ├── class-activator.php
│   ├── class-deactivator.php
│   ├── class-lead-time-resolver.php
│   ├── class-date-calculator.php
│   ├── class-checkout.php
│   ├── class-block-checkout-integration.php
│   └── class-rest-api.php
├── development-docs/
│   ├── CODE_QUALITY_AUDIT_REPORT.md
│   ├── AUDIT_SUMMARY.md
│   ├── PRE_PRODUCTION_CHECKLIST.md
│   └── (other development files)
├── tests/
├── .gitignore
├── composer.json
├── README.md
└── wc-collection-date.php
```

## Hooks and Filters

### Actions

```php
// Before collection date field is added
do_action( 'wc_collection_date_before_field' );

// After collection date is saved
do_action( 'wc_collection_date_saved', $order_id, $date );
```

### Filters

```php
// Modify available dates
apply_filters( 'wc_collection_date_available_dates', $dates );

// Modify field arguments
apply_filters( 'wc_collection_date_field_args', $args );
```

## Security

- All user inputs sanitized using WordPress functions
- Database queries use prepared statements via `$wpdb->prepare()`
- Nonce verification for all form submissions
- Capability checks for admin operations (`manage_woocommerce`)
- XSS prevention through proper escaping
- CSRF protection on all actions

## Compatibility

### WooCommerce
- HPOS (High-Performance Order Storage): ✅ Full support
- Block Checkout: ✅ Full support
- Classic Checkout: ✅ Full support
- WooCommerce 5.0+: ✅ Tested
- WooCommerce 9.0: ✅ Tested

### WordPress
- WordPress 5.8+: ✅ Tested
- Multisite: ✅ Compatible

## Support

For support, feature requests, or bug reports:

- GitHub Issues: https://github.com/yourusername/wc-collection-date/issues
- Documentation: https://github.com/yourusername/wc-collection-date/wiki

## Changelog

### 1.0.0 - 2025-12-10

**Core Features:**
- Collection date picker on checkout
- HPOS and Block Checkout compatibility
- REST API endpoints

**Phase 1 - Working Days & Cutoff:**
- Calendar vs Working Days calculation
- Cutoff time with penalty days
- Separate Working Days and Collection Days

**Phase 2 - Category Rules:**
- Tiered lead time system (Product > Category > Global)
- Category-based lead time rules with CRUD interface
- Multiple category resolution (longest wins)
- Cart analysis for multiple products
- Per-category cutoff times

**Admin Features:**
- 6 admin tabs (Settings, Category Rules, Schedule, Orders, Exclusions, Instructions)
- Instructions tab with plain language guide
- Date exclusions management
- Admin settings panel

**Technical:**
- Database table for exclusions
- Working days calculation
- Maximum booking window
- Order meta integration
- Email integration

## License

This plugin is licensed under the GPL v2 or later.

## Credits

Developed by Tomasz 'Amigo' Lewandowski

## Contributing

Contributions are welcome! Please:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

Please ensure:
- Code follows WordPress Coding Standards
- All tests pass
- New features include tests
- Documentation is updated

## Roadmap

### Phase 3 - Enhanced Features (Implemented)
- ✅ Cart integration with longest lead time detection
- ✅ REST API for AJAX operations
- ✅ Block Checkout support

### Phase 4 - Product-Level Overrides (Planned)
- Individual product lead time settings
- Override category and global rules
- Product meta UI
- API extensions

### Future Enhancements
- WooCommerce Subscriptions compatibility
- Booking calendar admin view
- CSV export for collection schedule
- Email notifications for upcoming collections
- Multi-location support
