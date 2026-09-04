/**
 * External dependencies
 */
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import Dashboard from '../../src/components/Dashboard';
import { resetSettings } from './settings-mock';

jest.mock( '../../src/settings', () => require( './settings-mock' ) );

/*
 * The dashboard's other cards fetch on mount and have tests of their own. Stub
 * them so what is asserted here is the dashboard's own behaviour.
 */
jest.mock( '../../src/components/RecentEvents', () => () => null );
jest.mock( '../../src/components/stats/StatsOverview', () => () => null );

/**
 * Render the finished-setup dashboard with a given sync state.
 *
 * @param {Object} sync Sync state to render with.
 * @return {Function} The launchSync spy the dashboard was given.
 */
function renderDashboard( sync ) {
	resetSettings();

	const launchSync = jest.fn();

	render(
		<Dashboard
			sync={ { status: '', pages: 0, runtime: '', ...sync } }
			launchSync={ launchSync }
		/>
	);

	return launchSync;
}

describe( 'Dashboard', () => {
	it( 'picks a sync back up when one was left in progress', () => {
		const launchSync = renderDashboard( {
			status: 'in_progress',
			pages: 12,
		} );

		expect( launchSync ).toHaveBeenCalled();
	} );

	it( 'does not start a sync on its own once one has finished', () => {
		const launchSync = renderDashboard( { status: 'done', pages: 0 } );

		expect( launchSync ).not.toHaveBeenCalled();
	} );

	/*
	 * An interrupted sync can be sitting on no recorded pages: one that gave up
	 * before it ever read a page count, or one whose stale status was cleared
	 * on update so the history could be imported again. Neither should start on
	 * its own, and both need a way to be started by hand.
	 */
	it( 'offers to import past history when no sync has finished', async () => {
		const user = userEvent.setup();
		const launchSync = renderDashboard( { status: '', pages: 0 } );

		expect( launchSync ).not.toHaveBeenCalled();

		await user.click(
			screen.getByRole( 'button', { name: 'Import past history' } )
		);

		expect( launchSync ).toHaveBeenCalled();
	} );

	it( 'does not offer the import once the history is in', () => {
		renderDashboard( { status: 'done', pages: 0 } );

		expect(
			screen.queryByRole( 'button', { name: 'Import past history' } )
		).not.toBeInTheDocument();
	} );

	it( 'shows the import as running while one is under way', () => {
		renderDashboard( { status: 'in_progress', pages: 4 } );

		expect(
			screen.getByRole( 'button', { name: 'Import past history' } )
		).toBeDisabled();
	} );
} );
