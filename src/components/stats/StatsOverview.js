/**
 * WordPress dependencies
 */
import { Card, CardBody, CardHeader } from '@wordpress/components';
import { __, sprintf } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import settings from '../../settings';

export default function StatsOverview() {
	// PHP formats the duration, because pluralising days and hours needs _n().
	const watched =
		settings.totalTimeWatched || __( 'quite some time', 'traktivity' );

	return (
		<Card>
			<CardHeader>
				<h2>{ __( 'In a nutshell', 'traktivity' ) }</h2>
			</CardHeader>
			<CardBody>
				<p>
					{ sprintf(
						/* translators: %s is a duration, already formatted, such as "3 days, 4 hours". */
						__(
							'You have already spent %s watching movies and TV series. Congrats!',
							'traktivity'
						),
						watched
					) }
				</p>
			</CardBody>
		</Card>
	);
}
