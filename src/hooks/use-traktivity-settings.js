/**
 * WordPress dependencies
 */
import { useCallback, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import settings from '../settings';

export const LAST_STEP = 5;

/**
 * Run a credential check.
 *
 * The endpoints read the credentials that were just saved, rather than taking
 * them as arguments, so that keys never travel in a URL.
 *
 * Both answer with { code, message } whether or not the credentials work, but
 * a rejected key comes back on an error status, which apiFetch rejects. Either
 * way the payload is what we want to show the user.
 *
 * @param {string} path REST path to call.
 * @return {Promise<Object>} The endpoint's { code, message } payload.
 */
function check( path ) {
	return apiFetch( { path } ).catch( ( error ) => error );
}

/**
 * Owns the dashboard's settings state and everything that persists it.
 *
 * @return {Object} State and the callbacks that change it.
 */
export default function useTraktivitySettings() {
	const [ step, setStep ] = useState(
		() => parseInt( settings.step, 10 ) || 1
	);
	const [ trakt, setTrakt ] = useState( () => ( {
		username: settings.traktUsername || '',
		key: settings.traktKey || '',
	} ) );
	const [ tmdb, setTmdb ] = useState( () => ( {
		key: settings.tmdbKey || '',
	} ) );
	const [ sync, setSync ] = useState( () => ( {
		status: settings.syncStatus || '',
		pages: parseInt( settings.syncPages, 10 ) || 0,
		runtime: settings.syncRuntime || '',
	} ) );
	const [ notice, setNotice ] = useState( null );

	const removeNotice = useCallback( () => setNotice( null ), [] );

	const save = useCallback(
		( payload ) =>
			apiFetch( {
				path: '/traktivity/v1/settings/edit',
				method: 'POST',
				data: payload,
			} ),
		[]
	);

	const failed = useCallback( ( error ) => {
		setNotice( {
			status: 'error',
			message:
				error?.message ||
				__( 'Changes could not be saved.', 'traktivity' ),
		} );
	}, [] );

	const goToNextStep = useCallback( () => {
		if ( step >= LAST_STEP ) {
			return Promise.resolve();
		}

		const next = step + 1;

		return save( { trakt, tmdb, step: next } )
			.then( () => {
				setStep( next );
				setNotice( null );
			} )
			.catch( failed );
	}, [ step, trakt, tmdb, save, failed ] );

	const saveTraktCredentials = useCallback(
		( username, key ) => {
			const updated = { username, key };
			setTrakt( updated );

			return save( { trakt: updated, tmdb, step } )
				.then( () => check( '/traktivity/v1/connection' ) )
				.then( ( body ) => {
					const valid = body.code === 200;
					setTrakt( { ...updated, valid } );
					setNotice( {
						status: valid ? 'success' : 'error',
						message: body.message,
					} );
					return valid;
				} )
				.catch( ( error ) => {
					failed( error );
					return false;
				} );
		},
		[ tmdb, step, save, failed ]
	);

	const saveTmdbCredentials = useCallback(
		( key ) => {
			const updated = { key };
			setTmdb( updated );

			return save( { trakt, tmdb: updated, step } )
				.then( () => check( '/traktivity/v1/tmdb' ) )
				.then( ( body ) => {
					const valid = body.code === 200;
					setTmdb( { ...updated, valid } );
					setNotice( {
						status: valid ? 'success' : 'error',
						message: body.message,
					} );
					return valid;
				} )
				.catch( ( error ) => {
					failed( error );
					return false;
				} );
		},
		[ trakt, step, save, failed ]
	);

	const launchSync = useCallback(
		( type = null ) =>
			apiFetch( {
				path: '/traktivity/v1/sync',
				method: 'POST',
				data: { type },
			} )
				.then( ( message ) => {
					setSync( ( current ) =>
						type === 'total_runtime'
							? { ...current, runtime: 'in_progress' }
							: { ...current, status: message }
					);
					setNotice( { status: 'success', message } );
				} )
				.catch( failed ),
		[ failed ]
	);

	return {
		step,
		trakt,
		tmdb,
		sync,
		notice,
		removeNotice,
		goToNextStep,
		saveTraktCredentials,
		saveTmdbCredentials,
		launchSync,
	};
}
