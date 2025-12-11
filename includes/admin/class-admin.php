<?php
/**
 * Admin functionality
 *
 * @package WC_Collection_Date
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Admin class
 */
class WC_Collection_Date_Admin {

	/**
	 * Settings instance
	 *
	 * @var WC_Collection_Date_Settings
	 */
	private $settings;

	/**
	 * Initialize the class
	 */
	public function __construct() {
		// Load settings class.
		require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/admin/class-settings.php';
		$this->settings = new WC_Collection_Date_Settings();

		// Register hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Register admin menu
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Collection Dates', 'wc-collection-date' ),
			__( 'Collection Dates', 'wc-collection-date' ),
			'manage_woocommerce',
			'wc-collection-date',
			array( $this->settings, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_styles( $hook ) {
		// Only load on our settings page.
		if ( 'woocommerce_page_wc-collection-date' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wc-collection-date-admin',
			WC_COLLECTION_DATE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WC_COLLECTION_DATE_VERSION,
			'all'
		);
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on our settings page.
		if ( 'woocommerce_page_wc-collection-date' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'wc-collection-date-admin',
			WC_COLLECTION_DATE_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WC_COLLECTION_DATE_VERSION,
			true
		);

		// Pass data to JavaScript.
		wp_localize_script(
			'wc-collection-date-admin',
			'wcCollectionDate',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'wc_collection_date_admin' ),
			)
		);
	}
}
