/**
 * WordPress dependencies
 */
import { Card, CardBody } from '@wordpress/components';
import { useEffect } from '@wordpress/element';

/**
 * Internal dependencies
 */
import RecentEvents from './RecentEvents';
import StatsOverview from './stats/StatsOverview';
import SyncShowTime from './SyncShowTime';
import settings from '../settings';

/**
 * Step 5: the dashboard shown once setup is complete.
 *
 * @param {Object}   props
 * @param {Object}   props.sync       Current sync state.
 * @param {Function} props.launchSync Starts a sync.
 * @return {Element} The dashboard.
 */
export default function Dashboard( { sync, launchSync } ) {
	const pagesLeft = sync.pages;

	useEffect( () => {
		// Resume a sync that still has pages left to fetch.
		if ( pagesLeft !== 0 ) {
			launchSync();
		}
		// Only ever run this on mount; launchSync is stable and re-running it
		// on every sync state change would restart the sync in a loop.
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	return (
		<>
			<Card>
				<CardBody>
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
				</CardBody>
			</Card>
			<StatsOverview />
			<RecentEvents />
			<SyncShowTime
				sync={ sync }
				launchRuntimeSync={ () => launchSync( 'total_runtime' ) }
			/>
		</>
	);
}
