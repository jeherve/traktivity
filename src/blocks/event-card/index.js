/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	ToggleControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import './style.scss';

/**
 * Preview the card the way the front end will render it.
 *
 * The card is composed from six taxonomies and some post meta, none of which
 * the editor has to hand, so ServerSideRender is the only way to show what a
 * reader will actually see rather than a guess at it.
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
				<PanelBody title={ __( 'Card', 'traktivity' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show artwork', 'traktivity' ) }
						checked={ attributes.showImage }
						onChange={ ( showImage ) =>
							setAttributes( { showImage } )
						}
					/>
					{ attributes.showImage && (
						<SelectControl
							__nextHasNoMarginBottom
							label={ __( 'Artwork shape', 'traktivity' ) }
							help={ __(
								'Artwork comes from TMDb as a 16:9 still. A portrait frame crops it.',
								'traktivity'
							) }
							value={ attributes.imageAspect }
							options={ [
								{
									label: __( 'Landscape', 'traktivity' ),
									value: 'landscape',
								},
								{
									label: __( 'Portrait', 'traktivity' ),
									value: 'poster',
								},
							] }
							onChange={ ( imageAspect ) =>
								setAttributes( { imageAspect } )
							}
						/>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show summary', 'traktivity' ) }
						checked={ attributes.showExcerpt }
						onChange={ ( showExcerpt ) =>
							setAttributes( { showExcerpt } )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Heading level', 'traktivity' ) }
						help={ __(
							'Pick the level that follows the heading above this block.',
							'traktivity'
						) }
						min={ 1 }
						max={ 6 }
						value={ attributes.headingLevel }
						onChange={ ( headingLevel ) =>
							setAttributes( { headingLevel: headingLevel || 3 } )
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
