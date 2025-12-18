<?php
/**
 * Unit Tests for WC_Collection_Date_REST_API Class
 *
 * @package WC_Collection_Date\Tests\Unit
 */

class WC_Collection_Date_Test_REST_API extends WC_Collection_Date_Test_Base {

	/**
	 * REST API instance.
	 *
	 * @var WC_Collection_Date_REST_API
	 */
	protected $rest_api;

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->rest_api = new WC_Collection_Date_REST_API();
	}

	/**
	 * Test REST API instantiation.
	 */
	public function test_rest_api_instantiation() {
		$this->assertInstanceOf( 'WC_Collection_Date_REST_API', $this->rest_api );
	}

	/**
	 * Test namespace property.
	 */
	public function test_namespace_property() {
		$reflection = new ReflectionClass( $this->rest_api );
		$property = $reflection->getProperty( 'namespace' );
		$property->setAccessible( true );

		$this->assertEquals( 'wc-collection-date/v1', $property->getValue( $this->rest_api ) );
	}

	/**
	 * Test that routes are registered.
	 */
	public function test_routes_registered() {
		$routes = rest_get_server()->get_routes();
		$namespace = 'wc-collection-date/v1';

		$this->assertArrayHasKey( $namespace, $routes );
		$this->assertArrayHasKey( $namespace . '/dates', $routes );
		$this->assertArrayHasKey( $namespace . '/dates/check', $routes );
		$this->assertArrayHasKey( $namespace . '/dates/range', $routes );
		$this->assertArrayHasKey( $namespace . '/settings', $routes );
	}

	/**
	 * Test get_available_dates endpoint.
	 */
	public function test_get_available_dates() {
		$request = new WP_REST_Request( 'GET', '/wc-collection-date/v1/dates' );
		$request->set_param( 'limit', 10 );

		$response = $this->rest_api->get_available_dates( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertIsArray( $data );
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'dates', $data );
		$this->assertArrayHasKey( 'count', $data );
		$this->assertEquals( 10, $data['count'] );
		$this->assertCount( 10, $data['dates'] );
	}

	/**
	 * Test get_available_dates with default limit.
	 */
	public function test_get_available_dates_default_limit() {
		$request = new WP_REST_Request( 'GET', '/wc-collection-date/v1/dates' );

		$response = $this->rest_api->get_available_dates( $request );

		$data = $response->get_data();
		$this->assertEquals( 90, $data['count'] ); // Default limit
	}

	/**
	 * Test get_available_dates with cart products.
	 */
	public function test_get_available_dates_with_cart() {
		// Mock WooCommerce cart with products.
		$this->setup_mock_woocommerce();

		$product_id = $this->create_test_product();
		$category_id = $this->create_test_category();
		wp_set_object_terms( $product_id, array( $category_id ), 'product_cat' );

		// Set up category rule with longer lead time.
		$this->setup_category_rule( $category_id, array( 'lead_time' => 5 ) );

		// Mock cart with product.
		$mock_cart = $this->createMock( 'WC_Cart' );
		$mock_cart->method( 'get_cart' )->willReturn( array(
			array( 'product_id' => $product_id ),
		) );

		WC()->cart = $mock_cart;

		$request = new WP_REST_Request( 'GET', '/wc-collection-date/v1/dates' );
		$request->set_param( 'limit', 10 );

		$response = $this->rest_api->get_available_dates( $request );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertCount( 10, $data['dates'] );

		// First date should respect the category lead time.
		$first_date = new DateTime( $data['dates'][0] );
		$expected_min = new DateTime( 'now' );
		$expected_min->modify( '+6 days' ); // lead_time(5) + 1

		$this->assertGreaterThanOrEqual( $expected_min, $first_date );
	}

	/**
	 * Test check_date_availability endpoint with available date.
	 */
	public function test_check_date_availability_available() {
		$request = new WP_REST_Request( 'POST', '/wc-collection-date/v1/dates/check' );
		$request->set_param( 'date', '2024-12-25' );

		// Mock calculator to return true.
		$calculator = $this->createMock( 'WC_Collection_Date_Calculator' );
		$calculator->method( 'is_date_available' )->willReturn( true );

		$reflection = new ReflectionClass( $this->rest_api );
		$property = $reflection->getProperty( 'calculator' );
		$property->setAccessible( true );
		$property->setValue( $this->rest_api, $calculator );

		$response = $this->rest_api->check_date_availability( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( '2024-12-25', $data['date'] );
		$this->assertTrue( $data['available'] );
		$this->assertEquals( 'Date is available', $data['message'] );
	}

	/**
	 * Test check_date_availability endpoint with unavailable date.
	 */
	public function test_check_date_availability_unavailable() {
		$request = new WP_REST_Request( 'POST', '/wc-collection-date/v1/dates/check' );
		$request->set_param( 'date', '2024-12-25' );

		// Mock calculator to return false.
		$calculator = $this->createMock( 'WC_Collection_Date_Calculator' );
		$calculator->method( 'is_date_available' )->willReturn( false );

		$reflection = new ReflectionClass( $this->rest_api );
		$property = $reflection->getProperty( 'calculator' );
		$property->setAccessible( true );
		$property->setValue( $this->rest_api, $calculator );

		$response = $this->rest_api->check_date_availability( $request );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( '2024-12-25', $data['date'] );
		$this->assertFalse( $data['available'] );
		$this->assertEquals( 'Date is not available', $data['message'] );
	}

	/**
	 * Test check_date_availability with missing date parameter.
	 */
	public function test_check_date_availability_missing_date() {
		$request = new WP_REST_Request( 'POST', '/wc-collection-date/v1/dates/check' );
		// Missing 'date' parameter.

		$response = $this->rest_api->check_date_availability( $request );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertNull( $data['date'] );
		$this->assertFalse( $data['available'] );
	}

	/**
	 * Test get_date_range endpoint.
	 */
	public function test_get_date_range() {
		$this->set_plugin_setting( 'lead_time', 3 );
		$this->set_plugin_setting( 'max_booking_days', 60 );

		$request = new WP_REST_Request( 'GET', '/wc-collection-date/v1/dates/range' );

		$response = $this->rest_api->get_date_range( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'min_date', $data );
		$this->assertArrayHasKey( 'max_date', $data );

		// Validate date format.
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $data['min_date'] );
		$this->assertMatchesRegularExpression( '/^\d{4}-\d{2}-\d{2}$/', $data['max_date'] );

		// Min date should be lead_time + 1 days from now.
		$expected_min = new DateTime( 'now' );
		$expected_min->modify( '+4 days' );
		$this->assertEquals( $expected_min->format( 'Y-m-d' ), $data['min_date'] );

		// Max date should be max_booking_days from now.
		$expected_max = new DateTime( 'now' );
		$expected_max->modify( '+60 days' );
		$this->assertEquals( $expected_max->format( 'Y-m-d' ), $data['max_date'] );
	}

	/**
	 * Test get_settings endpoint.
	 */
	public function test_get_settings() {
		$this->set_plugin_setting( 'lead_time', 5 );
		$this->set_plugin_setting( 'max_booking_days', 45 );
		$this->set_plugin_setting( 'working_days', array( '1', '2', '3', '4', '5' ) );
		update_option( 'date_format', 'd/m/Y' );

		$request = new WP_REST_Request( 'GET', '/wc-collection-date/v1/settings' );

		$response = $this->rest_api->get_settings( $request );

		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$this->assertEquals( 200, $response->get_status() );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertArrayHasKey( 'settings', $data );

		$settings = $data['settings'];
		$this->assertEquals( 5, $settings['lead_time'] );
		$this->assertEquals( 45, $settings['max_booking_days'] );
		$this->assertEquals( array( 1, 2, 3, 4, 5 ), $settings['working_days'] );
		$this->assertEquals( 'd/m/Y', $settings['date_format'] );
	}

	/**
	 * Test get_settings with invalid working days.
	 */
	public function test_get_settings_invalid_working_days() {
		// Set working_days to non-array value.
		update_option( 'wc_collection_date_working_days', 'invalid' );

		$request = new WP_REST_Request( 'GET', '/wc-collection-date/v1/settings' );

		$response = $this->rest_api->get_settings( $request );

		$data = $response->get_data();
		$this->assertTrue( $data['success'] );
		$this->assertEquals( array(), $data['settings']['working_days'] );
	}

	/**
	 * Test get_cart_product_with_longest_lead_time with empty cart.
	 */
	public function test_get_cart_product_with_longest_lead_time_empty_cart() {
		$this->setup_mock_woocommerce();

		// Mock empty cart.
		$mock_cart = $this->createMock( 'WC_Cart' );
		$mock_cart->method( 'get_cart' )->willReturn( array() );

		WC()->cart = $mock_cart;

		// Use reflection to test protected method.
		$reflection = new ReflectionClass( $this->rest_api );
		$method = $reflection->getMethod( 'get_cart_product_with_longest_lead_time' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->rest_api );

		$this->assertNull( $result );
	}

	/**
	 * Test get_cart_product_with_longest_lead_time with products.
	 */
	public function test_get_cart_product_with_longest_lead_time_with_products() {
		$this->setup_mock_woocommerce();

		// Create products and categories.
		$category1_id = $this->create_test_category();
		$category2_id = $this->create_test_category();
		$product1_id = $this->create_test_product();
		$product2_id = $this->create_test_product();

		// Assign products to categories.
		wp_set_object_terms( $product1_id, array( $category1_id ), 'product_cat' );
		wp_set_object_terms( $product2_id, array( $category2_id ), 'product_cat' );

		// Set up category rules.
		$this->setup_category_rule( $category1_id, array( 'lead_time' => 3 ) );
		$this->setup_category_rule( $category2_id, array( 'lead_time' => 7 ) ); // Longest

		// Mock cart with products.
		$mock_cart = $this->createMock( 'WC_Cart' );
		$mock_cart->method( 'get_cart' )->willReturn( array(
			array( 'product_id' => $product1_id ),
			array( 'product_id' => $product2_id ),
		) );

		WC()->cart = $mock_cart;

		// Use reflection to test protected method.
		$reflection = new ReflectionClass( $this->rest_api );
		$method = $reflection->getMethod( 'get_cart_product_with_longest_lead_time' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->rest_api );

		// Should return product with longest lead time.
		$this->assertEquals( $product2_id, $result );
	}

	/**
	 * Test get_cart_product_with_longest_lead_time with invalid product ID.
	 */
	public function test_get_cart_product_with_longest_lead_time_invalid_product() {
		$this->setup_mock_woocommerce();

		// Mock cart with invalid product ID.
		$mock_cart = $this->createMock( 'WC_Cart' );
		$mock_cart->method( 'get_cart' )->willReturn( array(
			array( 'product_id' => 99999 ), // Invalid product ID
		) );

		WC()->cart = $mock_cart;

		// Use reflection to test protected method.
		$reflection = new ReflectionClass( $this->rest_api );
		$method = $reflection->getMethod( 'get_cart_product_with_longest_lead_time' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->rest_api );

		$this->assertNull( $result );
	}

	/**
	 * Test get_cart_product_with_longest_lead_time without WooCommerce.
	 */
	public function test_get_cart_product_with_longest_lead_time_no_woocommerce() {
		// Mock that WooCommerce is not available.
		if ( ! function_exists( 'WC' ) ) {
			function WC() {
				return null;
			}
		}

		// Use reflection to test protected method.
		$reflection = new ReflectionClass( $this->rest_api );
		$method = $reflection->getMethod( 'get_cart_product_with_longest_lead_time' );
		$method->setAccessible( true );

		$result = $method->invoke( $this->rest_api );

		$this->assertNull( $result );
	}

	/**
	 * Test route permissions.
	 */
	public function test_route_permissions() {
		$server = rest_get_server();
		$routes = $server->get_routes();
		$namespace = 'wc-collection-date/v1';

		// All our routes should allow public access (permission_callback returns true).
		$test_routes = array(
			$namespace . '/dates',
			$namespace . '/dates/check',
			$namespace . '/dates/range',
			$namespace . '/settings',
		);

		foreach ( $test_routes as $route ) {
			if ( isset( $routes[ $route ] ) ) {
				$route_data = $routes[ $route ][0]; // GET method
				if ( isset( $route_data['permission_callback'] ) ) {
					$permission = $route_data['permission_callback']();
					$this->assertTrue( $permission, "Route {$route} should be publicly accessible" );
				}
			}
		}
	}

	/**
	 * Test request validation and sanitization.
	 */
	public function test_request_validation() {
		// Test get_available_dates with invalid limit parameter.
		$request = new WP_REST_Request( 'GET', '/wc-collection-date/v1/dates' );
		$request->set_param( 'limit', 'invalid' ); // Should be sanitized to 0.

		$response = $this->rest_api->get_available_dates( $request );

		// Should handle invalid input gracefully.
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		$this->assertTrue( $data['success'] );

		// Test check_date_availability with malicious input.
		$request = new WP_REST_Request( 'POST', '/wc-collection-date/v1/dates/check' );
		$request->set_param( 'date', '<script>alert("xss")</script>' );

		$response = $this->rest_api->check_date_availability( $request );

		// Should sanitize input.
		$this->assertInstanceOf( 'WP_REST_Response', $response );
		$data = $response->get_data();
		$this->assertNotContains( '<script>', $data['date'] );
	}

	/**
	 * Test response structure consistency.
	 */
	public function test_response_structure_consistency() {
		// Test all endpoints return consistent structure.
		$endpoints = array(
			'get_available_dates' => array(),
			'get_date_range' => array(),
			'get_settings' => array(),
		);

		foreach ( $endpoints as $method => $params ) {
			$request = new WP_REST_Request( 'GET', "/wc-collection-date/v1/{$params['path'] ?? 'dates'}" );

			if ( isset( $params['method'] ) ) {
				$request->set_method( $params['method'] );
			}

			foreach ( $params['params'] ?? array() as $key => $value ) {
				$request->set_param( $key, $value );
			}

			$response = $this->rest_api->$method( $request );

			$this->assert_rest_response_structure(
				$response,
				array( 'success' => null )
			);
		}
	}
}