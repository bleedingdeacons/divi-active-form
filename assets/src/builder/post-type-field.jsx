/**
 * The Divi Active Form's Post Type setting: an editable dropdown (combobox).
 *
 * Registered with Divi's field library as `divi-active-form/post-type`. Type a post
 * type's name, or open the list (▾, or the down arrow key) to choose from every post
 * type registered on the site; the list comes from PostTypes::options() via the
 * builder data, so it includes custom post types that are not exposed to the REST API.
 * A name that is not registered is allowed (a post type may be registered later) but
 * flagged, because the server refuses submissions to a post type that does not exist.
 */
import React, { useMemo, useRef, useState } from 'react';
import { filterOptions, findOption, moveHighlight } from './combobox';

export const FIELD_NAME = 'divi-active-form/post-type';

const postTypes = () => ( window.DiviActiveFormBuilderData && window.DiviActiveFormBuilderData.postTypes ) || [];

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

export const PostTypeField = ( { value, onChange, name, readonly = false } ) => {
	const options = useMemo( postTypes, [] );
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
					placeholder="e.g. post"
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
						aria-label="Show all post types"
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
				<p style={ styles.warning }>“{ text.trim() }” is not a post type registered on this site, so submissions would be refused.</p>
			) }
		</div>
	);
};

PostTypeField.fieldName = FIELD_NAME;
