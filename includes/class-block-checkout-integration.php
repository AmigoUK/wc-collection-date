<?php
/**
 * WooCommerce Blocks Checkout Integration
 *
 * Registers collection date field with Store API.
 *
 * @package WC_Collection_Date
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

use Automattic\WooCommerce\StoreApi\Schemas\ExtendSchema;
use Automattic\WooCommerce\StoreApi\StoreApi;
use Automattic\WooCommerce\StoreApi\Schemas\V1\CheckoutSchema;

/**
 * Block Checkout Integration class.
 */
class WC_Collection_Date_Block_Integration {

	/**
	 * Constructor.
	 */
	public function __construct() {
		// Register Store API extension
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_store_api_extension' ) );

		// Alternative: Hook into checkout data processing
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'save_from_request' ), 10, 2 );
	}

	/**
	 * Register Store API extension for collection date.
	 */
	public function register_store_api_extension() {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}


		woocommerce_store_api_register_endpoint_data(
			array(
				'endpoint'        => CheckoutSchema::IDENTIFIER,
				'namespace'       => 'wc-collection-date',
				'data_callback'   => array( $this, 'extend_store_api_data' ),
				'schema_callback' => array( $this, 'extend_store_api_schema' ),
				'schema_type'     => ARRAY_A,
			)
		);

	}

	/**
	 * Extend Store API data.
	 *
	 * @return array
	 */
	public function extend_store_api_data() {
		return array(
			'collection_date' => '',
		);
	}

	/**
	 * Extend Store API schema.
	 *
	 * @return array
	 */
	public function extend_store_api_schema() {
		return array(
			'collection_date' => array(
				'description' => __( 'Collection date for local pickup', 'wc-collection-date' ),
				'type'        => 'string',
				'context'     => array( 'view', 'edit' ),
				'required'    => true,
			),
		);
	}

	/**
	 * Save collection date from Store API request.
	 *
	 * @param WC_Order        $order   Order object.
	 * @param WP_REST_Request $request Request object.
	 */
	public function save_from_request( $order, $request ) {

		$collection_date = '';

		// Try multiple sources for the collection date

		// 1. Try to get from POST parameter (hidden field approach)
		if ( isset( $_POST['wc_collection_date'] ) && ! empty( $_POST['wc_collection_date'] ) ) {
			$collection_date = sanitize_text_field( wp_unslash( $_POST['wc_collection_date'] ) );
		}

		// 2. Try to get from extensions (Store API approach)
		if ( empty( $collection_date ) ) {
			$extensions = $request->get_param( 'extensions' );
			if ( isset( $extensions['wc-collection-date']['collection_date'] ) ) {
				$collection_date = sanitize_text_field( $extensions['wc-collection-date']['collection_date'] );
			}
		}

		// 3. Validate and save
		if ( ! empty( $collection_date ) && trim( $collection_date ) !== '' ) {
			$order->update_meta_data( '_collection_date', $collection_date );
			$order->save();

			// Verify save
			$saved = $order->get_meta( '_collection_date' );
		} else {

			// Add validation error
			throw new \Exception( __( 'Please select a collection date.', 'wc-collection-date' ) );
		}
	}
}
