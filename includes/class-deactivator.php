<?php
/**
 * Plugin Deactivation Handler
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
 * Fired during plugin deactivation.
 *
 * This class defines all code necessary to run during the plugin's deactivation.
 *
 * @since      1.0.0
 * @package    WC_Collection_Date
 * @subpackage WC_Collection_Date/includes
 */
class WC_Collection_Date_Deactivator {

	/**
	 * Plugin deactivation handler.
	 *
	 * Performs cleanup operations when the plugin is deactivated.
	 * Note: Database tables and options are preserved for potential reactivation.
	 *
	 * @since 1.0.0
	 */
	public static function deactivate() {
		// Clear any scheduled cron jobs.
		self::clear_scheduled_hooks();

		// Clear transients.
		self::clear_transients();

		// Flush rewrite rules.
		flush_rewrite_rules();

		// Log deactivation timestamp.
		update_option( 'wc_collection_date_deactivated', current_time( 'timestamp' ) );
	}

	/**
	 * Clear scheduled cron hooks.
	 *
	 * Removes any scheduled tasks created by the plugin.
	 *
	 * @since 1.0.0
	 */
	private static function clear_scheduled_hooks() {
		// Clear any scheduled hooks (none yet, but prepared for future use).
		$scheduled_hooks = array(
			'wc_collection_date_daily_cleanup',
			'wc_collection_date_sync_exclusions',
		);

		foreach ( $scheduled_hooks as $hook ) {
			$timestamp = wp_next_scheduled( $hook );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, $hook );
			}
		}
	}

	/**
	 * Clear plugin transients.
	 *
	 * Removes all cached data stored in transients.
	 *
	 * @since 1.0.0
	 */
	private static function clear_transients() {
		global $wpdb;

		// Delete transients with plugin prefix.
		$transient_prefix = '_transient_wc_collection_date_';
		$timeout_prefix   = '_transient_timeout_wc_collection_date_';

		// Prepare and execute query safely.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->options}
				WHERE option_name LIKE %s
				OR option_name LIKE %s",
				$wpdb->esc_like( $transient_prefix ) . '%',
				$wpdb->esc_like( $timeout_prefix ) . '%'
			)
		);

		// Clear object cache if available.
		if ( function_exists( 'wp_cache_flush' ) ) {
			wp_cache_flush();
		}
	}

	/**
	 * Cleanup plugin data on uninstall.
	 *
	 * This method is not called during deactivation but provided
	 * for potential use in an uninstall.php file.
	 *
	 * @since 1.0.0
	 */
	public static function uninstall() {
		// Check if user has permission to delete plugins.
		if ( ! current_user_can( 'delete_plugins' ) ) {
			return;
		}

		global $wpdb;

		// Remove database tables.
		$table_name = $wpdb->prefix . 'wc_collection_exclusions';
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared

		// Remove plugin options.
		$options = array(
			'wc_collection_date_working_days',
			'wc_collection_date_lead_time',
			'wc_collection_date_max_booking_days',
			'wc_collection_date_version',
			'wc_collection_date_db_version',
			'wc_collection_date_activated',
			'wc_collection_date_deactivated',
		);

		foreach ( $options as $option ) {
			delete_option( $option );
		}

		// Clear all transients.
		self::clear_transients();

		// Remove order meta data.
		$wpdb->query(
			$wpdb->prepare(
				"DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s",
				'_collection_date'
			)
		);
	}
}
