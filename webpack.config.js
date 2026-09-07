/**
 * WooCommerce Blocks build config for Checkout.com.
 *
 * Extends @wordpress/scripts' default webpack config and defines one entry per
 * payment method. Each entry emits build/{name}-blocks.js and, via the WooCommerce
 * DependencyExtractionWebpackPlugin, build/{name}-blocks.asset.php (dependencies + version)
 * which the PHP payment-method classes read in get_payment_method_script_handles().
 *
 * The WooCommerce plugin (replacing the default @wordpress one) also externalises
 * @woocommerce/* imports to the runtime wc.* globals (e.g. @woocommerce/blocks-registry
 * -> wc.wcBlocksRegistry) instead of trying to bundle them.
 */
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );
const WooCommerceDependencyExtractionWebpackPlugin = require( '@woocommerce/dependency-extraction-webpack-plugin' );
const path = require( 'path' );

const plugins = ( defaultConfig.plugins || [] ).filter(
	( plugin ) => plugin.constructor.name !== 'DependencyExtractionWebpackPlugin'
);

module.exports = {
	...defaultConfig,
	entry: {
		'cards-blocks': path.resolve( __dirname, 'client/blocks/cards.js' ),
		'flow-blocks': path.resolve( __dirname, 'client/blocks/flow.js' ),
		'googlepay-blocks': path.resolve( __dirname, 'client/blocks/googlepay.js' ),
		'applepay-blocks': path.resolve( __dirname, 'client/blocks/applepay.js' ),
		'paypal-blocks': path.resolve( __dirname, 'client/blocks/paypal.js' ),
	},
	output: {
		...defaultConfig.output,
		path: path.resolve( __dirname, 'build' ),
		filename: '[name].js',
	},
	plugins: [
		...plugins,
		new WooCommerceDependencyExtractionWebpackPlugin(),
	],
};
