/* External dependencies */
import React from 'react';
import Gridicon from 'gridicons';

class TraktForm extends React.Component {
	constructor() {
		super();

		this.saveTraktCreds = this.saveTraktCreds.bind(this);
	}

	saveTraktCreds(event) {
		event.preventDefault();

		// Send that data back so it can be tested and saved.
		this.props.updateSettings(event.target.name, event.target.value);
	}

	render() {
		const traktInfo = this.props.trakt;
		return (
			<div className="trakt_settings card">
				<h2 className="card_title">{traktivity_dash.form_trakt_title}</h2>
				<p>
					{traktivity_dash.form_trakt_intro}
					<span>
						<a href={traktivity_dash.form_trakt_api_url} title={traktivity_dash.form_trakt_create_app}>
							<Gridicon size={24} icon="link"/>
						</a>
					</span>
				</p>
				<p>{traktivity_dash.form_trakt_api_options}</p>
				<p>{traktivity_dash.form_trakt_api_fields}</p>
				<label htmlFor="username">
					<span>{traktivity_dash.form_trakt_username}</span>
					<input
						name="username"
						defaultValue={traktInfo.username}
						type="text"
						placeholder={traktivity_dash.form_trakt_username}
						required
						onChange={(event) => this.saveTraktCreds(event)}
					/>
				</label>
				<label htmlFor="key">
					<span>{traktivity_dash.form_trakt_key}</span>
					<input
						name="key"
						defaultValue={traktInfo.key}
						type="text"
						placeholder={traktivity_dash.form_trakt_key}
						required
						onChange={(event) => this.saveTraktCreds(event)}
					/>
				</label>
			</div>
		)
	}
}

export default TraktForm;
