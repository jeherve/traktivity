/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import Setup from '../../src/components/Setup';
import { resetSettings } from './settings-mock';

jest.mock( '@wordpress/api-fetch' );
jest.mock( '../../src/settings', () => require( './settings-mock' ) );

describe( 'Setup', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		window.sessionStorage.clear();
		apiFetch.mockResolvedValue( [] );
	} );

	it.each( [
		[ '1', "Let's get started!" ],
		[ '2', 'Trakt.tv Settings' ],
		[ '3', 'The Movie Database Settings' ],
		[ '4', 'Start synchronization' ],
	] )( 'renders step %s of the wizard', ( step, expected ) => {
		resetSettings( { step } );

		render( <Setup /> );

		expect( screen.getAllByText( expected ).length ).toBeGreaterThan( 0 );
	} );

	it( 'shows the dashboard once setup is complete', async () => {
		resetSettings( { step: '5' } );

		render( <Setup /> );

		expect(
			await screen.findByText( 'I am all set! What now?' )
		).toBeInTheDocument();
		expect( screen.getByText( 'In a nutshell' ) ).toBeInTheDocument();
	} );

	it( 'does not start a sync when there are no pages left', async () => {
		resetSettings( { step: '5', syncPages: '0' } );

		render( <Setup /> );

		await screen.findByText( 'I am all set! What now?' );

		const syncCalls = apiFetch.mock.calls.filter(
			( [ options ] ) => options.path === '/traktivity/v1/sync'
		);
		expect( syncCalls ).toHaveLength( 0 );
	} );

	it( 'resumes an interrupted sync on load', async () => {
		resetSettings( { step: '5', syncPages: '4' } );

		render( <Setup /> );

		await screen.findByText( 'I am all set! What now?' );

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( { path: '/traktivity/v1/sync' } )
		);
	} );

	it( 'always renders the plugin header', () => {
		resetSettings( { step: '1' } );

		render( <Setup /> );

		expect(
			screen.getByRole( 'heading', { name: 'Traktivity Dashboard' } )
		).toBeInTheDocument();
	} );
} );
