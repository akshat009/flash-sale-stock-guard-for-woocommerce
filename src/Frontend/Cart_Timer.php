<?php
/**
 * Cart countdown output.
 *
 * @package FlashSaleStockGuardWooCommerce\Frontend
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Frontend;

use FlashSaleStockGuardWooCommerce\Contracts\Service_Provider;
use FlashSaleStockGuardWooCommerce\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Cart_Timer.
 *
 * Renders a container via WooCommerce action hooks where it can, and lets the
 * script create its own where it can't — block-based Cart and Checkout don't
 * fire the legacy cart actions, so a PHP-only approach silently does nothing
 * there.
 */
class Cart_Timer implements Service_Provider {

	/**
	 * No container bindings needed.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function register( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	}

	/**
	 * Register frontend hooks.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function boot( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'woocommerce_before_cart', array( $this, 'render_container' ) );
		add_action( 'woocommerce_before_checkout_form', array( $this, 'render_container' ) );
	}

	/**
	 * Enqueue the countdown script.
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		wp_enqueue_script(
			'fssgw-cart-timer',
			FSSGW_URL . 'assets/js/cart-timer.js',
			array(),
			FSSGW_VERSION,
			true
		);

		wp_localize_script(
			'fssgw-cart-timer',
			'fssgwCartTimer',
			array(
				'endpoint' => rest_url( 'fssgw/v1/hold-status' ),
				'i18n'     => array(
					/* translators: %s: countdown, e.g. 14:32. */
					'held'    => __( 'Your items are held for %s', 'flash-sale-stock-guard-for-woocommerce' ),
					'expired' => __( 'Your hold has expired. Please check your cart before continuing.', 'flash-sale-stock-guard-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Output the container on classic cart/checkout.
	 *
	 * @return void
	 */
	public function render_container(): void {
		if ( ! $this->is_active() ) {
			return;
		}

		echo '<div class="woocommerce-info fssgw-cart-timer" data-fssgw-timer hidden></div>';
	}

	/**
	 * Whether the countdown should run on this request.
	 *
	 * Both is_cart() and is_checkout() cover the classic shortcode pages as
	 * well as block-based Cart/Checkout, since both are identified by the
	 * WooCommerce page IDs.
	 *
	 * @return bool
	 */
	private function is_active(): bool {
		if ( ! get_option( 'fssgw_enabled', true ) ) {
			return false;
		}

		if ( ! get_option( 'fssgw_show_cart_timer', true ) ) {
			return false;
		}

		if ( ! function_exists( 'is_cart' ) ) {
			return false;
		}

		return is_cart() || is_checkout();
	}
}
