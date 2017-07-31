/* External dependencies */
import React from 'react';
import PropTypes from 'prop-types';

/* Internal dependencies */
import Nav from './Nav.js';

class Header extends React.Component {
	render() {
		const displayMenu = this.props.step === 5;
		return (
			<header className="top">
				<div className="header_items">
					<h1>{traktivity_dash.title}</h1>
					{ displayMenu ? <Nav /> : null }
				</div>
			</header>
		)
	}
}

Header.propTypes = {
	step: PropTypes.number.isRequired,
};

export default Header;
