/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';

/**
 * Internal dependencies
 */
import settings from '../settings';

class SyncShowTime extends Component {
	render() {
		const inProgress = this.props.sync.runtime === 'in_progress';

		return (
			<div className="sync_settings card">
				<h2 className="card_title">{ settings.sync_runtime_title }</h2>
				<p>{ settings.sync_runtime_desc }</p>
				<div className="action">
					<button
						className="nav-button"
						disabled={ inProgress }
						onClick={ this.props.launchRuntimeSync }
					>
						{ settings.launch_sync }
					</button>
				</div>
			</div>
		);
	}
}

export default SyncShowTime;
