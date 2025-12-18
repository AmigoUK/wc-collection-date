<?php
/**
 * PHPUnit Bootstrap File
 *
 * Sets up the WordPress test environment for plugin testing.
 *
 * @package WC_Collection_Date\Tests
 */

// Define test environment constants.
define( 'WP_TESTS_DIR', getenv( 'WP_TESTS_DIR' ) ? getenv( 'WP_TESTS_DIR' ) : '/tmp/wordpress-tests-lib' );
define( 'WP_CORE_DIR', getenv( 'WP_CORE_DIR' ) ? getenv( 'WP_CORE_DIR' ) : '/tmp/wordpress' );

// Check if we're running in a CI environment.
$ci_environment = getenv( 'CI' ) || getenv( 'CONTINUOUS_INTEGRATION' );

// Require the WordPress testing framework.
if ( ! $ci_environment && ! file_exists( WP_TESTS_DIR . '/includes/functions.php' ) ) {
	echo "Could not find {$WP_TESTS_DIR}/includes/functions.php, have you run bin/install-wp-tests.sh ?" . PHP_EOL;
	exit( 1 );
}

// Load the WordPress test environment.
require_once WP_TESTS_DIR . '/includes/functions.php';

// Determine the plugin path dynamically.
$plugin_dir = dirname( dirname( __FILE__ ) );

/**
 * Load the plugin.
 */
function _manually_load_plugin() {
	global $plugin_dir;

	// Check if WooCommerce is loaded (for testing environment).
	if ( ! class_exists( 'WooCommerce' ) ) {
		// Try to load WooCommerce if it exists.
		$woocommerce_path = WP_PLUGIN_DIR . '/woocommerce/woocommerce.php';
		if ( file_exists( $woocommerce_path ) ) {
			require_once $woocommerce_path;
		}
	}

	require_once $plugin_dir . '/wc-collection-date.php';
}

// Add the filter to load the plugin.
tests_add_filter( 'muplugins_loaded', '_manually_load_plugin' );

// Start up the WP testing environment.
require_once WP_TESTS_DIR . '/includes/bootstrap.php';

// Include our custom test factories.
require_once __DIR__ . '/../factory/class-wc-collection-date-factory.php';

/**
 * Set up common test utilities.
 */
class WC_Collection_Date_Tests_Bootstrap {

	/**
	 * Singleton instance.
	 *
	 * @var self
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return self
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->includes();
		$this->setup_hooks();
	}

	/**
	 * Include required files.
	 */
	private function includes() {
		// Set up plugin constants if not already set.
		if ( ! defined( 'WC_COLLECTION_DATE_VERSION' ) ) {
			define( 'WC_COLLECTION_DATE_VERSION', '1.1.0' );
		}
		if ( ! defined( 'WC_COLLECTION_DATE_PLUGIN_DIR' ) ) {
			define( 'WC_COLLECTION_DATE_PLUGIN_DIR', dirname( dirname( __FILE__ ) ) . '/' );
		}
		if ( ! defined( 'WC_COLLECTION_DATE_PLUGIN_URL' ) ) {
			define( 'WC_COLLECTION_DATE_PLUGIN_URL', plugin_dir_url( dirname( dirname( __FILE__ ) ) . '/wc-collection-date.php' ) );
		}
	}

	/**
	 * Set up hooks.
	 */
	private function setup_hooks() {
		// Ensure WordPress is fully loaded.
		add_action( 'init', array( $this, 'init' ) );
	}

	/**
	 * Initialize testing environment.
	 */
	public function init() {
		// Create necessary database tables if they don't exist.
		$this->create_plugin_tables();

		// Set up default options for testing.
		$this->setup_default_options();
	}

	/**
	 * Create plugin database tables.
	 */
	private function create_plugin_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();

		// Create exclusions table.
		$table_name = $wpdb->prefix . 'wc_collection_exclusions';
		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			exclusion_date date NOT NULL,
			reason text NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY exclusion_date (exclusion_date)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Create analytics table.
		$table_name = $wpdb->prefix . 'wc_collection_date_analytics';
		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			collection_date date NOT NULL,
			selection_count int NOT NULL DEFAULT 0,
			total_orders int NOT NULL DEFAULT 0,
			total_value decimal(10,2) NOT NULL DEFAULT 0,
			avg_lead_time decimal(5,2) NOT NULL DEFAULT 0,
			last_selected datetime NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY collection_date (collection_date),
			KEY selection_count (selection_count),
			KEY last_selected (last_selected)
		) $charset_collate;";

		dbDelta( $sql );
	}

	/**
	 * Set up default options for testing.
	 */
	private function setup_default_options() {
		$default_options = array(
			'wc_collection_date_lead_time' => 2,
			'wc_collection_date_lead_time_type' => 'calendar',
			'wc_collection_date_cutoff_time' => '',
			'wc_collection_date_working_days' => array( '1', '2', '3', '4', '5', '6' ),
			'wc_collection_date_collection_days' => array( '0', '1', '2', '3', '4', '5', '6' ),
			'wc_collection_date_max_booking_days' => 90,
			'wc_collection_date_category_rules' => array(),
		);

		foreach ( $default_options as $option => $default ) {
			if ( false === get_option( $option ) ) {
				update_option( $option, $default );
			}
		}
	}

	/**
	 * Clean up test data.
	 */
	public static function cleanup() {
		global $wpdb;

		// Clean up plugin tables.
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wc_collection_exclusions" );
		$wpdb->query( "TRUNCATE TABLE {$wpdb->prefix}wc_collection_date_analytics" );

		// Clean up plugin options.
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

		// Clear all transients.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_wc_collection_date%'
			OR option_name LIKE '_transient_timeout_wc_collection_date%'"
		);
	}
}

// Initialize the bootstrap.
WC_Collection_Date_Tests_Bootstrap::instance();

/**
 * Custom error handler for debugging tests.
 */
set_error_handler( function( $errno, $errstr, $errfile, $errline ) {
	// Suppress warnings that come from WordPress core during testing.
	if ( E_WARNING === $errno && false !== strpos( $errfile, 'wp-includes' ) ) {
		return true;
	}

	// Handle other errors normally.
	return false;
}, E_WARNING );

/**
 * Helper function to get the factory.
 *
 * @return WC_Collection_Date_Factory
 */
function wc_collection_date_factory() {
	static $factory = null;

	if ( null === $factory ) {
		$factory = new WC_Collection_Date_Factory();
	}

	return $factory;
}