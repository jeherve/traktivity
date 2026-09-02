/**
 * Playwright configuration.
 *
 * Takes the @wordpress/scripts defaults and points them at tests/e2e.
 *
 * The webServer command starts the tests environment rather than the
 * development one. They are separate wp-env configurations: only the tests
 * environment loads tests/e2e/mu-plugins, which answers the Trakt.tv and TMDb
 * APIs locally.
 */
const path = require( 'path' );
const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

module.exports = {
	...baseConfig,
	testDir: path.join( __dirname, 'tests/e2e/specs' ),
	webServer: {
		...baseConfig.webServer,
		command: 'npm run env:tests start',
	},
};
