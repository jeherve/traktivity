/**
 * Internal dependencies
 */
import settings from '../settings';

export default function Header() {
	return (
		<header className="traktivity-header">
			<h1>{ settings.title }</h1>
			<p className="traktivity-header__tagline">{ settings.tagline }</p>
		</header>
	);
}
