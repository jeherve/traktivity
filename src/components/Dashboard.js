/**
 * WordPress dependencies
 */
import { Card, CardBody } from '@wordpress/components';
import { useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import RecentEvents from './RecentEvents';
import StatsOverview from './stats/StatsOverview';
import SyncHistory from './SyncHistory';
import SyncShowTime from './SyncShowTime';

/**
 * Step 5: the dashboard shown once setup is complete.
 *
 * @param {Object}   props
 * @param {Object}   props.sync       Current sync state.
 * @param {Function} props.launchSync Starts a sync.
 * @return {Element} The dashboard.
 */
export default function Dashboard( { sync, launchSync } ) {
	const historyImported = sync.status === 'done';

	useEffect( () => {
		/*
		 * Pick a sync back up if it was left part way through. A sync started
		 * from step 4 is kicked off there, not here.
		 *
		 * This follows the recorded status rather than the pages left to go,
		 * since a sync that stopped before it ever read a page count has none
		 * recorded, and so does one whose stale status was cleared on update.
		 * Both of those read as zero pages, which is also what a finished sync
		 * reads as, so the count cannot tell them apart. Neither starts on its
		 * own; the card below is how they get going again.
		 */
		if ( sync.status === 'in_progress' ) {
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
						<strong>
							{ __( 'I am all set! What now?', 'traktivity' ) }
						</strong>
					</p>
					<p>
						{ __(
							'Now that you have added an API from each service, Traktivity will start monitoring your Trakt.tv account. Every hour, it will check your profile to see if you have watched something new. If you have, it will be added to your WordPress site. You will see a new entry under "Trakt.tv Events" in this menu, with tons of details about what you have watched.',
							'traktivity'
						) }
					</p>
					<p>
						<strong>
							{ __(
								'Can I support Trakt.tv? That service is awesome!',
								'traktivity'
							) }
						</strong>
					</p>
					<p>
						{ __(
							"It is! If you'd like to support the Trakt.tv service, you can sign up for a VIP account at trakt.tv/vip. By doing so you will get rid of the ads and unlock lots of VIP features!",
							'traktivity'
						) }
					</p>
					<p>
						<strong>
							{ __(
								'Who is behind this great plugin?',
								'traktivity'
							) }
						</strong>
					</p>
					<p>
						{ __(
							'Traktivity is not endorsed or certified by TMDb or Trakt.tv. It is just a little plugin developed by a TV addict, just like you. :)',
							'traktivity'
						) }
					</p>
				</CardBody>
			</Card>
			<StatsOverview />
			<RecentEvents />
			{ ! historyImported && (
				<SyncHistory
					sync={ sync }
					launchHistorySync={ () => launchSync() }
				/>
			) }
			<SyncShowTime
				sync={ sync }
				launchRuntimeSync={ () => launchSync( 'total_runtime' ) }
			/>
		</>
	);
}
