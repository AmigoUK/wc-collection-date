<?php
/**
 * REST API Class
 *
 * Handles REST API endpoints for AJAX requests.
 *
 * @package WC_Collection_Date
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * REST API class.
 */
class WC_Collection_Date_REST_API {

	/**
	 * API namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'wc-collection-date/v1';

	/**
	 * Date calculator instance.
	 *
	 * @var WC_Collection_Date_Calculator
	 */
	protected $calculator;

	/**
	 * Calendar service instance.
	 *
	 * @var WC_Collection_Date_Calendar_Service
	 */
	protected $calendar_service;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->calculator = new WC_Collection_Date_Calculator();
		$this->calendar_service = new WC_Collection_Date_Calendar_Service();
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	protected function init_hooks() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes.
	 */
	public function register_routes() {
		// Get available dates.
		register_rest_route(
			$this->namespace,
			'/dates',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_available_dates' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'limit' => array(
						'description'       => __( 'Number of dates to return', 'wc-collection-date' ),
						'type'              => 'integer',
						'default'           => 90,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Check if specific date is available.
		register_rest_route(
			$this->namespace,
			'/dates/check',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'check_date_availability' ),
				'permission_callback' => '__return_true',
				'args'                => array(
					'date' => array(
						'description'       => __( 'Date to check in Y-m-d format', 'wc-collection-date' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Get date range.
		register_rest_route(
			$this->namespace,
			'/dates/range',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_date_range' ),
				'permission_callback' => '__return_true',
			)
		);

		// Get plugin settings (for frontend).
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_settings' ),
				'permission_callback' => '__return_true',
			)
		);

		// ===== Calendar Admin Endpoints =====

		// Get calendar data for a specific month (admin only).
		register_rest_route(
			$this->namespace,
			'/calendar/month',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_calendar_month' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'month' => array(
						'description'       => __( 'Month in Y-m format (e.g., 2024-01)', 'wc-collection-date' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
				),
			)
		);

		// Get multiple months of calendar data (admin only).
		register_rest_route(
			$this->namespace,
			'/calendar/multi-month',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_multi_month_calendar' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'start_date' => array(
						'description'       => __( 'Start date in Y-m-d format', 'wc-collection-date' ),
						'type'              => 'string',
						'required'          => true,
						'sanitize_callback' => 'sanitize_text_field',
					),
					'months' => array(
						'description'       => __( 'Number of months to return', 'wc-collection-date' ),
						'type'              => 'integer',
						'default'           => 3,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Get capacity settings for a specific date (admin only).
		register_rest_route(
			$this->namespace,
			'/calendar/capacity/(?P<date>[\d]{4}-[\d]{2}-[\d]{2})',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_capacity_settings' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
			)
		);

		// Update capacity settings for a specific date (admin only).
		register_rest_route(
			$this->namespace,
			'/calendar/capacity/(?P<date>[\d]{4}-[\d]{2}-[\d]{2})',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_capacity_settings' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'max_capacity' => array(
						'description'       => __( 'Maximum capacity for the date', 'wc-collection-date' ),
						'type'              => 'integer',
						'minimum'           => 0,
						'sanitize_callback' => 'absint',
					),
					'current_bookings' => array(
						'description'       => __( 'Current number of bookings', 'wc-collection-date' ),
						'type'              => 'integer',
						'minimum'           => 0,
						'sanitize_callback' => 'absint',
					),
					'is_enabled' => array(
						'description'       => __( 'Whether capacity management is enabled for this date', 'wc-collection-date' ),
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'notes' => array(
						'description'       => __( 'Optional notes for the date', 'wc-collection-date' ),
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_textarea_field',
					),
				),
			)
		);

		// Update booking count for a specific date (admin only).
		register_rest_route(
			$this->namespace,
			'/calendar/bookings/(?P<date>[\d]{4}-[\d]{2}-[\d]{2})',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_booking_count' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'change' => array(
						'description'       => __( 'Number of bookings to add (positive) or remove (negative)', 'wc-collection-date' ),
						'type'              => 'integer',
						'required'          => false,
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		// Get calendar settings (admin only).
		register_rest_route(
			$this->namespace,
			'/calendar/settings',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_calendar_settings' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
			)
		);

		// Update calendar settings (admin only).
		register_rest_route(
			$this->namespace,
			'/calendar/settings',
			array(
				'methods'             => WP_REST_Server::EDITABLE,
				'callback'            => array( $this, 'update_calendar_settings' ),
				'permission_callback' => array( $this, 'admin_permissions_check' ),
				'args'                => array(
					'capacity_enabled' => array(
						'description'       => __( 'Enable capacity management', 'wc-collection-date' ),
						'type'              => 'boolean',
						'sanitize_callback' => 'rest_sanitize_boolean',
					),
					'default_capacity' => array(
						'description'       => __( 'Default daily capacity', 'wc-collection-date' ),
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'capacity_buffer' => array(
						'description'       => __( 'Capacity buffer (reserved slots)', 'wc-collection-date' ),
						'type'              => 'integer',
						'minimum'           => 0,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	/**
	 * Get available collection dates.
	 *
	 * Analyzes cart contents and uses appropriate lead time rules.
	 * If cart has products, uses longest lead time from all products.
	 * Otherwise, uses global settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_available_dates( $request ) {
		$limit = $request->get_param( 'limit' );

		// Analyze cart for product-specific rules.
		$cart_product_id = $this->get_cart_product_with_longest_lead_time();

		// Use product-aware dates if cart has products, otherwise use global.
		if ( $cart_product_id ) {
			$dates = $this->calculator->get_available_dates_for_product( $cart_product_id, $limit );
		} else {
			$dates = $this->calculator->get_available_dates( $limit );
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'dates'   => $dates,
				'count'   => count( $dates ),
			)
		);
	}

	/**
	 * Analyze cart and return product ID with longest lead time.
	 *
	 * @return int|null Product ID with longest lead time, or null if cart is empty.
	 */
	protected function get_cart_product_with_longest_lead_time() {
		if ( ! function_exists( 'WC' ) || ! WC()->cart ) {
			return null;
		}

		$cart = WC()->cart->get_cart();

		if ( empty( $cart ) ) {
			return null;
		}

		$resolver = new WC_Collection_Date_Lead_Time_Resolver();
		$longest_lead_time = 0;
		$longest_product_id = null;

		foreach ( $cart as $cart_item ) {
			$product_id = isset( $cart_item['product_id'] ) ? absint( $cart_item['product_id'] ) : 0;

			if ( ! $product_id ) {
				continue;
			}

			// Get effective settings for this product.
			$settings = $resolver->get_effective_settings( $product_id );
			$lead_time = isset( $settings['lead_time'] ) ? absint( $settings['lead_time'] ) : 0;

			// Track product with longest lead time.
			if ( $lead_time > $longest_lead_time ) {
				$longest_lead_time = $lead_time;
				$longest_product_id = $product_id;
			}
		}

		return $longest_product_id;
	}

	/**
	 * Check if specific date is available.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function check_date_availability( $request ) {
		$date        = $request->get_param( 'date' );
		$is_available = $this->calculator->is_date_available( $date );

		return rest_ensure_response(
			array(
				'success'   => true,
				'date'      => $date,
				'available' => $is_available,
				'message'   => $is_available
					? __( 'Date is available', 'wc-collection-date' )
					: __( 'Date is not available', 'wc-collection-date' ),
			)
		);
	}

	/**
	 * Get date range.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_date_range( $request ) {
		$range = $this->calculator->get_date_range();

		return rest_ensure_response(
			array(
				'success'  => true,
				'min_date' => $range['min_date'],
				'max_date' => $range['max_date'],
			)
		);
	}

	/**
	 * Get plugin settings for frontend.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_settings( $request ) {
		$working_days = get_option( 'wc_collection_date_working_days', array( '1', '2', '3', '4', '5', '6' ) );

		if ( ! is_array( $working_days ) ) {
			$working_days = array();
		}

		return rest_ensure_response(
			array(
				'success'      => true,
				'settings'     => array(
					'lead_time'         => absint( get_option( 'wc_collection_date_lead_time', 2 ) ),
					'max_booking_days'  => absint( get_option( 'wc_collection_date_max_booking_days', 90 ) ),
					'working_days'      => array_map( 'intval', $working_days ),
					'date_format'       => get_option( 'date_format' ),
				),
			)
		);
	}

	/**
	 * Check if user has admin permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error True if user has permission, WP_Error otherwise.
	 */
	public function admin_permissions_check( $request ) {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return new WP_Error(
				'rest_forbidden',
				__( 'Sorry, you are not allowed to do that.', 'wc-collection-date' ),
				array( 'status' => 403 )
			);
		}

		// Verify nonce for POST/PUT/DELETE requests
		if ( in_array( $request->get_method(), array( 'POST', 'PUT', 'DELETE' ), true ) ) {
			$nonce = $request->get_header( 'X-WP-Nonce' );
			if ( ! $nonce || ! wp_verify_nonce( $nonce, 'wp_rest' ) ) {
				return new WP_Error(
					'rest_nonce_invalid',
					__( 'Nonce is invalid.', 'wc-collection-date' ),
					array( 'status' => 403 )
				);
			}
		}

		return true;
	}

	/**
	 * Get calendar data for a specific month.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_calendar_month( $request ) {
		$month = $request->get_param( 'month' );

		$calendar_data = $this->calendar_service->get_calendar_data( $month );

		if ( isset( $calendar_data['error'] ) ) {
			return new WP_Error(
				'invalid_month',
				$calendar_data['error'],
				array( 'status' => 400 )
			);
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $calendar_data,
			)
		);
	}

	/**
	 * Get multiple months of calendar data.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_multi_month_calendar( $request ) {
		$start_date = $request->get_param( 'start_date' );
		$months = $request->get_param( 'months' );

		$calendar_data = $this->calendar_service->get_multi_month_calendar( $start_date, $months );

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $calendar_data,
				'count'   => count( $calendar_data ),
			)
		);
	}

	/**
	 * Get capacity settings for a specific date.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_capacity_settings( $request ) {
		$date = $request->get_param( 'date' );

		$capacity = $this->calendar_service->get_capacity_settings( $date );

		if ( is_wp_error( $capacity ) ) {
			return $capacity;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $capacity,
			)
		);
	}

	/**
	 * Update capacity settings for a specific date.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function update_capacity_settings( $request ) {
		$date = $request->get_param( 'date' );

		// Build settings array from request parameters
		$settings = array();
		$params = array( 'max_capacity', 'current_bookings', 'is_enabled', 'notes' );

		foreach ( $params as $param ) {
			if ( $request->has_param( $param ) ) {
				$settings[ $param ] = $request->get_param( $param );
			}
		}

		$result = $this->calendar_service->update_capacity_settings( $date, $settings );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Capacity settings updated successfully.', 'wc-collection-date' ),
			)
		);
	}

	/**
	 * Update booking count for a specific date.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function update_booking_count( $request ) {
		$date = $request->get_param( 'date' );
		$change = $request->get_param( 'change' );

		$result = $this->calendar_service->update_booking_count( $date, $change );

		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$action = $change > 0 ? 'added' : 'removed';
		$message = sprintf(
			/* translators: %d: number of bookings, %s: action (added/removed) */
			__( '%d bookings %s successfully.', 'wc-collection-date' ),
			absint( $change ),
			$action
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => $message,
			)
		);
	}

	/**
	 * Get calendar settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function get_calendar_settings( $request ) {
		$settings = array(
			'capacity_enabled' => (bool) get_option( 'wc_collection_date_capacity_enabled', false ),
			'default_capacity' => absint( get_option( 'wc_collection_date_default_capacity', 50 ) ),
			'capacity_buffer' => absint( get_option( 'wc_collection_date_capacity_buffer', 5 ) ),
		);

		return rest_ensure_response(
			array(
				'success' => true,
				'data'    => $settings,
			)
		);
	}

	/**
	 * Update calendar settings.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function update_calendar_settings( $request ) {
		// Update each setting if provided
		$settings_map = array(
			'capacity_enabled' => 'wc_collection_date_capacity_enabled',
			'default_capacity' => 'wc_collection_date_default_capacity',
			'capacity_buffer' => 'wc_collection_date_capacity_buffer',
		);

		foreach ( $settings_map as $request_param => $option_name ) {
			if ( $request->has_param( $request_param ) ) {
				$value = $request->get_param( $request_param );
				update_option( $option_name, $value );
			}
		}

		return rest_ensure_response(
			array(
				'success' => true,
				'message' => __( 'Calendar settings updated successfully.', 'wc-collection-date' ),
			)
		);
	}
}
