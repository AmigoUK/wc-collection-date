<?php
/**
 * Plugin Activation Handler
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
 * Fired during plugin activation.
 *
 * This class defines all code necessary to run during the plugin's activation.
 *
 * @since      1.0.0
 * @package    WC_Collection_Date
 * @subpackage WC_Collection_Date/includes
 */
class WC_Collection_Date_Activator {

	/**
	 * Plugin activation handler.
	 *
	 * Checks for WooCommerce dependency, creates database tables,
	 * and sets default plugin options.
	 *
	 * @since 1.0.0
	 */
	public static function activate() {
		// Check if WooCommerce is active.
		if ( ! self::is_woocommerce_active() ) {
			deactivate_plugins( plugin_basename( WC_COLLECTION_DATE_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'WooCommerce Collection Date requires WooCommerce to be installed and active.', 'wc-collection-date' ),
				esc_html__( 'Plugin Activation Error', 'wc-collection-date' ),
				array( 'back_link' => true )
			);
		}

		// Check PHP version requirement.
		if ( version_compare( PHP_VERSION, '7.4', '<' ) ) {
			deactivate_plugins( plugin_basename( WC_COLLECTION_DATE_PLUGIN_FILE ) );
			wp_die(
				esc_html__( 'WooCommerce Collection Date requires PHP version 7.4 or higher.', 'wc-collection-date' ),
				esc_html__( 'Plugin Activation Error', 'wc-collection-date' ),
				array( 'back_link' => true )
			);
		}

		// Create database tables.
		self::create_tables();

		// Set default options.
		self::set_default_options();

		// Flush rewrite rules.
		flush_rewrite_rules();
	}

	/**
	 * Check if WooCommerce is active.
	 *
	 * @since  1.0.0
	 * @return bool True if WooCommerce is active, false otherwise.
	 */
	private static function is_woocommerce_active() {
		// Check if WooCommerce is active in single site.
		if ( in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ), true ) ) {
			return true;
		}

		// Check if WooCommerce is active in multisite.
		if ( is_multisite() ) {
			$plugins = get_site_option( 'active_sitewide_plugins' );
			if ( isset( $plugins['woocommerce/woocommerce.php'] ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Create database tables.
	 *
	 * Creates the wp_wc_collection_exclusions table for storing
	 * excluded collection dates.
	 *
	 * @since 1.0.0
	 */
	private static function create_tables() {
		global $wpdb;

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = $wpdb->prefix . 'wc_collection_exclusions';

		$sql = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			exclusion_date date NOT NULL,
			reason varchar(255) DEFAULT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY exclusion_date (exclusion_date),
			KEY created_at (created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		// Store database version.
		update_option( 'wc_collection_date_db_version', '1.0.0' );
	}

	/**
	 * Set default plugin options.
	 *
	 * Sets up default configuration values for the plugin.
	 *
	 * @since 1.0.0
	 */
	private static function set_default_options() {
		// Default working days (Monday to Saturday) - numeric format (1=Mon, 6=Sat).
		if ( false === get_option( 'wc_collection_date_working_days' ) ) {
			update_option( 'wc_collection_date_working_days', array( '1', '2', '3', '4', '5', '6' ) );
		}

		// Default collection days (all days) - numeric format (0=Sun, 6=Sat).
		if ( false === get_option( 'wc_collection_date_collection_days' ) ) {
			update_option( 'wc_collection_date_collection_days', array( '0', '1', '2', '3', '4', '5', '6' ) );
		}

		// Lead time in days (minimum days before collection).
		if ( false === get_option( 'wc_collection_date_lead_time' ) ) {
			update_option( 'wc_collection_date_lead_time', 2 );
		}

		// Lead time calculation type (calendar or working).
		if ( false === get_option( 'wc_collection_date_lead_time_type' ) ) {
			update_option( 'wc_collection_date_lead_time_type', 'calendar' );
		}

		// Cutoff time (optional, empty by default).
		if ( false === get_option( 'wc_collection_date_cutoff_time' ) ) {
			update_option( 'wc_collection_date_cutoff_time', '' );
		}

		// Maximum booking days in advance.
		if ( false === get_option( 'wc_collection_date_max_booking_days' ) ) {
			update_option( 'wc_collection_date_max_booking_days', 90 );
		}

		// Category rules (empty by default).
		if ( false === get_option( 'wc_collection_date_category_rules' ) ) {
			update_option( 'wc_collection_date_category_rules', array() );
		}

		// Store plugin version.
		update_option( 'wc_collection_date_version', '1.0.0' );

		// Set activation timestamp.
		if ( false === get_option( 'wc_collection_date_activated' ) ) {
			update_option( 'wc_collection_date_activated', current_time( 'timestamp' ) );
		}
	}

	/**
	 * Get table name with prefix.
	 *
	 * @since  1.0.0
	 * @return string Table name with WordPress prefix.
	 */
	public static function get_table_name() {
		global $wpdb;
		return $wpdb->prefix . 'wc_collection_exclusions';
	}
}
