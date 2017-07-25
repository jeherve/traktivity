/* External dependencies */
import React from 'react';

/* Internal dependencies */
import Header from './Header';
import Nav from './Nav.js';
import Footer from './Footer';
import TraktForm from './TraktForm';
import TmdbForm from './TmdbForm';
import Notice from './Notice';

class Dashboard extends React.Component {
	constructor() {
		super();

		this.updateSettings = this.updateSettings.bind(this);
		this.removeNotice = this.removeNotice.bind(this);
		this.checkTraktCreds = this.checkTraktCreds.bind(this);
		this.checkTmdbCreds = this.checkTmdbCreds.bind(this);

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
			samples: {},
		}
	}

	updateSettings( name, value ) {
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
		const settingsPromise = fetch( `${traktivity_dash.api_url}/traktivity/v1/settings/edit`, postOptions );
		return settingsPromise
			.then((response) => {
				if (response.status === 200 ) {
					this.setState({ notice: {
						message: 'Changes have been saved.',
						type: 'success',
					}});
				} else {
					this.setState({ notice: {
						message: 'Changes could not be saved.',
						type: 'error',
					}});
				}
			})
			.then((response) => {
				if ( settings.trakt.username && settings.trakt.key ) {
					this.checkTraktCreds(settings.trakt.username, settings.trakt.key);
				}
			})
			.then((response) => {
				if ( settings.tmdb.key ) {
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
		const checkCredsPromise = fetch( `${traktivity_dash.api_url}/traktivity/v1/connection/${username}/${key}`, fetchOptions );
		return checkCredsPromise
			.then((response) => response.json())
			.then((body) => {
				this.setState({ notice: {
					message: body.message,
					type: `${body.code === 200 ? 'success' : 'error'}`,
				}});
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
		const checkCredsPromise = fetch( `${traktivity_dash.api_url}/traktivity/v1/tmdb/${key}`, fetchOptions );
		return checkCredsPromise
			.then((response) => response.json())
			.then((body) => {
				this.setState({
					notice: {
						message: body.message,
						type: `${body.code === 200 ? 'success' : 'error'}`,
					},
					samples: {...body.samples}
				});
			})
			.catch((err) => {
				this.setState({ notice: {
					message: `${err}`,
					type: 'error',
				}});
			});
	}

	removeNotice() {
		this.setState({ notice: null });
	}

	render() {
		return (
			<div className="traktivity_dashboard">
				<Header />
				<Nav />
				<Notice
					notice={this.state.notice}
					removeNotice={this.removeNotice}
				/>
				<div className="card_list">
					<TraktForm
						trakt={this.state.trakt}
						updateSettings={this.updateSettings}
					/>
					<TmdbForm
						tmdb={this.state.tmdb}
						updateSettings={this.updateSettings}
					/>
				</div>
				<Footer />
			</div>
		)
	}
}

export default Dashboard;
