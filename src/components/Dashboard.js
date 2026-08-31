/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import Notice from './Notice';
import SyncShowTime from './SyncShowTime';
import StatsOverview from './stats/StatsOverview';
import settings from '../settings';

const STORAGE_KEY = 'traktivity_recent_events';

/**
 * Read the cached event list, tolerating both a missing entry and a corrupt one.
 *
 * @return {Array|null} Cached events, or null when there is nothing usable.
 */
function readCachedEvents() {
	try {
		const stored = window.sessionStorage.getItem( STORAGE_KEY );
		const parsed = stored ? JSON.parse( stored ) : null;
		return Array.isArray( parsed ) ? parsed : null;
	} catch {
		return null;
	}
}

class Dashboard extends Component {
	constructor( props ) {
		super( props );

		this.launchRuntimeSync = this.launchRuntimeSync.bind( this );

		this.state = { recent: [] };
	}

	componentDidMount() {
		// Resume a sync that still has pages left to fetch.
		if ( this.props.sync.pages !== 0 ) {
			this.props.launchSync();
		}

		const cached = readCachedEvents();
		if ( cached ) {
			this.setState( { recent: cached } );
			return;
		}

		apiFetch( { path: '/wp/v2/traktivity_event?per_page=6&_embed=1' } )
			.then( ( events ) => {
				try {
					window.sessionStorage.setItem(
						STORAGE_KEY,
						JSON.stringify( events )
					);
				} catch {
					// A full or unavailable sessionStorage should not blank the list.
				}
				this.setState( { recent: events } );
			} )
			.catch( () => {
				// Leaving the list empty is a reasonable failure mode here.
			} );
	}

	launchRuntimeSync() {
		this.props.launchSync( 'total_runtime' );
	}

	renderRecentEvents() {
		const list = this.state.recent
			.map( ( event ) => {
				const media = event._embedded?.[ 'wp:featuredmedia' ]?.[ 0 ];
				const size = media?.media_details?.sizes?.medium;

				if ( ! size ) {
					return null;
				}

				return (
					<a
						href={ event.link }
						title={ event.title.rendered }
						className="event-link"
						key={ event.id }
					>
						<img
							className="event-image"
							src={ size.source_url }
							alt={ media.alt_text }
							width={ size.width }
							height={ size.height }
						/>
						<span className="event-title">
							{ event.title.rendered }
						</span>
					</a>
				);
			} )
			.filter( Boolean );

		return (
			<div className="card event-list">
				<h3 className="list-title">{ settings.recent_list_title }</h3>
				<div className="images">{ list }</div>
			</div>
		);
	}

	render() {
		return (
			<div className="traktivity_dashboard">
				<Notice
					notice={ this.props.notice }
					removeNotice={ this.props.removeNotice }
				/>
				<div className="card_list">
					<div className="card faq">
						<p>
							<strong>{ settings.dashboard_intro_q }</strong>
						</p>
						<p>{ settings.dashboard_intro_a }</p>
						<p>
							<strong>{ settings.dashboard_sup_trakt_q }</strong>
						</p>
						<p>{ settings.dashboard_sup_trakt_a }</p>
						<p>
							<strong>{ settings.dash_faq_who }</strong>
						</p>
						<p>{ settings.trakt_dash_credits }</p>
					</div>
					<StatsOverview />
					{ this.renderRecentEvents() }
					<SyncShowTime
						launchRuntimeSync={ this.launchRuntimeSync }
						sync={ this.props.sync }
					/>
				</div>
			</div>
		);
	}
}

export default Dashboard;
