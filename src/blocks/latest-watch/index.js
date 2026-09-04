/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	SelectControl,
	TextControl,
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
 * Configure and preview the most recent watch.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element} The editor preview.
 */
function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Latest watch', 'traktivity' ) }>
					<TextControl
						__nextHasNoMarginBottom
						label={ __( 'Label above', 'traktivity' ) }
						help={ __(
							'Something like "Just watched". Leave empty for none.',
							'traktivity'
						) }
						value={ attributes.kicker }
						onChange={ ( kicker ) => setAttributes( { kicker } ) }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Limit to', 'traktivity' ) }
						value={ attributes.type }
						options={ [
							{
								label: __( 'Anything', 'traktivity' ),
								value: 'any',
							},
							{
								label: __( 'TV only', 'traktivity' ),
								value: 'tv',
							},
							{
								label: __( 'Films only', 'traktivity' ),
								value: 'movie',
							},
						] }
						onChange={ ( type ) => setAttributes( { type } ) }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Prefer an entry with artwork',
							'traktivity'
						) }
						help={ __(
							'TMDb has no image for everything, and a large blank reads as broken. This picks the most recent entry that has one.',
							'traktivity'
						) }
						checked={ attributes.preferWithImage }
						onChange={ ( preferWithImage ) =>
							setAttributes( { preferWithImage } )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Heading level', 'traktivity' ) }
						min={ 1 }
						max={ 6 }
						value={ attributes.headingLevel }
						onChange={ ( headingLevel ) =>
							setAttributes( { headingLevel: headingLevel || 2 } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<ServerSideRender
				block={ metadata.name }
				attributes={ attributes }
			/>
		</div>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
