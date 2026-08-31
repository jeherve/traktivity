/**
 * WordPress dependencies
 */
import { Button, Card, CardBody } from '@wordpress/components';

/**
 * Internal dependencies
 */
import settings from '../settings';

export default function Intro( { nextStep } ) {
	return (
		<Card>
			<CardBody>
				<p>
					<strong>{ settings.intro }</strong>
				</p>
				<p>{ settings.description }</p>
				<Button variant="primary" onClick={ nextStep }>
					{ settings.intro_next }
				</Button>
			</CardBody>
		</Card>
	);
}
