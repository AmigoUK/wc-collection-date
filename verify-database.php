<?php
/**
 * Quick Database Verification Script
 *
 * Run this script to verify the database structure is correct
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __FILE__ ) . '/' );
	require_once ABSPATH . 'wp-config.php';
} else {
	require_once ABSPATH . 'wp-config.php';
}

// Check if user is admin.
if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
	wp_die( 'Access denied. Administrator access required.' );
}

global $wpdb;
$table_name = $wpdb->prefix . 'wc_collection_exclusions';

echo "<h2>Database Structure Verification</h2>";

// Check if table exists.
$table_exists = $wpdb->get_var( $wpdb->prepare( "SHOW TABLES LIKE %s", $table_name ) );

if ( ! $table_exists ) {
	echo "<p style='color: red;'>❌ Table {$table_name} does not exist.</p>";
} else {
	echo "<p style='color: green;'>✅ Table {$table_name} exists.</p>";

	// Show table structure.
	echo "<h3>Current Table Structure:</h3>";
	echo "<table border='1' cellpadding='5' cellspacing='0'>";
	echo "<tr><th>Column</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th></tr>";

	$columns = $wpdb->get_results( "DESCRIBE {$table_name}" );
	foreach ( $columns as $col ) {
		echo "<tr>";
		echo "<td>{$col->Field}</td>";
		echo "<td>{$col->Type}</td>";
		echo "<td>{$col->Null}</td>";
		echo "<td>{$col->Key}</td>";
		echo "<td>{$col->Default}</td>";
		echo "</tr>";
	}
	echo "</table>";

	// Check for required columns.
	$required_columns = array( 'exclusion_type', 'exclusion_start', 'exclusion_end' );
	$missing = array();

	foreach ( $required_columns as $col ) {
		$exists = $wpdb->get_var( "SHOW COLUMNS FROM {$table_name} LIKE '{$col}'" );
		if ( ! $exists ) {
			$missing[] = $col;
		}
	}

	if ( empty( $missing ) ) {
		echo "<p style='color: green; font-weight: bold;'>✅ All required columns exist!</p>";
		echo "<p style='color: green;'>Date range exclusions should work now.</p>";
	} else {
		echo "<p style='color: red; font-weight: bold;'>❌ Missing columns: " . implode( ', ', $missing ) . "</p>";
		echo "<p style='color: orange;'>Run the fix script: <a href='fix-exclusion-columns.php'>fix-exclusion-columns.php</a></p>";
	}
}

// Show database version.
$db_version = get_option( 'wc_collection_date_db_version', 'Not set' );
echo "<h3>Database Version:</h3>";
echo "<p><strong>Database Version:</strong> {$db_version}</p>";
echo "<p><strong>Plugin Version:</strong> " . WC_COLLECTION_DATE_VERSION . "</p>";

if ( version_compare( $db_version, '1.4.0', '<' ) ) {
	echo "<p style='color: orange;'>⚠️ Database version is behind. Migration needed.</p>";
} else {
	echo "<p style='color: green;'>✅ Database version is current.</p>";
}

echo "<hr>";
echo "<p><strong>Instructions:</strong></p>";
echo "<ol>";
echo "<li>If columns are missing, run the fix script above</li>";
echo "<li>After fixing, delete both verification scripts for security</li>";
echo "<li>Test adding a date range exclusion in the admin panel</li>";
echo "</ol>";

?>