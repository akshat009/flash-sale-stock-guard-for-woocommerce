<?php
/**
 * Apply-to mode setting field view.
 *
 * Included by FlashSaleStockGuardWooCommerce\Admin\Settings_Registrar::render_apply_to_field()
 * with $name (string), $current (string), $threshold_name (string) and
 * $threshold (int) in scope.
 *
 * @package FlashSaleStockGuardWooCommerce\Admin
 *
 * @var string $name           The mode option's form field name.
 * @var string $current        Currently selected mode.
 * @var string $threshold_name The threshold option's form field name.
 * @var int    $threshold      Current scarcity threshold in units.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use FlashSaleStockGuardWooCommerce\Admin\Settings_Repository;

?>
<fieldset>
	<legend class="screen-reader-text">
		<?php esc_html_e( 'Apply stock guard to', 'flash-sale-stock-guard-for-woocommerce' ); ?>
	</legend>

	<p>
		<label>
			<input type="radio" name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( Settings_Repository::APPLY_LOW_STOCK ); ?>"
				<?php checked( $current, Settings_Repository::APPLY_LOW_STOCK ); ?> />
			<?php esc_html_e( 'Products running low on stock', 'flash-sale-stock-guard-for-woocommerce' ); ?>
		</label>
		&nbsp;&mdash;&nbsp;
		<?php esc_html_e( 'at or below', 'flash-sale-stock-guard-for-woocommerce' ); ?>
		<input type="number" min="1" max="9999" step="1"
			name="<?php echo esc_attr( $threshold_name ); ?>"
			value="<?php echo esc_attr( (string) $threshold ); ?>"
			class="small-text" />
		<?php esc_html_e( 'units', 'flash-sale-stock-guard-for-woocommerce' ); ?>
	</p>

	<p>
		<label>
			<input type="radio" name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( Settings_Repository::APPLY_ALL ); ?>"
				<?php checked( $current, Settings_Repository::APPLY_ALL ); ?> />
			<?php esc_html_e( 'Every product that manages stock', 'flash-sale-stock-guard-for-woocommerce' ); ?>
		</label>
	</p>

	<p>
		<label>
			<input type="radio" name="<?php echo esc_attr( $name ); ?>"
				value="<?php echo esc_attr( Settings_Repository::APPLY_MARKED ); ?>"
				<?php checked( $current, Settings_Repository::APPLY_MARKED ); ?> />
			<?php esc_html_e( 'Only products I tick individually', 'flash-sale-stock-guard-for-woocommerce' ); ?>
		</label>
	</p>

	<p class="description">
		<?php esc_html_e( 'Guarding a product with hundreds of units achieves nothing — two customers never collide over the last one. The low-stock option turns the guard on automatically as items get scarce, so you do not have to remember before a drop.', 'flash-sale-stock-guard-for-woocommerce' ); ?>
	</p>
	<p class="description">
		<?php esc_html_e( 'Ticking a product in its Inventory tab always guards it, whichever option is selected here.', 'flash-sale-stock-guard-for-woocommerce' ); ?>
	</p>
</fieldset>
