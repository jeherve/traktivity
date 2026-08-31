/**
 * WordPress dependencies
 */
import {
	Button,
	Card,
	CardBody,
	CardHeader,
	ExternalLink,
	Flex,
	TextControl,
} from '@wordpress/components';
import { useState } from '@wordpress/element';
import { __ } from '@wordpress/i18n';

/**
 * Internal dependencies
 */
import Notice from './Notice';

const CREATE_APP_URL = 'https://www.themoviedb.org/login';

/**
 * Step 3: collect and verify the TMDb API key. This step is optional.
 *
 * @param {Object}   props
 * @param {Object}   props.tmdb         Current TMDb settings.
 * @param {Function} props.saveCreds    Persists and verifies the key.
 * @param {Function} props.nextStep     Advances the wizard.
 * @param {Object}   [props.notice]     Status message to display.
 * @param {Function} props.removeNotice Clears the status message.
 * @return {Element} The form.
 */
export default function TmdbForm( {
	tmdb,
	saveCreds,
	nextStep,
	notice,
	removeNotice,
} ) {
	const [ key, setKey ] = useState( tmdb.key );
	const [ isBusy, setIsBusy ] = useState( false );

	const onSubmit = ( event ) => {
		event.preventDefault();
		setIsBusy( true );

		saveCreds( key )
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
				<h2>{ __( 'The Movie Database Settings', 'traktivity' ) }</h2>
			</CardHeader>
			<CardBody>
				<p>
					{ __(
						'To get images for each TV show, episode, and movie, we will also need to use another service, The Movie DB API.',
						'traktivity'
					) }{ ' ' }
					{ __(
						'This is optional. If you do not want to store and display images about the things you watch on your site, you can ignore this.',
						'traktivity'
					) }
				</p>
				<p>
					{ __(
						'To register for an API key, sign up and/or login to your account page on TMDb and click the "API" link in the left hand sidebar. Once your application is approved, copy the contents of the "API Key (v3 auth)" field, and paste it below.',
						'traktivity'
					) }{ ' ' }
					<ExternalLink href={ CREATE_APP_URL }>
						{ __( 'Click here to create that app.', 'traktivity' ) }
					</ExternalLink>
				</p>

				<form onSubmit={ onSubmit }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ __( 'TMDB API Key', 'traktivity' ) }
						value={ key }
						onChange={ setKey }
						autoComplete="off"
					/>

					<Notice notice={ notice } removeNotice={ removeNotice } />

					<Flex justify="flex-start" gap={ 2 }>
						<Button
							variant="tertiary"
							onClick={ nextStep }
							disabled={ isBusy }
						>
							{ __( 'Skip', 'traktivity' ) }
						</Button>
						<Button
							variant="primary"
							type="submit"
							isBusy={ isBusy }
							disabled={ isBusy || ! key }
						>
							{ __( 'Verify and continue', 'traktivity' ) }
						</Button>
					</Flex>
				</form>
			</CardBody>
		</Card>
	);
}
