/**
 * Reads a Form Field's settings from its Divi attributes, mirroring
 * FieldDefinition::fromAttrs() and StaticOptions::parse() on the PHP side, so the builder
 * preview shows what the front end will render.
 */
import metadata from '../../../modules-json/form-field/module.json';

// What Divi merges in on the server for any setting the editor has not changed.
const DEFAULTS = { fieldItem: metadata.attributes.fieldItem.default, hint: metadata.attributes.hint.default };

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

export const TYPES = [ 'text', 'email', 'tel', 'textarea', 'select', 'json', 'acf', 'checkbox' ];
export const WIDTHS = [ '25', '50', '75', '100' ];
export const LABEL_POSITIONS = [ 'top', 'left', 'below', 'hidden' ];

/** Same rules as FieldDefinition::sanitizeName(). */
export function sanitizeName( raw ) {
	return text( raw )
		.toLowerCase()
		.replace( /\s+/g, '_' )
		.replace( /[^a-z0-9_-]/g, '' )
		.replace( /^[_-]+/, '' );
}

export function fieldFromAttrs( attrs ) {
	const read = ( path ) => desktop( attrs, path ) ?? desktop( DEFAULTS, path );
	const setting = ( key ) => text( read( `fieldItem.advanced.${ key }` ) );
	const label = stripTags( text( read( 'fieldItem.innerContent' ) ) ).trim();
	const parsed = TYPES.includes( setting( 'type' ) ) ? setting( 'type' ) : 'text';
	// A Dropdown fed from a JSON source or an ACF field is the JSON or ACF type, as
	// FieldDefinition::type() has it.
	const from = setting( 'optionsFrom' );
	const type = parsed === 'select' && ( from === 'json' || from === 'acf' ) ? from : parsed;

	return {
		name: sanitizeName( setting( 'name' ) || label ),
		label,
		type,
		required: setting( 'required' ) === 'on',
		placeholder: setting( 'placeholder' ),
		width: WIDTHS.includes( setting( 'width' ) ) ? setting( 'width' ) : '100',
		labelPosition: LABEL_POSITIONS.includes( setting( 'labelPosition' ) ) ? setting( 'labelPosition' ) : 'top',
		hint: text( read( 'hint.advanced.text' ) ),
		acfPostType: setting( 'acfPostType' ).toLowerCase(),
		acfField: setting( 'acfField' ),
		hintPosition: text( read( 'hint.advanced.position' ) ) === 'above' ? 'above' : 'below',
		options: typeof read( 'fieldItem.advanced.options' ) === 'string' ? read( 'fieldItem.advanced.options' ) : '',
		source: setting( 'source' ),
		itemsPath: setting( 'itemsPath' ),
		valueKey: setting( 'valueKey' ) || 'value',
		labelKey: setting( 'labelKey' ) || 'label',
		emptyLabel: setting( 'emptyLabel' ),
		visibleRules: rulesText( read( 'fieldItem.advanced.visibleRules' ) ),
		visibleMatch: setting( 'visibleMatch' ) === 'any' ? 'any' : 'all',
		enabledRules: rulesText( read( 'fieldItem.advanced.enabledRules' ) ),
		enabledMatch: setting( 'enabledMatch' ) === 'any' ? 'any' : 'all',
	};
}

/** Whether a field's options come from the server (a Dropdown fed from JSON or an ACF field) and it has what it needs to ask. */
export function hasRemoteOptions( field ) {
	if ( ! field ) {
		return false;
	}
	if ( field.type === 'json' ) {
		return Boolean( field.source );
	}
	return field.type === 'acf' && Boolean( field.acfPostType && field.acfField );
}

/** A rules textarea's non-blank lines, trimmed. */
function rulesText( value ) {
	return String( typeof value === 'string' ? value : '' )
		.split( /\r\n|\r|\n/ )
		.map( ( line ) => line.trim() )
		.filter( Boolean );
}

/** One line for the builder preview, e.g. "Shown when size = large and email is not empty". */
export function conditionSummary( verb, lines, match ) {
	return lines.length ? `${ verb } when ${ lines.join( match === 'any' ? ' or ' : ' and ' ) }` : '';
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
	if ( field.type === 'acf' ) {
		return new URLSearchParams( { acfPostType: field.acfPostType, acfField: field.acfField } ).toString();
	}
	const params = new URLSearchParams( { source: field.source } );
	if ( field.itemsPath ) {
		params.set( 'itemsPath', field.itemsPath );
	}
	params.set( 'valueKey', field.valueKey );
	params.set( 'labelKey', field.labelKey );
	return params.toString();
}

export function emptyLabel( field ) {
	return field.emptyLabel || field.placeholder || 'Choose an option';
}
