/* External dependencies */
import React from 'react';

/* Internal dependencies */
import Header from './Header';
import Footer from './Footer';

import Intro from './Intro';
import TraktForm from './TraktForm';
import TmdbForm from './TmdbForm';
import SyncForm from './SyncForm';
import Dashboard from './Dashboard';

class Setup extends React.Component {
	constructor() {
		super();

		// Setup wizard
		this.nextStep = this.nextStep.bind(this);
		this.displayStep = this.displayStep.bind(this);

		// Notices
		this.removeNotice = this.removeNotice.bind(this);

		// API calls to check creds.
		this.saveCreds = this.saveCreds.bind(this);
		this.checkTraktCreds = this.checkTraktCreds.bind(this);
		this.checkTmdbCreds = this.checkTmdbCreds.bind(this);

		// Sync screen.
		this.launchSync = this.launchSync.bind(this);

		// Initial state.
		this.state = {
			trakt: {
				username: `${traktivity_dash.trakt_username}`,
				key: `${traktivity_dash.trakt_key}`,
			},
			tmdb: {
				key: `${traktivity_dash.tmdb_key}`,
			},
			notice: null,
			step: parseInt(traktivity_dash.traktivity_step),
			sync: {
				status: `${traktivity_dash.sync_status}`,
				pages: parseInt(traktivity_dash.sync_pages),
				runtime: `${traktivity_dash.sync_runtime}`,
			},
		}
	}

	nextStep() {
		// Get a copy of the existing state.
		let settings = {...this.state};

		// If we are already at the last step, let's stop right here.
		if ( this.state.step === 5 ) {
			return this.state.step;
		}

		// Increment the step counter.
		settings.step = this.state.step + 1;

		// Save in db.
		const postOptions = {
			credentials: 'same-origin',
			method: 'POST',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'X-WP-Nonce': traktivity_dash.api_nonce
			},
			body: JSON.stringify(settings),
		};
		const settingsPromise = fetch( `${traktivity_dash.api_url}traktivity/v1/settings/edit`, postOptions );
		return settingsPromise
			.then((response) => {
				if (response.status === 200 ) {
					// Save our new state.
					this.setState({ step: settings.step });
				}
			})
			.catch((err) => {
				this.setState({ notice: {
					message: `${err}`,
					type: 'error',
				}});
			});
	}

	displayStep() {
		switch (this.state.step) {
			case 1:
				return <Intro
							step={this.state.step}
							nextStep={this.nextStep}
						/>;
			case 2:
				return <TraktForm
							step={this.state.step}
							nextStep={this.nextStep}
							trakt={this.state.trakt}
							saveCreds={this.saveCreds}
							notice={this.state.notice}
							removeNotice={this.removeNotice}
						/>;
			case 3:
				return <TmdbForm
							step={this.state.step}
							nextStep={this.nextStep}
							tmdb={this.state.tmdb}
							saveCreds={this.saveCreds}
							notice={this.state.notice}
							removeNotice={this.removeNotice}
						/>;
			case 4:
				return <SyncForm
							step={this.state.step}
							nextStep={this.nextStep}
							notice={this.state.notice}
							removeNotice={this.removeNotice}
							sync={this.state.sync}
							launchSync={this.launchSync}
						/>;
			case 5:
				return <Dashboard
							step={this.state.step}
							nextStep={this.nextStep}
							notice={this.state.notice}
							removeNotice={this.removeNotice}
							sync={this.state.sync}
							launchSync={this.launchSync}
						/>;
		}
	}

	removeNotice() {
		this.setState({ notice: null });
	}

	saveCreds( name, value ) {
		// Name can be username, key, or tmdb_key
		// Get a copy of the existing state.
		let settings = {...this.state};

		// Add in the data we got from the form.
		if ( name === 'username' ) {
			settings.trakt.username = value;
		} else if ( name === 'key' ) {
			settings.trakt.key = value;
		} else if ( name === 'tmdb_key' ) {
			settings.tmdb.key = value;
		}

		// Save our state.
		this.setState({
			trakt: settings.trakt,
			tmdb: settings.tmdb
		});

		// Save in db as well.
		const postOptions = {
			credentials: 'same-origin',
			method: 'POST',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'X-WP-Nonce': traktivity_dash.api_nonce
			},
			body: JSON.stringify(
				settings
			),
		};
		const settingsPromise = fetch( `${traktivity_dash.api_url}traktivity/v1/settings/edit`, postOptions );
		return settingsPromise
			.then((response) => {
				if (response.status === 200 ) {
					this.setState({ notice: {
						message: traktivity_dash.notice_saved,
						type: 'success',
					}});
				} else {
					this.setState({ notice: {
						message: traktivity_dash.notice_error,
						type: 'error',
					}});
				}
			})
			.then((response) => {
				if (
					( name === 'username' || name === 'key' )
					&& settings.trakt.username
					&& settings.trakt.key
				) {
					this.checkTraktCreds(settings.trakt.username, settings.trakt.key);
				}
			})
			.then((response) => {
				if (
					name === 'tmdb_key'
					&& settings.tmdb.key
				) {
					this.checkTmdbCreds(settings.tmdb.key);
				}
			})
			.catch((err) => {
				this.setState({ notice: {
					message: `${err}`,
					type: 'error',
				}});
			});
	}

	checkTraktCreds( username, key ) {
		const fetchOptions = {
			credentials: 'same-origin',
			method: 'GET',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'X-WP-Nonce': traktivity_dash.api_nonce
			},
		};
		const checkCredsPromise = fetch( `${traktivity_dash.api_url}traktivity/v1/connection/${username}/${key}`, fetchOptions );
		return checkCredsPromise
			.then((response) => response.json())
			.then((body) => {
				// Grab a copy of our trakt state.
				let checkedTrack = {...this.state.trakt};
				if ( body.code === 200 ) {
					checkedTrack.valid = true;
				} else {
					checkedTrack.valid = false;
				}

				this.setState({
					notice: {
						message: body.message,
						type: `${body.code === 200 ? 'success' : 'error'}`,
					},
					trakt: checkedTrack
				});
			})
			.catch((err) => {
				this.setState({ notice: {
					message: `${err}`,
					type: 'error',
				}});
			});
	}

	checkTmdbCreds( key ) {
		const fetchOptions = {
			credentials: 'same-origin',
			method: 'GET',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'X-WP-Nonce': traktivity_dash.api_nonce
			},
		};
		const checkCredsPromise = fetch( `${traktivity_dash.api_url}traktivity/v1/tmdb/${key}`, fetchOptions );
		return checkCredsPromise
			.then((response) => response.json())
			.then((body) => {
				// Grab a copy of our trakt state.
				let checkedTmdb = {...this.state.tmdb};
				if ( body.code === 200 ) {
					checkedTmdb.valid = true;
				} else {
					checkedTmdb.valid = false;
				}
				this.setState({
					notice: {
						message: body.message,
						type: `${body.code === 200 ? 'success' : 'error'}`,
					},
					tmdb: checkedTmdb
				});
			})
			.catch((err) => {
				this.setState({ notice: {
					message: `${err}`,
					type: 'error',
				}});
			});
	}

	launchSync( type = null ) {
		const postOptions = {
			credentials: 'same-origin',
			method: 'POST',
			headers: {
				'Accept': 'application/json',
				'Content-Type': 'application/json',
				'X-WP-Nonce': traktivity_dash.api_nonce
			},
			body: JSON.stringify(
				{type}
			),
		};
		const syncPromise = fetch( `${traktivity_dash.api_url}traktivity/v1/sync`, postOptions );
		return syncPromise
			.then((response) => response.json())
			.then((body) => {
				// Get a copy of our state.
				let sync = {...this.state.sync};

				// Change the status to the matching body.
				if ( type === 'total_runtime' ) {
					sync.runtime = 'in_progress';
				} else {
					sync.status = body;
				}

				this.setState({
					notice: {
						message: body,
						type: 'success',
					},
					sync
				});
			})
			.catch((err) => {
				this.setState({ notice: {
					message: `${err}`,
					type: 'error',
				}});
			})
	}

	render() {
		return (
			<div className={`traktivity_dashboard step-${this.state.step}`}>
				<Header step={this.state.step}/>
				<div className="card_list">
					{this.displayStep()}
				</div>
				<Footer />
			</div>
		)
	}
}

export default Setup;
