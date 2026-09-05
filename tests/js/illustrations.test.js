/**
 * External dependencies
 */
import { render } from '@testing-library/react';

/**
 * Internal dependencies
 */
import illustrationFor from '../../src/components/templates/illustrations';

/**
 * Every slug the settings screen can put on a card.
 *
 * Kept in step with Traktivity_Templates::available() and
 * Traktivity_Placements::available() by hand, because the wireframes are
 * chosen in JavaScript from slugs decided in PHP. When the parts shrank, four
 * cases here went unreachable and every placement quietly fell through to the
 * generic grid; nothing caught it, because an unused exported component is not
 * a lint error.
 */
const TEMPLATE_SLUGS = [
	'single-traktivity_event',
	'archive-traktivity_event',
	'taxonomy-trakt_show',
	'taxonomy-trakt_genre',
	'taxonomy-trakt_year',
	'taxonomy-trakt_type',
];

const PLACEMENT_SLUGS = [
	'traktivity-totals-on-archive',
	'traktivity-latest-on-archive',
	'traktivity-series-after-entry',
	'traktivity-recent-in-footer',
	'traktivity-recent-after-archive',
];

/**
 * Render a wireframe and describe its shapes, so two can be told apart.
 *
 * @param {string} slug Template or placement slug.
 * @return {string} A signature for the shapes drawn.
 */
function signature( slug ) {
	const { container } = render( illustrationFor( slug ) );

	return [ ...container.querySelectorAll( 'rect' ) ]
		.map( ( r ) => `${ r.getAttribute( 'x' ) },${ r.getAttribute( 'y' ) }` )
		.join( '|' );
}

describe( 'illustrationFor', () => {
	it.each( [ ...TEMPLATE_SLUGS, ...PLACEMENT_SLUGS ] )(
		'draws something for %s',
		( slug ) => {
			expect( signature( slug ) ).not.toBe( '' );
		}
	);

	it( 'gives each placement its own wireframe', () => {
		const drawn = PLACEMENT_SLUGS.map( signature );

		// Four of the five are distinct; the recent-watches grid deliberately
		// reuses the archive wireframe, which is what it looks like.
		expect( new Set( drawn ).size ).toBeGreaterThanOrEqual( 4 );
	} );

	it( 'falls back rather than rendering nothing for an unknown slug', () => {
		expect( signature( 'something-that-does-not-exist' ) ).not.toBe( '' );
	} );
} );
