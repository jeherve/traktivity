/**
 * WordPress dependencies
 */
import { createRoot } from '@wordpress/element';

/**
 * Internal dependencies
 */
import Setup from './components/Setup';
import './style.css';

const container = document.querySelector( '#main' );

if ( container ) {
	createRoot( container ).render( <Setup /> );
}
