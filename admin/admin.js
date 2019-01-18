/* External (WP) dependencies */
const { createElement, render } = wp.element;
const { __ } = wp.i18n;

/* Internal dependencies */
import Header from './comps/header.js';
import TraktForm from './comps/traktform.js';

// Main dashboard  container.
const mainDashContainer = createElement(
	'div',
	{
		className: 'traktivity_dashboard'
	},
	<div>
		<Header />
		<TraktForm />
	</div>
);

render( mainDashContainer, document.getElementById( 'main' ) );
