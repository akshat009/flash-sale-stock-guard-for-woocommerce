<?php
/**
 * Checkbox setting field view.
 *
 * Included by FlashSaleStockGuardWooCommerce\Admin\Settings_Registrar with
 * $name (string), $checked (bool), $label (string) and $description (string)
 * in scope.
 *
 * @package FlashSaleStockGuardWooCommerce\Admin
 *
 * @var string $name        The option's form field name/id.
 * @var bool   $checked     Whether the box is ticked.
 * @var string $label       Text shown beside the checkbox.
 * @var string $description Optional note below the field; empty to omit.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<label>
	<input type="checkbox" name="<?php echo esc_attr( $name ); ?>" value="1" <?php checked( $checked ); ?> />
	<?php echo esc_html( $label ); ?>
</label>
<?php if ( '' !== $description ) : ?>
	<p class="description"><?php echo esc_html( $description ); ?></p>
<?php endif; ?>
