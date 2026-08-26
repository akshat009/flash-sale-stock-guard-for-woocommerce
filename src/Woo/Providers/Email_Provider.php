<?php
/**
 * WooCommerce custom order email registration for Flash Sale Stock Guard for WooCommerce.
 *
 * @package FlashSaleStockGuardWooCommerce\Woo\Providers
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Woo\Providers;

use FlashSaleStockGuardWooCommerce\Contracts\Conditional;
use FlashSaleStockGuardWooCommerce\Contracts\Service_Provider;
use FlashSaleStockGuardWooCommerce\Core\Container;
use FlashSaleStockGuardWooCommerce\Woo\Emails\Custom_Email;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Email_Provider.
 */
class Email_Provider implements Service_Provider, Conditional {

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
		add_filter( 'woocommerce_email_classes', array( $this, 'register_email' ) );
	}

	/**
	 * Register the custom order email with WooCommerce.
	 *
	 * @param array $emails Existing email classes (already-constructed instances).
	 * @return array
	 */
	public function register_email( $emails ) {
		$emails['fssgw_custom_email'] = new Custom_Email();
		return $emails;
	}
}
