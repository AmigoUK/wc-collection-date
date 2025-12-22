<?php
/**
 * Fix Database Columns for Date Range Exclusions
 *
 * Run this script once to add the missing columns for date range support.
 * Delete this file after running.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    // Load WordPress
    $wp_load_path = dirname( __FILE__ ) . '/../../../wp-load.php';
    if ( file_exists( $wp_load_path ) ) {
        require_once $wp_load_path;
    } else {
        die('WordPress not found');
    }
}

// Check if user has admin privileges
if ( ! current_user_can( 'manage_options' ) ) {
    die( 'Access denied. Admin privileges required.' );
}

global $wpdb;

echo "<h1>WC Collection Date - Fix Exclusion Columns</h1>";

$table_name = $wpdb->prefix . 'wc_collection_exclusions';

// Check if table exists
$table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$table_name'" ) === $table_name;

if ( ! $table_exists ) {
    echo "<div style='color: red; padding: 10px; border: 1px solid #ddd; margin: 10px 0;'>";
    echo "<h2>❌ Error: Exclusions table not found</h2>";
    echo "<p>The table {$table_name} does not exist. Please activate the plugin first.</p>";
    echo "</div>";
    exit;
}

echo "<div style='color: blue; padding: 10px; border: 1px solid #ddd; margin: 10px 0;'>";
echo "<h2>🔧 Checking table structure...</h2>";

// Get current columns
$columns = $wpdb->get_results( "SHOW COLUMNS FROM $table_name" );
$existing_columns = array();

foreach ( $columns as $column ) {
    $existing_columns[] = $column->Field;
}

// Check if exclusion_type column exists
if ( ! in_array( 'exclusion_type', $existing_columns ) ) {
    echo "<p>⚠️ <strong>exclusion_type</strong> column missing. Adding...</p>";

    $result = $wpdb->query( "
        ALTER TABLE {$table_name}
        ADD COLUMN exclusion_type ENUM('single', 'range') NOT NULL DEFAULT 'single' AFTER exclusion_date
    " );

    if ( $result !== false ) {
        echo "<p style='color: green;'>✅ <strong>exclusion_type</strong> column added successfully.</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to add <strong>exclusion_type</strong> column.</p>";
        echo "<p><strong>Error:</strong> " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✅ <strong>exclusion_type</strong> column already exists.</p>";
}

// Check if exclusion_start column exists
if ( ! in_array( 'exclusion_start', $existing_columns ) ) {
    echo "<p>⚠️ <strong>exclusion_start</strong> column missing. Adding...</p>";

    $result = $wpdb->query( "
        ALTER TABLE {$table_name}
        ADD COLUMN exclusion_start DATE NULL AFTER exclusion_type
    " );

    if ( $result !== false ) {
        echo "<p style='color: green;'>✅ <strong>exclusion_start</strong> column added successfully.</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to add <strong>exclusion_start</strong> column.</p>";
        echo "<p><strong>Error:</strong> " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✅ <strong>exclusion_start</strong> column already exists.</p>";
}

// Check if exclusion_end column exists
if ( ! in_array( 'exclusion_end', $existing_columns ) ) {
    echo "<p>⚠️ <strong>exclusion_end</strong> column missing. Adding...</p>";

    $result = $wpdb->query( "
        ALTER TABLE {$table_name}
        ADD COLUMN exclusion_end DATE NULL AFTER exclusion_start
    " );

    if ( $result !== false ) {
        echo "<p style='color: green;'>✅ <strong>exclusion_end</strong> column added successfully.</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to add <strong>exclusion_end</strong> column.</p>";
        echo "<p><strong>Error:</strong> " . $wpdb->last_error . "</p>";
    }
} else {
    echo "<p style='color: green;'>✅ <strong>exclusion_end</strong> column already exists.</p>";
}

echo "</div>";

// Add indexes for performance
echo "<div style='color: blue; padding: 10px; border: 1px solid #ddd; margin: 10px 0;'>";
echo "<h2>🔧 Adding indexes for performance...</h2>";

// Check and add index for exclusion_type
$index_exists = $wpdb->get_var( "
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = '" . DB_NAME . "'
    AND TABLE_NAME = '$table_name'
    AND INDEX_NAME = 'idx_exclusion_type'
" );

if ( ! $index_exists ) {
    echo "<p>⚠️ Index <strong>idx_exclusion_type</strong> missing. Adding...</p>";

    $result = $wpdb->query( "
        ALTER TABLE {$table_name}
        ADD INDEX idx_exclusion_type (exclusion_type)
    " );

    if ( $result !== false ) {
        echo "<p style='color: green;'>✅ Index <strong>idx_exclusion_type</strong> added successfully.</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to add index <strong>idx_exclusion_type</strong>.</p>";
    }
} else {
    echo "<p style='color: green;'>✅ Index <strong>idx_exclusion_type</strong> already exists.</p>";
}

// Check and add index for date range
$range_index_exists = $wpdb->get_var( "
    SELECT COUNT(*) FROM INFORMATION_SCHEMA.STATISTICS
    WHERE TABLE_SCHEMA = '" . DB_NAME . "'
    AND TABLE_NAME = '$table_name'
    AND INDEX_NAME = 'idx_date_range'
" );

if ( ! $range_index_exists ) {
    echo "<p>⚠️ Index <strong>idx_date_range</strong> missing. Adding...</p>";

    $result = $wpdb->query( "
        ALTER TABLE {$table_name}
        ADD INDEX idx_date_range (exclusion_start, exclusion_end)
    " );

    if ( $result !== false ) {
        echo "<p style='color: green;'>✅ Index <strong>idx_date_range</strong> added successfully.</p>";
    } else {
        echo "<p style='color: red;'>❌ Failed to add index <strong>idx_date_range</strong>.</p>";
    }
} else {
    echo "<p style='color: green;'>✅ Index <strong>idx_date_range</strong> already exists.</p>";
}

echo "</div>";

// Update database version
echo "<div style='color: blue; padding: 10px; border: 1px solid #ddd; margin: 10px 0;'>";
echo "<h2>🔧 Updating database version...</h2>";

update_option( 'wc_collection_date_db_version', '1.4.0' );
echo "<p style='color: green;'>✅ Database version updated to 1.4.0</p>";

echo "</div>";

// Test the functionality
echo "<div style='color: blue; padding: 10px; border: 1px solid #ddd; margin: 10px 0;'>";
echo "<h2>🧪 Testing date range exclusion...</h2>";

// Test data insertion
$test_date = date( 'Y-m-d', strtotime( '+30 days' ) );
$test_end_date = date( 'Y-m-d', strtotime( '+32 days' ) );

$test_data = array(
    'exclusion_type'  => 'range',
    'exclusion_start' => $test_date,
    'exclusion_end'   => $test_end_date,
    'reason'          => 'Test date range exclusion - should be deleted'
);

$result = $wpdb->insert(
    $table_name,
    $test_data,
    array( '%s', '%s', '%s', '%s' )
);

if ( $result !== false && $wpdb->insert_id ) {
    echo "<p style='color: green;'>✅ Date range exclusion test: PASSED</p>";
    echo "<p>Test record inserted with ID: " . $wpdb->insert_id . "</p>";

    // Clean up test data
    $wpdb->delete(
        $table_name,
        array( 'id' => $wpdb->insert_id ),
        array( '%d' )
    );
    echo "<p style='color: green;'>✅ Test data cleaned up successfully</p>";
} else {
    echo "<p style='color: red;'>❌ Date range exclusion test: FAILED</p>";
    echo "<p><strong>WordPress Error:</strong> " . $wpdb->last_error . "</p>";
}

echo "</div>";

echo "<div style='color: green; padding: 15px; border: 2px solid #28a745; margin: 10px 0; background-color: #d4edda;'>";
echo "<h2>🎉 Fix Complete!</h2>";
echo "<p>The database has been updated with the required columns for date range exclusions.</p>";
echo "<p><strong>Next steps:</strong></p>";
echo "<ol>";
echo "<li>Delete this file (<strong>fix-exclusion-columns.php</strong>) for security</li>";
echo "<li>Test the date range exclusion feature in the admin panel</li>";
echo "<li>Single date exclusions should continue to work as before</li>";
echo "</ol>";
echo "</div>";

?>