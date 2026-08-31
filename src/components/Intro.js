/**
 * WordPress dependencies
 */
import { Component } from '@wordpress/element';

/**
 * Internal dependencies
 */
import settings from '../settings';

class Intro extends Component {
	render() {
		return (
			<div className="intro card">
				<p>
					<strong>{ settings.intro }</strong>
				</p>
				<p>{ settings.description }</p>
				<div className="action">
					<button
						className="nav-button"
						onClick={ this.props.nextStep }
					>
						{ settings.intro_next }
					</button>
				</div>
			</div>
		);
	}
}

export default Intro;
