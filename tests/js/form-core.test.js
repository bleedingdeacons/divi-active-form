// @vitest-environment jsdom
import { describe, it, expect, beforeEach, vi } from 'vitest';
import { RegisterForm, initRegisterForms, passes, fieldStates } from '../../assets/src/frontend/form-core';

const markup = `
<form class="daf-form" method="post" action="/wp-admin/admin-post.php" data-daf-form="" data-rest="/wp-json/divi-active-form/v1/submit">
	<div class="daf-form__message" role="status" aria-live="polite" hidden></div>
	<div class="daf-form__fields">
		<div class="daf-field" data-daf-field="category">
			<select id="g" name="category" required><option value="">Choose</option><option value="12">News</option></select>
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
		expect( init.body.get( 'category' ) ).toBe( '12' );
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

		const error = form.querySelector( '[data-daf-field="category"] .daf-field__error' );
		expect( error.hidden ).toBe( false );
		expect( error.textContent ).toBe( 'No options are available right now.' );
	} );

	it( 'enhances every Register Form on the page', () => {
		document.body.innerHTML = markup + markup;

		expect( initRegisterForms( document, { fetch: vi.fn() } ) ).toHaveLength( 2 );
	} );
} );

describe( 'Conditional Logic', () => {
	const conditional = `
<form data-daf-form="" data-rest="/submit">
	<div class="daf-form__fields">
		<div class="daf-field-module"><div class="daf-field" data-daf-field="size">
			<select name="size"><option value="">-</option><option value="small">Small</option><option value="large">Large</option></select>
		</div></div>
		<div class="daf-field-module"><div class="daf-field" data-daf-field="reason"
			data-daf-visible='{"any":false,"rules":[{"field":"size","op":"=","value":"LARGE"}]}'>
			<input name="reason" required>
		</div></div>
		<div class="daf-field-module"><div class="daf-field" data-daf-field="callback">
			<input type="checkbox" name="callback" value="1">
		</div></div>
		<div class="daf-field-module"><div class="daf-field" data-daf-field="note"
			data-daf-enabled='{"any":false,"rules":[{"field":"callback","op":"filled","value":""}]}'>
			<textarea name="note"></textarea>
		</div></div>
	</div>
</form>`;

	const module = ( form, name ) => form.querySelector( `[data-daf-field="${ name }"]` ).closest( '.daf-field-module' );

	it( 'hides and disables a field until its rules hold, then shows it', () => {
		document.body.innerHTML = conditional;
		const form = document.querySelector( 'form' );
		new RegisterForm( form, { fetch: vi.fn() } );
		const reason = form.querySelector( '[name="reason"]' );

		expect( module( form, 'reason' ).hidden ).toBe( true );
		expect( reason.disabled ).toBe( true );

		const size = form.querySelector( '[name="size"]' );
		size.value = 'large';
		size.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		expect( module( form, 'reason' ).hidden ).toBe( false );
		expect( reason.disabled ).toBe( false );
	} );

	it( 'greys out a field until it is enabled, and leaves it out of the submission', () => {
		document.body.innerHTML = conditional;
		const form = document.querySelector( 'form' );
		new RegisterForm( form, { fetch: vi.fn() } );
		const note = form.querySelector( '[name="note"]' );
		const wrap = form.querySelector( '[data-daf-field="note"]' );

		expect( module( form, 'note' ).hidden ).toBe( false );
		expect( wrap.classList.contains( 'daf-field--disabled' ) ).toBe( true );
		expect( new FormData( form ).has( 'note' ) ).toBe( false );

		const callback = form.querySelector( '[name="callback"]' );
		callback.checked = true;
		callback.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		expect( note.disabled ).toBe( false );
		expect( wrap.classList.contains( 'daf-field--disabled' ) ).toBe( false );
	} );

	it( 'evaluates rules as the server does', () => {
		const rule = ( field, op, value = '' ) => ( { field, op, value } );

		expect( passes( null, {} ) ).toBe( true );
		expect( passes( { rules: [ rule( 'size', '=', 'Large' ) ] }, { size: ' large ' } ) ).toBe( true );
		expect( passes( { rules: [ rule( 'size', '!=', 'large' ) ] }, {} ) ).toBe( true );
		expect( passes( { rules: [ rule( 'email', 'empty' ) ] }, {} ) ).toBe( true );
		expect( passes( { any: true, rules: [ rule( 'a', 'filled' ), rule( 'b', 'filled' ) ] }, { b: 'x' } ) ).toBe( true );
		expect( passes( { any: false, rules: [ rule( 'a', 'filled' ), rule( 'b', 'filled' ) ] }, { b: 'x' } ) ).toBe( false );
	} );

	it( 'treats a hidden field as empty in other fields\' rules', () => {
		const fields = [
			{ name: 'callback', visible: null, enabled: null },
			{ name: 'phone', visible: { rules: [ { field: 'callback', op: 'filled', value: '' } ] }, enabled: null },
			{ name: 'note', visible: null, enabled: { rules: [ { field: 'phone', op: 'filled', value: '' } ] } },
		];

		expect( fieldStates( fields, { callback: '', phone: '0123', note: '' } ).note.enabled ).toBe( false );
		expect( fieldStates( fields, { callback: '1', phone: '0123', note: '' } ).note.enabled ).toBe( true );
	} );
} );
