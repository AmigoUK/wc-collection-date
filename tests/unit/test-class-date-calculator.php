<?php
/**
 * Unit Tests for WC_Collection_Date_Calculator Class
 *
 * @package WC_Collection_Date\Tests\Unit
 */

class WC_Collection_Date_Test_Date_Calculator extends WC_Collection_Date_Test_Base {

	/**
	 * Date calculator instance.
	 *
	 * @var WC_Collection_Date_Calculator
	 */
	protected $calculator;

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->calculator = new WC_Collection_Date_Calculator();
	}

	/**
	 * Test calculator instantiation.
	 */
	public function test_calculator_instantiation() {
		$this->assertInstanceOf( 'WC_Collection_Date_Calculator', $this->calculator );
	}

	/**
	 * Test get_available_dates with default settings.
	 */
	public function test_get_available_dates_default() {
		$dates = $this->calculator->get_available_dates( 30 );

		$this->assertIsArray( $dates );
		$this->assertCount( 30, $dates );

		// All dates should be in Y-m-d format.
		foreach ( $dates as $date ) {
			$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $date );
			$this->assertTrue( $this->calculator->is_valid_date_format( $date ) );
		}

		// Dates should be in chronological order.
		for ( $i = 1; $i < count( $dates ); $i++ ) {
			$this->assertLessThan( strtotime( $dates[ $i ] ), strtotime( $dates[ $i - 1 ] ) );
		}

		// First date should be at least lead time + 1 days from now.
		$min_date = new DateTime( 'now' );
		$min_date->modify( '+3 days' ); // lead_time(2) + 1
		$this->assertGreaterThanOrEqual( strtotime( $min_date->format( 'Y-m-d' ) ), strtotime( $dates[0] ) );
	}

	/**
	 * Test get_available_dates with custom lead time.
	 */
	public function test_get_available_dates_custom_lead_time() {
		$this->set_plugin_setting( 'lead_time', 5 );

		$dates = $this->calculator->get_available_dates( 10 );

		$this->assertCount( 10, $dates );

		// First date should be at least lead_time + 1 days from now.
		$min_date = new DateTime( 'now' );
		$min_date->modify( '+6 days' ); // lead_time(5) + 1
		$this->assertGreaterThanOrEqual( strtotime( $min_date->format( 'Y-m-d' ) ), strtotime( $dates[0] ) );
	}

	/**
	 * Test get_available_dates with working days calculation.
	 */
	public function test_get_available_dates_working_days() {
		$this->set_plugin_setting( 'lead_time', 3 );
		$this->set_plugin_setting( 'lead_time_type', 'working' );
		$this->set_plugin_setting( 'working_days', array( '1', '2', '3', '4', '5' ) ); // Monday to Friday
		$this->set_plugin_setting( 'collection_days', array( '0', '1', '2', '3', '4', '5', '6' ) ); // All days

		$dates = $this->calculator->get_available_dates( 20 );

		$this->assertIsArray( $dates );
		$this->assertCount( 20, $dates );

		// With working days calculation and starting from a weekday,
		// the first date should be further in the future than calendar days.
		$first_date = new DateTime( $dates[0] );
		$calendar_first_date = new DateTime( 'now' );
		$calendar_first_date->modify( '+4 days' ); // lead_time(3) + 1

		// Working days calculation should result in a later date.
		$this->assertGreaterThanOrEqual( $calendar_first_date, $first_date );
	}

	/**
	 * Test get_available_dates with collection days restrictions.
	 */
	public function test_get_available_dates_collection_days_restriction() {
		$this->set_plugin_setting( 'lead_time', 1 );
		$this->set_plugin_setting( 'collection_days', array( '1', '3', '5' ) ); // Monday, Wednesday, Friday only

		$dates = $this->calculator->get_available_dates( 15 );

		$this->assertIsArray( $dates );
		$this->assertCount( 15, $dates );

		// All returned dates should be Monday, Wednesday, or Friday.
		foreach ( $dates as $date ) {
			$day_of_week = (int) ( new DateTime( $date ) )->format( 'w' );
			$this->assertContains( (string) $day_of_week, array( '1', '3', '5' ) );
		}
	}

	/**
	 * Test get_available_dates with cutoff time penalty.
	 */
	public function test_get_available_dates_cutoff_time() {
		// Set cutoff time to a time that should apply penalty.
		$this->set_plugin_setting( 'lead_time', 2 );
		$this->set_plugin_setting( 'cutoff_time', '23:59' );

		$dates = $this->calculator->get_available_dates( 10 );

		$this->assertCount( 10, $dates );

		// First date should be at least lead_time + 1 (penalty) + 1 days from now.
		$min_date = new DateTime( 'now' );
		$min_date->modify( '+4 days' ); // lead_time(2) + penalty(1) + 1
		$this->assertGreaterThanOrEqual( strtotime( $min_date->format( 'Y-m-d' ) ), strtotime( $dates[0] ) );
	}

	/**
	 * Test get_available_dates for product with category rules.
	 */
	public function test_get_available_dates_for_product_with_category_rules() {
		// Create test product and category.
		$category_id = $this->create_test_category();
		$product_id = $this->create_test_product();

		// Assign product to category.
		wp_set_object_terms( $product_id, array( $category_id ), 'product_cat' );

		// Set up category rule with longer lead time.
		$category_settings = array(
			'lead_time'       => 5,
			'lead_time_type'  => 'calendar',
			'cutoff_time'     => '',
			'working_days'    => array( '1', '2', '3', '4', '5' ),
			'collection_days' => array( '0', '1', '2', '3', '4', '5', '6' ),
		);
		$this->setup_category_rule( $category_id, $category_settings );

		$dates = $this->calculator->get_available_dates_for_product( $product_id, 10 );

		$this->assertIsArray( $dates );
		$this->assertCount( 10, $dates );

		// First date should respect the category lead time (5 days + 1).
		$min_date = new DateTime( 'now' );
		$min_date->modify( '+6 days' );
		$this->assertGreaterThanOrEqual( strtotime( $min_date->format( 'Y-m-d' ) ), strtotime( $dates[0] ) );
	}

	/**
	 * Test get_available_dates for product with no category rules (fallback to global).
	 */
	public function test_get_available_dates_for_product_fallback_to_global() {
		$product_id = $this->create_test_product();

		// Set global settings.
		$this->set_plugin_setting( 'lead_time', 4 );
		$this->set_plugin_setting( 'collection_days', array( '1', '2', '3', '4', '5' ) );

		$global_dates = $this->calculator->get_available_dates( 10 );
		$product_dates = $this->calculator->get_available_dates_for_product( $product_id, 10 );

		// Should be the same since product has no category rules.
		$this->assertEquals( $global_dates, $product_dates );
	}

	/**
	 * Test date exclusions functionality.
	 */
	public function test_date_exclusions() {
		// Create exclusions for the next few days.
		$today = new DateTime( 'now' );
		$exclusions = array();

		for ( $i = 3; $i <= 7; $i++ ) { // Start from lead_time + 1
			$exclusion_date = clone $today;
			$exclusion_date->modify( "+{$i} days" );
			$exclusion_date_str = $exclusion_date->format( 'Y-m-d' );
			$exclusions[] = $exclusion_date_str;
			$this->create_test_exclusion( $exclusion_date_str, "Test exclusion {$i}" );
		}

		$this->set_plugin_setting( 'lead_time', 2 );

		$dates = $this->calculator->get_available_dates( 20 );

		$this->assertCount( 20, $dates );

		// None of the excluded dates should be in the results.
		foreach ( $exclusions as $exclusion ) {
			$this->assertDateNotAvailable( $exclusion, $dates );
		}
	}

	/**
	 * Test is_date_available functionality.
	 */
	public function test_is_date_available() {
		$this->set_plugin_setting( 'lead_time', 2 );

		// Test future date (should be available if it's a collection day).
		$future_date = new DateTime( 'now' );
		$future_date->modify( '+10 days' );
		$this->assertTrue( $this->calculator->is_date_available( $future_date->format( 'Y-m-d' ) ) );

		// Test past date (should not be available).
		$past_date = new DateTime( 'now' );
		$past_date->modify( '-10 days' );
		$this->assertFalse( $this->calculator->is_date_available( $past_date->format( 'Y-m-d' ) ) );

		// Test excluded date.
		$exclusion_date = new DateTime( 'now' );
		$exclusion_date->modify( '+10 days' );
		$this->create_test_exclusion( $exclusion_date->format( 'Y-m-d' ), 'Test exclusion' );
		$this->assertFalse( $this->calculator->is_date_available( $exclusion_date->format( 'Y-m-d' ) ) );

		// Test invalid date format.
		$this->assertFalse( $this->calculator->is_date_available( 'invalid-date' ) );
		$this->assertFalse( $this->calculator->is_date_available( '2024-13-01' ) );
		$this->assertFalse( $this->calculator->is_date_available( '' ) );
	}

	/**
	 * Test get_earliest_date functionality.
	 */
	public function test_get_earliest_date() {
		$earliest = $this->calculator->get_earliest_date();
		$dates = $this->calculator->get_available_dates( 10 );

		$this->assertNotEmpty( $earliest );
		$this->assertEquals( $dates[0], $earliest );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $earliest );
	}

	/**
	 * Test format_date_for_display functionality.
	 */
	public function test_format_date_for_display() {
		$date = '2024-12-25';
		$formatted = $this->calculator->format_date_for_display( $date );

		// Should use WordPress date format.
		$wp_format = get_option( 'date_format', 'Y-m-d' );
		$expected = date( $wp_format, strtotime( $date ) );

		$this->assertEquals( $expected, $formatted );

		// Test custom format.
		$custom_formatted = $this->calculator->format_date_for_display( $date, 'l, F j, Y' );
		$expected_custom = date( 'l, F j, Y', strtotime( $date ) );

		$this->assertEquals( $expected_custom, $custom_formatted );

		// Test invalid date (should return original string).
		$this->assertEquals( 'invalid-date', $this->calculator->format_date_for_display( 'invalid-date' ) );
	}

	/**
	 * Test get_date_range functionality.
	 */
	public function test_get_date_range() {
		$this->set_plugin_setting( 'lead_time', 3 );
		$this->set_plugin_setting( 'max_booking_days', 60 );

		$range = $this->calculator->get_date_range();

		$this->assertArrayHasKey( 'min_date', $range );
		$this->assertArrayHasKey( 'max_date', $range );

		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $range['min_date'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $range['max_date'] );

		// Min date should be lead_time + 1 days from now.
		$expected_min = new DateTime( 'now' );
		$expected_min->modify( '+4 days' );
		$this->assertEquals( $expected_min->format( 'Y-m-d' ), $range['min_date'] );

		// Max date should be max_booking_days from now.
		$expected_max = new DateTime( 'now' );
		$expected_max->modify( '+60 days' );
		$this->assertEquals( $expected_max->format( 'Y-m-d' ), $range['max_date'] );

		$this->assertLessThan( strtotime( $range['min_date'] ), strtotime( $range['max_date'] ) );
	}

	/**
	 * Test caching functionality.
	 */
	public function test_caching_functionality() {
		$dates1 = $this->calculator->get_available_dates( 30 );
		$dates2 = $this->calculator->get_available_dates( 30 );

		// Should return same results (from cache).
		$this->assertEquals( $dates1, $dates2 );

		// Test cache invalidation.
		WC_Collection_Date_Calculator::clear_cache();
		$dates3 = $this->calculator->get_available_dates( 30 );

		$this->assertEquals( $dates1, $dates3 );
	}

	/**
	 * Test cache key generation.
	 */
	public function test_cache_key_generation() {
		$reflection = new ReflectionClass( $this->calculator );
		$method = $reflection->getMethod( 'get_cache_key' );
		$method->setAccessible( true );

		$key1 = $method->invoke( $this->calculator, 'global', 30 );
		$key2 = $method->invoke( $this->calculator, 'global', 60 );
		$key3 = $method->invoke( $this->calculator, 'product_123', 30 );

		// Keys should be different for different parameters.
		$this->assertNotEquals( $key1, $key2 );
		$this->assertNotEquals( $key1, $key3 );

		// Keys should contain context and limit.
		$this->assertStringContains( 'global', $key1 );
		$this->assertStringContains( '30', $key1 );
		$this->assertStringContains( 'product_123', $key3 );
	}

	/**
	 * Test calculate_working_days_forward.
	 */
	public function test_calculate_working_days_forward() {
		$reflection = new ReflectionClass( $this->calculator );
		$method = $reflection->getMethod( 'calculate_working_days_forward' );
		$method->setAccessible( true );

		$this->set_plugin_setting( 'working_days', array( '1', '2', '3', '4', '5' ) );

		// Test starting from Monday, adding 3 working days.
		$start_date = new DateTime( '2024-01-15' ); // Monday
		$result = $method->invoke( $this->calculator, $start_date, 3 );

		// Monday + 3 working days = Thursday (skipping weekend)
		$expected = new DateTime( '2024-01-18' );
		$this->assertEquals( $expected->format( 'Y-m-d' ), $result->format( 'Y-m-d' ) );

		// Test starting from Friday, adding 2 working days.
		$start_date = new DateTime( '2024-01-19' ); // Friday
		$result = $method->invoke( $this->calculator, $start_date, 2 );

		// Friday + 2 working days = Tuesday (skipping weekend)
		$expected = new DateTime( '2024-01-23' );
		$this->assertEquals( $expected->format( 'Y-m-d' ), $result->format( 'Y-m-d' ) );
	}

	/**
	 * Test cutoff time penalty application.
	 */
	public function test_apply_cutoff_time_penalty() {
		$reflection = new ReflectionClass( $this->calculator );
		$method = $reflection->getMethod( 'apply_cutoff_time_penalty' );
		$method->setAccessible( true );

		// Test without cutoff time.
		$result = $method->invoke( $this->calculator, 2, '' );
		$this->assertEquals( 2, $result );

		// Test with cutoff time that doesn't apply (early morning).
		$result = $method->invoke( $this->calculator, 2, '12:00' );
		$this->assertEquals( 2, $result ); // Assuming test runs in morning

		// Test with cutoff time that applies.
		$result = $method->invoke( $this->calculator, 2, '00:00' );
		$this->assertEquals( 3, $result );
	}

	/**
	 * Test edge cases and error conditions.
	 */
	public function test_edge_cases() {
		// Test zero limit.
		$dates = $this->calculator->get_available_dates( 0 );
		$this->assertIsArray( $dates );

		// Test negative limit.
		$dates = $this->calculator->get_available_dates( -10 );
		$this->assertIsArray( $dates );

		// Test very large limit.
		$dates = $this->calculator->get_available_dates( 1000 );
		$this->assertIsArray( $dates );
		$this->assertLessThanOrEqual( 90, count( $dates ) ); // Limited by max_booking_days

		// Test invalid working days format.
		$this->set_plugin_setting( 'working_days', 'invalid' );
		$dates = $this->calculator->get_available_dates( 10 );
		$this->assertIsArray( $dates );

		// Test invalid collection days format.
		$this->set_plugin_setting( 'collection_days', 'invalid' );
		$dates = $this->calculator->get_available_dates( 10 );
		$this->assertIsArray( $dates );
	}

	/**
	 * Test max_booking_days limit.
	 */
	public function test_max_booking_days_limit() {
		$this->set_plugin_setting( 'max_booking_days', 30 );

		$range = $this->calculator->get_date_range();

		$min_date = new DateTime( $range['min_date'] );
		$max_date = new DateTime( $range['max_date'] );
		$days_diff = $max_date->diff( $min_date )->days;

		// Should be exactly max_booking_days days apart.
		$this->assertEquals( 30, $days_diff );
	}
}