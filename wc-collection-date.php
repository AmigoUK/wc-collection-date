<?php
/**
 * Plugin Name: WooCommerce Collection Date
 * Plugin URI: https://github.com/AmigoUK/wc-collection-date
 * Description: Extends WooCommerce local pickup with customer-selected collection dates and advanced business rule configuration.
 * Version: 1.4.3
 * Author: Tomasz 'Amigo' Lewandowski
 * Author URI: https://attv.uk
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wc-collection-date
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 *
 * @package WC_Collection_Date
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

/**
 * Current plugin version.
 */
define( 'WC_COLLECTION_DATE_VERSION', '1.4.3' );

/**
 * Plugin directory path.
 */
define( 'WC_COLLECTION_DATE_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

/**
 * Plugin directory URL.
 */
define( 'WC_COLLECTION_DATE_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * The code that runs during plugin activation.
 */
function activate_wc_collection_date() {
	require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/class-activator.php';
	WC_Collection_Date_Activator::activate();
}

/**
 * The code that runs during plugin deactivation.
 */
function deactivate_wc_collection_date() {
	require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/class-deactivator.php';
	WC_Collection_Date_Deactivator::deactivate();
}

register_activation_hook( __FILE__, 'activate_wc_collection_date' );
register_deactivation_hook( __FILE__, 'deactivate_wc_collection_date' );

/**
 * Check if WooCommerce is active before loading plugin.
 */
function wc_collection_date_check_woocommerce() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', 'wc_collection_date_woocommerce_missing_notice' );
		return false;
	}
	return true;
}

/**
 * Display admin notice when WooCommerce is not active.
 */
function wc_collection_date_woocommerce_missing_notice() {
	?>
	<div class="error">
		<p>
			<?php
			printf(
				/* translators: %s: WooCommerce plugin name */
				esc_html__( 'WooCommerce Collection Date requires %s to be installed and activated.', 'wc-collection-date' ),
				'<strong>WooCommerce</strong>'
			);
			?>
		</p>
	</div>
	<?php
}

/**
 * Declare compatibility with WooCommerce features.
 */
function wc_collection_date_declare_compatibility() {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\FeaturesUtil' ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
	}
}
add_action( 'before_woocommerce_init', 'wc_collection_date_declare_compatibility' );

/**
 * Begin execution of the plugin.
 */
function run_wc_collection_date() {
	if ( ! wc_collection_date_check_woocommerce() ) {
		return;
	}

	require_once WC_COLLECTION_DATE_PLUGIN_DIR . 'includes/class-wc-collection-date.php';
	$plugin = WC_Collection_Date::get_instance();
	$plugin->run();
}

add_action( 'plugins_loaded', 'run_wc_collection_date' );

