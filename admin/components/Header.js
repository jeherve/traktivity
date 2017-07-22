/* External dependencies */
import React from 'react';

class Header extends React.Component {
	render() {
		return (
			<header className="top">
				<h1>{traktivity_dash.title}</h1>
					<div className="tagline"><span>{traktivity_dash.tagline}</span></div>
					<p><strong>{traktivity_dash.intro}</strong></p>
					<p>{traktivity_dash.description}</p>
			</header>
		)
	}
}

export default Header;
