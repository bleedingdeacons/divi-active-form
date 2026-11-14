import { describe, it, expect } from 'vitest';
import { needsValue, operatorLabel, parseLine, parseRules, serializeRules } from '../../assets/src/builder/rules';

// Mirrors tests/php/ConditionsTest.php: the visual editor must read rules exactly as
// the server does.
describe( 'parseLine', () => {
	it( 'reads each kind of rule', () => {
		expect( parseLine( 'size = large' ) ).toEqual( { field: 'size', op: '=', value: 'large' } );
		expect( parseLine( "size != 'small'" ) ).toEqual( { field: 'size', op: '!=', value: 'small' } );
		expect( parseLine( 'email is empty' ) ).toEqual( { field: 'email', op: 'empty', value: '' } );
		expect( parseLine( 'email is not empty' ) ).toEqual( { field: 'email', op: 'filled', value: '' } );
		expect( parseLine( 'agree is checked' ) ).toEqual( { field: 'agree', op: 'filled', value: '' } );
		expect( parseLine( 'agree is not checked' ) ).toEqual( { field: 'agree', op: 'empty', value: '' } );
		expect( parseLine( 'size=large' ) ).toEqual( { field: 'size', op: '=', value: 'large' } );
	} );

	it( 'returns null for lines it cannot read', () => {
		expect( parseLine( 'size > 3' ) ).toBeNull();
		expect( parseLine( 'just words' ) ).toBeNull();
		expect( parseLine( '   ' ) ).toBeNull();
	} );
} );

describe( 'parseRules and serializeRules', () => {
	it( 'keeps unreadable lines as they were typed', () => {
		const rows = parseRules( 'size = large\n\nsize > 3\nagree is checked' );

		expect( rows ).toEqual( [
			{ field: 'size', op: '=', value: 'large' },
			{ raw: 'size > 3' },
			{ field: 'agree', op: 'filled', value: '' },
		] );
		expect( serializeRules( rows, { agree: 'checkbox' } ) ).toBe( 'size = large\nsize > 3\nagree is checked' );
	} );

	it( 'words empty / filled for checkboxes and for everything else', () => {
		const rows = [
			{ field: 'agree', op: 'filled', value: '' },
			{ field: 'agree', op: 'empty', value: '' },
			{ field: 'email', op: 'filled', value: '' },
			{ field: 'email', op: 'empty', value: '' },
			{ field: 'size', op: '!=', value: ' small ' },
		];

		expect( serializeRules( rows, { agree: 'checkbox', email: 'email' } ) ).toBe(
			'agree is checked\nagree is not checked\nemail is not empty\nemail is empty\nsize != small'
		);
	} );

	it( 'drops a rule with no field chosen yet', () => {
		expect( serializeRules( [ { field: '', op: '=', value: 'x' }, { field: 'a', op: '=', value: '1' } ] ) ).toBe( 'a = 1' );
	} );

	it( 'round-trips through the text', () => {
		const text = 'size = large\nemail is not empty\nagree is not checked';

		expect( serializeRules( parseRules( text ), { agree: 'checkbox' } ) ).toBe( text );
	} );
} );

describe( 'operatorLabel and needsValue', () => {
	it( 'names operators for the field type', () => {
		expect( operatorLabel( 'filled', 'checkbox' ) ).toBe( 'is checked' );
		expect( operatorLabel( 'filled', 'text' ) ).toBe( 'is not empty' );
		expect( operatorLabel( '!=', 'select' ) ).toBe( 'does not equal' );
	} );

	it( 'asks for a value only when comparing', () => {
		expect( [ '=', '!=', 'filled', 'empty' ].map( needsValue ) ).toEqual( [ true, true, false, false ] );
	} );
} );
