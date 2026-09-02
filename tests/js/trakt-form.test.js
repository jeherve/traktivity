/**
 * External dependencies
 */
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

/**
 * Internal dependencies
 */
import TraktForm from '../../src/components/TraktForm';

/**
 * Render the form with sensible defaults.
 *
 * @param {Object} overrides Props to override.
 * @return {Object} The props used, so tests can assert on the spies.
 */
function setup( overrides = {} ) {
	const props = {
		trakt: { username: '', key: '' },
		saveCreds: jest.fn().mockResolvedValue( true ),
		nextStep: jest.fn().mockResolvedValue(),
		notice: null,
		removeNotice: jest.fn(),
		...overrides,
	};

	render( <TraktForm { ...props } /> );

	return props;
}

describe( 'TraktForm', () => {
	it( 'starts from the credentials already stored', () => {
		setup( { trakt: { username: 'jeherve', key: 'stored-key' } } );

		expect( screen.getByLabelText( 'Trakt.tv Username' ) ).toHaveValue(
			'jeherve'
		);
		expect( screen.getByLabelText( 'Trakt.tv API Key' ) ).toHaveValue(
			'stored-key'
		);
	} );

	it( 'warns that creating a Trakt.tv API key now needs VIP', () => {
		setup();

		// The Notice renders a screen reader copy alongside the visible text.
		expect(
			screen.getAllByText( /requires a VIP account/ ).length
		).toBeGreaterThan( 0 );

		expect(
			screen.getByRole( 'link', { name: /Trakt\.tv VIP/ } )
		).toHaveAttribute( 'href', 'https://trakt.tv/vip' );
	} );

	it( 'keeps the VIP warning visible once a key is stored', () => {
		setup( { trakt: { username: 'jeherve', key: 'stored-key' } } );

		expect(
			screen.getAllByText( /requires a VIP account/ ).length
		).toBeGreaterThan( 0 );
	} );

	it( 'cannot be submitted until both fields are filled in', async () => {
		const user = userEvent.setup();
		setup();

		const submit = screen.getByRole( 'button', {
			name: 'Verify and continue',
		} );
		expect( submit ).toBeDisabled();

		await user.type(
			screen.getByLabelText( 'Trakt.tv Username' ),
			'jeherve'
		);
		expect( submit ).toBeDisabled();

		await user.type( screen.getByLabelText( 'Trakt.tv API Key' ), 'abc' );
		expect( submit ).toBeEnabled();
	} );

	it( 'saves once on submit rather than on every keystroke', async () => {
		const user = userEvent.setup();
		const props = setup();

		await user.type(
			screen.getByLabelText( 'Trakt.tv Username' ),
			'jeherve'
		);
		await user.type(
			screen.getByLabelText( 'Trakt.tv API Key' ),
			'abc123'
		);

		expect( props.saveCreds ).not.toHaveBeenCalled();

		await user.click(
			screen.getByRole( 'button', { name: 'Verify and continue' } )
		);

		expect( props.saveCreds ).toHaveBeenCalledTimes( 1 );
		expect( props.saveCreds ).toHaveBeenCalledWith( 'jeherve', 'abc123' );
	} );

	it( 'advances when the credentials check out', async () => {
		const user = userEvent.setup();
		const props = setup( {
			trakt: { username: 'jeherve', key: 'abc123' },
			saveCreds: jest.fn().mockResolvedValue( true ),
		} );

		await user.click(
			screen.getByRole( 'button', { name: 'Verify and continue' } )
		);

		await waitFor( () => expect( props.nextStep ).toHaveBeenCalled() );
	} );

	it( 'stays put when the credentials are rejected', async () => {
		const user = userEvent.setup();
		const props = setup( {
			trakt: { username: 'jeherve', key: 'wrong' },
			saveCreds: jest.fn().mockResolvedValue( false ),
		} );

		await user.click(
			screen.getByRole( 'button', { name: 'Verify and continue' } )
		);

		await waitFor( () => expect( props.saveCreds ).toHaveBeenCalled() );
		expect( props.nextStep ).not.toHaveBeenCalled();
	} );

	it( 'shows the notice it is given', () => {
		setup( {
			notice: { status: 'error', message: 'Invalid API key.' },
		} );

		// The Notice component also renders a visually hidden copy for
		// screen readers, so the message legitimately appears more than once.
		expect(
			screen.getAllByText( 'Invalid API key.' ).length
		).toBeGreaterThan( 0 );
	} );
} );
