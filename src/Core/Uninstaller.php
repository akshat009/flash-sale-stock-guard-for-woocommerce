<?php
/**
 * Uninstall cleanup service.
 *
 * @package FlashSaleStockGuardWooCommerce\Core
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Uninstaller.
 *
 * OOP wrapper for uninstall.php's cleanup logic — constructed and invoked
 * once per site from uninstall.php's multisite loop.
 */
class Uninstaller {

	/**
	 * Perform uninstall cleanup tasks for the current site.
	 *
	 * @return void
	 */
	public function cleanup(): void {
		delete_option( 'fssgw_version' );
		wp_clear_scheduled_hook( 'fssgw_cron_event' );
		delete_option( 'fssgw_option_name' );
		\FlashSaleStockGuardWooCommerce\Database\Schema::drop_table();
		delete_transient( 'fssgw_elementor_widgets' );
	}
}
