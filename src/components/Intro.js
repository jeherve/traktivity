/**
 * WordPress dependencies
 */
import { Button, Card, CardBody } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

export default function Intro( { nextStep } ) {
	return (
		<Card>
			<CardBody>
				<p>
					<strong>
						{ __(
							"Do you like to go to the movies and would like to remember what movies you saw, and when? Traktivity is for you! Are you a TV addict, and want to keep track of all the shows you've binge-watched? Traktivity is for you!",
							'traktivity'
						) }
					</strong>
				</p>
				<p>
					{ __(
						"This plugin relies on 2 external services to gather information about the things you watch: Trakt.tv is where you'll be marking shows or movies as watched, and The Movie DB is where the plugin will go grab images for each one of those shows or movies.",
						'traktivity'
					) }
				</p>
				<Button variant="primary" onClick={ nextStep }>
					{ __( "Let's get started!", 'traktivity' ) }
				</Button>
			</CardBody>
		</Card>
	);
}
