/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Notice,
	PanelBody,
	RangeControl,
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
 * Configure the series header.
 *
 * The block reads the queried series, which only exists on a series archive.
 * Anywhere else the server render is empty, so the editor says why rather than
 * leaving a blank area that looks like a bug.
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
				<PanelBody title={ __( 'Series header', 'traktivity' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show artwork', 'traktivity' ) }
						checked={ attributes.showImage }
						onChange={ ( showImage ) =>
							setAttributes( { showImage } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show network', 'traktivity' ) }
						checked={ attributes.showNetwork }
						onChange={ ( showNetwork ) =>
							setAttributes( { showNetwork } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show episodes and time watched',
							'traktivity'
						) }
						checked={ attributes.showStats }
						onChange={ ( showStats ) =>
							setAttributes( { showStats } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show synopsis', 'traktivity' ) }
						checked={ attributes.showSynopsis }
						onChange={ ( showSynopsis ) =>
							setAttributes( { showSynopsis } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show links to Trakt.tv, IMDb and TMDb',
							'traktivity'
						) }
						checked={ attributes.showLinks }
						onChange={ ( showLinks ) =>
							setAttributes( { showLinks } )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Heading level', 'traktivity' ) }
						min={ 1 }
						max={ 6 }
						value={ attributes.headingLevel }
						onChange={ ( headingLevel ) =>
							setAttributes( { headingLevel: headingLevel || 1 } )
						}
					/>
				</PanelBody>
			</InspectorControls>

			<Notice status="info" isDismissible={ false }>
				{ __(
					'This header fills in on a series archive. It stays empty everywhere else.',
					'traktivity'
				) }
			</Notice>

			<ServerSideRender
				block={ metadata.name }
				attributes={ attributes }
			/>
		</div>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
