/**
 * The visual editor for a Form Field's Conditional Logic (Show When / Enable When).
 *
 * Registered with Divi's field library as `divi-active-form/condition-rules` and named
 * by those two settings in modules-json/form-field/module.json. It edits the same text
 * the server parses (one rule per line), so a form saved with it and a form typed by
 * hand are the same thing. "Edit as text" switches to a plain textarea.
 *
 * The fields a rule can test are the other Form Fields in the same Divi Active Form, read
 * from Divi's edit-post store: the field whose settings are open is
 * useCurrentModuleId(), its parent holds the siblings.
 */
import React, { useMemo, useState } from 'react';
import { useSelect } from '@divi/data';
import { useCurrentModuleId } from '@divi/module-utils';
import { fieldFromAttrs, parseStaticOptions } from './field-options';
import { useJsonOptions } from './json-options';
import { needsValue, operatorLabel, OPERATORS, parseRules, serializeRules } from './rules';

export const FIELD_NAME = 'divi-active-form/condition-rules';
const FORM_FIELD = 'divi-active-form/form-field';

const plain = ( value ) => ( value && typeof value.toJS === 'function' ? value.toJS() : value );

/** The other Form Fields in this field's form: [{ name, label, type, ... }]. */
const useSiblingFields = () => {
	const id = useCurrentModuleId();
	// Selected as JSON text so the store sees an equal value between renders.
	const json = useSelect(
		( select ) => {
			const store = select( 'divi/edit-post' );
			if ( ! id || ! store ) {
				return '[]';
			}
			const parent = store.getParentModuleId( id );
			const children = parent ? store.getModule( parent )?.children : null;
			const fields = ( children ? Array.from( children ) : [] )
				.filter( ( child ) => child !== id && store.getModuleName( child ) === FORM_FIELD )
				.map( ( child ) => fieldFromAttrs( plain( store.getModuleAttrs( child ) ) ) )
				.filter( ( field ) => field.name );
			return JSON.stringify( fields );
		},
		[ id ]
	);
	return useMemo( () => JSON.parse( json ), [ json ] );
};

const styles = {
	row: { display: 'flex', flexWrap: 'wrap', gap: '6px', alignItems: 'center', marginBottom: '8px' },
	select: { flex: '1 1 30%', minWidth: '7em' },
	value: { flex: '1 1 30%', minWidth: '7em' },
	remove: { flex: '0 0 auto', cursor: 'pointer', padding: '0 6px', lineHeight: '24px' },
	raw: { flex: '1 1 100%', fontFamily: 'monospace', opacity: 0.8 },
	actions: { display: 'flex', gap: '12px', alignItems: 'center' },
	link: { cursor: 'pointer', textDecoration: 'underline', background: 'none', border: 0, padding: 0, color: 'inherit' },
	note: { opacity: 0.7, fontSize: '12px', margin: '0 0 8px' },
	textarea: { width: '100%', minHeight: '6em', fontFamily: 'monospace' },
};

/** The value to compare with: the tested field's own options where it has some. */
const ValueInput = ( { field, value, onChange, readonly } ) => {
	const json = useJsonOptions( field && field.type === 'json' ? field : null );
	let options = null;
	if ( field && field.type === 'select' ) {
		options = parseStaticOptions( field.options );
	} else if ( field && field.type === 'json' ) {
		options = json.options;
	}

	if ( options && options.length ) {
		const known = options.some( ( option ) => option.value === value );
		return (
			<select style={ styles.value } value={ value } disabled={ readonly } onChange={ ( e ) => onChange( e.target.value ) }>
				<option value="">Choose a value</option>
				{ ! known && value !== '' && <option value={ value }>{ value }</option> }
				{ options.map( ( option ) => (
					<option key={ option.value } value={ option.value }>
						{ option.label }
					</option>
				) ) }
			</select>
		);
	}

	return (
		<input
			type="text"
			style={ styles.value }
			value={ value }
			readOnly={ readonly }
			placeholder={ field && field.type === 'json' && json.status === 'loading' ? 'Loading options…' : 'Value' }
			onChange={ ( e ) => onChange( e.target.value ) }
		/>
	);
};

export const RuleEditor = ( { value, onChange, name, readonly = false } ) => {
	const siblings = useSiblingFields();
	const [ asText, setAsText ] = useState( false );
	const text = typeof value === 'string' ? value : '';
	const rows = parseRules( text );

	const types = Object.fromEntries( siblings.map( ( field ) => [ field.name, field.type ] ) );
	const byName = Object.fromEntries( siblings.map( ( field ) => [ field.name, field ] ) );
	const save = ( next ) => onChange( { inputValue: serializeRules( next, types ) } );
	const update = ( index, changes ) => save( rows.map( ( row, i ) => ( i === index ? { ...row, ...changes } : row ) ) );
	const remove = ( index ) => save( rows.filter( ( row, i ) => i !== index ) );
	const add = () => save( [ ...rows, { field: siblings[ 0 ]?.name || '', op: siblings[ 0 ]?.type === 'checkbox' ? 'filled' : '=', value: '' } ] );

	if ( asText ) {
		return (
			<div>
				<textarea
					style={ styles.textarea }
					name={ name }
					value={ text }
					readOnly={ readonly }
					onChange={ ( e ) => onChange( { inputValue: e.target.value } ) }
				/>
				<div style={ styles.actions }>
					<button type="button" style={ styles.link } onClick={ () => setAsText( false ) }>
						Edit visually
					</button>
				</div>
			</div>
		);
	}

	return (
		<div>
			{ ! siblings.length && <p style={ styles.note }>Add other fields to this form to build rules on them.</p> }
			{ ! rows.length && siblings.length > 0 && <p style={ styles.note }>No rules: always.</p> }
			{ rows.map( ( row, index ) => {
				if ( 'raw' in row ) {
					return (
						<div key={ index } style={ styles.row }>
							<span style={ styles.raw } title="This line could not be read; it is kept as typed and ignored.">
								{ row.raw }
							</span>
							{ ! readonly && (
								<span role="button" tabIndex={ 0 } style={ styles.remove } aria-label="Remove rule" onClick={ () => remove( index ) }>
									×
								</span>
							) }
						</div>
					);
				}
				const field = byName[ row.field ];
				const type = field ? field.type : '';
				const ops = type === 'checkbox' ? [ 'filled', 'empty' ] : OPERATORS;
				return (
					<div key={ index } style={ styles.row }>
						<select
							style={ styles.select }
							value={ row.field }
							disabled={ readonly }
							aria-label="Field"
							onChange={ ( e ) => {
								const nextType = types[ e.target.value ];
								const op = nextType === 'checkbox' && needsValue( row.op ) ? 'filled' : row.op;
								update( index, { field: e.target.value, op, value: '' } );
							} }
						>
							{ ! field && <option value={ row.field }>{ row.field || 'Choose a field' }</option> }
							{ siblings.map( ( sibling ) => (
								<option key={ sibling.name } value={ sibling.name }>
									{ sibling.label || sibling.name }
								</option>
							) ) }
						</select>
						<select
							style={ styles.select }
							value={ row.op }
							disabled={ readonly }
							aria-label="Condition"
							onChange={ ( e ) => update( index, { op: e.target.value } ) }
						>
							{ ops.map( ( op ) => (
								<option key={ op } value={ op }>
									{ operatorLabel( op, type ) }
								</option>
							) ) }
						</select>
						{ needsValue( row.op ) && (
							<ValueInput field={ field } value={ row.value } readonly={ readonly } onChange={ ( v ) => update( index, { value: v } ) } />
						) }
						{ ! readonly && (
							<span role="button" tabIndex={ 0 } style={ styles.remove } aria-label="Remove rule" onClick={ () => remove( index ) }>
								×
							</span>
						) }
					</div>
				);
			} ) }
			<div style={ styles.actions }>
				{ ! readonly && siblings.length > 0 && (
					<button type="button" className="et-vb-button" onClick={ add }>
						+ Add rule
					</button>
				) }
				<button type="button" style={ styles.link } onClick={ () => setAsText( true ) }>
					Edit as text
				</button>
			</div>
		</div>
	);
};

RuleEditor.fieldName = FIELD_NAME;
