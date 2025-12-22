<?php
/**
 * Database Investigation Script for WC Collection Date
 *
 * This script investigates why orders are not being found
 * and checks for HPOS, table prefixes, and database connections
 */

// Load WordPress
$wp_load_path = dirname( __FILE__ ) . '/../../../wp-load.php';
if ( file_exists( $wp_load_path ) ) {
    require_once $wp_load_path;
} else {
    die('WordPress not found at expected path');
}

// Check admin privileges
if ( ! current_user_can( 'manage_options' ) ) {
    die( 'Access denied. Admin privileges required.' );
}

?>
<!DOCTYPE html>
<html>
<head>
    <title>WC Collection Date - Database Investigation</title>
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
    <h1>WC Collection Date - Database Investigation</h1>

    <?php
    global $wpdb;

    // 1. Database Connection Check
    echo "<div class='section info'>";
    echo "<h2>1. Database Connection Information</h2>";
    echo "<p><strong>DB Prefix:</strong> " . esc_html( $wpdb->prefix ) . "</p>";
    echo "<p><strong>DB Name:</strong> " . esc_html( DB_NAME ) . "</p>";
    echo "<p><strong>DB User:</strong> " . esc_html( DB_USER ) . "</p>";
    echo "<p><strong>DB Host:</strong> " . esc_html( DB_HOST ) . "</p>";
    echo "<p><strong>Table Prefix:</strong> " . esc_html( $wpdb->base_prefix ) . "</p>";
    echo "</div>";

    // 2. WooCommerce HPOS Check
    echo "<div class='section info'>";
    echo "<h2>2. WooCommerce HPOS Status</h2>";

    // Check if HPOS is enabled
    $hpos_enabled = get_option( 'woocommerce_custom_orders_table_enabled' );
    $hpos_enabled_legacy = get_option( 'woocommerce_hpos_enabled' );
    $hpos_compatible = get_option( 'woocommerce_custom_orders_table_data_sync_enabled' );

    echo "<p><strong>HPOS Enabled (new setting):</strong> " . ( $hpos_enabled ? 'YES' : 'NO' ) . "</p>";
    echo "<p><strong>HPOS Enabled (legacy setting):</strong> " . ( $hpos_enabled_legacy ? 'YES' : 'NO' ) . "</p>";
    echo "<p><strong>HPOS Compatibility Mode:</strong> " . ( $hpos_compatible ? 'YES' : 'NO' ) . "</p>";

    // Check WooCommerce version
    if ( function_exists( 'WC' ) ) {
        echo "<p><strong>WooCommerce Version:</strong> " . esc_html( WC()->version ) . "</p>";
    } else {
        echo "<p class='error'>WooCommerce not loaded</p>";
    }

    echo "</div>";

    // 3. Table Existence Check
    echo "<div class='section info'>";
    echo "<h2>3. Table Existence Check</h2>";

    $tables_to_check = array(
        'wp_posts' => 'Posts table (legacy orders)',
        'wp_postmeta' => 'Post meta table (legacy order meta)',
        'wc_orders' => 'HPOS orders table',
        'wc_order_meta' => 'HPOS order meta table',
        'wc_order_product_lookup' => 'HPOS product lookup table',
        'wc_customer_lookup' => 'HPOS customer lookup table',
        'wc_coupon_usage' => 'HPOS coupon usage table'
    );

    echo "<table>";
    echo "<tr><th>Table Name</th><th>Purpose</th><th>Exists</th><th>Record Count</th></tr>";

    foreach ( $tables_to_check as $table => $description ) {
        $full_table_name = $wpdb->prefix . $table;
        $exists = $wpdb->get_var( "SHOW TABLES LIKE '$full_table_name'" ) === $full_table_name;
        $count = 0;

        if ( $exists ) {
            $count = $wpdb->get_var( "SELECT COUNT(*) FROM $full_table_name" );
        }

        echo "<tr>";
        echo "<td>" . esc_html( $full_table_name ) . "</td>";
        echo "<td>" . esc_html( $description ) . "</td>";
        echo "<td class='" . ( $exists ? 'success' : 'error' ) . "'>" . ( $exists ? 'YES' : 'NO' ) . "</td>";
        echo "<td>" . number_format( $count ) . "</td>";
        echo "</tr>";
    }

    echo "</table>";
    echo "</div>";

    // 4. Order Data Investigation
    echo "<div class='section info'>";
    echo "<h2>4. Order Data Investigation</h2>";

    // Check legacy orders in wp_posts
    $legacy_orders = $wpdb->get_var( "
        SELECT COUNT(*)
        FROM {$wpdb->posts}
        WHERE post_type = 'shop_order'
    " );

    echo "<p><strong>Legacy Orders (wp_posts):</strong> " . number_format( $legacy_orders ) . "</p>";

    // Check HPOS orders in wc_orders
    $hpos_orders = 0;
    $wc_orders_table = $wpdb->prefix . 'wc_orders';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$wc_orders_table'" ) === $wc_orders_table ) {
        $hpos_orders = $wpdb->get_var( "SELECT COUNT(*) FROM $wc_orders_table" );
        echo "<p><strong>HPOS Orders (wc_orders):</strong> " . number_format( $hpos_orders ) . "</p>";
    } else {
        echo "<p class='warning'>HPOS orders table does not exist</p>";
    }

    // Use WooCommerce API to get orders
    if ( function_exists( 'wc_get_orders' ) ) {
        $wc_orders = wc_get_orders( array( 'limit' => 1 ) );
        $wc_order_count = count( $wc_orders );
        echo "<p><strong>WooCommerce API Orders:</strong> " . ( $wc_order_count > 0 ? 'ORDERS FOUND' : 'NO ORDERS' ) . "</p>";
    }

    echo "</div>";

    // 5. Collection Date Meta Investigation
    echo "<div class='section info'>";
    echo "<h2>5. Collection Date Meta Investigation</h2>";

    // Check collection date meta in legacy system
    $legacy_collection_meta = $wpdb->get_var( "
        SELECT COUNT(*)
        FROM {$wpdb->postmeta} pm
        INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
        WHERE pm.meta_key = '_collection_date'
        AND p.post_type = 'shop_order'
    " );

    echo "<p><strong>Legacy Collection Date Meta:</strong> " . number_format( $legacy_collection_meta ) . "</p>";

    // Check collection date meta in HPOS system
    $hpos_collection_meta = 0;
    $wc_order_meta_table = $wpdb->prefix . 'wc_order_meta';
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$wc_order_meta_table'" ) === $wc_order_meta_table ) {
        $hpos_collection_meta = $wpdb->get_var( "
            SELECT COUNT(*)
            FROM $wc_order_meta_table
            WHERE meta_key = '_collection_date'
        " );
        echo "<p><strong>HPOS Collection Date Meta:</strong> " . number_format( $hpos_collection_meta ) . "</p>";
    } else {
        echo "<p class='warning'>HPOS order meta table does not exist</p>";
    }

    echo "</div>";

    // 6. Test Queries Used by Plugin
    echo "<div class='section info'>";
    echo "<h2>6. Plugin Query Tests</h2>";

    // Test the schedule query from debug script
    $schedule_query = $wpdb->prepare(
        "SELECT pm.meta_value as collection_date, COUNT(*) as order_count
        FROM {$wpdb->prefix}postmeta pm
        INNER JOIN {$wpdb->prefix}posts p ON p.ID = pm.post_id
        WHERE pm.meta_key = '_collection_date'
        AND p.post_type = 'shop_order'
        AND p.post_status IN ('wc-processing', 'wc-on-hold', 'wc-pending')
        AND pm.meta_value >= %s
        GROUP BY pm.meta_value
        ORDER BY pm.meta_value ASC
        LIMIT 30",
        gmdate( 'Y-m-d' )
    );

    $schedule_results = $wpdb->get_results( $schedule_query );

    echo "<h3>Schedule Query Results (Legacy)</h3>";
    echo "<p><strong>Query:</strong> " . esc_html( $schedule_query ) . "</p>";
    echo "<p><strong>Results:</strong> " . count( $schedule_results ) . " rows found</p>";

    if ( ! empty( $schedule_results ) ) {
        echo "<table><tr><th>Collection Date</th><th>Order Count</th></tr>";
        foreach ( $schedule_results as $row ) {
            echo "<tr><td>" . esc_html( $row->collection_date ) . "</td><td>" . $row->order_count . "</td></tr>";
        }
        echo "</table>";
    }

    // Test HPOS version of the same query
    if ( $wpdb->get_var( "SHOW TABLES LIKE '$wc_orders_table'" ) === $wc_orders_table ) {
        $hpos_schedule_query = $wpdb->prepare(
            "SELECT om.meta_value as collection_date, COUNT(*) as order_count
            FROM $wc_order_meta_table om
            INNER JOIN $wc_orders_table o ON o.id = om.order_id
            WHERE om.meta_key = '_collection_date'
            AND o.status IN ('wc-processing', 'wc-on-hold', 'wc-pending')
            AND om.meta_value >= %s
            GROUP BY om.meta_value
            ORDER BY om.meta_value ASC
            LIMIT 30",
            gmdate( 'Y-m-d' )
        );

        $hpos_schedule_results = $wpdb->get_results( $hpos_schedule_query );

        echo "<h3>Schedule Query Results (HPOS)</h3>";
        echo "<p><strong>Query:</strong> " . esc_html( $hpos_schedule_query ) . "</p>";
        echo "<p><strong>Results:</strong> " . count( $hpos_schedule_results ) . " rows found</p>";

        if ( ! empty( $hpos_schedule_results ) ) {
            echo "<table><tr><th>Collection Date</th><th>Order Count</th></tr>";
            foreach ( $hpos_schedule_results as $row ) {
                echo "<tr><td>" . esc_html( $row->collection_date ) . "</td><td>" . $row->order_count . "</td></tr>";
            }
            echo "</table>";
        }
    }

    echo "</div>";

    // 7. Plugin Configuration Check
    echo "<div class='section info'>";
    echo "<h2>7. Plugin Configuration Check</h2>";

    $plugin_settings = array(
        'wc_collection_date_working_days' => get_option( 'wc_collection_date_working_days', 'Not set' ),
        'wc_collection_date_lead_time' => get_option( 'wc_collection_date_lead_time', 'Not set' ),
        'wc_collection_date_max_booking_days' => get_option( 'wc_collection_date_max_booking_days', 'Not set' ),
        'wc_collection_date_version' => get_option( 'wc_collection_date_version', 'Not set' ),
        'wc_collection_date_db_version' => get_option( 'wc_collection_date_db_version', 'Not set' )
    );

    echo "<table>";
    echo "<tr><th>Setting</th><th>Value</th></tr>";
    foreach ( $plugin_settings as $key => $value ) {
        echo "<tr><td>" . esc_html( $key ) . "</td><td>" . esc_html( print_r( $value, true ) ) . "</td></tr>";
    }
    echo "</table>";
    echo "</div>";

    // 8. Database Errors
    echo "<div class='section info'>";
    echo "<h2>8. Database Errors</h2>";
    if ( $wpdb->last_error ) {
        echo "<p class='error'><strong>Last Database Error:</strong> " . esc_html( $wpdb->last_error ) . "</p>";
    } else {
        echo "<p class='success'>No database errors detected</p>";
    }
    echo "</div>";

    // 9. Recommendations
    echo "<div class='section warning'>";
    echo "<h2>9. Investigation Summary & Recommendations</h2>";

    $total_orders = $legacy_orders + $hpos_orders;
    $has_collection_dates = $legacy_collection_meta + $hpos_collection_meta;

    if ( $total_orders == 0 ) {
        echo "<p class='error'><strong>CRITICAL ISSUE:</strong> No orders found in either legacy or HPOS tables</p>";
        echo "<p>This suggests either:</p>";
        echo "<ul>";
        echo "<li>No orders have been placed in this WooCommerce installation</li>";
        echo "<li>Database connection issues</li>";
        echo "<li>Wrong database/tables being queried</li>";
        echo "</ul>";
    } elseif ( $hpos_orders > 0 && $legacy_orders == 0 ) {
        echo "<p class='warning'><strong>HPOS DETECTED:</strong> Orders are stored in HPOS tables but plugin queries legacy tables</p>";
        echo "<p><strong>Solution:</strong> Plugin queries need to be updated to support HPOS</p>";
    } elseif ( $hpos_orders > 0 && $legacy_orders > 0 ) {
        echo "<p class='warning'><strong>MIXED MODE:</strong> Orders found in both HPOS and legacy tables (compatibility mode)</p>";
        echo "<p>Plugin should check both systems or use WooCommerce API functions</p>";
    } elseif ( $hpos_orders == 0 && $legacy_orders > 0 ) {
        echo "<p class='success'><strong>LEGACY MODE:</strong> Orders are stored in legacy wp_posts table</p>";
        echo "<p>Plugin queries should work but may need to check why no collection dates are found</p>";
    }

    if ( $total_orders > 0 && $has_collection_dates == 0 ) {
        echo "<p class='warning'><strong>COLLECTION DATES:</strong> Orders exist but no collection date meta found</p>";
        echo "<p>This could mean:</p>";
        echo "<ul>";
        echo "<li>Collection date plugin is not properly tracking orders</li>";
        echo "<li>Meta key is different than '_collection_date'</li>";
        echo "<li>Orders were placed before plugin was active</li>";
        echo "</ul>";
    }

    echo "</div>";

    ?>

</body>
</html>