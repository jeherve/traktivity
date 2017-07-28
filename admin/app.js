/* External dependencies */
import React from 'react';
import { render } from 'react-dom';
import { BrowserRouter, Switch, Route } from 'react-router-dom';

/* Internal dependencies */
import Setup from './components/Setup';
import Parameters from './components/Parameters';
import Faq from './components/Faq';

render((
	<BrowserRouter>
		<Switch>
			// <Route exact path='/' component={ Dashboard }/>
			// <Route path='/parameters' component={ Parameters }/>
			// <Route path='/faq' component={ Faq }/>
			<Route path="*" component={ Setup } />
		</Switch>
	</BrowserRouter>
), document.querySelector( '#main' ) );
