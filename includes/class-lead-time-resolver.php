<?php
/**
 * Lead Time Resolver Class
 *
 * Resolves which lead time settings apply to a product based on priority:
 * Product Override > Category Rules > Global Settings
 *
 * @package WC_Collection_Date
 * @since 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/**
 * Lead Time Resolver class.
 */
class WC_Collection_Date_Lead_Time_Resolver {

	/**
	 * Get effective lead time settings for a product.
	 *
	 * Priority: Product Override > Category Rules > Global Settings
	 *
	 * @param int $product_id Product ID.
	 * @return array Effective settings array.
	 */
	public function get_effective_settings( $product_id ) {
		// Priority 1: Check for product-level override (Phase 4 - not implemented yet).
		$product_override = $this->get_product_override( $product_id );
		if ( ! empty( $product_override ) ) {
			return $product_override;
		}

		// Priority 2: Check for category rules.
		$category_settings = $this->get_category_settings( $product_id );
		if ( ! empty( $category_settings ) ) {
			return $category_settings;
		}

		// Priority 3: Fall back to global settings.
		return $this->get_global_settings();
	}

	/**
	 * Get product-level override settings.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null Product override settings or null if not set.
	 */
	protected function get_product_override( $product_id ) {
		// Phase 4 feature - not implemented yet.
		// Will check product meta for custom lead time settings.
		return null;
	}

	/**
	 * Get category-based settings for a product.
	 *
	 * If product has multiple categories with rules, returns the one with longest lead time.
	 *
	 * @param int $product_id Product ID.
	 * @return array|null Category settings or null if no category rules found.
	 */
	protected function get_category_settings( $product_id ) {
		$product_categories = $this->get_product_categories( $product_id );

		if ( empty( $product_categories ) ) {
			return null;
		}

		$category_rules = $this->get_all_category_rules();
		$applicable_rules = array();

		// Find rules for product's categories.
		foreach ( $product_categories as $category_id ) {
			if ( isset( $category_rules[ $category_id ] ) ) {
				$applicable_rules[ $category_id ] = $category_rules[ $category_id ];
			}
		}

		if ( empty( $applicable_rules ) ) {
			return null;
		}

		// If multiple categories have rules, use the one with longest lead time.
		return $this->resolve_multiple_category_rules( $applicable_rules );
	}

	/**
	 * Get all product categories for a given product.
	 *
	 * @param int $product_id Product ID.
	 * @return array Array of category IDs.
	 */
	protected function get_product_categories( $product_id ) {
		$product = wc_get_product( $product_id );

		if ( ! $product ) {
			return array();
		}

		$category_ids = $product->get_category_ids();

		return is_array( $category_ids ) ? $category_ids : array();
	}

	/**
	 * Get all category rules from database.
	 *
	 * @return array Array of category rules keyed by category ID.
	 */
	public function get_all_category_rules() {
		$rules = get_option( 'wc_collection_date_category_rules', array() );

		return is_array( $rules ) ? $rules : array();
	}

	/**
	 * Resolve multiple category rules by selecting the one with longest lead time.
	 *
	 * @param array $rules Array of category rules.
	 * @return array Settings from category with longest lead time.
	 */
	protected function resolve_multiple_category_rules( $rules ) {
		if ( empty( $rules ) ) {
			return array();
		}

		// If only one rule, return it.
		if ( count( $rules ) === 1 ) {
			return reset( $rules );
		}

		// Find rule with longest lead time.
		$longest_rule = null;
		$longest_time = 0;

		foreach ( $rules as $category_id => $rule ) {
			$lead_time = isset( $rule['lead_time'] ) ? absint( $rule['lead_time'] ) : 0;

			if ( $lead_time > $longest_time ) {
				$longest_time = $lead_time;
				$longest_rule = $rule;
			}
		}

		return $longest_rule ? $longest_rule : reset( $rules );
	}

	/**
	 * Get global default settings.
	 *
	 * @return array Global settings array.
	 */
	protected function get_global_settings() {
		return array(
			'lead_time'        => absint( get_option( 'wc_collection_date_lead_time', 2 ) ),
			'lead_time_type'   => get_option( 'wc_collection_date_lead_time_type', 'calendar' ),
			'cutoff_time'      => get_option( 'wc_collection_date_cutoff_time', '' ),
			'working_days'     => get_option( 'wc_collection_date_working_days', array( '1', '2', '3', '4', '5', '6' ) ),
			'collection_days'  => get_option( 'wc_collection_date_collection_days', array( '0', '1', '2', '3', '4', '5', '6' ) ),
		);
	}

	/**
	 * Get category rule for a specific category.
	 *
	 * @param int $category_id Category ID.
	 * @return array|null Category rule or null if not found.
	 */
	public function get_category_rule( $category_id ) {
		$rules = $this->get_all_category_rules();

		return isset( $rules[ $category_id ] ) ? $rules[ $category_id ] : null;
	}

	/**
	 * Save category rule.
	 *
	 * @param int   $category_id Category ID.
	 * @param array $settings Settings array.
	 * @return bool True on success, false on failure.
	 */
	public function save_category_rule( $category_id, $settings ) {
		$rules = $this->get_all_category_rules();

		// Validate and sanitize settings.
		$sanitized = $this->sanitize_category_rule( $settings );

		if ( empty( $sanitized ) ) {
			return false;
		}

		$rules[ $category_id ] = $sanitized;

		return update_option( 'wc_collection_date_category_rules', $rules );
	}

	/**
	 * Delete category rule.
	 *
	 * @param int $category_id Category ID.
	 * @return bool True on success, false on failure.
	 */
	public function delete_category_rule( $category_id ) {
		$rules = $this->get_all_category_rules();

		if ( ! isset( $rules[ $category_id ] ) ) {
			return false;
		}

		unset( $rules[ $category_id ] );

		return update_option( 'wc_collection_date_category_rules', $rules );
	}

	/**
	 * Sanitize category rule settings.
	 *
	 * @param array $settings Raw settings array.
	 * @return array Sanitized settings array.
	 */
	protected function sanitize_category_rule( $settings ) {
		$sanitized = array();

		// Lead time.
		$sanitized['lead_time'] = isset( $settings['lead_time'] ) ? absint( $settings['lead_time'] ) : 2;

		// Lead time type.
		$valid_types = array( 'calendar', 'working' );
		$sanitized['lead_time_type'] = isset( $settings['lead_time_type'] ) && in_array( $settings['lead_time_type'], $valid_types, true )
			? $settings['lead_time_type']
			: 'calendar';

		// Cutoff time.
		$sanitized['cutoff_time'] = isset( $settings['cutoff_time'] ) && preg_match( '/^([01]?[0-9]|2[0-3]):[0-5][0-9]$/', $settings['cutoff_time'] )
			? $settings['cutoff_time']
			: '';

		// Working days.
		$sanitized['working_days'] = $this->sanitize_days_array(
			isset( $settings['working_days'] ) ? $settings['working_days'] : array()
		);

		// Collection days.
		$sanitized['collection_days'] = $this->sanitize_days_array(
			isset( $settings['collection_days'] ) ? $settings['collection_days'] : array()
		);

		return $sanitized;
	}

	/**
	 * Sanitize days array.
	 *
	 * @param array $days Raw days array.
	 * @return array Sanitized days array.
	 */
	protected function sanitize_days_array( $days ) {
		if ( ! is_array( $days ) ) {
			return array();
		}

		$valid_days = array( '0', '1', '2', '3', '4', '5', '6' );
		$sanitized  = array();

		foreach ( $days as $day ) {
			if ( in_array( (string) $day, $valid_days, true ) ) {
				$sanitized[] = (string) $day;
			}
		}

		return $sanitized;
	}

	/**
	 * Get settings source information for display.
	 *
	 * @param int $product_id Product ID.
	 * @return array Information about where settings came from.
	 */
	public function get_settings_source( $product_id ) {
		// Check product override.
		if ( $this->get_product_override( $product_id ) ) {
			return array(
				'source' => 'product',
				'label'  => __( 'Product Override', 'wc-collection-date' ),
			);
		}

		// Check category.
		$category_settings = $this->get_category_settings( $product_id );
		if ( $category_settings ) {
			$categories = $this->get_product_categories( $product_id );
			$rules = $this->get_all_category_rules();

			foreach ( $categories as $cat_id ) {
				if ( isset( $rules[ $cat_id ] ) ) {
					$category = get_term( $cat_id, 'product_cat' );
					return array(
						'source'      => 'category',
						'label'       => __( 'Category Rule', 'wc-collection-date' ),
						'category_id' => $cat_id,
						'category_name' => $category ? $category->name : __( 'Unknown', 'wc-collection-date' ),
					);
				}
			}
		}

		// Global fallback.
		return array(
			'source' => 'global',
			'label'  => __( 'Global Default', 'wc-collection-date' ),
		);
	}
}
