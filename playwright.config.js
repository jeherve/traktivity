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
const { port } = require( './.wp-env.tests.json' );

/*
 * wp-env lets WP_ENV_PORT override the port in .wp-env.tests.json, so follow
 * it here too. This has to be set before the defaults are loaded, because they
 * read WP_BASE_URL as soon as they are required.
 */
process.env.WP_BASE_URL ??= `http://localhost:${
	process.env.WP_ENV_PORT || port
}`;

const baseConfig = require( '@wordpress/scripts/config/playwright.config' );

module.exports = {
	...baseConfig,
	testDir: path.join( __dirname, 'tests/e2e/specs' ),
	webServer: {
		...baseConfig.webServer,
		command: 'npm run env:tests start',

		/*
		 * Wait for a route only the e2e mu-plugin registers, rather than for
		 * the port to open. Another project's wp-env site on the same port
		 * would otherwise be taken for this one and the suite run against it.
		 */
		port: undefined,
		url: `${ baseConfig.use.baseURL }?rest_route=/traktivity-e2e/v1`,
	},
};
