<?php
/**
 * Date Calculator Class
 *
 * Handles date availability calculations based on settings and exclusions.
 *
 * @package WC_Collection_Date
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Date Calculator class.
 */
class WC_Collection_Date_Calculator {

	/**
	 * Cache duration in seconds (1 hour).
	 *
	 * @var int
	 */
	const CACHE_DURATION = 3600;

	/**
	 * Get available collection dates.
	 *
	 * @param int $limit Number of dates to return (default 90).
	 * @return array Array of available dates in Y-m-d format.
	 */
	public function get_available_dates( $limit = 90 ) {
		// Try to get from cache first.
		$cache_key = $this->get_cache_key( 'global', $limit );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		// Get global settings.
		$settings = array(
			'lead_time'       => absint( get_option( 'wc_collection_date_lead_time', 2 ) ),
			'lead_time_type'  => get_option( 'wc_collection_date_lead_time_type', 'calendar' ),
			'cutoff_time'     => get_option( 'wc_collection_date_cutoff_time', '' ),
			'working_days'    => get_option( 'wc_collection_date_working_days', array( '1', '2', '3', '4', '5', '6' ) ),
			'collection_days' => get_option( 'wc_collection_date_collection_days', array( '0', '1', '2', '3', '4', '5', '6' ) ),
		);

		$dates = $this->calculate_dates_from_settings( $settings, $limit );

		// Cache the result.
		set_transient( $cache_key, $dates, self::CACHE_DURATION );

		return $dates;
	}

	/**
	 * Get available collection dates for a specific product.
	 *
	 * Uses category rules if available, otherwise falls back to global settings.
	 *
	 * @param int $product_id Product ID.
	 * @param int $limit Number of dates to return (default 90).
	 * @return array Array of available dates in Y-m-d format.
	 */
	public function get_available_dates_for_product( $product_id, $limit = 90 ) {
		// Try to get from cache first.
		$cache_key = $this->get_cache_key( 'product_' . $product_id, $limit );
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		$resolver = new WC_Collection_Date_Lead_Time_Resolver();
		$settings = $resolver->get_effective_settings( $product_id );

		$dates = $this->calculate_dates_from_settings( $settings, $limit );

		// Cache the result.
		set_transient( $cache_key, $dates, self::CACHE_DURATION );

		return $dates;
	}

	/**
	 * Calculate available dates based on provided settings.
	 *
	 * @param array $settings Settings array containing lead_time, lead_time_type, etc.
	 * @param int   $limit Number of dates to return.
	 * @return array Array of available dates in Y-m-d format.
	 */
	protected function calculate_dates_from_settings( $settings, $limit = 90 ) {
		$available_dates  = array();
		$lead_time        = isset( $settings['lead_time'] ) ? absint( $settings['lead_time'] ) : 2;
		$max_days         = absint( get_option( 'wc_collection_date_max_booking_days', 90 ) );
		$lead_time_type   = isset( $settings['lead_time_type'] ) ? $settings['lead_time_type'] : 'calendar';
		$working_days     = isset( $settings['working_days'] ) ? $settings['working_days'] : array( '1', '2', '3', '4', '5', '6' );
		$collection_days  = isset( $settings['collection_days'] ) ? $settings['collection_days'] : array( '0', '1', '2', '3', '4', '5', '6' );
		$cutoff_time      = isset( $settings['cutoff_time'] ) ? $settings['cutoff_time'] : '';

		if ( ! is_array( $working_days ) ) {
			$working_days = array();
		}

		if ( ! is_array( $collection_days ) ) {
			$collection_days = array();
		}

		// Convert to integers for comparison.
		$working_days    = array_map( 'intval', $working_days );
		$collection_days = array_map( 'intval', $collection_days );

		// Apply cutoff time penalty.
		$lead_time = $this->apply_cutoff_time_penalty( $lead_time, $cutoff_time );

		// Calculate start date based on lead time type.
		$start_date = new DateTime( 'now', wp_timezone() );

		if ( 'working' === $lead_time_type ) {
			// Use working days calculation (for production time).
			$start_date = $this->calculate_working_days_forward( $start_date, $lead_time + 1 );
		} else {
			// Use calendar days (backward compatible).
			$start_date->modify( '+' . ( $lead_time + 1 ) . ' days' );
		}

		$excluded_dates = $this->get_excluded_dates();
		$dates_found    = 0;
		$days_checked   = 0;

		while ( $dates_found < $limit && $days_checked < $max_days ) {
			$date_string = $start_date->format( 'Y-m-d' );
			$day_of_week = (int) $start_date->format( 'w' ); // 0 (Sunday) to 6 (Saturday).

			// Check if date is available for collection and not excluded.
			if ( in_array( $day_of_week, $collection_days, true ) && ! in_array( $date_string, $excluded_dates, true ) ) {
				$available_dates[] = $date_string;
				$dates_found++;
			}

			$start_date->modify( '+1 day' );
			$days_checked++;
		}

		return $available_dates;
	}

	/**
	 * Get excluded dates from database.
	 *
	 * @return array Array of excluded dates in Y-m-d format.
	 */
	protected function get_excluded_dates() {
		// Try to get from cache first.
		$cache_key = 'wc_collection_date_exclusions';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached && is_array( $cached ) ) {
			return $cached;
		}

		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		// Check if table exists.
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) !== $table_name ) {
			return array();
		}

		$results = $wpdb->get_col(
			"SELECT exclusion_date
			FROM {$table_name}
			ORDER BY exclusion_date ASC"
		);

		$exclusions = $results ? $results : array();

		// Cache the result.
		set_transient( $cache_key, $exclusions, self::CACHE_DURATION );

		return $exclusions;
	}

	/**
	 * Check if a specific date is available.
	 *
	 * @param string $date Date to check in Y-m-d format.
	 * @return bool True if available, false otherwise.
	 */
	public function is_date_available( $date ) {
		if ( ! $this->is_valid_date_format( $date ) ) {
			return false;
		}

		$available_dates = $this->get_available_dates( 365 );
		return in_array( $date, $available_dates, true );
	}

	/**
	 * Validate date format.
	 *
	 * @param string $date Date string to validate.
	 * @return bool True if valid Y-m-d format, false otherwise.
	 */
	protected function is_valid_date_format( $date ) {
		$datetime = DateTime::createFromFormat( 'Y-m-d', $date );
		return $datetime && $datetime->format( 'Y-m-d' ) === $date;
	}

	/**
	 * Get the earliest available collection date.
	 *
	 * @return string|null Earliest available date in Y-m-d format or null if none available.
	 */
	public function get_earliest_date() {
		$available_dates = $this->get_available_dates( 1 );
		return ! empty( $available_dates ) ? $available_dates[0] : null;
	}

	/**
	 * Get formatted date for display.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @param string $format Date format (default: WordPress date format).
	 * @return string Formatted date string.
	 */
	public function format_date_for_display( $date, $format = '' ) {
		if ( empty( $format ) ) {
			$format = get_option( 'date_format' );
		}

		try {
			$datetime = new DateTime( $date, wp_timezone() );
			return $datetime->format( $format );
		} catch ( Exception $e ) {
			return $date;
		}
	}

	/**
	 * Get date range information.
	 *
	 * @return array Array with min_date and max_date.
	 */
	public function get_date_range() {
		$lead_time = absint( get_option( 'wc_collection_date_lead_time', 2 ) );
		$max_days  = absint( get_option( 'wc_collection_date_max_booking_days', 90 ) );

		$min_date = new DateTime( 'now', wp_timezone() );
		$min_date->modify( '+' . ( $lead_time + 1 ) . ' days' );

		$max_date = new DateTime( 'now', wp_timezone() );
		$max_date->modify( '+' . $max_days . ' days' );

		return array(
			'min_date' => $min_date->format( 'Y-m-d' ),
			'max_date' => $max_date->format( 'Y-m-d' ),
		);
	}

	/**
	 * Calculate future date by adding working days only.
	 *
	 * @param DateTime $start_date Starting date.
	 * @param int      $days Number of working days to add.
	 * @return DateTime Result date.
	 */
	protected function calculate_working_days_forward( $start_date, $days ) {
		$working_days     = get_option( 'wc_collection_date_working_days', array( '1', '2', '3', '4', '5', '6' ) );
		$working_days     = array_map( 'intval', (array) $working_days );
		$excluded_dates   = $this->get_excluded_dates();
		$current          = clone $start_date;
		$days_added       = 0;

		// Safety limit to prevent infinite loops.
		$max_iterations = $days * 3 + 365;
		$iterations     = 0;

		while ( $days_added < $days && $iterations < $max_iterations ) {
			$current->modify( '+1 day' );
			$day_of_week = (int) $current->format( 'w' );
			$date_string = $current->format( 'Y-m-d' );

			// Check if this is a working day and not excluded.
			if ( in_array( $day_of_week, $working_days, true ) &&
				! in_array( $date_string, $excluded_dates, true ) ) {
				$days_added++;
			}

			$iterations++;
		}

		return $current;
	}

	/**
	 * Apply cutoff time penalty if current time is past cutoff.
	 *
	 * @param int    $lead_time Base lead time in days.
	 * @param string $cutoff Cutoff time in HH:MM format (optional).
	 * @return int Adjusted lead time.
	 */
	protected function apply_cutoff_time_penalty( $lead_time, $cutoff = '' ) {
		if ( empty( $cutoff ) ) {
			return $lead_time; // No cutoff configured.
		}

		$current_time = current_time( 'H:i' );

		if ( $current_time > $cutoff ) {
			return $lead_time + 1; // Past cutoff, add penalty day.
		}

		return $lead_time;
	}

	/**
	 * Generate cache key for date calculations.
	 *
	 * @param string $context Context (e.g., 'global', 'product_123').
	 * @param int    $limit   Number of dates to fetch.
	 * @return string Cache key.
	 */
	protected function get_cache_key( $context, $limit ) {
		$current_date = current_time( 'Y-m-d' );
		$current_hour = current_time( 'H' );

		// Include current date and hour so cache refreshes daily and after cutoff.
		return sprintf(
			'wc_collection_dates_%s_%d_%s_%s',
			$context,
			$limit,
			$current_date,
			$current_hour
		);
	}

	/**
	 * Clear all cached date calculations.
	 *
	 * Call this when settings or exclusions change.
	 *
	 * @return bool True on success.
	 */
	public static function clear_cache() {
		global $wpdb;

		// Delete all transients with our prefix.
		$wpdb->query(
			"DELETE FROM {$wpdb->options}
			WHERE option_name LIKE '_transient_wc_collection_date%'
			OR option_name LIKE '_transient_timeout_wc_collection_date%'"
		);

		return true;
	}
}
