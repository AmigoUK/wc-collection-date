<?php
/**
 * Base Test Class for WooCommerce Collection Date Plugin
 *
 * Provides common setup, teardown, and utility methods for all test cases.
 *
 * @package WC_Collection_Date\Tests
 */

use PHPUnit\Framework\TestCase;

/**
 * Base test class.
 */
abstract class WC_Collection_Date_Test_Base extends TestCase {

	/**
	 * Factory instance.
	 *
	 * @var WC_Collection_Date_Factory
	 */
	protected $factory;

	/**
	 * Current user ID.
	 *
	 * @var int
	 */
	protected static $user_id = 0;

	/**
	 * Setup the test environment.
	 */
	public function setUp(): void {
		parent::setUp();

		// Get the factory instance.
		$this->factory = wc_collection_date_factory();

		// Clean up any existing data.
		$this->cleanup_plugin_data();

		// Set up default test environment.
		$this->setup_test_environment();

		// Ensure current user is set.
		if ( 0 === self::$user_id ) {
			self::$user_id = $this->factory->user->create(
				array(
					'role' => 'administrator',
				)
			);
		}
		wp_set_current_user( self::$user_id );
	}

	/**
	 * Tear down the test environment.
	 */
	public function tearDown(): void {
		// Clean up any test data.
		$this->cleanup_test_data();

		// Clear WordPress object cache.
		wp_cache_flush();

		parent::tearDown();
	}

	/**
	 * Clean up plugin-specific data before each test.
	 */
	protected function cleanup_plugin_data() {
		global $wpdb;

		// Clear plugin tables.
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wc_collection_exclusions" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wc_collection_date_analytics" );

		// Clear plugin options.
		$plugin_options = array(
			'wc_collection_date_lead_time',
			'wc_collection_date_lead_time_type',
			'wc_collection_date_cutoff_time',
			'wc_collection_date_working_days',
			'wc_collection_date_collection_days',
			'wc_collection_date_max_booking_days',
			'wc_collection_date_category_rules',
			'wc_collection_date_debug_logs',
			'wc_collection_date_hourly_stats',
			'wc_collection_date_day_stats',
			'wc_collection_date_monthly_stats',
		);

		foreach ( $plugin_options as $option ) {
			delete_option( $option );
		}

		// Clear all plugin transients.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_wc_collection_date%'
			OR option_name LIKE '_transient_timeout_wc_collection_date%'"
		);
	}

	/**
	 * Clean up test data after each test.
	 */
	protected function cleanup_test_data() {
		// Clean up any created posts, terms, etc.
		// This is handled by the factory's cleanup methods.
	}

	/**
	 * Set up default test environment.
	 */
	protected function setup_test_environment() {
		// Set default plugin options.
		update_option( 'wc_collection_date_lead_time', 2 );
		update_option( 'wc_collection_date_lead_time_type', 'calendar' );
		update_option( 'wc_collection_date_cutoff_time', '' );
		update_option( 'wc_collection_date_working_days', array( '1', '2', '3', '4', '5', '6' ) );
		update_option( 'wc_collection_date_collection_days', array( '0', '1', '2', '3', '4', '5', '6' ) );
		update_option( 'wc_collection_date_max_booking_days', 90 );
		update_option( 'wc_collection_date_category_rules', array() );

		// Set WordPress timezone for consistent testing.
		update_option( 'timezone_string', 'UTC' );
		update_option( 'date_format', 'Y-m-d' );
		update_option( 'time_format', 'H:i:s' );
	}

	/**
	 * Set up mock WooCommerce environment.
	 */
	protected function setup_mock_woocommerce() {
		// Ensure WooCommerce functions exist.
		if ( ! function_exists( 'wc_get_product' ) ) {
			// Mock basic WooCommerce functions.
			require_once dirname( __DIR__ ) . '/tests/mocks/woocommerce-mocks.php';
		}

		// Mock WooCommerce cart if it doesn't exist.
		if ( ! isset( WC()->cart ) ) {
			WC()->cart = $this->createMock( 'WC_Cart' );
		}
	}

	/**
	 * Create a test product with collection date settings.
	 *
	 * @param array $args Product arguments.
	 * @return WC_Product|int Product object or ID.
	 */
	protected function create_test_product( $args = array() ) {
		$defaults = array(
			'name'          => 'Test Product',
			'status'        => 'publish',
			'price'         => '10.00',
			'regular_price' => '10.00',
			'sku'           => 'TEST-PRODUCT-' . uniqid(),
			'manage_stock'  => false,
			'tax_status'    => 'taxable',
		);

		$args = wp_parse_args( $args, $defaults );

		return $this->factory->product->create( $args );
	}

	/**
	 * Create a test product category.
	 *
	 * @param array $args Category arguments.
	 * @return WP_Term|int Category object or ID.
	 */
	protected function create_test_category( $args = array() ) {
		$defaults = array(
			'taxonomy' => 'product_cat',
			'name'     => 'Test Category',
			'slug'     => 'test-category-' . uniqid(),
		);

		$args = wp_parse_args( $args, $defaults );

		return $this->factory->term->create( $args );
	}

	/**
	 * Create a test order with collection date.
	 *
	 * @param array $args Order arguments.
	 * @return WC_Order|int Order object or ID.
	 */
	protected function create_test_order( $args = array() ) {
		$defaults = array(
			'status'        => 'processing',
			'customer_id'   => self::$user_id,
			'total'         => '10.00',
			'payment_method' => 'cod',
		);

		$args = wp_parse_args( $args, $defaults );

		return $this->factory->order->create( $args );
	}

	/**
	 * Create a test date exclusion.
	 *
	 * @param string $date   Exclusion date in Y-m-d format.
	 * @param string $reason Exclusion reason.
	 * @return int Exclusion ID.
	 */
	protected function create_test_exclusion( $date, $reason = 'Test exclusion' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		$wpdb->insert(
			$table_name,
			array(
				'exclusion_date' => $date,
				'reason'         => $reason,
			),
			array( '%s', '%s' )
		);

		return $wpdb->insert_id;
	}

	/**
	 * Set up category rule for testing.
	 *
	 * @param int   $category_id Category ID.
	 * @param array $settings    Rule settings.
	 * @return bool Success status.
	 */
	protected function setup_category_rule( $category_id, $settings = array() ) {
		$defaults = array(
			'lead_time'        => 3,
			'lead_time_type'   => 'calendar',
			'cutoff_time'      => '',
			'working_days'     => array( '1', '2', '3', '4', '5' ),
			'collection_days'  => array( '0', '1', '2', '3', '4', '5', '6' ),
		);

		$settings = wp_parse_args( $settings, $defaults );

		$rules = get_option( 'wc_collection_date_category_rules', array() );
		$rules[ $category_id ] = $settings;

		return update_option( 'wc_collection_date_category_rules', $rules );
	}

	/**
	 * Get a DateTime object for testing.
	 *
	 * @param string $date Date string or relative format.
	 * @return DateTime
	 */
	protected function get_test_date( $date = 'now' ) {
		return new DateTime( $date, new DateTimeZone( 'UTC' ) );
	}

	/**
	 * Assert that two date arrays are equal, ignoring order.
	 *
	 * @param array $expected Expected dates.
	 * @param array $actual   Actual dates.
	 * @param string $message Optional message.
	 */
	protected function assertDateArraysEqual( $expected, $actual, $message = '' ) {
		sort( $expected );
		sort( $actual );

		$this->assertEquals( $expected, $actual, $message );
	}

	/**
	 * Assert that a date is within a range.
	 *
	 * @param string $date     Date to check.
	 * @param string $start_date Start of range.
	 * @param string $end_date   End of range.
	 * @param string $message Optional message.
	 */
	protected function assertDateInRange( $date, $start_date, $end_date, $message = '' ) {
		$check = strtotime( $date );
		$start  = strtotime( $start_date );
		$end    = strtotime( $end_date );

		$this->assertGreaterThanOrEqual( $start, $check, $message ?: "Date {$date} should be after {$start_date}" );
		$this->assertLessThanOrEqual( $end, $check, $message ?: "Date {$date} should be before {$end_date}" );
	}

	/**
	 * Assert that a date is available for collection.
	 *
	 * @param string $date   Date to check.
	 * @param array  $dates  Available dates array.
	 * @param string $message Optional message.
	 */
	protected function assertDateAvailable( $date, $dates, $message = '' ) {
		$this->assertContains( $date, $dates, $message ?: "Date {$date} should be available for collection" );
	}

	/**
	 * Assert that a date is NOT available for collection.
	 *
	 * @param string $date   Date to check.
	 * @param array  $dates  Available dates array.
	 * @param string $message Optional message.
	 */
	protected function assertDateNotAvailable( $date, $dates, $message = '' ) {
		$this->assertNotContains( $date, $dates, $message ?: "Date {$date} should not be available for collection" );
	}

	/**
	 * Mock WordPress time functions for testing.
	 *
	 * @param string $current_time Current time string.
	 */
	protected function mock_current_time( $current_time = '2024-01-15 10:00:00' ) {
		// Mock current_time function.
		if ( ! function_exists( 'tests_redefine_current_time' ) ) {
			function tests_redefine_current_time( $current_time ) {
				// This would need to be implemented with a proper mocking framework
				// For now, we'll use the real current_time
			}
		}
	}

	/**
	 * Get plugin setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed Setting value.
	 */
	protected function get_plugin_setting( $key, $default = null ) {
		$full_key = 'wc_collection_date_' . $key;
		return get_option( $full_key, $default );
	}

	/**
	 * Set plugin setting value.
	 *
	 * @param string $key   Setting key.
	 * @param mixed  $value Setting value.
	 * @return bool Success status.
	 */
	protected function set_plugin_setting( $key, $value ) {
		$full_key = 'wc_collection_date_' . $key;
		return update_option( $full_key, $value );
	}

	/**
	 * Create a mock WP_REST_Request.
	 *
	 * @param array $params Request parameters.
	 * @return WP_REST_Request
	 */
	protected function create_rest_request( $params = array() ) {
		$request = new WP_REST_Request();
		foreach ( $params as $key => $value ) {
			$request->set_param( $key, $value );
		}
		return $request;
	}

	/**
	 * Assert REST API response structure.
	 *
	 * @param WP_REST_Response $response Response object.
	 * @param array            $expected  Expected structure.
	 */
	protected function assert_rest_response_structure( $response, $expected ) {
		$data = $response->get_data();

		$this->assertTrue( $response->get_status() >= 200 && $response->get_status() < 300 );
		$this->assertTrue( $data['success'] ?? false );

		foreach ( $expected as $key => $value ) {
			if ( is_array( $value ) ) {
				$this->assertArrayHasKey( $key, $data );
				$this->assert_rest_response_structure(
					$this->create_rest_request( $data[ $key ] ),
					$value
				);
			} else {
				$this->assertArrayHasKey( $key, $data );
				if ( $value !== null ) {
					$this->assertEquals( $value, $data[ $key ] );
				}
			}
		}
	}
}