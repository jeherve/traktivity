/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';
import { Icon, external } from '@wordpress/icons';

/**
 * Internal dependencies
 */
import Notice from './Notice';
import settings from '../settings';

class TmdbForm extends Component {
	constructor() {
		super();

		this.saveTmdbCreds = this.saveTmdbCreds.bind( this );
	}

	saveTmdbCreds( event ) {
		event.preventDefault();

		// Send that data back so it can be tested and saved.
		this.props.saveCreds( event.target.name, event.target.value );
	}

	render() {
		const TmdbInfo = this.props.tmdb;
		const canContinue = TmdbInfo.valid === true;
		return (
			<div className="tmdb_settings card">
				<h2 className="card_title">{ settings.form_tmdb_title }</h2>
				<p>
					{ settings.form_tmdb_intro }{ ' ' }
					{ settings.form_tmdb_intro_opt }
				</p>
				<p>
					{ settings.form_tmdb_create_app }
					<span>
						<a
							href={ settings.form_tmdb_api_url }
							title={ settings.form_trakt_create_app }
						>
							<Icon size={ 24 } icon={ external } />
						</a>
					</span>
				</p>
				<label htmlFor="username">
					<span>{ settings.form_tmdb_key }</span>
					<input
						name="tmdb_key"
						defaultValue={ TmdbInfo.key }
						type="text"
						placeholder={ settings.form_tmdb_key }
						onChange={ ( event ) => this.saveTmdbCreds( event ) }
					/>
				</label>
				<Notice
					notice={ this.props.notice }
					removeNotice={ this.props.removeNotice }
				/>
				<div className="action">
					<button
						className="nav-button secondary"
						onClick={ this.props.nextStep }
					>
						{ settings.button_skip }
					</button>
					<button
						className="nav-button"
						disabled={ ! canContinue }
						onClick={ this.props.nextStep }
					>
						{ settings.button_next }
					</button>
				</div>
			</div>
		);
	}
}

export default TmdbForm;
