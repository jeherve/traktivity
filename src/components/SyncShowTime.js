/**
 * WordPress dependencies
 */
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function SyncShowTime( { sync, launchRuntimeSync } ) {
	const inProgress = sync.runtime === 'in_progress';

	return (
		<Card>
			<CardHeader>
				<h2>
					{ __(
						'Recalculate total runtime for each one of the series you have watched.',
						'traktivity'
					) }
				</h2>
			</CardHeader>
			<CardBody>
				<p>
					{ __(
						'If you used the Traktivity plugin before version 2.1.0 was released, it did not track the amount of time you had spent watching each series. This form allows you to recalculate runtime for all your series at once.',
						'traktivity'
					) }
				</p>
				<Button
					variant="secondary"
					disabled={ inProgress }
					isBusy={ inProgress }
					onClick={ launchRuntimeSync }
				>
					{ __( 'Start synchronization', 'traktivity' ) }
				</Button>
			</CardBody>
		</Card>
	);
}
