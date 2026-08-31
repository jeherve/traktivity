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

class TraktForm extends Component {
	constructor() {
		super();

		this.saveTraktCreds = this.saveTraktCreds.bind( this );
	}

	saveTraktCreds( event ) {
		event.preventDefault();

		// Send that data back so it can be tested and saved.
		this.props.saveCreds( event.target.name, event.target.value );
	}

	render() {
		const traktInfo = this.props.trakt;
		const canContinue = traktInfo.valid === true;
		return (
			<div className="trakt_settings card">
				<h2 className="card_title">{ settings.form_trakt_title }</h2>
				<p>
					{ settings.form_trakt_intro }
					<span>
						<a
							href={ settings.form_trakt_api_url }
							title={ settings.form_trakt_create_app }
						>
							<Icon size={ 24 } icon={ external } />
						</a>
					</span>
				</p>
				<p>{ settings.form_trakt_api_options }</p>
				<p>{ settings.form_trakt_api_fields }</p>
				<label htmlFor="username">
					<span>{ settings.form_trakt_username }</span>
					<input
						name="username"
						defaultValue={ traktInfo.username }
						type="text"
						placeholder={ settings.form_trakt_username }
						required
						onChange={ ( event ) => this.saveTraktCreds( event ) }
					/>
				</label>
				<label htmlFor="key">
					<span>{ settings.form_trakt_key }</span>
					<input
						name="key"
						defaultValue={ traktInfo.key }
						type="text"
						placeholder={ settings.form_trakt_key }
						required
						onChange={ ( event ) => this.saveTraktCreds( event ) }
					/>
				</label>
				<Notice
					notice={ this.props.notice }
					removeNotice={ this.props.removeNotice }
				/>
				<div className="action">
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

export default TraktForm;
