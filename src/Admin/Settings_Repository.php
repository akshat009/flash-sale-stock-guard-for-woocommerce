<?php
/**
 * Admin Settings Data Repository.
 *
 * @package FlashSaleStockGuardWooCommerce\Admin
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings_Repository.
 *
 * Pure data access for the plugin's store-wide settings — no WordPress hooks
 * are registered here, only get_option()/register_setting().
 */
class Settings_Repository {

	/**
	 * Settings API option group.
	 *
	 * @var string
	 */
	private const OPTIONS_GROUP = 'fssgw_options_group';

	/**
	 * Master switch.
	 *
	 * @var string
	 */
	public const OPT_ENABLED = 'fssgw_enabled';

	/**
	 * Which products the guard applies to.
	 *
	 * @var string
	 */
	public const OPT_APPLY_TO = 'fssgw_apply_to';

	/**
	 * Stock level at or below which a product counts as scarce.
	 *
	 * @var string
	 */
	public const OPT_LOW_STOCK = 'fssgw_low_stock_threshold';

	/**
	 * Hold lifetime, stored in seconds.
	 *
	 * @var string
	 */
	public const OPT_TTL = 'fssgw_hold_ttl';

	/**
	 * Whether customers see the countdown.
	 *
	 * @var string
	 */
	public const OPT_SHOW_TIMER = 'fssgw_show_cart_timer';

	/**
	 * Whether uninstall drops the holds table.
	 *
	 * @var string
	 */
	public const OPT_DELETE_ON_UNINSTALL = 'fssgw_delete_data_on_uninstall';

	/**
	 * Guard only products ticked in their Inventory tab.
	 *
	 * @var string
	 */
	public const APPLY_MARKED = 'marked';

	/**
	 * Guard every product that manages stock.
	 *
	 * @var string
	 */
	public const APPLY_ALL = 'all';

	/**
	 * Guard products whose available stock is at or below the threshold.
	 *
	 * @var string
	 */
	public const APPLY_LOW_STOCK = 'low_stock';

	/**
	 * Default hold lifetime in seconds.
	 *
	 * @var int
	 */
	public const DEFAULT_TTL = 900;

	/**
	 * Default scarcity threshold, in units.
	 *
	 * @var int
	 */
	public const DEFAULT_LOW_STOCK = 5;

	/**
	 * Maximum hold, in minutes (24 hours).
	 *
	 * @var int
	 */
	private const MAX_MINUTES = 1440;

	/**
	 * Get the Settings API option group these options belong to.
	 *
	 * @return string
	 */
	public function get_options_group(): string {
		return self::OPTIONS_GROUP;
	}

	/**
	 * Whether guarding is switched on store-wide.
	 *
	 * @return bool
	 */
	public function is_enabled(): bool {
		return (bool) get_option( self::OPT_ENABLED, true );
	}

	/**
	 * Which products the guard applies to.
	 *
	 * Defaults to low-stock rather than marked-only: the products this plugin
	 * exists for are the scarce ones, and asking an owner to tick fifty items
	 * before a drop is work the plugin can do itself.
	 *
	 * @return string
	 */
	public function get_apply_to(): string {
		$value = (string) get_option( self::OPT_APPLY_TO, self::APPLY_LOW_STOCK );

		return in_array( $value, $this->get_apply_modes(), true ) ? $value : self::APPLY_LOW_STOCK;
	}

	/**
	 * Valid values for the apply-to mode.
	 *
	 * @return array<int, string>
	 */
	public function get_apply_modes(): array {
		return array( self::APPLY_MARKED, self::APPLY_ALL, self::APPLY_LOW_STOCK );
	}

	/**
	 * Stock level at or below which a product counts as scarce.
	 *
	 * @return int
	 */
	public function get_low_stock_threshold(): int {
		return max( 1, (int) get_option( self::OPT_LOW_STOCK, self::DEFAULT_LOW_STOCK ) );
	}

	/**
	 * Hold lifetime in seconds.
	 *
	 * @return int
	 */
	public function get_ttl_seconds(): int {
		return (int) get_option( self::OPT_TTL, self::DEFAULT_TTL );
	}

	/**
	 * Hold lifetime in whole minutes, for the settings field.
	 *
	 * @return int
	 */
	public function get_ttl_minutes(): int {
		return max( 1, (int) round( $this->get_ttl_seconds() / MINUTE_IN_SECONDS ) );
	}

	/**
	 * Whether the countdown is shown to customers.
	 *
	 * @return bool
	 */
	public function shows_timer(): bool {
		return (bool) get_option( self::OPT_SHOW_TIMER, true );
	}

	/**
	 * Whether uninstall should drop the holds table.
	 *
	 * @return bool
	 */
	public function deletes_data_on_uninstall(): bool {
		return (bool) get_option( self::OPT_DELETE_ON_UNINSTALL, false );
	}

	/**
	 * Register every option with the Settings API.
	 *
	 * @return void
	 */
	public function register_setting(): void {
		register_setting(
			self::OPTIONS_GROUP,
			self::OPT_ENABLED,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			self::OPTIONS_GROUP,
			self::OPT_APPLY_TO,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_apply_to' ),
				'default'           => self::APPLY_LOW_STOCK,
			)
		);

		register_setting(
			self::OPTIONS_GROUP,
			self::OPT_LOW_STOCK,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_low_stock' ),
				'default'           => self::DEFAULT_LOW_STOCK,
			)
		);

		register_setting(
			self::OPTIONS_GROUP,
			self::OPT_TTL,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_ttl' ),
				'default'           => self::DEFAULT_TTL,
			)
		);

		register_setting(
			self::OPTIONS_GROUP,
			self::OPT_SHOW_TIMER,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => true,
			)
		);

		register_setting(
			self::OPTIONS_GROUP,
			self::OPT_DELETE_ON_UNINSTALL,
			array(
				'type'              => 'boolean',
				'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
				'default'           => false,
			)
		);
	}

	/**
	 * Checkbox sanitiser.
	 *
	 * @param mixed $value Raw value.
	 * @return bool
	 */
	public function sanitize_checkbox( $value ): bool {
		return (bool) $value;
	}

	/**
	 * Apply-to mode sanitiser — anything unrecognised falls back to the
	 * default rather than silently disabling the guard.
	 *
	 * @param mixed $value Raw value.
	 * @return string
	 */
	public function sanitize_apply_to( $value ): string {
		$value = sanitize_key( (string) $value );

		return in_array( $value, $this->get_apply_modes(), true ) ? $value : self::APPLY_LOW_STOCK;
	}

	/**
	 * Scarcity threshold sanitiser.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_low_stock( $value ): int {
		return max( 1, min( 9999, (int) $value ) );
	}

	/**
	 * Minutes in, seconds out.
	 *
	 * Idempotent on purpose: WordPress can invoke a sanitize callback more
	 * than once per request, and a naive minutes-to-seconds multiply would
	 * then run twice, turning 15 minutes into 900. Anything above the
	 * 1440-minute ceiling is therefore treated as already-seconds and clamped
	 * rather than multiplied again.
	 *
	 * @param mixed $value Raw value from the form.
	 * @return int Seconds.
	 */
	public function sanitize_ttl( $value ): int {
		$number = (int) $value;

		if ( $number > self::MAX_MINUTES ) {
			return max( MINUTE_IN_SECONDS, min( self::MAX_MINUTES * MINUTE_IN_SECONDS, $number ) );
		}

		return max( 1, min( self::MAX_MINUTES, $number ) ) * MINUTE_IN_SECONDS;
	}
}
