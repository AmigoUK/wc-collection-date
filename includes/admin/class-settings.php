<?php
/**
 * Settings page functionality
 *
 * @package WC_Collection_Date
 */

// If this file is called directly, abort.
if ( ! defined( 'ABSPATH' ) ) {
	die;
}

/**
 * Settings class
 */
class WC_Collection_Date_Settings {

	/**
	 * Initialize the class
	 */
	public function __construct() {
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Register plugin settings
	 */
	public function register_settings() {
		// Register settings group.
		register_setting(
			'wc_collection_date_settings',
			'wc_collection_date_working_days',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_working_days' ),
				'default'           => array( '1', '2', '3', '4', '5', '6' ),
			)
		);

		register_setting(
			'wc_collection_date_settings',
			'wc_collection_date_lead_time',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 2,
			)
		);

		register_setting(
			'wc_collection_date_settings',
			'wc_collection_date_max_booking_days',
			array(
				'type'              => 'integer',
				'sanitize_callback' => 'absint',
				'default'           => 90,
			)
		);

		register_setting(
			'wc_collection_date_settings',
			'wc_collection_date_lead_time_type',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_lead_time_type' ),
				'default'           => 'calendar',
			)
		);

		register_setting(
			'wc_collection_date_settings',
			'wc_collection_date_cutoff_time',
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_cutoff_time' ),
				'default'           => '',
			)
		);

		register_setting(
			'wc_collection_date_settings',
			'wc_collection_date_collection_days',
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_collection_days' ),
				'default'           => array( '0', '1', '2', '3', '4', '5', '6' ),
			)
		);

		// Add settings section.
		add_settings_section(
			'wc_collection_date_general',
			__( 'General Settings', 'wc-collection-date' ),
			array( $this, 'render_general_section' ),
			'wc-collection-date'
		);

		// Add settings fields.
		add_settings_field(
			'wc_collection_date_working_days',
			__( 'Working Days (Production)', 'wc-collection-date' ),
			array( $this, 'render_working_days_field' ),
			'wc-collection-date',
			'wc_collection_date_general'
		);

		add_settings_field(
			'wc_collection_date_collection_days',
			__( 'Collection Days (Customer Availability)', 'wc-collection-date' ),
			array( $this, 'render_collection_days_field' ),
			'wc-collection-date',
			'wc_collection_date_general'
		);

		add_settings_field(
			'wc_collection_date_lead_time',
			__( 'Lead Time (days)', 'wc-collection-date' ),
			array( $this, 'render_lead_time_field' ),
			'wc-collection-date',
			'wc_collection_date_general'
		);

		add_settings_field(
			'wc_collection_date_max_booking_days',
			__( 'Maximum Booking Window (days)', 'wc-collection-date' ),
			array( $this, 'render_max_booking_field' ),
			'wc-collection-date',
			'wc_collection_date_general'
		);

		add_settings_field(
			'wc_collection_date_lead_time_type',
			__( 'Lead Time Calculation', 'wc-collection-date' ),
			array( $this, 'render_lead_time_type_field' ),
			'wc-collection-date',
			'wc_collection_date_general'
		);

		add_settings_field(
			'wc_collection_date_cutoff_time',
			__( 'Order Cutoff Time', 'wc-collection-date' ),
			array( $this, 'render_cutoff_time_field' ),
			'wc-collection-date',
			'wc_collection_date_general'
		);

		// Exclusions section.
		add_settings_section(
			'wc_collection_date_exclusions',
			__( 'Date Exclusions', 'wc-collection-date' ),
			array( $this, 'render_exclusions_section' ),
			'wc-collection-date'
		);
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		// Check user capabilities.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'wc-collection-date' ) );
		}

		// Get current tab.
		$current_tab = isset( $_GET['tab'] ) ? sanitize_text_field( $_GET['tab'] ) : 'settings';

		// Define tabs.
		$tabs = array(
			'settings'       => __( 'Settings', 'wc-collection-date' ),
			'category_rules' => __( 'Category Rules', 'wc-collection-date' ),
			'schedule'       => __( 'Collection Schedule', 'wc-collection-date' ),
			'orders'         => __( 'Orders', 'wc-collection-date' ),
			'exclusions'     => __( 'Date Exclusions', 'wc-collection-date' ),
			'analytics'      => __( 'Analytics', 'wc-collection-date' ),
			'instructions'   => __( 'Instructions', 'wc-collection-date' ),
			'import_export'  => __( 'Import/Export', 'wc-collection-date' ),
		);
		?>
		<div class="wrap wc-collection-date-admin">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>

			<?php settings_errors(); ?>

			<!-- Tabs -->
			<nav class="nav-tab-wrapper woo-nav-tab-wrapper">
				<?php foreach ( $tabs as $tab_id => $tab_name ) : ?>
					<a
						href="<?php echo esc_url( add_query_arg( array( 'page' => 'wc-collection-date', 'tab' => $tab_id ), admin_url( 'admin.php' ) ) ); ?>"
						class="nav-tab <?php echo $current_tab === $tab_id ? 'nav-tab-active' : ''; ?>"
					>
						<?php echo esc_html( $tab_name ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<!-- Tab Content -->
			<div class="wc-collection-date-tab-content">
				<?php
				switch ( $current_tab ) {
					case 'category_rules':
						$this->render_category_rules_tab();
						break;
					case 'schedule':
						$this->render_schedule_tab();
						break;
					case 'orders':
						$this->render_orders_tab();
						break;
					case 'exclusions':
						$this->render_exclusions_tab();
						break;
					case 'analytics':
						$this->render_analytics_tab();
						break;
					case 'instructions':
						$this->render_instructions_tab();
						break;
					case 'import_export':
						$this->render_import_export_tab();
						break;
					case 'settings':
					default:
						$this->render_settings_tab();
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/**
	 * Render settings tab
	 */
	private function render_settings_tab() {
		?>
		<form method="post" action="options.php">
			<?php
			settings_fields( 'wc_collection_date_settings' );
			do_settings_sections( 'wc-collection-date' );
			submit_button();
			?>
		</form>
		<?php
	}

	/**
	 * Render category rules tab
	 */
	private function render_category_rules_tab() {
		// Handle form submissions.
		$this->handle_category_rule_actions();

		// Get resolver instance.
		$resolver = new WC_Collection_Date_Lead_Time_Resolver();
		$all_rules = $resolver->get_all_category_rules();

		// Get all product categories.
		$categories = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'orderby'    => 'name',
			)
		);

		// Check if we're editing a rule.
		$editing_category = isset( $_GET['edit_category'] ) ? absint( $_GET['edit_category'] ) : 0;
		$editing_rule = $editing_category ? $resolver->get_category_rule( $editing_category ) : null;

		?>
		<div class="wc-collection-date-category-rules">
			<p class="description">
				<?php esc_html_e( 'Configure different lead time rules for specific product categories. Products will use the rule from their category, with the longest lead time applied if a product belongs to multiple categories with rules.', 'wc-collection-date' ); ?>
			</p>

			<!-- Add/Edit Form -->
			<?php if ( $editing_category || isset( $_GET['add_rule'] ) ) : ?>
				<div class="wc-collection-date-rule-form" style="background: #f9f9f9; padding: 20px; margin: 20px 0; border: 1px solid #ddd;">
					<h2>
						<?php echo $editing_category ? esc_html__( 'Edit Category Rule', 'wc-collection-date' ) : esc_html__( 'Add Category Rule', 'wc-collection-date' ); ?>
					</h2>

					<form method="post" action="">
						<?php wp_nonce_field( 'wc_collection_date_category_rule', 'wc_collection_date_nonce' ); ?>
						<input type="hidden" name="action" value="<?php echo $editing_category ? 'update_rule' : 'add_rule'; ?>">
						<?php if ( $editing_category ) : ?>
							<input type="hidden" name="category_id" value="<?php echo esc_attr( $editing_category ); ?>">
						<?php endif; ?>

						<table class="form-table">
							<tr>
								<th scope="row">
									<label for="category_id"><?php esc_html_e( 'Category', 'wc-collection-date' ); ?></label>
								</th>
								<td>
									<?php if ( $editing_category ) : ?>
										<?php
										$category = get_term( $editing_category, 'product_cat' );
										echo '<strong>' . esc_html( $category ? $category->name : __( 'Unknown', 'wc-collection-date' ) ) . '</strong>';
										?>
									<?php else : ?>
										<select name="category_id" id="category_id" required>
											<option value=""><?php esc_html_e( 'Select a category...', 'wc-collection-date' ); ?></option>
											<?php foreach ( $categories as $category ) : ?>
												<?php if ( ! isset( $all_rules[ $category->term_id ] ) ) : ?>
													<option value="<?php echo esc_attr( $category->term_id ); ?>">
														<?php echo esc_html( $category->name ); ?>
														<?php if ( $category->count ) : ?>
															(<?php echo absint( $category->count ); ?> <?php esc_html_e( 'products', 'wc-collection-date' ); ?>)
														<?php endif; ?>
													</option>
												<?php endif; ?>
											<?php endforeach; ?>
										</select>
									<?php endif; ?>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="lead_time"><?php esc_html_e( 'Lead Time (days)', 'wc-collection-date' ); ?></label>
								</th>
								<td>
									<input
										type="number"
										name="lead_time"
										id="lead_time"
										value="<?php echo esc_attr( $editing_rule ? $editing_rule['lead_time'] : '2' ); ?>"
										min="0"
										max="365"
										required
									>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Lead Time Type', 'wc-collection-date' ); ?></th>
								<td>
									<fieldset>
										<label>
											<input
												type="radio"
												name="lead_time_type"
												value="calendar"
												<?php checked( $editing_rule ? $editing_rule['lead_time_type'] : 'calendar', 'calendar' ); ?>
											>
											<?php esc_html_e( 'Calendar Days', 'wc-collection-date' ); ?>
										</label>
										<br>
										<label>
											<input
												type="radio"
												name="lead_time_type"
												value="working"
												<?php checked( $editing_rule ? $editing_rule['lead_time_type'] : 'calendar', 'working' ); ?>
											>
											<?php esc_html_e( 'Working Days (skip weekends/non-production days)', 'wc-collection-date' ); ?>
										</label>
									</fieldset>
								</td>
							</tr>

							<tr>
								<th scope="row">
									<label for="cutoff_time"><?php esc_html_e( 'Cutoff Time (optional)', 'wc-collection-date' ); ?></label>
								</th>
								<td>
									<input
										type="time"
										name="cutoff_time"
										id="cutoff_time"
										value="<?php echo esc_attr( $editing_rule && isset( $editing_rule['cutoff_time'] ) ? $editing_rule['cutoff_time'] : '' ); ?>"
									>
									<p class="description"><?php esc_html_e( 'Orders placed after this time will require an extra day. Leave empty for no cutoff.', 'wc-collection-date' ); ?></p>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Working Days (Production)', 'wc-collection-date' ); ?></th>
								<td>
									<?php
									$days = array(
										'1' => __( 'Monday', 'wc-collection-date' ),
										'2' => __( 'Tuesday', 'wc-collection-date' ),
										'3' => __( 'Wednesday', 'wc-collection-date' ),
										'4' => __( 'Thursday', 'wc-collection-date' ),
										'5' => __( 'Friday', 'wc-collection-date' ),
										'6' => __( 'Saturday', 'wc-collection-date' ),
										'0' => __( 'Sunday', 'wc-collection-date' ),
									);
									$working_days = $editing_rule && isset( $editing_rule['working_days'] ) ? $editing_rule['working_days'] : array( '1', '2', '3', '4', '5' );
									?>
									<fieldset>
										<?php foreach ( $days as $day_num => $day_name ) : ?>
											<label style="display:inline-block; margin-right:15px;">
												<input
													type="checkbox"
													name="working_days[]"
													value="<?php echo esc_attr( $day_num ); ?>"
													<?php checked( in_array( (string) $day_num, (array) $working_days, true ) ); ?>
												>
												<?php echo esc_html( $day_name ); ?>
											</label>
										<?php endforeach; ?>
										<p class="description"><?php esc_html_e( 'Days when production happens. Used for calculating lead time.', 'wc-collection-date' ); ?></p>
									</fieldset>
								</td>
							</tr>

							<tr>
								<th scope="row"><?php esc_html_e( 'Collection Days', 'wc-collection-date' ); ?></th>
								<td>
									<?php
									$collection_days = $editing_rule && isset( $editing_rule['collection_days'] ) ? $editing_rule['collection_days'] : array( '0', '1', '2', '3', '4', '5', '6' );
									?>
									<fieldset>
										<?php foreach ( $days as $day_num => $day_name ) : ?>
											<label style="display:inline-block; margin-right:15px;">
												<input
													type="checkbox"
													name="collection_days[]"
													value="<?php echo esc_attr( $day_num ); ?>"
													<?php checked( in_array( (string) $day_num, (array) $collection_days, true ) ); ?>
												>
												<?php echo esc_html( $day_name ); ?>
											</label>
										<?php endforeach; ?>
										<p class="description"><?php esc_html_e( 'Days when customers can collect orders. These dates will appear in the date picker.', 'wc-collection-date' ); ?></p>
									</fieldset>
								</td>
							</tr>
						</table>

						<p class="submit">
							<button type="submit" class="button button-primary">
								<?php echo $editing_category ? esc_html__( 'Update Rule', 'wc-collection-date' ) : esc_html__( 'Add Rule', 'wc-collection-date' ); ?>
							</button>
							<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wc-collection-date', 'tab' => 'category_rules' ), admin_url( 'admin.php' ) ) ); ?>" class="button">
								<?php esc_html_e( 'Cancel', 'wc-collection-date' ); ?>
							</a>
						</p>
					</form>
				</div>
			<?php else : ?>
				<!-- List View -->
				<p>
					<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wc-collection-date', 'tab' => 'category_rules', 'add_rule' => '1' ), admin_url( 'admin.php' ) ) ); ?>" class="button button-primary">
						<?php esc_html_e( '+ Add Category Rule', 'wc-collection-date' ); ?>
					</a>
				</p>

				<?php if ( empty( $all_rules ) ) : ?>
					<div class="notice notice-info inline">
						<p><?php esc_html_e( 'No category rules configured yet. All products will use global settings.', 'wc-collection-date' ); ?></p>
					</div>
				<?php else : ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Category', 'wc-collection-date' ); ?></th>
								<th><?php esc_html_e( 'Lead Time', 'wc-collection-date' ); ?></th>
								<th><?php esc_html_e( 'Type', 'wc-collection-date' ); ?></th>
								<th><?php esc_html_e( 'Cutoff', 'wc-collection-date' ); ?></th>
								<th><?php esc_html_e( 'Products', 'wc-collection-date' ); ?></th>
								<th><?php esc_html_e( 'Actions', 'wc-collection-date' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $all_rules as $category_id => $rule ) : ?>
								<?php $category = get_term( $category_id, 'product_cat' ); ?>
								<?php if ( $category && ! is_wp_error( $category ) ) : ?>
									<tr>
										<td><strong><?php echo esc_html( $category->name ); ?></strong></td>
										<td><?php echo absint( $rule['lead_time'] ); ?> <?php esc_html_e( 'days', 'wc-collection-date' ); ?></td>
										<td><?php echo esc_html( ucfirst( $rule['lead_time_type'] ) ); ?></td>
										<td><?php echo ! empty( $rule['cutoff_time'] ) ? esc_html( $rule['cutoff_time'] ) : '—'; ?></td>
										<td><?php echo absint( $category->count ); ?></td>
										<td>
											<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wc-collection-date', 'tab' => 'category_rules', 'edit_category' => $category_id ), admin_url( 'admin.php' ) ) ); ?>">
												<?php esc_html_e( 'Edit', 'wc-collection-date' ); ?>
											</a>
											|
											<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'wc-collection-date', 'tab' => 'category_rules', 'action' => 'delete_rule', 'category_id' => $category_id ), admin_url( 'admin.php' ) ), 'delete_category_rule_' . $category_id ) ); ?>" class="wc-collection-date-delete-rule" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this rule?', 'wc-collection-date' ); ?>');">
												<?php esc_html_e( 'Delete', 'wc-collection-date' ); ?>
											</a>
										</td>
									</tr>
								<?php endif; ?>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle category rule form actions (add/update/delete)
	 */
	private function handle_category_rule_actions() {
		// Handle delete action.
		if ( isset( $_GET['action'] ) && 'delete_rule' === $_GET['action'] && isset( $_GET['category_id'] ) ) {
			$category_id = absint( $_GET['category_id'] );

			// Verify nonce.
			$nonce = isset( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
			if ( ! wp_verify_nonce( $nonce, 'delete_category_rule_' . $category_id ) ) {
				wp_die( esc_html__( 'Security check failed.', 'wc-collection-date' ) );
			}

			$resolver = new WC_Collection_Date_Lead_Time_Resolver();
			if ( $resolver->delete_category_rule( $category_id ) ) {
				add_settings_error(
					'wc_collection_date_messages',
					'rule_deleted',
					__( 'Category rule deleted successfully.', 'wc-collection-date' ),
					'success'
				);
			}

			// Redirect to clean URL.
			wp_safe_redirect( add_query_arg( array( 'page' => 'wc-collection-date', 'tab' => 'category_rules' ), admin_url( 'admin.php' ) ) );
			exit;
		}

		// Handle add/update action.
		$action = isset( $_POST['action'] ) ? sanitize_text_field( wp_unslash( $_POST['action'] ) ) : '';
		if ( in_array( $action, array( 'add_rule', 'update_rule' ), true ) ) {
			// Verify nonce.
			if ( ! isset( $_POST['wc_collection_date_nonce'] ) || ! wp_verify_nonce( $_POST['wc_collection_date_nonce'], 'wc_collection_date_category_rule' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'wc-collection-date' ) );
			}

			$category_id = isset( $_POST['category_id'] ) ? absint( $_POST['category_id'] ) : 0;

			if ( ! $category_id ) {
				add_settings_error(
					'wc_collection_date_messages',
					'invalid_category',
					__( 'Invalid category selected.', 'wc-collection-date' ),
					'error'
				);
				return;
			}

			// Prepare settings array.
			$settings = array(
				'lead_time'        => isset( $_POST['lead_time'] ) ? absint( $_POST['lead_time'] ) : 2,
				'lead_time_type'   => isset( $_POST['lead_time_type'] ) && in_array( $_POST['lead_time_type'], array( 'calendar', 'working' ), true ) ? $_POST['lead_time_type'] : 'calendar',
				'cutoff_time'      => isset( $_POST['cutoff_time'] ) ? sanitize_text_field( $_POST['cutoff_time'] ) : '',
				'working_days'     => isset( $_POST['working_days'] ) && is_array( $_POST['working_days'] ) ? array_map( 'sanitize_text_field', $_POST['working_days'] ) : array(),
				'collection_days'  => isset( $_POST['collection_days'] ) && is_array( $_POST['collection_days'] ) ? array_map( 'sanitize_text_field', $_POST['collection_days'] ) : array(),
			);

			$resolver = new WC_Collection_Date_Lead_Time_Resolver();
			if ( $resolver->save_category_rule( $category_id, $settings ) ) {
				// Clear cache when category rules change.
				WC_Collection_Date_Calculator::clear_cache();

				$message = 'add_rule' === $action
					? __( 'Category rule added successfully.', 'wc-collection-date' )
					: __( 'Category rule updated successfully.', 'wc-collection-date' );

				add_settings_error(
					'wc_collection_date_messages',
					'rule_saved',
					$message,
					'success'
				);

				// Redirect to clean URL.
				wp_safe_redirect( add_query_arg( array( 'page' => 'wc-collection-date', 'tab' => 'category_rules' ), admin_url( 'admin.php' ) ) );
				exit;
			} else {
				add_settings_error(
					'wc_collection_date_messages',
					'rule_save_failed',
					__( 'Failed to save category rule.', 'wc-collection-date' ),
					'error'
				);
			}
		}
	}

	/**
	 * Render general section description
	 */
	public function render_general_section() {
		echo '<p>' . esc_html__( 'Configure global collection date rules for all products and locations.', 'wc-collection-date' ) . '</p>';
	}

	/**
	 * Render working days field
	 */
	public function render_working_days_field() {
		$working_days = get_option( 'wc_collection_date_working_days', array( '1', '2', '3', '4', '5', '6' ) );
		$days         = array(
			'1' => __( 'Monday', 'wc-collection-date' ),
			'2' => __( 'Tuesday', 'wc-collection-date' ),
			'3' => __( 'Wednesday', 'wc-collection-date' ),
			'4' => __( 'Thursday', 'wc-collection-date' ),
			'5' => __( 'Friday', 'wc-collection-date' ),
			'6' => __( 'Saturday', 'wc-collection-date' ),
			'0' => __( 'Sunday', 'wc-collection-date' ),
		);
		?>
		<fieldset class="wc-collection-date-working-days">
			<?php foreach ( $days as $day_num => $day_name ) : ?>
				<label style="display:inline-block; margin-right:15px;">
					<input
						type="checkbox"
						name="wc_collection_date_working_days[]"
						value="<?php echo esc_attr( $day_num ); ?>"
						<?php checked( in_array( (string) $day_num, (array) $working_days, true ) ); ?>
					>
					<?php echo esc_html( $day_name ); ?>
				</label>
			<?php endforeach; ?>
			<p class="description">
				<?php esc_html_e( 'Days when production/preparation happens. Used to calculate lead time in "Working Days" mode.', 'wc-collection-date' ); ?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render collection days field
	 */
	public function render_collection_days_field() {
		$collection_days = get_option( 'wc_collection_date_collection_days', array( '0', '1', '2', '3', '4', '5', '6' ) );
		$days            = array(
			'1' => __( 'Monday', 'wc-collection-date' ),
			'2' => __( 'Tuesday', 'wc-collection-date' ),
			'3' => __( 'Wednesday', 'wc-collection-date' ),
			'4' => __( 'Thursday', 'wc-collection-date' ),
			'5' => __( 'Friday', 'wc-collection-date' ),
			'6' => __( 'Saturday', 'wc-collection-date' ),
			'0' => __( 'Sunday', 'wc-collection-date' ),
		);
		?>
		<fieldset class="wc-collection-date-collection-days">
			<?php foreach ( $days as $day_num => $day_name ) : ?>
				<label style="display:inline-block; margin-right:15px;">
					<input
						type="checkbox"
						name="wc_collection_date_collection_days[]"
						value="<?php echo esc_attr( $day_num ); ?>"
						<?php checked( in_array( (string) $day_num, (array) $collection_days, true ) ); ?>
					>
					<?php echo esc_html( $day_name ); ?>
				</label>
			<?php endforeach; ?>
			<p class="description">
				<?php esc_html_e( 'Days when customers can collect/pickup orders. These dates will be shown in the date picker.', 'wc-collection-date' ); ?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render lead time field
	 */
	public function render_lead_time_field() {
		$lead_time = get_option( 'wc_collection_date_lead_time', 2 );
		?>
		<input
			type="number"
			name="wc_collection_date_lead_time"
			value="<?php echo esc_attr( $lead_time ); ?>"
			min="0"
			max="365"
			class="small-text"
		>
		<p class="description">
			<?php esc_html_e( 'Minimum number of calendar days in advance that customers must order before collection.', 'wc-collection-date' ); ?>
		</p>
		<?php
	}

	/**
	 * Render max booking field
	 */
	public function render_max_booking_field() {
		$max_days = get_option( 'wc_collection_date_max_booking_days', 90 );
		?>
		<input
			type="number"
			name="wc_collection_date_max_booking_days"
			value="<?php echo esc_attr( $max_days ); ?>"
			min="1"
			max="365"
			class="small-text"
		>
		<p class="description">
			<?php esc_html_e( 'Maximum number of days in the future customers can book collection dates.', 'wc-collection-date' ); ?>
		</p>
		<?php
	}

	/**
	 * Render lead time type field
	 */
	public function render_lead_time_type_field() {
		$lead_time_type = get_option( 'wc_collection_date_lead_time_type', 'calendar' );
		?>
		<fieldset>
			<label>
				<input
					type="radio"
					name="wc_collection_date_lead_time_type"
					value="calendar"
					<?php checked( $lead_time_type, 'calendar' ); ?>
				>
				<?php esc_html_e( 'Calendar Days', 'wc-collection-date' ); ?>
			</label>
			<br>
			<label>
				<input
					type="radio"
					name="wc_collection_date_lead_time_type"
					value="working"
					<?php checked( $lead_time_type, 'working' ); ?>
				>
				<?php esc_html_e( 'Working Days (skip weekends and excluded dates)', 'wc-collection-date' ); ?>
			</label>
			<p class="description">
				<?php esc_html_e( 'Choose how lead time is calculated. Working days only counts enabled working days, while calendar days counts all days.', 'wc-collection-date' ); ?>
			</p>
		</fieldset>
		<?php
	}

	/**
	 * Render cutoff time field
	 */
	public function render_cutoff_time_field() {
		$cutoff_time = get_option( 'wc_collection_date_cutoff_time', '' );
		?>
		<input
			type="time"
			name="wc_collection_date_cutoff_time"
			value="<?php echo esc_attr( $cutoff_time ); ?>"
			class="regular-text"
		>
		<p class="description">
			<?php esc_html_e( 'Orders placed after this time will require an additional day of lead time. Leave empty to disable. Format: HH:MM (24-hour)', 'wc-collection-date' ); ?>
		</p>
		<?php
	}

	/**
	 * Render exclusions section
	 */
	public function render_exclusions_section() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		// Get exclusions from database.
		$exclusions = $wpdb->get_results(
			"SELECT * FROM {$table_name} ORDER BY exclusion_date ASC"
		);
		?>
		<p><?php esc_html_e( 'Manage dates that should be excluded from collection (holidays, closures, etc.).', 'wc-collection-date' ); ?></p>

		<table class="wp-list-table widefat fixed striped">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Date', 'wc-collection-date' ); ?></th>
					<th><?php esc_html_e( 'Reason', 'wc-collection-date' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $exclusions ) ) : ?>
					<tr>
						<td colspan="2"><?php esc_html_e( 'No exclusions configured yet.', 'wc-collection-date' ); ?></td>
					</tr>
				<?php else : ?>
					<?php foreach ( $exclusions as $exclusion ) : ?>
						<tr>
							<td><?php echo esc_html( date_i18n( get_option( 'date_format' ), strtotime( $exclusion->exclusion_date ) ) ); ?></td>
							<td><?php echo esc_html( $exclusion->reason ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Render Collection Schedule tab
	 */
	private function render_schedule_tab() {
		// Get orders with collection dates using WooCommerce API (HPOS compatible).
		$order_ids = wc_get_orders(
			array(
				'limit'      => -1,
				'status'     => array( 'processing', 'on-hold', 'pending' ),
				'meta_key'   => '_collection_date',
				'meta_query' => array(
					array(
						'key'     => '_collection_date',
						'value'   => gmdate( 'Y-m-d' ),
						'compare' => '>=',
					),
				),
				'return'     => 'ids',
			)
		);

		// Group orders by collection date.
		$date_counts = array();
		foreach ( $order_ids as $order_id ) {
			$order            = wc_get_order( $order_id );
			$collection_date  = $order->get_meta( '_collection_date' );

			if ( ! empty( $collection_date ) ) {
				if ( ! isset( $date_counts[ $collection_date ] ) ) {
					$date_counts[ $collection_date ] = 0;
				}
				$date_counts[ $collection_date ]++;
			}
		}

		// Sort by date and limit to 30.
		ksort( $date_counts );
		$date_counts = array_slice( $date_counts, 0, 30, true );

		// Convert to object format for template compatibility.
		$results = array();
		foreach ( $date_counts as $date => $count ) {
			$results[] = (object) array(
				'collection_date' => $date,
				'order_count'     => $count,
			);
		}

		?>
		<div class="wc-collection-schedule-wrapper">
			<h2><?php esc_html_e( 'Upcoming Collections', 'wc-collection-date' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Overview of upcoming collection dates and order counts.', 'wc-collection-date' ); ?>
			</p>

			<?php if ( empty( $results ) ) : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'No upcoming collections scheduled.', 'wc-collection-date' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th class="column-date"><?php esc_html_e( 'Collection Date', 'wc-collection-date' ); ?></th>
							<th class="column-day"><?php esc_html_e( 'Day', 'wc-collection-date' ); ?></th>
							<th class="column-orders"><?php esc_html_e( 'Orders', 'wc-collection-date' ); ?></th>
							<th class="column-actions"><?php esc_html_e( 'Actions', 'wc-collection-date' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $results as $row ) : ?>
							<?php
							$date      = strtotime( $row->collection_date );
							$is_today  = gmdate( 'Y-m-d', $date ) === gmdate( 'Y-m-d' );
							$row_class = $is_today ? 'wc-collection-today' : '';
							?>
							<tr class="<?php echo esc_attr( $row_class ); ?>">
								<td class="column-date">
									<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), $date ) ); ?></strong>
									<?php if ( $is_today ) : ?>
										<span class="wc-collection-badge"><?php esc_html_e( 'Today', 'wc-collection-date' ); ?></span>
									<?php endif; ?>
								</td>
								<td class="column-day">
									<?php echo esc_html( date_i18n( 'l', $date ) ); ?>
								</td>
								<td class="column-orders">
									<strong><?php echo absint( $row->order_count ); ?></strong>
									<?php echo absint( $row->order_count ) === 1 ? esc_html__( 'order', 'wc-collection-date' ) : esc_html__( 'orders', 'wc-collection-date' ); ?>
								</td>
								<td class="column-actions">
									<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wc-collection-date', 'tab' => 'orders', 'collection_date' => $row->collection_date ), admin_url( 'admin.php' ) ) ); ?>" class="button button-small">
										<?php esc_html_e( 'View Orders', 'wc-collection-date' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Orders tab
	 */
	private function render_orders_tab() {
		// Get filter parameter.
		$filter_date = isset( $_GET['collection_date'] ) ? sanitize_text_field( wp_unslash( $_GET['collection_date'] ) ) : '';

		// Build query args for WooCommerce API (HPOS compatible).
		$query_args = array(
			'limit'    => ! empty( $filter_date ) ? -1 : 50,
			'meta_key' => '_collection_date',
			'orderby'  => 'date',
			'order'    => 'DESC',
		);

		// Add meta query filter if date is specified.
		if ( ! empty( $filter_date ) ) {
			$query_args['meta_query'] = array(
				array(
					'key'   => '_collection_date',
					'value' => $filter_date,
				),
			);
		} else {
			// Only show orders that have a collection date.
			$query_args['meta_query'] = array(
				array(
					'key'     => '_collection_date',
					'compare' => 'EXISTS',
				),
			);
		}

		// Get orders using WooCommerce API.
		$wc_orders = wc_get_orders( $query_args );

		// Convert to array of objects for template compatibility.
		$orders = array();
		foreach ( $wc_orders as $wc_order ) {
			$orders[] = (object) array(
				'ID'              => $wc_order->get_id(),
				'post_date'       => $wc_order->get_date_created()->date( 'Y-m-d H:i:s' ),
				'collection_date' => $wc_order->get_meta( '_collection_date' ),
			);
		}

		// Sort by collection date if not filtered.
		if ( empty( $filter_date ) && ! empty( $orders ) ) {
			usort(
				$orders,
				function ( $a, $b ) {
					return strcmp( $b->collection_date, $a->collection_date );
				}
			);
		}

		?>
		<div class="wc-collection-orders-wrapper">
			<h2><?php esc_html_e( 'Orders with Collection Dates', 'wc-collection-date' ); ?></h2>

			<!-- Filter Form -->
			<div class="wc-collection-filter">
				<form method="get" action="">
					<input type="hidden" name="page" value="wc-collection-date">
					<input type="hidden" name="tab" value="orders">
					<label for="collection_date_filter"><?php esc_html_e( 'Filter by date:', 'wc-collection-date' ); ?></label>
					<input type="date" id="collection_date_filter" name="collection_date" value="<?php echo esc_attr( $filter_date ); ?>">
					<button type="submit" class="button"><?php esc_html_e( 'Filter', 'wc-collection-date' ); ?></button>
					<?php if ( ! empty( $filter_date ) ) : ?>
						<a href="<?php echo esc_url( add_query_arg( array( 'page' => 'wc-collection-date', 'tab' => 'orders' ), admin_url( 'admin.php' ) ) ); ?>" class="button">
							<?php esc_html_e( 'Clear Filter', 'wc-collection-date' ); ?>
						</a>
					<?php endif; ?>
				</form>
			</div>

			<?php if ( empty( $orders ) ) : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'No orders found.', 'wc-collection-date' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th class="column-order"><?php esc_html_e( 'Order', 'wc-collection-date' ); ?></th>
							<th class="column-status"><?php esc_html_e( 'Status', 'wc-collection-date' ); ?></th>
							<th class="column-date"><?php esc_html_e( 'Collection Date', 'wc-collection-date' ); ?></th>
							<th class="column-customer"><?php esc_html_e( 'Customer', 'wc-collection-date' ); ?></th>
							<th class="column-total"><?php esc_html_e( 'Total', 'wc-collection-date' ); ?></th>
							<th class="column-actions"><?php esc_html_e( 'Actions', 'wc-collection-date' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $orders as $order_data ) : ?>
							<?php
							$order = wc_get_order( $order_data->ID );
							if ( ! $order ) {
								continue;
							}
							$collection_date = strtotime( $order_data->collection_date );
							?>
							<tr>
								<td class="column-order">
									<strong>#<?php echo esc_html( $order->get_order_number() ); ?></strong>
									<br>
									<small><?php echo esc_html( date_i18n( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $order_data->post_date ) ) ); ?></small>
								</td>
								<td class="column-status">
									<span class="order-status status-<?php echo esc_attr( $order->get_status() ); ?>">
										<?php echo esc_html( wc_get_order_status_name( $order->get_status() ) ); ?>
									</span>
								</td>
								<td class="column-date">
									<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), $collection_date ) ); ?></strong>
									<br>
									<small><?php echo esc_html( date_i18n( 'l', $collection_date ) ); ?></small>
								</td>
								<td class="column-customer">
									<?php
									$billing_name = $order->get_billing_first_name() . ' ' . $order->get_billing_last_name();
									echo esc_html( $billing_name );
									?>
								</td>
								<td class="column-total">
									<?php echo wp_kses_post( $order->get_formatted_order_total() ); ?>
								</td>
								<td class="column-actions">
									<a href="<?php echo esc_url( $order->get_edit_order_url() ); ?>" class="button button-small">
										<?php esc_html_e( 'View', 'wc-collection-date' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render Exclusions tab
	 */
	private function render_exclusions_tab() {
		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		// Handle form submission.
		if ( isset( $_POST['wc_collection_add_exclusion'] ) && check_admin_referer( 'wc_collection_add_exclusion' ) ) {
			$exclusion_date = isset( $_POST['exclusion_date'] ) ? sanitize_text_field( wp_unslash( $_POST['exclusion_date'] ) ) : '';
			$reason         = isset( $_POST['reason'] ) ? sanitize_text_field( wp_unslash( $_POST['reason'] ) ) : '';

			if ( ! empty( $exclusion_date ) && ! empty( $reason ) ) {
				$wpdb->insert(
					$table_name,
					array(
						'exclusion_date' => $exclusion_date,
						'reason'         => $reason,
					),
					array( '%s', '%s' )
				);

				// Clear cache when exclusions change.
				WC_Collection_Date_Calculator::clear_cache();

				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Exclusion added successfully.', 'wc-collection-date' ) . '</p></div>';
			}
		}

		// Handle deletion.
		if ( isset( $_GET['delete_exclusion'] ) && check_admin_referer( 'delete_exclusion_' . absint( $_GET['delete_exclusion'] ) ) ) {
			$wpdb->delete(
				$table_name,
				array( 'id' => absint( $_GET['delete_exclusion'] ) ),
				array( '%d' )
			);

			// Clear cache when exclusions change.
			WC_Collection_Date_Calculator::clear_cache();

			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Exclusion deleted successfully.', 'wc-collection-date' ) . '</p></div>';
		}

		// Get exclusions from database.
		$exclusions = $wpdb->get_results(
			"SELECT * FROM {$table_name} ORDER BY exclusion_date ASC"
		);

		?>
		<div class="wc-collection-exclusions-wrapper">
			<h2><?php esc_html_e( 'Manage Date Exclusions', 'wc-collection-date' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Add dates that should be excluded from collection (holidays, closures, etc.). These dates will not be available in the collection date picker.', 'wc-collection-date' ); ?>
			</p>

			<!-- Add Exclusion Form -->
			<div class="wc-collection-add-exclusion">
				<h3><?php esc_html_e( 'Add New Exclusion', 'wc-collection-date' ); ?></h3>
				<form method="post" action="">
					<?php wp_nonce_field( 'wc_collection_add_exclusion' ); ?>
					<table class="form-table">
						<tr>
							<th scope="row">
								<label for="exclusion_date"><?php esc_html_e( 'Date', 'wc-collection-date' ); ?></label>
							</th>
							<td>
								<input type="date" id="exclusion_date" name="exclusion_date" required class="regular-text">
							</td>
						</tr>
						<tr>
							<th scope="row">
								<label for="reason"><?php esc_html_e( 'Reason', 'wc-collection-date' ); ?></label>
							</th>
							<td>
								<input type="text" id="reason" name="reason" required class="regular-text" placeholder="<?php esc_attr_e( 'e.g., Christmas Day, Store Closed', 'wc-collection-date' ); ?>">
								<p class="description"><?php esc_html_e( 'Brief description of why this date is excluded.', 'wc-collection-date' ); ?></p>
							</td>
						</tr>
					</table>
					<p class="submit">
						<button type="submit" name="wc_collection_add_exclusion" class="button button-primary">
							<?php esc_html_e( 'Add Exclusion', 'wc-collection-date' ); ?>
						</button>
					</p>
				</form>
			</div>

			<hr>

			<!-- Existing Exclusions -->
			<h3><?php esc_html_e( 'Existing Exclusions', 'wc-collection-date' ); ?></h3>
			<?php if ( empty( $exclusions ) ) : ?>
				<div class="notice notice-info inline">
					<p><?php esc_html_e( 'No exclusions configured yet.', 'wc-collection-date' ); ?></p>
				</div>
			<?php else : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th class="column-date"><?php esc_html_e( 'Date', 'wc-collection-date' ); ?></th>
							<th class="column-day"><?php esc_html_e( 'Day', 'wc-collection-date' ); ?></th>
							<th class="column-reason"><?php esc_html_e( 'Reason', 'wc-collection-date' ); ?></th>
							<th class="column-actions"><?php esc_html_e( 'Actions', 'wc-collection-date' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $exclusions as $exclusion ) : ?>
							<?php $date = strtotime( $exclusion->exclusion_date ); ?>
							<tr>
								<td class="column-date">
									<strong><?php echo esc_html( date_i18n( get_option( 'date_format' ), $date ) ); ?></strong>
								</td>
								<td class="column-day">
									<?php echo esc_html( date_i18n( 'l', $date ) ); ?>
								</td>
								<td class="column-reason">
									<?php echo esc_html( $exclusion->reason ); ?>
								</td>
								<td class="column-actions">
									<a href="<?php echo esc_url( wp_nonce_url( add_query_arg( array( 'page' => 'wc-collection-date', 'tab' => 'exclusions', 'delete_exclusion' => $exclusion->id ), admin_url( 'admin.php' ) ), 'delete_exclusion_' . $exclusion->id ) ); ?>" class="button button-small button-link-delete" onclick="return confirm('<?php esc_attr_e( 'Are you sure you want to delete this exclusion?', 'wc-collection-date' ); ?>');">
										<?php esc_html_e( 'Delete', 'wc-collection-date' ); ?>
									</a>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render instructions tab
	 */
	private function render_instructions_tab() {
		?>
		<div class="wc-collection-instructions-wrapper">
			<h2><?php esc_html_e( 'How Collection Dates Work', 'wc-collection-date' ); ?></h2>

			<!-- Introduction -->
			<div class="wc-instructions-section">
				<p class="description" style="font-size: 14px;">
					<?php esc_html_e( 'This plugin allows you to control when customers can collect their orders. It uses a flexible rule system that can handle simple to complex business requirements.', 'wc-collection-date' ); ?>
				</p>
			</div>

			<hr>

			<!-- Priority System -->
			<div class="wc-instructions-section">
				<h3><?php esc_html_e( '📋 Priority System - Which Rules Apply?', 'wc-collection-date' ); ?></h3>
				<p><?php esc_html_e( 'The system checks rules in this order:', 'wc-collection-date' ); ?></p>
				<ol style="line-height: 1.8; font-size: 14px;">
					<li><strong><?php esc_html_e( 'Product Override', 'wc-collection-date' ); ?></strong> - <?php esc_html_e( 'Individual product settings (Phase 4 - not yet available)', 'wc-collection-date' ); ?></li>
					<li><strong><?php esc_html_e( 'Category Rules', 'wc-collection-date' ); ?></strong> - <?php esc_html_e( 'Settings based on product categories', 'wc-collection-date' ); ?></li>
					<li><strong><?php esc_html_e( 'Global Settings', 'wc-collection-date' ); ?></strong> - <?php esc_html_e( 'Default settings for all products', 'wc-collection-date' ); ?></li>
				</ol>

				<div class="notice notice-info inline" style="margin: 15px 0;">
					<p><strong><?php esc_html_e( 'Important:', 'wc-collection-date' ); ?></strong> <?php esc_html_e( 'Category rules ALWAYS take priority over global settings. Global settings are only used as a fallback when no category rule exists.', 'wc-collection-date' ); ?></p>
				</div>
			</div>

			<hr>

			<!-- Two Types of Days -->
			<div class="wc-instructions-section">
				<h3><?php esc_html_e( '🗓️ Understanding Working Days vs Collection Days', 'wc-collection-date' ); ?></h3>
				<p><?php esc_html_e( 'There are TWO separate concepts:', 'wc-collection-date' ); ?></p>

				<h4 style="margin-top: 20px;"><?php esc_html_e( '1. Working Days (Production)', 'wc-collection-date' ); ?></h4>
				<ul style="line-height: 1.8; font-size: 14px;">
					<li><?php esc_html_e( 'Days when your business PRODUCES/PREPARES orders', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Used to calculate lead time when "Working Days" mode is selected', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Example: If you only produce Mon-Fri, uncheck Saturday and Sunday', 'wc-collection-date' ); ?></li>
				</ul>

				<h4 style="margin-top: 20px;"><?php esc_html_e( '2. Collection Days (Customer Availability)', 'wc-collection-date' ); ?></h4>
				<ul style="line-height: 1.8; font-size: 14px;">
					<li><?php esc_html_e( 'Days when customers CAN PICKUP their orders', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'These dates appear in the date picker for customer selection', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Example: If customers can pickup any day including weekends, check all 7 days', 'wc-collection-date' ); ?></li>
				</ul>

				<div class="notice notice-success inline" style="margin: 15px 0;">
					<p><strong><?php esc_html_e( 'Real Example:', 'wc-collection-date' ); ?></strong><br>
					<?php esc_html_e( 'Working Days: Mon-Fri (production)', 'wc-collection-date' ); ?><br>
					<?php esc_html_e( 'Collection Days: All 7 days', 'wc-collection-date' ); ?><br>
					<?php esc_html_e( 'Result: System calculates lead time skipping weekends, but customers can select weekend pickup dates.', 'wc-collection-date' ); ?>
					</p>
				</div>
			</div>

			<hr>

			<!-- Lead Time Calculation -->
			<div class="wc-instructions-section">
				<h3><?php esc_html_e( '⏱️ Lead Time Calculation Methods', 'wc-collection-date' ); ?></h3>

				<h4><?php esc_html_e( 'Calendar Days (Simple)', 'wc-collection-date' ); ?></h4>
				<p style="margin-left: 20px; line-height: 1.8; font-size: 14px;">
					<?php esc_html_e( '• Counts every day including weekends', 'wc-collection-date' ); ?><br>
					<?php esc_html_e( '• Example: 3 days from Friday = Monday', 'wc-collection-date' ); ?>
				</p>

				<h4 style="margin-top: 20px;"><?php esc_html_e( 'Working Days (Advanced)', 'wc-collection-date' ); ?></h4>
				<p style="margin-left: 20px; line-height: 1.8; font-size: 14px;">
					<?php esc_html_e( '• Only counts your working days', 'wc-collection-date' ); ?><br>
					<?php esc_html_e( '• Skips non-working days and exclusions', 'wc-collection-date' ); ?><br>
					<?php esc_html_e( '• Example: 3 working days from Friday (with Mon-Fri working) = Wednesday', 'wc-collection-date' ); ?><br>
					<?php esc_html_e( '  (Skips: Sat, Sun. Counts: Mon=1, Tue=2, Wed=3)', 'wc-collection-date' ); ?>
				</p>
			</div>

			<hr>

			<!-- Cutoff Time -->
			<div class="wc-instructions-section">
				<h3><?php esc_html_e( '⏰ Cutoff Time (Order Deadline)', 'wc-collection-date' ); ?></h3>
				<p style="line-height: 1.8; font-size: 14px;">
					<?php esc_html_e( 'Set a daily deadline for orders. Orders placed AFTER the cutoff time get an extra day added to the lead time.', 'wc-collection-date' ); ?>
				</p>

				<div class="notice notice-warning inline" style="margin: 15px 0;">
					<p><strong><?php esc_html_e( 'Example:', 'wc-collection-date' ); ?></strong><br>
					<?php esc_html_e( 'Lead Time: 3 days', 'wc-collection-date' ); ?><br>
					<?php esc_html_e( 'Cutoff Time: 12:00 PM', 'wc-collection-date' ); ?><br><br>
					<?php esc_html_e( '• Order at 11:00 AM = 3 days lead time', 'wc-collection-date' ); ?><br>
					<?php esc_html_e( '• Order at 2:00 PM = 4 days lead time (+1 penalty)', 'wc-collection-date' ); ?>
					</p>
				</div>
			</div>

			<hr>

			<!-- Multiple Categories -->
			<div class="wc-instructions-section">
				<h3><?php esc_html_e( '🏷️ Products in Multiple Categories', 'wc-collection-date' ); ?></h3>
				<p style="line-height: 1.8; font-size: 14px;">
					<?php esc_html_e( 'When a product belongs to multiple categories with different rules, the system uses the LONGEST lead time (most conservative approach).', 'wc-collection-date' ); ?>
				</p>

				<div class="notice notice-info inline" style="margin: 15px 0;">
					<p><strong><?php esc_html_e( 'Example:', 'wc-collection-date' ); ?></strong><br>
					<?php esc_html_e( 'Product "Deluxe Cake" is in:', 'wc-collection-date' ); ?><br>
					<?php esc_html_e( '• Category "Standard Cakes" = 3 days', 'wc-collection-date' ); ?><br>
					<?php esc_html_e( '• Category "3D Cakes" = 4 days', 'wc-collection-date' ); ?><br><br>
					<?php esc_html_e( 'Result: Uses 4 days (longest) to ensure enough time for production.', 'wc-collection-date' ); ?>
					</p>
				</div>
			</div>

			<hr>

			<!-- Cart Behavior -->
			<div class="wc-instructions-section">
				<h3><?php esc_html_e( '🛒 Shopping Cart with Multiple Products', 'wc-collection-date' ); ?></h3>
				<p style="line-height: 1.8; font-size: 14px;">
					<?php esc_html_e( 'When the cart contains products with different lead times, the system automatically uses the LONGEST lead time to ensure all products can be prepared.', 'wc-collection-date' ); ?>
				</p>

				<div class="notice notice-success inline" style="margin: 15px 0;">
					<p><strong><?php esc_html_e( 'Real Bakery Example:', 'wc-collection-date' ); ?></strong><br>
					<?php esc_html_e( 'Customer adds to cart:', 'wc-collection-date' ); ?><br>
					<?php esc_html_e( '1. "Birthday Cake" (Standard Cakes) = 3 days', 'wc-collection-date' ); ?><br>
					<?php esc_html_e( '2. "Wedding Cake" (Wedding Cakes) = 7 days', 'wc-collection-date' ); ?><br><br>
					<?php esc_html_e( 'Date Picker Shows: Dates starting 7 days from now', 'wc-collection-date' ); ?><br>
					<?php esc_html_e( 'Reason: Ensures wedding cake has enough time, birthday cake will be ready too.', 'wc-collection-date' ); ?>
					</p>
				</div>
			</div>

			<hr>

			<!-- Date Exclusions -->
			<div class="wc-instructions-section">
				<h3><?php esc_html_e( '🚫 Date Exclusions', 'wc-collection-date' ); ?></h3>
				<p style="line-height: 1.8; font-size: 14px;">
					<?php esc_html_e( 'Block specific dates from being available for collection (holidays, closures, etc.).', 'wc-collection-date' ); ?>
				</p>
				<ul style="line-height: 1.8; font-size: 14px;">
					<li><?php esc_html_e( 'Excluded dates are skipped in both production AND collection calculations', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Use the "Date Exclusions" tab to add holidays like Christmas, New Year, etc.', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'These dates will never appear in the customer date picker', 'wc-collection-date' ); ?></li>
				</ul>
			</div>

			<hr>

			<!-- Step by Step Setup -->
			<div class="wc-instructions-section">
				<h3><?php esc_html_e( '🚀 Quick Setup Guide', 'wc-collection-date' ); ?></h3>

				<h4><?php esc_html_e( 'For Simple Businesses:', 'wc-collection-date' ); ?></h4>
				<ol style="line-height: 1.8; font-size: 14px;">
					<li><?php esc_html_e( 'Go to "Settings" tab', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Set your lead time (e.g., 2 days)', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Choose "Calendar Days" if you work every day', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Check all 7 days for Collection Days', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Done! Customers will see dates 2+ days from now', 'wc-collection-date' ); ?></li>
				</ol>

				<h4 style="margin-top: 25px;"><?php esc_html_e( 'For Bakeries/Complex Businesses:', 'wc-collection-date' ); ?></h4>
				<ol style="line-height: 1.8; font-size: 14px;">
					<li><?php esc_html_e( 'Go to "Settings" tab', 'wc-collection-date' ); ?>
						<ul style="margin-left: 20px; margin-top: 5px;">
							<li><?php esc_html_e( 'Set Working Days: Mon-Fri (production days)', 'wc-collection-date' ); ?></li>
							<li><?php esc_html_e( 'Set Collection Days: All 7 days (customers can pickup anytime)', 'wc-collection-date' ); ?></li>
							<li><?php esc_html_e( 'Set a reasonable default lead time (e.g., 2 days)', 'wc-collection-date' ); ?></li>
							<li><?php esc_html_e( 'Choose "Working Days" calculation', 'wc-collection-date' ); ?></li>
							<li><?php esc_html_e( 'Set cutoff time (e.g., 12:00 PM)', 'wc-collection-date' ); ?></li>
						</ul>
					</li>
					<li style="margin-top: 10px;"><?php esc_html_e( 'Go to "Category Rules" tab', 'wc-collection-date' ); ?>
						<ul style="margin-left: 20px; margin-top: 5px;">
							<li><?php esc_html_e( 'Create rule for "Standard Cakes": 3 days, 12pm cutoff', 'wc-collection-date' ); ?></li>
							<li><?php esc_html_e( 'Create rule for "3D Cakes": 4 days, 12pm cutoff', 'wc-collection-date' ); ?></li>
							<li><?php esc_html_e( 'Create rule for "Wedding Cakes": 7 days, 5pm cutoff', 'wc-collection-date' ); ?></li>
						</ul>
					</li>
					<li style="margin-top: 10px;"><?php esc_html_e( 'Assign your products to the correct categories', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Add holiday exclusions in "Date Exclusions" tab', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Test by adding products to cart and viewing checkout!', 'wc-collection-date' ); ?></li>
				</ol>
			</div>

			<hr>

			<!-- Troubleshooting -->
			<div class="wc-instructions-section">
				<h3><?php esc_html_e( '🔧 Troubleshooting', 'wc-collection-date' ); ?></h3>

				<h4><?php esc_html_e( 'Wrong dates showing?', 'wc-collection-date' ); ?></h4>
				<ul style="line-height: 1.8; font-size: 14px;">
					<li><?php esc_html_e( 'Check if product has a category rule - it overrides global settings', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Verify Working Days vs Collection Days are set correctly', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Check if current time is past cutoff (adds +1 day)', 'wc-collection-date' ); ?></li>
				</ul>

				<h4 style="margin-top: 15px;"><?php esc_html_e( 'Weekends not being skipped?', 'wc-collection-date' ); ?></h4>
				<ul style="line-height: 1.8; font-size: 14px;">
					<li><?php esc_html_e( 'Make sure "Lead Time Calculation" is set to "Working Days"', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Uncheck Saturday and Sunday in "Working Days (Production)"', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Collection Days can still include weekends for customer pickup', 'wc-collection-date' ); ?></li>
				</ul>

				<h4 style="margin-top: 15px;"><?php esc_html_e( 'Category rules not working?', 'wc-collection-date' ); ?></h4>
				<ul style="line-height: 1.8; font-size: 14px;">
					<li><?php esc_html_e( 'Verify the product is actually assigned to that category', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Check that the category rule was saved successfully', 'wc-collection-date' ); ?></li>
					<li><?php esc_html_e( 'Clear your browser cache and try again', 'wc-collection-date' ); ?></li>
				</ul>
			</div>

		</div>
		<?php
	}

	/**
	 * Sanitize working days
	 *
	 * @param array $input Input values.
	 * @return array Sanitized values.
	 */
	public function sanitize_working_days( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$valid_days = array( '0', '1', '2', '3', '4', '5', '6' );
		$sanitized  = array();

		foreach ( $input as $day ) {
			if ( in_array( $day, $valid_days, true ) ) {
				$sanitized[] = $day;
			}
		}

		// Clear cache when settings change.
		WC_Collection_Date_Calculator::clear_cache();

		return $sanitized;
	}

	/**
	 * Sanitize collection days
	 *
	 * @param array $input Input value.
	 * @return array Sanitized value.
	 */
	public function sanitize_collection_days( $input ) {
		if ( ! is_array( $input ) ) {
			return array();
		}

		$valid_days = array( '0', '1', '2', '3', '4', '5', '6' );
		$sanitized  = array();

		foreach ( $input as $day ) {
			if ( in_array( $day, $valid_days, true ) ) {
				$sanitized[] = $day;
			}
		}

		// Clear cache when settings change.
		WC_Collection_Date_Calculator::clear_cache();

		return $sanitized;
	}

	/**
	 * Sanitize lead time type
	 *
	 * @param string $input Input value.
	 * @return string Sanitized value.
	 */
	public function sanitize_lead_time_type( $input ) {
		$valid_types = array( 'calendar', 'working' );

		// Clear cache when settings change.
		WC_Collection_Date_Calculator::clear_cache();

		if ( in_array( $input, $valid_types, true ) ) {
			return $input;
		}

		return 'calendar'; // Default to calendar if invalid.
	}

	/**
	 * Sanitize cutoff time
	 *
	 * @param string $input Input value.
	 * @return string Sanitized value.
	 */
	public function sanitize_cutoff_time( $input ) {
		// Clear cache when settings change.
		WC_Collection_Date_Calculator::clear_cache();

		// Allow empty string.
		if ( empty( $input ) ) {
			return '';
		}

		// Validate HH:MM format.
		if ( preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $input ) ) {
			return $input;
		}

		// Invalid format, return empty.
		return '';
	}

	/**
	 * Render Import/Export tab
	 */
	public function render_import_export_tab() {
		// Handle export.
		if ( isset( $_POST['wc_collection_export_settings'] ) && check_admin_referer( 'wc_collection_export_settings' ) ) {
			$this->export_settings();
			return;
		}

		// Handle import.
		if ( isset( $_POST['wc_collection_import_settings'] ) && check_admin_referer( 'wc_collection_import_settings' ) ) {
			$this->import_settings();
		}

		?>
		<div class="wc-collection-import-export-wrapper">
			<h2><?php esc_html_e( 'Import/Export Settings', 'wc-collection-date' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Export your plugin settings to a JSON file for backup or transfer to another site. Import settings from a JSON file to restore or clone configuration.', 'wc-collection-date' ); ?>
			</p>

			<div class="wc-collection-import-export-sections">
				<!-- Export Section -->
				<div class="wc-collection-export-section">
					<h3><?php esc_html_e( 'Export Settings', 'wc-collection-date' ); ?></h3>
					<p><?php esc_html_e( 'Download all plugin settings, category rules, and exclusions as a JSON file.', 'wc-collection-date' ); ?></p>

					<form method="post" action="">
						<?php wp_nonce_field( 'wc_collection_export_settings' ); ?>
						<p>
							<button type="submit" name="wc_collection_export_settings" class="button button-primary">
								<span class="dashicons dashicons-download" style="margin-top: 3px;"></span>
								<?php esc_html_e( 'Export Settings', 'wc-collection-date' ); ?>
							</button>
						</p>
					</form>

					<div class="wc-collection-export-info">
						<p><strong><?php esc_html_e( 'What will be exported:', 'wc-collection-date' ); ?></strong></p>
						<ul>
							<li><?php esc_html_e( 'Global settings (lead time, working days, collection days)', 'wc-collection-date' ); ?></li>
							<li><?php esc_html_e( 'Category-based rules', 'wc-collection-date' ); ?></li>
							<li><?php esc_html_e( 'Date exclusions', 'wc-collection-date' ); ?></li>
							<li><?php esc_html_e( 'Plugin version information', 'wc-collection-date' ); ?></li>
						</ul>
					</div>
				</div>

				<!-- Import Section -->
				<div class="wc-collection-import-section">
					<h3><?php esc_html_e( 'Import Settings', 'wc-collection-date' ); ?></h3>
					<p><?php esc_html_e( 'Upload a JSON file to restore or import settings from another site.', 'wc-collection-date' ); ?></p>

					<form method="post" action="" enctype="multipart/form-data">
						<?php wp_nonce_field( 'wc_collection_import_settings' ); ?>

						<p>
							<input type="file" name="import_file" accept=".json" required />
						</p>

						<p>
							<label>
								<input type="checkbox" name="overwrite_existing" value="1" />
								<?php esc_html_e( 'Overwrite existing settings', 'wc-collection-date' ); ?>
							</label>
						</p>

						<p>
							<button type="submit" name="wc_collection_import_settings" class="button button-secondary">
								<span class="dashicons dashicons-upload" style="margin-top: 3px;"></span>
								<?php esc_html_e( 'Import Settings', 'wc-collection-date' ); ?>
							</button>
						</p>
					</form>

					<div class="wc-collection-import-warning">
						<p><strong><?php esc_html_e( 'Important:', 'wc-collection-date' ); ?></strong></p>
						<ul>
							<li><?php esc_html_e( 'Always backup your settings before importing', 'wc-collection-date' ); ?></li>
							<li><?php esc_html_e( 'If "Overwrite existing" is unchecked, existing rules will be preserved', 'wc-collection-date' ); ?></li>
							<li><?php esc_html_e( 'Category IDs must match on both sites for rules to work correctly', 'wc-collection-date' ); ?></li>
						</ul>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Export settings to JSON file
	 */
	private function export_settings() {
		global $wpdb;

		// Gather all settings.
		$export_data = array(
			'version'         => get_option( 'wc_collection_date_version', '1.0.0' ),
			'export_date'     => current_time( 'mysql' ),
			'site_url'        => get_site_url(),
			'settings'        => array(
				'lead_time'          => get_option( 'wc_collection_date_lead_time', 2 ),
				'lead_time_type'     => get_option( 'wc_collection_date_lead_time_type', 'calendar' ),
				'cutoff_time'        => get_option( 'wc_collection_date_cutoff_time', '' ),
				'working_days'       => get_option( 'wc_collection_date_working_days', array() ),
				'collection_days'    => get_option( 'wc_collection_date_collection_days', array() ),
				'max_booking_days'   => get_option( 'wc_collection_date_max_booking_days', 90 ),
			),
			'category_rules'  => get_option( 'wc_collection_date_category_rules', array() ),
			'exclusions'      => array(),
		);

		// Get exclusions from database.
		$table_name = $wpdb->prefix . 'wc_collection_exclusions';
		if ( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $table_name ) ) === $table_name ) {
			$exclusions = $wpdb->get_results(
				"SELECT exclusion_date, reason FROM {$table_name} ORDER BY exclusion_date ASC",
				ARRAY_A
			);
			$export_data['exclusions'] = $exclusions ? $exclusions : array();
		}

		// Generate filename.
		$filename = 'wc-collection-date-settings-' . date( 'Y-m-d-His' ) . '.json';

		// Set headers for download.
		header( 'Content-Type: application/json' );
		header( 'Content-Disposition: attachment; filename=' . $filename );
		header( 'Pragma: no-cache' );
		header( 'Expires: 0' );

		// Output JSON.
		echo wp_json_encode( $export_data, JSON_PRETTY_PRINT );
		exit;
	}

	/**
	 * Import settings from JSON file
	 */
	private function import_settings() {
		global $wpdb;

		// Check file upload.
		if ( ! isset( $_FILES['import_file'] ) || $_FILES['import_file']['error'] !== UPLOAD_ERR_OK ) {
			add_settings_error(
				'wc_collection_date_messages',
				'import_error',
				__( 'Error uploading file. Please try again.', 'wc-collection-date' ),
				'error'
			);
			return;
		}

		// Read file contents.
		$file_content = file_get_contents( $_FILES['import_file']['tmp_name'] );
		$import_data  = json_decode( $file_content, true );

		// Validate JSON.
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			add_settings_error(
				'wc_collection_date_messages',
				'import_error',
				__( 'Invalid JSON file. Please check the file format.', 'wc-collection-date' ),
				'error'
			);
			return;
		}

		// Validate structure.
		if ( ! isset( $import_data['settings'] ) ) {
			add_settings_error(
				'wc_collection_date_messages',
				'import_error',
				__( 'Invalid settings file. Missing required data.', 'wc-collection-date' ),
				'error'
			);
			return;
		}

		$overwrite = isset( $_POST['overwrite_existing'] ) && $_POST['overwrite_existing'] === '1';

		// Import settings.
		if ( isset( $import_data['settings'] ) ) {
			foreach ( $import_data['settings'] as $key => $value ) {
				$option_name = 'wc_collection_date_' . $key;
				if ( $overwrite || false === get_option( $option_name ) ) {
					update_option( $option_name, $value );
				}
			}
		}

		// Import category rules.
		if ( isset( $import_data['category_rules'] ) && is_array( $import_data['category_rules'] ) ) {
			if ( $overwrite ) {
				update_option( 'wc_collection_date_category_rules', $import_data['category_rules'] );
			} else {
				$existing_rules = get_option( 'wc_collection_date_category_rules', array() );
				$merged_rules   = array_merge( $existing_rules, $import_data['category_rules'] );
				update_option( 'wc_collection_date_category_rules', $merged_rules );
			}
		}

		// Import exclusions.
		if ( isset( $import_data['exclusions'] ) && is_array( $import_data['exclusions'] ) ) {
			$table_name = $wpdb->prefix . 'wc_collection_exclusions';

			if ( $overwrite ) {
				// Clear existing exclusions.
				$wpdb->query( "TRUNCATE TABLE {$table_name}" );
			}

			foreach ( $import_data['exclusions'] as $exclusion ) {
				if ( isset( $exclusion['exclusion_date'] ) ) {
					$wpdb->insert(
						$table_name,
						array(
							'exclusion_date' => $exclusion['exclusion_date'],
							'reason'         => isset( $exclusion['reason'] ) ? $exclusion['reason'] : '',
						),
						array( '%s', '%s' )
					);
				}
			}
		}

		// Clear cache after import.
		WC_Collection_Date_Calculator::clear_cache();

		add_settings_error(
			'wc_collection_date_messages',
			'import_success',
			__( 'Settings imported successfully!', 'wc-collection-date' ),
			'success'
		);
	}

	/**
	 * Render Analytics tab
	 */
	private function render_analytics_tab() {
		// Ensure analytics class is loaded
		if ( ! class_exists( 'WC_Collection_Date_Analytics' ) ) {
			require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/class-analytics.php';
		}

		wp_enqueue_script( 'jquery-ui-datepicker' );
		wp_enqueue_style( 'jquery-ui' );
		?>
		<div class="wc-collection-analytics-wrapper">
			<h2><?php esc_html_e( 'Collection Date Analytics', 'wc-collection-date' ); ?></h2>
			<p class="description">
				<?php esc_html_e( 'Track collection date usage patterns, popular dates, and customer behavior.', 'wc-collection-date' ); ?>
			</p>

			<!-- Period Selector -->
			<div class="wc-analytics-period-selector">
				<label for="period"><?php esc_html_e( 'Period:', 'wc-collection-date' ); ?></label>
				<select id="period" name="period">
					<option value="7days"><?php esc_html_e( 'Last 7 days', 'wc-collection-date' ); ?></option>
					<option value="30days" selected><?php esc_html_e( 'Last 30 days', 'wc-collection-date' ); ?></option>
					<option value="90days"><?php esc_html_e( 'Last 90 days', 'wc-collection-date' ); ?></option>
					<option value="custom"><?php esc_html_e( 'Custom Range', 'wc-collection-date' ); ?></option>
				</select>

				<span id="custom-date-range" style="display: none;">
					<input type="text" id="start-date" placeholder="<?php esc_attr_e( 'Start date', 'wc-collection-date' ); ?>" class="regular-text">
					<span><?php esc_html_e( 'to', 'wc-collection-date' ); ?></span>
					<input type="text" id="end-date" placeholder="<?php esc_attr_e( 'End date', 'wc-collection-date' ); ?>" class="regular-text">
				</span>

				<button id="refresh-analytics" class="button">
					<span class="dashicons dashicons-update" style="margin-top: 3px;"></span>
					<?php esc_html_e( 'Refresh', 'wc-collection-date' ); ?>
				</button>
			</div>

			<!-- Loading Indicator -->
			<div id="analytics-loading" class="notice notice-info" style="display: none;">
				<p><span class="spinner is-active"></span> <?php esc_html_e( 'Loading analytics data...', 'wc-collection-date' ); ?></p>
			</div>

			<!-- Analytics Content -->
			<div id="analytics-content">
				<!-- Summary Cards -->
				<div class="wc-analytics-summary-cards">
					<div class="summary-card">
						<h3><?php esc_html_e( 'Total Selections', 'wc-collection-date' ); ?></h3>
						<span id="total-selections" class="big-number">-</span>
						<small><?php esc_html_e( 'Date selections', 'wc-collection-date' ); ?></small>
					</div>

					<div class="summary-card">
						<h3><?php esc_html_e( 'Total Orders', 'wc-collection-date' ); ?></h3>
						<span id="total-orders" class="big-number">-</span>
						<small><?php esc_html_e( 'Completed orders', 'wc-collection-date' ); ?></small>
					</div>

					<div class="summary-card">
						<h3><?php esc_html_e( 'Total Value', 'wc-collection-date' ); ?></h3>
						<span id="total-value" class="big-number">-</span>
						<small><?php esc_html_e( 'Order value', 'wc-collection-date' ); ?></small>
					</div>

					<div class="summary-card">
						<h3><?php esc_html_e( 'Avg Lead Time', 'wc-collection-date' ); ?></h3>
						<span id="avg-lead-time" class="big-number">-</span>
						<small><?php esc_html_e( 'Days', 'wc-collection-date' ); ?></small>
					</div>

					<div class="summary-card">
						<h3><?php esc_html_e( 'Conversion', 'wc-collection-date' ); ?></h3>
						<span id="conversion-rate" class="big-number">-</span>
						<small><?php esc_html_e( 'Selection rate', 'wc-collection-date' ); ?></small>
					</div>
				</div>

				<!-- Charts Grid -->
				<div class="wc-analytics-charts-grid">
					<!-- Popular Dates Chart -->
					<div class="chart-container">
						<h3><?php esc_html_e( 'Most Popular Collection Dates', 'wc-collection-date' ); ?></h3>
						<div id="popular-dates-chart" class="chart-content">
							<div class="chart-placeholder">
								<p><?php esc_html_e( 'Select a period and click Refresh', 'wc-collection-date' ); ?></p>
							</div>
						</div>
						<div class="chart-actions">
							<button class="button export-data" data-type="popular_dates">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Export', 'wc-collection-date' ); ?>
							</button>
						</div>
					</div>

					<!-- Hourly Distribution -->
					<div class="chart-container">
						<h3><?php esc_html_e( 'Order Distribution by Hour', 'wc-collection-date' ); ?></h3>
						<div id="hourly-chart" class="chart-content">
							<div class="chart-placeholder">
								<p><?php esc_html_e( 'Select a period and click Refresh', 'wc-collection-date' ); ?></p>
							</div>
						</div>
						<div class="chart-actions">
							<button class="button export-data" data-type="hourly">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Export', 'wc-collection-date' ); ?>
							</button>
						</div>
					</div>

					<!-- Day of Week Distribution -->
					<div class="chart-container">
						<h3><?php esc_html_e( 'Orders by Day of Week', 'wc-collection-date' ); ?></h3>
						<div id="day-week-chart" class="chart-content">
							<div class="chart-placeholder">
								<p><?php esc_html_e( 'Select a period and click Refresh', 'wc-collection-date' ); ?></p>
							</div>
						</div>
						<div class="chart-actions">
							<button class="button export-data" data-type="day_of_week">
								<span class="dashicons dashicons-download"></span>
								<?php esc_html_e( 'Export', 'wc-collection-date' ); ?>
							</button>
						</div>
					</div>

					<!-- Lead Time Distribution -->
					<div class="chart-container">
						<h3><?php esc_html_e( 'Lead Time Distribution', 'wc-collection-date' ); ?></h3>
						<div id="lead-time-chart" class="chart-content">
							<div class="chart-placeholder">
								<p><?php esc_html_e( 'Select a period and click Refresh', 'wc-collection-date' ); ?></p>
							</div>
						</div>
					</div>

					<!-- Monthly Trends -->
					<div class="chart-container full-width">
						<h3><?php esc_html_e( 'Monthly Trends', 'wc-collection-date' ); ?></h3>
						<div id="monthly-chart" class="chart-content">
							<div class="chart-placeholder">
								<p><?php esc_html_e( 'Select a period and click Refresh', 'wc-collection-date' ); ?></p>
							</div>
						</div>
					</div>
				</div>

				<!-- Popular Dates Table -->
				<div class="wc-analytics-table">
					<h3><?php esc_html_e( 'Top 20 Collection Dates', 'wc-collection-date' ); ?></h3>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Date', 'wc-collection-date' ); ?></th>
								<th><?php esc_html_e( 'Day', 'wc-collection-date' ); ?></th>
								<th><?php esc_html_e( 'Selections', 'wc-collection-date' ); ?></th>
								<th><?php esc_html_e( 'Orders', 'wc-collection-date' ); ?></th>
								<th><?php esc_html_e( 'Total Value', 'wc-collection-date' ); ?></th>
								<th><?php esc_html_e( 'Avg Lead Time', 'wc-collection-date' ); ?></th>
							</tr>
						</thead>
						<tbody id="popular-dates-table">
							<tr>
								<td colspan="6" style="text-align: center;">
									<p><?php esc_html_e( 'No data to display', 'wc-collection-date' ); ?></p>
								</td>
							</tr>
						</tbody>
					</table>
				</div>
			</div>
		</div>

		<style>
			.wc-collection-analytics-wrapper {
				padding: 20px;
				background: #fff;
				border: 1px solid #ccd0d4;
				border-radius: 4px;
				margin: 20px 0;
			}

			.wc-analytics-period-selector {
				margin: 20px 0;
				padding: 15px;
				background: #f9f9f9;
				border: 1px solid #e1e1e1;
				border-radius: 4px;
				display: flex;
				align-items: center;
				gap: 15px;
				flex-wrap: wrap;
			}

			.wc-analytics-period-selector label {
				font-weight: 600;
			}

			#custom-date-range {
				display: inline-flex;
				align-items: center;
				gap: 10px;
			}

			.wc-analytics-summary-cards {
				display: grid;
				grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
				gap: 20px;
				margin: 30px 0;
			}

			.summary-card {
				background: #f8f9fa;
				padding: 20px;
				border: 1px solid #e1e1e1;
				border-radius: 6px;
				text-align: center;
			}

			.summary-card h3 {
				margin: 0 0 10px;
				font-size: 14px;
				font-weight: 600;
				color: #666;
				text-transform: uppercase;
				letter-spacing: 0.5px;
			}

			.summary-card .big-number {
				display: block;
				font-size: 32px;
				font-weight: bold;
				color: #23282d;
				line-height: 1.2;
			}

			.summary-card small {
				color: #666;
				font-size: 12px;
			}

			.wc-analytics-charts-grid {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 30px;
				margin: 30px 0;
			}

			.chart-container {
				background: #fff;
				border: 1px solid #e1e1e1;
				border-radius: 6px;
				padding: 20px;
			}

			.chart-container.full-width {
				grid-column: 1 / -1;
			}

			.chart-container h3 {
				margin: 0 0 20px;
				font-size: 16px;
				color: #23282d;
			}

			.chart-content {
				min-height: 250px;
				display: flex;
				align-items: center;
				justify-content: center;
			}

			.chart-placeholder {
				color: #666;
				text-align: center;
			}

			.chart-actions {
				margin-top: 15px;
				padding-top: 15px;
				border-top: 1px solid #e1e1e1;
				text-align: right;
			}

			.wc-analytics-table {
				margin-top: 40px;
			}

			.wc-analytics-table h3 {
				margin-bottom: 20px;
				font-size: 18px;
				color: #23282d;
			}

			/* Chart visualization */
			.chart-bar {
				height: 30px;
				background: #0073aa;
				border-radius: 3px;
				margin: 5px 0;
				position: relative;
				transition: all 0.3s ease;
			}

			.chart-bar:hover {
				background: #005a87;
			}

			.chart-bar-label {
				position: absolute;
				left: 10px;
				top: 50%;
				transform: translateY(-50%);
				color: #fff;
				font-weight: 600;
				text-shadow: 1px 1px 2px rgba(0,0,0,0.3);
			}

			.chart-bar-value {
				position: absolute;
				right: 10px;
				top: 50%;
				transform: translateY(-50%);
				color: #fff;
				font-size: 12px;
			}

			/* Responsive */
			@media (max-width: 768px) {
				.wc-analytics-charts-grid {
					grid-template-columns: 1fr;
				}

				.wc-analytics-summary-cards {
					grid-template-columns: 1fr 1fr;
				}

				.wc-analytics-period-selector {
					flex-direction: column;
					align-items: flex-start;
				}
			}

			/* Loading animation */
			#analytics-loading .spinner {
				float: none;
				margin-right: 10px;
			}
		</style>

		<script>
			jQuery(document).ready(function($) {
				let analyticsData = null;

				// Period selector change
				$('#period').on('change', function() {
					if ($(this).val() === 'custom') {
						$('#custom-date-range').show();
						$('#start-date, #end-date').datepicker({
							dateFormat: 'yy-mm-dd'
						});
					} else {
						$('#custom-date-range').hide();
					}
				});

				// Refresh button
				$('#refresh-analytics').on('click', function() {
					loadAnalytics();
				});

				// Export buttons
				$('.export-data').on('click', function() {
					const dataType = $(this).data('type');
					exportData(dataType);
				});

				// Load analytics function
				function loadAnalytics() {
					const period = $('#period').val();
					let startDate, endDate;

					if (period === 'custom') {
						startDate = $('#start-date').val();
						endDate = $('#end-date').val();

						if (!startDate || !endDate) {
							alert('<?php esc_html_e( "Please select both start and end dates", "wc-collection-date" ); ?>');
							return;
						}
					}

					// Show loading
					$('#analytics-loading').show();
					$('#analytics-content').hide();

					// AJAX request
					$.ajax({
						url: ajaxurl,
						type: 'POST',
						data: {
							action: 'wc_collection_date_get_analytics',
							nonce: '<?php echo wp_create_nonce( "wc_collection_date_analytics_nonce" ); ?>',
							period: period,
							start_date: startDate,
							end_date: endDate
						},
						success: function(response) {
							if (response.success) {
								analyticsData = response.data;
								renderAnalytics(response.data);
								$('#analytics-loading').hide();
								$('#analytics-content').show();
							}
						},
						error: function() {
							$('#analytics-loading').hide();
							alert('<?php esc_html_e( "Error loading analytics data", "wc-collection-date" ); ?>');
						}
					});
				}

				// Render analytics
				function renderAnalytics(data) {
					// Update summary cards
					$('#total-selections').text(data.summary.total_selections.toLocaleString());
					$('#total-orders').text(data.summary.total_orders.toLocaleString());
					$('#total-value').text(data.summary.total_value);
					$('#avg-lead-time').text(data.summary.avg_lead_time + ' days');
					$('#conversion-rate').text(data.summary.conversion_rate + '%');

					// Render popular dates chart
					renderPopularDatesChart(data.popular_dates);

					// Render hourly chart
					renderHourlyChart(data.hourly_distribution);

					// Render day of week chart
					renderDayWeekChart(data.day_of_week_distribution);

					// Render lead time chart
					renderLeadTimeChart(data.lead_time_distribution);

					// Render monthly chart
					renderMonthlyChart(data.monthly_trends);

					// Populate popular dates table
					populatePopularDatesTable(data.popular_dates);
				}

				// Render popular dates chart
				function renderPopularDatesChart(data) {
					const container = $('#popular-dates-chart');
					let html = '';

					if (data && data.length > 0) {
						// Find max value for scaling
						const maxValue = Math.max(...data.map(d => parseInt(d.selection_count)));

						data.slice(0, 10).forEach(item => {
							const width = (item.selection_count / maxValue) * 100;
							const date = new Date(item.collection_date).toLocaleDateString();
							html += `
								<div style="margin: 10px 0;">
									<div style="font-size: 12px; margin-bottom: 2px;">${date}</div>
									<div class="chart-bar" style="width: ${width}%; background: #0073aa;">
										<span class="chart-bar-label">${item.selection_count}</span>
									</div>
								</div>
							`;
						});
					} else {
						html = '<div class="chart-placeholder"><p><?php esc_html_e( "No data available", "wc-collection-date" ); ?></p></div>';
					}

					container.html(html);
				}

				// Render hourly chart
				function renderHourlyChart(data) {
					const container = $('#hourly-chart');
					const hours = ['12am', '1am', '2am', '3am', '4am', '5am', '6am', '7am', '8am', '9am', '10am', '11am', '12pm', '1pm', '2pm', '3pm', '4pm', '5pm', '6pm', '7pm', '8pm', '9pm', '10pm', '11pm'];

					if (data) {
						const maxValue = Math.max(...Object.values(data));
						let html = '';

						data.forEach((count, hour) => {
							if (count > 0) {
								const width = (count / maxValue) * 100;
								html += `
									<div style="margin: 10px 0; display: flex; align-items: center;">
										<div style="width: 50px; font-size: 11px;">${hours[hour]}</div>
										<div style="flex: 1; margin-left: 10px;">
											<div class="chart-bar" style="width: ${width}%; background: #28a745;">
												<span class="chart-bar-value">${count}</span>
											</div>
										</div>
									</div>
								`;
							}
						});

						if (html) {
							container.html(html);
						} else {
							container.html('<div class="chart-placeholder"><p><?php esc_html_e( "No hourly data", "wc-collection-date" ); ?></p></div>');
						}
					}
				}

				// Render day of week chart
				function renderDayWeekChart(data) {
					const container = $('#day-week-chart');
					const days = ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'];

					if (data) {
						const maxValue = Math.max(...Object.values(data));
						let html = '';

						for (let i = 0; i < days.length; i++) {
							const dayIndex = (i + 1) % 7 === 0 ? 7 : (i + 1) % 7; // Convert to 1-7 (Mon-Sun)
							const count = data[dayIndex] || 0;
							const width = count > 0 ? (count / maxValue) * 100 : 0;

							html += `
								<div style="margin: 10px 0; display: flex; align-items: center;">
									<div style="width: 50px; font-weight: 600;">${days[i]}</div>
									<div style="flex: 1; margin-left: 10px;">
										<div class="chart-bar" style="width: ${width}%; background: #ffc107;">
											<span class="chart-bar-value">${count}</span>
										</div>
									</div>
								</div>
							`;
						}

						container.html(html);
					}
				}

				// Render lead time distribution
				function renderLeadTimeChart(data) {
					const container = $('#lead-time-chart');

					if (data) {
						const labels = Object.keys(data);
						const values = Object.values(data);
						const maxValue = Math.max(...values);

						let html = '';
						const colors = ['#0073aa', '#28a745', '#ffc107', '#dc3545', '#6f42c1'];

						labels.forEach((label, index) => {
							const value = values[index];
							const width = value > 0 ? (value / maxValue) * 100 : 0;

							html += `
								<div style="margin: 15px 0;">
									<div style="font-size: 12px; margin-bottom: 5px; font-weight: 600;">${label}: ${value} orders</div>
									<div class="chart-bar" style="width: ${width}%; background: ${colors[index]};">
									</div>
								</div>
							`;
						});

						container.html(html);
					}
				}

				// Render monthly chart
				function renderMonthlyChart(data) {
					const container = $('#monthly-chart');
					const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

					if (data) {
						const maxValue = Math.max(...Object.values(data));
						let html = '';

						for (let i = 0; i < 12; i++) {
							const count = data[i + 1] || 0;
							const width = count > 0 ? (count / maxValue) * 100 : 0;

							html += `
								<div style="display: inline-block; width: 8%; margin: 0 0.5%; text-align: center;">
									<div style="font-size: 11px; margin-bottom: 5px;">${months[i]}</div>
									<div class="chart-bar" style="height: 100px; background: #17a2b8; display: flex; align-items: flex-end; justify-content: center; padding-bottom: 5px;">
										<span style="color: #fff; font-size: 10px; writing-mode: vertical-rl;">${count}</span>
									</div>
								</div>
							`;
						}

						container.html(html);
					}
				}

				// Populate popular dates table
				function populatePopularDatesTable(data) {
					const tbody = $('#popular-dates-table');

					if (data && data.length > 0) {
						let html = '';
						data.forEach(item => {
							const date = new Date(item.collection_date);
							const dayOfWeek = date.toLocaleDateString('en-US', { weekday: 'long' });
							const formattedDate = date.toLocaleDateString();

							html += `
								<tr>
									<td><strong>${formattedDate}</strong></td>
									<td>${dayOfWeek}</td>
									<td>${item.selection_count}</td>
									<td>${item.total_orders}</td>
									<td>${wc_price_format(item.total_value)}</td>
									<td>${item.avg_lead_time} days</td>
								</tr>
							`;
						});
						tbody.html(html);
					}
				}

				// Export function
				function exportData(dataType) {
					const period = $('#period').val();
					let startDate, endDate;

					if (period === 'custom') {
						startDate = $('#start-date').val();
						endDate = $('#end-date').val();
					}

					// Create form and submit
					const form = $('<form>', {
						method: 'POST',
						action: ajaxurl
					});

					form.append($('<input>', {
						type: 'hidden',
						name: 'action',
						value: 'wc_collection_date_export_analytics'
					}));

					form.append($('<input>', {
						type: 'hidden',
						name: 'nonce',
						value: '<?php echo wp_create_nonce( "wc_collection_date_analytics_nonce" ); ?>'
					}));

					form.append($('<input>', {
						type: 'hidden',
						name: 'period',
						value: period
					}));

					form.append($('<input>', {
						type: 'hidden',
						name: 'data_type',
						value: dataType
					}));

					if (startDate && endDate) {
						form.append($('<input>', {
							type: 'hidden',
							name: 'start_date',
							value: startDate
						}));

						form.append($('<input>', {
							type: 'hidden',
							name: 'end_date',
							value: endDate
						}));
					}

					$('body').append(form);
					form.submit();
					form.remove();
				}

				// Price format helper
				function wc_price_format(amount) {
					// Simple price formatting - replace with your currency symbol as needed
					return '<?php echo get_woocommerce_currency_symbol(); ?>' + parseFloat(amount).toFixed(2);
				}

				// Load initial data
				loadAnalytics();
			});
		</script>
		<?php
	}
}
