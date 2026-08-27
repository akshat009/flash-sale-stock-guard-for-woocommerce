<?php
/**
 * Admin Settings Page Registrar.
 *
 * @package FlashSaleStockGuardWooCommerce\Admin
 */

declare(strict_types=1);

namespace FlashSaleStockGuardWooCommerce\Admin;

use FlashSaleStockGuardWooCommerce\Contracts\Service_Provider;
use FlashSaleStockGuardWooCommerce\Core\Container;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Settings_Registrar.
 *
 * Registers the admin menu page and Settings API hooks. Data access is
 * delegated to Settings_Repository; markup lives in src/Admin/views/.
 */
class Settings_Registrar implements Service_Provider {

	/**
	 * Menu slug and Settings API page identifier.
	 *
	 * @var string
	 */
	public const PAGE_SLUG = 'flash-sale-stock-guard-for-woocommerce';

	/**
	 * Settings section identifier.
	 *
	 * @var string
	 */
	private const SECTION = 'fssgw_main_section';

	/**
	 * Expiry popup appearance section identifier.
	 *
	 * @var string
	 */
	private const SECTION_POPUP = 'fssgw_popup_section';

	/**
	 * Application container, kept for on-demand Settings_Repository lookups
	 * from inside WordPress-invoked callbacks (add_submenu_page() and
	 * add_settings_field() call these with fixed signatures, so the
	 * repository can't be a constructor argument here).
	 *
	 * @var Container
	 */
	private Container $container;

	/**
	 * Bind Settings_Repository as a singleton.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function register( Container $container ): void {
		$container->singleton( Settings_Repository::class, static fn () => new Settings_Repository() );
	}

	/**
	 * Register admin menu and settings hooks.
	 *
	 * @param Container $container Application container.
	 * @return void
	 */
	public function boot( Container $container ): void {
		$this->container = $container;

		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
	}

	/**
	 * Add the page under WooCommerce rather than Settings — store owners look
	 * for store features there.
	 *
	 * @return void
	 */
	public function add_menu_page() {
		add_submenu_page(
			'woocommerce',
			__( 'Stock Guard', 'flash-sale-stock-guard-for-woocommerce' ),
			__( 'Stock Guard', 'flash-sale-stock-guard-for-woocommerce' ),
			'manage_woocommerce',
			self::PAGE_SLUG,
			array( $this, 'render_page' )
		);
	}

	/**
	 * Register settings using the Settings API.
	 *
	 * @return void
	 */
	public function register_settings() {
		$repository = $this->container->get( Settings_Repository::class );
		$repository->register_setting();

		add_settings_section(
			self::SECTION,
			__( 'Stock Guard Settings', 'flash-sale-stock-guard-for-woocommerce' ),
			array( $this, 'render_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			Settings_Repository::OPT_ENABLED,
			__( 'Enable stock guard', 'flash-sale-stock-guard-for-woocommerce' ),
			array( $this, 'render_enabled_field' ),
			self::PAGE_SLUG,
			self::SECTION
		);

		add_settings_field(
			Settings_Repository::OPT_APPLY_TO,
			__( 'Apply stock guard to', 'flash-sale-stock-guard-for-woocommerce' ),
			array( $this, 'render_apply_to_field' ),
			self::PAGE_SLUG,
			self::SECTION
		);

		add_settings_field(
			Settings_Repository::OPT_TTL,
			__( 'Hold stock for', 'flash-sale-stock-guard-for-woocommerce' ),
			array( $this, 'render_ttl_field' ),
			self::PAGE_SLUG,
			self::SECTION
		);

		add_settings_field(
			Settings_Repository::OPT_SHOW_TIMER,
			__( 'Show countdown in cart', 'flash-sale-stock-guard-for-woocommerce' ),
			array( $this, 'render_show_timer_field' ),
			self::PAGE_SLUG,
			self::SECTION
		);

		add_settings_field(
			Settings_Repository::OPT_DELETE_ON_UNINSTALL,
			__( 'Delete data on uninstall', 'flash-sale-stock-guard-for-woocommerce' ),
			array( $this, 'render_delete_field' ),
			self::PAGE_SLUG,
			self::SECTION
		);

		add_settings_section(
			self::SECTION_POPUP,
			__( 'Expiry popup', 'flash-sale-stock-guard-for-woocommerce' ),
			array( $this, 'render_popup_section_intro' ),
			self::PAGE_SLUG
		);

		add_settings_field(
			Settings_Repository::OPT_EXPIRY_BG_COLOR,
			__( 'Popup background', 'flash-sale-stock-guard-for-woocommerce' ),
			array( $this, 'render_expiry_bg_color_field' ),
			self::PAGE_SLUG,
			self::SECTION_POPUP
		);

		add_settings_field(
			Settings_Repository::OPT_EXPIRY_TEXT_COLOR,
			__( 'Popup text colour', 'flash-sale-stock-guard-for-woocommerce' ),
			array( $this, 'render_expiry_text_color_field' ),
			self::PAGE_SLUG,
			self::SECTION_POPUP
		);

		add_settings_field(
			Settings_Repository::OPT_EXPIRY_BUTTON_BG,
			__( 'Button colour', 'flash-sale-stock-guard-for-woocommerce' ),
			array( $this, 'render_expiry_button_bg_field' ),
			self::PAGE_SLUG,
			self::SECTION_POPUP
		);

		add_settings_field(
			Settings_Repository::OPT_EXPIRY_BUTTON_COLOR,
			__( 'Button text colour', 'flash-sale-stock-guard-for-woocommerce' ),
			array( $this, 'render_expiry_button_color_field' ),
			self::PAGE_SLUG,
			self::SECTION_POPUP
		);

		add_settings_field(
			Settings_Repository::OPT_EXPIRY_FONT_SIZE,
			__( 'Text size', 'flash-sale-stock-guard-for-woocommerce' ),
			array( $this, 'render_expiry_font_size_field' ),
			self::PAGE_SLUG,
			self::SECTION_POPUP
		);
	}

	/**
	 * Render the section intro.
	 *
	 * @return void
	 */
	public function render_section_intro() {
		include FSSGW_PATH . 'src/Admin/views/section-intro.php';
	}

	/**
	 * Render the master switch.
	 *
	 * @return void
	 */
	public function render_enabled_field() {
		$name        = Settings_Repository::OPT_ENABLED;
		$checked     = $this->container->get( Settings_Repository::class )->is_enabled();
		$label       = __( 'Hold stock for a customer as soon as they add a guarded item to their cart', 'flash-sale-stock-guard-for-woocommerce' );
		$description = __( 'Turning this off returns the store to WooCommerce default behaviour, where stock is only reduced once an order is placed.', 'flash-sale-stock-guard-for-woocommerce' );

		include FSSGW_PATH . 'src/Admin/views/checkbox-field.php';
	}

	/**
	 * Render the apply-to mode selector.
	 *
	 * @return void
	 */
	public function render_apply_to_field() {
		$repository = $this->container->get( Settings_Repository::class );

		$name           = Settings_Repository::OPT_APPLY_TO;
		$current        = $repository->get_apply_to();
		$threshold_name = Settings_Repository::OPT_LOW_STOCK;
		$threshold      = $repository->get_low_stock_threshold();

		include FSSGW_PATH . 'src/Admin/views/apply-to-field.php';
	}

	/**
	 * Render the hold duration field.
	 *
	 * @return void
	 */
	public function render_ttl_field() {
		$name    = Settings_Repository::OPT_TTL;
		$minutes = $this->container->get( Settings_Repository::class )->get_ttl_minutes();

		include FSSGW_PATH . 'src/Admin/views/ttl-field.php';
	}

	/**
	 * Render the countdown visibility switch.
	 *
	 * @return void
	 */
	public function render_show_timer_field() {
		$name        = Settings_Repository::OPT_SHOW_TIMER;
		$checked     = $this->container->get( Settings_Repository::class )->shows_timer();
		$label       = __( 'Show customers how long their items are held for', 'flash-sale-stock-guard-for-woocommerce' );
		$description = __( 'Display only. Turning this off does not affect the hold — stock is still guarded and still expires on schedule.', 'flash-sale-stock-guard-for-woocommerce' );

		include FSSGW_PATH . 'src/Admin/views/checkbox-field.php';
	}

	/**
	 * Render the destructive-uninstall opt-in.
	 *
	 * @return void
	 */
	public function render_delete_field() {
		$name        = Settings_Repository::OPT_DELETE_ON_UNINSTALL;
		$checked     = $this->container->get( Settings_Repository::class )->deletes_data_on_uninstall();
		$label       = __( 'Drop the holds table when the plugin is deleted', 'flash-sale-stock-guard-for-woocommerce' );
		$description = '';

		include FSSGW_PATH . 'src/Admin/views/checkbox-field.php';
	}

	/**
	 * Render the expiry popup section intro.
	 *
	 * @return void
	 */
	public function render_popup_section_intro() {
		echo '<p>' . esc_html__( 'Style the "reservation expired" popup customers see when the countdown runs out. Wording is fixed; only appearance changes here.', 'flash-sale-stock-guard-for-woocommerce' ) . '</p>';
	}

	/**
	 * Render the popup card background colour field.
	 *
	 * @return void
	 */
	public function render_expiry_bg_color_field() {
		$name        = Settings_Repository::OPT_EXPIRY_BG_COLOR;
		$value       = $this->container->get( Settings_Repository::class )->get_expiry_bg_color();
		$description = '';

		include FSSGW_PATH . 'src/Admin/views/color-field.php';
	}

	/**
	 * Render the popup card text colour field.
	 *
	 * @return void
	 */
	public function render_expiry_text_color_field() {
		$name        = Settings_Repository::OPT_EXPIRY_TEXT_COLOR;
		$value       = $this->container->get( Settings_Repository::class )->get_expiry_text_color();
		$description = '';

		include FSSGW_PATH . 'src/Admin/views/color-field.php';
	}

	/**
	 * Render the popup button background colour field.
	 *
	 * @return void
	 */
	public function render_expiry_button_bg_field() {
		$name        = Settings_Repository::OPT_EXPIRY_BUTTON_BG;
		$value       = $this->container->get( Settings_Repository::class )->get_expiry_button_bg();
		$description = '';

		include FSSGW_PATH . 'src/Admin/views/color-field.php';
	}

	/**
	 * Render the popup button text colour field.
	 *
	 * @return void
	 */
	public function render_expiry_button_color_field() {
		$name        = Settings_Repository::OPT_EXPIRY_BUTTON_COLOR;
		$value       = $this->container->get( Settings_Repository::class )->get_expiry_button_color();
		$description = '';

		include FSSGW_PATH . 'src/Admin/views/color-field.php';
	}

	/**
	 * Render the popup text size field.
	 *
	 * @return void
	 */
	public function render_expiry_font_size_field() {
		$name        = Settings_Repository::OPT_EXPIRY_FONT_SIZE;
		$value       = $this->container->get( Settings_Repository::class )->get_expiry_font_size();
		$min         = Settings_Repository::MIN_EXPIRY_FONT_SIZE;
		$max         = Settings_Repository::MAX_EXPIRY_FONT_SIZE;
		$suffix      = __( 'px', 'flash-sale-stock-guard-for-woocommerce' );
		$description = __( 'Base text size for the popup. Heading and button scale from this.', 'flash-sale-stock-guard-for-woocommerce' );

		include FSSGW_PATH . 'src/Admin/views/number-field.php';
	}

	/**
	 * Render admin page content.
	 *
	 * @return void
	 */
	public function render_page() {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'flash-sale-stock-guard-for-woocommerce' ) );
		}

		$repository = $this->container->get( Settings_Repository::class );

		include FSSGW_PATH . 'src/Admin/views/settings-page.php';
	}
}
