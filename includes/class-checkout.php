<?php
/**
 * Checkout Integration Class
 *
 * Handles WooCommerce checkout field and validation.
 *
 * @package WC_Collection_Date
 * @since 1.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Checkout class.
 */
class WC_Collection_Date_Checkout {

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
		// Add collection date field to checkout (multiple hooks for compatibility).
		add_action( 'woocommerce_after_order_notes', array( $this, 'add_collection_date_field' ) );
		add_action( 'woocommerce_review_order_before_payment', array( $this, 'add_collection_date_field' ) );
		add_action( 'woocommerce_checkout_billing', array( $this, 'add_collection_date_field' ) );

		// Classic Checkout: Validate collection date.
		add_action( 'woocommerce_checkout_process', array( $this, 'validate_collection_date' ) );

		// Classic Checkout: Save collection date to order meta.
		add_action( 'woocommerce_checkout_update_order_meta', array( $this, 'save_collection_date' ) );

		// Block Checkout: Validate and save via Store API
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'save_collection_date_block_checkout' ), 10, 2 );
		add_action( 'woocommerce_store_api_checkout_order_processed', array( $this, 'save_collection_date_after_processing' ) );

		// Display collection date in order confirmation.
		add_action( 'woocommerce_order_details_after_order_table', array( $this, 'display_collection_date_confirmation' ) );

		// Display collection date in admin order page.
		add_action( 'woocommerce_admin_order_data_after_billing_address', array( $this, 'display_collection_date_admin' ) );

		// Display collection date in emails.
		add_action( 'woocommerce_email_after_order_table', array( $this, 'display_collection_date_email' ), 10, 4 );

		// Add collection date to order display.
		add_filter( 'woocommerce_get_order_item_totals', array( $this, 'add_collection_date_to_order_display' ), 10, 2 );

		// Enqueue scripts and styles.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_checkout_assets' ) );

		// Add shortcode for manual placement
		add_shortcode( 'collection_date_field', array( $this, 'render_shortcode' ) );

		// Try to inject via footer as fallback
		add_action( 'wp_footer', array( $this, 'inject_field_via_js' ), 20 );
	}

	/**
	 * Add collection date field to checkout page.
	 *
	 * @param WC_Checkout $checkout Current checkout object.
	 */
	public function add_collection_date_field( $checkout = null ) {
		// Prevent duplicate rendering
		static $rendered = false;
		if ( $rendered ) {
			return;
		}
		$rendered = true;

		echo '<div id="collection_date_field" class="wc-collection-date-wrapper">';

		// Add label for inline calendar
		echo '<h3 class="wc-collection-date-label">' . esc_html__( 'Preferred Collection Date', 'wc-collection-date' ) . ' <abbr class="required" title="required">*</abbr></h3>';

		woocommerce_form_field(
			'collection_date',
			array(
				'type'        => 'text',
				'class'       => array( 'form-row-wide', 'wc-collection-date-field' ),
				'label'       => '',
				'placeholder' => __( 'Select a collection date', 'wc-collection-date' ),
				'required'    => true,
				'readonly'    => 'readonly',
				'custom_attributes' => array(
					'readonly' => 'readonly',
				),
			),
			$checkout ? $checkout->get_value( 'collection_date' ) : ''
		);

		echo '</div>';
	}

	/**
	 * Validate collection date field.
	 */
	public function validate_collection_date() {

		if ( empty( $_POST['collection_date'] ) ) {
			wc_add_notice(
				__( 'Please select a collection date.', 'wc-collection-date' ),
				'error'
			);
			return;
		}

		$collection_date = sanitize_text_field( wp_unslash( $_POST['collection_date'] ) );

		if ( ! $this->calculator->is_date_available( $collection_date ) ) {
			wc_add_notice(
				__( 'The selected collection date is not available. Please choose another date.', 'wc-collection-date' ),
				'error'
			);
		} else {
		}
	}

	/**
	 * Save collection date to order meta.
	 *
	 * @param int $order_id Order ID.
	 */
	public function save_collection_date( $order_id ) {

		if ( ! empty( $_POST['collection_date'] ) ) {
			$collection_date = sanitize_text_field( wp_unslash( $_POST['collection_date'] ) );

			// Get order object for HPOS compatibility
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$order->update_meta_data( '_collection_date', $collection_date );
				$order->save();

				// Verify it was saved
				$saved_date = $order->get_meta( '_collection_date' );
			}
		} else {
		}
	}

	/**
	 * Display collection date in order confirmation page.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function display_collection_date_confirmation( $order ) {
		$collection_date = $order->get_meta( '_collection_date' );

		if ( $collection_date ) {
			$formatted_date = $this->calculator->format_date_for_display( $collection_date );
			?>
			<div class="wc-collection-date-confirmation">
				<h2><?php esc_html_e( 'Collection Information', 'wc-collection-date' ); ?></h2>
				<table class="woocommerce-table woocommerce-table--collection-date shop_table collection_date">
					<tbody>
						<tr>
							<th><?php esc_html_e( 'Collection Date:', 'wc-collection-date' ); ?></th>
							<td><strong><?php echo esc_html( $formatted_date ); ?></strong></td>
						</tr>
					</tbody>
				</table>
			</div>
			<?php
		}
	}

	/**
	 * Display collection date in admin order page.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function display_collection_date_admin( $order ) {
		$collection_date = $order->get_meta( '_collection_date' );

		if ( $collection_date ) {
			$formatted_date = $this->calculator->format_date_for_display( $collection_date );
			?>
			<div class="wc-collection-date-admin">
				<h3><?php esc_html_e( 'Collection Information', 'wc-collection-date' ); ?></h3>
				<p>
					<strong><?php esc_html_e( 'Collection Date:', 'wc-collection-date' ); ?></strong><br>
					<?php echo esc_html( $formatted_date ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Display collection date in order emails.
	 *
	 * @param WC_Order $order Order object.
	 * @param bool     $sent_to_admin Whether sent to admin.
	 * @param bool     $plain_text Whether plain text email.
	 * @param WC_Email $email Email object.
	 */
	public function display_collection_date_email( $order, $sent_to_admin, $plain_text, $email ) {
		$collection_date = $order->get_meta( '_collection_date' );

		if ( ! $collection_date ) {
			return;
		}

		$formatted_date = $this->calculator->format_date_for_display( $collection_date );

		if ( $plain_text ) {
			echo "\n" . esc_html__( 'COLLECTION INFORMATION', 'wc-collection-date' ) . "\n";
			echo esc_html__( 'Collection Date:', 'wc-collection-date' ) . ' ' . esc_html( $formatted_date ) . "\n\n";
		} else {
			?>
			<div class="wc-collection-date-email" style="margin: 20px 0; padding: 15px; background: #f5f5f5; border: 1px solid #ddd;">
				<h2 style="margin-top: 0;"><?php esc_html_e( 'Collection Information', 'wc-collection-date' ); ?></h2>
				<p style="margin: 0;">
					<strong><?php esc_html_e( 'Collection Date:', 'wc-collection-date' ); ?></strong><br>
					<?php echo esc_html( $formatted_date ); ?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Add collection date to order totals display.
	 *
	 * @param array    $total_rows Order total rows.
	 * @param WC_Order $order Order object.
	 * @return array Modified total rows.
	 */
	public function add_collection_date_to_order_display( $total_rows, $order ) {
		$collection_date = $order->get_meta( '_collection_date' );

		if ( $collection_date ) {
			$formatted_date = $this->calculator->format_date_for_display( $collection_date );

			$new_rows = array();
			foreach ( $total_rows as $key => $row ) {
				$new_rows[ $key ] = $row;

				// Add collection date after payment method.
				if ( 'payment_method' === $key ) {
					$new_rows['collection_date'] = array(
						'label' => __( 'Collection Date:', 'wc-collection-date' ),
						'value' => esc_html( $formatted_date ),
					);
				}
			}

			return $new_rows;
		}

		return $total_rows;
	}

	/**
	 * Enqueue checkout assets.
	 */
	public function enqueue_checkout_assets() {
		if ( ! is_checkout() ) {
			return;
		}

		// Enqueue Flatpickr from CDN.
		wp_enqueue_style(
			'flatpickr',
			'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css',
			array(),
			'4.6.13'
		);

		wp_enqueue_script(
			'flatpickr',
			'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js',
			array(),
			'4.6.13',
			true
		);

		// Enqueue custom CSS.
		wp_enqueue_style(
			'wc-collection-date-checkout',
			plugins_url( 'assets/css/checkout.css', dirname( __FILE__ ) ),
			array( 'flatpickr' ),
			WC_COLLECTION_DATE_VERSION
		);

		// Enqueue custom JS with cache busting.
		wp_enqueue_script(
			'wc-collection-date-checkout',
			plugins_url( 'assets/js/checkout.js', dirname( __FILE__ ) ),
			array( 'jquery', 'flatpickr' ),
			WC_COLLECTION_DATE_VERSION . '.' . time(),
			true
		);

		// Localize script with data.
		wp_localize_script(
			'wc-collection-date-checkout',
			'wcCollectionDate',
			array(
				'ajaxUrl'      => admin_url( 'admin-ajax.php' ),
				'nonce'        => wp_create_nonce( 'wc_collection_date_nonce' ),
				'restUrl'      => rest_url( 'wc-collection-date/v1' ),
				'restNonce'    => wp_create_nonce( 'wp_rest' ),
				'dateFormat'   => $this->get_flatpickr_date_format(),
				'i18n'         => array(
					'selectDate' => __( 'Please select a collection date', 'wc-collection-date' ),
					'noDate'     => __( 'No available dates', 'wc-collection-date' ),
					'loading'    => __( 'Loading...', 'wc-collection-date' ),
				),
			)
		);
	}

	/**
	 * Get Flatpickr date format based on WordPress date format.
	 *
	 * @return string Flatpickr date format.
	 */
	protected function get_flatpickr_date_format() {
		$wp_format = get_option( 'date_format' );

		// Basic format mapping.
		$format_map = array(
			'Y-m-d' => 'Y-m-d',
			'd/m/Y' => 'd/m/Y',
			'm/d/Y' => 'm/d/Y',
			'F j, Y' => 'F j, Y',
			'j F Y' => 'j F Y',
		);

		return isset( $format_map[ $wp_format ] ) ? $format_map[ $wp_format ] : 'Y-m-d';
	}

	/**
	 * Render field via shortcode
	 */
	public function render_shortcode() {
		ob_start();
		$this->add_collection_date_field();
		return ob_get_clean();
	}

	/**
	 * Inject field via JavaScript for Block Checkout compatibility
	 */
	public function inject_field_via_js() {
		if ( ! is_checkout() || is_order_received_page() ) {
			return;
		}

		ob_start();
		$this->add_collection_date_field();
		$field_html = ob_get_clean();

		// Escape for JavaScript
		$field_html = str_replace( array( "\r", "\n" ), '', $field_html );
		$field_html = addslashes( $field_html );
		?>
		<script>
		jQuery(document).ready(function($) {
			console.log('Attempting to inject collection date field...');

			// Wait for WooCommerce Blocks to fully render
			function injectField() {
				// Try multiple injection points for different checkout types
				var injectionPoints = [
					'.wc-block-components-checkout-step__container', // Block checkout
					'.wp-block-woocommerce-checkout-contact-information-block', // Block checkout contact
					'.woocommerce-billing-fields',                   // Classic after billing
					'.woocommerce-additional-fields',                // Classic after additional
					'form.checkout'                                  // Fallback: end of form
				];

				var fieldHtml = '<?php echo $field_html; ?>';
				var injected = false;

				// Check if already injected
				if ($('#collection_date_field').length > 0) {
					console.log('Collection date field already exists');
					return true;
				}

				for (var i = 0; i < injectionPoints.length; i++) {
					var $target = $(injectionPoints[i]).first();
					if ($target.length > 0 && !injected) {
						console.log('Injecting field after:', injectionPoints[i]);
						$target.after(fieldHtml);
						injected = true;

						// Re-initialize date picker after injection
						setTimeout(function() {
							if (window.wcCollectionDatePicker) {
								console.log('Re-initializing date picker after injection');
								window.wcCollectionDatePicker.init();
							}
						}, 1000);
						break;
					}
				}

				if (!injected) {
					console.warn('Could not find suitable injection point for collection date field');
				}

				return injected;
			}

			// Try immediately
			if (!injectField()) {
				// If failed, wait for Blocks to load and try again
				console.log('Waiting for WooCommerce Blocks to load...');
				setTimeout(injectField, 2000);
			}
		});
		</script>
		<?php
	}

	/**
	 * Save collection date for Block Checkout via Store API.
	 *
	 * @param WC_Order $order Order object.
	 * @param WP_REST_Request $request Request object.
	 */
	public function save_collection_date_block_checkout( $order, $request ) {

		// Get extensions data from request
		$extensions = $request->get_param( 'extensions' );

		// Try to get collection_date from various locations
		$collection_date = null;

		// Check in extensions
		if ( isset( $extensions['wc-collection-date']['collection_date'] ) ) {
			$collection_date = $extensions['wc-collection-date']['collection_date'];
		}

		// Check in billing data
		$billing_data = $request->get_param( 'billing_address' );
		if ( ! $collection_date && isset( $billing_data['collection_date'] ) ) {
			$collection_date = $billing_data['collection_date'];
		}

		// Check directly in request params
		if ( ! $collection_date && $request->get_param( 'collection_date' ) ) {
			$collection_date = $request->get_param( 'collection_date' );
		}

		if ( $collection_date ) {
			$collection_date = sanitize_text_field( $collection_date );
			$order->update_meta_data( '_collection_date', $collection_date );
			$order->save();

			$saved_date = $order->get_meta( '_collection_date' );
		} else {
		}
	}

	/**
	 * Fallback save method for Block Checkout after order processing.
	 *
	 * @param WC_Order $order Order object.
	 */
	public function save_collection_date_after_processing( $order ) {

		// Check if already saved
		$existing_date = $order->get_meta( '_collection_date' );
		if ( $existing_date ) {
			return;
		}

		// Try to get from POST data as fallback
		if ( ! empty( $_POST['collection_date'] ) ) {
			$collection_date = sanitize_text_field( wp_unslash( $_POST['collection_date'] ) );
			$order->update_meta_data( '_collection_date', $collection_date );
			$order->save();
		} else {
		}
	}
}
