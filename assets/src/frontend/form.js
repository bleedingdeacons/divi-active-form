/**
 * Front-end entry: enhances every Divi Active Form on the page.
 */
import './form.css';
import { initActiveForms } from './form-core';

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => initActiveForms() );
} else {
	initActiveForms();
}
