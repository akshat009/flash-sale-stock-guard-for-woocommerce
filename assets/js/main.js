/**
 * Main JavaScript file for Flash Sale Stock Guard for WooCommerce.
 */
console.log( 'Flash Sale Stock Guard for WooCommerce loaded.' );

if ( typeof window.fssgwAjax !== 'undefined' ) {
	const formData = new FormData();
	formData.append( 'action', 'fssgw_action' );
	formData.append( 'nonce', window.fssgwAjax.nonce );
	formData.append( 'input_text', 'hello' );

	fetch( window.fssgwAjax.ajax_url, {
		method: 'POST',
		body: formData
	} )
		.then( response => response.json() )
		.then( data => console.log( 'Flash Sale Stock Guard for WooCommerce AJAX response:', data ) )
		.catch( error => console.error( 'Flash Sale Stock Guard for WooCommerce AJAX error:', error ) );
}
