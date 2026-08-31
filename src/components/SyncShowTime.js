/**
 * WordPress dependencies
 */
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';

/**
 * Internal dependencies
 */
import settings from '../settings';

export default function SyncShowTime( { sync, launchRuntimeSync } ) {
	const inProgress = sync.runtime === 'in_progress';

	return (
		<Card>
			<CardHeader>
				<h2>{ settings.sync_runtime_title }</h2>
			</CardHeader>
			<CardBody>
				<p>{ settings.sync_runtime_desc }</p>
				<Button
					variant="secondary"
					disabled={ inProgress }
					isBusy={ inProgress }
					onClick={ launchRuntimeSync }
				>
					{ settings.launch_sync }
				</Button>
			</CardBody>
		</Card>
	);
}
