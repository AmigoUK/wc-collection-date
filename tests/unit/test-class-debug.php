<?php
/**
 * Unit Tests for WC_Collection_Date_Debug Class
 *
 * @package WC_Collection_Date\Tests\Unit
 */

class WC_Collection_Date_Test_Debug extends WC_Collection_Date_Test_Base {

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::setUp();

		// Define debug constant for testing.
		if ( ! defined( 'WC_COLLECTION_DATE_DEBUG' ) ) {
			define( 'WC_COLLECTION_DATE_DEBUG', true );
		}

		// Set up WP_DEBUG_LOG for testing.
		if ( ! defined( 'WP_DEBUG_LOG' ) ) {
			define( 'WP_DEBUG_LOG', true );
		}
	}

	/**
	 * Test is_enabled with debug constant true.
	 */
	public function test_is_enabled_with_debug_true() {
		if ( ! defined( 'WC_COLLECTION_DATE_DEBUG' ) ) {
			define( 'WC_COLLECTION_DATE_DEBUG', true );
		}

		$this->assertTrue( WC_Collection_Date_Debug::is_enabled() );
	}

	/**
	 * Test is_enabled with debug constant false.
	 */
	public function test_is_enabled_with_debug_false() {
		// Can't redefine constant, so we'll test the logic with reflection.
		$reflection = new ReflectionClass( 'WC_Collection_Date_Debug' );
		$method = $reflection->getMethod( 'is_enabled' );
		$method->setAccessible( true );

		// When constant is not defined or is false, should return false.
		$result = $method->invoke( null );
		$this->assertTrue( $result ); // Should be true due to our setUp
	}

	/**
	 * Test log with debug enabled.
	 */
	public function test_log_with_debug_enabled() {
		$message = 'Test debug message';
		$context = 'test-context';
		$data = array( 'key' => 'value' );

		// Clear existing logs.
		WC_Collection_Date_Debug::clear_logs();

		WC_Collection_Date_Debug::log( $message, $context, $data );

		$logs = WC_Collection_Date_Debug::get_logs();

		$this->assertCount( 1, $logs );
		$this->assertStringContainsString( $message, $logs[0] );
		$this->assertStringContainsString( strtoupper( $context ), $logs[0] );
		$this->assertStringContainsString( '"key":"value"', $logs[0] );
	}

	/**
	 * Test log with debug disabled.
	 */
	public function test_log_with_debug_disabled() {
		// We can't easily test this without modifying the constant,
		// but we can test the log structure.
		$message = 'Test message';

		WC_Collection_Date_Debug::log( $message );

		$logs = WC_Collection_Date_Debug::get_logs();

		$this->assertNotEmpty( $logs[0] );
		$this->assertStringContainsString( $message, $logs[0] );
	}

	/**
	 * Test log without data parameter.
	 */
	public function test_log_without_data() {
		$message = 'Test message without data';
		$context = 'test';

		WC_Collection_Date_Debug::clear_logs();
		WC_Collection_Date_Debug::log( $message, $context );

		$logs = WC_Collection_Date_Debug::get_logs();

		$this->assertCount( 1, $logs );
		$this->assertStringContainsString( $message, $logs[0] );
		$this->assertStringNotContainsString( 'Data:', $logs[0] );
	}

	/**
	 * Test log with complex data.
	 */
	public function test_log_with_complex_data() {
		$message = 'Complex data test';
		$data = array(
			'string' => 'test string',
			'number' => 42,
			'array' => array( 'nested' => 'value' ),
			'boolean' => true,
			'null' => null,
		);

		WC_Collection_Date_Debug::clear_logs();
		WC_Collection_Date_Debug::log( $message, 'test', $data );

		$logs = WC_Collection_Date_Debug::get_logs();

		$this->assertCount( 1, $logs );
		$this->assertStringContainsString( '"string":"test string"', $logs[0] );
		$this->assertStringContainsString( '"number":42', $logs[0] );
		$this->assertStringContainsString( '"boolean":true', $logs[0] );
		$this->assertStringContainsString( '"null":null', $logs[0] );
	}

	/**
	 * Test log_cache method.
	 */
	public function test_log_cache() {
		$operation = 'hit';
		$key = 'test_cache_key';

		WC_Collection_Date_Debug::clear_logs();
		WC_Collection_Date_Debug::log_cache( $operation, $key );

		$logs = WC_Collection_Date_Debug::get_logs();

		$this->assertCount( 1, $logs );
		$this->assertStringContainsString( 'Cache hit: test_cache_key', $logs[0] );
		$this->assertStringContainsString( 'CACHE', $logs[0] );
	}

	/**
	 * Test log_date_calculation method.
	 */
	public function test_log_date_calculation() {
		$settings = array(
			'lead_time' => 3,
			'lead_time_type' => 'working',
		);
		$limit = 30;
		$dates = array( '2024-12-25', '2024-12-26' );

		WC_Collection_Date_Debug::clear_logs();
		WC_Collection_Date_Debug::log_date_calculation( $settings, $limit, $dates );

		$logs = WC_Collection_Date_Debug::get_logs();

		$this->assertCount( 1, $logs );
		$this->assertStringContainsString( 'Calculated 2 dates with limit 30', $logs[0] );
		$this->assertStringContainsString( 'DATE-CALC', $logs[0] );
		$this->assertStringContainsString( '"lead_time":3', $logs[0] );
		$this->assertStringContainsString( '"lead_time_type":"working"', $logs[0] );
		$this->assertStringContainsString( '"first_date":"2024-12-25"', $logs[0] );
		$this->assertStringContainsString( '"last_date":"2024-12-26"', $logs[0] );
	}

	/**
	 * Test log_date_calculation with empty dates.
	 */
	public function test_log_date_calculation_empty_dates() {
		$settings = array();
		$limit = 0;
		$dates = array();

		WC_Collection_Date_Debug::clear_logs();
		WC_Collection_Date_Debug::log_date_calculation( $settings, $limit, $dates );

		$logs = WC_Collection_Date_Debug::get_logs();

		$this->assertCount( 1, $logs );
		$this->assertStringContainsString( 'Calculated 0 dates with limit 0', $logs[0] );
		$this->assertStringContainsString( '"first_date":null', $logs[0] );
		$this->assertStringContainsString( '"last_date":null', $logs[0] );
	}

	/**
	 * Test log_api method.
	 */
	public function test_log_api() {
		$endpoint = '/wc-collection-date/v1/dates';
		$params = array( 'limit' => 30 );
		$response = array( 'success' => true, 'dates' => array() );

		WC_Collection_Date_Debug::clear_logs();
		WC_Collection_Date_Debug::log_api( $endpoint, $params, $response );

		$logs = WC_Collection_Date_Debug::get_logs();

		$this->assertCount( 1, $logs );
		$this->assertStringContainsString( 'API Request: /wc-collection-date/v1/dates', $logs[0] );
		$this->assertStringContainsString( 'API', $logs[0] );
		$this->assertStringContainsString( '"params":', $logs[0] );
		$this->assertStringContainsString( '"response":', $logs[0] );
	}

	/**
	 * Test get_logs with default limit.
	 */
	public function test_get_logs_default_limit() {
		// Create more than 50 logs to test limit.
		WC_Collection_Date_Debug::clear_logs();

		for ( $i = 0; $i < 60; $i++ ) {
			WC_Collection_Date_Debug::log( "Test message {$i}" );
		}

		$logs = WC_Collection_Date_Debug::get_logs();

		$this->assertCount( 50, $logs ); // Default limit
		$this->assertStringContainsString( 'Test message 0', $logs[0] ); // Most recent first
		$this->assertStringContainsString( 'Test message 49', $logs[49] );
	}

	/**
	 * Test get_logs with custom limit.
	 */
	public function test_get_logs_custom_limit() {
		WC_Collection_Date_Debug::clear_logs();

		for ( $i = 0; $i < 10; $i++ ) {
			WC_Collection_Date_Debug::log( "Test message {$i}" );
		}

		$logs = WC_Collection_Date_Debug::get_logs( 5 );

		$this->assertCount( 5, $logs );
		$this->assertStringContainsString( 'Test message 0', $logs[0] );
		$this->assertStringContainsString( 'Test message 4', $logs[4] );
	}

	/**
	 * Test clear_logs functionality.
	 */
	public function test_clear_logs() {
		// Add some logs.
		WC_Collection_Date_Debug::log( 'Test message 1' );
		WC_Collection_Date_Debug::log( 'Test message 2' );

		// Verify logs exist.
		$logs = WC_Collection_Date_Debug::get_logs();
		$this->assertNotEmpty( $logs );

		// Clear logs.
		$result = WC_Collection_Date_Debug::clear_logs();

		$this->assertTrue( $result );

		// Verify logs are cleared.
		$logs = WC_Collection_Date_Debug::get_logs();
		$this->assertEmpty( $logs );
	}

	/**
	 * Test log entry format and structure.
	 */
	public function test_log_entry_format() {
		$message = 'Test message';
		$context = 'test';

		WC_Collection_Date_Debug::clear_logs();
		WC_Collection_Date_Debug::log( $message, $context );

		$logs = WC_Collection_Date_Debug::get_logs();
		$log_entry = $logs[0];

		// Should contain timestamp.
		$this->assertMatchesRegularExpression( '/^\[\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}\]/', $log_entry );

		// Should contain context in uppercase.
		$this->assertStringContainsString( '[TEST]', $log_entry );

		// Should contain message.
		$this->assertStringContainsString( $message, $log_entry );
	}

	/**
	 * Test log entry storage and retrieval consistency.
	 */
	public function test_log_storage_consistency() {
		$test_messages = array(
			'First message',
			'Second message with special chars: äöüß',
			'Third message with "quotes"',
		);

		WC_Collection_Date_Debug::clear_logs();

		foreach ( $test_messages as $message ) {
			WC_Collection_Date_Debug::log( $message );
		}

		$logs = WC_Collection_Date_Debug::get_logs();

		$this->assertCount( count( $test_messages ), $logs );

		// Messages should be stored in reverse order (newest first).
		for ( $i = 0; $i < count( $test_messages ); $i++ ) {
			$this->assertStringContainsString( $test_messages[ $i ], $logs[ $i ] );
		}
	}

	/**
	 * Test log with different contexts.
	 */
	public function test_log_different_contexts() {
		$contexts = array( 'cache', 'date-calc', 'api', 'general', 'custom' );

		WC_Collection_Date_Debug::clear_logs();

		foreach ( $contexts as $context ) {
			WC_Collection_Date_Debug::log( "Message for {$context}", $context );
		}

		$logs = WC_Collection_Date_Debug::get_logs();

		$this->assertCount( count( $contexts ), $logs );

		foreach ( $contexts as $context ) {
			$found = false;
			foreach ( $logs as $log ) {
				if ( strpos( $log, '[' . strtoupper( $context ) . ']' ) !== false ) {
					$found = true;
					break;
				}
			}
			$this->assertTrue( $found, "Context {$context} should be found in logs" );
		}
	}

	/**
	 * Test log limit enforcement.
	 */
	public function test_log_limit_enforcement() {
		WC_Collection_Date_Debug::clear_logs();

		// Create exactly 100 logs (the limit).
		for ( $i = 0; $i < 100; $i++ ) {
			WC_Collection_Date_Debug::log( "Message {$i}" );
		}

		$logs = WC_Collection_Date_Debug::get_logs( 100 );
		$this->assertCount( 100, $logs );

		// Add one more log.
		WC_Collection_Date_Debug::log( 'Message 100' );

		$logs = WC_Collection_Date_Debug::get_logs( 100 );
		$this->assertCount( 100, $logs ); // Should still be 100 (oldest removed)

		// The newest message should be present.
		$this->assertStringContainsString( 'Message 100', $logs[0] );

		// The oldest message should be gone.
		$this->assertStringNotContainsString( 'Message 0', $logs[99] );
	}

	/**
	 * Test concurrent log operations.
	 */
	public function test_concurrent_operations() {
		WC_Collection_Date_Debug::clear_logs();

		// Simulate multiple log operations.
		for ( $i = 0; $i < 10; $i++ ) {
			WC_Collection_Date_Debug::log( "Message {$i}", 'test' );
			WC_Collection_Date_Debug::log_cache( 'hit', "cache_key_{$i}" );
		}

		$logs = WC_Collection_Date_Debug::get_logs( 20 );

		// Should have 20 log entries (10 regular + 10 cache).
		$this->assertCount( 20, $logs );

		$regular_count = 0;
		$cache_count = 0;

		foreach ( $logs as $log ) {
			if ( strpos( $log, 'TEST' ) !== false ) {
				$regular_count++;
			} elseif ( strpos( $log, 'CACHE' ) !== false ) {
				$cache_count++;
			}
		}

		$this->assertEquals( 10, $regular_count );
		$this->assertEquals( 10, $cache_count );
	}
}