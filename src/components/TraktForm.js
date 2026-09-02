/**
 * WordPress dependencies
 */
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
	Notice as WPNotice,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Notice from './Notice';

const CREATE_APP_URL = 'https://trakt.tv/oauth/applications/new';
const VIP_URL = 'https://trakt.tv/vip';

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
				<h2>{ __( 'Trakt.tv Settings', 'traktivity' ) }</h2>
			</CardHeader>
			<CardBody>
				{ /*
				 * Not dismissible, and shown whether or not a key is already
				 * stored: it is a prerequisite for the link below, and someone
				 * with a working key may still need to create a new one.
				 */ }
				<WPNotice status="warning" isDismissible={ false }>
					<strong>
						{ __(
							'Trakt.tv now requires a VIP account to create an API application.',
							'traktivity'
						) }
					</strong>{ ' ' }
					{ __(
						'If you already have an API key, it will keep working.',
						'traktivity'
					) }{ ' ' }
					<ExternalLink href={ VIP_URL }>
						{ __( 'Trakt.tv VIP', 'traktivity' ) }
					</ExternalLink>
				</WPNotice>

				<p>
					{ __(
						'To use the plugin, you will need to create an API application on Trakt.tv first.',
						'traktivity'
					) }{ ' ' }
					<ExternalLink href={ CREATE_APP_URL }>
						{ __( 'Click here to create that app.', 'traktivity' ) }
					</ExternalLink>
				</p>
				<p>
					{ __(
						'In the Redirect uri field, you can enter your site URL. You can give it both checkin and scrobble permissions.',
						'traktivity'
					) }
				</p>
				<p>
					{ __(
						'Once you created your app, copy the "Client ID" value below. You will also want to enter your Trakt.tv username.',
						'traktivity'
					) }
				</p>

				<form className="traktivity-form" onSubmit={ onSubmit }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Trakt.tv Username', 'traktivity' ) }
						value={ username }
						onChange={ setUsername }
						autoComplete="off"
						required
					/>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'Trakt.tv API Key', 'traktivity' ) }
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
