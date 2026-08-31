/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';

/**
 * Internal dependencies
 */
import settings from '../settings';

class SyncForm extends Component {
	render() {
		return (
			<div className="sync_settings card">
				<h2 className="card_title">{ settings.sync_title }</h2>
				<p>{ settings.sync_description }</p>
				<div className="action">
					<button
						className="nav-button"
						onClick={ this.props.nextStep }
					>
						{ settings.launch_sync }
					</button>
				</div>
			</div>
		);
	}
}

export default SyncForm;
