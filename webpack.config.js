const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

/**
 * Merges our explicit entries with wp-scripts' own lazily-computed entry
 * function so native blocks (block.json under assets/src/blocks/**) keep
 * building automatically alongside them. wp-scripts loads this file
 * automatically when present at the project root.
 */
module.exports = {
	...defaultConfig,
	entry: () => ( {
		...( typeof defaultConfig.entry === 'function' ? defaultConfig.entry() : defaultConfig.entry ),
		'wc-gateway-block': './assets/src/wc-gateway-block.js',
		'blocks-integration': './assets/src/blocks-integration.js',
	} ),
};
