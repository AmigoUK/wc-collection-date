<?php
/**
 * Calendar Admin Tab Class
 *
 * @package    WC_Collection_Date
 * @subpackage WC_Collection_Date/includes/admin
 * @since      1.2.0
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Calendar admin tab class.
 *
 * Handles the calendar interface for managing daily capacities and bookings.
 *
 * @since 1.2.0
 */
class WC_Collection_Date_Calendar {

	/**
	 * Settings instance.
	 *
	 * @var WC_Collection_Date_Settings
	 */
	private $settings;

	/**
	 * Initialize the calendar class.
	 *
	 * @since 1.2.0
	 */
	public function __construct() {
		$this->settings = new WC_Collection_Date_Settings();
	}

	
	/**
	 * Render calendar tab.
	 *
	 * @since 1.2.0
	 */
	public function render_calendar_tab() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wc-collection-date' ) );
		}

		?>
		<div class="wc-collection-date-calendar-wrapper">
			<div class="calendar-header">
				<h2><?php esc_html_e( 'Collection Calendar', 'wc-collection-date' ); ?></h2>
				<p class="description">
					<?php esc_html_e( 'View and manage daily collection capacities, track bookings, and monitor availability. Click on any date to view details and adjust capacity.', 'wc-collection-date' ); ?>
				</p>
			</div>

			<!-- Calendar Controls -->
			<div class="calendar-controls">
				<div class="calendar-navigation">
					<button type="button" id="calendar-prev-month" class="button">
						<span class="dashicons dashicons-arrow-left-alt2"></span>
						<?php esc_html_e( 'Previous', 'wc-collection-date' ); ?>
					</button>

					<div class="current-month-display">
						<span id="current-month-year"><?php echo esc_html( current_time( 'F Y' ) ); ?></span>
					</div>

					<button type="button" id="calendar-next-month" class="button">
						<?php esc_html_e( 'Next', 'wc-collection-date' ); ?>
						<span class="dashicons dashicons-arrow-right-alt2"></span>
					</button>
				</div>

				<div class="calendar-actions">
					<button type="button" id="calendar-today" class="button">
						<span class="dashicons dashicons-calendar-alt"></span>
						<?php esc_html_e( 'Today', 'wc-collection-date' ); ?>
					</button>

					<button type="button" id="calendar-refresh" class="button">
						<span class="dashicons dashicons-update"></span>
						<?php esc_html_e( 'Refresh', 'wc-collection-date' ); ?>
					</button>
				</div>
			</div>

			<!-- Legend -->
			<div class="calendar-legend">
				<h3><?php esc_html_e( 'Legend', 'wc-collection-date' ); ?></h3>
				<div class="legend-items">
					<div class="legend-item">
						<span class="legend-color available"></span>
						<span><?php esc_html_e( 'Available', 'wc-collection-date' ); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color moderate"></span>
						<span><?php esc_html_e( 'Moderate', 'wc-collection-date' ); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color high"></span>
						<span><?php esc_html_e( 'High', 'wc-collection-date' ); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color full"></span>
						<span><?php esc_html_e( 'Full', 'wc-collection-date' ); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color excluded"></span>
						<span><?php esc_html_e( 'Excluded', 'wc-collection-date' ); ?></span>
					</div>
					<div class="legend-item">
						<span class="legend-color past"></span>
						<span><?php esc_html_e( 'Past', 'wc-collection-date' ); ?></span>
					</div>
				</div>
			</div>

			<!-- Calendar Container -->
			<div class="calendar-container">
				<div id="calendar-loading" class="calendar-loading">
					<span class="spinner is-active"></span>
					<span><?php esc_html_e( 'Loading calendar...', 'wc-collection-date' ); ?></span>
				</div>

				<div id="calendar-grid" class="calendar-grid" style="display: none;">
					<!-- Calendar will be populated by JavaScript -->
				</div>
			</div>

			<!-- Statistics Panel -->
			<div class="calendar-stats">
				<h3><?php esc_html_e( 'Monthly Statistics', 'wc-collection-date' ); ?></h3>
				<div class="stats-grid">
					<div class="stat-card">
						<div class="stat-value" id="total-bookings">-</div>
						<div class="stat-label"><?php esc_html_e( 'Total Bookings', 'wc-collection-date' ); ?></div>
					</div>
					<div class="stat-card">
						<div class="stat-value" id="total-capacity">-</div>
						<div class="stat-label"><?php esc_html_e( 'Total Capacity', 'wc-collection-date' ); ?></div>
					</div>
					<div class="stat-card">
						<div class="stat-value" id="utilization-rate">-</div>
						<div class="stat-label"><?php esc_html_e( 'Utilization Rate', 'wc-collection-date' ); ?></div>
					</div>
					<div class="stat-card">
						<div class="stat-value" id="available-days">-</div>
						<div class="stat-label"><?php esc_html_e( 'Available Days', 'wc-collection-date' ); ?></div>
					</div>
				</div>
			</div>

			<!-- Quick Actions -->
			<div class="calendar-quick-actions">
				<h3><?php esc_html_e( 'Quick Actions', 'wc-collection-date' ); ?></h3>
				<div class="actions-grid">
					<div class="action-group">
						<label for="bulk-capacity"><?php esc_html_e( 'Set Capacity for Multiple Days:', 'wc-collection-date' ); ?></label>
						<div class="bulk-action-controls">
							<input type="number" id="bulk-capacity" min="1" max="999" placeholder="<?php esc_attr_e( 'Capacity', 'wc-collection-date' ); ?>">
							<button type="button" id="bulk-set-capacity" class="button" disabled>
								<?php esc_html_e( 'Apply to Selected', 'wc-collection-date' ); ?>
							</button>
						</div>
					</div>

					<div class="action-group">
						<button type="button" id="export-calendar" class="button">
							<span class="dashicons dashicons-download"></span>
							<?php esc_html_e( 'Export Calendar Data', 'wc-collection-date' ); ?>
						</button>
					</div>
				</div>
			</div>
		</div>

		<!-- Day Details Modal -->
		<div id="day-details-modal" class="calendar-modal" style="display: none;">
			<div class="modal-content">
				<div class="modal-header">
					<h3 id="modal-date-title"><?php esc_html_e( 'Date Details', 'wc-collection-date' ); ?></h3>
					<button type="button" class="modal-close">&times;</button>
				</div>

				<div class="modal-body">
					<div class="day-summary">
						<div class="summary-item">
							<span class="label"><?php esc_html_e( 'Date:', 'wc-collection-date' ); ?></span>
							<span id="modal-date-display">-</span>
						</div>
						<div class="summary-item">
							<span class="label"><?php esc_html_e( 'Day:', 'wc-collection-date' ); ?></span>
							<span id="modal-day-display">-</span>
						</div>
					</div>

					<div class="capacity-section">
						<h4><?php esc_html_e( 'Capacity Management', 'wc-collection-date' ); ?></h4>
						<div class="capacity-input-group">
							<label for="modal-capacity-input"><?php esc_html_e( 'Daily Capacity:', 'wc-collection-date' ); ?></label>
							<input type="number" id="modal-capacity-input" min="1" max="999" class="small-text">
							<button type="button" id="modal-update-capacity" class="button button-primary">
								<?php esc_html_e( 'Update', 'wc-collection-date' ); ?>
							</button>
						</div>
						<div class="capacity-display">
							<span id="modal-current-capacity">-</span>
							<span id="modal-booked-count">-</span>
							<span id="modal-available-count">-</span>
						</div>
					</div>

					<div class="bookings-section">
						<h4><?php esc_html_e( 'Bookings', 'wc-collection-date' ); ?></h4>
						<div id="modal-bookings-list" class="bookings-list">
							<div class="loading-bookings">
								<span class="spinner is-active"></span>
								<span><?php esc_html_e( 'Loading bookings...', 'wc-collection-date' ); ?></span>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>

		<style>
			/* Calendar styles will be loaded from calendar.css */
			.calendar-loading {
				text-align: center;
				padding: 40px;
				color: #666;
			}

			.calendar-loading .spinner {
				margin-right: 10px;
			}
		</style>
		<?php
	}

	
	/**
	 * Get calendar data for a specific month.
	 *
	 * @since 1.2.0
	 * @param int $year  Year.
	 * @param int $month Month.
	 * @return array Calendar data.
	 */
	private function get_calendar_data( $year, $month ) {
		global $wpdb;

		$calendar_data = array();
		$days_in_month = date( 't', mktime( 0, 0, 0, $month, 1, $year ) );

		// Get capacity data for the month.
		$capacity_table = $wpdb->prefix . 'wc_collection_date_capacity';
		$capacities = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT collection_date as date, max_capacity as capacity FROM {$capacity_table} WHERE YEAR(collection_date) = %d AND MONTH(collection_date) = %d",
				$year,
				$month
			)
		);

		$capacity_map = array();
		foreach ( $capacities as $capacity ) {
			$capacity_map[ $capacity->date ] = (int) $capacity->capacity;
		}

		// Get booking counts for the month.
		$booking_counts = $this->get_booking_counts_for_month( $year, $month );

		// Get exclusions for the month.
		$exclusions = $this->get_exclusions_for_month( $year, $month );
		$exclusion_map = array();
		foreach ( $exclusions as $exclusion ) {
			$exclusion_map[ $exclusion->exclusion_date ] = $exclusion->reason;
		}

		// Get collection days.
		$collection_days = get_option( 'wc_collection_date_collection_days', array() );
		$working_days    = get_option( 'wc_collection_date_working_days', array() );

		// Build calendar data.
		for ( $day = 1; $day <= $days_in_month; $day++ ) {
			$date = sprintf( '%04d-%02d-%02d', $year, $month, $day );
			$day_of_week = (int) date( 'w', strtotime( $date ) );

			// Check if date is excluded.
			$is_excluded = isset( $exclusion_map[ $date ] );

			// Check if date is a collection day.
			$is_collection_day = in_array( (string) $day_of_week, $collection_days, true );

			// Check if date is in the past.
			$is_past = strtotime( $date ) < strtotime( current_time( 'Y-m-d' ) );

			// Get capacity and booking count.
			$capacity = isset( $capacity_map[ $date ] ) ? $capacity_map[ $date ] : $this->get_default_capacity();
			$booked   = isset( $booking_counts[ $date ] ) ? $booking_counts[ $date ] : 0;
			$available = $capacity - $booked;

			// Determine status.
			if ( $is_past ) {
				$status = 'past';
			} elseif ( $is_excluded || ! $is_collection_day ) {
				$status = 'excluded';
			} elseif ( $available <= 0 ) {
				$status = 'full';
			} elseif ( $available <= $capacity * 0.2 ) {
				$status = 'high';
			} elseif ( $available <= $capacity * 0.5 ) {
				$status = 'moderate';
			} else {
				$status = 'available';
			}

			$calendar_data[] = array(
				'date'       => $date,
				'day'        => $day,
				'day_of_week' => $day_of_week,
				'capacity'   => $capacity,
				'booked'     => $booked,
				'available'  => $available,
				'status'     => $status,
				'is_excluded' => $is_excluded,
				'exclusion_reason' => $is_excluded ? $exclusion_map[ $date ] : '',
				'is_collection_day' => $is_collection_day,
				'is_today'   => $date === current_time( 'Y-m-d' ),
			);
		}

		return $calendar_data;
	}

	/**
	 * Get booking counts for a month.
	 *
	 * @since 1.2.0
	 * @param int $year  Year.
	 * @param int $month Month.
	 * @return array Booking counts indexed by date.
	 */
	private function get_booking_counts_for_month( $year, $month ) {
		$order_counts = wc_get_orders(
			array(
				'limit'        => -1,
				'status'       => array( 'processing', 'on-hold', 'pending', 'completed' ),
				'meta_key'     => '_collection_date',
				'meta_query'   => array(
					array(
						'key'     => '_collection_date',
						'value'   => array( sprintf( '%04d-%02d-01', $year, $month ), sprintf( '%04d-%02d-31', $year, $month ) ),
						'compare' => 'BETWEEN',
						'type'    => 'DATE',
					),
				),
				'return'       => 'ids',
			)
		);

		$counts = array();
		foreach ( $order_counts as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$collection_date = $order->get_meta( '_collection_date' );
				if ( $collection_date ) {
					if ( ! isset( $counts[ $collection_date ] ) ) {
						$counts[ $collection_date ] = 0;
					}
					$counts[ $collection_date ]++;
				}
			}
		}

		return $counts;
	}

	/**
	 * Get exclusions for a month.
	 *
	 * @since 1.2.0
	 * @param int $year  Year.
	 * @param int $month Month.
	 * @return array Exclusion data.
	 */
	private function get_exclusions_for_month( $year, $month ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_exclusions';
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT exclusion_date, reason FROM {$table_name} WHERE YEAR(exclusion_date) = %d AND MONTH(exclusion_date) = %d ORDER BY exclusion_date ASC",
				$year,
				$month
			)
		);
	}

	/**
	 * Get month statistics.
	 *
	 * @since 1.2.0
	 * @param int $year  Year.
	 * @param int $month Month.
	 * @return array Statistics data.
	 */
	private function get_month_statistics( $year, $month ) {
		$calendar_data = $this->get_calendar_data( $year, $month );

		$total_bookings = 0;
		$total_capacity = 0;
		$available_days = 0;

		foreach ( $calendar_data as $day_data ) {
			if ( ! in_array( $day_data['status'], array( 'excluded', 'past' ), true ) ) {
				$total_bookings += $day_data['booked'];
				$total_capacity += $day_data['capacity'];

				if ( $day_data['available'] > 0 ) {
					$available_days++;
				}
			}
		}

		$utilization_rate = $total_capacity > 0 ? round( ( $total_bookings / $total_capacity ) * 100, 1 ) : 0;

		return array(
			'total_bookings'    => $total_bookings,
			'total_capacity'    => $total_capacity,
			'utilization_rate'  => $utilization_rate,
			'available_days'    => $available_days,
		);
	}

	/**
	 * Get bookings for a specific day.
	 *
	 * @since 1.2.0
	 * @param string $date Date in Y-m-d format.
	 * @return array Bookings data.
	 */
	private function get_day_bookings( $date ) {
		$order_ids = wc_get_orders(
			array(
				'limit'      => -1,
				'status'     => array( 'processing', 'on-hold', 'pending', 'completed' ),
				'meta_key'   => '_collection_date',
				'meta_query' => array(
					array(
						'key'   => '_collection_date',
						'value' => $date,
					),
				),
				'return'     => 'ids',
			)
		);

		$bookings = array();
		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$bookings[] = array(
					'order_id'     => $order->get_id(),
					'order_number' => $order->get_order_number(),
					'customer'     => $order->get_formatted_billing_full_name(),
					'total'        => $order->get_formatted_order_total(),
					'status'       => $order->get_status(),
					'date_created' => $order->get_date_created()->date( 'Y-m-d H:i:s' ),
					'edit_url'     => $order->get_edit_order_url(),
				);
			}
		}

		return $bookings;
	}

	/**
	 * Get default capacity.
	 *
	 * @since 1.2.0
	 * @return int Default capacity value.
	 */
	private function get_default_capacity() {
		return (int) apply_filters( 'wc_collection_date_default_capacity', 50 );
	}

	}