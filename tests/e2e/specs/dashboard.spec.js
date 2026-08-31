/**
 * WordPress dependencies
 */
const { test, expect } = require( '@wordpress/e2e-test-utils-playwright' );

const DASHBOARD_QUERY = 'post_type=traktivity_event&page=traktivity_dashboard';

// Values the mu-plugin in tests/e2e/mu-plugins treats as working credentials.
const VALID_TRAKT_KEY = 'e2e-valid-trakt-key';
const VALID_TMDB_KEY = 'e2e-valid-tmdb-key';

test.describe( 'Traktivity dashboard', () => {
	test.beforeEach( async ( { requestUtils, admin } ) => {
		await requestUtils.rest( {
			method: 'POST',
			path: '/traktivity-e2e/v1/reset',
		} );
		await admin.visitAdminPage( 'edit.php', DASHBOARD_QUERY );
	} );

	test( 'renders the app rather than an empty container', async ( {
		page,
	} ) => {
		await expect(
			page.getByRole( 'heading', { name: 'Traktivity Dashboard' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: "Let's get started!" } )
		).toBeVisible();
	} );

	test( 'loads its script and styles without console errors', async ( {
		page,
		admin,
	} ) => {
		const errors = [];
		page.on( 'console', ( message ) => {
			if ( message.type() === 'error' ) {
				errors.push( message.text() );
			}
		} );
		page.on( 'pageerror', ( error ) => errors.push( error.message ) );

		await admin.visitAdminPage( 'edit.php', DASHBOARD_QUERY );
		await expect(
			page.getByRole( 'button', { name: "Let's get started!" } )
		).toBeVisible();

		expect( errors ).toEqual( [] );
	} );

	test( 'walks the whole setup wizard', async ( { page } ) => {
		// Step 1: introduction.
		await page
			.getByRole( 'button', { name: "Let's get started!" } )
			.click();

		// Step 2: Trakt.tv credentials.
		await expect(
			page.getByRole( 'heading', { name: 'Trakt.tv Settings' } )
		).toBeVisible();

		const submit = page.getByRole( 'button', {
			name: 'Verify and continue',
		} );
		await expect( submit ).toBeDisabled();

		await page.getByLabel( 'Trakt.tv Username' ).fill( 'jeherve' );
		await page.getByLabel( 'Trakt.tv API Key' ).fill( 'a-wrong-key' );
		await submit.click();

		// A rejected key keeps us on the same step and says why.
		await expect(
			page.getByText( 'Invalid API key or unapproved app.' ).first()
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', { name: 'Trakt.tv Settings' } )
		).toBeVisible();

		await page.getByLabel( 'Trakt.tv API Key' ).fill( VALID_TRAKT_KEY );
		await submit.click();

		// Step 3: TMDb key.
		await expect(
			page.getByRole( 'heading', {
				name: 'The Movie Database Settings',
			} )
		).toBeVisible();

		await page.getByLabel( 'TMDB API Key' ).fill( VALID_TMDB_KEY );
		await page
			.getByRole( 'button', { name: 'Verify and continue' } )
			.click();

		// Step 4: offer to run a full sync.
		await expect(
			page.getByRole( 'button', { name: 'Start synchronization' } )
		).toBeVisible();
		await page
			.getByRole( 'button', { name: 'Start synchronization' } )
			.click();

		// Step 5: the dashboard.
		await expect(
			page.getByText( 'I am all set! What now?' )
		).toBeVisible();
		await expect(
			page.getByRole( 'heading', { name: 'In a nutshell' } )
		).toBeVisible();
	} );

	test( 'starts the full sync when the wizard asks for it', async ( {
		page,
		requestUtils,
	} ) => {
		await page
			.getByRole( 'button', { name: "Let's get started!" } )
			.click();
		await page.getByLabel( 'Trakt.tv Username' ).fill( 'jeherve' );
		await page.getByLabel( 'Trakt.tv API Key' ).fill( VALID_TRAKT_KEY );
		await page
			.getByRole( 'button', { name: 'Verify and continue' } )
			.click();
		await page.getByRole( 'button', { name: 'Skip' } ).click();

		// Step 4 is the only place a first sync is ever kicked off.
		await page
			.getByRole( 'button', { name: 'Start synchronization' } )
			.click();
		await expect(
			page.getByText( 'I am all set! What now?' )
		).toBeVisible();

		const options = await requestUtils.rest( {
			path: '/traktivity-e2e/v1/options',
		} );
		expect( options.full_sync ).toBeTruthy();
	} );

	test( 'remembers the step it was left on', async ( { page, admin } ) => {
		await page
			.getByRole( 'button', { name: "Let's get started!" } )
			.click();
		await expect(
			page.getByRole( 'heading', { name: 'Trakt.tv Settings' } )
		).toBeVisible();

		await admin.visitAdminPage( 'edit.php', DASHBOARD_QUERY );

		await expect(
			page.getByRole( 'heading', { name: 'Trakt.tv Settings' } )
		).toBeVisible();
	} );

	test( 'never puts the API key in a request URL', async ( { page } ) => {
		const paths = [];
		page.on( 'request', ( request ) => paths.push( request.url() ) );

		await page
			.getByRole( 'button', { name: "Let's get started!" } )
			.click();
		await page.getByLabel( 'Trakt.tv Username' ).fill( 'jeherve' );
		await page.getByLabel( 'Trakt.tv API Key' ).fill( VALID_TRAKT_KEY );
		await page
			.getByRole( 'button', { name: 'Verify and continue' } )
			.click();

		await expect(
			page.getByRole( 'heading', {
				name: 'The Movie Database Settings',
			} )
		).toBeVisible();

		expect(
			paths.filter( ( url ) => url.includes( VALID_TRAKT_KEY ) )
		).toEqual( [] );
	} );
} );
