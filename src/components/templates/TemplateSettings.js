/**
 * WordPress dependencies
 */
import apiFetch from '@wordpress/api-fetch';
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	Notice,
} from '@wordpress/components';
import { useCallback, useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import { addQueryArgs } from '@wordpress/url';

/**
 * Internal dependencies
 */
import settings from '../../settings';
import TemplateCard from './TemplateCard';

/**
 * Build the Site Editor URL for one template or part.
 *
 * Returns nothing on a classic theme, where the Site Editor has nothing to
 * show: a link that goes somewhere useless is worse than no link.
 *
 * @param {Object} template The template or part.
 * @return {string} The URL, or an empty string.
 */
function editUrlFor( template ) {
	if ( ! settings.isBlockTheme || ! settings.themeStylesheet ) {
		return '';
	}

	return addQueryArgs( settings.siteEditorUrl, {
		postType: template.type,
		postId: `${ settings.themeStylesheet }//${ template.slug }`,
		canvas: 'edit',
	} );
}

/**
 * Choose which templates and parts Traktivity provides.
 *
 * Everything is off until someone switches it on, which makes this section the
 * only way anyone finds out these exist. So it explains what each one is and
 * links straight into the Site Editor rather than leaving people to hunt.
 *
 * @return {Element} The section.
 */
export default function TemplateSettings() {
	const [ templates, setTemplates ] = useState(
		() => settings.templates || []
	);
	const [ saving, setSaving ] = useState( false );
	const [ notice, setNotice ] = useState( null );

	/*
	 * The edit link follows what has been saved, not what is on screen. Link
	 * off the optimistic value and someone ticks a box, clicks straight
	 * through, and lands on a template that was never registered.
	 */
	const [ saved, setSaved ] = useState( () => settings.templates || [] );

	const toggle = useCallback(
		( slug, next ) => {
			const updated = templates.map( ( template ) =>
				template.slug === slug
					? { ...template, enabled: next }
					: template
			);

			setTemplates( updated );
			setSaving( true );
			setNotice( null );

			const enabled = {};
			updated.forEach( ( template ) => {
				enabled[ template.slug ] = template.enabled;
			} );

			apiFetch( {
				path: '/traktivity/v1/templates',
				method: 'POST',
				data: { enabled },
			} )
				.then( ( body ) => {
					const stored = body?.templates || updated;
					setTemplates( stored );
					setSaved( stored );
				} )
				.catch( ( error ) => {
					// Put the switch back where it was, so the screen matches the site.
					setTemplates( templates );
					setNotice(
						error?.message ||
							__(
								'That change could not be saved.',
								'traktivity'
							)
					);
				} )
				.finally( () => setSaving( false ) );
		},
		[ templates ]
	);

	const isSaved = useCallback(
		( slug ) =>
			saved.some(
				( template ) => template.slug === slug && template.enabled
			),
		[ saved ]
	);

	if ( ! templates.length ) {
		return null;
	}

	return (
		<Card className="traktivity-templates">
			<CardHeader>
				<h2>{ __( 'Show your watch history', 'traktivity' ) }</h2>
			</CardHeader>
			<CardBody>
				<p>
					{ __(
						'Traktivity can lay out your entries for you. Nothing here is on until you switch it on, and your theme always wins if it has its own version.',
						'traktivity'
					) }
				</p>

				{ ! settings.isBlockTheme && (
					<Notice status="warning" isDismissible={ false }>
						{ __(
							'Your theme is a classic theme, so WordPress will not use any of these. They will start working if you switch to a block theme.',
							'traktivity'
						) }
					</Notice>
				) }

				{ settings.isBlockTheme && ! settings.hasEvents && (
					<Notice status="info" isDismissible={ false }>
						{ __(
							'Nothing has synced yet, so there is nothing to preview. Switch these on now if you like; they will fill in as entries arrive.',
							'traktivity'
						) }
					</Notice>
				) }

				{ notice && (
					<Notice status="error" onRemove={ () => setNotice( null ) }>
						{ notice }
					</Notice>
				) }

				<div className="traktivity-templates__grid">
					{ templates.map( ( template ) => (
						<TemplateCard
							key={ template.slug }
							template={ template }
							disabled={ saving || ! settings.isBlockTheme }
							editUrl={
								isSaved( template.slug )
									? editUrlFor( template )
									: ''
							}
							onChange={ toggle }
						/>
					) ) }
				</div>

				{ settings.isBlockTheme && (
					<p>
						<Button
							variant="link"
							href={ settings.siteEditorUrl }
							target="_blank"
						>
							{ __( 'Open the Site Editor', 'traktivity' ) }
						</Button>
					</p>
				) }
			</CardBody>
		</Card>
	);
}
