<?php
/**
 * Factory for creating test data for WC Collection Date plugin tests
 *
 * @package WC_Collection_Date\Tests\Factory
 */

if ( ! class_exists( 'WP_UnitTest_Factory' ) ) {
	require_once ABSPATH . 'wp-tests/includes/factory/class-wp-unittest-factory.php';
}

/**
 * WC Collection Date Factory class.
 *
 * Extends WordPress unit test factory with plugin-specific factories.
 */
class WC_Collection_Date_Factory extends WP_UnitTest_Factory {

	/**
	 * Product factory.
	 *
	 * @var WP_UnitTest_Factory_For_Product
	 */
	public $product;

	/**
	 * Order factory.
	 *
	 * @var WP_UnitTest_Factory_For_Order
	 */
	public $order;

	/**
	 * Term factory.
	 *
	 * @var WP_UnitTest_Factory_For_Term
	 */
	public $term;

	/**
	 * User factory.
	 *
	 * @var WP_UnitTest_Factory_For_User
	 */
	public $user;

	/**
	 * Collection date exclusion factory.
	 *
	 * @var WC_Collection_Date_Factory_For_Exclusion
	 */
	public $exclusion;

	/**
	 * Collection date analytics factory.
	 *
	 * @var WC_Collection_Date_Factory_For_Analytics
	 */
	public $analytics;

	/**
	 * Constructor.
	 */
	public function __construct() {
		parent::__construct();

		// Initialize WordPress core factories.
		$this->user = new WP_UnitTest_Factory_For_User( $this );
		$this->term = new WP_UnitTest_Factory_For_Term( $this );

		// Initialize WooCommerce factories if available.
		if ( class_exists( 'WP_UnitTest_Factory_For_Product' ) ) {
			$this->product = new WP_UnitTest_Factory_For_Product( $this );
		} else {
			// Fallback product factory.
			$this->product = new WC_Collection_Date_Factory_For_Product_Fallback( $this );
		}

		if ( class_exists( 'WP_UnitTest_Factory_For_Order' ) ) {
			$this->order = new WP_UnitTest_Factory_For_Order( $this );
		} else {
			// Fallback order factory.
			$this->order = new WC_Collection_Date_Factory_For_Order_Fallback( $this );
		}

		// Initialize plugin-specific factories.
		$this->exclusion = new WC_Collection_Date_Factory_For_Exclusion( $this );
		$this->analytics = new WC_Collection_Date_Factory_For_Analytics( $this );
	}
}

/**
 * Factory for creating WooCommerce products.
 */
if ( ! class_exists( 'WP_UnitTest_Factory_For_Product' ) ) {
	class WC_Collection_Date_Factory_For_Product_Fallback extends WP_UnitTest_Factory_For_Post {

		/**
		 * Constructor.
		 *
		 * @param object $factory Factory object.
		 */
		public function __construct( $factory = null ) {
			parent::__construct( $factory );
			$this->default_generation_definitions = array(
				'post_status'  => 'publish',
				'post_title'   => 'Product',
				'post_content' => 'Product description',
				'post_excerpt' => 'Product short description',
				'post_type'    => 'product',
			);
		}

		/**
		 * Create a product.
		 *
		 * @param array $args Product arguments.
		 * @return int Product ID.
		 */
		public function create_object( $args ) {
			// Set post type to product.
			$args['post_type'] = 'product';

			// Create the post.
			$post_id = parent::create_object( $args );

			// Set WooCommerce-specific meta.
			if ( ! empty( $args['price'] ) ) {
				update_post_meta( $post_id, '_price', $args['price'] );
				update_post_meta( $post_id, '_regular_price', $args['price'] );
			}

			if ( ! empty( $args['sku'] ) ) {
				update_post_meta( $post_id, '_sku', $args['sku'] );
			}

			if ( isset( $args['manage_stock'] ) ) {
				update_post_meta( $post_id, '_manage_stock', $args['manage_stock'] ? 'yes' : 'no' );
			}

			if ( isset( $args['stock_status'] ) ) {
				update_post_meta( $post_id, '_stock_status', $args['stock_status'] );
			}

			// Set product type.
			wp_set_object_terms( $post_id, 'simple', 'product_type' );

			return $post_id;
		}

		/**
		 * Update a product.
		 *
		 * @param int   $post_id Post ID.
		 * @param array $fields  Fields to update.
		 * @return int|bool Post ID on success, false on failure.
		 */
		public function update_object( $post_id, $fields ) {
			return parent::update_object( $post_id, $fields );
		}

		/**
		 * Get a product object.
		 *
		 * @param int $post_id Post ID.
		 * @return WC_Product|false Product object or false.
		 */
		public function get_object_by_id( $post_id ) {
			if ( function_exists( 'wc_get_product' ) ) {
				return wc_get_product( $post_id );
			}

			// Fallback to post object.
			return get_post( $post_id );
		}
	}
}

/**
 * Factory for creating WooCommerce orders.
 */
if ( ! class_exists( 'WP_UnitTest_Factory_For_Order' ) ) {
	class WC_Collection_Date_Factory_For_Order_Fallback extends WP_UnitTest_Factory_For_Post {

		/**
		 * Constructor.
		 *
		 * @param object $factory Factory object.
		 */
		public function __construct( $factory = null ) {
			parent::__construct( $factory );
			$this->default_generation_definitions = array(
				'post_status'  => 'wc-processing',
				'post_title'   => 'Order',
				'post_type'    => 'shop_order',
				'post_content' => '',
			);
		}

		/**
		 * Create an order.
		 *
		 * @param array $args Order arguments.
		 * @return int Order ID.
		 */
		public function create_object( $args ) {
			// Set post type to shop_order.
			$args['post_type'] = 'shop_order';

			// Create the post.
			$order_id = parent::create_object( $args );

			// Set WooCommerce-specific meta.
			if ( ! empty( $args['customer_id'] ) ) {
				update_post_meta( $order_id, '_customer_user', $args['customer_id'] );
			}

			if ( ! empty( $args['total'] ) ) {
				update_post_meta( $order_id, '_order_total', $args['total'] );
			}

			if ( ! empty( $args['payment_method'] ) ) {
				update_post_meta( $order_id, '_payment_method', $args['payment_method'] );
			}

			if ( ! empty( $args['billing_email'] ) ) {
				update_post_meta( $order_id, '_billing_email', $args['billing_email'] );
			}

			if ( ! empty( $args['billing_first_name'] ) ) {
				update_post_meta( $order_id, '_billing_first_name', $args['billing_first_name'] );
			}

			if ( ! empty( $args['billing_last_name'] ) ) {
				update_post_meta( $order_id, '_billing_last_name', $args['billing_last_name'] );
			}

			return $order_id;
		}

		/**
		 * Update an order.
		 *
		 * @param int   $post_id Post ID.
		 * @param array $fields  Fields to update.
		 * @return int|bool Post ID on success, false on failure.
		 */
		public function update_object( $post_id, $fields ) {
			return parent::update_object( $post_id, $fields );
		}

		/**
		 * Get an order object.
		 *
		 * @param int $post_id Post ID.
		 * @return WC_Order|false Order object or false.
		 */
		public function get_object_by_id( $post_id ) {
			if ( function_exists( 'wc_get_order' ) ) {
				return wc_get_order( $post_id );
			}

			// Fallback to post object.
			return get_post( $post_id );
		}
	}
}

/**
 * Factory for creating collection date exclusions.
 */
class WC_Collection_Date_Factory_For_Exclusion {

	/**
	 * Factory object.
	 *
	 * @var WP_UnitTest_Factory
	 */
	protected $factory;

	/**
	 * Default exclusion arguments.
	 *
	 * @var array
	 */
	protected $default_generation_definitions = array(
		'exclusion_date' => '2024-12-25',
		'reason'         => 'Christmas Day',
	);

	/**
	 * Constructor.
	 *
	 * @param WP_UnitTest_Factory $factory Factory object.
	 */
	public function __construct( $factory = null ) {
		$this->factory = $factory;
	}

	/**
	 * Create a collection date exclusion.
	 *
	 * @param array $args Exclusion arguments.
	 * @return int Exclusion ID.
	 */
	public function create( $args = array() ) {
		$args = array_merge( $this->default_generation_definitions, $args );

		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		// Ensure table exists.
		$this->create_table_if_not_exists();

		$result = $wpdb->insert(
			$table_name,
			array(
				'exclusion_date' => $args['exclusion_date'],
				'reason'         => $args['reason'],
				'created_at'     => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return 0;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Create multiple exclusions.
	 *
	 * @param int   $count Number of exclusions to create.
	 * @param array $args  Arguments for each exclusion.
	 * @return array Array of exclusion IDs.
	 */
	public function create_many( $count, $args = array() ) {
		$ids = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$exclusion_args = $args;

			// Generate unique date if not specified.
			if ( ! isset( $exclusion_args['exclusion_date'] ) ) {
				$exclusion_args['exclusion_date'] = date( 'Y-m-d', strtotime( "+{$i} days" ) );
			}

			// Generate unique reason if not specified.
			if ( ! isset( $exclusion_args['reason'] ) ) {
				$exclusion_args['reason'] = "Test Exclusion {$i}";
			}

			$ids[] = $this->create( $exclusion_args );
		}

		return $ids;
	}

	/**
	 * Create table if it doesn't exist.
	 */
	protected function create_table_if_not_exists() {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_exclusions';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS {$table_name} (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			exclusion_date date NOT NULL,
			reason text NOT NULL,
			created_at datetime DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			UNIQUE KEY exclusion_date (exclusion_date)
		) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get an exclusion by ID.
	 *
	 * @param int $exclusion_id Exclusion ID.
	 * @return object|null Exclusion object or null.
	 */
	public function get_object_by_id( $exclusion_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_exclusions';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				$exclusion_id
			)
		);
	}
}

/**
 * Factory for creating collection date analytics data.
 */
class WC_Collection_Date_Factory_For_Analytics {

	/**
	 * Factory object.
	 *
	 * @var WP_UnitTest_Factory
	 */
	protected $factory;

	/**
	 * Default analytics arguments.
	 *
	 * @var array
	 */
	protected $default_generation_definitions = array(
		'collection_date'   => '2024-01-15',
		'selection_count'   => 5,
		'total_orders'      => 3,
		'total_value'       => '89.97',
		'avg_lead_time'     => '2.5',
		'last_selected'     => '2024-01-14 10:30:00',
	);

	/**
	 * Constructor.
	 *
	 * @param WP_UnitTest_Factory $factory Factory object.
	 */
	public function __construct( $factory = null ) {
		$this->factory = $factory;
	}

	/**
	 * Create analytics data.
	 *
	 * @param array $args Analytics arguments.
	 * @return int Analytics record ID.
	 */
	public function create( $args = array() ) {
		$args = array_merge( $this->default_generation_definitions, $args );

		global $wpdb;
		$table_name = $wpdb->prefix . 'wc_collection_date_analytics';

		// Ensure table exists.
		$this->create_table_if_not_exists();

		$result = $wpdb->insert(
			$table_name,
			array(
				'collection_date' => $args['collection_date'],
				'selection_count' => $args['selection_count'],
				'total_orders'    => $args['total_orders'],
				'total_value'     => $args['total_value'],
				'avg_lead_time'   => $args['avg_lead_time'],
				'last_selected'   => $args['last_selected'],
				'created_at'      => current_time( 'mysql' ),
				'updated_at'      => current_time( 'mysql' ),
			),
			array( '%s', '%d', '%d', '%f', '%f', '%s', '%s', '%s' )
		);

		if ( false === $result ) {
			return 0;
		}

		return $wpdb->insert_id;
	}

	/**
	 * Create multiple analytics records.
	 *
	 * @param int   $count Number of records to create.
	 * @param array $args  Arguments for each record.
	 * @return array Array of record IDs.
	 */
	public function create_many( $count, $args = array() ) {
		$ids = array();

		for ( $i = 0; $i < $count; $i++ ) {
			$analytics_args = $args;

			// Generate unique date if not specified.
			if ( ! isset( $analytics_args['collection_date'] ) ) {
				$analytics_args['collection_date'] = date( 'Y-m-d', strtotime( "+{$i} days" ) );
			}

			// Generate unique values if not specified.
			if ( ! isset( $analytics_args['selection_count'] ) ) {
				$analytics_args['selection_count'] = rand( 1, 20 );
			}

			if ( ! isset( $analytics_args['total_orders'] ) ) {
				$analytics_args['total_orders'] = rand( 1, 10 );
			}

			if ( ! isset( $analytics_args['total_value'] ) ) {
				$analytics_args['total_value'] = rand( 1000, 10000 ) / 100;
			}

			$ids[] = $this->create( $analytics_args );
		}

		return $ids;
	}

	/**
	 * Create table if it doesn't exist.
	 */
	protected function create_table_if_not_exists() {
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

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Get analytics data by ID.
	 *
	 * @param int $analytics_id Analytics record ID.
	 * @return object|null Analytics record or null.
	 */
	public function get_object_by_id( $analytics_id ) {
		global $wpdb;

		$table_name = $wpdb->prefix . 'wc_collection_date_analytics';

		return $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$table_name} WHERE id = %d",
				$analytics_id
			)
		);
	}

	/**
	 * Create analytics data for a date range.
	 *
	 * @param string $start_date Start date in Y-m-d format.
	 * @param string $end_date   End date in Y-m-d format.
	 * @param array  $args      Additional arguments.
	 * @return array Array of created record IDs.
	 */
	public function create_for_date_range( $start_date, $end_date, $args = array() ) {
		$ids = array();
		$current = new DateTime( $start_date );
		$end = new DateTime( $end_date );

		while ( $current <= $end ) {
			$date_args = array_merge( $args, array(
				'collection_date' => $current->format( 'Y-m-d' ),
			) );

			$ids[] = $this->create( $date_args );
			$current->modify( '+1 day' );
		}

		return $ids;
	}
}