/**
 * Visual Builder entry point: registers the Register Form and its Form Field child with
 * Divi 5's module library, and the Conditional Logic rule editor with its field library.
 */
import { addAction } from '@wordpress/hooks';
import { registerModule } from '@divi/module-library';
import { registerFieldComponent } from '@divi/field-library';
import { registerFormModule } from './register-form';
import { formFieldModule } from './form-field';
import { FIELD_NAME, RuleEditor } from './rule-editor';

// Styles come from assets/build/form.css, which Assets::registerBuilder() loads into the builder.

// Settings fields are looked up by name when a settings panel opens, so this only has
// to happen before the first Form Field's settings are opened.
registerFieldComponent( { name: FIELD_NAME, component: RuleEditor } );

addAction( 'divi.moduleLibrary.registerModuleLibraryStore.after', 'diviActiveForm', () => {
	for ( const definition of [ registerFormModule, formFieldModule ] ) {
		const { metadata, ...rest } = definition;
		registerModule( metadata, rest );
	}
} );
