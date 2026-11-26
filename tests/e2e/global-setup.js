/**
 * Playwright global setup.
 *
 * Playwright counts a 401 or 403 as a ready server, so a site from another
 * checkout that blocks anonymous REST requests passes the webServer probe and
 * gets reused. Check the site's identity here, before the inherited setup logs
 * in and before any spec resets the plugin's options.
 */
const baseGlobalSetup = require( '@wordpress/scripts/config/playwright/global-setup' );

/**
 * @param {import('@playwright/test').FullConfig} config
 * @return {Promise<void>}
 */
module.exports = async function globalSetup( config ) {
	const url = config.webServer?.url;

	if ( ! url ) {
		throw new Error(
			'The Playwright config has no checkout URL to check.'
		);
	}

	const response = await fetch( url, { redirect: 'manual' } );
	const body = await response.json().catch( () => null );

	if ( response.status !== 200 || body?.checkout !== true ) {
		throw new Error(
			`The site at ${ config.projects[ 0 ].use.baseURL } doesn't run this checkout (HTTP ${ response.status }). ` +
				'Stop the site holding that port, or set WP_ENV_PORT to start this checkout on another one.'
		);
	}

	await baseGlobalSetup( config );
};
