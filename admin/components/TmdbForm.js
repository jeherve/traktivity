/* External dependencies */
import React from 'react';
import Gridicon from 'gridicons';
import PropTypes from 'prop-types';

/* Internal dependencies */
import Notice from './Notice';

class TmdbForm extends React.Component {
	constructor() {
		super();

		this.saveTmdbCreds = this.saveTmdbCreds.bind(this);
	}

	saveTmdbCreds(event) {
		event.preventDefault();

		// Send that data back so it can be tested and saved.
		this.props.saveCreds(event.target.name, event.target.value);
	}

	render() {
		const TmdbInfo = this.props.tmdb;
		const canContinue = TmdbInfo.valid === true;
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
				<Notice
					notice={this.props.notice}
					removeNotice={this.props.removeNotice}
				/>
				<div className="action">
					<button className="nav-button secondary" onClick={this.props.nextStep}>{traktivity_dash.button_skip}</button>
					<button className="nav-button" disabled={!canContinue} onClick={this.props.nextStep}>{traktivity_dash.button_next}</button>
				</div>
			</div>
		)
	}
}

TmdbForm.propTypes = {
	tmdb: PropTypes.object.isRequired,
	saveCreds: PropTypes.func.isRequired,
	notice: PropTypes.object.isRequired,
	removeNotice: PropTypes.func.isRequired,
	nextStep: PropTypes.func.isRequired,
};

export default TmdbForm;
