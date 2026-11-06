/**
 * Babel config for the WooCommerce Blocks build.
 *
 * Keeps the classic JSX transform (React createElement, imported from the
 * WordPress `react` script) that @wordpress/scripts used before v28. Newer
 * versions use the automatic runtime, which needs the `react-jsx-runtime`
 * script (WordPress 6.6+); without it the runtime is bundled and depends on
 * React internals that React 19 removed. Settings match
 * @wordpress/babel-preset-default 7.x.
 */
module.exports = ( api ) => {
	api.cache( true );

	return {
		presets: [ '@wordpress/babel-preset-default' ],
		plugins: [
			[
				'@wordpress/babel-plugin-import-jsx-pragma',
				{
					scopeVariable: 'createElement',
					scopeVariableFrag: 'Fragment',
					source: 'react',
					isDefault: false,
				},
			],
			[
				'@babel/plugin-transform-react-jsx',
				{
					pragma: 'createElement',
					pragmaFrag: 'Fragment',
				},
			],
		],
	};
};
