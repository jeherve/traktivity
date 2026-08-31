/**
 * WordPress dependencies
 */
import { Card, CardBody, CardHeader } from '@wordpress/components';

/**
 * Internal dependencies
 */
import settings from '../../settings';

export default function StatsOverview() {
	return (
		<Card>
			<CardHeader>
				<h2>{ settings.stats_overview_title }</h2>
			</CardHeader>
			<CardBody>
				<p>{ settings.tt_watched_desc }</p>
			</CardBody>
		</Card>
	);
}
