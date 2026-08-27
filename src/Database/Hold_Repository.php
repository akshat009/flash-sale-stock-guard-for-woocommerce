<?php
/**
 * Data access + concurrency-safe hold logic.
 *
 * @package FlashSaleStockGuardWooCommerce\Database
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Database;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Hold_Repository.
 *
 * Locking strategy: MySQL named locks (GET_LOCK / RELEASE_LOCK), scoped per
 * product+variation, NOT `SELECT ... FOR UPDATE`.
 *
 * Why: FOR UPDATE only locks rows that already exist. When two people fight
 * over a product's *first* hold — the common last-unit case during a drop —
 * there are zero rows to lock, so both requests pass the availability check.
 * Locking WooCommerce's own stock row instead would mean branching on HPOS vs
 * legacy postmeta storage, coupling this plugin to internals that change
 * between releases. A named advisory lock exists independently of any row,
 * behaves identically on both backends, and — since only one lock is ever
 * held at a time — cannot deadlock.
 *
 * Deliberately not a Service_Provider: this is a plain collaborator with no
 * hooks of its own, constructed by the providers that need it.
 */
class Hold_Repository {

	public const STATUS_ACTIVE    = 'active';
	public const STATUS_CONVERTED = 'converted';
	public const STATUS_RELEASED  = 'released';
	public const STATUS_EXPIRED   = 'expired';

	/**
	 * Seconds to wait for the advisory lock before giving up.
	 *
	 * @var int
	 */
	private const LOCK_TIMEOUT = 5;

	/**
	 * Attempt to hold stock.
	 *
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID, or 0.
	 * @param int    $quantity     Quantity to hold.
	 * @param string $session_id   Cart session key.
	 * @param int    $user_id      User ID, or 0 for guest.
	 * @param int    $ttl_seconds  Hold lifetime in seconds.
	 * @return int|\WP_Error Hold ID on success, WP_Error otherwise.
	 */
	public function reserve( int $product_id, int $variation_id, int $quantity, string $session_id, int $user_id, int $ttl_seconds ) {
		global $wpdb;

		$lock_name = $this->lock_name( $product_id, $variation_id );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- advisory lock, not a cacheable read.
		$got_lock = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT GET_LOCK(%s, %d)', $lock_name, self::LOCK_TIMEOUT ) );

		if ( 1 !== $got_lock ) {
			return new \WP_Error(
				'fssgw_lock_timeout',
				__( 'This item is being claimed by another customer right now — please try again.', 'flash-sale-stock-guard-for-woocommerce' )
			);
		}

		try {
			if ( $this->get_available_stock( $product_id, $variation_id ) < $quantity ) {
				return new \WP_Error(
					'fssgw_insufficient_stock',
					__( 'Not enough stock available to hold.', 'flash-sale-stock-guard-for-woocommerce' )
				);
			}

			$now = current_time( 'mysql', true );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- custom table, no core API.
			$inserted = $wpdb->insert(
				Schema::table_name(),
				array(
					'product_id'   => $product_id,
					'variation_id' => $variation_id,
					'session_id'   => $session_id,
					'user_id'      => $user_id,
					'quantity'     => $quantity,
					'status'       => self::STATUS_ACTIVE,
					'expires_at'   => gmdate( 'Y-m-d H:i:s', time() + $ttl_seconds ),
					'created_at'   => $now,
					'updated_at'   => $now,
				),
				array( '%d', '%d', '%s', '%d', '%d', '%s', '%s', '%s', '%s' )
			);

			if ( false === $inserted ) {
				return new \WP_Error(
					'fssgw_db_error',
					__( 'Could not create the hold.', 'flash-sale-stock-guard-for-woocommerce' )
				);
			}

			return (int) $wpdb->insert_id;
		} finally {
			// Released in a finally so an early return, a thrown exception,
			// or a fatal all still free the lock — otherwise one bad path
			// would leave a product locked for the rest of the connection.
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- releasing advisory lock.
			$wpdb->query( $wpdb->prepare( 'SELECT RELEASE_LOCK(%s)', $lock_name ) );
		}
	}

	/**
	 * Sum of active, non-expired held quantity for a product/variation.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID, or 0.
	 * @return int
	 */
	public function get_held_quantity( int $product_id, int $variation_id ): int {
		global $wpdb;
		$table = Schema::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- caching this read would reintroduce the race this plugin prevents; freshness is the correctness property here.
		$held = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name constant, not user input.
				"SELECT COALESCE(SUM(quantity), 0) FROM {$table} WHERE product_id = %d AND variation_id = %d AND status = %s AND expires_at > UTC_TIMESTAMP()",
				$product_id,
				$variation_id,
				self::STATUS_ACTIVE
			)
		);

		return (int) $held;
	}

	/**
	 * Real stock minus what's currently held by anyone.
	 *
	 * Returns PHP_INT_MAX for unmanaged stock so callers can compare
	 * numerically without special-casing.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID, or 0.
	 * @return int
	 */
	public function get_available_stock( int $product_id, int $variation_id ): int {
		$product = wc_get_product( $variation_id > 0 ? $variation_id : $product_id );

		if ( ! $product || ! $product->managing_stock() ) {
			return PHP_INT_MAX;
		}

		return (int) $product->get_stock_quantity() - $this->get_held_quantity( $product_id, $variation_id );
	}

	/**
	 * Whether an active hold covers at least $quantity for this identity.
	 *
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID, or 0.
	 * @param string $session_id   Cart session key.
	 * @param int    $user_id      User ID, or 0.
	 * @param int    $quantity     Quantity that must be covered.
	 * @return bool
	 */
	public function has_active_hold( int $product_id, int $variation_id, string $session_id, int $user_id, int $quantity ): bool {
		global $wpdb;
		$table = Schema::table_name();

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- freshness required for correctness.
		$sum = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name constant, not user input.
				"SELECT COALESCE(SUM(quantity), 0) FROM {$table} WHERE product_id = %d AND variation_id = %d AND session_id = %s AND user_id = %d AND status = %s AND expires_at > UTC_TIMESTAMP()",
				$product_id,
				$variation_id,
				$session_id,
				$user_id,
				self::STATUS_ACTIVE
			)
		);

		return (int) $sum >= $quantity;
	}

	/**
	 * Seconds left on the earliest-expiring active hold for this cart, or
	 * null if there isn't one. Powers the countdown.
	 *
	 * Matched on session ID alone, deliberately. WooCommerce filters
	 * `nonce_user_logged_out` once a cart exists, which invalidates the
	 * `wp_rest` nonce the page was rendered with — so the countdown request
	 * has to run without one, and arrives as user 0 even for a logged-in
	 * customer. The WooCommerce session ID identifies the cart on its own
	 * (it *is* the user ID when logged in), so nothing is lost.
	 *
	 * @param string $session_id Cart session key.
	 * @return int|null
	 */
	public function get_seconds_remaining( string $session_id ): ?int {
		global $wpdb;
		$table = Schema::table_name();

		if ( '' === $session_id ) {
			return null;
		}

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- live countdown must not be cached.
		$seconds = $wpdb->get_var(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name constant, not user input.
				"SELECT TIMESTAMPDIFF(SECOND, UTC_TIMESTAMP(), MIN(expires_at)) FROM {$table} WHERE session_id = %s AND status = %s AND expires_at > UTC_TIMESTAMP()",
				$session_id,
				self::STATUS_ACTIVE
			)
		);

		return null === $seconds ? null : max( 0, (int) $seconds );
	}

	/**
	 * Release active holds for a product + cart identity.
	 *
	 * @param int    $product_id   Product ID.
	 * @param int    $variation_id Variation ID, or 0.
	 * @param string $session_id   Cart session key.
	 * @param int    $user_id      User ID, or 0.
	 * @return void
	 */
	public function release( int $product_id, int $variation_id, string $session_id, int $user_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, no core API.
		$wpdb->update(
			Schema::table_name(),
			array(
				'status'     => self::STATUS_RELEASED,
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'session_id'   => $session_id,
				'user_id'      => $user_id,
				'status'       => self::STATUS_ACTIVE,
			),
			array( '%s', '%s' ),
			array( '%d', '%d', '%s', '%d', '%s' )
		);
	}

	/**
	 * Convert active holds into an order-backed hold that stops expiring.
	 *
	 * @param string $session_id Cart session key.
	 * @param int    $user_id    User ID, or 0.
	 * @param int    $order_id   Order ID.
	 * @return void
	 */
	public function convert_to_order( string $session_id, int $user_id, int $order_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, no core API.
		$wpdb->update(
			Schema::table_name(),
			array(
				'status'     => self::STATUS_CONVERTED,
				'order_id'   => $order_id,
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'session_id' => $session_id,
				'user_id'    => $user_id,
				'status'     => self::STATUS_ACTIVE,
			),
			array( '%s', '%d', '%s' ),
			array( '%s', '%d', '%s' )
		);
	}

	/**
	 * Release holds tied to an order that failed, was cancelled, or refunded.
	 * Without this a failed payment would hold stock indefinitely, since a
	 * converted hold no longer expires.
	 *
	 * @param int $order_id Order ID.
	 * @return void
	 */
	public function release_by_order( int $order_id ): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, no core API.
		$wpdb->update(
			Schema::table_name(),
			array(
				'status'     => self::STATUS_RELEASED,
				'updated_at' => current_time( 'mysql', true ),
			),
			array(
				'order_id' => $order_id,
				'status'   => self::STATUS_CONVERTED,
			),
			array( '%s', '%s' ),
			array( '%d', '%s' )
		);
	}

	/**
	 * Mark lapsed holds as expired, in batches.
	 *
	 * Housekeeping only — availability is already enforced at query time by
	 * `expires_at > UTC_TIMESTAMP()`, so a missed sweep can never cause
	 * overselling, only untidy rows.
	 *
	 * @param int $limit Max rows per run.
	 * @return int Rows affected.
	 */
	public function expire_batch( int $limit = 100 ): int {
		global $wpdb;
		$table = Schema::table_name();
		$now   = current_time( 'mysql', true );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- custom table, no core API.
		$updated = $wpdb->query(
			$wpdb->prepare(
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name constant, not user input.
				"UPDATE {$table} SET status = %s, updated_at = %s WHERE status = %s AND expires_at <= %s LIMIT %d",
				self::STATUS_EXPIRED,
				$now,
				self::STATUS_ACTIVE,
				$now,
				$limit
			)
		);

		return (int) $updated;
	}

	/**
	 * Named-lock key for a product/variation pair. Scoped per product so two
	 * customers buying different items never wait on each other.
	 *
	 * @param int $product_id   Product ID.
	 * @param int $variation_id Variation ID, or 0.
	 * @return string
	 */
	private function lock_name( int $product_id, int $variation_id ): string {
		return 'fssgw_lock_' . $product_id . '_' . $variation_id;
	}
}
