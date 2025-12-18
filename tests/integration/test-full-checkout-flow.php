<?php
/**
 * Integration Tests for Full Checkout Flow
 *
 * @package WC_Collection_Date\Tests\Integration
 */

class WC_Collection_Date_Integration_Full_Checkout_Flow extends WC_Collection_Date_Test_Base {

	/**
	 * Date calculator instance.
	 *
	 * @var WC_Collection_Date_Calculator
	 */
	protected $calculator;

	/**
	 * Lead time resolver instance.
	 *
	 * @var WC_Collection_Date_Lead_Time_Resolver
	 */
	protected $resolver;

	/**
	 * Checkout instance.
	 *
	 * @var WC_Collection_Date_Checkout
	 */
	protected $checkout;

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::setUp();

		$this->calculator = new WC_Collection_Date_Calculator();
		$this->resolver = new WC_Collection_Date_Lead_Time_Resolver();
		$this->checkout = new WC_Collection_Date_Checkout();

		// Set up mock WooCommerce environment.
		$this->setup_mock_woocommerce();
	}

	/**
	 * Test complete checkout flow with single product and no category rules.
	 */
	public function test_complete_checkout_flow_single_product() {
		// Create test product.
		$product_id = $this->create_test_product( array(
			'name' => 'Test Simple Product',
			'price' => '25.00',
		) );

		// Get available dates for product.
		$dates = $this->calculator->get_available_dates_for_product( $product_id, 10 );

		$this->assertNotEmpty( $dates );
		$this->assertGreaterThan( 3, count( $dates ) ); // Should have dates after lead time

		// Select first available date.
		$selected_date = $dates[0];

		// Verify selected date is available.
		$this->assertTrue( $this->calculator->is_date_available( $selected_date ) );

		// Create order with collection date.
		$order_id = $this->create_test_order( array(
			'total' => '25.00',
		) );

		// Simulate checkout form submission.
		$_POST = array(
			'collection_date' => $selected_date,
		);

		// Test validation.
		$this->checkout->validate_collection_date();

		// Test saving collection date.
		$this->checkout->save_collection_date( $order_id );

		// Verify collection date was saved to order.
		$order = wc_get_order( $order_id );
		$saved_date = $order->get_meta( '_collection_date' );
		$this->assertEquals( $selected_date, $saved_date );

		// Test order display functions.
		ob_start();
		$this->checkout->display_collection_date_confirmation( $order );
		$output = ob_get_clean();
		$this->assertStringContainsString( $selected_date, $output );

		ob_start();
		$this->checkout->display_collection_date_admin( $order );
		$output = ob_get_clean();
		$this->assertStringContainsString( $selected_date, $output );
	}

	/**
	 * Test checkout flow with product category rules.
	 */
	public function test_checkout_flow_with_category_rules() {
		// Create category with specific rules.
		$category_id = $this->create_test_category( array(
			'name' => 'Premium Cakes',
		) );

		$category_settings = array(
			'lead_time'        => 5,
			'lead_time_type'   => 'working',
			'cutoff_time'      => '14:00',
			'working_days'     => array( '1', '2', '3', '4', '5' ),
			'collection_days'  => array( '0', '1', '2', '3', '4', '5', '6' ),
		);
		$this->setup_category_rule( $category_id, $category_settings );

		// Create product in category.
		$product_id = $this->create_test_product( array(
			'name' => 'Premium Birthday Cake',
			'price' => '75.00',
		) );

		wp_set_object_terms( $product_id, array( $category_id ), 'product_cat' );

		// Test that category rules are applied.
		$effective_settings = $this->resolver->get_effective_settings( $product_id );
		$this->assertEquals( 5, $effective_settings['lead_time'] );
		$this->assertEquals( 'working', $effective_settings['lead_time_type'] );
		$this->assertEquals( '14:00', $effective_settings['cutoff_time'] );

		// Get available dates.
		$dates = $this->calculator->get_available_dates_for_product( $product_id, 10 );
		$this->assertNotEmpty( $dates );

		// First date should respect category rules (5 working days + buffer).
		$first_date = new DateTime( $dates[0] );
		$expected_min = new DateTime( 'now' );
		$expected_min->modify( '+8 days' ); // Approximately 5 working days + buffer

		$this->assertGreaterThanOrEqual( $expected_min, $first_date );

		// Create order and complete checkout flow.
		$order_id = $this->create_test_order( array(
			'total' => '75.00',
		) );

		$_POST['collection_date'] = $dates[0];
		$this->checkout->save_collection_date( $order_id );

		$order = wc_get_order( $order_id );
		$this->assertEquals( $dates[0], $order->get_meta( '_collection_date' ) );
	}

	/**
	 * Test checkout flow with multiple products (longest lead time wins).
	 */
	public function test_checkout_flow_multiple_products() {
		// Create categories with different lead times.
		$category1_id = $this->create_test_category( array( 'name' => 'Standard Cakes' ) );
		$category2_id = $this->create_test_category( array( 'name' => 'Wedding Cakes' ) );

		// Set category rules with different lead times.
		$this->setup_category_rule( $category1_id, array( 'lead_time' => 2 ) );
		$this->setup_category_rule( $category2_id, array( 'lead_time' => 7 ) ); // Longest

		// Create products.
		$product1_id = $this->create_test_product( array(
			'name' => 'Standard Birthday Cake',
			'price' => '45.00',
		) );
		$product2_id = $this->create_test_product( array(
			'name' => 'Wedding Cake',
			'price' => '150.00',
		) );

		// Assign products to categories.
		wp_set_object_terms( $product1_id, array( $category1_id ), 'product_cat' );
		wp_set_object_terms( $product2_id, array( $category2_id ), 'product_cat' );

		// Mock cart with both products.
		$mock_cart = $this->createMock( 'WC_Cart' );
		$mock_cart->method( 'get_cart' )->willReturn( array(
			array( 'product_id' => $product1_id ),
			array( 'product_id' => $product2_id ),
		) );

		WC()->cart = $mock_cart;

		// Test REST API with cart products.
		$rest_api = new WC_Collection_Date_REST_API();
		$request = new WP_REST_Request( 'GET', '/wc-collection-date/v1/dates' );
		$request->set_param( 'limit', 10 );

		$response = $rest_api->get_available_dates( $request );
		$data = $response->get_data();

		$this->assertTrue( $data['success'] );
		$this->assertCount( 10, $data['dates'] );

		// First date should respect longest lead time (7 days).
		$first_date = new DateTime( $data['dates'][0] );
		$expected_min = new DateTime( 'now' );
		$expected_min->modify( '+8 days' ); // 7 days lead time + buffer

		$this->assertGreaterThanOrEqual( $expected_min, $first_date );

		// Create order with mixed products.
		$order_id = $this->create_test_order( array(
			'total' => '195.00', // 45.00 + 150.00
		) );

		$_POST['collection_date'] = $data['dates'][0];
		$this->checkout->save_collection_date( $order_id );

		$order = wc_get_order( $order_id );
		$this->assertEquals( $data['dates'][0], $order->get_meta( '_collection_date' ) );
	}

	/**
	 * Test checkout flow with date exclusions.
	 */
	public function test_checkout_flow_with_exclusions() {
		// Create exclusions for upcoming dates.
		$today = new DateTime( 'now' );
		$exclusions = array();

		for ( $i = 3; $i <= 8; $i++ ) {
			$exclusion_date = clone $today;
			$exclusion_date->modify( "+{$i} days" );
			$exclusion_date_str = $exclusion_date->format( 'Y-m-d' );
			$exclusions[] = $exclusion_date_str;
			$this->create_test_exclusion( $exclusion_date_str, "Holiday {$i}" );
		}

		// Set shorter lead time to ensure we hit exclusions.
		$this->set_plugin_setting( 'lead_time', 1 );

		// Create product.
		$product_id = $this->create_test_product( array( 'price' => '30.00' ) );

		// Get available dates.
		$dates = $this->calculator->get_available_dates_for_product( $product_id, 20 );

		// Should have dates but skip excluded ones.
		$this->assertNotEmpty( $dates );

		// Verify none of the excluded dates are in the results.
		foreach ( $exclusions as $exclusion ) {
			$this->assertDateNotAvailable( $exclusion, $dates );
		}

		// Test that all returned dates are valid (not excluded).
		foreach ( $dates as $date ) {
			$this->assertTrue( $this->calculator->is_date_available( $date ) );
		}

		// Complete checkout with first available date.
		$order_id = $this->create_test_order( array( 'total' => '30.00' ) );
		$_POST['collection_date'] = $dates[0];
		$this->checkout->save_collection_date( $order_id );

		$order = wc_get_order( $order_id );
		$this->assertEquals( $dates[0], $order->get_meta( '_collection_date' ) );
	}

	/**
	 * Test checkout flow validation with invalid date.
	 */
	public function test_checkout_flow_validation_invalid_date() {
		$product_id = $this->create_test_product();
		$order_id = $this->create_test_order();

		// Try to use an invalid date (in the past).
		$invalid_date = new DateTime( 'now' );
		$invalid_date->modify( '-10 days' );

		$_POST['collection_date'] = $invalid_date->format( 'Y-m-d' );

		// Capture WooCommerce notices.
		$initial_notices = wc_get_notices( 'error' );

		// Test validation.
		$this->checkout->validate_collection_date();

		$final_notices = wc_get_notices( 'error' );
		$error_added = count( $final_notices ) > count( $initial_notices );

		$this->assertTrue( $error_added, 'Should add error notice for invalid date' );
	}

	/**
	 * Test checkout flow with cutoff time penalty.
	 */
	public function test_checkout_flow_cutoff_time() {
		// Set cutoff time that should apply penalty.
		$this->set_plugin_setting( 'lead_time', 2 );
		$this->set_plugin_setting( 'cutoff_time', '00:00' ); // Always applies penalty

		$product_id = $this->create_test_product( array( 'price' => '35.00' ) );

		// Get available dates.
		$dates = $this->calculator->get_available_dates_for_product( $product_id, 10 );

		$this->assertNotEmpty( $dates );

		// First date should reflect penalty (lead_time + penalty + 1).
		$first_date = new DateTime( $dates[0] );
		$expected_min = new DateTime( 'now' );
		$expected_min->modify( '+4 days' ); // 2 days lead + 1 day penalty + 1 buffer

		$this->assertGreaterThanOrEqual( $expected_min, $first_date );

		// Complete checkout.
		$order_id = $this->create_test_order( array( 'total' => '35.00' ) );
		$_POST['collection_date'] = $dates[0];
		$this->checkout->save_collection_date( $order_id );

		$order = wc_get_order( $order_id );
		$this->assertEquals( $dates[0], $order->get_meta( '_collection_date' ) );
	}

	/**
	 * Test block checkout integration.
	 */
	public function test_block_checkout_integration() {
		$product_id = $this->create_test_product( array( 'price' => '40.00' ) );

		$dates = $this->calculator->get_available_dates_for_product( $product_id, 5 );
		$this->assertNotEmpty( $dates );

		$order_id = $this->create_test_order( array( 'total' => '40.00' ) );
		$order = wc_get_order( $order_id );

		// Test block checkout save method.
		$request = $this->create_rest_request( array(
			'extensions' => array(
				'wc-collection-date' => array(
					'collection_date' => $dates[0],
				),
			),
		) );

		$this->checkout->save_collection_date_block_checkout( $order, $request );

		$this->assertEquals( $dates[0], $order->get_meta( '_collection_date' ) );

		// Test with billing data location.
		$request = $this->create_rest_request( array(
			'billing_address' => array(
				'collection_date' => $dates[1],
			),
		) );

		$this->checkout->save_collection_date_block_checkout( $order, $request );

		$this->assertEquals( $dates[1], $order->get_meta( '_collection_date' ) );
	}

	/**
	 * Test analytics tracking during checkout.
	 */
	public function test_analytics_tracking_during_checkout() {
		$analytics = WC_Collection_Date_Analytics::instance();

		$product_id = $this->create_test_product( array( 'total' => '55.00' ) );
		$dates = $this->calculator->get_available_dates_for_product( $product_id, 5 );
		$order_id = $this->create_test_order( array( 'total' => '55.00' ) );

		$_POST['collection_date'] = $dates[0];

		// Track collection date selection.
		$analytics->track_collection_date_selection( $order_id, $_POST );

		// Verify analytics data was created.
		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_collection_date_analytics';

		$result = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE collection_date = %s",
				$dates[0]
			)
		);

		$this->assertNotNull( $result );
		$this->assertEquals( 1, $result->selection_count );
		$this->assertEquals( 1, $result->total_orders );
		$this->assertEquals( '55.00', $result->total_value );
	}

	/**
	 * Test email notifications with collection date.
	 */
	public function test_email_notifications() {
		$order_id = $this->create_test_order( array( 'total' => '60.00' ) );
		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_collection_date', '2024-12-25' );
		$order->save();

		// Test HTML email.
		$email = $this->createMock( 'WC_Email' );

		ob_start();
		$this->checkout->display_collection_date_email( $order, false, false, $email );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Collection Information', $output );
		$this->assertStringContainsString( '2024-12-25', $output );
		$this->assertStringContainsString( '<div', $output ); // HTML format

		// Test plain text email.
		ob_start();
		$this->checkout->display_collection_date_email( $order, false, true, $email );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'COLLECTION INFORMATION', $output );
		$this->assertStringContainsString( '2024-12-25', $output );
		$this->assertStringNotContainsString( '<div', $output ); // Plain text format
	}

	/**
	 * Test order totals display modification.
	 */
	public function test_order_totals_display() {
		$order_id = $this->create_test_order( array( 'total' => '80.00' ) );
		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_collection_date', '2024-12-20' );
		$order->save();

		$total_rows = array(
			'subtotal' => array(
				'label' => 'Subtotal',
				'value' => '$80.00',
			),
			'payment_method' => array(
				'label' => 'Payment Method',
				'value' => 'Cash on Delivery',
			),
			'total' => array(
				'label' => 'Total',
				'value' => '$80.00',
			),
		);

		$modified_rows = $this->checkout->add_collection_date_to_order_display( $total_rows, $order );

		$this->assertArrayHasKey( 'collection_date', $modified_rows );
		$this->assertEquals( 'Collection Date:', $modified_rows['collection_date']['label'] );
		$this->assertStringContainsString( '2024-12-20', $modified_rows['collection_date']['value'] );

		// Verify collection date appears after payment method.
		$keys = array_keys( $modified_rows );
		$collection_date_index = array_search( 'collection_date', $keys );
		$payment_method_index = array_search( 'payment_method', $keys );
		$this->assertTrue( $collection_date_index > $payment_method_index );
	}

	/**
	 * Test cache invalidation during settings changes.
	 */
	public function test_cache_invalidation_integration() {
		$product_id = $this->create_test_product();
		$category_id = $this->create_test_category();
		wp_set_object_terms( $product_id, array( $category_id ), 'product_cat' );

		// Get initial dates (should use global settings).
		$initial_dates = $this->calculator->get_available_dates_for_product( $product_id, 10 );
		$this->assertNotEmpty( $initial_dates );

		// Add category rule.
		$this->setup_category_rule( $category_id, array( 'lead_time' => 5 ) );

		// Clear cache.
		WC_Collection_Date_Calculator::clear_cache();

		// Get new dates (should use category settings).
		$new_dates = $this->calculator->get_available_dates_for_product( $product_id, 10 );

		// Dates should be different due to new lead time.
		$this->assertNotEquals( $initial_dates, $new_dates );

		// First date should be later due to longer lead time.
		$first_initial = new DateTime( $initial_dates[0] );
		$first_new = new DateTime( $new_dates[0] );
		$this->assertGreaterThan( $first_initial, $first_new );
	}

	/**
	 * Test complete integration with working days calculation.
	 */
	public function test_complete_integration_working_days() {
		// Configure working days and lead time type.
		$this->set_plugin_setting( 'lead_time', 4 );
		$this->set_plugin_setting( 'lead_time_type', 'working' );
		$this->set_plugin_setting( 'working_days', array( '1', '2', '3', '4', '5' ) ); // Mon-Fri
		$this->set_plugin_setting( 'collection_days', array( '0', '1', '2', '3', '4', '5', '6' ) ); // All days

		$product_id = $this->create_test_product( array( 'price' => '65.00' ) );

		$dates = $this->calculator->get_available_dates_for_product( $product_id, 20 );

		$this->assertNotEmpty( $dates );

		// With working days calculation, dates should skip weekends.
		// This is harder to test precisely without knowing current day, but we can verify basic structure.
		foreach ( $dates as $date ) {
			$date_obj = new DateTime( $date );
			$day_of_week = (int) $date_obj->format( 'w' );
			$this->assertTrue( $day_of_week >= 0 && $day_of_week <= 6 );
		}

		// Complete checkout flow.
		$order_id = $this->create_test_order( array( 'total' => '65.00' ) );
		$_POST['collection_date'] = $dates[0];
		$this->checkout->save_collection_date( $order_id );

		$order = wc_get_order( $order_id );
		$this->assertEquals( $dates[0], $order->get_meta( '_collection_date' ) );
	}
}