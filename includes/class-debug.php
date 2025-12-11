<?php
/**
 * Debug Mode Handler
 *
 * Handles debug logging when WC_COLLECTION_DATE_DEBUG constant is enabled.
 *
 * @package WC_Collection_Date
 * @since 1.1.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Debug class.
 */
class WC_Collection_Date_Debug {

	/**
	 * Check if debug mode is enabled.
	 *
	 * @return bool True if debug mode enabled.
	 */
	public static function is_enabled() {
		return defined( 'WC_COLLECTION_DATE_DEBUG' ) && WC_COLLECTION_DATE_DEBUG === true;
	}

	/**
	 * Log debug message.
	 *
	 * Only logs if debug mode is enabled.
	 *
	 * @param string $message Message to log.
	 * @param string $context Optional context (e.g., 'date-calculation', 'api', 'cache').
	 * @param mixed  $data    Optional data to include.
	 */
	public static function log( $message, $context = 'general', $data = null ) {
		if ( ! self::is_enabled() ) {
			return;
		}

		$log_entry = sprintf(
			'[%s] [%s] %s',
			current_time( 'Y-m-d H:i:s' ),
			strtoupper( $context ),
			$message
		);

		if ( null !== $data ) {
			$log_entry .= ' | Data: ' . wp_json_encode( $data );
		}

		// Log to WordPress debug.log if WP_DEBUG_LOG is enabled.
		if ( defined( 'WP_DEBUG_LOG' ) && WP_DEBUG_LOG ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			error_log( 'WC Collection Date: ' . $log_entry );
		}

		// Also store in option for admin viewing.
		self::store_log_entry( $log_entry );
	}

	/**
	 * Store log entry in database for admin viewing.
	 *
	 * Keeps last 100 entries.
	 *
	 * @param string $log_entry Log entry to store.
	 */
	private static function store_log_entry( $log_entry ) {
		$logs = get_option( 'wc_collection_date_debug_logs', array() );

		// Add new entry at the beginning.
		array_unshift( $logs, $log_entry );

		// Keep only last 100 entries.
		$logs = array_slice( $logs, 0, 100 );

		update_option( 'wc_collection_date_debug_logs', $logs, false );
	}

	/**
	 * Get debug logs.
	 *
	 * @param int $limit Number of logs to retrieve.
	 * @return array Array of log entries.
	 */
	public static function get_logs( $limit = 50 ) {
		$logs = get_option( 'wc_collection_date_debug_logs', array() );
		return array_slice( $logs, 0, $limit );
	}

	/**
	 * Clear all debug logs.
	 *
	 * @return bool True on success.
	 */
	public static function clear_logs() {
		return delete_option( 'wc_collection_date_debug_logs' );
	}

	/**
	 * Log cache operation.
	 *
	 * @param string $operation Operation type (hit, miss, clear).
	 * @param string $key       Cache key.
	 */
	public static function log_cache( $operation, $key ) {
		self::log(
			sprintf( 'Cache %s: %s', $operation, $key ),
			'cache'
		);
	}

	/**
	 * Log date calculation.
	 *
	 * @param array $settings Settings used for calculation.
	 * @param int   $limit    Number of dates calculated.
	 * @param array $dates    Calculated dates.
	 */
	public static function log_date_calculation( $settings, $limit, $dates ) {
		self::log(
			sprintf( 'Calculated %d dates with limit %d', count( $dates ), $limit ),
			'date-calc',
			array(
				'lead_time'      => $settings['lead_time'] ?? null,
				'lead_time_type' => $settings['lead_time_type'] ?? null,
				'first_date'     => $dates[0] ?? null,
				'last_date'      => end( $dates ) ?: null,
			)
		);
	}

	/**
	 * Log API request.
	 *
	 * @param string $endpoint Endpoint called.
	 * @param array  $params   Request parameters.
	 * @param mixed  $response Response data.
	 */
	public static function log_api( $endpoint, $params, $response ) {
		self::log(
			sprintf( 'API Request: %s', $endpoint ),
			'api',
			array(
				'params'   => $params,
				'response' => $response,
			)
		);
	}
}
