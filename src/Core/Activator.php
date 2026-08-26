<?php
/**
 * Fired during plugin activation.
 *
 * @package FlashSaleStockGuardWooCommerce\Core
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Core;

use FlashSaleStockGuardWooCommerce\Contracts\Activatable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Activator.
 *
 * Fired during plugin activation.
 */
class Activator implements Activatable {

	/**
	 * Execute activation tasks.
	 *
	 * @param Container $container Application container (already registered — register_all() has run).
	 * @return void
	 */
	public function activate( Container $container ): void {
		update_option( 'fssgw_version', FSSGW_VERSION );
		if ( ! wp_next_scheduled( 'fssgw_cron_event' ) ) {
			wp_schedule_event( time(), 'hourly', 'fssgw_cron_event' );
		}
		$container->get( \FlashSaleStockGuardWooCommerce\Database\Schema::class )->create_table();
	}
}
