/* External dependencies */
import React from 'react';
import PropTypes from 'prop-types';

class StatsOverview extends React.Component {
	render() {
		return (
			<div className="stats_overview card">
				<h2 className="card_title">{traktivity_dash.stats_overview_title}</h2>
				<p>{traktivity_dash.tt_watched_desc}</p>
				{ /* Mayve in the future we will use the value in a graph. {this.props.stats.total_time_watched} */ }
			</div>
		)
	}
}

StatsOverview.propTypes = {
	stats: PropTypes.object.isRequired,
};

export default StatsOverview;
