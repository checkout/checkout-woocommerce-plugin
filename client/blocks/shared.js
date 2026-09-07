/**
 * Shared helpers for the Checkout.com WooCommerce Blocks integrations.
 */

/**
 * Load an external script once and resolve when ready.
 *
 * @param {string} src Script URL.
 * @param {string} id  Optional element id used to de-dupe.
 * @return {Promise<void>} Resolves when the script has loaded.
 */
export const loadScript = ( src, id ) => {
	return new Promise( ( resolve, reject ) => {
		const existing = id ? document.getElementById( id ) : document.querySelector( `script[src="${ src }"]` );
		if ( existing ) {
			if ( existing.getAttribute( 'data-loaded' ) === 'true' ) {
				resolve();
			} else {
				existing.addEventListener( 'load', () => resolve() );
				existing.addEventListener( 'error', reject );
			}
			return;
		}
		const script = document.createElement( 'script' );
		script.src = src;
		if ( id ) {
			script.id = id;
		}
		script.async = true;
		script.addEventListener( 'load', () => {
			script.setAttribute( 'data-loaded', 'true' );
			resolve();
		} );
		script.addEventListener( 'error', reject );
		document.head.appendChild( script );
	} );
};

/**
 * Read the cardholder name from the Blocks billing fields, if present.
 *
 * @param {Object} billing Billing data from the Blocks billing context.
 * @return {string} Cardholder name or empty string.
 */
export const getCardholderName = ( billing ) => {
	const address = ( billing && billing.billingAddress ) || {};
	return [ address.first_name, address.last_name ].filter( Boolean ).join( ' ' ).trim();
};
