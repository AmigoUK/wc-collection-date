<?php
/**
 * Analytics Class for WooCommerce Collection Date
 *
 * Tracks and displays collection date usage statistics and analytics
 *
 * @since  1.2.0
 * @package WC_Collection_Date
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Collection_Date_Analytics class.
 */
class WC_Collection_Date_Analytics {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.2.0
	 * @var   WC_Collection_Date_Analytics
	 */
	protected static $_instance = null;

	/**
	 * Main WC_Collection_Date_Analytics Instance.
	 *
	 * Ensures only one instance of WC_Collection_Date_Analytics is loaded.
	 *
	 * @since  1.2.0
	 * @static
	 * @see    WC_Collection_Date()
	 * @return WC_Collection_Date_Analytics - Main instance.
	 */
	public static function instance() {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self();
		}
		return self::$_instance;
	}

	/**
	 * Constructor.
	 */
	public function __construct() {
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'track_collection_date_selection' ), 10, 2 );
		add_action( 'wp_ajax_wc_collection_date_get_analytics', array( $this, 'get_analytics_data' ) );
		add_action( 'wp_ajax_wc_collection_date_export_analytics', array( $this, 'export_analytics_data' ) );

		// Daily aggregation cron
		if ( ! wp_next_scheduled( 'wc_collection_date_daily_analytics' ) ) {
			wp_schedule_event( strtotime( 'today 2am' ), 'daily', 'wc_collection_date_daily_analytics' );
		}
		add_action( 'wc_collection_date_daily_analytics', array( $this, 'aggregate_daily_stats' ) );
	}

	/**
	 * Track collection date selection when order is placed.
	 *
	 * @param int   $order_id Order ID.
	 * @param array $data     Posted data.
	 */
	public function track_collection_date_selection( $order_id, $data ) {
		$collection_date = isset( $data['collection_date'] ) ? sanitize_text_field( $data['collection_date'] ) : '';

		if ( empty( $collection_date ) ) {
			return;
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			return;
		}

		// Get order data
		$order_total = $order->get_total();
		$order_date = $order->get_date_created()->format( 'Y-m-d H:i:s' );
		$lead_time_days = $this->calculate_lead_time_days( $collection_date, $order_date );

		// Track individual selection
		$this->track_date_selection( $collection_date, $order_id, $order_total, $lead_time_days );

		// Track hourly stats
		$hour = date_i18n( 'H' );
		$this->track_hourly_stats( $hour, 1 );

		// Track day of week stats
		$day_of_week = date_i18n( 'N' ); // 1 (Monday) to 7 (Sunday)
		$this->track_day_of_week_stats( $day_of_week, 1 );

		// Track month stats
		$month = date_i18n( 'n' ); // 1 to 12
		$this->track_monthly_stats( $month, 1 );
	}

	/**
	 * Calculate lead time days between order date and collection date.
	 *
	 * @param string $collection_date Selected collection date (Y-m-d).
	 * @param string $order_date      Order date (Y-m-d H:i:s).
	 * @return int Lead time in days.
	 */
	private function calculate_lead_time_days( $collection_date, $order_date ) {
		$order_timestamp = strtotime( $order_date );
		$collection_timestamp = strtotime( $collection_date );

		return max( 0, ceil( ( $collection_timestamp - $order_timestamp ) / DAY_IN_SECONDS ) );
	}

	/**
	 * Track date selection in database.
	 *
	 * @param string $date         Collection date (Y-m-d).
	 * @param int    $order_id     Order ID.
	 * @param float  $order_total  Order total.
	 * @param int    $lead_time    Lead time in days.
	 */
	private function track_date_selection( $date, $order_id, $order_total, $lead_time ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_date_analytics';

		// Create table if not exists
		$this->create_analytics_table();

		// Check if date already exists
		$existing = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$table_name} WHERE collection_date = %s",
			$date
		) );

		if ( $existing ) {
			// Update existing record
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$table_name} SET
					selection_count = selection_count + 1,
					total_orders = total_orders + 1,
					total_value = total_value + %f,
					avg_lead_time = (avg_lead_time * total_orders + %d) / (total_orders + 1),
					last_selected = %s,
					updated_at = NOW()
				WHERE collection_date = %s",
				$order_total,
				$lead_time,
				current_time( 'mysql' ),
				$date
			) );
		} else {
			// Insert new record
			$wpdb->insert(
				$table_name,
				array(
					'collection_date' => $date,
					'selection_count' => 1,
					'total_orders' => 1,
					'total_value' => $order_total,
					'avg_lead_time' => $lead_time,
					'last_selected' => current_time( 'mysql' ),
					'created_at' => current_time( 'mysql' ),
					'updated_at' => current_time( 'mysql' ),
				),
				array( '%s', '%d', '%d', '%f', '%d', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Track hourly statistics.
	 *
	 * @param int $hour    Hour of day (0-23).
	 * @param int $count   Number of orders.
	 */
	private function track_hourly_stats( $hour, $count ) {
		$option_name = 'wc_collection_date_hourly_stats';
		$stats = get_option( $option_name, array() );

		$today = date_i18n( 'Y-m-d' );
		if ( ! isset( $stats[ $today ] ) ) {
			$stats[ $today ] = array_fill( 0, 24, 0 );
		}

		$stats[ $today ][ $hour ] += $count;

		// Keep only last 90 days
		$cutoff = date_i18n( 'Y-m-d', strtotime( '-90 days' ) );
		foreach ( $stats as $date => $data ) {
			if ( $date < $cutoff ) {
				unset( $stats[ $date ] );
			}
		}

		update_option( $option_name, $stats );
	}

	/**
	 * Track day of week statistics.
	 *
	 * @param int $day    Day of week (1-7, Monday to Sunday).
	 * @param int $count  Number of orders.
	 */
	private function track_day_of_week_stats( $day, $count ) {
		$option_name = 'wc_collection_date_day_stats';
		$stats = get_option( $option_name, array_fill( 1, 7, 0 ) );

		$stats[ $day ] += $count;
		update_option( $option_name, $stats );
	}

	/**
	 * Track monthly statistics.
	 *
	 * @param int $month  Month (1-12).
	 * @param int $count  Number of orders.
	 */
	private function track_monthly_stats( $month, $count ) {
		$option_name = 'wc_collection_date_monthly_stats';
		$stats = get_option( $option_name, array_fill( 1, 12, 0 ) );

		$stats[ $month ] += $count;
		update_option( $option_name, $stats );
	}

	/**
	 * Create analytics database table.
	 */
	private function create_analytics_table() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_date_analytics';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			collection_date date NOT NULL,
			selection_count int NOT NULL DEFAULT 0,
			total_orders int NOT NULL DEFAULT 0,
			total_value decimal(10,2) NOT NULL DEFAULT 0,
			avg_lead_time decimal(5,2) NOT NULL DEFAULT 0,
			last_selected datetime NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY collection_date (collection_date),
			KEY selection_count (selection_count),
			KEY last_selected (last_selected)
		) $charset_collate;";

		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
		dbDelta( $sql );
	}

	/**
	 * Get analytics data for dashboard.
	 */
	public function get_analytics_data() {
		check_ajax_referer( 'wc_collection_date_analytics_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die();
		}

		$period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : '30days';

		$data = array(
			'summary' => $this->get_summary_stats( $period ),
			'popular_dates' => $this->get_popular_dates( $period ),
			'hourly_distribution' => $this->get_hourly_distribution(),
			'day_of_week_distribution' => $this->get_day_of_week_distribution(),
			'lead_time_distribution' => $this->get_lead_time_distribution( $period ),
			'monthly_trends' => $this->get_monthly_trends(),
		);

		wp_send_json_success( $data );
	}

	/**
	 * Get summary statistics.
	 *
	 * @param string $period Time period (7days, 30days, 90days).
	 * @return array Summary stats.
	 */
	private function get_summary_stats( $period ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_date_analytics';
		$date_limit = date_i18n( 'Y-m-d', strtotime( "-{$period}" ) );

		$stats = $wpdb->get_row( $wpdb->prepare(
			"SELECT
				COUNT(*) as total_dates,
				SUM(selection_count) as total_selections,
				SUM(total_orders) as total_orders,
				SUM(total_value) as total_value,
				AVG(avg_lead_time) as avg_lead_time
			FROM {$table_name}
			WHERE last_selected >= %s",
			$date_limit
		) );

		return array(
			'total_dates_available' => intval( $stats->total_dates ),
			'total_selections' => intval( $stats->total_selections ),
			'total_orders' => intval( $stats->total_orders ),
			'total_value' => wc_price( floatval( $stats->total_value ) ),
			'avg_lead_time' => round( floatval( $stats->avg_lead_time ), 1 ),
			'conversion_rate' => $stats->total_dates > 0 ? round( ( $stats->total_selections / $stats->total_dates ) * 100, 1 ) : 0,
		);
	}

	/**
	 * Get most popular collection dates.
	 *
	 * @param string $period Time period.
	 * @return array Popular dates data.
	 */
	private function get_popular_dates( $period ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_date_analytics';
		$date_limit = date_i18n( 'Y-m-d', strtotime( "-{$period}" ) );

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT
				collection_date,
				selection_count,
				total_orders,
				total_value,
				avg_lead_time
			FROM {$table_name}
			WHERE last_selected >= %s
			ORDER BY selection_count DESC
			LIMIT 20",
			$date_limit
		) );
	}

	/**
	 * Get hourly distribution of orders.
	 *
	 * @return array Hour data (0-23).
	 */
	private function get_hourly_distribution() {
		$stats = get_option( 'wc_collection_date_hourly_stats', array() );
		$hourly = array_fill( 0, 24, 0 );

		foreach ( $stats as $date => $hours ) {
			foreach ( $hours as $hour => $count ) {
				$hourly[ $hour ] += $count;
			}
		}

		return $hourly;
	}

	/**
	 * Get day of week distribution.
	 *
	 * @return array Day stats (1-7).
	 */
	private function get_day_of_week_distribution() {
		$stats = get_option( 'wc_collection_date_day_stats', array_fill( 1, 7, 0 ) );
		return $stats;
	}

	/**
	 * Get lead time distribution.
	 *
	 * @param string $period Time period.
	 * @return array Lead time buckets.
	 */
	private function get_lead_time_distribution( $period ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_date_analytics';
		$date_limit = date_i18n( 'Y-m-d', strtotime( "-{$period}" ) );

		$buckets = array(
			'0-1 days' => 0,
			'2-3 days' => 0,
			'4-7 days' => 0,
			'8-14 days' => 0,
			'15+ days' => 0,
		);

		$results = $wpdb->get_results( $wpdb->prepare(
			"SELECT avg_lead_time, total_orders
			FROM {$table_name}
			WHERE last_selected >= %s",
			$date_limit
		) );

		foreach ( $results as $row ) {
			$days = intval( $row->avg_lead_time );

			if ( $days <= 1 ) {
				$buckets['0-1 days'] += $row->total_orders;
			} elseif ( $days <= 3 ) {
				$buckets['2-3 days'] += $row->total_orders;
			} elseif ( $days <= 7 ) {
				$buckets['4-7 days'] += $row->total_orders;
			} elseif ( $days <= 14 ) {
				$buckets['8-14 days'] += $row->total_orders;
			} else {
				$buckets['15+ days'] += $row->total_orders;
			}
		}

		return $buckets;
	}

	/**
	 * Get monthly trends.
	 *
	 * @return array Monthly data.
	 */
	private function get_monthly_trends() {
		$stats = get_option( 'wc_collection_date_monthly_stats', array_fill( 1, 12, 0 ) );
		return $stats;
	}

	/**
	 * Export analytics data to CSV.
	 */
	public function export_analytics_data() {
		check_ajax_referer( 'wc_collection_date_analytics_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die();
		}

		$period = isset( $_POST['period'] ) ? sanitize_text_field( $_POST['period'] ) : '30days';
		$data_type = isset( $_POST['data_type'] ) ? sanitize_text_field( $_POST['data_type'] ) : 'summary';

		switch ( $data_type ) {
			case 'popular_dates':
				$data = $this->get_popular_dates( $period );
				$filename = 'collection-dates-popular-' . $period . '.csv';
				$headers = array( 'Date', 'Selection Count', 'Total Orders', 'Total Value', 'Avg Lead Time' );
				break;

			case 'hourly':
				$data = $this->get_hourly_distribution();
				$filename = 'collection-dates-hourly-' . $period . '.csv';
				$headers = array( 'Hour', 'Order Count' );
				break;

			case 'daily':
				global $wpdb;
				$table_name = $wpdb->prefix . 'wc_collection_date_analytics';
				$date_limit = date_i18n( 'Y-m-d', strtotime( "-{$period}" ) );

				$data = $wpdb->get_results( $wpdb->prepare(
					"SELECT * FROM {$table_name} WHERE last_selected >= %s ORDER BY collection_date DESC",
					$date_limit
				) );
				$filename = 'collection-dates-daily-' . $period . '.csv';
				$headers = array( 'Date', 'Selections', 'Orders', 'Total Value', 'Avg Lead Time', 'Last Selected' );
				break;

			default:
				wp_send_json_error( 'Invalid data type' );
				return;
		}

		// Generate CSV
		$csv = '"' . implode( '","', $headers ) . "\"\n";

		foreach ( $data as $row ) {
			if ( is_object( $row ) ) {
				$values = array(
					$row->collection_date ?? '',
					$row->selection_count ?? 0,
					$row->total_orders ?? 0,
					$row->total_value ?? 0,
					$row->avg_lead_time ?? 0,
					$row->last_selected ?? '',
				);
			} else {
				$values = array_keys( $row );
			}

			$csv .= '"' . implode( '","', $values ) . "\"\n";
		}

		// Send file
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename="' . $filename . '"' );
		header( 'Cache-Control: no-cache, no-store, must-revalidate' );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		echo $csv;
		exit;
	}

	/**
	 * Aggregate daily statistics (cron job).
	 */
	public function aggregate_daily_stats() {
		// Clean up old hourly stats
		$option_name = 'wc_collection_date_hourly_stats';
		$stats = get_option( $option_name, array() );

		$cutoff = date_i18n( 'Y-m-d', strtotime( '-90 days' ) );
		foreach ( $stats as $date => $data ) {
			if ( $date < $cutoff ) {
				unset( $stats[ $date ] );
			}
		}

		update_option( $option_name, $stats );

		// Aggregate monthly stats to yearly for historical data
		$this->archive_monthly_stats();
	}

	/**
	 * Archive monthly statistics to yearly.
	 */
	private function archive_monthly_stats() {
		$monthly_stats = get_option( 'wc_collection_date_monthly_stats', array_fill( 1, 12, 0 ) );
		$current_year = date_i18n( 'Y' );

		$yearly_key = 'wc_collection_date_yearly_stats_' . $current_year;
		$yearly_stats = get_option( $yearly_key, array_fill( 1, 12, 0 ) );

		// Add current year's stats to archive
		for ( $month = 1; $month <= 12; $month++ ) {
			$yearly_stats[ $month ] += $monthly_stats[ $month ];
		}

		update_option( $yearly_key, $yearly_stats );
	}

	/**
	 * Get analytics for specific date range.
	 *
	 * @param string $start_date Start date (Y-m-d).
	 * @param string $end_date   End date (Y-m-d).
	 * @return array Analytics data.
	 */
	public function get_date_range_analytics( $start_date, $end_date ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_date_analytics';

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT
				collection_date,
				selection_count,
				total_orders,
				total_value,
				avg_lead_time,
				DAYOFWEEK(collection_date) as day_of_week
			FROM {$table_name}
			WHERE collection_date BETWEEN %s AND %s
			ORDER BY collection_date",
			$start_date,
			$end_date
		) );
	}
}