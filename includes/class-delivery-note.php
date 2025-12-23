<?php
/**
 * Delivery Note Generator for Cake/Confectionery Business
 *
 * Generates printable delivery notes with two sections:
 * 1. Full A4 delivery note for shop records
 * 2. Compact box label for customer packaging
 *
 * @package WC_Collection_Date
 * @since 1.5.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * WC_Collection_Date_Delivery_Note class.
 */
class WC_Collection_Date_Delivery_Note {

	/**
	 * The single instance of the class.
	 *
	 * @since 1.5.0
	 * @var   WC_Collection_Date_Delivery_Note
	 */
	protected static $_instance = null;

	/**
	 * Main WC_Collection_Date_Delivery_Note Instance.
	 *
	 * Ensures only one instance of WC_Collection_Date_Delivery_Note is loaded.
	 *
	 * @since  1.5.0
	 * @static
	 * @return WC_Collection_Date_Delivery_Note - Main instance
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
		// Add print button to order actions (HPOS compatible)
		add_filter( 'woocommerce_admin_order_actions', array( $this, 'add_print_button' ), 10, 2 );

		// Handle print action
		add_action( 'admin_action_wc_print_delivery_note', array( $this, 'print_delivery_note' ) );

		// Add bulk print action
		add_filter( 'bulk_admin_edit_get_actions', array( $this, 'add_bulk_print_action' ) );
		add_action( 'admin_action_wc_bulk_print_delivery_notes', array( $this, 'bulk_print_delivery_notes' ) );

		// Add meta box for delivery note options
		add_action( 'add_meta_boxes', array( $this, 'add_delivery_note_meta_box' ) );

		// AJAX handler for saving delivery note options
		add_action( 'wp_ajax_wc_save_delivery_note_options', array( $this, 'ajax_save_delivery_note_options' ) );

		// AJAX handler for printing
		add_action( 'wp_ajax_wc_print_delivery_note', array( $this, 'ajax_print_delivery_note' ) );
	}

	/**
	 * Add print button to order actions.
	 *
	 * @since 1.5.0
	 * @param array $actions Existing order actions.
	 * @param WC_Order $order Order object.
	 * @return array Modified actions array.
	 */
	public function add_print_button( $actions, $order ) {
		if ( ! is_object( $order ) || ! method_exists( $order, 'get_id' ) ) {
			return $actions;
		}

		$order_id = $order->get_id();
		$actions['wc_print_delivery_note'] = array(
			'url' => wp_nonce_url(
				admin_url( 'admin-ajax.php?action=wc_print_delivery_note&order_id=' . $order_id ),
				'wc_print_delivery_note_' . $order_id
			),
			'name' => __( '📄 Print Note', 'wc-collection-date' ),
			'action' => 'wc_print_delivery_note',
		);

		return $actions;
	}

	/**
	 * Add delivery note options meta box.
	 *
	 * @since 1.5.0
	 */
	public function add_delivery_note_meta_box() {
		add_meta_box(
			'wc_collection_date_delivery_note',
			__( 'Delivery Note Options', 'wc-collection-date' ),
			array( $this, 'render_delivery_note_meta_box' ),
			'shop_order',
			'side',
			'default'
		);
	}

	/**
	 * Render delivery note meta box content.
	 *
	 * @since 1.5.0
	 * @param WP_Post $post Post object.
	 */
	public function render_delivery_note_meta_box( $post ) {
		$order = wc_get_order( $post->ID );
		if ( ! $order ) {
			return;
		}

		// Get saved delivery note options
		$storage_instructions = $order->get_meta( '_storage_instructions', true );
		$allergen_info = $order->get_meta( '_allergen_info', true );
		$handling_instructions = $order->get_meta( '_handling_instructions', true );

		wp_nonce_field( 'wc_delivery_note_options', 'wc_delivery_note_nonce' );
		?>
		<div class="wc-delivery-note-options">
			<p>
				<label for="storage_instructions"><?php esc_html_e( 'Storage Instructions:', 'wc-collection-date' ); ?></label>
				<textarea id="storage_instructions" name="storage_instructions" class="large-text" rows="2"><?php echo esc_textarea( $storage_instructions ); ?></textarea>
			</p>

			<p>
				<label for="allergen_info"><?php esc_html_e( 'Allergen Information:', 'wc-collection-date' ); ?></label>
				<textarea id="allergen_info" name="allergen_info" class="large-text" rows="2" placeholder="<?php esc_attr_e( 'e.g., Contains: Nuts, Dairy, Eggs, Gluten', 'wc-collection-date' ); ?>"><?php echo esc_textarea( $allergen_info ); ?></textarea>
			</p>

			<p>
				<label for="handling_instructions"><?php esc_html_e( 'Handling Instructions:', 'wc-collection-date' ); ?></label>
				<select id="handling_instructions" name="handling_instructions" class="regular-text">
					<option value=""><?php esc_html_e( 'None', 'wc-collection-date' ); ?></option>
					<option value="fragile" <?php selected( $handling_instructions, 'fragile' ); ?>><?php esc_html_e( '⚠️ Fragile - Handle with Care', 'wc-collection-date' ); ?></option>
					<option value="refrigerate" <?php selected( $handling_instructions, 'refrigerate' ); ?>><?php esc_html_e( '❄️ Keep Refrigerated', 'wc-collection-date' ); ?></option>
					<option value="frozen" <?php selected( $handling_instructions, 'frozen' ); ?>><?php esc_html_e( '🧊 Keep Frozen', 'wc-collection-date' ); ?></option>
					<option value="tiered" <?php selected( $handling_instructions, 'tiered' ); ?>><?php esc_html_e( '🎂 Tiered Cake - Do Not Stack', 'wc-collection-date' ); ?></option>
				</select>
			</p>

			<p>
				<button type="button" class="button button-primary button-large" id="save-delivery-note-options">
					<?php esc_html_e( 'Save Options', 'wc-collection-date' ); ?>
				</button>
			</p>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			$('#save-delivery-note-options').on('click', function() {
				var data = {
					action: 'wc_save_delivery_note_options',
					order_id: <?php echo $order->get_id(); ?>,
					storage_instructions: $('#storage_instructions').val(),
					allergen_info: $('#allergen_info').val(),
					handling_instructions: $('#handling_instructions').val(),
					nonce: $('#wc_delivery_note_nonce').val()
				};

				$.post(ajaxurl, data, function(response) {
					if (response.success) {
						$('#save-delivery-note-options').after('<div class="notice notice-success inline"><p><?php esc_html_e( 'Options saved!', 'wc-collection-date' ); ?></p></div>');
						setTimeout(function() {
							$('.notice-success.inline').fadeOut();
						}, 2000);
					}
				});
			});
		});
		</script>
		<?php
	}

	/**
	 * AJAX handler for saving delivery note options.
	 *
	 * @since 1.5.0
	 */
	public function ajax_save_delivery_note_options() {
		check_ajax_referer( 'wc_delivery_note_options', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_send_json_error( 'Permission denied' );
		}

		$order_id = isset( $_POST['order_id'] ) ? absint( $_POST['order_id'] ) : 0;
		if ( ! $order_id ) {
			wp_send_json_error( 'Invalid order ID' );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_send_json_error( 'Order not found' );
		}

		// Save delivery note options
		if ( isset( $_POST['storage_instructions'] ) ) {
			$order->update_meta_data( '_storage_instructions', sanitize_textarea_field( $_POST['storage_instructions'] ) );
		}

		if ( isset( $_POST['allergen_info'] ) ) {
			$order->update_meta_data( '_allergen_info', sanitize_textarea_field( $_POST['allergen_info'] ) );
		}

		if ( isset( $_POST['handling_instructions'] ) ) {
			$order->update_meta_data( '_handling_instructions', sanitize_text_field( $_POST['handling_instructions'] ) );
		}

		$order->save();

		wp_send_json_success();
	}

	/**
	 * AJAX handler for printing delivery note.
	 *
	 * @since 1.5.0
	 */
	public function ajax_print_delivery_note() {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_die( 'Invalid order ID' );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( 'Order not found' );
		}

		$this->render_delivery_note( $order );
		exit;
	}

	/**
	 * Render delivery note HTML.
	 *
	 * @since 1.5.0
	 * @param WC_Order $order Order object.
	 */
	public function render_delivery_note( $order ) {
		// Get collection date
		$collection_date = $order->get_meta( '_collection_date', true );

		// Get delivery note options
		$storage_instructions = $order->get_meta( '_storage_instructions', true );
		$allergen_info = $order->get_meta( '_allergen_info', true );
		$handling_instructions = $order->get_meta( '_handling_instructions', true );

		// Get shop settings
		$shop_name = get_bloginfo( 'name' );
		$shop_address = get_option( 'woocommerce_store_address' );
		$shop_phone = get_option( 'woocommerce_store_phone' );
		$shop_email = get_option( 'woocommerce_email_from_address' );

		// Format collection date/time
		$collection_display = $collection_date ? date( 'l, j M Y', strtotime( $collection_date ) ) : 'Not set';

		?>
		<!DOCTYPE html>
		<html <?php language_attributes(); ?>>
		<head>
			<meta charset="<?php bloginfo( 'charset' ); ?>">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<title><?php printf( esc_html__( 'Delivery Note #%s', 'wc-collection-date' ), $order->get_order_number() ); ?></title>
			<style>
				@page {
					size: A4;
					margin: 0;
				}

				* {
					margin: 0;
					padding: 0;
					box-sizing: border-box;
				}

				body {
					font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
					font-size: 12px;
					line-height: 1.4;
					color: #333;
					background: #fff;
					-webkit-print-color-adjust: exact;
					print-color-adjust: exact;
				}

				.delivery-note-container {
					max-width: 210mm;
					margin: 0 auto;
					padding: 20px;
				}

				/* Full Delivery Note (A4) */
				.full-delivery-note {
					page-break-after: always;
					border: 1px solid #ddd;
					padding: 30px;
					margin-bottom: 20px;
				}

				.header {
					display: flex;
					justify-content: space-between;
					align-items: flex-start;
					margin-bottom: 30px;
					padding-bottom: 20px;
					border-bottom: 2px solid #333;
				}

				.shop-info h1 {
					font-size: 24px;
					margin-bottom: 10px;
					color: #333;
				}

				.shop-info p {
					margin: 5px 0;
					color: #666;
				}

				.document-title {
					text-align: right;
				}

				.document-title h2 {
					font-size: 32px;
					color: #333;
					margin-bottom: 5px;
				}

				.document-title .order-number {
					font-size: 18px;
					font-weight: bold;
					color: #666;
				}

				.section {
					margin-bottom: 25px;
				}

				.section-title {
					font-size: 14px;
					font-weight: bold;
					color: #333;
					background: #f5f5f5;
					padding: 8px 12px;
					margin-bottom: 15px;
					border-left: 4px solid #333;
				}

				.customer-info,
				.collection-info {
					display: flex;
					gap: 40px;
				}

				.info-group {
					flex: 1;
				}

				.info-group h4 {
					font-size: 12px;
					color: #666;
					margin-bottom: 10px;
					text-transform: uppercase;
					letter-spacing: 0.5px;
				}

				.info-group p {
					margin: 5px 0;
				}

				.info-group strong {
					display: inline-block;
					width: 100px;
					color: #333;
				}

				.collection-highlight {
					background: #e8f5e9;
					border: 2px solid #4caf50;
					padding: 20px;
					border-radius: 8px;
					text-align: center;
				}

				.collection-highlight .date {
					font-size: 28px;
					font-weight: bold;
					color: #2e7d32;
					margin: 10px 0;
				}

				.order-table {
					width: 100%;
					border-collapse: collapse;
					margin-bottom: 20px;
				}

				.order-table th,
				.order-table td {
					border: 1px solid #ddd;
					padding: 12px;
					text-align: left;
				}

				.order-table th {
					background: #f5f5f5;
					font-weight: bold;
					color: #333;
				}

				.order-table .item-name {
					font-weight: bold;
				}

				.order-table .item-meta {
					font-size: 11px;
					color: #666;
					margin-top: 4px;
				}

				.totals {
					width: 300px;
					margin-left: auto;
				}

				.totals .row {
					display: flex;
					justify-content: space-between;
					padding: 8px 0;
					border-bottom: 1px solid #eee;
				}

				.totals .row.total {
					font-size: 16px;
					font-weight: bold;
					border-top: 2px solid #333;
					border-bottom: none;
					padding-top: 15px;
				}

				.special-instructions {
					background: #fff3cd;
					border-left: 4px solid #ffc107;
					padding: 15px;
					margin: 20px 0;
				}

				.special-instructions h4 {
					color: #856404;
					margin-bottom: 10px;
				}

				.special-instructions p {
					margin: 5px 0;
					color: #856404;
				}

				.allergen-warning {
					background: #f8d7da;
					border: 2px solid #f5c6cb;
					padding: 15px;
					margin: 20px 0;
					border-radius: 4px;
				}

				.allergen-warning h4 {
					color: #721c24;
					margin-bottom: 10px;
				}

				.allergen-warning p {
					margin: 5px 0;
					color: #721c24;
				}

				.footer {
					margin-top: 40px;
					padding-top: 20px;
					border-top: 1px solid #ddd;
					text-align: center;
					color: #666;
					font-size: 11px;
				}

				/* Cut line */
				.cut-line {
					border-top: 2px dashed #333;
					margin: 30px 0;
					position: relative;
				}

				.cut-line::after {
					content: '✂ Cut here for box label';
					position: absolute;
					top: -12px;
					left: 50%;
					transform: translateX(-50%);
					background: #fff;
					padding: 0 15px;
					font-size: 10px;
					color: #666;
				}

				/* Box Label (Compact) */
				.box-label {
					width: 4in;
					height: 6in;
					border: 2px solid #333;
					padding: 15px;
					margin: 0 auto;
					page-break-after: always;
					background: #fff;
				}

				.box-label .label-header {
					text-align: center;
					border-bottom: 2px solid #333;
					padding-bottom: 10px;
					margin-bottom: 15px;
				}

				.box-label .shop-name {
					font-size: 16px;
					font-weight: bold;
					margin-bottom: 5px;
				}

				.box-label .order-info {
					font-size: 11px;
					color: #666;
				}

				.box-label .collection-section {
					background: #e8f5e9;
					border: 2px solid #4caf50;
					padding: 15px;
					text-align: center;
					margin: 15px 0;
					border-radius: 6px;
				}

				.box-label .collection-section .label {
					font-size: 10px;
					text-transform: uppercase;
					color: #2e7d32;
					margin-bottom: 5px;
				}

				.box-label .collection-section .date-time {
					font-size: 20px;
					font-weight: bold;
					color: #1b5e20;
				}

				.box-label .customer-name {
					font-size: 18px;
					font-weight: bold;
					text-align: center;
					margin: 15px 0;
					padding: 10px;
					background: #f5f5f5;
					border-radius: 4px;
				}

				.box-label .items {
					margin: 15px 0;
					padding: 10px;
					border: 1px solid #ddd;
					border-radius: 4px;
				}

				.box-label .item {
					margin-bottom: 10px;
					padding-bottom: 10px;
					border-bottom: 1px dashed #ddd;
				}

				.box-label .item:last-child {
					border-bottom: none;
					margin-bottom: 0;
					padding-bottom: 0;
				}

				.box-label .item-name {
					font-weight: bold;
					font-size: 12px;
				}

				.box-label .item-details {
					font-size: 10px;
					color: #666;
					margin-top: 3px;
				}

				.box-label .handling-badge {
					display: inline-block;
					padding: 5px 10px;
					border-radius: 4px;
					font-size: 10px;
					font-weight: bold;
					margin: 5px 2px;
				}

				.box-label .handling-refrigerate {
					background: #e3f2fd;
					color: #1565c0;
					border: 1px solid #2196f3;
				}

				.box-label .handling-frozen {
					background: #e1f5fe;
					color: #0277bd;
					border: 1px solid #03a9f4;
				}

				.box-label .handling-fragile {
					background: #ffebee;
					color: #c62828;
					border: 1px solid #ef5350;
				}

				.box-label .handling-tiered {
					background: #fff3e0;
					color: #e65100;
					border: 1px solid #ff9800;
				}

				.box-label .allergen-section {
					background: #ffebee;
					border: 1px solid #ef5350;
					padding: 10px;
					border-radius: 4px;
					margin: 15px 0;
					font-size: 10px;
				}

				.box-label .allergen-section strong {
					color: #c62828;
					display: block;
					margin-bottom: 5px;
				}

				.box-label .qr-placeholder {
					text-align: center;
					margin-top: 15px;
					padding: 10px;
					background: #f5f5f5;
					border-radius: 4px;
					font-size: 10px;
					color: #666;
				}

				.box-label .contact-info {
					text-align: center;
					font-size: 10px;
					color: #666;
					margin-top: 10px;
				}

				/* Print specific styles */
				@media print {
					body {
						background: #fff;
					}

					.delivery-note-container {
						box-shadow: none;
					}

					.no-print {
						display: none !important;
					}
				}
			</style>
		</head>
		<body>
			<div class="delivery-note-container">
				<!-- FULL DELIVERY NOTE (A4) -->
				<div class="full-delivery-note">
					<div class="header">
						<div class="shop-info">
							<h1><?php echo esc_html( $shop_name ); ?></h1>
							<p><?php echo esc_html( $shop_address ); ?></p>
							<p><?php echo esc_html( $shop_phone ); ?></p>
							<p><?php echo esc_html( $shop_email ); ?></p>
						</div>
						<div class="document-title">
							<h2>DELIVERY NOTE</h2>
							<div class="order-number">Order #<?php echo esc_html( $order->get_order_number() ); ?></div>
							<div><?php echo $order->get_date_created()->date_i18n( 'j M Y, g:i A' ); ?></div>
						</div>
					</div>

					<div class="section">
						<h3 class="section-title">Customer Information</h3>
						<div class="customer-info">
							<div class="info-group">
								<h4>Contact Details</h4>
								<p><strong>Name:</strong> <?php echo esc_html( $order->get_formatted_billing_full_name() ); ?></p>
								<p><strong>Phone:</strong> <?php echo esc_html( $order->get_billing_phone() ); ?></p>
								<p><strong>Email:</strong> <?php echo esc_html( $order->get_billing_email() ); ?></p>
							</div>
							<div class="info-group">
								<h4>Billing Address</h4>
								<?php echo $order->get_formatted_billing_address(); ?>
							</div>
						</div>
					</div>

					<div class="section">
						<h3 class="section-title">Collection Information</h3>
						<div class="collection-highlight">
							<div>📅 Collection Date & Time</div>
							<div class="date"><?php echo esc_html( $collection_display ); ?></div>
							<?php if ( $collection_time = $order->get_meta( '_collection_time', true ) ) : ?>
								<div style="font-size: 18px; margin-top: 10px;">
									🕐 <?php echo esc_html( $collection_time ); ?>
								</div>
							<?php endif; ?>
						</div>
					</div>

					<div class="section">
						<h3 class="section-title">Order Items</h3>
						<table class="order-table">
							<thead>
								<tr>
									<th>Item</th>
									<th>Qty</th>
									<th>Price</th>
									<th>Total</th>
								</tr>
							</thead>
							<tbody>
								<?php foreach ( $order->get_items() as $item_id => $item ) : ?>
									<tr>
										<td>
											<div class="item-name"><?php echo esc_html( $item->get_name() ); ?></div>
											<?php
											$item_meta = $item->get_meta_data();
											if ( ! empty( $item_meta ) ) :
												$meta_output = array();
												foreach ( $item_meta as $meta_id => $meta ) {
													if ( is_array( $meta->value ) ) {
														$meta->value = implode( ', ', $meta->value );
													}
													$meta_output[] = sprintf( '%s: %s', $meta->key, $meta->value );
												}
												if ( ! empty( $meta_output ) ) :
											?>
												<div class="item-meta"><?php echo esc_html( implode( ' | ', $meta_output ) ); ?></div>
											<?php
												endif;
											endif;
											?>
										</td>
										<td><?php echo esc_html( $item->get_quantity() ); ?></td>
										<td><?php echo $order->get_formatted_line_subtotal( $item ); ?></td>
										<td><?php echo $order->get_formatted_line_total( $item ); ?></td>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table>

						<div class="totals">
							<div class="row">
								<span>Subtotal:</span>
								<span><?php echo $order->get_formatted_subtotal(); ?></span>
							</div>
							<?php if ( $order->get_shipping_method() ) : ?>
							<div class="row">
								<span>Shipping:</span>
								<span><?php echo $order->get_formatted_shipping_total(); ?></span>
							</div>
							<?php endif; ?>
							<div class="row">
								<span>Total Paid:</span>
								<span><?php echo $order->get_formatted_total(); ?></span>
							</div>
							<div class="row total">
								<span>Balance Due:</span>
								<span><?php echo $order->get_formatted_total(); ?></span>
							</div>
						</div>
					</div>

					<?php if ( $order->get_customer_note() || $storage_instructions || $allergen_info ) : ?>
					<div class="section">
						<h3 class="section-title">Special Instructions</h3>

						<?php if ( $order->get_customer_note() ) : ?>
						<div class="special-instructions">
							<h4>📝 Customer Note:</h4>
							<p><?php echo nl2br( esc_html( $order->get_customer_note() ) ); ?></p>
						</div>
						<?php endif; ?>

						<?php if ( $storage_instructions ) : ?>
						<div class="special-instructions">
							<h4>❄️ Storage Instructions:</h4>
							<p><?php echo nl2br( esc_html( $storage_instructions ) ); ?></p>
						</div>
						<?php endif; ?>

						<?php if ( $allergen_info ) : ?>
						<div class="allergen-warning">
							<h4>⚠️ ALLERGEN INFORMATION</h4>
							<p><strong><?php echo nl2br( esc_html( $allergen_info ) ); ?></strong></p>
						</div>
						<?php endif; ?>
					</div>
					<?php endif; ?>

					<div class="footer">
						<p><strong>Thank you for your order!</strong></p>
						<p><?php echo esc_html( $shop_name ); ?> • <?php echo esc_html( $shop_phone ); ?></p>
						<p><?php echo esc_html( $shop_email ); ?></p>
					</div>
				</div>

				<!-- CUT LINE -->
				<div class="cut-line"></div>

				<!-- BOX LABEL (Compact 4x6) -->
				<div class="box-label">
					<div class="label-header">
						<div class="shop-name"><?php echo esc_html( $shop_name ); ?></div>
						<div class="order-info">
							Order #<?php echo esc_html( $order->get_order_number() ); ?> •
							<?php echo $order->get_date_created()->date_i18n( 'j M Y' ); ?>
						</div>
					</div>

					<div class="customer-name">
						<?php echo esc_html( $order->get_formatted_billing_full_name() ); ?>
					</div>

					<div class="collection-section">
						<div class="label">📅 COLLECTION DATE</div>
						<div class="date-time"><?php echo esc_html( $collection_display ); ?></div>
						<?php if ( $collection_time ) : ?>
							<div style="font-size: 16px; margin-top: 5px;">
								🕐 <?php echo esc_html( $collection_time ); ?>
							</div>
						<?php endif; ?>
					</div>

					<div class="items">
						<?php foreach ( $order->get_items() as $item ) : ?>
							<div class="item">
								<div class="item-name"><?php echo esc_html( $item->get_name() ); ?></div>
								<div class="item-details">
									Qty: <?php echo esc_html( $item->get_quantity() ); ?>
									<?php
									$item_meta = $item->get_meta_data();
									if ( ! empty( $item_meta ) ) :
										$meta_list = array();
										foreach ( $item_meta as $meta ) {
											if ( stripos( $meta->key, 'size' ) !== false ||
											 stripos( $meta->key, 'flavor' ) !== false ||
											 stripos( $meta->key, 'filling' ) !== false ||
											 stripos( $meta->key, 'decoration' ) !== false ) {
												if ( is_array( $meta->value ) ) {
													$meta->value = implode( ', ', $meta->value );
												}
												$meta_list[] = $meta->value;
											}
										}
										if ( ! empty( $meta_list ) ) :
											echo ' • ' . esc_html( implode( ' • ', $meta_list ) );
										endif;
									endif;
									?>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<?php if ( $handling_instructions ) : ?>
						<div style="text-align: center; margin: 10px 0;">
							<?php if ( $handling_instructions === 'refrigerate' ) : ?>
								<span class="handling-badge handling-refrigerate">❄️ KEEP REFRIGERATED</span>
							<?php elseif ( $handling_instructions === 'frozen' ) : ?>
								<span class="handling-badge handling-frozen">🧊 KEEP FROZEN</span>
							<?php elseif ( $handling_instructions === 'fragile' ) : ?>
								<span class="handling-badge handling-fragile">⚠️ FRAGILE</span>
							<?php elseif ( $handling_instructions === 'tiered' ) : ?>
								<span class="handling-badge handling-tiered">🎂 TIERED CAKE</span>
							<?php endif; ?>
						</div>
					<?php endif; ?>

					<?php if ( $allergen_info ) : ?>
						<div class="allergen-section">
							<strong>⚠️ ALLERGENS</strong>
							<?php echo esc_html( $allergen_info ); ?>
						</div>
					<?php endif; ?>

					<?php if ( $order->get_customer_note() ) : ?>
						<div style="font-size: 10px; margin: 10px 0; padding: 8px; background: #fff3cd; border-radius: 4px;">
							<strong>📝 Note:</strong> <?php echo esc_html( substr( $order->get_customer_note(), 0, 100 ) ); ?><?php echo strlen( $order->get_customer_note() ) > 100 ? '...' : ''; ?>
						</div>
					<?php endif; ?>

					<div class="qr-placeholder">
						<div style="font-size: 24px;">▓</div>
						<div>Order #<?php echo esc_html( $order->get_order_number() ); ?></div>
					</div>

					<div class="contact-info">
						📞 <?php echo esc_html( $shop_phone ); ?><br>
						<?php echo esc_html( $shop_name ); ?>
					</div>
				</div>
			</div>

			<script type="text/javascript">
				// Auto-print on load
				window.onload = function() {
					window.print();
				};
			</script>
		</body>
		</html>
		<?php
	}

	/**
	 * Handle print delivery note action.
	 *
	 * @since 1.5.0
	 */
	public function print_delivery_note() {
		$order_id = isset( $_GET['order_id'] ) ? absint( $_GET['order_id'] ) : 0;

		if ( ! $order_id ) {
			wp_die( 'Invalid order ID' );
		}

		$order = wc_get_order( $order_id );
		if ( ! $order ) {
			wp_die( 'Order not found' );
		}

		$this->render_delivery_note( $order );
		exit;
	}

	/**
	 * Add bulk print action to orders list.
	 *
	 * @since 1.5.0
	 * @param array $actions Bulk actions.
	 * @return array Modified actions.
	 */
	public function add_bulk_print_action( $actions ) {
		$actions['wc_bulk_print_delivery_notes'] = __( '📄 Print Delivery Notes', 'wc-collection-date' );
		return $actions;
	}

	/**
	 * Handle bulk print delivery notes action.
	 *
	 * @since 1.5.0
	 */
	public function bulk_print_delivery_notes() {
		if ( empty( $_GET['post'] ) || empty( $_GET['action'] ) ) {
			return;
		}

		check_admin_referer( 'bulk-posts' );

		$order_ids = array_map( 'absint', $_GET['post'] );

		foreach ( $order_ids as $order_id ) {
			$order = wc_get_order( $order_id );
			if ( $order ) {
				$this->render_delivery_note( $order );
				echo '<div style="page-break-after: always;"></div>';
			}
		}

		exit;
	}
}