/**
 * Pure helpers for the editable dropdown (combobox) settings field.
 */

/**
 * The options to list: all of them when the list was opened with its button, else
 * those whose value or label contains the typed text (ignoring case).
 *
 * @param {Array<{ value: string, label: string }>} options
 * @param {string} query
 * @param {boolean} showAll
 */
export function filterOptions( options, query, showAll = false ) {
	const q = String( query || '' ).trim().toLowerCase();
	if ( showAll || ! q ) {
		return options;
	}
	return options.filter( ( o ) => o.value.toLowerCase().includes( q ) || o.label.toLowerCase().includes( q ) );
}

/** The option whose value is exactly `value`, or undefined. */
export const findOption = ( options, value ) => options.find( ( o ) => o.value === String( value || '' ).trim() );

/** The next highlighted index for an arrow key, wrapping at either end; -1 for an empty list. */
export function moveHighlight( current, delta, length ) {
	if ( length <= 0 ) {
		return -1;
	}
	if ( current < 0 ) {
		return delta > 0 ? 0 : length - 1;
	}
	return ( current + delta + length ) % length;
}
