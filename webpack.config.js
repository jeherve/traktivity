/**
 * Build configuration.
 *
 * wp-scripts builds either the blocks it finds or a default `src/index.js`
 * entry, not both: as soon as a `block.json` appears under `src/`, its entry
 * list becomes the blocks alone and the dashboard bundle silently stops being
 * emitted. This puts the dashboard entry back alongside the blocks.
 *
 * Everything else is left to the default config.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: () => {
		const blockEntries =
			typeof defaultConfig.entry === 'function'
				? defaultConfig.entry()
				: defaultConfig.entry;

		return {
			...blockEntries,
			index: path.resolve( process.cwd(), 'src', 'index.js' ),
		};
	},
};
