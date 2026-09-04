/**
 * WordPress dependencies
 */
import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import {
	Button,
	CheckboxControl,
	PanelBody,
	SelectControl,
} from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import metadata from './block.json';
import './style.scss';

const FIGURES = [
	{ key: 'hours', label: __( 'Hours watched', 'traktivity' ) },
	{ key: 'entries', label: __( 'Entries logged', 'traktivity' ) },
	{ key: 'episodes', label: __( 'Episodes', 'traktivity' ) },
	{ key: 'films', label: __( 'Films', 'traktivity' ) },
	{ key: 'shows', label: __( 'Series', 'traktivity' ) },
	{ key: 'since', label: __( 'Logging since', 'traktivity' ) },
];

/**
 * Pick which figures to show, and in what order.
 *
 * Order is the order they were ticked, so a checkbox list doubles as the
 * ordering control and there is no separate drag handle to build.
 *
 * @param {Object}   props               Block props.
 * @param {Object}   props.attributes    Block attributes.
 * @param {Function} props.setAttributes Attribute setter.
 * @return {Element} The editor preview.
 */
function Edit( { attributes, setAttributes } ) {
	const blockProps = useBlockProps();
	const chosen = attributes.figures || [];

	const toggle = ( key ) => {
		setAttributes( {
			figures: chosen.includes( key )
				? chosen.filter( ( item ) => item !== key )
				: [ ...chosen, key ],
		} );
	};

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Figures', 'traktivity' ) }>
					{ FIGURES.map( ( { key, label } ) => (
						<CheckboxControl
							__nextHasNoMarginBottom
							key={ key }
							label={ label }
							checked={ chosen.includes( key ) }
							onChange={ () => toggle( key ) }
						/>
					) ) }
					<Button
						variant="secondary"
						onClick={ () =>
							setAttributes( {
								figures: FIGURES.map(
									( figure ) => figure.key
								),
							} )
						}
					>
						{ __( 'Show all', 'traktivity' ) }
					</Button>
				</PanelBody>
				<PanelBody title={ __( 'Layout', 'traktivity' ) }>
					<SelectControl
						__nextHasNoMarginBottom
						label={ __( 'Arrangement', 'traktivity' ) }
						help={ __(
							'A stack suits a sidebar; a row suits a full-width band.',
							'traktivity'
						) }
						value={ attributes.layout }
						options={ [
							{ label: __( 'Row', 'traktivity' ), value: 'row' },
							{
								label: __( 'Stack', 'traktivity' ),
								value: 'stack',
							},
						] }
						onChange={ ( layout ) => setAttributes( { layout } ) }
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
