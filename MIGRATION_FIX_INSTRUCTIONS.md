# WC Collection Date - Migration Fix Instructions

## Problem Summary
The "Failed to add exclusion to database" error occurs because:
1. **Version Mismatch**: Plugin header says v1.3.0 but code expects v1.4.0 features
2. **Missing Database Columns**: Date range columns (`exclusion_type`, `exclusion_start`, `exclusion_end`) were never added
3. **Incomplete Migration**: The migration system didn't run properly due to version inconsistency

## Immediate Fix

### Step 1: Run the Emergency Fix Script
1. Access: `https://your-site.com/wp-content/plugins/wc-collection-date/fix-exclusion-columns.php`
2. Login as WordPress administrator
3. Follow the on-screen instructions
4. **IMPORTANT**: Delete the fix script after completion for security

### Step 2: Update Plugin Version (Permanent Fix)

#### Method A: Update Main Plugin File
Edit `wc-collection-date.php` line 6 and 29:
```php
// Change from:
Version: 1.3.0
define( 'WC_COLLECTION_DATE_VERSION', '1.3.0' );

// To:
Version: 1.4.0
define( 'WC_COLLECTION_DATE_VERSION', '1.4.0' );
```

#### Method B: Update Database Version Option
Run this in WordPress admin or via WP-CLI:
```sql
UPDATE wp_options SET option_value = '1.4.0' WHERE option_name = 'wc_collection_date_db_version';
```

## Root Cause Details

### The Chain of Failure:
1. **Plugin Header**: Declares version 1.3.0
2. **Activator**: Creates table with basic columns only (1.3.0 structure)
3. **Migration System**: Designed for 1.4.0 but never triggered due to version mismatch
4. **Admin Interface**: Shows date range options (1.4.0 feature)
5. **ExclusionManager**: Tries to insert 1.4.0 data into 1.3.0 table structure
6. **Database Insert**: Fails because `exclusion_type`, `exclusion_start`, `exclusion_end` columns don't exist
7. **User Sees**: "Failed to add exclusion to database" error

### What Should Have Happened:
1. Plugin header should say version 1.4.0
2. Migration system should detect version mismatch
3. Migration should add missing columns automatically
4. Date range exclusions should work seamlessly

## Verification Steps

After applying the fix:

1. **Check Database Structure**:
   ```sql
   DESCRIBE wp_wc_collection_exclusions;
   ```
   You should see: `exclusion_type`, `exclusion_start`, `exclusion_end` columns

2. **Test Single Date Exclusion**:
   - Go to WooCommerce → Collection Dates
   - Add a single date exclusion
   - Should work as before

3. **Test Date Range Exclusion**:
   - Add a date range exclusion (e.g., Jan 1-3, 2025)
   - Should work without errors
   - Check that individual dates within the range are also excluded

4. **Check Version Consistency**:
   ```php
   echo get_option('wc_collection_date_db_version'); // Should be "1.4.0"
   echo WC_COLLECTION_DATE_VERSION; // Should be "1.4.0"
   ```

## Additional Files Created for Diagnosis

1. **`diagnostic-database-check.php`**: Comprehensive database structure analysis
2. **`fix-exclusion-columns.php`**: Emergency fix script (DELETE after use)

## Prevention

To prevent this in future updates:

1. **Always update version numbers** in both plugin header and constants together
2. **Test migrations** on staging before production
3. **Verify database structure** after major updates
4. **Keep version numbers consistent** across all files

## Support

If issues persist after applying these fixes:
1. Check WordPress debug logs for additional errors
2. Verify database user has ALTER TABLE privileges
3. Ensure WordPress database connection is working properly
4. Test with different date ranges to isolate data-specific issues