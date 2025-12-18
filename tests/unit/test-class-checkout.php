<?php
/**
 * Unit Tests for WC_Collection_Date_Checkout Class
 *
 * @package WC_Collection_Date\Tests\Unit
 */

class WC_Collection_Date_Test_Checkout extends WC_Collection_Date_Test_Base {

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
		$this->checkout = new WC_Collection_Date_Checkout();
	}

	/**
	 * Test checkout instantiation.
	 */
	public function test_checkout_instantiation() {
		$this->assertInstanceOf( 'WC_Collection_Date_Checkout', $this->checkout );
	}

	/**
	 * Test that hooks are properly registered.
	 */
	public function test_hooks_registered() {
		$hooks = array(
			'woocommerce_after_order_notes',
			'woocommerce_review_order_before_payment',
			'woocommerce_checkout_billing',
			'woocommerce_checkout_process',
			'woocommerce_checkout_update_order_meta',
			'woocommerce_store_api_checkout_update_order_from_request',
			'woocommerce_store_api_checkout_order_processed',
			'woocommerce_order_details_after_order_table',
			'woocommerce_admin_order_data_after_billing_address',
			'woocommerce_email_after_order_table',
			'woocommerce_get_order_item_totals',
			'wp_enqueue_scripts',
		);

		foreach ( $hooks as $hook ) {
			$this->assertTrue( has_filter( $hook ) !== false, "Hook {$hook} should be registered" );
		}
	}

	/**
	 * Test add_collection_date_field rendering.
	 */
	public function test_add_collection_date_field() {
		ob_start();
		$this->checkout->add_collection_date_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'collection_date_field', $output );
		$this->assertStringContainsString( 'Preferred Collection Date', $output );
		$this->assertStringContainsString( 'collection_date', $output );
		$this->assertStringContainsString( 'required', $output );
		$this->assertStringContainsString( 'readonly', $output );
	}

	/**
	 * Test collection date field with checkout object.
	 */
	public function test_add_collection_date_field_with_checkout() {
		// Mock checkout object.
		$mock_checkout = $this->createMock( 'WC_Checkout' );
		$mock_checkout->method( 'get_value' )
			->with( 'collection_date' )
			->willReturn( '2024-12-25' );

		ob_start();
		$this->checkout->add_collection_date_field( $mock_checkout );
		$output = ob_get_clean();

		$this->assertStringContainsString( '2024-12-25', $output );
	}

	/**
	 * Test collection date field rendering prevention of duplicates.
	 */
	public function test_add_collection_date_field_no_duplicates() {
		// Call method twice.
		ob_start();
		$this->checkout->add_collection_date_field();
		$output1 = ob_get_clean();

		ob_start();
		$this->checkout->add_collection_date_field();
		$output2 = ob_get_clean();

		// Second call should return empty.
		$this->assertNotEmpty( $output1 );
		$this->assertEmpty( $output2 );
	}

	/**
	 * Test validate_collection_date with empty date.
	 */
	public function test_validate_collection_date_empty() {
		$_POST = array(); // Empty POST data.

		// Capture WooCommerce notices.
		$notices = wc_get_notices( 'error' );

		$this->checkout->validate_collection_date();

		$new_notices = wc_get_notices( 'error' );
		$error_added = count( $new_notices ) > count( $notices );

		$this->assertTrue( $error_added );
	}

	/**
	 * Test validate_collection_date with invalid date.
	 */
	public function test_validate_collection_date_invalid() {
		$_POST = array(
			'collection_date' => 'invalid-date',
		);

		$calculator = $this->createMock( 'WC_Collection_Date_Calculator' );
		$calculator->method( 'is_date_available' )
			->willReturn( false );

		// Use reflection to set the calculator.
		$reflection = new ReflectionClass( $this->checkout );
		$property = $reflection->getProperty( 'calculator' );
		$property->setAccessible( true );
		$property->setValue( $this->checkout, $calculator );

		$notices = wc_get_notices( 'error' );

		$this->checkout->validate_collection_date();

		$new_notices = wc_get_notices( 'error' );
		$error_added = count( $new_notices ) > count( $notices );

		$this->assertTrue( $error_added );
	}

	/**
	 * Test validate_collection_date with valid date.
	 */
	public function test_validate_collection_date_valid() {
		$_POST = array(
			'collection_date' => '2024-12-25',
		);

		$calculator = $this->createMock( 'WC_Collection_Date_Calculator' );
		$calculator->method( 'is_date_available' )
			->willReturn( true );

		$reflection = new ReflectionClass( $this->checkout );
		$property = $reflection->getProperty( 'calculator' );
		$property->setAccessible( true );
		$property->setValue( $this->checkout, $calculator );

		$notices = wc_get_notices( 'error' );

		$this->checkout->validate_collection_date();

		$new_notices = wc_get_notices( 'error' );
		$error_added = count( $new_notices ) > count( $notices );

		$this->assertFalse( $error_added );
	}

	/**
	 * Test save_collection_date functionality.
	 */
	public function test_save_collection_date() {
		$order_id = $this->create_test_order();
		$_POST = array(
			'collection_date' => '2024-12-25',
		);

		$this->checkout->save_collection_date( $order_id );

		$saved_date = get_post_meta( $order_id, '_collection_date', true );
		$this->assertEquals( '2024-12-25', $saved_date );
	}

	/**
	 * Test save_collection_date with empty date.
	 */
	public function test_save_collection_date_empty() {
		$order_id = $this->create_test_order();
		$_POST = array(); // Empty POST data.

		$this->checkout->save_collection_date( $order_id );

		$saved_date = get_post_meta( $order_id, '_collection_date', true );
		$this->assertEmpty( $saved_date );
	}

	/**
	 * Test save_collection_date for block checkout.
	 */
	public function test_save_collection_date_block_checkout() {
		$order_id = $this->create_test_order();

		// Mock request with collection date in extensions.
		$request = $this->create_rest_request( array(
			'extensions' => array(
				'wc-collection-date' => array(
					'collection_date' => '2024-12-25',
				),
			),
		) );

		$order = wc_get_order( $order_id );
		$this->checkout->save_collection_date_block_checkout( $order, $request );

		$saved_date = $order->get_meta( '_collection_date' );
		$this->assertEquals( '2024-12-25', $saved_date );
	}

	/**
	 * Test save_collection_date for block checkout with billing data.
	 */
	public function test_save_collection_date_block_checkout_billing_data() {
		$order_id = $this->create_test_order();

		$request = $this->create_rest_request( array(
			'billing_address' => array(
				'collection_date' => '2024-12-26',
			),
		) );

		$order = wc_get_order( $order_id );
		$this->checkout->save_collection_date_block_checkout( $order, $request );

		$saved_date = $order->get_meta( '_collection_date' );
		$this->assertEquals( '2024-12-26', $saved_date );
	}

	/**
	 * Test save_collection_date for block checkout with direct param.
	 */
	public function test_save_collection_date_block_checkout_direct_param() {
		$order_id = $this->create_test_order();

		$request = $this->create_rest_request( array(
			'collection_date' => '2024-12-27',
		) );

		$order = wc_get_order( $order_id );
		$this->checkout->save_collection_date_block_checkout( $order, $request );

		$saved_date = $order->get_meta( '_collection_date' );
		$this->assertEquals( '2024-12-27', $saved_date );
	}

	/**
	 * Test save_collection_date after processing fallback.
	 */
	public function test_save_collection_date_after_processing() {
		$order_id = $this->create_test_order();
		$_POST = array(
			'collection_date' => '2024-12-28',
		);

		$order = wc_get_order( $order_id );
		$this->checkout->save_collection_date_after_processing( $order );

		$saved_date = $order->get_meta( '_collection_date' );
		$this->assertEquals( '2024-12-28', $saved_date );
	}

	/**
	 * Test save_collection_date after processing with existing date (no override).
	 */
	public function test_save_collection_date_after_processing_existing() {
		$order_id = $this->create_test_order();
		$_POST = array(
			'collection_date' => '2024-12-28',
		);

		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_collection_date', '2024-12-25' );
		$order->save();

		$this->checkout->save_collection_date_after_processing( $order );

		// Should not override existing date.
		$saved_date = $order->get_meta( '_collection_date' );
		$this->assertEquals( '2024-12-25', $saved_date );
	}

	/**
	 * Test display_collection_date_confirmation.
	 */
	public function test_display_collection_date_confirmation() {
		$order_id = $this->create_test_order();
		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_collection_date', '2024-12-25' );
		$order->save();

		ob_start();
		$this->checkout->display_collection_date_confirmation( $order );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Collection Information', $output );
		$this->assertStringContainsString( 'Collection Date:', $output );
		$this->assertStringContainsString( '2024-12-25', $output );
	}

	/**
	 * Test display_collection_date_confirmation with no date.
	 */
	public function test_display_collection_date_confirmation_no_date() {
		$order_id = $this->create_test_order();
		$order = wc_get_order( $order_id );

		ob_start();
		$this->checkout->display_collection_date_confirmation( $order );
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}

	/**
	 * Test display_collection_date_admin.
	 */
	public function test_display_collection_date_admin() {
		$order_id = $this->create_test_order();
		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_collection_date', '2024-12-25' );
		$order->save();

		ob_start();
		$this->checkout->display_collection_date_admin( $order );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Collection Information', $output );
		$this->assertStringContainsString( 'Collection Date:', $output );
		$this->assertStringContainsString( '2024-12-25', $output );
	}

	/**
	 * Test display_collection_date_email (HTML).
	 */
	public function test_display_collection_date_email_html() {
		$order_id = $this->create_test_order();
		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_collection_date', '2024-12-25' );
		$order->save();

		$email = $this->createMock( 'WC_Email' );

		ob_start();
		$this->checkout->display_collection_date_email( $order, false, false, $email );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Collection Information', $output );
		$this->assertStringContainsString( 'Collection Date:', $output );
		$this->assertStringContainsString( '2024-12-25', $output );
		$this->assertStringContainsString( '<div', $output ); // HTML format
	}

	/**
	 * Test display_collection_date_email (plain text).
	 */
	public function test_display_collection_date_email_plain() {
		$order_id = $this->create_test_order();
		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_collection_date', '2024-12-25' );
		$order->save();

		$email = $this->createMock( 'WC_Email' );

		ob_start();
		$this->checkout->display_collection_date_email( $order, false, true, $email );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'COLLECTION INFORMATION', $output );
		$this->assertStringContainsString( 'Collection Date:', $output );
		$this->assertStringContainsString( '2024-12-25', $output );
		$this->assertStringNotContainsString( '<div', $output ); // Plain text format
	}

	/**
	 * Test add_collection_date_to_order_display.
	 */
	public function test_add_collection_date_to_order_display() {
		$order_id = $this->create_test_order();
		$order = wc_get_order( $order_id );
		$order->update_meta_data( '_collection_date', '2024-12-25' );
		$order->save();

		$total_rows = array(
			'subtotal' => array(
				'label' => 'Subtotal',
				'value' => '$10.00',
			),
			'payment_method' => array(
				'label' => 'Payment Method',
				'value' => 'Cash on Delivery',
			),
			'total' => array(
				'label' => 'Total',
				'value' => '$10.00',
			),
		);

		$modified_rows = $this->checkout->add_collection_date_to_order_display( $total_rows, $order );

		$this->assertArrayHasKey( 'collection_date', $modified_rows );
		$this->assertEquals( 'Collection Date:', $modified_rows['collection_date']['label'] );
		$this->assertStringContainsString( '2024-12-25', $modified_rows['collection_date']['value'] );

		// Should maintain original order.
		$keys = array_keys( $modified_rows );
		$collection_date_index = array_search( 'collection_date', $keys );
		$payment_method_index = array_search( 'payment_method', $keys );

		$this->assertTrue( $collection_date_index > $payment_method_index );
	}

	/**
	 * Test add_collection_date_to_order_display with no date.
	 */
	public function test_add_collection_date_to_order_display_no_date() {
		$order_id = $this->create_test_order();
		$order = wc_get_order( $order_id );

		$total_rows = array(
			'payment_method' => array(
				'label' => 'Payment Method',
				'value' => 'Cash on Delivery',
			),
		);

		$modified_rows = $this->checkout->add_collection_date_to_order_display( $total_rows, $order );

		$this->assertArrayNotHasKey( 'collection_date', $modified_rows );
		$this->assertEquals( $total_rows, $modified_rows );
	}

	/**
	 * Test enqueue_checkout_assets.
	 */
	public function test_enqueue_checkout_assets() {
		// Mock is_checkout() to return true.
		if ( ! function_exists( 'is_checkout' ) ) {
			function is_checkout() {
				return true;
			}
		}

		$this->checkout->enqueue_checkout_assets();

		// Check that scripts and styles are enqueued.
		$this->assertTrue( wp_style_is( 'flatpickr', 'registered' ) );
		$this->assertTrue( wp_script_is( 'flatpickr', 'registered' ) );
		$this->assertTrue( wp_style_is( 'wc-collection-date-checkout', 'registered' ) );
		$this->assertTrue( wp_script_is( 'wc-collection-date-checkout', 'registered' ) );
	}

	/**
	 * Test enqueue_checkout_assets not on checkout page.
	 */
	public function test_enqueue_checkout_assets_not_checkout() {
		// Mock is_checkout() to return false.
		if ( ! function_exists( 'is_checkout' ) ) {
			function is_checkout() {
				return false;
			}
		}

		$this->checkout->enqueue_checkout_assets();

		// Should not enqueue assets outside checkout.
		$this->assertFalse( wp_style_is( 'wc-collection-date-checkout', 'registered' ) );
		$this->assertFalse( wp_script_is( 'wc-collection-date-checkout', 'registered' ) );
	}

	/**
	 * Test render_shortcode.
	 */
	public function test_render_shortcode() {
		$output = $this->checkout->render_shortcode();

		$this->assertStringContainsString( 'collection_date_field', $output );
		$this->assertStringContainsString( 'Preferred Collection Date', $output );
	}

	/**
	 * Test get_flatpickr_date_format.
	 */
	public function test_get_flatpickr_date_format() {
		// Use reflection to test protected method.
		$reflection = new ReflectionClass( $this->checkout );
		$method = $reflection->getMethod( 'get_flatpickr_date_format' );
		$method->setAccessible( true );

		// Test common WordPress date formats.
		update_option( 'date_format', 'Y-m-d' );
		$this->assertEquals( 'Y-m-d', $method->invoke( $this->checkout ) );

		update_option( 'date_format', 'd/m/Y' );
		$this->assertEquals( 'd/m/Y', $method->invoke( $this->checkout ) );

		update_option( 'date_format', 'm/d/Y' );
		$this->assertEquals( 'm/d/Y', $method->invoke( $this->checkout ) );

		update_option( 'date_format', 'F j, Y' );
		$this->assertEquals( 'F j, Y', $method->invoke( $this->checkout ) );

		// Test unknown format (should default to Y-m-d).
		update_option( 'date_format', 'unknown' );
		$this->assertEquals( 'Y-m-d', $method->invoke( $this->checkout ) );
	}

	/**
	 * Test inject_field_via_js exists and outputs JavaScript.
	 */
	public function test_inject_field_via_js() {
		// Mock is_checkout() and is_order_received_page().
		if ( ! function_exists( 'is_checkout' ) ) {
			function is_checkout() {
				return true;
			}
		}
		if ( ! function_exists( 'is_order_received_page' ) ) {
			function is_order_received_page() {
				return false;
			}
		}

		ob_start();
		$this->checkout->inject_field_via_js();
		$output = ob_get_clean();

		$this->assertStringContainsString( '<script>', $output );
		$this->assertStringContainsString( 'collection_date_field', $output );
		$this->assertStringContainsString( 'jQuery', $output );
	}

	/**
	 * Test inject_field_via_js not on checkout page.
	 */
	public function test_inject_field_via_js_not_checkout() {
		// Mock is_checkout() to return false.
		if ( ! function_exists( 'is_checkout' ) ) {
			function is_checkout() {
				return false;
			}
		}
		if ( ! function_exists( 'is_order_received_page' ) ) {
			function is_order_received_page() {
				return false;
			}
		}

		ob_start();
		$this->checkout->inject_field_via_js();
		$output = ob_get_clean();

		$this->assertEmpty( $output );
	}
}