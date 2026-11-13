import { describe, it, expect } from 'vitest';
import { emptyLabel, fieldFromAttrs, optionsQuery, parseStaticOptions, sanitizeName } from '../../assets/src/builder/field-options';

const attrs = ( label, advanced ) => ( {
	fieldItem: {
		innerContent: { desktop: { value: label } },
		advanced: Object.fromEntries( Object.entries( advanced ).map( ( [ key, value ] ) => [ key, { desktop: { value } } ] ) ),
	},
} );

// These mirror the PHP tests in tests/php/SchemaTest.php and OptionsTest.php: the
// builder preview must read a field exactly as the front end does.
describe( 'fieldFromAttrs', () => {
	it( 'reads a JSON dropdown', () => {
		expect( fieldFromAttrs( attrs( 'Your group', { name: 'group', type: 'json', required: 'on', source: '/wp-json/x', fullwidth: 'off' } ) ) ).toMatchObject( {
			name: 'group',
			label: 'Your group',
			type: 'json',
			required: true,
			fullwidth: false,
			source: '/wp-json/x',
			valueKey: 'value',
			labelKey: 'label',
		} );
	} );

	it( 'derives the name from the label and defaults to a text field', () => {
		expect( fieldFromAttrs( attrs( 'Email Address!', { type: 'file' } ) ) ).toMatchObject( { name: 'email_address', type: 'text', fullwidth: true } );
	} );

	it( 'copes with missing attributes', () => {
		expect( fieldFromAttrs( undefined ) ).toMatchObject( { name: '', label: '', type: 'text' } );
	} );
} );

describe( 'sanitizeName', () => {
	it( 'never yields a protected (underscore) meta key', () => {
		expect( sanitizeName( '_edit_lock' ) ).toBe( 'edit_lock' );
		expect( sanitizeName( '__-x' ) ).toBe( 'x' );
	} );
} );

describe( 'parseStaticOptions', () => {
	it( 'parses value|label lines and bare labels, in order, without repeats', () => {
		expect( parseStaticOptions( '10|Ten\r\n\n  Plain  \n2|Two\n10|Duplicate' ) ).toEqual( [
			{ value: '10', label: 'Ten' },
			{ value: 'Plain', label: 'Plain' },
			{ value: '2', label: 'Two' },
		] );
	} );
} );

describe( 'optionsQuery and emptyLabel', () => {
	it( 'sends the source and keys to the preview endpoint', () => {
		const field = fieldFromAttrs( attrs( 'G', { type: 'json', source: '/wp-json/wp/v2/categories?per_page=100', labelKey: 'name' } ) );

		expect( new URLSearchParams( optionsQuery( field ) ).get( 'source' ) ).toBe( '/wp-json/wp/v2/categories?per_page=100' );
		expect( new URLSearchParams( optionsQuery( field ) ).get( 'labelKey' ) ).toBe( 'name' );
	} );

	it( 'prefers the first-option text, then the placeholder', () => {
		expect( emptyLabel( { emptyLabel: 'Choose', placeholder: 'p' } ) ).toBe( 'Choose' );
		expect( emptyLabel( { emptyLabel: '', placeholder: 'p' } ) ).toBe( 'p' );
		expect( emptyLabel( { emptyLabel: '', placeholder: '' } ) ).toBe( 'Select…' );
	} );
} );
