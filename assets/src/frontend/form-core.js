/**
 * Register Form behaviour: sends the form to the REST endpoint and shows the outcome
 * in place. Without this script the form still works, posting to admin-post.php.
 *
 * Kept free of globals other than those passed in, so it can be tested under jsdom.
 */

const FIELD = '[data-daf-field]';

/**
 * Whether a field's Conditional Logic holds for these values. Mirrors
 * Conditions::passes() in PHP: trimmed text, compared ignoring case; a missing field
 * counts as empty; no rules always pass.
 *
 * @param {{ any?: boolean, rules?: Array<{ field: string, op: string, value: string }> } | null} conditions
 * @param {Record<string, string>} values
 */
export function passes( conditions, values ) {
	const rules = ( conditions && conditions.rules ) || [];
	if ( ! rules.length ) {
		return true;
	}
	const holds = ( rule ) => {
		const actual = String( values[ rule.field ] ?? '' ).trim();
		const expected = String( rule.value ?? '' ).trim().toLowerCase();
		switch ( rule.op ) {
			case 'empty':
				return actual === '';
			case 'filled':
				return actual !== '';
			case '!=':
				return actual.toLowerCase() !== expected;
			default:
				return actual.toLowerCase() === expected;
		}
	};
	return conditions.any ? rules.some( holds ) : rules.every( holds );
}

/**
 * Each field's state, given every field's raw value. A field's rules see a hidden or
 * disabled field as empty, so this repeats until nothing changes, as
 * Validator::activeFields() does on the server, with the same pass limit.
 *
 * @param {Array<{ name: string, visible: object | null, enabled: object | null }>} fields
 * @param {Record<string, string>} values
 * @returns {Record<string, { visible: boolean, enabled: boolean }>}
 */
export function fieldStates( fields, values ) {
	let states = {};
	fields.forEach( ( f ) => ( states[ f.name ] = { visible: true, enabled: true } ) );

	for ( let pass = 0; pass <= fields.length; pass++ ) {
		const seen = {};
		Object.keys( values ).forEach( ( name ) => {
			const st = states[ name ];
			seen[ name ] = ! st || ( st.visible && st.enabled ) ? values[ name ] : '';
		} );

		const next = {};
		fields.forEach( ( f ) => {
			next[ f.name ] = { visible: passes( f.visible, seen ), enabled: passes( f.enabled, seen ) };
		} );

		const same = fields.every(
			( f ) => next[ f.name ].visible === states[ f.name ].visible && next[ f.name ].enabled === states[ f.name ].enabled
		);
		states = next;
		if ( same ) {
			break;
		}
	}
	return states;
}

const readJson = ( el, attr ) => {
	const raw = el.getAttribute( attr );
	if ( ! raw ) {
		return null;
	}
	try {
		return JSON.parse( raw );
	} catch ( e ) {
		return null;
	}
};

export class RegisterForm {
	/**
	 * @param {HTMLFormElement} form
	 * @param {{ fetch?: typeof fetch, location?: { assign(url: string): void } }} [env]
	 */
	constructor( form, env = {} ) {
		this.form = form;
		this.fetch = env.fetch || window.fetch.bind( window );
		this.location = env.location || window.location;
		this.message = form.querySelector( '.daf-form__message' );
		this.button = form.querySelector( '.daf-form__submit' );
		this.busy = false;
		this.onSubmit = this.onSubmit.bind( this );
		form.addEventListener( 'submit', this.onSubmit );
		form.addEventListener( 'input', ( event ) => {
			this.clearFieldError( event.target );
			this.applyConditions();
		} );
		form.addEventListener( 'change', ( event ) => {
			this.clearFieldError( event.target );
			this.applyConditions();
		} );
		this.fields = Array.from( form.querySelectorAll( FIELD ) ).map( ( wrap ) => ( {
			wrap,
			name: wrap.getAttribute( 'data-daf-field' ),
			visible: readJson( wrap, 'data-daf-visible' ),
			enabled: readJson( wrap, 'data-daf-enabled' ),
		} ) );
		this.markEmptySelects();
		this.applyConditions();
	}

	/** Every field's current value, as the server will read it. */
	values() {
		const values = {};
		this.fields.forEach( ( { wrap, name } ) => {
			const control = wrap.querySelector( '[name]' );
			if ( ! control ) {
				values[ name ] = '';
			} else if ( control.type === 'checkbox' ) {
				values[ name ] = control.checked ? '1' : '';
			} else {
				values[ name ] = String( control.value || '' ).trim();
			}
		} );
		return values;
	}

	/**
	 * Hides and disables fields whose Conditional Logic fails. A hidden field is disabled
	 * too, so it is neither sent nor held to `required`.
	 */
	applyConditions() {
		if ( ! this.fields.some( ( f ) => f.visible || f.enabled ) ) {
			return;
		}
		const states = fieldStates( this.fields, this.values() );
		this.fields.forEach( ( { wrap, name } ) => {
			const { visible, enabled } = states[ name ];
			const container = wrap.closest( '.daf-field-module' ) || wrap;
			container.hidden = ! visible;
			wrap.classList.toggle( 'daf-field--disabled', visible && ! enabled );
			wrap.querySelectorAll( 'input, select, textarea' ).forEach( ( control ) => {
				control.disabled = ! ( visible && enabled );
			} );
		} );
	}

	destroy() {
		this.form.removeEventListener( 'submit', this.onSubmit );
	}

	/** A JSON dropdown whose source returned nothing says so rather than offering nothing. */
	markEmptySelects() {
		this.form.querySelectorAll( 'select[data-daf-empty]' ).forEach( ( select ) => {
			const wrap = select.closest( FIELD );
			const error = wrap && wrap.querySelector( '.daf-field__error' );
			if ( error ) {
				error.textContent = select.getAttribute( 'data-daf-empty' );
				error.hidden = false;
			}
		} );
	}

	async onSubmit( event ) {
		const endpoint = this.form.getAttribute( 'data-rest' );
		if ( ! endpoint || typeof FormData === 'undefined' ) {
			return; // Let the browser post the form to admin-post.php.
		}
		event.preventDefault();
		if ( this.busy ) {
			return;
		}

		this.setBusy( true );
		this.clearErrors();

		let response;
		let body;
		try {
			response = await this.fetch( endpoint, {
				method: 'POST',
				body: new FormData( this.form ),
				credentials: 'same-origin',
				headers: { Accept: 'application/json' },
			} );
			body = await response.json();
		} catch ( error ) {
			// The endpoint is unreachable or did not answer in JSON: fall back to a
			// normal post, which reports the outcome on the page it returns to.
			this.setBusy( false );
			this.form.submit();
			return;
		}

		this.setBusy( false );

		if ( body && body.ok ) {
			if ( body.redirect ) {
				this.location.assign( body.redirect );
				return;
			}
			this.form.reset();
			this.applyConditions();
			this.form.classList.add( 'daf-form--sent' );
			this.showMessage( body.message, false );
			return;
		}

		const errors = ( body && body.errors ) || {};
		const names = Object.keys( errors );
		names.forEach( ( name ) => this.showFieldError( name, errors[ name ] ) );
		this.showMessage( ( body && body.message ) || 'Something went wrong. Please try again.', true );

		const first = names.length ? this.form.querySelector( `${ FIELD }[data-daf-field="${ cssEscape( names[ 0 ] ) }"] [name]` ) : null;
		if ( first && typeof first.focus === 'function' ) {
			first.focus();
		}
	}

	setBusy( busy ) {
		this.busy = busy;
		this.form.classList.toggle( 'daf-form--busy', busy );
		if ( this.button ) {
			this.button.disabled = busy;
		}
	}

	showMessage( text, isError ) {
		if ( ! this.message ) {
			return;
		}
		this.message.textContent = text || '';
		this.message.hidden = ! text;
		this.message.classList.toggle( 'daf-form__message--error', !! isError );
		this.message.classList.toggle( 'daf-form__message--success', ! isError );
	}

	showFieldError( name, text ) {
		const wrap = this.form.querySelector( `${ FIELD }[data-daf-field="${ cssEscape( name ) }"]` );
		if ( ! wrap ) {
			return;
		}
		const error = wrap.querySelector( '.daf-field__error' );
		const control = wrap.querySelector( '[name]' );
		wrap.classList.add( 'daf-field--invalid' );
		if ( control ) {
			control.setAttribute( 'aria-invalid', 'true' );
		}
		if ( error ) {
			error.textContent = text;
			error.hidden = false;
		}
	}

	clearFieldError( control ) {
		const wrap = control && control.closest ? control.closest( FIELD ) : null;
		if ( ! wrap || ! wrap.classList.contains( 'daf-field--invalid' ) ) {
			return;
		}
		wrap.classList.remove( 'daf-field--invalid' );
		control.removeAttribute( 'aria-invalid' );
		const error = wrap.querySelector( '.daf-field__error' );
		if ( error ) {
			error.textContent = '';
			error.hidden = true;
		}
	}

	clearErrors() {
		this.form.querySelectorAll( `${ FIELD }.daf-field--invalid [name]` ).forEach( ( control ) => this.clearFieldError( control ) );
		this.showMessage( '', false );
	}
}

function cssEscape( value ) {
	return typeof CSS !== 'undefined' && CSS.escape ? CSS.escape( value ) : String( value ).replace( /["\\]/g, '\\$&' );
}

export function initRegisterForms( root = document, env = {} ) {
	return Array.from( root.querySelectorAll( 'form[data-daf-form]' ) ).map( ( form ) => new RegisterForm( form, env ) );
}
