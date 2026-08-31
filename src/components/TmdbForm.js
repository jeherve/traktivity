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
import settings from '../settings';

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
				<h2>{ settings.form_tmdb_title }</h2>
			</CardHeader>
			<CardBody>
				<p>
					{ settings.form_tmdb_intro }{ ' ' }
					{ settings.form_tmdb_intro_opt }
				</p>
				<p>
					{ settings.form_tmdb_create_app }{ ' ' }
					<ExternalLink href={ settings.form_tmdb_api_url }>
						{ settings.form_trakt_create_app }
					</ExternalLink>
				</p>

				<form onSubmit={ onSubmit }>
					<TextControl
						__next40pxDefaultSize
						__nextHasNoMarginBottom
						label={ settings.form_tmdb_key }
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
							{ settings.button_skip }
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
