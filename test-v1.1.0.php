<?php
/**
 * Test Script for v1.1.0 Improvements
 *
 * Run: docker exec wordpress_app php /var/www/html/wp-content/plugins/wc-collection-date/test-v1.1.0.php
 */

// Load WordPress
require_once '/var/www/html/wp-load.php';

echo "\n=== WC Collection Date v1.1.0 Test Suite ===\n\n";

// Test 1: Plugin Classes Load
echo "Test 1: Plugin Classes Loading\n";
echo "-------------------------------\n";
try {
	$classes = [
		'WC_Collection_Date',
		'WC_Collection_Date_Debug',
		'WC_Collection_Date_Calculator',
	];

	foreach ( $classes as $class ) {
		if ( class_exists( $class ) ) {
			echo "✓ {$class} loaded\n";
		} else {
			echo "✗ {$class} NOT FOUND\n";
		}
	}
} catch ( Exception $e ) {
	echo "✗ Error: {$e->getMessage()}\n";
}

// Test 2: Debug Mode
echo "\n\nTest 2: Debug Mode Functionality\n";
echo "-----------------------------------\n";
try {
	// Test debug mode detection
	$debug_enabled = WC_Collection_Date_Debug::is_enabled();
	echo "Debug mode enabled: " . ( $debug_enabled ? 'YES' : 'NO' ) . "\n";

	// Test logging (should only log if WC_COLLECTION_DATE_DEBUG is true)
	WC_Collection_Date_Debug::log( 'Test log entry from v1.1.0 test suite', 'test' );
	echo "✓ Debug logging executed (check logs if debug enabled)\n";

	// Test specialized logging
	WC_Collection_Date_Debug::log_cache( 'test', 'test_cache_key' );
	echo "✓ Cache logging executed\n";

} catch ( Exception $e ) {
	echo "✗ Error: {$e->getMessage()}\n";
}

// Test 3: Caching Layer
echo "\n\nTest 3: Caching Layer\n";
echo "----------------------\n";
try {
	// Clear cache first
	$cleared = WC_Collection_Date_Calculator::clear_cache();
	echo "Cache cleared: " . ( $cleared ? 'YES' : 'NO' ) . "\n";

	// Create calculator instance
	$calculator = new WC_Collection_Date_Calculator();

	// Test cache miss (first call)
	$start = microtime( true );
	$dates1 = $calculator->get_available_dates( 30 );
	$time1 = ( microtime( true ) - $start ) * 1000;
	echo "First call (cache miss): {$time1}ms - " . count( $dates1 ) . " dates\n";

	// Test cache hit (second call)
	$start = microtime( true );
	$dates2 = $calculator->get_available_dates( 30 );
	$time2 = ( microtime( true ) - $start ) * 1000;
	echo "Second call (cache hit): {$time2}ms - " . count( $dates2 ) . " dates\n";

	$speedup = round( $time1 / $time2, 2 );
	echo "Cache speedup: {$speedup}x faster\n";

	if ( $dates1 === $dates2 ) {
		echo "✓ Cached data matches original\n";
	} else {
		echo "✗ Cache data mismatch\n";
	}

} catch ( Exception $e ) {
	echo "✗ Error: {$e->getMessage()}\n";
}

// Test 4: Settings Import/Export Structure
echo "\n\nTest 4: Settings Import/Export Methods\n";
echo "----------------------------------------\n";
try {
	if ( class_exists( 'WC_Collection_Date_Settings' ) ) {
		$settings = new WC_Collection_Date_Settings();

		if ( method_exists( $settings, 'export_settings' ) ) {
			echo "✓ export_settings() method exists\n";
		} else {
			echo "✗ export_settings() method NOT FOUND\n";
		}

		if ( method_exists( $settings, 'import_settings' ) ) {
			echo "✓ import_settings() method exists\n";
		} else {
			echo "✗ import_settings() method NOT FOUND\n";
		}

		if ( method_exists( $settings, 'render_import_export_tab' ) ) {
			echo "✓ render_import_export_tab() method exists\n";
		} else {
			echo "✗ render_import_export_tab() method NOT FOUND\n";
		}

	} else {
		echo "✗ WC_Collection_Date_Settings class not loaded\n";
	}
} catch ( Exception $e ) {
	echo "✗ Error: {$e->getMessage()}\n";
}

// Test 5: Built Assets
echo "\n\nTest 5: Production Assets\n";
echo "---------------------------\n";
$assets = [
	'/var/www/html/wp-content/plugins/wc-collection-date/assets/dist/js/admin.min.js',
	'/var/www/html/wp-content/plugins/wc-collection-date/assets/dist/js/checkout.min.js',
	'/var/www/html/wp-content/plugins/wc-collection-date/assets/dist/css/admin-styles.min.css',
	'/var/www/html/wp-content/plugins/wc-collection-date/assets/dist/css/checkout-styles.min.css',
];

foreach ( $assets as $asset ) {
	$basename = basename( $asset );
	if ( file_exists( $asset ) ) {
		$size = round( filesize( $asset ) / 1024, 2 );
		echo "✓ {$basename} ({$size} KB)\n";
	} else {
		echo "✗ {$basename} NOT FOUND\n";
	}
}

// Summary
echo "\n\n=== Test Summary ===\n";
echo "All core v1.1.0 improvements tested.\n";
echo "Check output above for any ✗ failures.\n\n";
