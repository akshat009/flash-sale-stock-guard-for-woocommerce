<?php
/**
 * WP-Cron Task Scheduler.
 *
 * @package FlashSaleStockGuardWooCommerce\Cron
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Cron;

use FlashSaleStockGuardWooCommerce\Contracts\Service_Provider;
use FlashSaleStockGuardWooCommerce\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Scheduler.
 */
class Scheduler implements Service_Provider {

	/**
	 * No bindings needed.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function register( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	}

	/**
	 * Register cron event actions.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function boot( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		add_action( 'fssgw_cron_event', array( $this, 'execute_cron_job' ) );
	}

	/**
	 * Execute cron job logic.
	 *
	 * @return void
	 */
	public function execute_cron_job(): void {
		// Cron task execution logic.
	}
}
