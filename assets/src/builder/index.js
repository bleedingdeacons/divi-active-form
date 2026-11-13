/**
 * Visual Builder entry point: registers the Register Form and its Form Field child with
 * Divi 5's module library.
 */
import { addAction } from '@wordpress/hooks';
import { registerModule } from '@divi/module-library';
import { registerFormModule } from './register-form';
import { formFieldModule } from './form-field';

// Styles come from assets/build/form.css, which Assets::registerBuilder() loads into the builder.

addAction( 'divi.moduleLibrary.registerModuleLibraryStore.after', 'diviActiveForm', () => {
	for ( const definition of [ registerFormModule, formFieldModule ] ) {
		const { metadata, ...rest } = definition;
		registerModule( metadata, rest );
	}
} );
