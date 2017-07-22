/* External dependencies */
import React from 'react';

/* Internal dependencies */
import Header from './Header';
import Nav from './Nav.js';
import Footer from './Footer';

class Dashboard extends React.Component {
	render() {
		return (
			<div>
				<Header />
				<Nav />
				<p><a href="/parameters">Settings</a></p>
				<Footer />
			</div>
		)
	}
}

export default Dashboard;
