/* External dependencies */
import React from 'react';

/* Internal dependencies */

class SyncForm extends React.Component {
	render() {
		const syncInfo = this.props.sync;
		return (
			<div className="sync_settings card">
				<h2 className="card_title">{traktivity_dash.sync_title}</h2>
				<p>{traktivity_dash.sync_description}</p>
				<div className="action">
					<button className="nav-button" onClick={this.props.nextStep}>{traktivity_dash.launch_sync}</button>
				</div>
			</div>
		)
	}
}

export default SyncForm;
