/**
 * WordPress dependencies
 */
import { CheckboxControl, ExternalLink } from '@wordpress/components';
import { useCallback } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import illustrationFor from './illustrations';

/**
 * One template or part, as a selectable card.
 *
 * The label wraps only the wireframe, so clicking the picture toggles the
 * checkbox while the title and the edit link sit outside it and do not. The
 * visible title is borrowed as the checkbox's accessible name rather than
 * repeated in a hidden label.
 *
 * @param {Object}   props          Component props.
 * @param {Object}   props.template The template or part being offered.
 * @param {boolean}  props.disabled Whether the checkbox is unavailable.
 * @param {string}   props.editUrl  Site Editor URL, when there is a useful one.
 * @param {Function} props.onChange Called with ( slug, next ).
 * @return {Element} The card.
 */
export default function TemplateCard( {
	template,
	disabled = false,
	editUrl = '',
	onChange,
} ) {
	const id = `traktivity-template-${ template.slug }`;
	const titleId = `${ id }__title`;

	const handleChange = useCallback(
		( next ) => onChange( template.slug, next ),
		[ onChange, template.slug ]
	);

	return (
		<div className="traktivity-template-card">
			<label
				htmlFor={ id }
				className="traktivity-template-card__surface"
				data-checked={ template.enabled ? 'true' : 'false' }
			>
				{ illustrationFor( template.slug ) }
			</label>

			<div className="traktivity-template-card__body">
				<CheckboxControl
					__nextHasNoMarginBottom
					id={ id }
					checked={ template.enabled }
					disabled={ disabled }
					onChange={ handleChange }
					aria-labelledby={ titleId }
					label=""
				/>
				<div>
					<p
						id={ titleId }
						className="traktivity-template-card__title"
					>
						{ template.title }
					</p>
					<p className="traktivity-template-card__description">
						{ template.description }
					</p>

					{ template.themeProvides && (
						<p className="traktivity-template-card__note">
							{ __(
								'Your theme already has a template for this, and its own wins. Switching this on will not change anything.',
								'traktivity'
							) }
						</p>
					) }

					{ template.type === 'wp_template_part' && (
						<p className="traktivity-template-card__note">
							{ __(
								'Nothing places this for you. Switch it on, then add a Template Part block wherever you want it.',
								'traktivity'
							) }
						</p>
					) }

					{ editUrl && (
						<ExternalLink href={ editUrl }>
							{ __( 'Preview and edit', 'traktivity' ) }
						</ExternalLink>
					) }
				</div>
			</div>
		</div>
	);
}
