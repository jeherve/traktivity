/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import './style.scss';

/**
 * Preview the details table.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @param {Object}   props.context       Block context, carrying the post ID.
 * @return {Element} The editor preview.
 */
function Edit( { attributes, setAttributes, context } ) {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Rows', 'traktivity' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Series, season, genre and year',
							'traktivity'
						) }
						checked={ attributes.showTaxonomies }
						onChange={ ( showTaxonomies ) =>
							setAttributes( { showTaxonomies } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Runtime', 'traktivity' ) }
						checked={ attributes.showRuntime }
						onChange={ ( showRuntime ) =>
							setAttributes( { showRuntime } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Links to Trakt.tv, IMDb and TMDb',
							'traktivity'
						) }
						checked={ attributes.showLinks }
						onChange={ ( showLinks ) =>
							setAttributes( { showLinks } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<ServerSideRender
				block={ metadata.name }
				attributes={ attributes }
				urlQueryArgs={
					context?.postId ? { post_id: context.postId } : {}
				}
			/>
		</div>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
