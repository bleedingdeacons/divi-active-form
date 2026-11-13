// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { RegisterForm, initRegisterForms } from '../../assets/src/frontend/form-core';

const markup = `
<form class="daf-form" method="post" action="/wp-admin/admin-post.php" data-daf-form="" data-rest="/wp-json/divi-active-form/v1/submit">
	<div class="daf-form__message" role="status" aria-live="polite" hidden></div>
	<div class="daf-form__fields">
		<div class="daf-field" data-daf-field="group">
			<select id="g" name="group" required><option value="">Choose</option><option value="12">Bath</option></select>
			<span class="daf-field__error" hidden></span>
		</div>
		<div class="daf-field" data-daf-field="email">
			<input id="e" name="email" type="email">
			<span class="daf-field__error" hidden></span>
		</div>
	</div>
	<input type="hidden" name="_daf_schema" value="token">
	<div class="daf-form__footer"><button type="submit" class="daf-form__submit">Register</button></div>
</form>`;

const respond = ( status, body ) => vi.fn().mockResolvedValue( { status, ok: status < 400, json: () => Promise.resolve( body ) } );

const submit = async ( form ) => {
	form.dispatchEvent( new Event( 'submit', { cancelable: true } ) );
	// Let the fetch and json() promises settle.
	await new Promise( ( resolve ) => setTimeout( resolve, 0 ) );
};

describe( 'RegisterForm', () => {
	let form;

	beforeEach( () => {
		document.body.innerHTML = markup;
		form = document.querySelector( 'form' );
	} );

	it( 'posts the form data to the REST endpoint', async () => {
		const fetch = respond( 201, { ok: true, message: 'Thanks', errors: {}, redirect: '' } );
		new RegisterForm( form, { fetch } );
		form.querySelector( 'select' ).value = '12';

		await submit( form );

		expect( fetch ).toHaveBeenCalledOnce();
		const [ url, init ] = fetch.mock.calls[ 0 ];
		expect( url ).toBe( '/wp-json/divi-active-form/v1/submit' );
		expect( init.method ).toBe( 'POST' );
		expect( init.body.get( 'group' ) ).toBe( '12' );
		expect( init.body.get( '_daf_schema' ) ).toBe( 'token' );
	} );

	it( 'shows the success message and hides the fields', async () => {
		new RegisterForm( form, { fetch: respond( 201, { ok: true, message: 'Thanks', errors: {}, redirect: '' } ) } );

		await submit( form );

		const message = form.querySelector( '.daf-form__message' );
		expect( message.hidden ).toBe( false );
		expect( message.textContent ).toBe( 'Thanks' );
		expect( message.classList.contains( 'daf-form__message--success' ) ).toBe( true );
		expect( form.classList.contains( 'daf-form--sent' ) ).toBe( true );
	} );

	it( 'follows the redirect when the form has one', async () => {
		const location = { assign: vi.fn() };
		new RegisterForm( form, { fetch: respond( 201, { ok: true, message: '', errors: {}, redirect: '/thanks/' } ), location } );

		await submit( form );

		expect( location.assign ).toHaveBeenCalledWith( '/thanks/' );
	} );

	it( 'marks each invalid field and clears the mark when it is edited', async () => {
		new RegisterForm( form, {
			fetch: respond( 422, { ok: false, message: 'Please correct the highlighted fields.', errors: { email: 'Email is required.' } } ),
		} );

		await submit( form );

		const wrap = form.querySelector( '[data-daf-field="email"]' );
		const input = wrap.querySelector( 'input' );
		expect( wrap.classList.contains( 'daf-field--invalid' ) ).toBe( true );
		expect( input.getAttribute( 'aria-invalid' ) ).toBe( 'true' );
		expect( wrap.querySelector( '.daf-field__error' ).textContent ).toBe( 'Email is required.' );
		expect( document.activeElement ).toBe( input );
		expect( form.querySelector( '.daf-form__message' ).classList.contains( 'daf-form__message--error' ) ).toBe( true );

		input.value = 'a@b.co';
		input.dispatchEvent( new Event( 'input', { bubbles: true } ) );

		expect( wrap.classList.contains( 'daf-field--invalid' ) ).toBe( false );
		expect( input.hasAttribute( 'aria-invalid' ) ).toBe( false );
		expect( wrap.querySelector( '.daf-field__error' ).hidden ).toBe( true );
	} );

	it( 'falls back to a normal post when the endpoint cannot be reached', async () => {
		const nativeSubmit = vi.spyOn( form, 'submit' ).mockImplementation( () => {} );
		new RegisterForm( form, { fetch: vi.fn().mockRejectedValue( new TypeError( 'Failed to fetch' ) ) } );

		await submit( form );

		expect( nativeSubmit ).toHaveBeenCalledOnce();
		expect( form.querySelector( '.daf-form__submit' ).disabled ).toBe( false );
	} );

	it( 'says when a JSON dropdown has no options', () => {
		form.querySelector( 'select' ).setAttribute( 'data-daf-empty', 'No options are available right now.' );
		new RegisterForm( form, { fetch: vi.fn() } );

		const error = form.querySelector( '[data-daf-field="group"] .daf-field__error' );
		expect( error.hidden ).toBe( false );
		expect( error.textContent ).toBe( 'No options are available right now.' );
	} );

	it( 'enhances every Register Form on the page', () => {
		document.body.innerHTML = markup + markup;

		expect( initRegisterForms( document, { fetch: vi.fn() } ) ).toHaveLength( 2 );
	} );
} );
