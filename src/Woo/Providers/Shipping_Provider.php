<?php
/**
 * WooCommerce shipping method registration for Flash Sale Stock Guard for WooCommerce.
 *
 * @package FlashSaleStockGuardWooCommerce\Woo\Providers
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Woo\Providers;

use FlashSaleStockGuardWooCommerce\Contracts\Conditional;
use FlashSaleStockGuardWooCommerce\Contracts\Service_Provider;
use FlashSaleStockGuardWooCommerce\Core\Container;
use FlashSaleStockGuardWooCommerce\Woo\Shipping\Shipping_Method;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Shipping_Provider.
 */
class Shipping_Provider implements Service_Provider, Conditional {

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
		add_filter( 'woocommerce_shipping_methods', array( $this, 'register_shipping_method' ) );
	}

	/**
	 * Register the custom shipping method with WooCommerce.
	 *
	 * @param array $methods Existing shipping methods.
	 * @return array
	 */
	public function register_shipping_method( $methods ) {
		$methods['fssgw_shipping'] = Shipping_Method::class;
		return $methods;
	}
}
