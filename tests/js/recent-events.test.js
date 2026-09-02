/**
 * External dependencies
 */
import { render, screen, waitFor } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import RecentEvents from '../../src/components/RecentEvents';

jest.mock( '@wordpress/api-fetch' );

const STORAGE_KEY = 'traktivity_recent_events';

/**
 * Build a REST-shaped event record.
 *
 * @param {number}  id        Event ID.
 * @param {string}  title     Rendered title.
 * @param {boolean} withMedia Whether to embed a featured image.
 * @return {Object} The event.
 */
function event( id, title, withMedia = true ) {
	return {
		id,
		link: `https://example.com/${ id }`,
		title: { rendered: title },
		_embedded: withMedia
			? {
					'wp:featuredmedia': [
						{
							alt_text: `${ title } poster`,
							media_details: {
								sizes: {
									medium: {
										source_url: `https://example.com/${ id }.jpg`,
										width: 300,
										height: 450,
									},
								},
							},
						},
					],
			  }
			: undefined,
	};
}

describe( 'RecentEvents', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		window.sessionStorage.clear();
	} );

	it( 'shows a spinner until the request resolves', async () => {
		let resolve;
		apiFetch.mockReturnValue(
			new Promise( ( r ) => {
				resolve = r;
			} )
		);

		const { container } = render( <RecentEvents /> );

		expect( container.querySelector( '.components-spinner' ) ).toBeTruthy();

		resolve( [] );
		await waitFor( () =>
			expect(
				container.querySelector( '.components-spinner' )
			).toBeFalsy()
		);
	} );

	it( 'renders an image and title for each event', async () => {
		apiFetch.mockResolvedValue( [
			event( 1, 'The Bear' ),
			event( 2, 'Slow Horses' ),
		] );

		render( <RecentEvents /> );

		expect( await screen.findByText( 'The Bear' ) ).toBeInTheDocument();
		expect( screen.getByText( 'Slow Horses' ) ).toBeInTheDocument();

		const image = screen.getByAltText( 'The Bear poster' );
		expect( image ).toHaveAttribute( 'src', 'https://example.com/1.jpg' );
		expect( image ).toHaveAttribute( 'width', '300' );
	} );

	it( 'skips events that have no featured image', async () => {
		apiFetch.mockResolvedValue( [
			event( 1, 'Has art' ),
			event( 2, 'No art', false ),
		] );

		render( <RecentEvents /> );

		expect( await screen.findByText( 'Has art' ) ).toBeInTheDocument();
		expect( screen.queryByText( 'No art' ) ).not.toBeInTheDocument();
	} );

	it( 'tells the user when nothing has been logged yet', async () => {
		apiFetch.mockResolvedValue( [] );

		render( <RecentEvents /> );

		expect(
			await screen.findByText( /Nothing logged yet/ )
		).toBeInTheDocument();
	} );

	it( 'reads the cache instead of making a second request', async () => {
		window.sessionStorage.setItem(
			STORAGE_KEY,
			JSON.stringify( [ event( 9, 'Cached show' ) ] )
		);

		render( <RecentEvents /> );

		expect( await screen.findByText( 'Cached show' ) ).toBeInTheDocument();
		expect( apiFetch ).not.toHaveBeenCalled();
	} );

	it( 'refetches when the cached value is not usable', async () => {
		window.sessionStorage.setItem( STORAGE_KEY, 'not json at all' );
		apiFetch.mockResolvedValue( [ event( 1, 'Fresh show' ) ] );

		render( <RecentEvents /> );

		expect( await screen.findByText( 'Fresh show' ) ).toBeInTheDocument();
		expect( apiFetch ).toHaveBeenCalledTimes( 1 );
	} );

	it( 'shows the empty state when the request fails', async () => {
		apiFetch.mockRejectedValue( new Error( 'offline' ) );

		render( <RecentEvents /> );

		expect(
			await screen.findByText( /Nothing logged yet/ )
		).toBeInTheDocument();
	} );

	it( 'requests six embedded events', async () => {
		apiFetch.mockResolvedValue( [] );

		render( <RecentEvents /> );

		await waitFor( () => expect( apiFetch ).toHaveBeenCalled() );
		expect( apiFetch ).toHaveBeenCalledWith( {
			path: '/wp/v2/traktivity_event?per_page=6&_embed=1',
		} );
	} );
} );
