/**
 * WordPress dependencies
 */
import { Card, CardBody, CardHeader, Spinner } from '@wordpress/components';
import { useEffect, useState } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import settings from '../settings';

const STORAGE_KEY = 'traktivity_recent_events';

/**
 * Read the cached event list, tolerating a missing entry, a corrupt one, and
 * browsers that throw on sessionStorage access altogether.
 *
 * @return {Array|null} Cached events, or null when there is nothing usable.
 */
function readCache() {
	try {
		const stored = window.sessionStorage.getItem( STORAGE_KEY );
		const parsed = stored ? JSON.parse( stored ) : null;
		return Array.isArray( parsed ) ? parsed : null;
	} catch {
		return null;
	}
}

/**
 * Store the event list, ignoring a full or unavailable sessionStorage.
 *
 * @param {Array} events Events to cache.
 */
function writeCache( events ) {
	try {
		window.sessionStorage.setItem( STORAGE_KEY, JSON.stringify( events ) );
	} catch {
		// Not being able to cache is not worth surfacing to the user.
	}
}

/**
 * Pull the medium-sized featured image out of an embedded event.
 *
 * @param {Object} event A traktivity_event REST record.
 * @return {Object|null} The image, or null when the event has none.
 */
function featuredImage( event ) {
	const media = event._embedded?.[ 'wp:featuredmedia' ]?.[ 0 ];
	const size = media?.media_details?.sizes?.medium;

	return size ? { ...size, alt: media.alt_text } : null;
}

export default function RecentEvents() {
	const [ events, setEvents ] = useState( () => readCache() );

	useEffect( () => {
		if ( events ) {
			return;
		}

		let cancelled = false;

		apiFetch( { path: '/wp/v2/traktivity_event?per_page=6&_embed=1' } )
			.then( ( fetched ) => {
				if ( cancelled ) {
					return;
				}
				writeCache( fetched );
				setEvents( fetched );
			} )
			.catch( () => {
				if ( ! cancelled ) {
					setEvents( [] );
				}
			} );

		return () => {
			cancelled = true;
		};
	}, [ events ] );

	return (
		<Card>
			<CardHeader>
				<h2>{ settings.recent_list_title }</h2>
			</CardHeader>
			<CardBody>
				{ events === null && <Spinner /> }
				{ events?.length === 0 && (
					<p>
						{ __(
							'Nothing logged yet. Go watch something!',
							'traktivity'
						) }
					</p>
				) }
				<div className="traktivity-recent-events">
					{ ( events || [] ).map( ( event ) => {
						const image = featuredImage( event );

						if ( ! image ) {
							return null;
						}

						return (
							<a
								key={ event.id }
								href={ event.link }
								className="traktivity-recent-events__item"
							>
								<img
									src={ image.source_url }
									alt={ image.alt }
									width={ image.width }
									height={ image.height }
								/>
								<span className="traktivity-recent-events__title">
									{ event.title.rendered }
								</span>
							</a>
						);
					} ) }
				</div>
			</CardBody>
		</Card>
	);
}
