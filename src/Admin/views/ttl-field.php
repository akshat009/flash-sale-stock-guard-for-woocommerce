<?php
/**
 * Hold duration setting field view.
 *
 * Included by FlashSaleStockGuardWooCommerce\Admin\Settings_Registrar::render_ttl_field()
 * with $name (string) and $minutes (int) in scope.
 *
 * Entered in minutes, stored in seconds — see Settings_Repository::sanitize_ttl().
 *
 * @package FlashSaleStockGuardWooCommerce\Admin
 *
 * @var string $name    The option's form field name/id.
 * @var int    $minutes Current hold duration in whole minutes.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<input type="number" min="1" max="1440" step="1"
	name="<?php echo esc_attr( $name ); ?>"
	value="<?php echo esc_attr( (string) $minutes ); ?>"
	class="small-text" />
<?php esc_html_e( 'minutes', 'flash-sale-stock-guard-for-woocommerce' ); ?>
<p class="description">
	<?php esc_html_e( 'How long a guarded item stays held before it is released back to other customers. Long enough to finish checkout, short enough that an abandoned cart does not sit on your inventory. To turn holds off entirely, use the checkbox above.', 'flash-sale-stock-guard-for-woocommerce' ); ?>
</p>
