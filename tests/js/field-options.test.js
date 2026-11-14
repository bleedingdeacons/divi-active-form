import { describe, it, expect } from 'vitest';
import { conditionSummary, emptyLabel, fieldFromAttrs, optionsQuery, parseStaticOptions, sanitizeName } from '../../assets/src/builder/field-options';

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
		expect( fieldFromAttrs( attrs( 'Category', { name: 'category', type: 'json', required: 'on', source: '/wp-json/x', width: '50', labelPosition: 'hidden' } ) ) ).toMatchObject( {
			name: 'category',
			label: 'Category',
			type: 'json',
			required: true,
			width: '50',
			labelPosition: 'hidden',
			source: '/wp-json/x',
			valueKey: 'value',
			labelKey: 'label',
		} );
	} );

	it( 'derives the name from the label and defaults to a text field', () => {
		expect( fieldFromAttrs( attrs( 'Email Address!', { type: 'file' } ) ) ).toMatchObject( { name: 'email_address', type: 'text', width: '100', labelPosition: 'top' } );
		expect( fieldFromAttrs( attrs( 'A', { width: '33', labelPosition: 'right' } ) ) ).toMatchObject( { width: '100', labelPosition: 'top' } );
	} );

	it( 'falls back to the module.json defaults, as Divi does on the server', () => {
		expect( fieldFromAttrs( undefined ) ).toMatchObject( {
			name: 'field',
			label: 'Field',
			type: 'text',
			source: '/wp-json/divi-active-form/v1/example-options',
			emptyLabel: '',
		} );
		expect( parseStaticOptions( fieldFromAttrs( undefined ).options ).map( ( o ) => o.label ) ).toEqual( [ 'Small', 'Medium', 'Large' ] );
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
		expect( emptyLabel( { emptyLabel: '', placeholder: '' } ) ).toBe( 'Choose an option' );
	} );
} );

describe( 'Hint settings', () => {
	it( 'reads the hint and its position, defaulting to below', () => {
		const withHint = { ...attrs( 'Email', {} ), hint: { advanced: { text: { desktop: { value: ' Reply only ' } }, position: { desktop: { value: 'above' } } } } };

		expect( fieldFromAttrs( withHint ) ).toMatchObject( { hint: 'Reply only', hintPosition: 'above' } );
		expect( fieldFromAttrs( attrs( 'Email', {} ) ) ).toMatchObject( { hint: '', hintPosition: 'below' } );
	} );
} );

describe( 'Conditional Logic settings', () => {
	it( 'reads the rules and match, and summarises them for the builder', () => {
		const field = fieldFromAttrs( attrs( 'Phone', { visibleRules: 'callback is checked\n\n  size = large  ', visibleMatch: 'any', enabledRules: '' } ) );

		expect( field.visibleRules ).toEqual( [ 'callback is checked', 'size = large' ] );
		expect( field.visibleMatch ).toBe( 'any' );
		expect( field.enabledRules ).toEqual( [] );
		expect( conditionSummary( 'Shown', field.visibleRules, field.visibleMatch ) ).toBe( 'Shown when callback is checked or size = large' );
		expect( conditionSummary( 'Enabled', field.enabledRules, field.enabledMatch ) ).toBe( '' );
	} );
} );
