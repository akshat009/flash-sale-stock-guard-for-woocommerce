<?php
/**
 * WooCommerce custom product type registration for Flash Sale Stock Guard for WooCommerce.
 *
 * @package FlashSaleStockGuardWooCommerce\Woo\Providers
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Woo\Providers;

use FlashSaleStockGuardWooCommerce\Contracts\Conditional;
use FlashSaleStockGuardWooCommerce\Contracts\Service_Provider;
use FlashSaleStockGuardWooCommerce\Core\Container;
use FlashSaleStockGuardWooCommerce\Woo\Products\Custom_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Product_Type_Provider.
 */
class Product_Type_Provider implements Service_Provider, Conditional {

	/**
	 * Only needed when WooCommerce is active.
	 *
	 * @return bool
	 */
	public function is_needed(): bool {
		return class_exists( 'WooCommerce' );
	}

	/**
	 * No bindings needed.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function register( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	}

	/**
	 * Register hooks.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function boot( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		add_filter( 'woocommerce_product_class', array( Custom_Product::class, 'filter_product_class' ), 10, 2 );
		add_filter( 'product_type_selector', array( Custom_Product::class, 'filter_product_type_selector' ) );
		add_action( 'woocommerce_single_product_summary', array( $this, 'custom_product_summary_note' ), 25 );
	}

	/**
	 * Render a custom note on the single product page.
	 *
	 * @return void
	 */
	public function custom_product_summary_note() {
		echo '<div class="' . esc_attr( 'flash-sale-stock-guard-for-woocommerce-woo-note' ) . '">' . esc_html__( 'Special Product Note', 'flash-sale-stock-guard-for-woocommerce' ) . '</div>';
	}
}
