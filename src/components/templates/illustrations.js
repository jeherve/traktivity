/**
 * Wireframes for each template and part.
 *
 * Deliberately crude: enough to tell a grid from a hero from a band at a
 * glance, and nothing that has to be redrawn when a block's styling changes.
 * They use currentColor throughout so they follow the admin colour scheme
 * rather than fighting it.
 */

/**
 * Frame every wireframe shares.
 *
 * @param {Object}  props          Component props.
 * @param {Element} props.children Shapes to draw inside the frame.
 * @return {Element} The wireframe.
 */
function Frame( { children } ) {
	return (
		<svg
			viewBox="0 0 120 80"
			role="presentation"
			focusable="false"
			style={ { width: '100%', height: 'auto', display: 'block' } }
		>
			<rect
				x="0.5"
				y="0.5"
				width="119"
				height="79"
				rx="3"
				fill="none"
				stroke="currentColor"
				strokeOpacity="0.25"
			/>
			<g fill="currentColor" fillOpacity="0.25">
				{ children }
			</g>
		</svg>
	);
}

/** @return {Element} A single entry: image, title, meta rows. */
export function SingleEntry() {
	return (
		<Frame>
			<rect x="10" y="10" width="100" height="26" rx="2" />
			<rect x="10" y="42" width="64" height="5" rx="2" />
			<rect x="10" y="52" width="40" height="4" rx="2" />
			<rect x="10" y="62" width="100" height="3" rx="1" />
			<rect x="10" y="69" width="80" height="3" rx="1" />
		</Frame>
	);
}

/** @return {Element} An archive: a band above a grid of cards. */
export function ArchiveGrid() {
	return (
		<Frame>
			<rect x="10" y="8" width="100" height="8" rx="2" />
			<rect x="10" y="24" width="46" height="24" rx="2" />
			<rect x="64" y="24" width="46" height="24" rx="2" />
			<rect x="10" y="54" width="46" height="18" rx="2" />
			<rect x="64" y="54" width="46" height="18" rx="2" />
		</Frame>
	);
}

/** @return {Element} A series archive: header beside artwork, then episodes. */
export function SeriesArchive() {
	return (
		<Frame>
			<rect x="10" y="10" width="42" height="26" rx="2" />
			<rect x="58" y="12" width="40" height="5" rx="2" />
			<rect x="58" y="22" width="52" height="3" rx="1" />
			<rect x="58" y="29" width="46" height="3" rx="1" />
			<rect x="10" y="46" width="30" height="24" rx="2" />
			<rect x="45" y="46" width="30" height="24" rx="2" />
			<rect x="80" y="46" width="30" height="24" rx="2" />
		</Frame>
	);
}

/** @return {Element} A hero: one large image beside a title. */
export function Hero() {
	return (
		<Frame>
			<rect x="10" y="14" width="62" height="52" rx="2" />
			<rect x="80" y="22" width="30" height="5" rx="2" />
			<rect x="80" y="33" width="24" height="4" rx="2" />
			<rect x="80" y="43" width="28" height="3" rx="1" />
		</Frame>
	);
}

/** @return {Element} A band of figures. */
export function StatsBand() {
	return (
		<Frame>
			<rect x="10" y="30" width="18" height="10" rx="2" />
			<rect x="10" y="44" width="14" height="3" rx="1" />
			<rect x="36" y="30" width="18" height="10" rx="2" />
			<rect x="36" y="44" width="14" height="3" rx="1" />
			<rect x="62" y="30" width="18" height="10" rx="2" />
			<rect x="62" y="44" width="14" height="3" rx="1" />
			<rect x="88" y="30" width="18" height="10" rx="2" />
			<rect x="88" y="44" width="14" height="3" rx="1" />
		</Frame>
	);
}

/** @return {Element} An index: many small tiles. */
export function SeriesIndex() {
	return (
		<Frame>
			<rect x="10" y="10" width="30" height="18" rx="2" />
			<rect x="45" y="10" width="30" height="18" rx="2" />
			<rect x="80" y="10" width="30" height="18" rx="2" />
			<rect x="10" y="33" width="30" height="18" rx="2" />
			<rect x="45" y="33" width="30" height="18" rx="2" />
			<rect x="80" y="33" width="30" height="18" rx="2" />
			<rect x="10" y="56" width="30" height="14" rx="2" />
			<rect x="45" y="56" width="30" height="14" rx="2" />
		</Frame>
	);
}

/** @return {Element} A narrow list, as in a sidebar. */
export function CompactList() {
	return (
		<Frame>
			<rect x="34" y="12" width="52" height="5" rx="2" />
			<rect x="34" y="24" width="52" height="4" rx="2" />
			<rect x="34" y="34" width="52" height="4" rx="2" />
			<rect x="34" y="44" width="52" height="4" rx="2" />
			<rect x="34" y="54" width="52" height="4" rx="2" />
			<rect x="34" y="64" width="36" height="4" rx="2" />
		</Frame>
	);
}

/**
 * Pick the wireframe for a slug.
 *
 * @param {string} slug Template or part slug.
 * @return {Element} The wireframe to draw.
 */
export default function illustrationFor( slug ) {
	switch ( slug ) {
		case 'single-traktivity_event':
			return <SingleEntry />;
		case 'taxonomy-trakt_show':
			return <SeriesArchive />;
		case 'traktivity-latest-watch':
			return <Hero />;
		case 'traktivity-watch-stats':
			return <StatsBand />;
		case 'traktivity-series-index':
			return <SeriesIndex />;
		case 'traktivity-recent-compact':
			return <CompactList />;
		default:
			return <ArchiveGrid />;
	}
}
