<?php
/**
 * WooCommerce Blocks (Cart & Checkout) integration registration for Flash Sale Stock Guard for WooCommerce.
 *
 * @package FlashSaleStockGuardWooCommerce\Woo\Providers
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Woo\Providers;

use FlashSaleStockGuardWooCommerce\Contracts\Conditional;
use FlashSaleStockGuardWooCommerce\Contracts\Service_Provider;
use FlashSaleStockGuardWooCommerce\Core\Container;
use FlashSaleStockGuardWooCommerce\Woo\Blocks\Cart_Summary_Block;
use FlashSaleStockGuardWooCommerce\Woo\Blocks\Integration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Blocks_Provider.
 */
class Blocks_Provider implements Service_Provider, Conditional {

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
		add_action( 'init', array( Cart_Summary_Block::class, 'register' ) );
		add_action( 'woocommerce_blocks_loaded', array( $this, 'register_blocks_integration' ) );
	}

	/**
	 * Register the Cart/Checkout content Integration with WooCommerce Blocks'
	 * own registry (separate from anything hooked into the classic templates).
	 *
	 * @return void
	 */
	public function register_blocks_integration(): void {
		$register_integration = array( $this, 'register_integration' );
		add_action( 'woocommerce_blocks_cart_block_registration', $register_integration );
		add_action( 'woocommerce_blocks_checkout_block_registration', $register_integration );
	}

	/**
	 * Register the Integration with a WooCommerce Blocks registry.
	 *
	 * @param object $registry WooCommerce Blocks integration registry.
	 * @return void
	 */
	public function register_integration( $registry ): void {
		$registry->register( new Integration() );
	}
}
