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
	 * Expiry popup: button background colour, #rrggbb.
	 *
	 * @var string
	 */
	public const OPT_EXPIRY_BUTTON_BG = 'fssgw_expiry_button_bg';

	/**
	 * Expiry popup: button text colour, #rrggbb.
	 *
	 * @var string
	 */
	public const OPT_EXPIRY_BUTTON_COLOR = 'fssgw_expiry_button_color';

	/**
	 * Expiry popup: base text size in pixels.
	 *
	 * @var string
	 */
	public const OPT_EXPIRY_FONT_SIZE = 'fssgw_expiry_font_size';

	/**
	 * Expiry popup: card background colour, #rrggbb.
	 *
	 * @var string
	 */
	public const OPT_EXPIRY_BG_COLOR = 'fssgw_expiry_bg_color';

	/**
	 * Expiry popup: card text colour, #rrggbb.
	 *
	 * @var string
	 */
	public const OPT_EXPIRY_TEXT_COLOR = 'fssgw_expiry_text_color';

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
	 * Default expiry popup button background colour.
	 *
	 * @var string
	 */
	public const DEFAULT_EXPIRY_BUTTON_BG = '#720eec';

	/**
	 * Default expiry popup button text colour.
	 *
	 * @var string
	 */
	public const DEFAULT_EXPIRY_BUTTON_COLOR = '#ffffff';

	/**
	 * Default expiry popup card background colour.
	 *
	 * @var string
	 */
	public const DEFAULT_EXPIRY_BG_COLOR = '#ffffff';

	/**
	 * Default expiry popup card text colour.
	 *
	 * @var string
	 */
	public const DEFAULT_EXPIRY_TEXT_COLOR = '#1e1e1e';

	/**
	 * Default expiry popup text size, in pixels.
	 *
	 * @var int
	 */
	public const DEFAULT_EXPIRY_FONT_SIZE = 16;

	/**
	 * Smallest allowed popup text size, in pixels.
	 *
	 * @var int
	 */
	public const MIN_EXPIRY_FONT_SIZE = 10;

	/**
	 * Largest allowed popup text size, in pixels.
	 *
	 * @var int
	 */
	public const MAX_EXPIRY_FONT_SIZE = 32;

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
	 * Expiry popup button background colour, #rrggbb.
	 *
	 * @return string
	 */
	public function get_expiry_button_bg(): string {
		$value = (string) get_option( self::OPT_EXPIRY_BUTTON_BG, self::DEFAULT_EXPIRY_BUTTON_BG );

		return $this->is_hex_color( $value ) ? $value : self::DEFAULT_EXPIRY_BUTTON_BG;
	}

	/**
	 * Expiry popup button text colour, #rrggbb.
	 *
	 * @return string
	 */
	public function get_expiry_button_color(): string {
		$value = (string) get_option( self::OPT_EXPIRY_BUTTON_COLOR, self::DEFAULT_EXPIRY_BUTTON_COLOR );

		return $this->is_hex_color( $value ) ? $value : self::DEFAULT_EXPIRY_BUTTON_COLOR;
	}

	/**
	 * Expiry popup base text size in pixels, clamped to the allowed range.
	 *
	 * @return int
	 */
	public function get_expiry_font_size(): int {
		$value = (int) get_option( self::OPT_EXPIRY_FONT_SIZE, self::DEFAULT_EXPIRY_FONT_SIZE );

		return max( self::MIN_EXPIRY_FONT_SIZE, min( self::MAX_EXPIRY_FONT_SIZE, $value ) );
	}

	/**
	 * Expiry popup card background colour, #rrggbb.
	 *
	 * @return string
	 */
	public function get_expiry_bg_color(): string {
		$value = (string) get_option( self::OPT_EXPIRY_BG_COLOR, self::DEFAULT_EXPIRY_BG_COLOR );

		return $this->is_hex_color( $value ) ? $value : self::DEFAULT_EXPIRY_BG_COLOR;
	}

	/**
	 * Expiry popup card text colour, #rrggbb.
	 *
	 * @return string
	 */
	public function get_expiry_text_color(): string {
		$value = (string) get_option( self::OPT_EXPIRY_TEXT_COLOR, self::DEFAULT_EXPIRY_TEXT_COLOR );

		return $this->is_hex_color( $value ) ? $value : self::DEFAULT_EXPIRY_TEXT_COLOR;
	}

	/**
	 * Whether a string is a #rrggbb colour.
	 *
	 * @param string $value Candidate.
	 * @return bool
	 */
	private function is_hex_color( string $value ): bool {
		return 1 === preg_match( '/^#[0-9a-fA-F]{6}$/', $value );
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

		register_setting(
			self::OPTIONS_GROUP,
			self::OPT_EXPIRY_BUTTON_BG,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_hex_color' ),
				'default'           => self::DEFAULT_EXPIRY_BUTTON_BG,
			)
		);

		register_setting(
			self::OPTIONS_GROUP,
			self::OPT_EXPIRY_BUTTON_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_hex_color' ),
				'default'           => self::DEFAULT_EXPIRY_BUTTON_COLOR,
			)
		);

		register_setting(
			self::OPTIONS_GROUP,
			self::OPT_EXPIRY_FONT_SIZE,
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_font_size' ),
				'default'           => self::DEFAULT_EXPIRY_FONT_SIZE,
			)
		);

		register_setting(
			self::OPTIONS_GROUP,
			self::OPT_EXPIRY_BG_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_hex_color' ),
				'default'           => self::DEFAULT_EXPIRY_BG_COLOR,
			)
		);

		register_setting(
			self::OPTIONS_GROUP,
			self::OPT_EXPIRY_TEXT_COLOR,
			array(
				'type'              => 'string',
				'sanitize_callback' => array( $this, 'sanitize_hex_color' ),
				'default'           => self::DEFAULT_EXPIRY_TEXT_COLOR,
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
	 * Colour sanitiser — keep a valid #rrggbb, otherwise store '' and let the
	 * getter substitute that field's own default. One callback serves both
	 * colour options, so it can't pick the right default itself.
	 *
	 * @param mixed $value Raw value from the colour input.
	 * @return string
	 */
	public function sanitize_hex_color( $value ): string {
		$value = strtolower( trim( (string) $value ) );

		return $this->is_hex_color( $value ) ? $value : '';
	}

	/**
	 * Popup text size sanitiser — whole pixels, clamped to the allowed range.
	 *
	 * @param mixed $value Raw value.
	 * @return int
	 */
	public function sanitize_font_size( $value ): int {
		return max( self::MIN_EXPIRY_FONT_SIZE, min( self::MAX_EXPIRY_FONT_SIZE, (int) $value ) );
	}

	/**
	 * Minutes in, seconds out.
	 *
	 * WordPress can run a sanitize callback more than once for a single save —
	 * the first save of an option sanitizes on both the update and the add
	 * path. Only the first pass carries the form's minutes value, so the
	 * converted result is cached in a request-scoped static and every later
	 * pass returns that same figure instead of multiplying by sixty again
	 * (which is what turned an entered "1" into 3600 seconds / "60 minutes").
	 * One settings form means one hold duration per request, so a plain static
	 * is safe here; it resets on the next request.
	 *
	 * @param mixed $value Raw value from the form.
	 * @return int Seconds.
	 */
	public function sanitize_ttl( $value ): int {
		static $seconds = null;

		if ( null !== $seconds ) {
			return $seconds;
		}

		$minutes = max( 1, min( self::MAX_MINUTES, (int) $value ) );
		$seconds = $minutes * MINUTE_IN_SECONDS;

		return $seconds;
	}
}
