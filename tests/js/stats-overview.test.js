/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';

/**
 * Internal dependencies
 */
import StatsOverview from '../../src/components/stats/StatsOverview';
import { resetSettings } from './settings-mock';

jest.mock( '../../src/settings', () => require( './settings-mock' ) );

describe( 'StatsOverview', () => {
	it( 'shows the duration PHP formatted for it', () => {
		resetSettings( { totalTimeWatched: '3 days, 4 hours' } );

		render( <StatsOverview /> );

		expect(
			screen.getByText(
				'You have already spent 3 days, 4 hours watching movies and TV series. Congrats!'
			)
		).toBeInTheDocument();
	} );

	it( 'falls back to a vague phrase before any sync has run', () => {
		resetSettings( { totalTimeWatched: '' } );

		render( <StatsOverview /> );

		expect(
			screen.getByText(
				'You have already spent quite some time watching movies and TV series. Congrats!'
			)
		).toBeInTheDocument();
	} );
} );
