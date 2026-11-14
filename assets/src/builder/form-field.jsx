/**
 * Builder definition of the Form Field module (a Register Form child). Settings panels
 * come from module.json; this file renders the field as the front end will, fetching a
 * JSON dropdown's options through divi-active-form/v1/options.
 */
import React, { useEffect, useState } from 'react';
import { ModuleContainer, StyleContainer, elementClassnames } from '@divi/module';
import metadata from '../../../modules-json/form-field/module.json';
import { conditionSummary, emptyLabel, fieldFromAttrs, optionsQuery, parseStaticOptions } from './field-options';

const builderData = () => window.DiviActiveFormBuilderData || {};

const cache = new Map();

/** A JSON dropdown's options, as the server reads them (same normalising, same fallbacks). */
const useJsonOptions = ( field ) => {
	const query = field.type === 'json' && field.source ? optionsQuery( field ) : '';
	const [ state, setState ] = useState( () => ( cache.has( query ) ? cache.get( query ) : { status: 'idle', options: [] } ) );

	useEffect( () => {
		if ( ! query ) {
			setState( { status: 'idle', options: [] } );
			return undefined;
		}
		if ( cache.has( query ) ) {
			setState( cache.get( query ) );
			return undefined;
		}

		const { optionsUrl, nonce } = builderData();
		if ( ! optionsUrl ) {
			setState( { status: 'error', options: [], message: 'Builder data is missing.' } );
			return undefined;
		}

		const controller = new AbortController();
		// Debounced: the source is typed a character at a time.
		const timer = setTimeout( () => {
			setState( { status: 'loading', options: [] } );
			fetch( `${ optionsUrl }${ optionsUrl.includes( '?' ) ? '&' : '?' }${ query }`, {
				credentials: 'same-origin',
				headers: { 'X-WP-Nonce': nonce || '' },
				signal: controller.signal,
			} )
				.then( ( response ) => {
					if ( ! response.ok ) {
						throw new Error( `HTTP ${ response.status }` );
					}
					return response.json();
				} )
				.then( ( body ) => {
					const result = body.readable
						? { status: 'ready', options: body.options || [] }
						: { status: 'error', options: [], message: 'The JSON source could not be read.' };
					cache.set( query, result );
					setState( result );
				} )
				.catch( ( error ) => {
					if ( error.name !== 'AbortError' ) {
						setState( { status: 'error', options: [], message: error.message } );
					}
				} );
		}, 400 );

		return () => {
			clearTimeout( timer );
			controller.abort();
		};
	}, [ query ] );

	return state;
};

const ModuleStyles = ( { elements, settings, mode, state, noStyleTag } ) => (
	<StyleContainer mode={ mode } state={ state } noStyleTag={ noStyleTag }>
		{ elements.style( {
			attrName: 'module',
			styleProps: { disabledOn: { disabledModuleVisibility: settings?.disabledModuleVisibility } },
		} ) }
	</StyleContainer>
);

const moduleClassnames = ( { classnamesInstance, attrs } ) => {
	classnamesInstance.add( 'daf-field-module', true );
	classnamesInstance.add( 'daf-field-module--half', fieldFromAttrs( attrs ).fullwidth === false );
	classnamesInstance.add( elementClassnames( { attrs: attrs?.module?.decoration ?? {} } ) );
};

const Note = ( { children } ) => <span className="daf-field__note">{ children }</span>;

const Control = ( { field, json } ) => {
	const common = { className: 'daf-field__input', name: field.name, readOnly: true, tabIndex: -1 };

	switch ( field.type ) {
		case 'textarea':
			return <textarea { ...common } rows={ 5 } placeholder={ field.placeholder } />;
		case 'select':
		case 'json': {
			const options = field.type === 'select' ? parseStaticOptions( field.options ) : json.options;
			return (
				<select { ...common } className="daf-field__input daf-field__select" defaultValue="">
					<option value="">{ emptyLabel( field ) }</option>
					{ options.map( ( option ) => (
						<option key={ option.value } value={ option.value }>
							{ option.label }
						</option>
					) ) }
				</select>
			);
		}
		default:
			return <input { ...common } type={ field.type } placeholder={ field.placeholder } />;
	}
};

const JsonStatus = ( { field, json } ) => {
	if ( field.type !== 'json' ) {
		return null;
	}
	if ( ! field.source ) {
		return <Note>Set a JSON Source under Dropdown Options.</Note>;
	}
	if ( json.status === 'loading' ) {
		return <Note>Loading options…</Note>;
	}
	if ( json.status === 'error' ) {
		return <Note>{ `Could not load options: ${ json.message }` }</Note>;
	}
	if ( json.status === 'ready' ) {
		return <Note>{ `${ json.options.length } option${ json.options.length === 1 ? '' : 's' } from ${ field.source }` }</Note>;
	}
	return null;
};

/** The field's Conditional Logic, in words: the builder always shows the field. */
const Conditions = ( { field } ) => {
	const lines = [
		conditionSummary( 'Shown', field.visibleRules, field.visibleMatch ),
		conditionSummary( 'Enabled', field.enabledRules, field.enabledMatch ),
	].filter( Boolean );
	return lines.length ? <Note>{ lines.join( ' · ' ) }</Note> : null;
};

const FormFieldEdit = ( { attrs, elements, id, name } ) => {
	const field = fieldFromAttrs( attrs );
	const json = useJsonOptions( field );

	let body;
	if ( ! field.name ) {
		body = <p className="daf-field daf-field--unconfigured">Give this field a label or a field name.</p>;
	} else if ( field.type === 'checkbox' ) {
		body = (
			<div className="daf-field daf-field--checkbox">
				<label className="daf-field__checkbox">
					<input type="checkbox" tabIndex={ -1 } readOnly /> <span>{ field.label }</span>
				</label>
				<Conditions field={ field } />
			</div>
		);
	} else {
		body = (
			<div className={ `daf-field daf-field--${ field.type }` }>
				{ field.label && (
					<label className="daf-field__label">
						{ field.label }
						{ field.required && <span className="daf-field__required"> *</span> }
					</label>
				) }
				<Control field={ field } json={ json } />
				<JsonStatus field={ field } json={ json } />
				<Conditions field={ field } />
			</div>
		);
	}

	return (
		<ModuleContainer
			attrs={ attrs }
			elements={ elements }
			id={ id }
			name={ name }
			stylesComponent={ ModuleStyles }
			classnamesFunction={ moduleClassnames }
		>
			{ elements.styleComponents( { attrName: 'module' } ) }
			{ body }
		</ModuleContainer>
	);
};

export const formFieldModule = {
	metadata,
	renderers: {
		edit: FormFieldEdit,
	},
};
