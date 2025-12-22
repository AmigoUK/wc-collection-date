<?php
/**
 * Admin functionality
 *
 * @package WC_Collection_Date
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Admin class
 */
class WC_Collection_Date_Admin {

	/**
	 * Settings instance
	 *
	 * @var WC_Collection_Date_Settings
	 */
	private $settings;

	/**
	 * Initialize the class
	 */
	public function __construct() {
		// Load settings class.
		require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/admin/class-settings.php';
		$this->settings = new WC_Collection_Date_Settings();

		// Register hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

		// Register AJAX handlers for calendar (register for both admin and AJAX contexts)
		add_action( 'wp_ajax_wc_collection_date_get_calendar_data', array( $this, 'ajax_get_calendar_data' ) );
		add_action( 'wp_ajax_wc_collection_date_update_capacity', array( $this, 'ajax_update_capacity' ) );
		add_action( 'wp_ajax_wc_collection_date_get_day_bookings', array( $this, 'ajax_get_day_bookings' ) );

		// Register AJAX handlers for date range exclusions
		add_action( 'wp_ajax_wc_collection_add_exclusion', array( $this, 'ajax_add_exclusion' ) );
		add_action( 'wp_ajax_wc_collection_delete_exclusion', array( $this, 'ajax_delete_exclusion' ) );
		add_action( 'wp_ajax_wc_collection_export_exclusions', array( $this, 'ajax_export_exclusions' ) );
	}

	/**
	 * Register admin menu
	 */
	public function add_admin_menu() {
		add_submenu_page(
			'woocommerce',
			__( 'Collection Dates', 'wc-collection-date' ),
			__( 'Collection Dates', 'wc-collection-date' ),
			'manage_woocommerce',
			'wc-collection-date',
			array( $this->settings, 'render_settings_page' )
		);
	}

	/**
	 * Enqueue admin styles
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_styles( $hook ) {
		// Only load on our settings page.
		if ( 'woocommerce_page_wc-collection-date' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'wc-collection-date-admin',
			WC_COLLECTION_DATE_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WC_COLLECTION_DATE_VERSION,
			'all'
		);

		// Check if we're on the calendar tab
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';

		if ( 'calendar' === $current_tab ) {
			// Enqueue calendar styles.
			wp_enqueue_style(
				'wc-collection-date-calendar',
				WC_COLLECTION_DATE_PLUGIN_URL . 'assets/css/calendar.css',
				array(),
				WC_COLLECTION_DATE_VERSION,
				'all'
			);
		}
	}

	/**
	 * Enqueue admin scripts
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_scripts( $hook ) {
		// Only load on our settings page.
		if ( 'woocommerce_page_wc-collection-date' !== $hook ) {
			return;
		}

		wp_enqueue_script(
			'wc-collection-date-admin',
			WC_COLLECTION_DATE_PLUGIN_URL . 'assets/js/admin.js',
			array( 'jquery' ),
			WC_COLLECTION_DATE_VERSION,
			true
		);

		// Check if we're on the calendar tab
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';

		if ( 'calendar' === $current_tab ) {
			// Enqueue calendar script.
			wp_enqueue_script(
				'wc-collection-date-calendar',
				WC_COLLECTION_DATE_PLUGIN_URL . 'assets/js/calendar.js',
				array( 'jquery', 'wc-collection-date-admin' ),
				WC_COLLECTION_DATE_VERSION,
				true
			);

			// Get month names
			$month_names = array(
				'January'   => __( 'January', 'wc-collection-date' ),
				'February'  => __( 'February', 'wc-collection-date' ),
				'March'     => __( 'March', 'wc-collection-date' ),
				'April'     => __( 'April', 'wc-collection-date' ),
				'May'       => __( 'May', 'wc-collection-date' ),
				'June'      => __( 'June', 'wc-collection-date' ),
				'July'      => __( 'July', 'wc-collection-date' ),
				'August'    => __( 'August', 'wc-collection-date' ),
				'September' => __( 'September', 'wc-collection-date' ),
				'October'   => __( 'October', 'wc-collection-date' ),
				'November'  => __( 'November', 'wc-collection-date' ),
				'December'  => __( 'December', 'wc-collection-date' ),
			);

			// Get day names (minimal format)
			$day_names_min = array(
				'Sun' => __( 'Su', 'wc-collection-date' ),
				'Mon' => __( 'Mo', 'wc-collection-date' ),
				'Tue' => __( 'Tu', 'wc-collection-date' ),
				'Wed' => __( 'We', 'wc-collection-date' ),
				'Thu' => __( 'Th', 'wc-collection-date' ),
				'Fri' => __( 'Fr', 'wc-collection-date' ),
				'Sat' => __( 'Sa', 'wc-collection-date' ),
			);

			// Pass data to JavaScript.
			wp_localize_script(
				'wc-collection-date-calendar',
				'wcCollectionCalendar',
				array(
					'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
					'nonce'       => wp_create_nonce( 'wc_collection_date_calendar' ),
					'today'       => current_time( 'Y-m-d' ),
					'firstDay'    => absint( get_option( 'start_of_week', 1 ) ),
					'monthNames'  => array_values( $month_names ),
					'dayNamesMin' => array_values( $day_names_min ),
					'texts'       => array(
						'today'            => __( 'Today', 'wc-collection-date' ),
						'capacity'         => __( 'Capacity', 'wc-collection-date' ),
						'booked'           => __( 'Booked', 'wc-collection-date' ),
						'available'        => __( 'Available', 'wc-collection-date' ),
						'unavailable'      => __( 'Unavailable', 'wc-collection-date' ),
						'excluded'         => __( 'Excluded', 'wc-collection-date' ),
						'loading'          => __( 'Loading...', 'wc-collection-date' ),
						'noBookings'       => __( 'No bookings for this date', 'wc-collection-date' ),
						'capacityUpdated'  => __( 'Capacity updated successfully!', 'wc-collection-date' ),
						'error'            => __( 'Error occurred. Please try again.', 'wc-collection-date' ),
						'confirmCapacity'  => __( 'Are you sure you want to update capacity for this date?', 'wc-collection-date' ),
						'view'             => __( 'View', 'wc-collection-date' ),
					),
				)
			);
		}
	}

	/**
	 * AJAX handler for getting calendar data.
	 *
	 * @since 1.2.0
	 */
	public function ajax_get_calendar_data() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'wc_collection_date_calendar', 'nonce', false ) ) {
			wp_die( json_encode( array( 'success' => false, 'message' => __( 'Security check failed.', 'wc-collection-date' ) ) ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( json_encode( array( 'success' => false, 'message' => __( 'Insufficient permissions.', 'wc-collection-date' ) ) ) );
		}

		$year  = isset( $_POST['year'] ) ? absint( $_POST['year'] ) : current_time( 'Y' );
		$month = isset( $_POST['month'] ) ? absint( $_POST['month'] ) : current_time( 'n' );

		// Load calendar class and get data
		require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/admin/class-calendar.php';
		$calendar = new WC_Collection_Date_Calendar();

		// Use reflection to access private method
		$reflection = new ReflectionClass( $calendar );
		$get_calendar_data = $reflection->getMethod( 'get_calendar_data' );
		$get_calendar_data->setAccessible( true );
		$calendar_data = $get_calendar_data->invoke( $calendar, $year, $month );

		$get_month_statistics = $reflection->getMethod( 'get_month_statistics' );
		$get_month_statistics->setAccessible( true );
		$statistics = $get_month_statistics->invoke( $calendar, $year, $month );

		wp_send_json_success(
			array(
				'calendar_data' => $calendar_data,
				'statistics'    => $statistics,
				'current_date'  => current_time( 'Y-m-d' ),
			)
		);
	}

	/**
	 * AJAX handler for updating capacity.
	 *
	 * @since 1.2.0
	 */
	public function ajax_update_capacity() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'wc_collection_date_calendar', 'nonce', false ) ) {
			wp_die( json_encode( array( 'success' => false, 'message' => __( 'Security check failed.', 'wc-collection-date' ) ) ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( json_encode( array( 'success' => false, 'message' => __( 'Insufficient permissions.', 'wc-collection-date' ) ) ) );
		}

		$date     = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';
		$capacity = isset( $_POST['capacity'] ) ? absint( $_POST['capacity'] ) : 0;

		if ( empty( $date ) ) {
			wp_die( json_encode( array( 'success' => false, 'message' => __( 'Invalid date.', 'wc-collection-date' ) ) ) );
		}

		if ( $capacity < 1 || $capacity > 999 ) {
			wp_die( json_encode( array( 'success' => false, 'message' => __( 'Invalid capacity value.', 'wc-collection-date' ) ) ) );
		}

		// Update capacity in database.
		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_collection_date_capacity';

		// Check if record exists.
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table_name} WHERE collection_date = %s",
				$date
			)
		);

		$updated = false;
		if ( $existing ) {
			// Update existing record.
			$updated = $wpdb->update(
				$table_name,
				array( 'max_capacity' => $capacity ),
				array( 'collection_date' => $date ),
				array( '%d' ),
				array( '%s' )
			);
		} else {
			// Insert new record.
			$updated = $wpdb->insert(
				$table_name,
				array(
					'collection_date' => $date,
					'max_capacity'    => $capacity,
					'available_slots'  => $capacity,
				),
				array( '%s', '%d', '%d' )
			);
		}

		if ( $updated ) {
			wp_send_json_success( array( 'message' => __( 'Capacity updated successfully!', 'wc-collection-date' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to update capacity.', 'wc-collection-date' ) ) );
		}
	}

	/**
	 * AJAX handler for getting day bookings.
	 *
	 * @since 1.2.0
	 */
	public function ajax_get_day_bookings() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'wc_collection_date_calendar', 'nonce', false ) ) {
			wp_die( json_encode( array( 'success' => false, 'message' => __( 'Security check failed.', 'wc-collection-date' ) ) ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( json_encode( array( 'success' => false, 'message' => __( 'Insufficient permissions.', 'wc-collection-date' ) ) ) );
		}

		$date = isset( $_POST['date'] ) ? sanitize_text_field( wp_unslash( $_POST['date'] ) ) : '';

		if ( empty( $date ) ) {
			wp_die( json_encode( array( 'success' => false, 'message' => __( 'Invalid date.', 'wc-collection-date' ) ) ) );
		}

		// Get bookings for the date.
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

		wp_send_json_success( array( 'bookings' => $bookings ) );
	}

	/**
	 * Validate date string.
	 *
	 * @since 1.2.0
	 * @param string $date Date string in Y-m-d format.
	 * @return bool True if valid date.
	 */
	private function is_valid_date( $date ) {
		$datetime = DateTime::createFromFormat( 'Y-m-d', $date );
		return $datetime && $datetime->format( 'Y-m-d' ) === $date;
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

	/**
	 * AJAX handler for adding exclusions.
	 *
	 * @since 1.4.0
	 */
	public function ajax_add_exclusion() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'wc_collection_add_exclusion', 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wc-collection-date' ) ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wc-collection-date' ) ) );
		}

		// Get exclusion data.
		$exclusion_type = isset( $_POST['exclusion_type'] ) ? sanitize_text_field( wp_unslash( $_POST['exclusion_type'] ) ) : 'single';
		$reason = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

		if ( empty( $reason ) ) {
			wp_send_json_error( array( 'message' => __( 'Reason is required.', 'wc-collection-date' ) ) );
		}

		$data = array(
			'exclusion_type' => $exclusion_type,
			'reason' => $reason,
		);

		if ( 'single' === $exclusion_type ) {
			$exclusion_date = isset( $_POST['exclusion_date'] ) ? sanitize_text_field( wp_unslash( $_POST['exclusion_date'] ) ) : '';
			if ( empty( $exclusion_date ) ) {
				wp_send_json_error( array( 'message' => __( 'Date is required for single exclusions.', 'wc-collection-date' ) ) );
			}
			$data['exclusion_date'] = $exclusion_date;
		} else {
			$exclusion_start = isset( $_POST['exclusion_start'] ) ? sanitize_text_field( wp_unslash( $_POST['exclusion_start'] ) ) : '';
			$exclusion_end = isset( $_POST['exclusion_end'] ) ? sanitize_text_field( wp_unslash( $_POST['exclusion_end'] ) ) : '';
			if ( empty( $exclusion_start ) || empty( $exclusion_end ) ) {
				wp_send_json_error( array( 'message' => __( 'Both start and end dates are required for date ranges.', 'wc-collection-date' ) ) );
			}
			$data['exclusion_start'] = $exclusion_start;
			$data['exclusion_end'] = $exclusion_end;
		}

		// Use ExclusionManager to add exclusion.
		$result = WC_Collection_Date_Exclusion_Manager::add_exclusion( $data );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Clear cache when exclusions change.
		WC_Collection_Date_Calculator::clear_cache();

		wp_send_json_success( array( 'message' => __( 'Exclusion added successfully!', 'wc-collection-date' ) ) );
	}

	/**
	 * AJAX handler for deleting exclusions.
	 *
	 * @since 1.4.0
	 */
	public function ajax_delete_exclusion() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'delete_exclusion_' . absint( $_POST['id'] ), 'nonce', false ) ) {
			wp_send_json_error( array( 'message' => __( 'Security check failed.', 'wc-collection-date' ) ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wc-collection-date' ) ) );
		}

		$id = isset( $_POST['id'] ) ? absint( $_POST['id'] ) : 0;
		if ( ! $id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid exclusion ID.', 'wc-collection-date' ) ) );
		}

		// Use ExclusionManager to delete exclusion.
		$result = WC_Collection_Date_Exclusion_Manager::delete_exclusion( $id );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		// Clear cache when exclusions change.
		WC_Collection_Date_Calculator::clear_cache();

		wp_send_json_success( array( 'message' => __( 'Exclusion deleted successfully!', 'wc-collection-date' ) ) );
	}

	/**
	 * AJAX handler for exporting exclusions.
	 *
	 * @since 1.4.0
	 */
	public function ajax_export_exclusions() {
		// Verify nonce.
		if ( ! check_ajax_referer( 'export_exclusions', 'nonce', false ) ) {
			wp_die( __( 'Security check failed.', 'wc-collection-date' ) );
		}

		// Check user capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( __( 'Insufficient permissions.', 'wc-collection-date' ) );
		}

		// Get all exclusions.
		$exclusions = WC_Collection_Date_Exclusion_Manager::get_exclusions( array( 'limit' => 10000 ) );

		// Prepare CSV data.
		$csv_data = array(
			array( 'Type', 'Start Date', 'End Date', 'Reason', 'Created' )
		);

		foreach ( $exclusions as $exclusion ) {
			if ( 'range' === $exclusion->exclusion_type ) {
				$csv_data[] = array(
					'Date Range',
					$exclusion->exclusion_start,
					$exclusion->exclusion_end,
					$exclusion->reason,
					$exclusion->created_at,
				);
			} else {
				$csv_data[] = array(
					'Single Date',
					$exclusion->exclusion_date,
					'',
					$exclusion->reason,
					$exclusion->created_at,
				);
			}
		}

		// Create CSV file.
		$filename = 'exclusions-export-' . date( 'Y-m-d' ) . '.csv';
		$handle = fopen( 'php://temp', 'w' );

		foreach ( $csv_data as $row ) {
			fputcsv( $handle, $row );
		}

		rewind( $handle );
		$csv = stream_get_contents( $handle );
		fclose( $handle );

		// Set headers for download.
		header( 'Content-Type: text/csv' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Content-Length: ' . strlen( $csv ) );
		header( 'Cache-Control: no-cache, must-revalidate' );
		header( 'Pragma: no-cache' );

		echo $csv;
		exit;
	}
}
