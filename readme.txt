=== Divi Active Form ===
Requires at least: 6.5
Tested up to: 6.8
Requires PHP: 8.1
Stable tag: 0.2.0
Build date: 2026/11/14 17:58:02
License: GPLv2 or later

Divi 5 form builder: submissions create posts, with dropdowns filled from JSON.

== Description ==

Adds a "Register Form" module and its "Form Field" children to the Divi 5 Visual Builder.
Each submission creates a post of the type and status the form sets, saves each field to
post meta or ACF, and fires an after-insert action.

Form Fields can be text, email, phone, paragraph, dropdown, checkbox, or a JSON Dropdown
whose options come from a URL or a site REST path, such as
/wp-json/wp/v2/categories.

Conditional Logic shows or enables each field according to other fields' values
(e.g. "size = large", "agree is checked"), in the browser and again on the server.

Requires the Divi 5 theme.
