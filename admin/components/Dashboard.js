/* External dependencies */
import React from 'react';

/* Internal dependencies */
import Header from './Header';
import Nav from './Nav.js';
import Footer from './Footer';
import TraktForm from './TraktForm';
import TmdbForm from './TmdbForm';

class Dashboard extends React.Component {
	constructor() {
		super();

		this.updateSettings = this.updateSettings.bind(this);

		// Initial state.
		this.state = {
			trakt: {
				username: `${traktivity_dash.trakt_username}`,
				key: `${traktivity_dash.trakt_key}`,
			},
			tmdb: {
				key: `${traktivity_dash.tmdb_key}`,
			},
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

		// Save in options as well.
		let postResults = {}; // I don't do anything with this yet, but it would be nice to output it upon save.
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
					return Promise.resolve(response);
				} else {
					return Promise.reject(new Error(response.statusText));
				}
			})
			// .then((response) => response.json())
			// .then((response) => {
			// 	console.log(response);
			// })
			.catch((err) => console.error(err));
	}

	render() {
		return (
			<div className="traktivity_dashboard">
				<Header />
				<Nav />
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
