<?php
/**
 * Exclusion Manager Class
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
 * Exclusion management class.
 *
 * Handles CRUD operations for date exclusions including single dates and date ranges.
 *
 * @since 1.4.0
 */
class WC_Collection_Date_Exclusion_Manager {

	/**
	 * Add a new exclusion.
	 *
	 * @since 1.4.0
	 * @param array $data Exclusion data.
	 * @return int|WP_Error Exclusion ID or error.
	 */
	public static function add_exclusion( $data ) {
		global $wpdb;

		// Validate input data.
		$validated = WC_Collection_Date_Exclusion_Validator::validate( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		// Handle single date exclusion.
		if ( 'single' === $validated['exclusion_type'] ) {
			$result = $wpdb->insert(
				$table_name,
				array(
					'exclusion_type' => 'single',
					'exclusion_date' => $validated['exclusion_date'],
					'reason'         => $validated['reason'],
				),
				array( '%s', '%s', '%s' )
			);
		}
		// Handle date range exclusion.
		else {
			$result = $wpdb->insert(
				$table_name,
				array(
					'exclusion_type'  => 'range',
					'exclusion_start' => $validated['exclusion_start'],
					'exclusion_end'   => $validated['exclusion_end'],
					'reason'          => $validated['reason'],
				),
				array( '%s', '%s', '%s', '%s' )
			);

			// Also insert individual dates for range for backward compatibility.
			if ( $result && isset( $validated['exclusion_start'] ) && isset( $validated['exclusion_end'] ) ) {
				$start = new DateTime( $validated['exclusion_start'] );
				$end   = new DateTime( $validated['exclusion_end'] );
				$interval = new DateInterval( 'P1D' );
				$period   = new DatePeriod( $start, $interval, $end );

				foreach ( $period as $date ) {
					$wpdb->insert(
						$table_name,
						array(
							'exclusion_type' => 'single',
							'exclusion_date' => $date->format( 'Y-m-d' ),
							'reason'         => $validated['reason'] . ' (Range)',
						),
						array( '%s', '%s', '%s' )
					);
				}
			}
		}

		if ( $result === false ) {
			return new WP_Error(
				'db_error',
				__( 'Failed to add exclusion to database.', 'wc-collection-date' ),
				array( 'status' => 500 )
			);
		}

		return $wpdb->insert_id;
	}

	/**
	 * Get exclusions with optional filtering.
	 *
	 * @since 1.4.0
	 * @param array $args Query arguments.
	 * @return array Exclusions list.
	 */
	public static function get_exclusions( $args = array() ) {
		global $wpdb;

		$defaults = array(
			'type'       => 'all', // all, single, range
			'start_date' => null,
			'end_date'   => null,
			'limit'      => 100,
			'offset'     => 0,
		);

		$args = wp_parse_args( $args, $defaults );
		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		$where_clauses = array();
		$sql_params   = array();

		// Filter by type.
		if ( 'single' === $args['type'] ) {
			$where_clauses[] = "exclusion_type = 'single'";
		} elseif ( 'range' === $args['type'] ) {
			$where_clauses[] = "exclusion_type = 'range'";
		}

		// Filter by date range.
		if ( $args['start_date'] ) {
			if ( 'range' === $args['type'] ) {
				$where_clauses[] = "exclusion_start >= %s";
				$sql_params[]     = $args['start_date'];
			} else {
				$where_clauses[] = "exclusion_date >= %s";
				$sql_params[]     = $args['start_date'];
			}
		}

		if ( $args['end_date'] ) {
			if ( 'range' === $args['type'] ) {
				$where_clauses[] = "exclusion_end <= %s";
				$sql_params[]     = $args['end_date'];
			} else {
				$where_clauses[] = "exclusion_date <= %s";
				$sql_params[]     = $args['end_date'];
			}
		}

		// Build WHERE clause.
		$where_clause = '';
		if ( ! empty( $where_clauses ) ) {
			$where_clause = 'WHERE ' . implode( ' AND ', $where_clauses );
		}

		// Determine order by clause.
		$order_by = 'exclusion_type = \'single\', exclusion_date ASC, exclusion_start ASC';
		if ( 'range' === $args['type'] ) {
			$order_by = 'exclusion_start ASC';
		} elseif ( 'single' === $args['type'] ) {
			$order_by = 'exclusion_date ASC';
		}

		// Build the query.
		$sql = "SELECT id, exclusion_type, exclusion_date, exclusion_start, exclusion_end, reason, created_at, updated_at
				FROM {$table_name}
				{$where_clause}
				ORDER BY {$order_by}
				LIMIT %d OFFSET %d";

		$sql_params[] = $args['limit'];
		$sql_params[] = $args['offset'];

		$results = $wpdb->get_results(
			$wpdb->prepare( $sql, $sql_params )
		);

		return $results;
	}

	/**
	 * Get exclusion by ID.
	 *
	 * @since 1.4.0
	 * @param int $id Exclusion ID.
	 * @return object|false Exclusion data or false if not found.
	 */
	public static function get_exclusion( $id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT id, exclusion_type, exclusion_date, exclusion_start, exclusion_end, reason, created_at, updated_at
				 FROM {$table_name}
				 WHERE id = %d",
				$id
			)
		);
	}

	/**
	 * Update an existing exclusion.
	 *
	 * @since 1.4.0
	 * @param int   $id   Exclusion ID.
	 * @param array $data Updated exclusion data.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function update_exclusion( $id, $data ) {
		global $wpdb;

		// Validate input data.
		$validated = WC_Collection_Date_Exclusion_Validator::validate( $data );
		if ( is_wp_error( $validated ) ) {
			return $validated;
		}

		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		// Get existing exclusion to handle type changes.
		$existing = self::get_exclusion( $id );
		if ( ! $existing ) {
			return new WP_Error(
				'not_found',
				__( 'Exclusion not found.', 'wc-collection-date' ),
				array( 'status' => 404 )
			);
		}

		// Prepare update data.
		$update_data = array(
			'reason' => $validated['reason'],
		);
		$update_format = array( '%s' );

		if ( 'single' === $validated['exclusion_type'] ) {
			$update_data['exclusion_type'] = 'single';
			$update_data['exclusion_date'] = $validated['exclusion_date'];
			$update_data['exclusion_start'] = null;
			$update_data['exclusion_end'] = null;
			$update_format = array( '%s', '%s', '%s', '%s', '%s' );
		} else {
			$update_data['exclusion_type'] = 'range';
			$update_data['exclusion_start'] = $validated['exclusion_start'];
			$update_data['exclusion_end'] = $validated['exclusion_end'];
			$update_data['exclusion_date'] = null;
			$update_format = array( '%s', '%s', '%s', '%s', '%s' );
		}

		$result = $wpdb->update(
			$table_name,
			$update_data,
			array( 'id' => $id ),
			$update_format,
			array( '%d' )
		);

		if ( $result === false ) {
			return new WP_Error(
				'db_error',
				__( 'Failed to update exclusion.', 'wc-collection-date' ),
				array( 'status' => 500 )
			);
		}

		return true;
	}

	/**
	 * Delete an exclusion.
	 *
	 * @since 1.4.0
	 * @param int $id Exclusion ID.
	 * @return bool|WP_Error True on success, error on failure.
	 */
	public static function delete_exclusion( $id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		// Get existing exclusion to handle range cleanup.
		$existing = self::get_exclusion( $id );
		if ( ! $existing ) {
			return new WP_Error(
				'not_found',
				__( 'Exclusion not found.', 'wc-collection-date' ),
				array( 'status' => 404 )
			);
		}

		// Delete the main exclusion record.
		$result = $wpdb->delete(
			$table_name,
			array( 'id' => $id ),
			array( '%d' )
		);

		if ( $result === false ) {
			return new WP_Error(
				'db_error',
				__( 'Failed to delete exclusion.', 'wc-collection-date' ),
				array( 'status' => 500 )
			);
		}

		// If it was a range, also clean up associated single date records.
		if ( 'range' === $existing->exclusion_type ) {
			$wpdb->delete(
				$table_name,
				array(
					'exclusion_type' => 'single',
					'reason'         => $existing->reason . ' (Range)',
				),
				array( '%s', '%s' )
			);
		}

		return true;
	}

	/**
	 * Get exclusions for a specific date range.
	 *
	 * @since 1.4.0
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date   End date in Y-m-d format.
	 * @return array List of exclusions in the date range.
	 */
	public static function get_exclusions_in_range( $start_date, $end_date ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		// Get single date exclusions in range.
		$single_exclusions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, exclusion_type, exclusion_date, reason
				 FROM {$table_name}
				 WHERE exclusion_type = 'single'
				   AND exclusion_date BETWEEN %s AND %s
				 ORDER BY exclusion_date ASC",
				$start_date,
				$end_date
			)
		);

		// Get range exclusions that overlap with the requested range.
		$range_exclusions = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT id, exclusion_type, exclusion_start, exclusion_end, reason
				 FROM {$table_name}
				 WHERE exclusion_type = 'range'
				   AND (
				     (exclusion_start <= %s AND exclusion_end >= %s) OR
				     (exclusion_start BETWEEN %s AND %s) OR
				     (exclusion_end BETWEEN %s AND %s)
				   )
				 ORDER BY exclusion_start ASC",
				$start_date,
				$end_date,
				$start_date,
				$end_date,
				$start_date,
				$end_date
			)
		);

		return array(
			'single' => $single_exclusions,
			'range'  => $range_exclusions,
		);
	}

	/**
	 * Get total count of exclusions.
	 *
	 * @since 1.4.0
	 * @param string $type Exclusion type (all, single, range).
	 * @return int Total count.
	 */
	public static function get_exclusions_count( $type = 'all' ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		$where_clause = '';
		if ( 'single' === $type ) {
			$where_clause = "WHERE exclusion_type = 'single'";
		} elseif ( 'range' === $type ) {
			$where_clause = "WHERE exclusion_type = 'range'";
		}

		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$table_name} {$where_clause}"
		);
	}
}