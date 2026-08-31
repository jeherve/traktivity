/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import Header from './Header';
import Intro from './Intro';
import TraktForm from './TraktForm';
import TmdbForm from './TmdbForm';
import SyncForm from './SyncForm';
import Dashboard from './Dashboard';
import settings from '../settings';

const LAST_STEP = 5;

/**
 * Run a credential check, treating an error status as a result rather than a
 * failure. The REST endpoints answer with { code, message } either way, and
 * both are worth showing to the user.
 *
 * @param {string} path REST path to call.
 * @return {Promise<Object>} The endpoint's { code, message } payload.
 */
function checkCredentials( path ) {
	return apiFetch( { path } ).catch( ( error ) => error );
}

class Setup extends Component {
	constructor( props ) {
		super( props );

		this.nextStep = this.nextStep.bind( this );
		this.removeNotice = this.removeNotice.bind( this );
		this.saveCreds = this.saveCreds.bind( this );
		this.launchSync = this.launchSync.bind( this );

		this.state = {
			trakt: {
				username: settings.trakt_username || '',
				key: settings.trakt_key || '',
			},
			tmdb: {
				key: settings.tmdb_key || '',
			},
			notice: null,
			step: parseInt( settings.traktivity_step, 10 ) || 1,
			sync: {
				status: settings.sync_status || '',
				pages: parseInt( settings.sync_pages, 10 ) || 0,
				runtime: settings.sync_runtime || '',
			},
		};
	}

	/**
	 * Persist the current credentials and step to the database.
	 *
	 * @param {Object} payload Settings to store.
	 * @return {Promise} The save request.
	 */
	saveSettings( payload ) {
		return apiFetch( {
			path: '/traktivity/v1/settings/edit',
			method: 'POST',
			data: payload,
		} );
	}

	nextStep() {
		// If we are already at the last step, let's stop right here.
		if ( this.state.step === LAST_STEP ) {
			return Promise.resolve();
		}

		const step = this.state.step + 1;

		return this.saveSettings( { ...this.state, step } )
			.then( () => this.setState( { step } ) )
			.catch( ( error ) => this.showError( error ) );
	}

	saveCreds( name, value ) {
		const trakt = { ...this.state.trakt };
		const tmdb = { ...this.state.tmdb };

		if ( name === 'username' ) {
			trakt.username = value;
		} else if ( name === 'key' ) {
			trakt.key = value;
		} else if ( name === 'tmdb_key' ) {
			tmdb.key = value;
		}

		this.setState( { trakt, tmdb } );

		return this.saveSettings( { ...this.state, trakt, tmdb } )
			.then( () => {
				this.setState( {
					notice: {
						message: settings.notice_saved,
						type: 'success',
					},
				} );

				if (
					( name === 'username' || name === 'key' ) &&
					trakt.username &&
					trakt.key
				) {
					return this.checkTraktCreds( trakt.username, trakt.key );
				}

				if ( name === 'tmdb_key' && tmdb.key ) {
					return this.checkTmdbCreds( tmdb.key );
				}

				return null;
			} )
			.catch( ( error ) => this.showError( error ) );
	}

	checkTraktCreds( username, key ) {
		return checkCredentials(
			`/traktivity/v1/connection/${ username }/${ key }`
		).then( ( body ) =>
			this.setState( {
				notice: {
					message: body.message,
					type: body.code === 200 ? 'success' : 'error',
				},
				trakt: { ...this.state.trakt, valid: body.code === 200 },
			} )
		);
	}

	checkTmdbCreds( key ) {
		return checkCredentials( `/traktivity/v1/tmdb/${ key }` ).then(
			( body ) =>
				this.setState( {
					notice: {
						message: body.message,
						type: body.code === 200 ? 'success' : 'error',
					},
					tmdb: { ...this.state.tmdb, valid: body.code === 200 },
				} )
		);
	}

	launchSync( type = null ) {
		return apiFetch( {
			path: '/traktivity/v1/sync',
			method: 'POST',
			data: { type },
		} )
			.then( ( body ) => {
				const sync = { ...this.state.sync };

				if ( type === 'total_runtime' ) {
					sync.runtime = 'in_progress';
				} else {
					sync.status = body;
				}

				this.setState( {
					notice: { message: body, type: 'success' },
					sync,
				} );
			} )
			.catch( ( error ) => this.showError( error ) );
	}

	showError( error ) {
		this.setState( {
			notice: {
				message: error?.message || settings.notice_error,
				type: 'error',
			},
		} );
	}

	removeNotice() {
		this.setState( { notice: null } );
	}

	displayStep() {
		const shared = {
			step: this.state.step,
			nextStep: this.nextStep,
			notice: this.state.notice,
			removeNotice: this.removeNotice,
		};

		switch ( this.state.step ) {
			case 1:
				return <Intro { ...shared } />;
			case 2:
				return (
					<TraktForm
						{ ...shared }
						trakt={ this.state.trakt }
						saveCreds={ this.saveCreds }
					/>
				);
			case 3:
				return (
					<TmdbForm
						{ ...shared }
						tmdb={ this.state.tmdb }
						saveCreds={ this.saveCreds }
					/>
				);
			case 4:
				return (
					<SyncForm
						{ ...shared }
						sync={ this.state.sync }
						launchSync={ this.launchSync }
					/>
				);
			default:
				return (
					<Dashboard
						{ ...shared }
						sync={ this.state.sync }
						launchSync={ this.launchSync }
					/>
				);
		}
	}

	render() {
		return (
			<div className={ `traktivity_dashboard step-${ this.state.step }` }>
				<Header />
				<div className="card_list">{ this.displayStep() }</div>
			</div>
		);
	}
}

export default Setup;
