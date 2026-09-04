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
 * Configure and preview the series grid.
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
				<PanelBody title={ __( 'Series', 'traktivity' ) }>
					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'How many', 'traktivity' ) }
						help={ __(
							'Zero shows every series, for a full index page.',
							'traktivity'
						) }
						min={ 0 }
						max={ 48 }
						value={ attributes.number }
						onChange={ ( number ) => setAttributes( { number } ) }
					/>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Order by', 'traktivity' ) }
						value={ attributes.orderby }
						options={ [
							{
								label: __( 'Episodes watched', 'traktivity' ),
								value: 'count',
							},
							{
								label: __( 'Name, A to Z', 'traktivity' ),
								value: 'name',
							},
						] }
						onChange={ ( orderby ) => setAttributes( { orderby } ) }
					/>
					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Columns', 'traktivity' ) }
						min={ 1 }
						max={ 6 }
						value={ attributes.columns }
						onChange={ ( columns ) => setAttributes( { columns } ) }
					/>
				</PanelBody>
				<PanelBody title={ __( 'Each series', 'traktivity' ) }>
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
						label={ __( 'Show episode count', 'traktivity' ) }
						checked={ attributes.showCount }
						onChange={ ( showCount ) =>
							setAttributes( { showCount } )
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
					<RangeControl
						__nextHasNoMarginBottom
						label={ __( 'Heading level', 'traktivity' ) }
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
			/>
		</div>
	);
}

registerBlockType( metadata.name, { edit: Edit } );
