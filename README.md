# Divi Active Form

[![CI](https://github.com/bleedingdeacons/divi-active-form/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/bleedingdeacons/divi-active-form/actions/workflows/ci.yml)
[![Semgrep](https://github.com/bleedingdeacons/divi-active-form/actions/workflows/semgrep.yml/badge.svg?branch=main)](https://github.com/bleedingdeacons/divi-active-form/actions/workflows/semgrep.yml)
![PHPStan](https://img.shields.io/badge/dynamic/yaml?url=https%3A%2F%2Fraw.githubusercontent.com%2Fbleedingdeacons%2Fdivi-active-form%2Fmain%2Fphpstan.neon.dist&query=%24.parameters.level&label=PHPStan&prefix=level%20&color=brightgreen)
![PHPCS](https://img.shields.io/badge/dynamic/xml?url=https%3A%2F%2Fraw.githubusercontent.com%2Fbleedingdeacons%2Fdivi-active-form%2Fmain%2F.phpcs.xml.dist&query=%2Fruleset%2Frule%5B1%5D%2F%40ref&label=PHPCS&color=brightgreen)
![Version](https://img.shields.io/badge/version-1.2.0-blue)
![PHP](https://img.shields.io/badge/php-8.1%2B-777bb4)
![Licence](https://img.shields.io/badge/licence-GPL--2.0--or--later-green)

A Divi 5 form builder whose submissions become WordPress posts. It adds two modules:

- **Divi Active Form**: the form. Each submission creates a post of the type and status
  you choose. The default is a `post` held as Pending Review.
- **Form Field**: one field. Add as many as you need. Each has a type: Text, Email,
  Phone, Paragraph, **Dropdown** (options typed in, or from a JSON source), Checkbox,
  or **ACF Dropdown** (options from a field's values across a post type).

Requires **Divi 5** (developed against 5.19.0). PHP 8.1+. ACF is optional: with it, fields
are saved through `update_field()`.

## Building a form

1. Add a **Divi Active Form** and set, under **Submission**:

   | Setting | Default | |
   |---|---|---|
   | Form ID | `active-form` | Passed to the after-insert hooks so they can tell forms apart |
   | Post Type | `post` | An editable dropdown: ▾ lists the site's content post types (filter `divi_active_form/post_types`), or type any registered post type's name |
   | Post Status | Pending Review | Published, Pending Review, Draft or Private |
   | Post Title | automatic | `{field_name}` placeholders, e.g. `Submission from {email}` |
   | Fire After-Insert Hook | off | Also fire `daf_after_insert_post` (see below) |

2. Add **Form Fields**. Each one's **Field Name** is the post meta (or ACF field) the value
   is saved under; leave it blank to derive it from the label.
   - **Placeholder**: greyed hint text inside Text, Email, Phone and Paragraph
     fields. A Dropdown or ACF Dropdown shows it as its first, "nothing chosen"
     option unless First Option Text is set. A Checkbox has none.
   - **Hint** (its own group): helper text shown **Below** the input (the default)
     or **Above** it, in grey unless you pick a **Hint Colour**. It is linked to the
     input with `aria-describedby`, so screen readers read it with the field.
   - **Label Position**: Top (the default), Left, Below, or Hidden. A hidden label is
     still read by screen readers, so give the field a Placeholder.
   - **Width**: Full (the default), Three Quarters, Half or Quarter of the row.
     Narrower fields sit side by side. On tablets quarters widen to halves, and on
     phones every field is full width.

### Dropdown Options

A new Form Field comes with a working example in its **Dropdown Options** group, so
switching its Type to a dropdown shows choices straight away. Replace them with your own.

| Setting | Example (the default) | |
|---|---|---|
| First Option Text | *(blank: the Placeholder, else `Choose an option`)* | The leading "nothing chosen" line |
| Options | `small\|Small` · `medium\|Medium` · `large\|Large`, one per line | **Dropdown**: `value\|label`, or just a label |
| Options From | Typed List | **Dropdown**: Typed List (the Options box) or JSON Source |
| JSON Source | `/wp-json/divi-active-form/v1/example-options` | **Dropdown from a JSON source**: a sample list (below) |
| Items Path | *(blank)* | Only when the list is nested, e.g. `data.items` |
| Value Key | *(blank: tries `value`, `id`, `ID`, `slug`)* | e.g. `id` |
| Label Key | *(blank: tries `label`, `name`, `title.rendered`, `title`)* | e.g. `name` |

#### The example endpoint

`GET /wp-json/divi-active-form/v1/example-options` is a public sample source that works
on any site. It also shows how the JSON settings read different shapes:

| URL | Returns | Settings |
|---|---|---|
| `/wp-json/divi-active-form/v1/example-options` | `[{"value": "red", "label": "Red"}, …]` | none needed |
| `…/example-options?shape=nested` | `{"data": {"items": [{"code": "red", "name": "Red"}, …]}}` | Items Path `data.items`, Value Key `code`, Label Key `name` |

Change its list with the `divi_active_form/example_options` filter (value => label).

Real data works the same way. Any WordPress REST collection can be a source, e.g.
`/wp-json/wp/v2/categories` (no keys needed), `/wp-json/wp/v2/tags`,
`/wp-json/wp/v2/pages?per_page=100` (labels from `title.rendered`), or a custom post
type's REST route.

### Conditional Logic

Each Form Field has a **Conditional Logic** group with two sets of rules, each built
in a visual editor:

- **Show When**: when the rules fail, the field is hidden.
- **Enable When**: when the rules fail, the field is shown greyed out.

Either way, a field whose rules fail is not sent, not required and not saved.

In the editor, **+ Add rule** adds a row. Pick another field in this form, then a
condition: *equals*, *does not equal*, *is empty* or *is not empty*. A checkbox has
*is checked* / *is not checked* instead. Where a comparison needs a value, a Dropdown
or ACF Dropdown field offers its own options to choose from; other fields take typed
text. **×** removes a rule.

**Edit as text** shows the same rules as text, one per line, naming fields by their
**Field Name**. The editor reads and writes exactly this format, and a line it cannot
read is kept as typed:

```text
size = large            equals (ignoring case)
size != small           does not equal
email is not empty      has a value          (also: email is empty)
agree is checked        a ticked checkbox    (also: agree is not checked)
```

**Show When Matches** and **Enable When Matches** choose whether every rule must hold
or any one of them. Leave the rules blank for "always". A field hidden or disabled by
its own rules counts as empty in other fields' rules, so rules can be chained.

The rules run in the browser as the visitor fills in the form. They run again on the
server against the submitted values, and they are part of the signed schema, so a
visitor cannot get round them. Without JavaScript every field shows, and the server
still ignores the ones whose rules fail.

## ACF Dropdowns

An **ACF Dropdown** lists the values one field holds across the published posts of
one post type. Set two things under **Dropdown Options**:

- **ACF Post Type**: ▾ lists the site's post types; or type one.
- **ACF Field**: ▾ lists the site's ACF fields by name; or type a field name. A plain
  post meta key works too.

Each distinct value is one option, used as both the value saved and the label shown,
in natural order. Posts where the field is empty are skipped. With ACF active the
field is read with `get_field()`, so formatted values come out readable: a Select's
label, a Post Object's title, a Taxonomy's term names, a multi-value field's values
joined with commas.

The list is built on the server when the page renders, and again when the form is
sent, so a submitted value must be one the field holds on some published post. The
builder previews it through the editors-only `/v1/options` route. The field suggestions
can be changed with the `divi_active_form/acf_fields` filter.

## Dropdowns from a JSON source

Set a Dropdown's **Options From** to **JSON Source** and it reads its options from JSON
instead of the Options box. (Forms saved while this was a separate JSON Dropdown type
keep working unchanged.)

**JSON Source** is a URL or a site path. A path into this site's REST API (`/wp-json/…`
or `?rest_route=…`) is answered in-process, with no loopback HTTP request, and is not
cached because its result can differ by user. Anything else is fetched with
`wp_safe_remote_get()` and cached for five minutes.

These shapes are understood:

```text
[{"value": "s", "label": "Small"}]                    // the default keys
[{"id": 12, "name": "News"}]                          // /wp/v2/categories and tags, with no keys set
[{"id": 7, "title": {"rendered": "About"}}]           // /wp/v2/pages and posts, with no keys set
["Small", "Large"]                                    // value = label
{"s": "Small", "l": "Large"}                          // value => label
{"data": {"items": [ … ]}}                            // set Items Path to data.items
```

**Value Key** and **Label Key** take dot paths such as `meta.code`. When an item lacks the
configured key, the usual names are tried (`id` / `ID` / `slug` for the value, `name` /
`title.rendered` / `title` for the label).

The options are read when the page renders, so the form works without JavaScript. They
are read again on submit, so a value the source does not offer is rejected.

A REST source is read as the visitor reading the form, so use a public route (as the
WordPress collections above are) or the dropdown will be empty for signed-out visitors.
To supply options from PHP instead, filter `divi_active_form/field_options`.

## How a submission is handled

The Divi Active Form reads its fields' settings and signs them (form ID, post type, status
and each field's name, type and options) into a hidden input with an HMAC keyed on the
site's salts. On submit:

1. The signed schema is checked. A changed or foreign schema is refused, so a visitor
   cannot change the post type, add fields or write other meta keys.
2. A filled-in honeypot gets a pretend success, and nothing is saved.
3. Each IP address may send ten submissions per ten minutes
   (filter `divi_active_form/rate_limit`).
4. Each field is sanitised and validated: required, email format, and for dropdowns,
   membership of the current options. Only fields in the schema are read, and Field
   Names can never begin with `_`.
5. The post is created and each value saved, then
   `divi_active_form/after_insert_post ($formId, $postId, $values)` fires, and
   `daf_after_insert_post ($formId, $postId)` too when the form turns it on.

With JavaScript, `assets/build/form.js` posts to `POST /wp-json/divi-active-form/v1/submit`
and shows errors beside each field. Without it, the form posts to `admin-post.php` and
returns to the page with the outcome.

There is no nonce, deliberately: visitors are not signed in and the page may be cached.
The signed schema, the honeypot and the rate limit take its place.

## Hooks

| Hook | Type | Purpose |
|---|---|---|
| `divi_active_form/after_insert_post` | action | After a submission is saved: `$formId, $postId, $values` |
| `daf_after_insert_post` | action | The same moment, with just `$formId, $postId`. Only when the form's Fire After-Insert Hook setting is on |
| `divi_active_form/field_options` | filter | A dropdown's options, after normalising: `$options, FieldDefinition $field` |
| `divi_active_form/json_cache_ttl` | filter | Seconds to cache JSON fetched over HTTP (default 300; 0 = off) |
| `divi_active_form/rate_limit` | filter | Submissions per IP per ten minutes (default 10; 0 = off) |
| `divi_active_form/loaded` | action | The plugin has hooked itself in |

Kill switch: `define( 'DIVI_ACTIVE_FORM_KILL', true );` in `wp-config.php`.

## Development

The dev site is the Local site **aa-bristol-future** (Divi 5 and ACF). Its demo form
has a three-quarter Category dropdown (from a JSON source) beside a quarter Size dropdown, and a
half-width email beside a half-width "Call me back". Its conditional fields are
"Why large?" (label hidden), shown only for Large; a phone number (label left), shown
only when "Call me back" is ticked; and a message (label below), enabled once a
category is chosen. It saves submissions as draft posts.

```bash
pwsh bin/dev.ps1 setup     # build, junction into the site, activate, seed categories + demo page
pwsh bin/dev.ps1 composer install
npm run build              # or: npm run watch
vendor/bin/pest            # PHP tests (wp-mocks), including architecture rules (tests/php/ArchTest.php)
npm test                   # Vitest: form behaviour and the builder's field parsing
pwsh bin/dev.ps1 composer phpstan
pwsh bin/dev.ps1 composer phpcs
```

Demo page: <http://aa-bristol-future.local/divi-active-form-demo/> (builder: add `?et_fb=1`).

`composer build:production` builds `build/divi-active-form-production-<version>.zip`.
CI releases on merge to `main`, as for the rest of the suite.
