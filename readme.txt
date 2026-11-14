=== Divi Active Form ===
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 1.1.0
Build date: 2026/11/14 22:13:28
License: GPLv2 or later

Divi 5 form builder: submissions create posts, with dropdowns filled from JSON.

== Description ==

Adds a "Divi Active Form" module and its "Form Field" children to the Divi 5 Visual Builder.
Each submission creates a post of the type and status the form sets, saves each field to
post meta or ACF, and fires an after-insert action.

Form Fields can be text, email, phone, paragraph, dropdown, checkbox, or a JSON Dropdown
whose options come from a URL or a site REST path, such as the bundled sample
/wp-json/divi-active-form/v1/example-options or /wp-json/wp/v2/categories.

Each field has a placeholder, a hint shown above or below it (grey, or any colour),
a label above, to the left, below or hidden, and a width of a quarter, half, three
quarters or the full row.

Conditional Logic shows or enables each field according to other fields' values
(e.g. "size = large", "agree is checked"), built in a visual rule editor and applied
in the browser and again on the server.

Requires the Divi 5 theme.
