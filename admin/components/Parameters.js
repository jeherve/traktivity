/* External dependencies */
import React from 'react';

/* Internal dependencies */
import Header from './Header';
import Nav from './Nav.js';
import Footer from './Footer';

class Parameters extends React.Component {
	render() {
		return (
			<div>
				<Header />
				<Nav />
				<p>Our Settings</p>
				<Footer />
			</div>
		)
	}
}

export default Parameters;
