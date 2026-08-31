/**
 * Playwright configuration.
 *
 * Takes the @wordpress/scripts defaults and points them at tests/e2e. The
 * default webServer command names an npm script this project does not have,
 * so it is replaced here too.
 */
const path = require( 'path' );
const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

module.exports = {
	...baseConfig,
	testDir: path.join( __dirname, 'tests/e2e/specs' ),
	webServer: {
		...baseConfig.webServer,
		command: 'npm run env start',
	},
};
