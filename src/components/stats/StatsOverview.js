/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';

/**
 * Internal dependencies
 */
import settings from '../../settings';

class StatsOverview extends Component {
	render() {
		return (
			<div className="stats_overview card">
				<h2 className="card_title">
					{ settings.stats_overview_title }
				</h2>
				<p>{ settings.tt_watched_desc }</p>
			</div>
		);
	}
}

export default StatsOverview;
