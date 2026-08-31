/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';

/**
 * Internal dependencies
 */
import settings from '../settings';

class Header extends Component {
	render() {
		return (
			<header className="top">
				<div className="header_items">
					<h1>{ settings.title }</h1>
				</div>
			</header>
		);
	}
}

export default Header;
