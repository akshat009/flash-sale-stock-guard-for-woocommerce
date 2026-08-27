<?php
/**
 * Colour setting field view.
 *
 * Included by FlashSaleStockGuardWooCommerce\Admin\Settings_Registrar with
 * $name (string), $value (string, #rrggbb) and $description (string) in scope.
 *
 * @package FlashSaleStockGuardWooCommerce\Admin
 *
 * @var string $name        The option's form field name/id.
 * @var string $value       Current colour as #rrggbb.
 * @var string $description  Optional note below the field; empty to omit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<input type="color"
	name="<?php echo esc_attr( $name ); ?>"
	value="<?php echo esc_attr( $value ); ?>" />
<code><?php echo esc_html( $value ); ?></code>
<?php if ( '' !== $description ) : ?>
	<p class="description"><?php echo esc_html( $description ); ?></p>
<?php endif; ?>
