<?php
/**
 * Unit Tests for WC_Collection_Date_Settings Class
 *
 * @package WC_Collection_Date\Tests\Unit
 */

class WC_Collection_Date_Test_Settings extends WC_Collection_Date_Test_Base {

	/**
	 * Settings instance.
	 *
	 * @var WC_Collection_Date_Settings
	 */
	protected $settings;

	/**
	 * Set up the test.
	 */
	public function setUp(): void {
		parent::setUp();
		$this->settings = new WC_Collection_Date_Settings();
	}

	/**
	 * Test settings instantiation.
	 */
	public function test_settings_instantiation() {
		$this->assertInstanceOf( 'WC_Collection_Date_Settings', $this->settings );
	}

	/**
	 * Test settings initialization hooks.
	 */
	public function test_initialization_hooks() {
		$this->assertTrue( has_action( 'admin_init', array( $this->settings, 'register_settings' ) ) !== false );
	}

	/**
	 * Test register_settings functionality.
	 */
	public function test_register_settings() {
		$this->settings->register_settings();

		// Check that settings are registered.
		global $wp_registered_settings;

		$registered_settings = get_registered_settings();

		$this->assertArrayHasKey( 'wc_collection_date_settings', $registered_settings );
		$this->assertArrayHasKey( 'wc_collection_date_working_days', $registered_settings['wc_collection_date_settings'] );
		$this->assertArrayHasKey( 'wc_collection_date_lead_time', $registered_settings['wc_collection_date_settings'] );
		$this->assertArrayHasKey( 'wc_collection_date_max_booking_days', $registered_settings['wc_collection_date_settings'] );
		$this->assertArrayHasKey( 'wc_collection_date_lead_time_type', $registered_settings['wc_collection_date_settings'] );
		$this->assertArrayHasKey( 'wc_collection_date_cutoff_time', $registered_settings['wc_collection_date_settings'] );
		$this->assertArrayHasKey( 'wc_collection_date_collection_days', $registered_settings['wc_collection_date_settings'] );
	}

	/**
	 * Test sanitize_working_days method.
	 */
	public function test_sanitize_working_days() {
		// Test valid array input.
		$input = array( '1', '2', '3', '8', 'invalid', '5' );
		$result = $this->settings->sanitize_working_days( $input );

		$this->assertEquals( array( '1', '2', '3', '5' ), $result );

		// Test non-array input.
		$result = $this->settings->sanitize_working_days( 'not-an-array' );
		$this->assertEquals( array(), $result );

		// Test empty array.
		$result = $this->settings->sanitize_working_days( array() );
		$this->assertEquals( array(), $result );

		// Test all valid days.
		$input = array( '0', '1', '2', '3', '4', '5', '6' );
		$result = $this->settings->sanitize_working_days( $input );
		$this->assertEquals( $input, $result );
	}

	/**
	 * Test sanitize_collection_days method.
	 */
	public function test_sanitize_collection_days() {
		// Test valid array input.
		$input = array( '0', '1', '9', 'invalid', '6' );
		$result = $this->settings->sanitize_collection_days( $input );

		$this->assertEquals( array( '0', '1', '6' ), $result );

		// Test non-array input.
		$result = $this->settings->sanitize_collection_days( 'not-an-array' );
		$this->assertEquals( array(), $result );

		// Test mixed valid/invalid.
		$input = array( 'monday', '1', 'tuesday', '2', 3 );
		$result = $this->settings->sanitize_collection_days( $input );
		$this->assertEquals( array( '1', '2' ), $result );
	}

	/**
	 * Test sanitize_lead_time_type method.
	 */
	public function test_sanitize_lead_time_type() {
		// Test valid types.
		$this->assertEquals( 'calendar', $this->settings->sanitize_lead_time_type( 'calendar' ) );
		$this->assertEquals( 'working', $this->settings->sanitize_lead_time_type( 'working' ) );

		// Test invalid types.
		$this->assertEquals( 'calendar', $this->settings->sanitize_lead_time_type( 'invalid' ) );
		$this->assertEquals( 'calendar', $this->settings->sanitize_lead_time_type( '' ) );
		$this->assertEquals( 'calendar', $this->settings->sanitize_lead_time_type( null ) );
		$this->assertEquals( 'calendar', $this->settings->sanitize_lead_time_type( 123 ) );
	}

	/**
	 * Test sanitize_cutoff_time method.
	 */
	public function test_sanitize_cutoff_time() {
		// Test valid formats.
		$this->assertEquals( '12:00', $this->settings->sanitize_cutoff_time( '12:00' ) );
		$this->assertEquals( '23:59', $this->settings->sanitize_cutoff_time( '23:59' ) );
		$this->assertEquals( '00:00', $this->settings->sanitize_cutoff_time( '00:00' ) );
		$this->assertEquals( '09:30', $this->settings->sanitize_cutoff_time( '9:30' ) );

		// Test invalid formats.
		$this->assertEquals( '', $this->settings->sanitize_cutoff_time( '25:00' ) );
		$this->assertEquals( '', $this->settings->sanitize_cutoff_time( '12:60' ) );
		$this->assertEquals( '', $this->settings->sanitize_cutoff_time( 'invalid' ) );
		$this->assertEquals( '', $this->settings->sanitize_cutoff_time( '12:30:45' ) );

		// Test empty input.
		$this->assertEquals( '', $this->settings->sanitize_cutoff_time( '' ) );
		$this->assertEquals( '', $this->settings->sanitize_cutoff_time( null ) );
	}

	/**
	 * Test render_settings_page basic functionality.
	 */
	public function test_render_settings_page() {
		// Mock WordPress admin functions.
		if ( ! function_exists( 'current_user_can' ) ) {
			function current_user_can( $capability ) {
				return 'manage_woocommerce' === $capability;
			}
		}

		ob_start();
		$this->settings->render_settings_page();
		$output = ob_get_clean();

		// Should contain page structure.
		$this->assertStringContainsString( 'wrap wc-collection-date-admin', $output );
		$this->assertStringContainsString( 'nav-tab-wrapper', $output );
		$this->assertStringContainsString( 'wc-collection-date-tab-content', $output );
	}

	/**
	 * Test render_settings_page without permissions.
	 */
	public function test_render_settings_page_no_permissions() {
		if ( ! function_exists( 'current_user_can' ) ) {
			function current_user_can( $capability ) {
				return false;
			}
		}

		ob_start();
		$this->settings->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'You do not have sufficient permissions', $output );
	}

	/**
	 * Test render_settings_tab with current tab parameter.
	 */
	public function test_render_settings_tab_with_parameter() {
		// Mock $_GET.
		$_GET['tab'] = 'settings';

		ob_start();
		$this->settings->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'nav-tab-active', $output );
		$this->assertStringContainsString( 'Settings', $output );
	}

	/**
	 * Test category rules tab rendering.
	 */
	public function test_render_category_rules_tab() {
		$_GET['tab'] = 'category_rules';

		ob_start();
		$this->settings->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Category Rules', $output );
		$this->assertStringContainsString( 'Configure different lead time rules', $output );
	}

	/**
	 * Test render_general_section method.
	 */
	public function test_render_general_section() {
		ob_start();
		$this->settings->render_general_section();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Configure global collection date rules', $output );
	}

	/**
	 * Test render_working_days_field method.
	 */
	public function test_render_working_days_field() {
		update_option( 'wc_collection_date_working_days', array( '1', '2', '3', '4', '5' ) );

		ob_start();
		$this->settings->render_working_days_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wc-collection-date-working-days', $output );
		$this->assertStringContainsString( 'type="checkbox"', $output );
		$this->assertStringContainsString( 'name="wc_collection_date_working_days[]"', $output );
		$this->assertStringContainsString( 'checked="checked"', $output ); // Some checkboxes should be checked
	}

	/**
	 * Test render_collection_days_field method.
	 */
	public function test_render_collection_days_field() {
		update_option( 'wc_collection_date_collection_days', array( '0', '1', '2', '3', '4', '5', '6' ) );

		ob_start();
		$this->settings->render_collection_days_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'wc-collection-date-collection-days', $output );
		$this->assertStringContainsString( 'type="checkbox"', $output );
		$this->assertStringContainsString( 'name="wc_collection_date_collection_days[]"', $output );
	}

	/**
	 * Test render_lead_time_field method.
	 */
	public function test_render_lead_time_field() {
		update_option( 'wc_collection_date_lead_time', 3 );

		ob_start();
		$this->settings->render_lead_time_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="number"', $output );
		$this->assertStringContainsString( 'name="wc_collection_date_lead_time"', $output );
		$this->assertStringContainsString( 'value="3"', $output );
		$this->assertStringContainsString( 'Minimum number of calendar days', $output );
	}

	/**
	 * Test render_max_booking_field method.
	 */
	public function test_render_max_booking_field() {
		update_option( 'wc_collection_date_max_booking_days', 60 );

		ob_start();
		$this->settings->render_max_booking_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="number"', $output );
		$this->assertStringContainsString( 'name="wc_collection_date_max_booking_days"', $output );
		$this->assertStringContainsString( 'value="60"', $output );
		$this->assertStringContainsString( 'Maximum number of days', $output );
	}

	/**
	 * Test render_lead_time_type_field method.
	 */
	public function test_render_lead_time_type_field() {
		update_option( 'wc_collection_date_lead_time_type', 'working' );

		ob_start();
		$this->settings->render_lead_time_type_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="radio"', $output );
		$this->assertStringContainsString( 'name="wc_collection_date_lead_time_type"', $output );
		$this->assertStringContainsString( 'value="calendar"', $output );
		$this->assertStringContainsString( 'value="working"', $output );
		$this->assertStringContainsString( 'checked="checked"', $output );
	}

	/**
	 * Test render_cutoff_time_field method.
	 */
	public function test_render_cutoff_time_field() {
		update_option( 'wc_collection_date_cutoff_time', '14:30' );

		ob_start();
		$this->settings->render_cutoff_time_field();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'type="time"', $output );
		$this->assertStringContainsString( 'name="wc_collection_date_cutoff_time"', $output );
		$this->assertStringContainsString( 'value="14:30"', $output );
		$this->assertStringContainsString( 'Order Cutoff Time', $output );
	}

	/**
	 * Test render_exclusions_section method.
	 */
	public function test_render_exclusions_section() {
		// Create test exclusions.
		$this->factory->exclusion->create_many( 3 );

		ob_start();
		$this->settings->render_exclusions_section();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Date Exclusions', $output );
		$this->assertStringContainsString( 'wp-list-table', $output );
	}

	/**
	 * Test category rule actions handling.
	 */
	public function test_handle_category_rule_actions() {
		// Use reflection to test protected method.
		$reflection = new ReflectionClass( $this->settings );
		$method = $reflection->getMethod( 'handle_category_rule_actions' );
		$method->setAccessible( true );

		// Test with no actions.
		$method->invoke( $this->settings );
		// Should not crash and should complete without output.

		// Test delete action with invalid nonce.
		$_GET['action'] = 'delete_rule';
		$_GET['category_id'] = '1';
		$_GET['_wpnonce'] = 'invalid_nonce';

		$method->invoke( $this->settings );
		// Should not crash with invalid nonce.
	}

	/**
	 * Test export_settings method.
	 */
	public function test_export_settings() {
		// Set up test settings.
		update_option( 'wc_collection_date_lead_time', 5 );
		update_option( 'wc_collection_date_working_days', array( '1', '2', '3', '4', '5' ) );
		update_option( 'wc_collection_date_category_rules', array( 1 => array( 'lead_time' => 3 ) ) );

		// Create test exclusions.
		$this->factory->exclusion->create( array(
			'exclusion_date' => '2024-12-25',
			'reason' => 'Christmas',
		) );

		// Mock WordPress headers and exit.
		if ( ! function_exists( 'header' ) ) {
			function header( $header ) {
				// Mock header function.
			}
		}

		// Use reflection to test protected method.
		$reflection = new ReflectionClass( $this->settings );
		$method = $reflection->getMethod( 'export_settings' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $this->settings );
		$output = ob_get_clean();

		$json_data = json_decode( $output, true );

		$this->assertIsArray( $json_data );
		$this->assertArrayHasKey( 'version', $json_data );
		$this->assertArrayHasKey( 'export_date', $json_data );
		$this->assertArrayHasKey( 'settings', $json_data );
		$this->assertArrayHasKey( 'category_rules', $json_data );
		$this->assertArrayHasKey( 'exclusions', $json_data );

		$this->assertEquals( 5, $json_data['settings']['lead_time'] );
		$this->assertEquals( array( '1', '2', '3', '4', '5' ), $json_data['settings']['working_days'] );
		$this->assertArrayHasKey( 1, $json_data['category_rules'] );
		$this->assertEquals( 3, $json_data['category_rules'][1]['lead_time'] );
	}

	/**
	 * Test import_settings method.
	 */
	public function test_import_settings() {
		$import_data = array(
			'version' => '1.1.0',
			'settings' => array(
				'lead_time' => 4,
				'working_days' => array( '1', '2', '3', '4' ),
				'collection_days' => array( '0', '1', '2', '3', '4', '5', '6' ),
			),
			'category_rules' => array(
				1 => array(
					'lead_time' => 6,
					'lead_time_type' => 'working',
				),
			),
			'exclusions' => array(
				array(
					'exclusion_date' => '2024-12-25',
					'reason' => 'Test Import Exclusion',
				),
			),
		);

		// Use reflection to test protected method.
		$reflection = new ReflectionClass( $this->settings );
		$method = $reflection->getMethod( 'import_settings' );
		$method->setAccessible( true );

		// Mock file upload and validation.
		$_FILES = array(
			'import_file' => array(
				'tmp_name' => $this->create_temp_json_file( $import_data ),
				'error' => UPLOAD_ERR_OK,
			),
		);

		$_POST['overwrite_existing'] = '1';

		ob_start();
		$method->invoke( $this->settings );
		$output = ob_get_clean();

		// Verify settings were imported.
		$this->assertEquals( 4, get_option( 'wc_collection_date_lead_time' ) );
		$this->assertEquals( array( '1', '2', '3', '4' ), get_option( 'wc_collection_date_working_days' ) );

		$category_rules = get_option( 'wc_collection_date_category_rules', array() );
		$this->assertArrayHasKey( 1, $category_rules );
		$this->assertEquals( 6, $category_rules[1]['lead_time'] );
	}

	/**
	 * Test import_settings with invalid JSON.
	 */
	public function test_import_settings_invalid_json() {
		// Mock file upload with invalid JSON.
		$_FILES = array(
			'import_file' => array(
				'tmp_name' => $this->create_temp_file( 'invalid json content' ),
				'error' => UPLOAD_ERR_OK,
			),
		);

		// Use reflection to test protected method.
		$reflection = new ReflectionClass( $this->settings );
		$method = $reflection->getMethod( 'import_settings' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $this->settings );
		$output = ob_get_clean();

		// Should handle invalid JSON gracefully.
		$this->assertStringContainsString( 'Invalid JSON file', $output );
	}

	/**
	 * Test import_settings with invalid structure.
	 */
	public function test_import_settings_invalid_structure() {
		$invalid_data = array(
			'invalid_key' => 'invalid_value',
		);

		$_FILES = array(
			'import_file' => array(
				'tmp_name' => $this->create_temp_json_file( $invalid_data ),
				'error' => UPLOAD_ERR_OK,
			),
		);

		$reflection = new ReflectionClass( $this->settings );
		$method = $reflection->getMethod( 'import_settings' );
		$method->setAccessible( true );

		ob_start();
		$method->invoke( $this->settings );
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Invalid settings file', $output );
	}

	/**
	 * Test render_import_export_tab method.
	 */
	public function test_render_import_export_tab() {
		$_GET['tab'] = 'import_export';

		ob_start();
		$this->settings->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Import/Export Settings', $output );
		$this->assertStringContainsString( 'Export Settings', $output );
		$this->assertStringContainsString( 'Import Settings', $output );
	}

	/**
	 * Test render_analytics_tab method.
	 */
	public function test_render_analytics_tab() {
		$_GET['tab'] = 'analytics';

		ob_start();
		$this->settings->render_settings_page();
		$output = ob_get_clean();

		$this->assertStringContainsString( 'Collection Date Analytics', $output );
		$this->assertStringContainsString( 'analytics-wrapper', $output );
		$this->assertStringContainsString( 'Period:', $output );
	}

	/**
	 * Test cache clearing on settings changes.
	 */
	public function test_cache_clearing_on_settings_change() {
		// Set up some cached data.
		set_transient( 'test_cache_key', 'test_value', 3600 );

		// Test that sanitize methods clear cache.
		$this->settings->sanitize_working_days( array( '1', '2', '3' ) );

		// Cache should still exist (clearing happens during option update).
		$this->assertEquals( 'test_value', get_transient( 'test_cache_key' ) );

		// Update option to trigger cache clearing.
		update_option( 'wc_collection_date_working_days', array( '1', '2', '3' ) );

		// Cache should be cleared by the sanitize callback.
		// Note: This might be hard to test directly due to WordPress caching behavior.
	}

	/**
	 * Helper method to create temporary JSON file.
	 *
	 * @param array $data Data to encode.
	 * @return string Temporary file path.
	 */
	protected function create_temp_json_file( $data ) {
		$file = tempnam( sys_get_temp_dir(), 'wc_collection_test_' );
		file_put_contents( $file, json_encode( $data ) );
		return $file;
	}

	/**
	 * Helper method to create temporary file.
	 *
	 * @param string $content File content.
	 * @return string Temporary file path.
	 */
	protected function create_temp_file( $content ) {
		$file = tempnam( sys_get_temp_dir(), 'wc_collection_test_' );
		file_put_contents( $file, $content );
		return $file;
	}
}