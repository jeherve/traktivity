/* External dependencies */
import React from 'react';
import { Link } from 'react-router-dom';

class Nav extends React.Component {
	render() {
		return (
			<nav>
				<ul>
					{ /*
					<li><Link to={{ pathname: traktivity_dash.dash_url }}>{traktivity_dash.nav_dash}</Link></li>
					<li><Link to={`${traktivity_dash.dash_url}/parameters`}>{traktivity_dash.nav_params}</Link></li>
					<li><Link to={`${traktivity_dash.dash_url}/faq`}>{traktivity_dash.nav_faq}</Link></li>
					*/ }
				</ul>
			</nav>
		)
	}
}

export default Nav;
