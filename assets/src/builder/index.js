/**
 * Visual Builder entry point: registers the Divi Active Form and its Form Field child with
 * Divi 5's module library, and its two custom settings fields (the Conditional Logic
 * rule editor and the Post Type dropdown) with the field library.
 */
import { addAction } from '@wordpress/hooks';
import { registerModule } from '@divi/module-library';
import { registerFieldComponent } from '@divi/field-library';
import { formModule } from './form';
import { formFieldModule } from './form-field';
import { FIELD_NAME as RULES_FIELD, RuleEditor } from './rule-editor';
import { FIELD_NAME as POST_TYPE_FIELD, PostTypeField } from './post-type-field';

// Styles come from assets/build/form.css, which Assets::registerBuilder() loads into the builder.

// Settings fields are looked up by name when a settings panel opens, so this only has
// to happen before the first Form Field's settings are opened.
registerFieldComponent( { name: RULES_FIELD, component: RuleEditor } );
registerFieldComponent( { name: POST_TYPE_FIELD, component: PostTypeField } );

addAction( 'divi.moduleLibrary.registerModuleLibraryStore.after', 'diviActiveForm', () => {
	for ( const definition of [ formModule, formFieldModule ] ) {
		const { metadata, ...rest } = definition;
		registerModule( metadata, rest );
	}
} );
