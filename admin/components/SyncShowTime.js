/* External dependencies */
import React from 'react';
import PropTypes from 'prop-types';

class SyncShowTime extends React.Component {
	render() {
		const syncInfo = this.props.sync;
		const inProgress = syncInfo.runtime === 'in_progress';
		return (
			<div className="sync_settings card">
				<h2 className="card_title">{traktivity_dash.sync_runtime_title}</h2>
				<p>{traktivity_dash.sync_runtime_desc}</p>
				<div className="action">
					<button className="nav-button" disabled={inProgress} onClick={this.props.launchRuntimeSync}>{traktivity_dash.launch_sync}</button>
				</div>
			</div>
		)
	}
}

SyncShowTime.propTypes = {
	sync: PropTypes.object.isRequired,
	launchRuntimeSync: PropTypes.func.isRequired,
};

export default SyncShowTime;
