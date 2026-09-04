/**
 * WordPress dependencies
 */
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Offer to walk back through the Trakt.tv history and import what is missing.
 *
 * Traktivity records what you watch from the moment it is set up. Everything
 * from before that only arrives through this, and a synchronization that was
 * interrupted, gave up, or ran on a version that stopped early leaves the rest
 * of the history sitting on Trakt.tv with nothing scheduled to go and get it.
 *
 * @param {Object}   props
 * @param {Object}   props.sync              Current sync state.
 * @param {Function} props.launchHistorySync Starts the history import.
 * @return {Element} The card.
 */
export default function SyncHistory( { sync, launchHistorySync } ) {
	const [ launched, setLaunched ] = useState( false );

	/*
	 * Anything recorded that is not 'done' means an import is under way, either
	 * one left in progress or the message returned by the request that just
	 * started one.
	 */
	const running = launched || ( !! sync.status && sync.status !== 'done' );

	return (
		<Card>
			<CardHeader>
				<h2>
					{ __(
						'Import everything you watched before installing Traktivity.',
						'traktivity'
					) }
				</h2>
			</CardHeader>
			<CardBody>
				<p>
					{ __(
						'Traktivity logs what you watch from now on. This goes back through your Trakt.tv history and adds everything that is missing, a batch at a time, so a long history takes a while to come in. Events already imported are left alone, so there is no harm in starting this more than once.',
						'traktivity'
					) }
				</p>
				<Button
					variant="secondary"
					disabled={ running }
					isBusy={ running }
					onClick={ () => {
						setLaunched( true );

						/*
						 * Let go of the pending flag either way. A request that
						 * fails leaves the sync status untouched, so holding on
						 * to it would keep the button stuck with nothing to
						 * clear it but a page reload. One that works reports a
						 * status, and that is what keeps the button disabled.
						 */
						Promise.resolve( launchHistorySync() ).finally( () =>
							setLaunched( false )
						);
					} }
				>
					{ __( 'Import past history', 'traktivity' ) }
				</Button>
			</CardBody>
		</Card>
	);
}
