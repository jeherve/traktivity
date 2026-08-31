/**
 * WordPress dependencies
 */
import { Button, Card, CardBody, CardHeader } from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Step 4: offer to back-fill everything watched so far.
 *
 * @param {Object}   props
 * @param {Function} props.launchSync Starts the full sync.
 * @param {Function} props.nextStep   Advances to the dashboard.
 * @return {Element} The form.
 */
export default function SyncForm( { launchSync, nextStep } ) {
	const [ isBusy, setIsBusy ] = useState( false );

	const onClick = async () => {
		setIsBusy( true );
		await launchSync();
		await nextStep();
	};

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
				<Button
					variant="primary"
					isBusy={ isBusy }
					disabled={ isBusy }
					onClick={ onClick }
				>
					{ __( 'Start synchronization', 'traktivity' ) }
				</Button>
			</CardBody>
		</Card>
	);
}
