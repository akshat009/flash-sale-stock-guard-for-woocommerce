<?php
/**
 * Number setting field view.
 *
 * Included by FlashSaleStockGuardWooCommerce\Admin\Settings_Registrar with
 * $name (string), $value (int), $min (int), $max (int), $suffix (string) and
 * $description (string) in scope.
 *
 * @package FlashSaleStockGuardWooCommerce\Admin
 *
 * @var string $name        The option's form field name/id.
 * @var int    $value       Current value.
 * @var int    $min         Minimum accepted value.
 * @var int    $max         Maximum accepted value.
 * @var string $suffix      Text shown after the input (e.g. "px"); empty to omit.
 * @var string $description  Optional note below the field; empty to omit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<input type="number" class="small-text" step="1"
	name="<?php echo esc_attr( $name ); ?>"
	value="<?php echo esc_attr( (string) $value ); ?>"
	min="<?php echo esc_attr( (string) $min ); ?>"
	max="<?php echo esc_attr( (string) $max ); ?>" />
<?php
if ( '' !== $suffix ) {
	echo ' ' . esc_html( $suffix );
}
?>
<?php if ( '' !== $description ) : ?>
	<p class="description"><?php echo esc_html( $description ); ?></p>
<?php endif; ?>
