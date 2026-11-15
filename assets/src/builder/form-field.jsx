/**
 * Builder definition of the Form Field module (a Divi Active Form child). Settings panels
 * come from module.json (Conditional Logic uses this plugin's own rule editor, in
 * rule-editor.jsx); this file renders the field as the front end will.
 */
import React from 'react';
import { ModuleContainer, StyleContainer, elementClassnames } from '@divi/module';
import metadata from '../../../modules-json/form-field/module.json';
import { conditionSummary, emptyLabel, fieldFromAttrs, parseStaticOptions } from './field-options';
import { useJsonOptions } from './json-options';

const ModuleStyles = ( { attrs, elements, settings, orderClass, mode, state, noStyleTag } ) => (
	<StyleContainer mode={ mode } state={ state } noStyleTag={ noStyleTag }>
		{ elements.style( {
			attrName: 'module',
			styleProps: { disabledOn: { disabledModuleVisibility: settings?.disabledModuleVisibility } },
		} ) }
		{ /* Hint Colour, as FormFieldModule::module_styles() prints it on the front end. */ }
		{ elements.style( {
			attrName: 'hint',
			styleProps: {
				advancedStyles: [
					{
						componentName: 'divi/common',
						props: {
							selector: `${ orderClass } .daf-field__hint`,
							attr: attrs?.hint?.advanced?.color,
							property: 'color',
						},
					},
				],
			},
		} ) }
	</StyleContainer>
);

/** The field's hint, above or below the input as FieldRenderer places it. */
const Hint = ( { field, at } ) =>
	field.hint && field.hintPosition === at ? (
		<p className={ `daf-field__hint daf-field__hint--${ field.hintPosition }` }>{ field.hint }</p>
	) : null;

const moduleClassnames = ( { classnamesInstance, attrs } ) => {
	classnamesInstance.add( 'daf-field-module', true );
	classnamesInstance.add( `daf-field-module--w${ fieldFromAttrs( attrs ).width }`, true );
	classnamesInstance.add( elementClassnames( { attrs: attrs?.module?.decoration ?? {} } ) );
};

const Note = ( { children, className = '' } ) => <span className={ `daf-field__note ${ className }`.trim() }>{ children }</span>;

const Control = ( { field, json } ) => {
	const common = { className: 'daf-field__input', name: field.name, readOnly: true, tabIndex: -1 };

	switch ( field.type ) {
		case 'textarea':
			return <textarea { ...common } rows={ 5 } placeholder={ field.placeholder } />;
		case 'select':
		case 'json':
		case 'acf': {
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
	if ( field.type !== 'json' && field.type !== 'acf' ) {
		return null;
	}
	if ( field.type === 'json' && ! field.source ) {
		return <Note>Options From is JSON Source: set a JSON Source under Dropdown Options.</Note>;
	}
	if ( field.type === 'acf' && ! ( field.acfPostType && field.acfField ) ) {
		return <Note>Options From is ACF Field: set an ACF Post Type and ACF Field under Dropdown Options.</Note>;
	}
	if ( json.status === 'loading' ) {
		return <Note>Loading options…</Note>;
	}
	if ( json.status === 'error' ) {
		return <Note>{ `Could not load options: ${ json.message }` }</Note>;
	}
	if ( json.status === 'ready' ) {
		const from = field.type === 'acf' ? `${ field.acfField } on ${ field.acfPostType }` : field.source;
		return <Note>{ `${ json.options.length } option${ json.options.length === 1 ? '' : 's' } from ${ from }` }</Note>;
	}
	return null;
};

/** The field's Conditional Logic, in words: the builder always shows the field. */
const Conditions = ( { field } ) => {
	const lines = [
		conditionSummary( 'Shown', field.visibleRules, field.visibleMatch ),
		conditionSummary( 'Enabled', field.enabledRules, field.enabledMatch ),
	].filter( Boolean );
	return lines.length ? <Note className="daf-field__note--conditions">{ lines.join( ' · ' ) }</Note> : null;
};

const FormFieldEdit = ( { attrs, elements, id, name } ) => {
	const field = fieldFromAttrs( attrs );
	const json = useJsonOptions( field );

	let body;
	if ( ! field.name ) {
		body = <p className="daf-field daf-field--unconfigured">Give this field a label or a field name.</p>;
	} else if ( field.type === 'checkbox' ) {
		body = (
			<div className={ `daf-field daf-field--checkbox daf-field--label-${ field.labelPosition }${ field.hint ? ` daf-field--hint-${ field.hintPosition }` : '' }` }>
				<Hint field={ field } at="above" />
				<label className="daf-field__checkbox">
					<input type="checkbox" tabIndex={ -1 } readOnly />{ ' ' }
					<span className={ field.labelPosition === 'hidden' ? 'daf-screen-reader-text' : undefined }>{ field.label }</span>
				</label>
				<Hint field={ field } at="below" />
				<Conditions field={ field } />
			</div>
		);
	} else {
		body = (
			<div className={ `daf-field daf-field--${ field.type } daf-field--label-${ field.labelPosition }${ field.hint ? ` daf-field--hint-${ field.hintPosition }` : '' }` }>
				{ field.label && (
					<label className={ field.labelPosition === 'hidden' ? 'daf-field__label daf-screen-reader-text' : 'daf-field__label' }>
						{ field.label }
						{ field.required && <span className="daf-field__required"> *</span> }
					</label>
				) }
				<Hint field={ field } at="above" />
				<Control field={ field } json={ json } />
				<Hint field={ field } at="below" />
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
