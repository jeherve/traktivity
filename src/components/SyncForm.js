/**
 * WordPress dependencies
 */
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function SyncForm( { nextStep } ) {
	return (
		<Card>
			<CardHeader>
				<h2>
					{ __(
						'You did it! Traktivity will now start logging all the movies and TV shows you watch.',
						'traktivity'
					) }
				</h2>
			</CardHeader>
			<CardBody>
				<p>
					{ __(
						"One more thing: by default, Traktivity only gathers data about the last 10 things you've watched, and then automatically logs all future things you'll watch. Thanks to the button below, you can launch a full synchronization of all the things you've ever watched. It can take a while, though!",
						'traktivity'
					) }
				</p>
				<Button variant="primary" onClick={ nextStep }>
					{ __( 'Start synchronization', 'traktivity' ) }
				</Button>
			</CardBody>
		</Card>
	);
}
