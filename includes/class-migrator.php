<?php
/**
 * Database Migration Handler
 *
 * @package    WC_Collection_Date
 * @subpackage WC_Collection_Date/includes
 * @since      1.4.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Database migration class.
 *
 * Handles database schema updates for new features.
 *
 * @since 1.4.0
 */
class WC_Collection_Date_Migrator {

	/**
	 * Current database version.
	 *
	 * @since 1.4.0
	 * @var string
	 */
	private static $current_version = '1.4.0';

	/**
	 * Run necessary migrations.
	 *
	 * @since 1.4.0
	 */
	public static function migrate() {
		$saved_version = get_option( 'wc_collection_date_db_version', '1.0.0' );

		if ( version_compare( $saved_version, self::$current_version, '<' ) ) {
			self::migrate_to_1_4_0();
			update_option( 'wc_collection_date_db_version', self::$current_version );
		}
	}

	/**
	 * Migrate database to version 1.4.0.
	 *
	 * Adds date range support to exclusions table.
	 *
	 * @since 1.4.0
	 */
	private static function migrate_to_1_4_0() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_exclusions';
		$charset_collate = $wpdb->get_charset_collate();

		// Check if the new columns already exist.
		$column_exists = $wpdb->get_results(
			"SELECT COLUMN_NAME
			 FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = '" . DB_NAME . "'
			   AND TABLE_NAME = '{$table_name}'
			   AND COLUMN_NAME = 'exclusion_type'"
		);

		if ( empty( $column_exists ) ) {
			// Add new columns for date range support.
			$sql = "ALTER TABLE {$table_name}
					ADD COLUMN exclusion_type ENUM('single', 'range') NOT NULL DEFAULT 'single' AFTER exclusion_date,
					ADD COLUMN exclusion_start date NULL AFTER exclusion_type,
					ADD COLUMN exclusion_end date NULL AFTER exclusion_start,
					DROP INDEX exclusion_date,
					ADD INDEX idx_exclusion_date (exclusion_date),
					ADD INDEX idx_date_range (exclusion_start, exclusion_end),
					ADD INDEX idx_exclusion_type (exclusion_type)";

			require_once ABSPATH . 'wp-admin/includes/upgrade.php';
			dbDelta( $sql );
		}
	}
}