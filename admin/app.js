/* External dependencies */
import React from 'react';
import { render } from 'react-dom';
import { BrowserRouter, Switch, Route } from 'react-router-dom';

/* Internal dependencies */
import Setup from './components/Setup';
import Dashboard from './components/Dashboard';
import Parameters from './components/Parameters';
import Faq from './components/Faq';

render((
	<BrowserRouter>
		<Switch>
			// <Route exact path='/' component={ Setup }/>
			// <Route path={`${traktivity_dash.dash_url}/parameters`} component={ Parameters }/>
			// <Route path={`${traktivity_dash.dash_url}/faq`} component={ Faq }/>
			<Route path="*" component={ Setup } />
		</Switch>
	</BrowserRouter>
), document.querySelector( '#main' ) );
