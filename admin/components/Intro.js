/* External dependencies */
import React from 'react';

class Intro extends React.Component {
	render() {
		return (
			<div className="intro card">
				<p><strong>{traktivity_dash.intro}</strong></p>
				<p>{traktivity_dash.description}</p>
				<div className="action">
					<button className="nav-button" onClick={this.props.nextStep}>{traktivity_dash.intro_next}</button>
				</div>
			</div>
		)
	}
}

export default Intro;
