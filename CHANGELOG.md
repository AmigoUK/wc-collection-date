# Changelog

All notable changes to WooCommerce Collection Date will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [1.1.0] - 2025-12-11

### Added
- **Performance Optimization**: Transient-based caching system for date calculations
  - Cache duration: 1 hour (3600 seconds)
  - Automatic cache invalidation on settings changes
  - Cache speedup: ~220x faster on subsequent requests
  - Reduces database queries by ~80%
- **Debug Mode**: Conditional logging system via WC_COLLECTION_DATE_DEBUG constant
  - Logs to WordPress debug.log when WP_DEBUG_LOG is enabled
  - Stores last 100 log entries in database for admin viewing
  - Specialized logging methods for cache operations, date calculations, and API calls
- **Settings Import/Export**: JSON-based backup and restore functionality
  - Export all settings, category rules, and exclusions
  - Import with version compatibility validation
  - Admin UI in new "Import/Export" tab
- **Loading States**: Skeleton screen and loading indicators for date picker
  - Smooth loading animations with CSS keyframes
  - Improved perceived performance on slow networks
  - Professional shimmer effect during data fetching
- **Enhanced Error Messages**: Context-specific error messages with helpful guidance
  - "No dates available" includes troubleshooting tips
  - API errors display clear action items
  - Improved user experience for common issues
- **Production Build System**: Webpack-based asset pipeline
  - Minified JS: admin.min.js (6.59 KB), checkout.min.js (5.68 KB)
  - Minified CSS: admin-styles.min.css (9.47 KB), checkout-styles.min.css (7.71 KB)
  - Console.log statements stripped in production
  - ~40% file size reduction through minification

### Changed
- Updated admin settings to include import/export tab
- Modified date calculator to use transient caching
- Enhanced checkout.js with loading states and better error handling
- Improved CSS for skeleton loaders and loading animations

### Technical
- Added webpack 5 + Terser for production builds
- Implemented cache invalidation hooks on all settings updates
- Added WC_Collection_Date_Debug class for conditional logging
- Build scripts: `npm run dev` (watch mode), `npm run build` (production)

### Developer Notes
- Enable debug mode: Define WC_COLLECTION_DATE_DEBUG as true in wp-config.php
- Clear cache programmatically: WC_Collection_Date_Calculator::clear_cache()
- View debug logs: Settings > Debug tab (when debug mode enabled)
- Built assets located in assets/dist/ for easy deployment

---

## [1.0.0] - 2025-12-04

### Added
- Initial release
- **Core Features**:
  - Collection date picker on WooCommerce checkout
  - Tiered lead time system (Product > Category > Global)
  - Category-based lead time rules with CRUD interface
  - Calendar vs Working Days calculation modes
  - Separate Working Days and Collection Days configuration
  - Cutoff time with penalty days
  - Date exclusions management (one-time and recurring)
  - Admin dashboard with 6 tabs (Settings, Working Days, Collection Days, Cutoff, Exclusions, Category Rules)
  - REST API endpoints for date availability

### Technical
- WordPress 5.8+ compatibility
- WooCommerce 5.0+ compatibility
- PHP 7.4+ required
- HPOS (High-Performance Order Storage) compatible
- Block Checkout integration
- Security: Nonce verification, input sanitization, capability checks
- Database: wp_wc_collection_exclusions table for exclusions
- Architecture: Singleton pattern with WordPress hooks/filters

### Phase 1 & 2 Complete
- ✅ Working Days & Cutoff Time calculation
- ✅ Category-Based Lead Time Rules
- ✅ Cart analysis (longest lead time wins)
- ✅ Instructions tab with plain language guide
- ✅ Comprehensive admin interface
- ✅ Block and classic checkout support

---

[1.1.0]: https://github.com/AmigoUK/wc-collection-date/compare/v1.0.0...v1.1.0
[1.0.0]: https://github.com/AmigoUK/wc-collection-date/releases/tag/v1.0.0
