/**
 * External dependencies
 */
import { act, renderHook } from '@testing-library/react';

/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import useTraktivitySettings from '../../src/hooks/use-traktivity-settings';
import { resetSettings } from './settings-mock';

jest.mock( '@wordpress/api-fetch' );
jest.mock( '../../src/settings', () => require( './settings-mock' ) );

describe( 'useTraktivitySettings', () => {
	beforeEach( () => {
		jest.clearAllMocks();
		resetSettings();
	} );

	it( 'seeds its state from what PHP localised', () => {
		resetSettings( {
			step: '3',
			traktUsername: 'jeherve',
			traktKey: 'abc123',
			syncPages: '7',
			syncRuntime: 'in_progress',
		} );

		const { result } = renderHook( () => useTraktivitySettings() );

		expect( result.current.step ).toBe( 3 );
		expect( result.current.trakt ).toEqual( {
			username: 'jeherve',
			key: 'abc123',
		} );
		expect( result.current.sync.pages ).toBe( 7 );
		expect( result.current.sync.runtime ).toBe( 'in_progress' );
	} );

	it( 'falls back to step 1 when the stored step is not a number', () => {
		resetSettings( { step: '' } );

		const { result } = renderHook( () => useTraktivitySettings() );

		expect( result.current.step ).toBe( 1 );
	} );

	it( 'persists the new step before advancing', async () => {
		resetSettings( { step: '1' } );
		apiFetch.mockResolvedValue( {} );

		const { result } = renderHook( () => useTraktivitySettings() );

		await act( () => result.current.goToNextStep() );

		expect( apiFetch ).toHaveBeenCalledWith(
			expect.objectContaining( {
				path: '/traktivity/v1/settings/edit',
				method: 'POST',
				data: expect.objectContaining( { step: 2 } ),
			} )
		);
		expect( result.current.step ).toBe( 2 );
	} );

	it( 'refuses to advance past the last step', async () => {
		resetSettings( { step: '5' } );

		const { result } = renderHook( () => useTraktivitySettings() );

		await act( () => result.current.goToNextStep() );

		expect( apiFetch ).not.toHaveBeenCalled();
		expect( result.current.step ).toBe( 5 );
	} );

	it( 'leaves the step alone when the save fails, and shows why', async () => {
		resetSettings( { step: '2' } );
		apiFetch.mockRejectedValue( { message: 'Nope.' } );

		const { result } = renderHook( () => useTraktivitySettings() );

		await act( () => result.current.goToNextStep() );

		expect( result.current.step ).toBe( 2 );
		expect( result.current.notice ).toEqual( {
			status: 'error',
			message: 'Nope.',
		} );
	} );

	describe( 'saveTraktCredentials', () => {
		it( 'saves, then verifies without putting the key in the URL', async () => {
			apiFetch
				.mockResolvedValueOnce( {} )
				.mockResolvedValueOnce( { code: 200, message: 'Working.' } );

			const { result } = renderHook( () => useTraktivitySettings() );

			let valid;
			await act( async () => {
				valid = await result.current.saveTraktCredentials(
					'jeherve',
					'secret-key'
				);
			} );

			expect( valid ).toBe( true );

			const [ save, verify ] = apiFetch.mock.calls;
			expect( save[ 0 ].data.trakt ).toEqual( {
				username: 'jeherve',
				key: 'secret-key',
			} );
			expect( verify[ 0 ].path ).toBe( '/traktivity/v1/connection' );
			expect( verify[ 0 ].path ).not.toContain( 'secret-key' );

			expect( result.current.trakt.valid ).toBe( true );
			expect( result.current.notice ).toEqual( {
				status: 'success',
				message: 'Working.',
			} );
		} );

		it( 'reports rejected credentials as invalid rather than throwing', async () => {
			apiFetch.mockResolvedValueOnce( {} ).mockRejectedValueOnce( {
				code: 403,
				message: 'Invalid API key or unapproved app.',
			} );

			const { result } = renderHook( () => useTraktivitySettings() );

			let valid;
			await act( async () => {
				valid = await result.current.saveTraktCredentials( 'a', 'b' );
			} );

			expect( valid ).toBe( false );
			expect( result.current.trakt.valid ).toBe( false );
			expect( result.current.notice ).toEqual( {
				status: 'error',
				message: 'Invalid API key or unapproved app.',
			} );
		} );
	} );

	describe( 'saveTmdbCredentials', () => {
		it( 'verifies against the parameterless endpoint', async () => {
			apiFetch
				.mockResolvedValueOnce( {} )
				.mockResolvedValueOnce( { code: 200, message: 'Working.' } );

			const { result } = renderHook( () => useTraktivitySettings() );

			await act( () => result.current.saveTmdbCredentials( 'tmdb-key' ) );

			const [ , verify ] = apiFetch.mock.calls;
			expect( verify[ 0 ].path ).toBe( '/traktivity/v1/tmdb' );
			expect( verify[ 0 ].path ).not.toContain( 'tmdb-key' );
			expect( result.current.tmdb.valid ).toBe( true );
		} );
	} );

	describe( 'launchSync', () => {
		it( 'records the returned status for a full sync', async () => {
			apiFetch.mockResolvedValue( 'Sync in progress.' );

			const { result } = renderHook( () => useTraktivitySettings() );

			await act( () => result.current.launchSync() );

			expect( apiFetch ).toHaveBeenCalledWith( {
				path: '/traktivity/v1/sync',
				method: 'POST',
				data: { type: null },
			} );
			expect( result.current.sync.status ).toBe( 'Sync in progress.' );
		} );

		it( 'marks the runtime sync in progress rather than the main status', async () => {
			apiFetch.mockResolvedValue( 'Started.' );

			const { result } = renderHook( () => useTraktivitySettings() );

			await act( () => result.current.launchSync( 'total_runtime' ) );

			expect( result.current.sync.runtime ).toBe( 'in_progress' );
			expect( result.current.sync.status ).toBe( '' );
		} );
	} );

	it( 'clears a notice on request', async () => {
		apiFetch.mockResolvedValue( 'Done.' );

		const { result } = renderHook( () => useTraktivitySettings() );

		await act( () => result.current.launchSync() );
		expect( result.current.notice ).not.toBeNull();

		await act( async () => result.current.removeNotice() );
		expect( result.current.notice ).toBeNull();
	} );
} );
