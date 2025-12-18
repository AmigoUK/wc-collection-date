<?php
/**
 * Unit Tests for WC_Collection_Date_Lead_Time_Resolver Class
 *
 * @package WC_Collection_Date\Tests\Unit
 */

class WC_Collection_Date_Test_Lead_Time_Resolver extends WC_Collection_Date_Test_Base {

	/**
	 * Lead time resolver instance.
	 *
	 * @var WC_Collection_Date_Lead_Time_Resolver
	 */
	protected $resolver;

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->resolver = new WC_Collection_Date_Lead_Time_Resolver();
	}

	/**
	 * Test resolver instantiation.
	 */
	public function test_resolver_instantiation() {
		$this->assertInstanceOf( 'WC_Collection_Date_Lead_Time_Resolver', $this->resolver );
	}

	/**
	 * Test get_effective_settings with global fallback.
	 */
	public function test_get_effective_settings_global_fallback() {
		// Create a product with no category rules.
		$product_id = $this->create_test_product();

		$settings = $this->resolver->get_effective_settings( $product_id );

		$this->assertIsArray( $settings );
		$this->assertArrayHasKey( 'lead_time', $settings );
		$this->assertArrayHasKey( 'lead_time_type', $settings );
		$this->assertArrayHasKey( 'cutoff_time', $settings );
		$this->assertArrayHasKey( 'working_days', $settings );
		$this->assertArrayHasKey( 'collection_days', $settings );

		// Should match global settings.
		$this->assertEquals( 2, $settings['lead_time'] );
		$this->assertEquals( 'calendar', $settings['lead_time_type'] );
		$this->assertEquals( array( '1', '2', '3', '4', '5', '6' ), $settings['working_days'] );
		$this->assertEquals( array( '0', '1', '2', '3', '4', '5', '6' ), $settings['collection_days'] );
	}

	/**
	 * Test get_effective_settings with category rule priority.
	 */
	public function test_get_effective_settings_category_priority() {
		// Create test product and category.
		$category_id = $this->create_test_category();
		$product_id = $this->create_test_product();

		// Assign product to category.
		wp_set_object_terms( $product_id, array( $category_id ), 'product_cat' );

		// Set up category rule with different settings from global.
		$category_settings = array(
			'lead_time'        => 5,
			'lead_time_type'   => 'working',
			'cutoff_time'      => '14:00',
			'working_days'     => array( '1', '2', '3', '4' ),
			'collection_days'  => array( '1', '2', '3', '4', '5' ),
		);
		$this->setup_category_rule( $category_id, $category_settings );

		$settings = $this->resolver->get_effective_settings( $product_id );

		// Should use category settings, not global.
		$this->assertEquals( 5, $settings['lead_time'] );
		$this->assertEquals( 'working', $settings['lead_time_type'] );
		$this->assertEquals( '14:00', $settings['cutoff_time'] );
		$this->assertEquals( array( '1', '2', '3', '4' ), $settings['working_days'] );
		$this->assertEquals( array( '1', '2', '3', '4', '5' ), $settings['collection_days'] );
	}

	/**
	 * Test get_effective_settings with multiple categories (longest lead time).
	 */
	public function test_get_effective_settings_multiple_categories() {
		// Create test product and multiple categories.
		$category1_id = $this->create_test_category(
			array( 'name' => 'Category 1' )
		);
		$category2_id = $this->create_test_category(
			array( 'name' => 'Category 2' )
		);
		$category3_id = $this->create_test_category(
			array( 'name' => 'Category 3' )
		);

		$product_id = $this->create_test_product();

		// Assign product to all categories.
		wp_set_object_terms( $product_id, array( $category1_id, $category2_id, $category3_id ), 'product_cat' );

		// Set up category rules with different lead times.
		$this->setup_category_rule( $category1_id, array( 'lead_time' => 2 ) );
		$this->setup_category_rule( $category2_id, array( 'lead_time' => 5 ) ); // Longest
		$this->setup_category_rule( $category3_id, array( 'lead_time' => 3 ) );

		$settings = $this->resolver->get_effective_settings( $product_id );

		// Should use the category with longest lead time (Category 2).
		$this->assertEquals( 5, $settings['lead_time'] );
	}

	/**
	 * Test get_effective_settings with non-existent product.
	 */
	public function test_get_effective_settings_non_existent_product() {
		$settings = $this->resolver->get_effective_settings( 99999 );

		$this->assertIsArray( $settings );
		// Should fall back to global settings.
		$this->assertEquals( 2, $settings['lead_time'] );
	}

	/**
	 * Test get_all_category_rules functionality.
	 */
	public function test_get_all_category_rules() {
		// Initially should be empty.
		$rules = $this->resolver->get_all_category_rules();
		$this->assertIsArray( $rules );
		$this->assertEmpty( $rules );

		// Add some category rules.
		$category1_id = $this->create_test_category();
		$category2_id = $this->create_test_category();

		$this->setup_category_rule( $category1_id, array( 'lead_time' => 3 ) );
		$this->setup_category_rule( $category2_id, array( 'lead_time' => 5 ) );

		$rules = $this->resolver->get_all_category_rules();

		$this->assertArrayHasKey( $category1_id, $rules );
		$this->assertArrayHasKey( $category2_id, $rules );
		$this->assertEquals( 3, $rules[ $category1_id ]['lead_time'] );
		$this->assertEquals( 5, $rules[ $category2_id ]['lead_time'] );
	}

	/**
	 * Test get_category_rule functionality.
	 */
	public function test_get_category_rule() {
		$category_id = $this->create_test_category();
		$category_settings = array(
			'lead_time'       => 4,
			'lead_time_type'  => 'working',
			'cutoff_time'     => '16:30',
		);
		$this->setup_category_rule( $category_id, $category_settings );

		$rule = $this->resolver->get_category_rule( $category_id );

		$this->assertIsArray( $rule );
		$this->assertEquals( 4, $rule['lead_time'] );
		$this->assertEquals( 'working', $rule['lead_time_type'] );
		$this->assertEquals( '16:30', $rule['cutoff_time'] );

		// Test non-existent category.
		$rule = $this->resolver->get_category_rule( 99999 );
		$this->assertNull( $rule );
	}

	/**
	 * Test save_category_rule functionality.
	 */
	public function test_save_category_rule() {
		$category_id = $this->create_test_category();
		$settings = array(
			'lead_time'        => 6,
			'lead_time_type'   => 'working',
			'cutoff_time'      => '12:00',
			'working_days'     => array( '1', '2', '3', '4', '5' ),
			'collection_days'  => array( '0', '1', '2', '3', '4', '5', '6' ),
		);

		$result = $this->resolver->save_category_rule( $category_id, $settings );

		$this->assertTrue( $result );

		// Verify the rule was saved correctly.
		$rule = $this->resolver->get_category_rule( $category_id );
		$this->assertEquals( 6, $rule['lead_time'] );
		$this->assertEquals( 'working', $rule['lead_time_type'] );
		$this->assertEquals( '12:00', $rule['cutoff_time'] );
	}

	/**
	 * Test save_category_rule with invalid data.
	 */
	public function test_save_category_rule_invalid_data() {
		$category_id = $this->create_test_category();

		// Test empty settings.
		$result = $this->resolver->save_category_rule( $category_id, array() );
		$this->assertFalse( $result );

		// Test invalid lead time type.
		$settings = array(
			'lead_time'      => 3,
			'lead_time_type' => 'invalid',
		);
		$result = $this->resolver->save_category_rule( $category_id, $settings );
		$this->assertTrue( $result ); // Should sanitize to default

		$rule = $this->resolver->get_category_rule( $category_id );
		$this->assertEquals( 'calendar', $rule['lead_time_type'] ); // Sanitized to default
	}

	/**
	 * Test delete_category_rule functionality.
	 */
	public function test_delete_category_rule() {
		$category_id = $this->create_test_category();

		// First, save a rule.
		$this->setup_category_rule( $category_id, array( 'lead_time' => 3 ) );

		// Verify it exists.
		$rule = $this->resolver->get_category_rule( $category_id );
		$this->assertNotNull( $rule );

		// Delete it.
		$result = $this->resolver->delete_category_rule( $category_id );
		$this->assertTrue( $result );

		// Verify it's gone.
		$rule = $this->resolver->get_category_rule( $category_id );
		$this->assertNull( $rule );

		// Test deleting non-existent rule.
		$result = $this->resolver->delete_category_rule( 99999 );
		$this->assertFalse( $result );
	}

	/**
	 * Test get_settings_source functionality.
	 */
	public function test_get_settings_source() {
		// Test global fallback.
		$product_id = $this->create_test_product();
		$source = $this->resolver->get_settings_source( $product_id );

		$this->assertIsArray( $source );
		$this->assertEquals( 'global', $source['source'] );
		$this->assertEquals( 'Global Default', $source['label'] );

		// Test category rule.
		$category_id = $this->create_test_category(
			array( 'name' => 'Test Category Name' )
		);
		$product_id = $this->create_test_product();
		wp_set_object_terms( $product_id, array( $category_id ), 'product_cat' );

		$this->setup_category_rule( $category_id, array( 'lead_time' => 3 ) );

		$source = $this->resolver->get_settings_source( $product_id );

		$this->assertEquals( 'category', $source['source'] );
		$this->assertEquals( 'Category Rule', $source['label'] );
		$this->assertEquals( $category_id, $source['category_id'] );
		$this->assertEquals( 'Test Category Name', $source['category_name'] );
	}

	/**
	 * Test category rules data sanitization.
	 */
	public function test_category_rules_sanitization() {
		$category_id = $this->create_test_category();

		$raw_settings = array(
			'lead_time'       => 'abc', // Should be sanitized to integer
			'lead_time_type'  => 'invalid', // Should be sanitized to default
			'cutoff_time'     => '25:00', // Invalid time, should be sanitized to empty
			'working_days'    => array( '1', '8', 'invalid', '2' ), // Only valid days
			'collection_days' => 'not-an-array', // Should be converted to empty array
		);

		$result = $this->resolver->save_category_rule( $category_id, $raw_settings );
		$this->assertTrue( $result );

		$rule = $this->resolver->get_category_rule( $category_id );

		// Verify sanitization.
		$this->assertEquals( 0, $rule['lead_time'] ); // abc becomes 0 via absint
		$this->assertEquals( 'calendar', $rule['lead_time_type'] ); // invalid becomes default
		$this->assertEquals( '', $rule['cutoff_time'] ); // invalid time becomes empty
		$this->assertEquals( array( '1', '2' ), $rule['working_days'] ); // Only valid days kept
		$this->assertEquals( array(), $rule['collection_days'] ); // Invalid becomes empty array
	}

	/**
	 * Test get_product_categories functionality.
	 */
	public function test_get_product_categories() {
		// Use reflection to test protected method.
		$reflection = new ReflectionClass( $this->resolver );
		$method = $reflection->getMethod( 'get_product_categories' );
		$method->setAccessible( true );

		// Test product with no categories.
		$product_id = $this->create_test_product();
		$categories = $method->invoke( $this->resolver, $product_id );
		$this->assertIsArray( $categories );
		$this->assertEmpty( $categories );

		// Test product with categories.
		$category1_id = $this->create_test_category();
		$category2_id = $this->create_test_category();

		wp_set_object_terms( $product_id, array( $category1_id, $category2_id ), 'product_cat' );

		$categories = $method->invoke( $this->resolver, $product_id );
		$this->assertContains( $category1_id, $categories );
		$this->assertContains( $category2_id, $categories );

		// Test non-existent product.
		$categories = $method->invoke( $this->resolver, 99999 );
		$this->assertIsArray( $categories );
		$this->assertEmpty( $categories );
	}

	/**
	 * Test resolve_multiple_category_rules functionality.
	 */
	public function test_resolve_multiple_category_rules() {
		// Use reflection to test protected method.
		$reflection = new ReflectionClass( $this->resolver );
		$method = $reflection->getMethod( 'resolve_multiple_category_rules' );
		$method->setAccessible( true );

		// Test empty rules.
		$result = $method->invoke( $this->resolver, array() );
		$this->assertIsArray( $result );
		$this->assertEmpty( $result );

		// Test single rule.
		$rules = array(
			1 => array( 'lead_time' => 3, 'cutoff_time' => '12:00' ),
		);
		$result = $method->invoke( $this->resolver, $rules );
		$this->assertEquals( 3, $result['lead_time'] );
		$this->assertEquals( '12:00', $result['cutoff_time'] );

		// Test multiple rules with different lead times.
		$rules = array(
			1 => array( 'lead_time' => 2, 'cutoff_time' => '10:00' ),
			2 => array( 'lead_time' => 5, 'cutoff_time' => '12:00' ), // Longest lead time
			3 => array( 'lead_time' => 3, 'cutoff_time' => '14:00' ),
		);
		$result = $method->invoke( $this->resolver, $rules );
		$this->assertEquals( 5, $result['lead_time'] );
		$this->assertEquals( '12:00', $result['cutoff_time'] ); // Should include other settings too
	}

	/**
	 * Test sanitize_category_rule functionality.
	 */
	public function test_sanitize_category_rule() {
		// Use reflection to test protected method.
		$reflection = new ReflectionClass( $this->resolver );
		$method = $reflection->getMethod( 'sanitize_category_rule' );
		$method->setAccessible( true );

		$raw_settings = array(
			'lead_time'       => '7',
			'lead_time_type'  => 'working',
			'cutoff_time'     => '18:30',
			'working_days'    => array( '1', '2', '3', '8', 'invalid' ),
			'collection_days' => array( '0', '1', '2', '3', '4', '5', '6', '9' ),
		);

		$sanitized = $method->invoke( $this->resolver, $raw_settings );

		$this->assertEquals( 7, $sanitized['lead_time'] );
		$this->assertEquals( 'working', $sanitized['lead_time_type'] );
		$this->assertEquals( '18:30', $sanitized['cutoff_time'] );
		$this->assertEquals( array( '1', '2', '3' ), $sanitized['working_days'] ); // Only valid days
		$this->assertEquals( array( '0', '1', '2', '3', '4', '5', '6' ), $sanitized['collection_days'] ); // Only valid days
	}

	/**
	 * Test sanitize_days_array functionality.
	 */
	public function test_sanitize_days_array() {
		// Use reflection to test protected method.
		$reflection = new ReflectionClass( $this->resolver );
		$method = $reflection->getMethod( 'sanitize_days_array' );
		$method->setAccessible( true );

		// Test valid array.
		$result = $method->invoke( $this->resolver, array( '0', '1', '2', '3', '4', '5', '6' ) );
		$this->assertEquals( array( '0', '1', '2', '3', '4', '5', '6' ), $result );

		// Test mixed valid/invalid.
		$result = $method->invoke( $this->resolver, array( '1', '8', 'invalid', '5' ) );
		$this->assertEquals( array( '1', '5' ), $result );

		// Test non-array input.
		$result = $method->invoke( $this->resolver, 'not-an-array' );
		$this->assertEquals( array(), $result );

		// Test empty array.
		$result = $method->invoke( $this->resolver, array() );
		$this->assertEquals( array(), $result );
	}

	/**
	 * Test complex scenario with multiple categories and partial rules.
	 */
	public function test_complex_category_rules_scenario() {
		// Create a complex scenario with multiple products and categories.
		$category_a_id = $this->create_test_category( array( 'name' => 'Category A' ) );
		$category_b_id = $this->create_test_category( array( 'name' => 'Category B' ) );
		$category_c_id = $this->create_test_category( array( 'name' => 'Category C' ) );

		// Set up rules for some categories.
		$this->setup_category_rule( $category_a_id, array(
			'lead_time' => 2,
			'lead_time_type' => 'calendar',
			'cutoff_time' => '12:00',
		));

		$this->setup_category_rule( $category_c_id, array(
			'lead_time' => 4,
			'lead_time_type' => 'working',
			'cutoff_time' => '16:00',
		));

		// Category B has no rule.

		// Test different product scenarios.

		// Product in Category A only.
		$product_a = $this->create_test_product();
		wp_set_object_terms( $product_a, array( $category_a_id ), 'product_cat' );

		$settings = $this->resolver->get_effective_settings( $product_a );
		$this->assertEquals( 2, $settings['lead_time'] );
		$this->assertEquals( 'calendar', $settings['lead_time_type'] );

		// Product in Category B only (no rule, uses global).
		$product_b = $this->create_test_product();
		wp_set_object_terms( $product_b, array( $category_b_id ), 'product_cat' );

		$settings = $this->resolver->get_effective_settings( $product_b );
		$this->assertEquals( 2, $settings['lead_time'] ); // Global default
		$this->assertEquals( 'calendar', $settings['lead_time_type'] );

		// Product in Category A and C (uses longest lead time - Category C).
		$product_ac = $this->create_test_product();
		wp_set_object_terms( $product_ac, array( $category_a_id, $category_c_id ), 'product_cat' );

		$settings = $this->resolver->get_effective_settings( $product_ac );
		$this->assertEquals( 4, $settings['lead_time'] ); // Longest (Category C)
		$this->assertEquals( 'working', $settings['lead_time_type'] ); // From Category C
		$this->assertEquals( '16:00', $settings['cutoff_time'] ); // From Category C

		// Product in all categories.
		$product_all = $this->create_test_product();
		wp_set_object_terms( $product_all, array( $category_a_id, $category_b_id, $category_c_id ), 'product_cat' );

		$settings = $this->resolver->get_effective_settings( $product_all );
		$this->assertEquals( 4, $settings['lead_time'] ); // Still Category C (longest)
	}
}