/**
 * Reads a Form Field's settings from its Divi attributes, mirroring
 * FieldDefinition::fromAttrs() and StaticOptions::parse() on the PHP side, so the builder
 * preview shows what the front end will render.
 */

const desktop = ( attrs, path ) => {
	let value = attrs;
	for ( const key of `${ path }.desktop.value`.split( '.' ) ) {
		if ( value === null || typeof value !== 'object' || ! ( key in value ) ) {
			return undefined;
		}
		value = value[ key ];
	}
	return value;
};

const text = ( value ) => ( typeof value === 'string' || typeof value === 'number' ? String( value ).trim() : '' );

const stripTags = ( html ) => html.replace( /<[^>]*>/g, '' );

export const TYPES = [ 'text', 'email', 'tel', 'textarea', 'select', 'json', 'checkbox' ];

/** Same rules as FieldDefinition::sanitizeName(). */
export function sanitizeName( raw ) {
	return text( raw )
		.toLowerCase()
		.replace( /\s+/g, '_' )
		.replace( /[^a-z0-9_-]/g, '' )
		.replace( /^[_-]+/, '' );
}

export function fieldFromAttrs( attrs ) {
	const setting = ( key ) => text( desktop( attrs, `fieldItem.advanced.${ key }` ) );
	const label = stripTags( text( desktop( attrs, 'fieldItem.innerContent' ) ) ).trim();
	const type = TYPES.includes( setting( 'type' ) ) ? setting( 'type' ) : 'text';

	return {
		name: sanitizeName( setting( 'name' ) || label ),
		label,
		type,
		required: setting( 'required' ) === 'on',
		placeholder: setting( 'placeholder' ),
		fullwidth: setting( 'fullwidth' ) !== 'off',
		options: typeof desktop( attrs, 'fieldItem.advanced.options' ) === 'string' ? desktop( attrs, 'fieldItem.advanced.options' ) : '',
		source: setting( 'source' ),
		itemsPath: setting( 'itemsPath' ),
		valueKey: setting( 'valueKey' ) || 'value',
		labelKey: setting( 'labelKey' ) || 'label',
		emptyLabel: setting( 'emptyLabel' ),
	};
}

/** Same rules as StaticOptions::parse(). */
export function parseStaticOptions( textValue ) {
	const seen = new Set();
	const options = [];
	for ( const raw of String( textValue || '' ).split( /\r\n|\r|\n/ ) ) {
		const line = raw.trim();
		if ( ! line ) {
			continue;
		}
		const bar = line.indexOf( '|' );
		const value = ( bar === -1 ? line : line.slice( 0, bar ) ).trim();
		const label = ( bar === -1 ? line : line.slice( bar + 1 ) ).trim();
		if ( ! value || seen.has( value ) ) {
			continue;
		}
		seen.add( value );
		options.push( { value, label: label || value } );
	}
	return options;
}

/** Query string for the builder's options preview endpoint. */
export function optionsQuery( field ) {
	const params = new URLSearchParams( { source: field.source } );
	if ( field.itemsPath ) {
		params.set( 'itemsPath', field.itemsPath );
	}
	params.set( 'valueKey', field.valueKey );
	params.set( 'labelKey', field.labelKey );
	return params.toString();
}

export function emptyLabel( field ) {
	return field.emptyLabel || field.placeholder || 'Select…';
}
