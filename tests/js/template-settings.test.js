/**
 * External dependencies
 */
import { render, screen, waitFor } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import apiFetch from '@wordpress/api-fetch';

/**
 * Internal dependencies
 */
import TemplateSettings from '../../src/components/templates/TemplateSettings';
import { resetSettings } from './settings-mock';

jest.mock( '../../src/settings', () => require( './settings-mock' ) );
jest.mock( '@wordpress/api-fetch' );

const TEMPLATE = {
	slug: 'archive-traktivity_event',
	type: 'wp_template',
	title: 'Everything watched',
	description: 'The full archive of what you have watched.',
	enabled: false,
	themeProvides: false,
};

const PLACEMENT = {
	slug: 'traktivity-totals-on-archive',
	type: 'placement',
	editSlug: '',
	editType: '',
	title: 'Totals above the archive',
	description:
		'Hours, entries, episodes, films and series, at the top of your archive.',
	enabled: false,
	themeProvides: false,
};

/**
 * Set up the settings a test needs.
 *
 * @param {Object} overrides Values to apply on top of the block-theme default.
 */
function withSettings( overrides = {} ) {
	resetSettings( {
		isBlockTheme: true,
		hasEvents: true,
		themeStylesheet: 'twentytwentyfour',
		siteEditorUrl: 'http://example.org/wp-admin/site-editor.php',
		templates: [ TEMPLATE, PLACEMENT ],
		...overrides,
	} );
}

describe( 'TemplateSettings', () => {
	beforeEach( () => {
		apiFetch.mockReset();
		apiFetch.mockResolvedValue( { templates: [] } );
	} );

	it( 'renders nothing when the plugin offers no templates', () => {
		withSettings( { templates: [] } );

		const { container } = render( <TemplateSettings /> );

		expect( container ).toBeEmptyDOMElement();
	} );

	it( 'lists what the plugin can provide', () => {
		withSettings();

		render( <TemplateSettings /> );

		expect( screen.getByText( 'Everything watched' ) ).toBeInTheDocument();
		expect(
			screen.getByText( 'Totals above the archive' )
		).toBeInTheDocument();
	} );

	it( 'says the plugin does the placing, and it can be moved after', () => {
		withSettings();

		render( <TemplateSettings /> );

		expect(
			screen.getByText( /Traktivity puts this on the page for you/ )
		).toBeInTheDocument();
	} );

	/*
	 * getAllByText throughout for notice copy: @wordpress/components mirrors a
	 * Notice into an aria-live region, so the text is genuinely in the
	 * document twice and getByText would fail on the duplicate.
	 */
	it( 'warns that a classic theme will not use any of this', () => {
		withSettings( { isBlockTheme: false } );

		render( <TemplateSettings /> );

		expect(
			screen.getAllByText( /Your theme is a classic theme/ ).length
		).toBeGreaterThan( 0 );
	} );

	it( 'says there is nothing to preview before the first sync', () => {
		withSettings( { hasEvents: false } );

		render( <TemplateSettings /> );

		expect(
			screen.getAllByText( /Nothing has synced yet/ ).length
		).toBeGreaterThan( 0 );
	} );

	it( 'flags a template the theme already covers', () => {
		withSettings( {
			templates: [ { ...TEMPLATE, themeProvides: true } ],
		} );

		render( <TemplateSettings /> );

		expect(
			screen.getByText( /Your theme already has a template for this/ )
		).toBeInTheDocument();
	} );

	it( 'saves a change', async () => {
		withSettings();
		apiFetch.mockResolvedValue( {
			templates: [ { ...TEMPLATE, enabled: true }, PLACEMENT ],
		} );

		render( <TemplateSettings /> );
		await userEvent.click( screen.getAllByRole( 'checkbox' )[ 0 ] );

		await waitFor( () =>
			expect( apiFetch ).toHaveBeenCalledWith(
				expect.objectContaining( {
					path: '/traktivity/v1/templates',
					method: 'POST',
					data: {
						enabled: {
							'archive-traktivity_event': true,
							'traktivity-totals-on-archive': false,
						},
					},
				} )
			)
		);
	} );

	it( 'puts the switch back when saving fails', async () => {
		withSettings();
		apiFetch.mockRejectedValue( { message: 'Nope.' } );

		render( <TemplateSettings /> );
		const checkbox = screen.getAllByRole( 'checkbox' )[ 0 ];
		await userEvent.click( checkbox );

		await waitFor( () =>
			expect( screen.getAllByText( 'Nope.' ).length ).toBeGreaterThan( 0 )
		);
		expect( checkbox ).not.toBeChecked();
	} );

	it( 'only offers an edit link once the change is saved', async () => {
		withSettings();
		apiFetch.mockResolvedValue( {
			templates: [ { ...TEMPLATE, enabled: true }, PLACEMENT ],
		} );

		render( <TemplateSettings /> );

		// Nothing is enabled yet, so there is nothing worth linking to.
		expect(
			screen.queryByText( 'Preview and edit' )
		).not.toBeInTheDocument();

		await userEvent.click( screen.getAllByRole( 'checkbox' )[ 0 ] );

		await waitFor( () =>
			expect( screen.getByText( 'Preview and edit' ) ).toBeInTheDocument()
		);
	} );

	it( 'hides edit links on a classic theme rather than linking nowhere', async () => {
		withSettings( {
			isBlockTheme: false,
			templates: [ { ...TEMPLATE, enabled: true } ],
		} );

		render( <TemplateSettings /> );

		expect(
			screen.queryByText( 'Preview and edit' )
		).not.toBeInTheDocument();
	} );

	it( 'points the edit link at the right template in the Site Editor', async () => {
		withSettings( {
			templates: [ { ...TEMPLATE, enabled: true } ],
		} );

		render( <TemplateSettings /> );

		const link = screen.getByText( 'Preview and edit' ).closest( 'a' );

		expect( link ).toHaveAttribute(
			'href',
			expect.stringContaining( 'postType=wp_template' )
		);
		expect( link.getAttribute( 'href' ) ).toContain(
			encodeURIComponent( 'twentytwentyfour//archive-traktivity_event' )
		);
	} );
} );
