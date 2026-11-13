/**
 * Front-end entry: enhances every Register Form on the page.
 */
import './form.css';
import { initRegisterForms } from './form-core';

if ( document.readyState === 'loading' ) {
	document.addEventListener( 'DOMContentLoaded', () => initRegisterForms() );
} else {
	initRegisterForms();
}
