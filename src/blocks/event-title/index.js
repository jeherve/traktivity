/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { PanelBody, RangeControl, ToggleControl } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import './style.scss';

/**
 * Preview the composed title.
 *
 * The show name and the episode numbers live in three taxonomies the editor
 * does not have to hand, so the preview is rendered on the server.
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
				<PanelBody title={ __( 'Title', 'traktivity' ) }>
					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Heading level', 'traktivity' ) }
						min={ 1 }
						max={ 6 }
						value={ attributes.level }
						onChange={ ( level ) =>
							setAttributes( { level: level || 1 } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show the series name', 'traktivity' ) }
						help={ __(
							'Turn this off where the series is already named above.',
							'traktivity'
						) }
						checked={ attributes.showShowName }
						onChange={ ( showShowName ) =>
							setAttributes( { showShowName } )
						}
					/>
					{ attributes.showShowName && (
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Link the series name', 'traktivity' ) }
							checked={ attributes.linkShowName }
							onChange={ ( linkShowName ) =>
								setAttributes( { linkShowName } )
							}
						/>
					) }
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Link to the entry', 'traktivity' ) }
						help={ __(
							'Useful in a listing, not on the entry itself.',
							'traktivity'
						) }
						checked={ attributes.isLink }
						onChange={ ( isLink ) => setAttributes( { isLink } ) }
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
