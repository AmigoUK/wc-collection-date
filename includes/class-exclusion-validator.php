<?php
/**
 * Exclusion Validator Class
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
 * Exclusion validation class.
 *
 * Handles validation of single dates and date ranges for exclusions.
 *
 * @since 1.4.0
 */
class WC_Collection_Date_Exclusion_Validator {

	/**
	 * Validate exclusion data.
	 *
	 * @since 1.4.0
	 * @param array $data Exclusion data to validate.
	 * @return array|WP_Error Validation result or error.
	 */
	public static function validate( $data ) {
		// Basic required fields.
		if ( empty( $data['reason'] ) ) {
			return new WP_Error(
				'invalid_reason',
				__( 'Reason is required.', 'wc-collection-date' ),
				array( 'status' => 400 )
			);
		}

		$exclusion_type = isset( $data['exclusion_type'] ) ? $data['exclusion_type'] : 'single';

		if ( ! in_array( $exclusion_type, array( 'single', 'range' ), true ) ) {
			return new WP_Error(
				'invalid_type',
				__( 'Invalid exclusion type. Must be single or range.', 'wc-collection-date' ),
				array( 'status' => 400 )
			);
		}

		// Validate based on type.
		if ( 'single' === $exclusion_type ) {
			return self::validate_single_date( $data );
		} else {
			return self::validate_date_range( $data );
		}
	}

	/**
	 * Validate single date exclusion.
	 *
	 * @since 1.4.0
	 * @param array $data Single date data.
	 * @return array|WP_Error Validated data or error.
	 */
	private static function validate_single_date( $data ) {
		if ( empty( $data['exclusion_date'] ) ) {
			return new WP_Error(
				'invalid_date',
				__( 'Exclusion date is required for single date exclusions.', 'wc-collection-date' ),
				array( 'status' => 400 )
			);
		}

		$date = DateTime::createFromFormat( 'Y-m-d', $data['exclusion_date'] );
		if ( ! $date ) {
			return new WP_Error(
				'invalid_date_format',
				__( 'Invalid date format. Use Y-m-d format.', 'wc-collection-date' ),
				array( 'status' => 400 )
			);
		}

		$today = new DateTime( current_time( 'Y-m-d' ) );
		if ( $date < $today ) {
			return new WP_Error(
				'past_date',
				__( 'Cannot exclude dates in the past.', 'wc-collection-date' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'exclusion_type' => 'single',
			'exclusion_date' => $data['exclusion_date'],
			'reason'          => sanitize_text_field( $data['reason'] ),
		);
	}

	/**
	 * Validate date range exclusion.
	 *
	 * @since 1.4.0
	 * @param array $data Date range data.
	 * @return array|WP_Error Validated data or error.
	 */
	private static function validate_date_range( $data ) {
		if ( empty( $data['exclusion_start'] ) || empty( $data['exclusion_end'] ) ) {
			return new WP_Error(
				'invalid_range',
				__( 'Both start and end dates are required for date range exclusions.', 'wc-collection-date' ),
				array( 'status' => 400 )
			);
		}

		$start_date = DateTime::createFromFormat( 'Y-m-d', $data['exclusion_start'] );
		$end_date   = DateTime::createFromFormat( 'Y-m-d', $data['exclusion_end'] );

		if ( ! $start_date || ! $end_date ) {
			return new WP_Error(
				'invalid_date_format',
				__( 'Invalid date format. Use Y-m-d format.', 'wc-collection-date' ),
				array( 'status' => 400 )
			);
		}

		if ( $start_date > $end_date ) {
			return new WP_Error(
				'invalid_range_order',
				__( 'Start date must be before or equal to end date.', 'wc-collection-date' ),
				array( 'status' => 400 )
			);
		}

		$today = new DateTime( current_time( 'Y-m-d' ) );
		if ( $end_date < $today ) {
			return new WP_Error(
				'past_range',
				__( 'Cannot exclude date ranges ending in the past.', 'wc-collection-date' ),
				array( 'status' => 400 )
			);
		}

		// Check for overlap with existing exclusions.
		if ( self::range_overlaps_existing( $start_date->format( 'Y-m-d' ), $end_date->format( 'Y-m-d' ) ) ) {
			return new WP_Error(
				'overlap_detected',
				__( 'This date range overlaps with existing exclusions.', 'wc-collection-date' ),
				array( 'status' => 409 )
			);
		}

		return array(
			'exclusion_type'  => 'range',
			'exclusion_start' => $start_date->format( 'Y-m-d' ),
			'exclusion_end'   => $end_date->format( 'Y-m-d' ),
			'reason'          => sanitize_text_field( $data['reason'] ),
		);
	}

	/**
	 * Check if a date range overlaps with existing exclusions.
	 *
	 * @since 1.4.0
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date   End date in Y-m-d format.
	 * @return bool True if overlap exists.
	 */
	private static function range_overlaps_existing( $start_date, $end_date ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		// Check for overlaps with single date exclusions.
		$single_overlaps = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$table_name}
				 WHERE exclusion_type = 'single'
				   AND exclusion_date BETWEEN %s AND %s",
				$start_date,
				$end_date
			)
		);

		if ( $single_overlaps > 0 ) {
			return true;
		}

		// Check for overlaps with existing range exclusions.
		$range_overlaps = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*)
				 FROM {$table_name}
				 WHERE exclusion_type = 'range'
				   AND (
				     (exclusion_start <= %s AND exclusion_end >= %s) OR
				     (exclusion_start <= %s AND exclusion_end >= %s) OR
				     (exclusion_start >= %s AND exclusion_end <= %s)
				   )",
				$start_date,
				$start_date,
				$end_date,
				$end_date,
				$start_date,
				$end_date
			)
		);

		return $range_overlaps > 0;
	}

	/**
	 * Check if a specific date is excluded.
	 *
	 * @since 1.4.0
	 * @param string $date Date to check in Y-m-d format.
	 * @return array|false Exclusion data or false if not excluded.
	 */
	public static function is_date_excluded( $date ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		// Check single date exclusions.
		$single = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, exclusion_type, exclusion_date, reason
				 FROM {$table_name}
				 WHERE exclusion_type = 'single'
				   AND exclusion_date = %s",
				$date
			)
		);

		if ( $single ) {
			return array(
				'id'             => $single->id,
				'exclude_type'   => 'single',
				'exclude_date'   => $single->exclusion_date,
				'reason'         => $single->reason,
				'overlap_count'  => 1,
			);
		}

		// Check date range exclusions.
		$range = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, exclusion_type, exclusion_start, exclusion_end, reason
				 FROM {$table_name}
				 WHERE exclusion_type = 'range'
				   AND %s BETWEEN exclusion_start AND exclusion_end",
				$date
			)
		);

		if ( $range ) {
			// Calculate total number of dates in this range.
			$start = new DateTime( $range->exclusion_start );
			$end   = new DateTime( $range->exclusion_end );
			$days  = $start->diff( $end )->days + 1;

			return array(
				'id'            => $range->id,
				'exclude_type'  => 'range',
				'exclude_start' => $range->exclusion_start,
				'exclude_end'   => $range->exclusion_end,
				'reason'        => $range->reason,
				'overlap_count' => $days,
			);
		}

		return false;
	}
}