<?php
/**
 * Custom database table schema + migrations.
 *
 * @package FlashSaleStockGuardWooCommerce\Database
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Database;

use FlashSaleStockGuardWooCommerce\Contracts\Service_Provider;
use FlashSaleStockGuardWooCommerce\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Schema.
 *
 * Owns the holds table. dbDelta() creates or upgrades it — called
 * synchronously on activation (Activator resolves this from the container),
 * and again on every request via maybe_upgrade() so a plugin *update*
 * (which doesn't fire register_activation_hook()) still gets migrated.
 * dbDelta() is idempotent: re-running it against an up-to-date table is a
 * cheap no-op, it only ever adds/alters, and get_option() short-circuits
 * maybe_upgrade() once VERSION_OPTION already matches VERSION.
 *
 * Holds live here rather than in post meta for three reasons: row-level
 * locking on wp_postmeta would lock rows in the site's busiest shared table;
 * expiry can't be indexed inside serialized meta values; and concurrent
 * writes to a serialized array of holds silently lose entries — the exact
 * bug this plugin exists to prevent.
 */
class Schema implements Service_Provider {

	/**
	 * Bump this whenever create_table()'s SQL changes — dbDelta() diffs
	 * against the live table and adds/alters columns and indexes without
	 * dropping existing data.
	 *
	 * @var string
	 */
	public const VERSION = '1.0.0';

	/**
	 * Option name storing the schema version currently applied to the database.
	 *
	 * @var string
	 */
	private const VERSION_OPTION = 'fssgw_db_version';

	/**
	 * Get the holds table's fully-qualified name (respects multisite table prefixing).
	 *
	 * @return string
	 */
	public static function table_name(): string {
		global $wpdb;
		return $wpdb->prefix . 'fssgw_holds';
	}

	/**
	 * Bind this instance so Activator can resolve it to run create_table()
	 * once, synchronously, on activation.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->instance( self::class, $this );
	}

	/**
	 * Check for pending migrations on every request.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function boot( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		add_action( 'plugins_loaded', array( $this, 'maybe_upgrade' ) );
	}

	/**
	 * Run create_table() again if the stored schema version is behind VERSION.
	 *
	 * @return void
	 */
	public function maybe_upgrade(): void {
		if ( get_option( self::VERSION_OPTION ) === self::VERSION ) {
			return;
		}

		$this->create_table();
	}

	/**
	 * Create (or, via dbDelta()'s diffing, migrate) the holds table.
	 *
	 * Indexes: (product_id, variation_id, status) covers the availability
	 * lookup that runs on every add-to-cart; expires_at covers the cron
	 * sweep; session_id and user_id cover the per-customer countdown query.
	 *
	 * @return void
	 */
	public function create_table(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$table_name      = self::table_name();

		// dbDelta() is picky about formatting: two spaces after "PRIMARY KEY",
		// each field/index on its own line, no trailing comma on the last one.
		$sql = "CREATE TABLE {$table_name} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id BIGINT UNSIGNED NOT NULL,
			variation_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			session_id VARCHAR(64) NOT NULL DEFAULT '',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			quantity INT UNSIGNED NOT NULL DEFAULT 1,
			status VARCHAR(20) NOT NULL DEFAULT 'active',
			order_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			expires_at DATETIME NOT NULL,
			created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY  (id),
			KEY product_status (product_id, variation_id, status),
			KEY expires_at (expires_at),
			KEY session_id (session_id),
			KEY user_id (user_id)
		) {$charset_collate};";

		dbDelta( $sql );

		update_option( self::VERSION_OPTION, self::VERSION );
	}

	/**
	 * Drop the holds table. Called from uninstall.php.
	 *
	 * @return void
	 */
	public static function drop_table(): void {
		global $wpdb;
		$table_name = self::table_name();
		$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- table name isn't user input; DROP TABLE doesn't support placeholders.
		delete_option( self::VERSION_OPTION );
	}
}
