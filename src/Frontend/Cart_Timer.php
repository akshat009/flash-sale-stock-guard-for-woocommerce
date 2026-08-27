<?php
/**
 * Cart countdown output.
 *
 * @package FlashSaleStockGuardWooCommerce\Frontend
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Frontend;

use FlashSaleStockGuardWooCommerce\Admin\Settings_Repository;
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

		$settings = new Settings_Repository();

		wp_localize_script(
			'fssgw-cart-timer',
			'fssgwCartTimer',
			array(
				'endpoint' => rest_url( 'fssgw/v1/hold-status' ),
				'shopUrl'  => $this->shop_url(),
				'modal'    => array(
					'background'  => $settings->get_expiry_bg_color(),
					'textColor'   => $settings->get_expiry_text_color(),
					'buttonBg'    => $settings->get_expiry_button_bg(),
					'buttonColor' => $settings->get_expiry_button_color(),
					'fontSize'    => $settings->get_expiry_font_size(),
				),
				'i18n'     => array(
					/* translators: %s: countdown, e.g. 14:32. */
					'held'         => __( 'Your items are held for %s', 'flash-sale-stock-guard-for-woocommerce' ),
					'expiredTitle' => __( 'Your reservation has expired', 'flash-sale-stock-guard-for-woocommerce' ),
					'expiredBody'  => __( 'The items you were holding have been released for other shoppers. Please start again.', 'flash-sale-stock-guard-for-woocommerce' ),
					'expiredCta'   => __( 'Back to shop', 'flash-sale-stock-guard-for-woocommerce' ),
				),
			)
		);
	}

	/**
	 * Where to send a customer whose hold has lapsed: the shop page, or the
	 * site root if the store hasn't set one.
	 *
	 * @return string
	 */
	private function shop_url(): string {
		$shop = wc_get_page_permalink( 'shop' );

		return $shop ? $shop : home_url( '/' );
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
