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
	 * Constructor.
	 */
	public function __construct() {
		$this->calculator = new WC_Collection_Date_Calculator();
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
}
