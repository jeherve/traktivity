/* External dependencies */
import React from 'react';
import WPAPI from 'wpapi';
import PropTypes from 'prop-types';

/* Internal dependencies */
import Notice from './Notice';
import routes from '../routes.json';
import SyncShowTime from './SyncShowTime';
import StatsOverview from './stats/StatsOverview';

class Dashboard extends React.Component {
	constructor() {
		super();

		this.renderRecentEvents = this.renderRecentEvents.bind(this);
		this.launchRuntimeSync = this.launchRuntimeSync.bind(this);

		/* API Data */
		this.site = new WPAPI({
			endpoint: traktivity_dash.api_url,
			routes: routes
		});

		// Initial state.
		this.state = {
			recent : [],
			stats: {
				total_time_watched: `${traktivity_dash.total_time_watched}`,
			},
		};
	}

	componentWillMount() {
		// Launch sync if needed.
		const syncStatus = this.props.sync;
		if ( this.props.sync.pages != 0 ) {
			this.props.launchSync();
		}

		// Check if we have some samples saved in sessionStorage.
		const storedEvents = sessionStorage.getItem( 'traktivity_recent_events' );
		// If not, let's query for a fresh recent event list and save it.
		if ( ! storedEvents ) {
			const recentEvents = this.site.traktivity_event()
				.embed()
				.param( 'per_page', 6 )
				.then((data) => {
					sessionStorage.setItem( 'traktivity_recent_events', JSON.stringify( data ) );
					this.setState({ recent: data });
				});
		}
	}

	componentDidMount() {
		// Check if we have some samples saved in localStorage.
		const recentEvents = sessionStorage.getItem( 'traktivity_recent_events' );

		if ( recentEvents ) {
			this.setState({ recent: JSON.parse(recentEvents) });
		}
	}

	renderRecentEvents() {
		const events = this.state.recent;

		const list = events.map(event => {
			if ( event._embedded['wp:featuredmedia'] != null ) {
				return (
					<a href={event.link} title={event.title.rendered} className="event-link" key={event.id}>
						<img
							className="event-image"
							src={event._embedded['wp:featuredmedia'][0].media_details.sizes.medium.source_url}
							alt={event._embedded['wp:featuredmedia'][0].alt_text}
							width={event._embedded['wp:featuredmedia'][0].media_details.sizes.medium.width}
							height={event._embedded['wp:featuredmedia'][0].media_details.sizes.medium.height}
						/>
					<span className="event-title">{event.title.rendered}</span>
					</a>
				)
			}
		});

		return (
			<div className="card event-list">
				<h3 className="list-title">{traktivity_dash.recent_list_title}</h3>
				<div className="images">{list}</div>
			</div>
		)
	}

	launchRuntimeSync() {
		this.props.launchSync('total_runtime');
	}

	render() {
		return (
			<div className="traktivity_dashboard">
				<Notice
					notice={this.props.notice}
					removeNotice={this.props.removeNotice}
				/>
				<div className="card_list">
					<div className="card faq">
						<p><strong>{traktivity_dash.dashboard_intro_q}</strong></p>
						<p>{traktivity_dash.dashboard_intro_a}</p>
						<p><strong>{traktivity_dash.dashboard_sup_trakt_q}</strong></p>
						<p>{traktivity_dash.dashboard_sup_trakt_a}</p>
						<p><strong>{traktivity_dash.dash_faq_who}</strong></p>
						<p>{traktivity_dash.trakt_dash_credits}</p>
					</div>
					<StatsOverview
						stats={this.state.stats}
					/>
					{this.renderRecentEvents()}
					<SyncShowTime
						launchRuntimeSync={this.launchRuntimeSync}
						sync={this.props.sync}
					/>
				</div>
			</div>
		)
	}
}

Dashboard.propTypes = {
	sync: PropTypes.object.isRequired,
	launchSync: PropTypes.func.isRequired,
	notice: PropTypes.object,
	removeNotice: PropTypes.func.isRequired,
};

export default Dashboard;
