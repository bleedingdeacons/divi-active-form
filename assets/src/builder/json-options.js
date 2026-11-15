/**
 * A Dropdown's JSON or ACF field options in the builder, fetched through divi-active-form/v1/options so
 * they are read exactly as the server reads them (same normalising, same fallbacks).
 * Shared by the field preview and the Conditional Logic rule editor.
 */
import { useEffect, useState } from 'react';
import { hasRemoteOptions, optionsQuery } from './field-options';

const builderData = () => window.DiviActiveFormBuilderData || {};

const cache = new Map();

export const useJsonOptions = ( field ) => {
	const query = hasRemoteOptions( field ) ? optionsQuery( field ) : '';
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
