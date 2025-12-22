<?php
/**
 * Database Diagnostic Script for WC Collection Date Plugin
 *
 * This script checks the current database structure and identifies issues
 * with the exclusion date range feature.
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    // Load WordPress if not loaded
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

?>
<!DOCTYPE html>
<html>
<head>
    <title>WC Collection Date Database Diagnostic</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .section { margin: 20px 0; padding: 15px; border: 1px solid #ddd; }
        .success { background-color: #d4edda; border-color: #c3e6cb; }
        .error { background-color: #f8d7da; border-color: #f5c6cb; }
        .warning { background-color: #fff3cd; border-color: #ffeaa7; }
        .info { background-color: #d1ecf1; border-color: #bee5eb; }
        pre { background: #f8f9fa; padding: 10px; overflow-x: auto; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; }
    </style>
</head>
<body>
    <h1>WC Collection Date Database Diagnostic</h1>

    <?php

    global $wpdb;

    // Get current database version
    $db_version = get_option( 'wc_collection_date_db_version', 'Not set' );
    $plugin_version = get_option( 'wc_collection_date_version', 'Not set' );

    echo "<div class='section info'>";
    echo "<h2>Version Information</h2>";
    echo "<p><strong>Database Version:</strong> " . esc_html( $db_version ) . "</p>";
    echo "<p><strong>Plugin Version:</strong> " . esc_html( $plugin_version ) . "</p>";
    echo "</div>";

    // Check table existence
    $exclusions_table = $wpdb->prefix . 'wc_collection_exclusions';
    $table_exists = $wpdb->get_var( "SHOW TABLES LIKE '$exclusions_table'" ) === $exclusions_table;

    echo "<div class='section " . ( $table_exists ? 'success' : 'error' ) . "'>";
    echo "<h2>Exclusions Table Check</h2>";
    echo "<p><strong>Table Name:</strong> " . esc_html( $exclusions_table ) . "</p>";
    echo "<p><strong>Status:</strong> " . ( $table_exists ? 'EXISTS' : 'MISSING' ) . "</p>";
    echo "</div>";

    if ( $table_exists ) {
        // Get table structure
        $columns = $wpdb->get_results( "SHOW COLUMNS FROM $exclusions_table" );

        echo "<div class='section info'>";
        echo "<h2>Current Table Structure</h2>";
        echo "<table>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";

        $required_columns = array(
            'id',
            'exclusion_type',
            'exclusion_date',
            'exclusion_start',
            'exclusion_end',
            'reason',
            'created_at',
            'updated_at'
        );

        $found_columns = array();

        foreach ( $columns as $column ) {
            echo "<tr>";
            echo "<td>" . esc_html( $column->Field ) . "</td>";
            echo "<td>" . esc_html( $column->Type ) . "</td>";
            echo "<td>" . esc_html( $column->Null ) . "</td>";
            echo "<td>" . esc_html( $column->Key ) . "</td>";
            echo "<td>" . esc_html( $column->Default ) . "</td>";
            echo "<td>" . esc_html( $column->Extra ) . "</td>";
            echo "</tr>";

            $found_columns[] = $column->Field;
        }
        echo "</table>";
        echo "</div>";

        // Check for missing columns
        $missing_columns = array_diff( $required_columns, $found_columns );

        if ( ! empty( $missing_columns ) ) {
            echo "<div class='section error'>";
            echo "<h2>Missing Columns</h2>";
            echo "<p>The following required columns are missing:</p>";
            echo "<ul>";
            foreach ( $missing_columns as $column ) {
                echo "<li><strong>" . esc_html( $column ) . "</strong></li>";
            }
            echo "</ul>";
            echo "</div>";
        } else {
            echo "<div class='section success'>";
            echo "<h2>Columns Check</h2>";
            echo "<p>All required columns are present.</p>";
            echo "</div>";
        }

        // Check indexes
        $indexes = $wpdb->get_results( "SHOW INDEX FROM $exclusions_table" );

        echo "<div class='section info'>";
        echo "<h2>Table Indexes</h2>";
        echo "<table>";
        echo "<tr><th>Table</th><th>Non_unique</th><th>Key_name</th><th>Seq_in_index</th><th>Column_name</th><th>Index_type</th></tr>";

        foreach ( $indexes as $index ) {
            echo "<tr>";
            echo "<td>" . esc_html( $index->Table ) . "</td>";
            echo "<td>" . esc_html( $index->Non_unique ) . "</td>";
            echo "<td>" . esc_html( $index->Key_name ) . "</td>";
            echo "<td>" . esc_html( $index->Seq_in_index ) . "</td>";
            echo "<td>" . esc_html( $index->Column_name ) . "</td>";
            echo "<td>" . esc_html( $index->Index_type ) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "</div>";

        // Test data insertion
        echo "<div class='section info'>";
        echo "<h2>Test Data Insertion</h2>";

        // Test single date exclusion
        $test_single = array(
            'exclusion_type' => 'single',
            'exclusion_date' => date( 'Y-m-d', strtotime( '+30 days' ) ),
            'reason' => 'Test single exclusion'
        );

        $wpdb->insert(
            $exclusions_table,
            $test_single,
            array( '%s', '%s', '%s' )
        );

        if ( $wpdb->insert_id ) {
            echo "<p class='success'>✓ Single date exclusion test: PASSED</p>";
            $wpdb->delete(
                $exclusions_table,
                array( 'id' => $wpdb->insert_id ),
                array( '%d' )
            );
        } else {
            echo "<p class='error'>✗ Single date exclusion test: FAILED</p>";
            echo "<p><strong>WordPress DB Error:</strong> " . esc_html( $wpdb->last_error ) . "</p>";
        }

        // Test date range exclusion (only if columns exist)
        if ( in_array( 'exclusion_type', $found_columns ) &&
             in_array( 'exclusion_start', $found_columns ) &&
             in_array( 'exclusion_end', $found_columns ) ) {

            $test_range = array(
                'exclusion_type'  => 'range',
                'exclusion_start' => date( 'Y-m-d', strtotime( '+60 days' ) ),
                'exclusion_end'   => date( 'Y-m-d', strtotime( '+62 days' ) ),
                'reason'          => 'Test range exclusion'
            );

            $wpdb->insert(
                $exclusions_table,
                $test_range,
                array( '%s', '%s', '%s', '%s' )
            );

            if ( $wpdb->insert_id ) {
                echo "<p class='success'>✓ Date range exclusion test: PASSED</p>";
                $wpdb->delete(
                    $exclusions_table,
                    array( 'id' => $wpdb->insert_id ),
                    array( '%d' )
                );
            } else {
                echo "<p class='error'>✗ Date range exclusion test: FAILED</p>";
                echo "<p><strong>WordPress DB Error:</strong> " . esc_html( $wpdb->last_error ) . "</p>";
            }
        } else {
            echo "<p class='warning'>⚠ Date range exclusion test: SKIPPED (missing columns)</p>";
        }

        echo "</div>";

        // Show current data
        $current_exclusions = $wpdb->get_results( "SELECT * FROM $exclusions_table LIMIT 10" );

        echo "<div class='section info'>";
        echo "<h2>Current Exclusions (First 10)</h2>";
        if ( $current_exclusions ) {
            echo "<table>";
            echo "<tr><th>ID</th><th>Type</th><th>Date</th><th>Start</th><th>End</th><th>Reason</th><th>Created</th></tr>";
            foreach ( $current_exclusions as $exclusion ) {
                echo "<tr>";
                echo "<td>" . esc_html( $exclusion->id ) . "</td>";
                echo "<td>" . esc_html( $exclusion->exclusion_type ?? 'N/A' ) . "</td>";
                echo "<td>" . esc_html( $exclusion->exclusion_date ?? 'N/A' ) . "</td>";
                echo "<td>" . esc_html( $exclusion->exclusion_start ?? 'N/A' ) . "</td>";
                echo "<td>" . esc_html( $exclusion->exclusion_end ?? 'N/A' ) . "</td>";
                echo "<td>" . esc_html( $exclusion->reason ) . "</td>";
                echo "<td>" . esc_html( $exclusion->created_at ) . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        } else {
            echo "<p>No exclusions found in the database.</p>";
        }
        echo "</div>";
    }

    // Migration suggestions
    echo "<div class='section warning'>";
    echo "<h2>Migration Status & Recommendations</h2>";

    if ( version_compare( $db_version, '1.4.0', '<' ) ) {
        echo "<p><strong>⚠ Migration Needed:</strong> Database version ($db_version) is behind plugin version 1.4.0</p>";
        echo "<p>The migration to add date range support has not been executed properly.</p>";
    } else {
        echo "<p><strong>✓ Migration Status:</strong> Database version indicates migration should have run.</p>";
    }

    echo "<h3>Required Actions:</h3>";
    echo "<ol>";
    echo "<li><strong>Backup your database</strong> before making any changes</li>";
    echo "<li><strong>Run the migration manually</strong> if needed using the code below</li>";
    echo "<li><strong>Test the date range exclusion feature</strong> after migration</li>";
    echo "</ol>";

    echo "<h3>Manual Migration SQL:</h3>";
    echo "<pre>";
    echo "-- First, check if columns exist and add them if needed
ALTER TABLE {$exclusions_table}
ADD COLUMN exclusion_type ENUM('single', 'range') NOT NULL DEFAULT 'single' AFTER exclusion_date,
ADD COLUMN exclusion_start date NULL AFTER exclusion_type,
ADD COLUMN exclusion_end date NULL AFTER exclusion_start,
DROP INDEX exclusion_date,
ADD INDEX idx_exclusion_date (exclusion_date),
ADD INDEX idx_date_range (exclusion_start, exclusion_end),
ADD INDEX idx_exclusion_type (exclusion_type);

-- Update the database version
UPDATE wp_options SET option_value = '1.4.0' WHERE option_name = 'wc_collection_date_db_version';";
    echo "</pre>";
    echo "</div>";

    ?>

</body>
</html>