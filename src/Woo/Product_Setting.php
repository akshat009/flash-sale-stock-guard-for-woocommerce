<?php
/**
 * Per-product opt-in for stock guarding.
 *
 * @package FlashSaleStockGuardWooCommerce\Woo
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Woo;

use FlashSaleStockGuardWooCommerce\Admin\Settings_Repository;
use FlashSaleStockGuardWooCommerce\Contracts\Service_Provider;
use FlashSaleStockGuardWooCommerce\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Product_Setting.
 *
 * Adds a checkbox to the product (and variation) Inventory tab, and decides
 * whether any given product is guarded.
 *
 * The store-wide "apply to" mode does the bulk of the work — asking an owner
 * to tick fifty items before a drop is work the plugin can do itself. The
 * per-product checkbox is an override on top: ticking it always guards that
 * product, whatever the store-wide mode says.
 */
class Product_Setting implements Service_Provider {

	/**
	 * Post meta key holding the per-product opt-in flag.
	 *
	 * @var string
	 */
	public const META_KEY = '_fssgw_guard_enabled';

	/**
	 * Whether a product (or variation) is guarded.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID, or 0.
	 * @return bool
	 */
	public static function is_guarded( int $product_id, int $variation_id = 0 ): bool {
		$guarded = self::is_marked( $product_id, $variation_id ) || self::matches_store_mode( $product_id, $variation_id );

		/**
		 * Filter whether a product is guarded.
		 *
		 * The escape hatch for stores that want this decided programmatically
		 * — by category, by campaign, by anything the settings don't cover.
		 *
		 * @param bool $guarded      Whether the product is guarded.
		 * @param int  $product_id   Product ID.
		 * @param int  $variation_id Variation ID, or 0.
		 */
		return (bool) apply_filters( 'fssgw_is_product_guarded', $guarded, $product_id, $variation_id );
	}

	/**
	 * Whether the product was ticked individually.
	 *
	 * Variations inherit from the parent unless they set their own value, so
	 * one tick can cover a whole variable product.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID, or 0.
	 * @return bool
	 */
	private static function is_marked( int $product_id, int $variation_id ): bool {
		if ( $variation_id > 0 ) {
			$own = get_post_meta( $variation_id, self::META_KEY, true );

			if ( '' !== $own ) {
				return 'yes' === $own;
			}
		}

		return 'yes' === get_post_meta( $product_id, self::META_KEY, true );
	}

	/**
	 * Whether the store-wide mode covers this product.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID, or 0.
	 * @return bool
	 */
	private static function matches_store_mode( int $product_id, int $variation_id ): bool {
		$mode = (string) get_option( Settings_Repository::OPT_APPLY_TO, Settings_Repository::APPLY_LOW_STOCK );

		if ( Settings_Repository::APPLY_MARKED === $mode ) {
			return false;
		}

		$product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );

		if ( ! $product || ! $product->managing_stock() ) {
			return false;
		}

		if ( Settings_Repository::APPLY_ALL === $mode ) {
			return true;
		}

		$threshold = max( 1, (int) get_option( Settings_Repository::OPT_LOW_STOCK, Settings_Repository::DEFAULT_LOW_STOCK ) );
		$stock     = (int) $product->get_stock_quantity();

		// Compared against real stock, not availability-minus-holds: using the
		// latter would make a product cross the threshold *because* it was
		// guarded, and stop being guarded again the moment holds lapsed.
		return $stock > 0 && $stock <= $threshold;
	}

	/**
	 * No container bindings needed.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function register( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	}

	/**
	 * Register admin hooks.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function boot( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		add_action( 'woocommerce_product_options_inventory_product_data', array( $this, 'render_product_field' ) );
		add_action( 'woocommerce_process_product_meta', array( $this, 'save_product_field' ) );

		add_action( 'woocommerce_variation_options_inventory', array( $this, 'render_variation_field' ), 10, 3 );
		add_action( 'woocommerce_save_product_variation', array( $this, 'save_variation_field' ), 10, 2 );
	}

	/**
	 * Checkbox on the product Inventory tab.
	 *
	 * @return void
	 */
	public function render_product_field(): void {
		woocommerce_wp_checkbox(
			array(
				'id'          => self::META_KEY,
				'label'       => __( 'Always guard this item', 'flash-sale-stock-guard-for-woocommerce' ),
				'description' => __( 'Hold this item at cart stage regardless of the store-wide setting. Use it on one-of-a-kind pieces and drops you want protected from the start.', 'flash-sale-stock-guard-for-woocommerce' ),
				'desc_tip'    => false,
			)
		);
	}

	/**
	 * Save the product-level checkbox.
	 *
	 * @param int $post_id Product ID.
	 * @return void
	 */
	public function save_product_field( $post_id ): void {
		// Nonce is verified by WooCommerce before this hook fires.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing
		$value = isset( $_POST[ self::META_KEY ] ) ? 'yes' : 'no';

		update_post_meta( (int) $post_id, self::META_KEY, $value );
	}

	/**
	 * Checkbox on each variation's Inventory section.
	 *
	 * @param int      $loop           Variation loop index.
	 * @param array    $variation_data Variation data.
	 * @param \WP_Post $variation      Variation post object.
	 * @return void
	 */
	public function render_variation_field( $loop, $variation_data, $variation ): void {
		unset( $variation_data );

		$current = get_post_meta( $variation->ID, self::META_KEY, true );

		if ( '' === $current ) {
			$current = 'yes' === get_post_meta( $variation->post_parent, self::META_KEY, true ) ? 'yes' : 'no';
		}

		woocommerce_wp_checkbox(
			array(
				'id'            => self::META_KEY . '[' . $loop . ']',
				'name'          => self::META_KEY . '[' . $loop . ']',
				'value'         => $current,
				'label'         => __( 'Always guard this variation', 'flash-sale-stock-guard-for-woocommerce' ),
				'desc_tip'      => false,
				'wrapper_class' => 'form-row form-row-full',
			)
		);
	}

	/**
	 * Save a variation's checkbox.
	 *
	 * @param int $variation_id Variation ID.
	 * @param int $loop         Variation loop index.
	 * @return void
	 */
	public function save_variation_field( $variation_id, $loop ): void {
		// Nonce is verified by WooCommerce before this hook fires. The posted
		// value itself is never used beyond an isset() presence check below,
		// so there's nothing to sanitize.
		// phpcs:ignore WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$posted = isset( $_POST[ self::META_KEY ] ) && is_array( $_POST[ self::META_KEY ] ) ? wp_unslash( $_POST[ self::META_KEY ] ) : array();

		$value = isset( $posted[ $loop ] ) ? 'yes' : 'no';

		update_post_meta( (int) $variation_id, self::META_KEY, $value );
	}
}
