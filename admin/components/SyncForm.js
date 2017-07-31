/* External dependencies */
import React from 'react';
import PropTypes from 'prop-types';

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

SyncForm.propTypes = {
	sync: PropTypes.object.isRequired,
	nextStep: PropTypes.func.isRequired,
};

export default SyncForm;
