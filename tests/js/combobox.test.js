import { describe, it, expect } from 'vitest';
import { filterOptions, findOption, moveHighlight } from '../../assets/src/builder/combobox';

const types = [
	{ value: 'post', label: 'Posts (post)' },
	{ value: 'page', label: 'Pages (page)' },
	{ value: 'project', label: 'Projects (project)' },
];

describe( 'filterOptions', () => {
	it( 'matches the value or the label, ignoring case', () => {
		expect( filterOptions( types, 'PAG' ).map( ( o ) => o.value ) ).toEqual( [ 'page' ] );
		expect( filterOptions( types, 'projects' ).map( ( o ) => o.value ) ).toEqual( [ 'project' ] );
		expect( filterOptions( types, 'p' ) ).toHaveLength( 3 );
	} );

	it( 'lists everything when opened with the button, or when nothing is typed', () => {
		expect( filterOptions( types, 'page', true ) ).toHaveLength( 3 );
		expect( filterOptions( types, '  ' ) ).toHaveLength( 3 );
	} );

	it( 'lists nothing for text that matches nothing', () => {
		expect( filterOptions( types, 'answerz' ) ).toEqual( [] );
	} );
} );

describe( 'findOption', () => {
	it( 'finds the exact value only', () => {
		expect( findOption( types, ' page ' ) ).toEqual( types[ 1 ] );
		expect( findOption( types, 'pag' ) ).toBeUndefined();
	} );
} );

describe( 'moveHighlight', () => {
	it( 'starts at either end and wraps', () => {
		expect( moveHighlight( -1, 1, 3 ) ).toBe( 0 );
		expect( moveHighlight( -1, -1, 3 ) ).toBe( 2 );
		expect( moveHighlight( 2, 1, 3 ) ).toBe( 0 );
		expect( moveHighlight( 0, -1, 3 ) ).toBe( 2 );
		expect( moveHighlight( 0, 1, 0 ) ).toBe( -1 );
	} );
} );
