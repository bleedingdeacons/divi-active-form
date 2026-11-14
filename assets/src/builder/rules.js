/**
 * Conditional Logic rules as the visual editor works with them, and the text they are
 * stored as. Reading mirrors Conditions::parse() in PHP, so what the editor shows is
 * what the server will apply:
 *
 *   size = large          { field: 'size', op: '=', value: 'large' }
 *   size != small         { field: 'size', op: '!=', value: 'small' }
 *   email is empty        { field: 'email', op: 'empty' }
 *   email is not empty    { field: 'email', op: 'filled' }
 *   agree is checked      { field: 'agree', op: 'filled' }
 *   agree is not checked  { field: 'agree', op: 'empty' }
 *
 * A line that cannot be read is kept as it is, so the editor never loses text someone
 * typed; the server ignores it.
 */

export const OPERATORS = [ '=', '!=', 'filled', 'empty' ];

const sanitizeName = ( raw ) =>
	String( raw || '' )
		.trim()
		.toLowerCase()
		.replace( /\s+/g, '_' )
		.replace( /[^a-z0-9_-]/g, '' )
		.replace( /^[_-]+/, '' );

const unquote = ( value ) => {
	if ( value.length >= 2 && ( value[ 0 ] === '"' || value[ 0 ] === "'" ) && value[ value.length - 1 ] === value[ 0 ] ) {
		return value.slice( 1, -1 );
	}
	return value;
};

/** One line, as a rule, or null when it cannot be read. */
export function parseLine( line ) {
	const text = String( line || '' ).trim();
	if ( ! text ) {
		return null;
	}

	let m = text.match( /^([a-z0-9_-]+)\s+is\s+(not\s+)?(empty|checked)$/i );
	if ( m ) {
		const isEmpty = ( m[ 3 ].toLowerCase() === 'empty' ) !== Boolean( m[ 2 ] );
		return { field: sanitizeName( m[ 1 ] ), op: isEmpty ? 'empty' : 'filled', value: '' };
	}

	m = text.match( /^([a-z0-9_-]+)\s*(!=|=)\s*(.*)$/i );
	if ( m ) {
		return { field: sanitizeName( m[ 1 ] ), op: m[ 2 ], value: unquote( m[ 3 ].trim() ) };
	}

	return null;
}

/**
 * The stored text as editor rows: readable lines become rules, the rest are kept raw.
 *
 * @returns {Array<{ field: string, op: string, value: string } | { raw: string }>}
 */
export function parseRules( text ) {
	return String( text || '' )
		.split( /\r\n|\r|\n/ )
		.map( ( line ) => line.trim() )
		.filter( Boolean )
		.map( ( line ) => parseLine( line ) || { raw: line } );
}

/**
 * Editor rows back to stored text. A checkbox field's empty/filled tests are written as
 * "is not checked" / "is checked", which read naturally and mean the same.
 *
 * @param {Array} rows
 * @param {Record<string, string>} types Field name => type, to word checkbox rules.
 */
export function serializeRules( rows, types = {} ) {
	return rows
		.map( ( row ) => {
			if ( 'raw' in row ) {
				return row.raw;
			}
			if ( ! row.field ) {
				return '';
			}
			const checkbox = types[ row.field ] === 'checkbox';
			switch ( row.op ) {
				case 'empty':
					return `${ row.field } is ${ checkbox ? 'not checked' : 'empty' }`;
				case 'filled':
					return `${ row.field } is ${ checkbox ? 'checked' : 'not empty' }`;
				case '!=':
					return `${ row.field } != ${ String( row.value ?? '' ).trim() }`;
				default:
					return `${ row.field } = ${ String( row.value ?? '' ).trim() }`;
			}
		} )
		.filter( Boolean )
		.join( '\n' );
}

/** The words for an operator, for the field it tests. */
export function operatorLabel( op, type ) {
	if ( type === 'checkbox' ) {
		return { '=': 'equals', '!=': 'does not equal', filled: 'is checked', empty: 'is not checked' }[ op ];
	}
	return { '=': 'equals', '!=': 'does not equal', filled: 'is not empty', empty: 'is empty' }[ op ];
}

/** Whether an operator compares against a value (and so needs a value input). */
export const needsValue = ( op ) => op === '=' || op === '!=';
