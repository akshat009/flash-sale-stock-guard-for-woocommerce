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
use FlashSaleStockGuardWooCommerce\Database\Hold_Repository;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Scheduler.
 *
 * Housekeeping only. Availability is enforced at query time via
 * `expires_at > UTC_TIMESTAMP()`, so a missed or delayed run can never cause
 * overselling — it only leaves stale rows marked 'active' for longer. That
 * separation matters because WP-Cron fires on page visits and is unreliable
 * on low-traffic stores, which is exactly where nobody would notice.
 */
class Scheduler implements Service_Provider {

	/**
	 * Cron hook name.
	 *
	 * @var string
	 */
	public const HOOK = 'fssgw_cron_event';

	/**
	 * Custom schedule interval name.
	 *
	 * @var string
	 */
	public const INTERVAL = 'fssgw_five_minutes';

	/**
	 * Rows to expire per run, so a large backlog can't produce a long-running
	 * query on a busy store.
	 *
	 * @var int
	 */
	public const BATCH_SIZE = 100;

	/**
	 * Hold data access.
	 *
	 * @var Hold_Repository
	 */
	private Hold_Repository $repository;

	/**
	 * Constructor.
	 *
	 * @param Hold_Repository|null $repository Injected for testing.
	 */
	public function __construct( ?Hold_Repository $repository = null ) {
		$this->repository = $repository ?? new Hold_Repository();
	}

	/**
	 * No container bindings needed.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function register( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
	}

	/**
	 * Register cron hooks.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function boot( Container $container ): void { // phpcs:ignore Generic.CodeAnalysis.UnusedFunctionParameter.Found
		add_filter( 'cron_schedules', array( $this, 'add_schedule' ) ); // phpcs:ignore WordPress.WP.CronInterval.ChangeDetected, WordPress.WP.CronInterval.CronSchedulesInterval -- five-minute cleanup is intentional; holds lapse in minutes, and correctness never depends on this running.
		add_action( self::HOOK, array( $this, 'execute_cron_job' ) );
		add_action( 'init', array( $this, 'maybe_schedule' ) );
	}

	/**
	 * Add a five-minute interval. Hourly is too coarse for holds that lapse
	 * in fifteen minutes.
	 *
	 * @param array $schedules Existing schedules.
	 * @return array
	 */
	public function add_schedule( $schedules ): array {
		$schedules = is_array( $schedules ) ? $schedules : array();
		$schedules[ self::INTERVAL ] = array(
			'interval' => 5 * MINUTE_IN_SECONDS,
			'display'  => __( 'Every 5 Minutes (Stock Guard)', 'flash-sale-stock-guard-for-woocommerce' ),
		);

		return $schedules;
	}

	/**
	 * Schedule the sweep if it isn't already.
	 *
	 * Hooked to `init` rather than the activation hook: activation runs
	 * before `plugins_loaded`, so the custom interval isn't registered yet
	 * and wp_schedule_event() fails silently. Scheduling here also self-heals
	 * if the event is ever lost.
	 *
	 * @return void
	 */
	public function maybe_schedule(): void {
		if ( ! wp_next_scheduled( self::HOOK ) ) {
			wp_schedule_event( time(), self::INTERVAL, self::HOOK );
		}
	}

	/**
	 * Mark lapsed holds as expired.
	 *
	 * @return void
	 */
	public function execute_cron_job(): void {
		$expired = $this->repository->expire_batch( self::BATCH_SIZE );

		/**
		 * Fires after an expiry sweep, for logging or metrics.
		 *
		 * @param int $expired Number of holds expired this run.
		 */
		do_action( 'fssgw_holds_expired', $expired );
	}
}
