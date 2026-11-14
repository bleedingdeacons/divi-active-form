/**
 * Builder definition of the Divi Active Form module. Its Form Field children are rendered by
 * Divi through ChildModulesContainer; this file draws the form around them.
 */
import React from 'react';
import {
	ChildModulesContainer,
	CssStyle,
	ModuleContainer,
	StyleContainer,
	TextStyle,
	elementClassnames,
	textOptionsClassnames,
} from '@divi/module';
import metadata from '../../../modules-json/form/module.json';

const setting = ( attrs, key ) => attrs?.form?.advanced?.[ key ]?.desktop?.value ?? '';

const moduleClassnames = ( { classnamesInstance, attrs } ) => {
	classnamesInstance.add( 'daf-form-module', true );
	classnamesInstance.add( textOptionsClassnames( attrs?.module?.advanced?.text ), true );
	classnamesInstance.add( elementClassnames( { attrs: attrs?.module?.decoration ?? {} } ) );
};

const ModuleStyles = ( { attrs, elements, settings, orderClass, mode, state, noStyleTag } ) => (
	<StyleContainer mode={ mode } state={ state } noStyleTag={ noStyleTag }>
		{ elements.style( {
			attrName: 'module',
			styleProps: { disabledOn: { disabledModuleVisibility: settings?.disabledModuleVisibility } },
		} ) }
		<TextStyle selector={ orderClass } attr={ attrs?.module?.advanced?.text } />
		{ elements.style( { attrName: 'title' } ) }
		{ elements.style( { attrName: 'label' } ) }
		{ elements.style( { attrName: 'field' } ) }
		{ elements.style( { attrName: 'button' } ) }
		<CssStyle selector={ orderClass } attr={ attrs?.css } cssFields={ metadata.customCssFields } />
	</StyleContainer>
);

const FormEdit = ( { attrs, elements, id, name, childrenIds } ) => {
	const title = setting( attrs, 'title' );
	const submitLabel = setting( attrs, 'submitLabel' ) || 'Submit';
	const hasFields = Array.isArray( childrenIds ) && childrenIds.length > 0;

	return (
		<ModuleContainer
			attrs={ attrs }
			elements={ elements }
			id={ id }
			name={ name }
			childrenIds={ childrenIds }
			stylesComponent={ ModuleStyles }
			classnamesFunction={ moduleClassnames }
		>
			{ elements.styleComponents( { attrName: 'module' } ) }
			{ title && <h3 className="daf-form__title">{ title }</h3> }
			<div className="daf-form">
				<div className="daf-form__fields">
					{ hasFields ? (
						<ChildModulesContainer ids={ childrenIds } />
					) : (
						<p className="daf-builder-empty">Add a Field to start building the form.</p>
					) }
				</div>
				<div className="daf-form__footer">
					<button type="button" className="et_pb_button daf-form__submit" tabIndex={ -1 }>
						{ submitLabel }
					</button>
				</div>
			</div>
		</ModuleContainer>
	);
};

export const formModule = {
	metadata,
	renderers: {
		edit: FormEdit,
	},
};
