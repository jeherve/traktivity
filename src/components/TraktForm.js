/**
 * WordPress dependencies
 */
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Notice from './Notice';
import settings from '../settings';

/**
 * Step 2: collect and verify the Trakt.tv credentials.
 *
 * Credentials are saved and checked once on submit rather than on every
 * keystroke, which is what the previous version did.
 *
 * @param {Object}   props
 * @param {Object}   props.trakt        Current Trakt settings.
 * @param {Function} props.saveCreds    Persists and verifies the credentials.
 * @param {Function} props.nextStep     Advances the wizard.
 * @param {Object}   [props.notice]     Status message to display.
 * @param {Function} props.removeNotice Clears the status message.
 * @return {Element} The form.
 */
export default function TraktForm( {
	trakt,
	saveCreds,
	nextStep,
	notice,
	removeNotice,
} ) {
	const [ username, setUsername ] = useState( trakt.username );
	const [ key, setKey ] = useState( trakt.key );
	const [ isBusy, setIsBusy ] = useState( false );

	const onSubmit = ( event ) => {
		event.preventDefault();
		setIsBusy( true );

		saveCreds( username, key )
			.then( ( valid ) => {
				if ( valid ) {
					return nextStep();
				}
				return null;
			} )
			.finally( () => setIsBusy( false ) );
	};

	return (
		<Card>
			<CardHeader>
				<h2>{ settings.form_trakt_title }</h2>
			</CardHeader>
			<CardBody>
				<p>
					{ settings.form_trakt_intro }{ ' ' }
					<ExternalLink href={ settings.form_trakt_api_url }>
						{ settings.form_trakt_create_app }
					</ExternalLink>
				</p>
				<p>{ settings.form_trakt_api_options }</p>
				<p>{ settings.form_trakt_api_fields }</p>

				<form onSubmit={ onSubmit }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ settings.form_trakt_username }
						value={ username }
						onChange={ setUsername }
						autoComplete="off"
						required
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ settings.form_trakt_key }
						value={ key }
						onChange={ setKey }
						autoComplete="off"
						required
					/>

					<Notice notice={ notice } removeNotice={ removeNotice } />

					<Button
						variant="primary"
						type="submit"
						isBusy={ isBusy }
						disabled={ isBusy || ! username || ! key }
					>
						{ __( 'Verify and continue', 'traktivity' ) }
					</Button>
				</form>
			</CardBody>
		</Card>
	);
}
