/**
 * Playwright configuration.
 *
 * Takes the `@wordpress/scripts` defaults and points them at tests/e2e.
 *
 * The webServer command starts the tests environment rather than the
 * development one. They are separate wp-env configurations: only the tests
 * environment loads tests/e2e/mu-plugins, which answers the Trakt.tv and TMDb
 * APIs locally.
 */
const { createHash } = require( 'crypto' );
const fs = require( 'fs' );
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

/*
 * Every checkout's tests site answers on the same port by default, and the
 * specs reset the plugin's options before each test, so the suite has to know
 * the site it reaches runs this checkout. The e2e mu-plugin reads this ID from
 * the file next to it and only reports ready when it's asked for the same one.
 */
const checkoutId = createHash( 'sha256' )
	.update( __dirname )
	.digest( 'hex' )
	.slice( 0, 16 );
const checkoutIdFile = path.join(
	__dirname,
	'tests/e2e/mu-plugins/.checkout-id'
);

if (
	! fs.existsSync( checkoutIdFile ) ||
	fs.readFileSync( checkoutIdFile, 'utf8' ) !== checkoutId
) {
	fs.writeFileSync( checkoutIdFile, checkoutId );
}

module.exports = {
	...baseConfig,
	testDir: path.join( __dirname, 'tests/e2e/specs' ),
	webServer: {
		...baseConfig.webServer,
		command: 'npm run env:tests start',

		/*
		 * Wait for this checkout's site, rather than for the port to open.
		 * Another project's site, or another checkout's, answers this with a
		 * 404 or a 409; Playwright then runs the command above, which fails on
		 * the busy port instead of running the suite against the wrong site.
		 */
		port: undefined,
		url: `${ baseConfig.use.baseURL }?rest_route=/traktivity-e2e/v1/checkout&checkout=${ checkoutId }`,
	},
};
