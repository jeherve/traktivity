/**
 * WordPress dependencies
 */
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';

/**
 * Internal dependencies
 */
import settings from '../settings';

export default function SyncForm( { nextStep } ) {
	return (
		<Card>
			<CardHeader>
				<h2>{ settings.sync_title }</h2>
			</CardHeader>
			<CardBody>
				<p>{ settings.sync_description }</p>
				<Button variant="primary" onClick={ nextStep }>
					{ settings.launch_sync }
				</Button>
			</CardBody>
		</Card>
	);
}
