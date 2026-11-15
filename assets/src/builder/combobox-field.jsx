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
 *   divi-active-form/acf-field  ACF field names (AcfFields::options()): a Form
 *                               Field's ACF Field
 */
import React, { useMemo, useRef, useState } from 'react';
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

const makeComboboxField = ( { dataKey, placeholder, listLabel, unknown } ) => ( { value, onChange, name, readonly = false } ) => {
	const options = useMemo( () => builderList( dataKey ), [] );
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
			{ current && <p style={ styles.note }>{ current.label }</p> }
			{ ! current && text.trim() !== '' && options.length > 0 && (
				<p style={ styles.warning }>{ unknown( text.trim() ) }</p>
			) }
		</div>
	);
};

export const POST_TYPE_FIELD = 'divi-active-form/post-type';
export const ACF_FIELD_FIELD = 'divi-active-form/acf-field';

export const PostTypeField = makeComboboxField( {
	dataKey: 'postTypes',
	placeholder: 'e.g. post',
	listLabel: 'Show all post types',
	unknown: ( text ) => `“${ text }” is not a post type registered on this site.`,
} );

export const AcfFieldField = makeComboboxField( {
	dataKey: 'acfFields',
	placeholder: 'e.g. region',
	listLabel: 'Show all ACF fields',
	unknown: ( text ) => `“${ text }” is not an ACF field on this site; it will be read as plain post meta.`,
} );
