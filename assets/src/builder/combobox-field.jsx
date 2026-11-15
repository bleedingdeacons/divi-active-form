/**
 * Editable dropdown (combobox) settings fields: type a value, or open the list (▾, or
 * the down arrow key) to pick one. Typing filters the list; any value can still be
 * typed, and one that is not in the list is flagged.
 *
 * Each list comes from the builder data that Assets::registerBuilder() passes in, so it
 * is built in PHP (and can include things the REST API does not expose). Registered
 * with Divi's field library under these names:
 *
 *   divi-active-form/post-type  post types (PostTypes::options()): the Divi Active
 *                               Form's Post Type, and a Form Field's ACF Post Type
 *   divi-active-form/acf-field  the fields on the post type chosen in the same Form
 *                               Field's ACF Post Type (GET /v1/fields: its ACF fields
 *                               and the post meta keys its posts use), or every ACF
 *                               field when none is chosen (AcfFields::options())
 */
import React, { useEffect, useMemo, useRef, useState } from 'react';
import { useSelect } from '@divi/data';
import { useCurrentModuleId } from '@divi/module-utils';
import { filterOptions, findOption, moveHighlight } from './combobox';

const builderList = ( key ) => ( window.DiviActiveFormBuilderData && window.DiviActiveFormBuilderData[ key ] ) || [];

const styles = {
	wrap: { position: 'relative' },
	row: { display: 'flex', gap: '4px' },
	input: { flex: '1 1 auto', minWidth: 0 },
	toggle: { flex: '0 0 auto', cursor: 'pointer', padding: '0 8px' },
	list: {
		position: 'absolute',
		zIndex: 100,
		left: 0,
		right: 0,
		top: '100%',
		maxHeight: '220px',
		overflowY: 'auto',
		margin: '2px 0 0',
		padding: 0,
		listStyle: 'none',
		background: '#fff',
		color: '#333',
		border: '1px solid #ccc',
		borderRadius: '3px',
		boxShadow: '0 2px 8px rgba(0,0,0,.15)',
	},
	item: { padding: '6px 8px', cursor: 'pointer' },
	active: { background: '#e8f0fe' },
	note: { margin: '6px 0 0', fontSize: '12px', opacity: 0.75 },
	warning: { margin: '6px 0 0', fontSize: '12px', color: '#b42318' },
};

const makeComboboxField = ( { useOptions, placeholder, listLabel, unknown } ) => ( { value, onChange, name, readonly = false } ) => {
	const { options, context = '', loading = false } = useOptions();
	const text = typeof value === 'string' ? value : '';
	const [ open, setOpen ] = useState( false );
	const [ showAll, setShowAll ] = useState( false );
	const [ highlight, setHighlight ] = useState( -1 );
	const blurTimer = useRef( null );

	const shown = filterOptions( options, text, showAll );
	const current = findOption( options, text );
	const set = ( next ) => onChange( { inputValue: next } );
	const choose = ( option ) => {
		set( option.value );
		setOpen( false );
		setHighlight( -1 );
	};

	const onKeyDown = ( e ) => {
		if ( e.key === 'ArrowDown' || e.key === 'ArrowUp' ) {
			e.preventDefault();
			if ( ! open ) {
				setOpen( true );
				setShowAll( true );
			}
			setHighlight( ( h ) => moveHighlight( h, e.key === 'ArrowDown' ? 1 : -1, shown.length ) );
		} else if ( e.key === 'Enter' && open && highlight >= 0 && shown[ highlight ] ) {
			e.preventDefault();
			choose( shown[ highlight ] );
		} else if ( e.key === 'Escape' && open ) {
			e.preventDefault();
			setOpen( false );
		}
	};

	return (
		<div style={ styles.wrap }>
			<div style={ styles.row }>
				<input
					type="text"
					style={ styles.input }
					name={ name }
					value={ text }
					readOnly={ readonly }
					placeholder={ placeholder }
					role="combobox"
					aria-expanded={ open }
					aria-autocomplete="list"
					onChange={ ( e ) => {
						set( e.target.value );
						setOpen( true );
						setShowAll( false );
						setHighlight( -1 );
					} }
					onKeyDown={ onKeyDown }
					onFocus={ () => clearTimeout( blurTimer.current ) }
					// Delayed, so a click on an option lands before the list closes.
					onBlur={ () => ( blurTimer.current = setTimeout( () => setOpen( false ), 150 ) ) }
				/>
				{ ! readonly && (
					<button
						type="button"
						style={ styles.toggle }
						aria-label={ listLabel }
						onMouseDown={ ( e ) => e.preventDefault() }
						onClick={ () => {
							setOpen( ! open );
							setShowAll( true );
							setHighlight( -1 );
						} }
					>
						▾
					</button>
				) }
			</div>
			{ open && shown.length > 0 && (
				<ul style={ styles.list } role="listbox">
					{ shown.map( ( option, index ) => (
						<li
							key={ option.value }
							role="option"
							aria-selected={ option.value === text }
							style={ index === highlight ? { ...styles.item, ...styles.active } : styles.item }
							onMouseDown={ ( e ) => e.preventDefault() }
							onMouseEnter={ () => setHighlight( index ) }
							onClick={ () => choose( option ) }
						>
							{ option.label }
						</li>
					) ) }
				</ul>
			) }
			{ loading && <p style={ styles.note }>Loading fields…</p> }
			{ ! loading && current && <p style={ styles.note }>{ current.label }</p> }
			{ ! loading && context && options.length === 0 && (
				<p style={ styles.note }>No fields found on { context } posts yet; a field name can still be typed.</p>
			) }
			{ ! loading && ! current && text.trim() !== '' && options.length > 0 && (
				<p style={ styles.warning }>{ unknown( text.trim(), context ) }</p>
			) }
		</div>
	);
};

export const POST_TYPE_FIELD = 'divi-active-form/post-type';
export const ACF_FIELD_FIELD = 'divi-active-form/acf-field';

/** The site's post types, from the builder data. */
const usePostTypes = () => ( { options: useMemo( () => builderList( 'postTypes' ), [] ) } );

const fieldsCache = new Map();

/**
 * The fields for the post type chosen in this same Form Field's ACF Post Type: its ACF
 * fields and the post meta keys its posts use, from /v1/fields. With no post type
 * chosen, every ACF field on the site, from the builder data.
 */
const usePostTypeFields = () => {
	const id = useCurrentModuleId();
	const postType = useSelect(
		( select ) => {
			const store = select( 'divi/edit-post' );
			const raw = id && store ? store.getModuleAttrs( id ) : null;
			const attrs = raw && typeof raw.toJS === 'function' ? raw.toJS() : raw;
			const value = attrs?.fieldItem?.advanced?.acfPostType?.desktop?.value;
			return typeof value === 'string' ? value.trim().toLowerCase() : '';
		},
		[ id ]
	);
	const fallback = useMemo( () => builderList( 'acfFields' ), [] );
	const [ state, setState ] = useState( () => ( fieldsCache.has( postType ) ? { options: fieldsCache.get( postType ), loading: false } : null ) );

	useEffect( () => {
		if ( ! postType ) {
			setState( null );
			return undefined;
		}
		if ( fieldsCache.has( postType ) ) {
			setState( { options: fieldsCache.get( postType ), loading: false } );
			return undefined;
		}
		const data = window.DiviActiveFormBuilderData || {};
		if ( ! data.fieldsUrl ) {
			setState( null );
			return undefined;
		}
		const controller = new AbortController();
		setState( { options: [], loading: true } );
		fetch( `${ data.fieldsUrl }${ data.fieldsUrl.includes( '?' ) ? '&' : '?' }postType=${ encodeURIComponent( postType ) }`, {
			credentials: 'same-origin',
			headers: { 'X-WP-Nonce': data.nonce || '' },
			signal: controller.signal,
		} )
			.then( ( response ) => ( response.ok ? response.json() : [] ) )
			.then( ( options ) => {
				const list = Array.isArray( options ) ? options : [];
				fieldsCache.set( postType, list );
				setState( { options: list, loading: false } );
			} )
			.catch( ( error ) => {
				if ( error.name !== 'AbortError' ) {
					setState( { options: [], loading: false } );
				}
			} );
		return () => controller.abort();
	}, [ postType ] );

	if ( ! postType || ! state ) {
		return { options: fallback, context: '' };
	}
	return { options: state.options, context: postType, loading: state.loading };
};

export const PostTypeField = makeComboboxField( {
	useOptions: usePostTypes,
	placeholder: 'e.g. post',
	listLabel: 'Show all post types',
	unknown: ( text ) => `“${ text }” is not a post type registered on this site.`,
} );

export const AcfFieldField = makeComboboxField( {
	useOptions: usePostTypeFields,
	placeholder: 'e.g. region',
	listLabel: 'Show the fields on this post type',
	unknown: ( text, postType ) =>
		postType
			? `No ${ postType } post uses “${ text }” yet, so the dropdown will be empty until one does.`
			: `“${ text }” is not an ACF field on this site; it will be read as plain post meta.`,
} );
