<?php
/**
 * Uninstall cleanup service.
 *
 * @package FlashSaleStockGuardWooCommerce\Core
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Core;

use FlashSaleStockGuardWooCommerce\Admin\Settings_Repository;
use FlashSaleStockGuardWooCommerce\Database\Schema;
use FlashSaleStockGuardWooCommerce\Woo\Product_Setting;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Uninstaller.
 *
 * OOP wrapper for uninstall.php's cleanup logic — constructed and invoked
 * once per site from uninstall.php's multisite loop.
 *
 * Settings and the scheduled sweep always go, since they're meaningless
 * without the plugin. The holds table and the per-product opt-in flags are
 * only destroyed if the store owner ticked "Delete data on uninstall":
 * deleting a plugin shouldn't silently discard data someone may want back,
 * and a store that removes this plugin to debug something would otherwise
 * lose every product's guard setting.
 */
class Uninstaller {

	/**
	 * Perform uninstall cleanup tasks for the current site.
	 *
	 * @return void
	 */
	public function cleanup(): void {
		$delete_data = (bool) get_option( Settings_Repository::OPT_DELETE_ON_UNINSTALL, false );

		wp_clear_scheduled_hook( 'fssgw_cron_event' );

		delete_option( 'fssgw_version' );
		delete_option( Settings_Repository::OPT_ENABLED );
		delete_option( Settings_Repository::OPT_TTL );
		delete_option( Settings_Repository::OPT_SHOW_TIMER );
		delete_option( Settings_Repository::OPT_DELETE_ON_UNINSTALL );

		if ( ! $delete_data ) {
			return;
		}

		Schema::drop_table();

		delete_post_meta_by_key( Product_Setting::META_KEY );
	}
}
