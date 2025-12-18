<?php
/**
 * Unit Tests for WC_Collection_Date_Analytics Class
 *
 * @package WC_Collection_Date\Tests\Unit
 */

class WC_Collection_Date_Test_Analytics extends WC_Collection_Date_Test_Base {

	/**
	 * Analytics instance.
	 *
	 * @var WC_Collection_Date_Analytics
	 */
	protected $analytics;

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->analytics = WC_Collection_Date_Analytics::instance();
	}

	/**
	 * Test analytics instantiation.
	 */
	public function test_analytics_instantiation() {
		$this->assertInstanceOf( 'WC_Collection_Date_Analytics', $this->analytics );
	}

	/**
	 * Test singleton pattern.
	 */
	public function test_singleton_pattern() {
		$instance1 = WC_Collection_Date_Analytics::instance();
		$instance2 = WC_Collection_Date_Analytics::instance();

		$this->assertSame( $instance1, $instance2 );
	}

	/**
	 * Test hooks are registered.
	 */
	public function test_hooks_registered() {
		$hooks = array(
			'woocommerce_checkout_update_order_meta',
			'wp_ajax_wc_collection_date_get_analytics',
			'wp_ajax_wc_collection_date_export_analytics',
		);

		foreach ( $hooks as $hook ) {
			$this->assertTrue( has_filter( $hook ) !== false, "Hook {$hook} should be registered" );
		}

		// Check if cron job is scheduled.
		$timestamp = wp_next_scheduled( 'wc_collection_date_daily_analytics' );
		$this->assertNotFalse( $timestamp, 'Daily analytics cron should be scheduled' );
	}

	/**
	 * Test track_collection_date_selection.
	 */
	public function test_track_collection_date_selection() {
		$order_id = $this->create_test_order(
			array(
				'total' => '100.50',
			)
		);

		$_POST = array(
			'collection_date' => '2024-12-25',
		);

		// Mock current_time for consistent testing.
		if ( ! function_exists( 'date_i18n' ) ) {
			function date_i18n( $format, $timestamp = null ) {
				if ( null === $timestamp ) {
					$timestamp = time();
				}
				return date( $format, $timestamp );
			}
		}

		$this->analytics->track_collection_date_selection( $order_id, $_POST );

		// Verify analytics data was created.
		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_collection_date_analytics';

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE collection_date = %s",
				'2024-12-25'
			)
		);

		$this->assertNotNull( $result );
		$this->assertEquals( 1, $result->selection_count );
		$this->assertEquals( 1, $result->total_orders );
		$this->assertEquals( '100.50', $result->total_value );
		$this->assertEquals( 1, $result->avg_lead_time ); // Approximately 1 day from now to Dec 25
	}

	/**
	 * Test track_collection_date_selection with empty date.
	 */
	public function test_track_collection_date_selection_empty_date() {
		$order_id = $this->create_test_order();
		$_POST = array(); // Empty collection_date

		$initial_count = $this->get_analytics_record_count();

		$this->analytics->track_collection_date_selection( $order_id, $_POST );

		$final_count = $this->get_analytics_record_count();
		$this->assertEquals( $initial_count, $final_count ); // No new record should be created
	}

	/**
	 * Test track_collection_date_selection with invalid order.
	 */
	public function test_track_collection_date_selection_invalid_order() {
		$_POST = array(
			'collection_date' => '2024-12-25',
		);

		$initial_count = $this->get_analytics_record_count();

		$this->analytics->track_collection_date_selection( 99999, $_POST );

		$final_count = $this->get_analytics_record_count();
		$this->assertEquals( $initial_count, $final_count ); // No new record should be created
	}

	/**
	 * Test track_collection_date_selection updates existing record.
	 */
	public function test_track_collection_date_selection_updates_existing() {
		$collection_date = '2024-12-25';

		// Create initial analytics record.
		$analytics_id = $this->factory->analytics->create( array(
			'collection_date' => $collection_date,
			'selection_count' => 2,
			'total_orders' => 2,
			'total_value' => '50.00',
			'avg_lead_time' => '2.0',
		) );

		$order_id = $this->create_test_order( array( 'total' => '25.00' ) );
		$_POST = array( 'collection_date' => $collection_date );

		$this->analytics->track_collection_date_selection( $order_id, $_POST );

		// Verify record was updated.
		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_collection_date_analytics';

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE collection_date = %s",
				$collection_date
			)
		);

		$this->assertEquals( 3, $result->selection_count ); // 2 + 1
		$this->assertEquals( 3, $result->total_orders ); // 2 + 1
		$this->assertEquals( '75.00', $result->total_value ); // 50.00 + 25.00
	}

	/**
	 * Test calculate_lead_time_days.
	 */
	public function test_calculate_lead_time_days() {
		// Use reflection to test private method.
		$reflection = new ReflectionClass( $this->analytics );
		$method = $reflection->getMethod( 'calculate_lead_time_days' );
		$method->setAccessible( true );

		$order_date = '2024-12-20 10:00:00';
		$collection_date = '2024-12-25';

		$lead_time = $method->invoke( $this->analytics, $collection_date, $order_date );
		$this->assertEquals( 5, $lead_time );

		// Test same day collection (should be 0).
		$same_day_collection = '2024-12-20';
		$lead_time = $method->invoke( $this->analytics, $same_day_collection, $order_date );
		$this->assertEquals( 0, $lead_time );

		// Test past date (should be 0).
		$past_collection = '2024-12-15';
		$lead_time = $method->invoke( $this->analytics, $past_collection, $order_date );
		$this->assertEquals( 0, $lead_time );
	}

	/**
	 * Test get_analytics_data AJAX handler.
	 */
	public function test_get_analytics_data() {
		// Create test analytics data.
		$this->factory->analytics->create_many( 5, array(
			'selection_count' => 10,
			'total_orders' => 5,
			'total_value' => '100.00',
			'avg_lead_time' => '2.5',
		) );

		// Mock WordPress AJAX functions.
		$_POST['nonce'] = wp_create_nonce( 'wc_collection_date_analytics_nonce' );
		$_POST['period'] = '30days';

		// Mock current user capabilities.
		$current_user = wp_get_current_user();
		$current_user->add_cap( 'manage_woocommerce' );

		ob_start();
		$this->analytics->get_analytics_data();
		$output = ob_get_clean();

		$response = json_decode( $output, true );

		$this->assertTrue( $response['success'] );
		$this->assertArrayHasKey( 'summary', $response['data'] );
		$this->assertArrayHasKey( 'popular_dates', $response['data'] );
		$this->assertArrayHasKey( 'hourly_distribution', $response['data'] );
		$this->assertArrayHasKey( 'day_of_week_distribution', $response['data'] );
		$this->assertArrayHasKey( 'lead_time_distribution', $response['data'] );
		$this->assertArrayHasKey( 'monthly_trends', $response['data'] );
	}

	/**
	 * Test get_analytics_data without nonce.
	 */
	public function test_get_analytics_data_no_nonce() {
		$_POST['period'] = '30days';
		// Missing nonce.

		ob_start();
		$this->analytics->get_analytics_data();
		$output = ob_get_clean();

		$this->assertEmpty( $output ); // Should die due to missing nonce
	}

	/**
	 * Test get_analytics_data without permissions.
	 */
	public function test_get_analytics_data_no_permissions() {
		$_POST['nonce'] = wp_create_nonce( 'wc_collection_date_analytics_nonce' );
		$_POST['period'] = '30days';

		// Remove user capabilities.
		$current_user = wp_get_current_user();
		$current_user->remove_all_caps();

		ob_start();
		$this->analytics->get_analytics_data();
		$output = ob_get_clean();

		$this->assertEmpty( $output ); // Should die due to insufficient permissions
	}

	/**
	 * Test get_summary_stats.
	 */
	public function test_get_summary_stats() {
		// Create test analytics data.
		$analytics_ids = $this->factory->analytics->create_many( 3, array(
			'selection_count' => 10,
			'total_orders' => 5,
			'total_value' => '100.00',
			'avg_lead_time' => '2.0',
		) );

		// Update last_selected to be recent.
		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_collection_date_analytics';
		$wpdb->query(
			"UPDATE {$table_name} SET last_selected = NOW() WHERE id IN (" . implode( ',', $analytics_ids ) . ")"
		);

		// Use reflection to test private method.
		$reflection = new ReflectionClass( $this->analytics );
		$method = $reflection->getMethod( 'get_summary_stats' );
		$method->setAccessible( true );

		$stats = $method->invoke( $this->analytics, '30days' );

		$this->assertEquals( 3, $stats['total_dates_available'] );
		$this->assertEquals( 30, $stats['total_selections'] ); // 3 * 10
		$this->assertEquals( 15, $stats['total_orders'] ); // 3 * 5
		$this->assertEquals( '300.00', $stats['total_value']['raw'] ); // 3 * 100.00
		$this->assertEquals( 2.0, $stats['avg_lead_time'] );
		$this->assertGreaterThan( 0, $stats['conversion_rate'] );
	}

	/**
	 * Test get_popular_dates.
	 */
	public function test_get_popular_dates() {
		// Create test analytics with varying popularity.
		$this->factory->analytics->create( array(
			'collection_date' => '2024-12-25',
			'selection_count' => 20,
			'total_orders' => 10,
			'total_value' => '200.00',
			'avg_lead_time' => '2.0',
		) );

		$this->factory->analytics->create( array(
			'collection_date' => '2024-12-26',
			'selection_count' => 15,
			'total_orders' => 8,
			'total_value' => '150.00',
			'avg_lead_time' => '1.5',
		) );

		$this->factory->analytics->create( array(
			'collection_date' => '2024-12-27',
			'selection_count' => 25,
			'total_orders' => 12,
			'total_value' => '250.00',
			'avg_lead_time' => '3.0',
		) );

		// Use reflection to test private method.
		$reflection = new ReflectionClass( $this->analytics );
		$method = $reflection->getMethod( 'get_popular_dates' );
		$method->setAccessible( true );

		$popular_dates = $method->invoke( $this->analytics, '30days' );

		$this->assertCount( 3, $popular_dates );

		// Should be ordered by selection_count descending.
		$this->assertEquals( '2024-12-27', $popular_dates[0]->collection_date ); // 25 selections
		$this->assertEquals( '2024-12-25', $popular_dates[1]->collection_date ); // 20 selections
		$this->assertEquals( '2024-12-26', $popular_dates[2]->collection_date ); // 15 selections
	}

	/**
	 * Test export_analytics_data.
	 */
	public function test_export_analytics_data() {
		// Create test analytics data.
		$this->factory->analytics->create_many( 3, array(
			'collection_date' => '2024-12-25',
			'selection_count' => 10,
			'total_orders' => 5,
			'total_value' => '100.00',
			'avg_lead_time' => '2.0',
		) );

		$_POST['nonce'] = wp_create_nonce( 'wc_collection_date_analytics_nonce' );
		$_POST['period'] = '30days';
		$_POST['data_type'] = 'popular_dates';

		$current_user = wp_get_current_user();
		$current_user->add_cap( 'manage_woocommerce' );

		ob_start();
		$this->analytics->export_analytics_data();
		$output = ob_get_clean();

		// Should contain CSV headers and data.
		$this->assertStringContainsString( 'Content-Type: text/csv', xdebug_get_headers() ? '' : '' );
		$this->assertStringContainsString( 'Date', $output );
		$this->assertStringContainsString( 'Selection Count', $output );
		$this->assertStringContainsString( 'Total Orders', $output );
		$this->assertStringContainsString( '2024-12-25', $output );
	}

	/**
	 * Test track_hourly_stats.
	 */
	public function test_track_hourly_stats() {
		// Use reflection to test private method.
		$reflection = new ReflectionClass( $this->analytics );
		$method = $reflection->getMethod( 'track_hourly_stats' );
		$method->setAccessible( true );

		$hour = 14; // 2 PM
		$count = 5;

		$method->invoke( $this->analytics, $hour, $count );

		$hourly_stats = get_option( 'wc_collection_date_hourly_stats', array() );
		$today = date( 'Y-m-d' );

		$this->assertArrayHasKey( $today, $hourly_stats );
		$this->assertArrayHasKey( 14, $hourly_stats[ $today ] );
		$this->assertEquals( 5, $hourly_stats[ $today ][14] );
	}

	/**
	 * Test track_day_of_week_stats.
	 */
	public function test_track_day_of_week_stats() {
		$reflection = new ReflectionClass( $this->analytics );
		$method = $reflection->getMethod( 'track_day_of_week_stats' );
		$method->setAccessible( true );

		$day = 3; // Wednesday
		$count = 8;

		$method->invoke( $this->analytics, $day, $count );

		$day_stats = get_option( 'wc_collection_date_day_stats', array_fill( 1, 7, 0 ) );

		$this->assertEquals( 8, $day_stats[ $day ] );
	}

	/**
	 * Test track_monthly_stats.
	 */
	public function test_track_monthly_stats() {
		$reflection = new ReflectionClass( $this->analytics );
		$method = $reflection->getMethod( 'track_monthly_stats' );
		$method->setAccessible( true );

		$month = 12; // December
		$count = 15;

		$method->invoke( $this->analytics, $month, $count );

		$monthly_stats = get_option( 'wc_collection_date_monthly_stats', array_fill( 1, 12, 0 ) );

		$this->assertEquals( 15, $monthly_stats[ $month ] );
	}

	/**
	 * Test aggregate_daily_stats.
	 */
	public function test_aggregate_daily_stats() {
		// Set up some old hourly stats.
		$old_date = date( 'Y-m-d', strtotime( '-100 days' ) );
		$old_stats = array(
			$old_date => array_fill( 0, 24, 5 ),
		);
		update_option( 'wc_collection_date_hourly_stats', $old_stats );

		$this->analytics->aggregate_daily_stats();

		$hourly_stats = get_option( 'wc_collection_date_hourly_stats', array() );

		// Old data should be removed.
		$this->assertArrayNotHasKey( $old_date, $hourly_stats );
	}

	/**
	 * Test get_date_range_analytics.
	 */
	public function test_get_date_range_analytics() {
		// Create test analytics data for date range.
		$this->factory->analytics->create_for_date_range( '2024-12-01', '2024-12-05' );

		$analytics = $this->analytics->get_date_range_analytics( '2024-12-01', '2024-12-05' );

		$this->assertCount( 5, $analytics );

		foreach ( $analytics as $record ) {
			$this->assertObjectHasProperty( 'collection_date', $record );
			$this->assertObjectHasProperty( 'selection_count', $record );
			$this->assertObjectHasProperty( 'total_orders', $record );
			$this->assertObjectHasProperty( 'total_value', $record );
			$this->assertObjectHasProperty( 'avg_lead_time', $record );
			$this->assertObjectHasProperty( 'day_of_week', $record );
		}
	}

	/**
	 * Test get_lead_time_distribution.
	 */
	public function test_get_lead_time_distribution() {
		// Create test analytics with different lead times.
		$this->factory->analytics->create( array(
			'collection_date' => '2024-12-25',
			'avg_lead_time' => '0.5',
			'total_orders' => 3,
		) );

		$this->factory->analytics->create( array(
			'collection_date' => '2024-12-26',
			'avg_lead_time' => '2.5',
			'total_orders' => 5,
		) );

		$this->factory->analytics->create( array(
			'collection_date' => '2024-12-27',
			'avg_lead_time' => '5.0',
			'total_orders' => 2,
		) );

		$this->factory->analytics->create( array(
			'collection_date' => '2024-12-28',
			'avg_lead_time' => '10.0',
			'total_orders' => 1,
		) );

		// Use reflection to test private method.
		$reflection = new ReflectionClass( $this->analytics );
		$method = $reflection->getMethod( 'get_lead_time_distribution' );
		$method->setAccessible( true );

		$distribution = $method->invoke( $this->analytics, '30days' );

		$this->assertArrayHasKey( '0-1 days', $distribution );
		$this->assertArrayHasKey( '2-3 days', $distribution );
		$this->assertArrayHasKey( '4-7 days', $distribution );
		$this->assertArrayHasKey( '8-14 days', $distribution );
		$this->assertArrayHasKey( '15+ days', $distribution );

		$this->assertEquals( 3, $distribution['0-1 days'] );
		$this->assertEquals( 5, $distribution['2-3 days'] );
		$this->assertEquals( 2, $distribution['4-7 days'] );
		$this->assertEquals( 1, $distribution['8-14 days'] );
		$this->assertEquals( 0, $distribution['15+ days'] );
	}

	/**
	 * Helper method to get analytics record count.
	 *
	 * @return int Record count.
	 */
	protected function get_analytics_record_count() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_collection_date_analytics';

		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table_name}" );
	}
}