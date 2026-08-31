/**
 * WordPress dependencies
 */
import { __ } from '@wordpress/i18n';

export default function Header() {
	return (
		<header className="traktivity-header">
			<h1>{ __( 'Traktivity Dashboard', 'traktivity' ) }</h1>
			<p className="traktivity-header__tagline">
				{ __(
					'Log your activity in front of the screen.',
					'traktivity'
				) }
			</p>
		</header>
	);
}
