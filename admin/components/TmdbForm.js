/* External dependencies */
import React from 'react';
import Gridicon from 'gridicons';

class TmdbForm extends React.Component {
	constructor() {
		super();

		this.saveTmdbCreds = this.saveTmdbCreds.bind(this);
	}

	saveTmdbCreds(event) {
		event.preventDefault();

		// Send that data back so it can be tested and saved.
		this.props.updateSettings(event.target.name, event.target.value);
	}

	render() {
		const TmdbInfo = this.props.tmdb;
		return (
			<div className="tmdb_settings card">
				<h2 className="card_title">{traktivity_dash.form_tmdb_title}</h2>
				<p>{traktivity_dash.form_tmdb_intro} {traktivity_dash.form_tmdb_intro_opt}</p>
				<p>{traktivity_dash.form_tmdb_create_app}
					<span>
						<a href={traktivity_dash.form_tmdb_api_url} title={traktivity_dash.form_trakt_create_app}>
							<Gridicon size={24} icon="link"/>
						</a>
					</span>
				</p>
				<label htmlFor="username">
					<span>{traktivity_dash.form_tmdb_key}</span>
					<input
						name="tmdb_key"
						defaultValue={TmdbInfo.key}
						type="text"
						placeholder={traktivity_dash.form_tmdb_key}
						onChange={(event) => this.saveTmdbCreds(event)}
					/>
				</label>
			</div>
		)
	}
}

export default TmdbForm;
