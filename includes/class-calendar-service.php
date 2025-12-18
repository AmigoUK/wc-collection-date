<?php
/**
 * Calendar Service Class
 *
 * Handles all calendar-related functionality including capacity management,
 * booking calendar data, and availability calculations.
 *
 * @package WC_Collection_Date
 * @subpackage WC_Collection_Date/includes
 * @since 1.3.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calendar Service class.
 *
 * @since 1.3.0
 */
class WC_Collection_Date_Calendar_Service {

	/**
	 * Database table name for capacity management.
	 *
	 * @var string
	 */
	protected $capacity_table;

	/**
	 * Database table name for analytics.
	 *
	 * @var string
	 */
	protected $analytics_table;

	/**
	 * Date calculator instance.
	 *
	 * @var WC_Collection_Date_Calculator
	 */
	protected $calculator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		global $wpdb;
		$this->capacity_table = $wpdb->prefix . 'wc_collection_date_capacity';
		$this->analytics_table = $wpdb->prefix . 'wc_collection_date_analytics';
		$this->calculator = new WC_Collection_Date_Calculator();
	}

	/**
	 * Get calendar data for a specific month.
	 *
	 * @param string $year_month Year and month in Y-m format.
	 * @return array Calendar data with daily information.
	 */
	public function get_calendar_data( $year_month ) {
		// Validate input format
		if ( ! preg_match( '/^\d{4}-\d{2}$/', $year_month ) ) {
			return array( 'error' => 'Invalid date format. Use Y-m format.' );
		}

		list( $year, $month ) = explode( '-', $year_month );
		$year = absint( $year );
		$month = absint( $month );

		// Validate date
		if ( $year < 2020 || $year > 2030 || $month < 1 || $month > 12 ) {
			return array( 'error' => 'Invalid date range.' );
		}

		// Get first and last day of month
		$first_day = new DateTime( "{$year}-{$month}-01" );
		$last_day = clone $first_day;
		$last_day->modify( 'last day of this month' );

		// Get all days in month
		$days = array();
		$current = clone $first_day;

		while ( $current <= $last_day ) {
			$days[] = $this->get_day_data( $current );
			$current->modify( '+1 day' );
		}

		// Get month summary
		$month_summary = $this->get_month_summary( $year, $month );

		return array(
			'year' => $year,
			'month' => $month,
			'month_name' => $first_day->format( 'F Y' ),
			'days' => $days,
			'summary' => $month_summary,
		);
	}

	/**
	 * Get detailed information for a specific day.
	 *
	 * @param DateTime $date Date to get information for.
	 * @return array Day information.
	 */
	protected function get_day_data( DateTime $date ) {
		$date_str = $date->format( 'Y-m-d' );
		$day_of_week = absint( $date->format( 'w' ) ); // 0 = Sunday, 6 = Saturday

		// Get basic availability
		$is_available = $this->calculator->is_date_available( $date_str );
		$is_working_day = $this->is_working_day( $day_of_week );
		$is_collection_day = $this->is_collection_day( $day_of_week );

		// Get capacity information
		$capacity = $this->get_day_capacity( $date_str );

		// Get analytics data
		$analytics = $this->get_day_analytics( $date_str );

		// Determine day status
		$status = $this->determine_day_status( $is_available, $is_working_day, $is_collection_day, $capacity );

		return array(
			'date' => $date_str,
			'day_of_week' => $day_of_week,
			'day_name' => $date->format( 'l' ),
			'day_number' => absint( $date->format( 'd' ) ),
			'is_available' => $is_available,
			'is_working_day' => $is_working_day,
			'is_collection_day' => $is_collection_day,
			'capacity' => $capacity,
			'analytics' => $analytics,
			'status' => $status,
		);
	}

	/**
	 * Get capacity information for a specific date.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return array Capacity information.
	 */
	protected function get_day_capacity( $date ) {
		global $wpdb;

		// Check if capacity management is enabled
		if ( ! $this->is_capacity_enabled() ) {
			return array(
				'enabled' => false,
				'max_capacity' => 0,
				'current_bookings' => 0,
				'available_slots' => 0,
				'utilization' => 0,
			);
		}

		// Get capacity from database
		$capacity = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT max_capacity, current_bookings, available_slots, is_enabled, notes
				FROM {$this->capacity_table}
				WHERE collection_date = %s",
				$date
			),
			ARRAY_A
		);

		if ( ! $capacity ) {
			// Create default capacity entry
			$default_capacity = $this->get_default_capacity();
			$capacity = array(
				'max_capacity' => $default_capacity,
				'current_bookings' => 0,
				'available_slots' => $default_capacity,
				'is_enabled' => 1,
				'notes' => null,
			);
		}

		// Calculate utilization
		$utilization = 0;
		if ( $capacity['max_capacity'] > 0 ) {
			$utilization = round( ( $capacity['current_bookings'] / $capacity['max_capacity'] ) * 100, 2 );
		}

		return array(
			'enabled' => true,
			'max_capacity' => absint( $capacity['max_capacity'] ),
			'current_bookings' => absint( $capacity['current_bookings'] ),
			'available_slots' => absint( $capacity['available_slots'] ),
			'utilization' => $utilization,
			'is_day_enabled' => absint( $capacity['is_enabled'] ) === 1,
			'notes' => $capacity['notes'],
		);
	}

	/**
	 * Get analytics data for a specific date.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return array Analytics information.
	 */
	protected function get_day_analytics( $date ) {
		global $wpdb;

		$analytics = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT selection_count, total_orders, total_value, avg_lead_time, last_selected
				FROM {$this->analytics_table}
				WHERE collection_date = %s",
				$date
			),
			ARRAY_A
		);

		if ( ! $analytics ) {
			return array(
				'selection_count' => 0,
				'total_orders' => 0,
				'total_value' => 0,
				'avg_lead_time' => 0,
				'last_selected' => null,
			);
		}

		return array(
			'selection_count' => absint( $analytics['selection_count'] ),
			'total_orders' => absint( $analytics['total_orders'] ),
			'total_value' => floatval( $analytics['total_value'] ),
			'avg_lead_time' => floatval( $analytics['avg_lead_time'] ),
			'last_selected' => $analytics['last_selected'],
		);
	}

	/**
	 * Determine the status of a day based on various factors.
	 *
	 * @param bool $is_available Whether date is available.
	 * @param bool $is_working_day Whether it's a working day.
	 * @param bool $is_collection_day Whether it's a collection day.
	 * @param array $capacity Capacity information.
	 * @return string Day status.
	 */
	protected function determine_day_status( $is_available, $is_working_day, $is_collection_day, $capacity ) {
		// Check basic availability
		if ( ! $is_available ) {
			return 'unavailable';
		}

		// Check if it's a working and collection day
		if ( ! $is_working_day || ! $is_collection_day ) {
			return 'non-working';
		}

		// Check capacity if enabled
		if ( $capacity['enabled'] ) {
			if ( ! $capacity['is_day_enabled'] ) {
				return 'disabled';
			}

			if ( $capacity['available_slots'] <= 0 ) {
				return 'full';
			}

			$utilization = $capacity['utilization'];
			if ( $utilization >= 90 ) {
				return 'high-usage';
			} elseif ( $utilization >= 75 ) {
				return 'moderate-usage';
			}
		}

		return 'available';
	}

	/**
	 * Get month summary statistics.
	 *
	 * @param int $year Year.
	 * @param int $month Month.
	 * @return array Month summary.
	 */
	protected function get_month_summary( $year, $month ) {
		global $wpdb;

		$month_start = "{$year}-{$month}-01";
		$month_end = date( 'Y-m-t', strtotime( $month_start ) );

		// Get capacity summary
		$capacity_summary = array(
			'enabled' => $this->is_capacity_enabled(),
		);

		if ( $capacity_summary['enabled'] ) {
			$capacity_stats = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT
						COUNT(*) as total_days,
						SUM(max_capacity) as total_capacity,
						SUM(current_bookings) as total_bookings,
						SUM(available_slots) as total_available
					FROM {$this->capacity_table}
					WHERE collection_date BETWEEN %s AND %s AND is_enabled = 1",
					$month_start,
					$month_end
				),
				ARRAY_A
			);

			$capacity_summary['total_days'] = absint( $capacity_stats['total_days'] );
			$capacity_summary['total_capacity'] = absint( $capacity_stats['total_capacity'] );
			$capacity_summary['total_bookings'] = absint( $capacity_stats['total_bookings'] );
			$capacity_summary['total_available'] = absint( $capacity_stats['total_available'] );
			$capacity_summary['utilization'] = $capacity_summary['total_capacity'] > 0
				? round( ( $capacity_summary['total_bookings'] / $capacity_summary['total_capacity'] ) * 100, 2 )
				: 0;
		}

		// Get analytics summary
		$analytics_stats = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT
					COUNT(*) as total_days,
					SUM(selection_count) as total_selections,
					SUM(total_orders) as total_orders,
					SUM(total_value) as total_value,
					AVG(avg_lead_time) as avg_lead_time
				FROM {$this->analytics_table}
				WHERE collection_date BETWEEN %s AND %s",
				$month_start,
				$month_end
			),
			ARRAY_A
		);

		$analytics_summary = array(
			'total_days' => absint( $analytics_stats['total_days'] ),
			'total_selections' => absint( $analytics_stats['total_selections'] ),
			'total_orders' => absint( $analytics_stats['total_orders'] ),
			'total_value' => floatval( $analytics_stats['total_value'] ),
			'avg_lead_time' => floatval( $analytics_stats['avg_lead_time'] ),
		);

		return array(
			'capacity' => $capacity_summary,
			'analytics' => $analytics_summary,
		);
	}

	/**
	 * Update capacity settings for a specific date.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @param array  $settings Capacity settings.
	 * @return bool|WP_Error Success or error.
	 */
	public function update_capacity_settings( $date, $settings ) {
		global $wpdb;

		// Validate date
		if ( ! $this->validate_date( $date ) ) {
			return new WP_Error( 'invalid_date', 'Invalid date format.' );
		}

		// Validate settings
		$max_capacity = isset( $settings['max_capacity'] ) ? absint( $settings['max_capacity'] ) : 0;
		$current_bookings = isset( $settings['current_bookings'] ) ? absint( $settings['current_bookings'] ) : 0;
		$is_enabled = isset( $settings['is_enabled'] ) ? (bool) $settings['is_enabled'] : true;
		$notes = isset( $settings['notes'] ) ? sanitize_textarea_field( $settings['notes'] ) : null;

		// Calculate available slots
		$available_slots = max( 0, $max_capacity - $current_bookings );

		// Check if record exists
		$exists = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$this->capacity_table} WHERE collection_date = %s",
				$date
			)
		);

		if ( $exists ) {
			// Update existing record
			$result = $wpdb->update(
				$this->capacity_table,
				array(
					'max_capacity' => $max_capacity,
					'current_bookings' => $current_bookings,
					'available_slots' => $available_slots,
					'is_enabled' => $is_enabled ? 1 : 0,
					'notes' => $notes,
					'updated_at' => current_time( 'mysql' ),
				),
				array( 'collection_date' => $date ),
				array( '%d', '%d', '%d', '%d', '%s', '%s' ),
				array( '%s' )
			);
		} else {
			// Insert new record
			$result = $wpdb->insert(
				$this->capacity_table,
				array(
					'collection_date' => $date,
					'max_capacity' => $max_capacity,
					'current_bookings' => $current_bookings,
					'available_slots' => $available_slots,
					'is_enabled' => $is_enabled ? 1 : 0,
					'notes' => $notes,
					'created_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%d', '%d', '%d', '%s', '%s', '%s' )
			);
		}

		if ( $result === false ) {
			return new WP_Error( 'database_error', 'Failed to update capacity settings.' );
		}

		// Clear cache
		$this->clear_cache( $date );

		return true;
	}

	/**
	 * Get capacity settings for a specific date.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return array|WP_Error Capacity settings or error.
	 */
	public function get_capacity_settings( $date ) {
		global $wpdb;

		if ( ! $this->validate_date( $date ) ) {
			return new WP_Error( 'invalid_date', 'Invalid date format.' );
		}

		$capacity = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$this->capacity_table} WHERE collection_date = %s",
				$date
			),
			ARRAY_A
		);

		if ( ! $capacity ) {
			// Return default settings
			$default_capacity = $this->get_default_capacity();
			return array(
				'collection_date' => $date,
				'max_capacity' => $default_capacity,
				'current_bookings' => 0,
				'available_slots' => $default_capacity,
				'is_enabled' => true,
				'notes' => null,
			);
		}

		// Convert boolean values
		$capacity['is_enabled'] = absint( $capacity['is_enabled'] ) === 1;

		return $capacity;
	}

	/**
	 * Update booking count for a specific date.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @param int    $change Number of bookings to add (positive) or remove (negative).
	 * @return bool|WP_Error Success or error.
	 */
	public function update_booking_count( $date, $change = 1 ) {
		global $wpdb;

		if ( ! $this->validate_date( $date ) ) {
			return new WP_Error( 'invalid_date', 'Invalid date format.' );
		}

		$change = absint( $change );

		// Get current capacity settings
		$capacity = $this->get_capacity_settings( $date );

		if ( is_wp_error( $capacity ) ) {
			return $capacity;
		}

		// Calculate new booking count
		$new_bookings = max( 0, $capacity['current_bookings'] + $change );

		// Update settings
		return $this->update_capacity_settings( $date, array(
			'max_capacity' => $capacity['max_capacity'],
			'current_bookings' => $new_bookings,
			'is_enabled' => $capacity['is_enabled'],
			'notes' => $capacity['notes'],
		) );
	}

	/**
	 * Check if capacity management is enabled.
	 *
	 * @return bool True if capacity management is enabled.
	 */
	protected function is_capacity_enabled() {
		return (bool) get_option( 'wc_collection_date_capacity_enabled', false );
	}

	/**
	 * Get default capacity setting.
	 *
	 * @return int Default capacity.
	 */
	protected function get_default_capacity() {
		return absint( get_option( 'wc_collection_date_default_capacity', 50 ) );
	}

	/**
	 * Check if a day is a working day.
	 *
	 * @param int $day_of_week Day of week (0 = Sunday, 6 = Saturday).
	 * @return bool True if it's a working day.
	 */
	protected function is_working_day( $day_of_week ) {
		$working_days = get_option( 'wc_collection_date_working_days', array( '1', '2', '3', '4', '5', '6' ) );
		return in_array( (string) $day_of_week, $working_days, true );
	}

	/**
	 * Check if a day is a collection day.
	 *
	 * @param int $day_of_week Day of week (0 = Sunday, 6 = Saturday).
	 * @return bool True if it's a collection day.
	 */
	protected function is_collection_day( $day_of_week ) {
		$collection_days = get_option( 'wc_collection_date_collection_days', array( '0', '1', '2', '3', '4', '5', '6' ) );
		return in_array( (string) $day_of_week, $collection_days, true );
	}

	/**
	 * Validate date format.
	 *
	 * @param string $date Date in Y-m-d format.
	 * @return bool True if valid.
	 */
	protected function validate_date( $date ) {
		return (bool) preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && strtotime( $date );
	}

	/**
	 * Clear cache for a specific date.
	 *
	 * @param string $date Date in Y-m-d format.
	 */
	protected function clear_cache( $date ) {
		$cache_keys = array(
			"wc_calendar_day_{$date}",
			"wc_capacity_{$date}",
		);

		foreach ( $cache_keys as $key ) {
			wp_cache_delete( $key );
		}
	}

	/**
	 * Get multiple months of calendar data.
	 *
	 * @param string $start_date Start date in Y-m-d format.
	 * @param int    $months Number of months to return.
	 * @return array Multi-month calendar data.
	 */
	public function get_multi_month_calendar( $start_date, $months = 3 ) {
		$calendar_data = array();
		$current_date = new DateTime( $start_date );

		for ( $i = 0; $i < $months; $i++ ) {
			$year_month = $current_date->format( 'Y-m' );
			$month_data = $this->get_calendar_data( $year_month );

			if ( ! isset( $month_data['error'] ) ) {
				$calendar_data[] = $month_data;
			}

			$current_date->modify( '+1 month' );
		}

		return $calendar_data;
	}
}