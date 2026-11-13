/**
 * Register Form behaviour: sends the form to the REST endpoint and shows the outcome
 * in place. Without this script the form still works, posting to admin-post.php.
 *
 * Kept free of globals other than those passed in, so it can be tested under jsdom.
 */

const FIELD = '[data-daf-field]';

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
		form.addEventListener( 'input', ( event ) => this.clearFieldError( event.target ) );
		form.addEventListener( 'change', ( event ) => this.clearFieldError( event.target ) );
		this.markEmptySelects();
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
