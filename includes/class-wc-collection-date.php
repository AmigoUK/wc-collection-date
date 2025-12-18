<?php
/**
 * Main Plugin Class
 *
 * @package    WC_Collection_Date
 * @subpackage WC_Collection_Date/includes
 * @since      1.0.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The core plugin class.
 *
 * This is used to define internationalization, admin-specific hooks, and
 * public-facing site hooks.
 *
 * @since      1.0.0
 * @package    WC_Collection_Date
 * @subpackage WC_Collection_Date/includes
 */
class WC_Collection_Date {

	/**
	 * The single instance of the class.
	 *
	 * @since  1.0.0
	 * @var    WC_Collection_Date|null
	 */
	protected static $instance = null;

	/**
	 * Plugin version.
	 *
	 * @since  1.0.0
	 * @var    string
	 */
	protected $version = '1.2.0';

	/**
	 * Main WC_Collection_Date Instance.
	 *
	 * Ensures only one instance of WC_Collection_Date is loaded or can be loaded.
	 *
	 * @since  1.0.0
	 * @static
	 * @return WC_Collection_Date Main instance.
	 */
	public static function get_instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Cloning is forbidden.
	 *
	 * @since 1.0.0
	 */
	private function __clone() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Cloning is forbidden.', 'wc-collection-date' ), '1.0.0' );
	}

	/**
	 * Unserializing instances of this class is forbidden.
	 *
	 * @since 1.0.0
	 */
	public function __wakeup() {
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Unserializing instances is forbidden.', 'wc-collection-date' ), '1.0.0' );
	}

	/**
	 * WC_Collection_Date Constructor.
	 *
	 * @since 1.0.0
	 */
	private function __construct() {
		$this->define_constants();
		$this->includes();
		$this->define_hooks();
	}

	/**
	 * Define plugin constants.
	 *
	 * @since 1.0.0
	 */
	private function define_constants() {
		if ( ! defined( 'WC_COLLECTION_DATE_VERSION' ) ) {
			define( 'WC_COLLECTION_DATE_VERSION', $this->version );
		}
	}

	/**
	 * Include required core files.
	 *
	 * @since 1.0.0
	 */
	private function includes() {
		// Load debug class first (for use in other classes).
		require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/class-debug.php';

		// Load core classes.
		require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/class-lead-time-resolver.php';
		require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/class-date-calculator.php';
		require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/class-checkout.php';
		require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/class-rest-api.php';
		require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/class-block-checkout-integration.php';
		require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/class-analytics.php';

		// Include admin classes if in admin context.
		if ( is_admin() ) {
			$this->load_admin_classes();
		}

		// Initialize components.
		$this->init_components();
	}

	/**
	 * Load admin-specific classes.
	 *
	 * @since 1.0.0
	 */
	private function load_admin_classes() {
		require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/admin/class-admin.php';
		new WC_Collection_Date_Admin();
	}

	/**
	 * Initialize plugin components.
	 *
	 * @since 1.0.0
	 */
	private function init_components() {
		// Initialize checkout integration.
		new WC_Collection_Date_Checkout();

		// Initialize REST API.
		new WC_Collection_Date_REST_API();

		// Initialize Block Checkout integration.
		new WC_Collection_Date_Block_Integration();

		// Initialize analytics tracking.
		if ( class_exists( 'WC_Collection_Date_Analytics' ) ) {
			WC_Collection_Date_Analytics::instance();
		}
	}

	/**
	 * Define hooks and filters.
	 *
	 * @since 1.0.0
	 */
	private function define_hooks() {
		// Enqueue admin scripts and styles.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	/**
	 * Enqueue admin assets.
	 *
	 * @since 1.0.0
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		// Only load on WooCommerce settings pages.
		if ( ! in_array( $hook, array( 'woocommerce_page_wc-settings' ), true ) ) {
			return;
		}

		wp_enqueue_style(
			'wc-collection-date-admin',
			WC_COLLECTION_DATE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WC_COLLECTION_DATE_VERSION
		);

		wp_enqueue_script(
			'wc-collection-date-admin',
			WC_COLLECTION_DATE_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WC_COLLECTION_DATE_VERSION,
			true
		);
	}


	/**
	 * Run the plugin.
	 *
	 * @since 1.0.0
	 */
	public function run() {
		// Plugin is now running with all hooks defined.
	}

	/**
	 * Get plugin version.
	 *
	 * @since  1.0.0
	 * @return string Plugin version.
	 */
	public function get_version() {
		return $this->version;
	}
}
