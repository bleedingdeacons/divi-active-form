# Divi Active Form

[![CI](https://github.com/bleedingdeacons/divi-active-form/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/bleedingdeacons/divi-active-form/actions/workflows/ci.yml)
[![Semgrep](https://github.com/bleedingdeacons/divi-active-form/actions/workflows/semgrep.yml/badge.svg?branch=main)](https://github.com/bleedingdeacons/divi-active-form/actions/workflows/semgrep.yml)
![PHPStan](https://img.shields.io/badge/dynamic/yaml?url=https%3A%2F%2Fraw.githubusercontent.com%2Fbleedingdeacons%2Fdivi-active-form%2Fmain%2Fphpstan.neon.dist&query=%24.parameters.level&label=PHPStan&prefix=level%20&color=brightgreen)
![PHPCS](https://img.shields.io/badge/dynamic/xml?url=https%3A%2F%2Fraw.githubusercontent.com%2Fbleedingdeacons%2Fdivi-active-form%2Fmain%2F.phpcs.xml.dist&query=%2Fruleset%2Frule%5B1%5D%2F%40ref&label=PHPCS&color=brightgreen)
![Version](https://img.shields.io/badge/version-0.1.0-blue)
![PHP](https://img.shields.io/badge/php-8.1%2B-777bb4)
![Licence](https://img.shields.io/badge/licence-GPL--2.0--or--later-green)

A Divi 5 form builder whose submissions become WordPress posts. It adds two modules:

- **Register Form**: the form. Each submission creates a post of the type and status
  you choose. The default is a `post` held as Pending Review.
- **Form Field**: one field. Add as many as you need. Each has a type: Text, Email,
  Phone, Paragraph, Dropdown, Checkbox or **JSON Dropdown**, whose options come from a
  JSON source.

Requires **Divi 5** (developed against 5.19.0). PHP 8.1+. ACF is optional: with it, fields
are saved through `update_field()`.

## Building a form

1. Add a **Register Form** and set, under **Submission**:

   | Setting | Default | |
   |---|---|---|
   | Form ID | `active-form` | Passed to the after-insert hooks so they can tell forms apart |
   | Post Type | `post` | Any registered post type |
   | Post Status | Pending Review | Published, Pending Review, Draft or Private |
   | Post Title | automatic | `{field_name}` placeholders, e.g. `Submission from {email}` |
   | Fire Divi Form Builder Hook | off | Also fire `df_after_insert_post` (see below) |

2. Add **Form Fields**. Each one's **Field Name** is the post meta (or ACF field) the value
   is saved under; leave it blank to derive it from the label. Turn **Full Width** off to
   put two fields side by side.

## JSON Dropdowns

**JSON Source** is a URL or a site path. A path into this site's REST API (`/wp-json/…`
or `?rest_route=…`) is answered in-process, with no loopback HTTP request, and is not
cached because its result can differ by user. Anything else is fetched with
`wp_safe_remote_get()` and cached for five minutes.

These shapes are understood:

```text
[{"value": 12, "label": "Bath Tuesday"}]              // the default keys
[{"id": 12, "title": {"rendered": "Bath Tuesday"}}]   // WordPress REST collections, with no keys set
["Bath", "Bristol"]                                    // value = label
{"12": "Bath Tuesday", "15": "Bristol Friday"}        // value => label
{"data": {"items": [ … ]}}                             // set Items Path to data.items
```

**Value Key** and **Label Key** take dot paths such as `meta.code`. When an item lacks the
configured key, the usual names are tried (`id` / `ID` / `slug` for the value, `name` /
`title.rendered` / `title` for the label).

The options are read when the page renders, so the form works without JavaScript. They
are read again on submit, so a value the source does not offer is rejected.

### The groups endpoint

`GET /wp-json/divi-active-form/v1/groups` lists published 12 Step Meeting List groups
(`tsml_group` posts) as `[{"value": "<post ID>", "label": "<title>"}]`, sorted by title:
a ready-made source for a "which group?" dropdown. It is public, as TSML's own listings
are, and returns an empty list when 12 Step Meeting List is not active. Filter it with
`divi_active_form/groups`.

## How a submission is handled

The Register Form reads its fields' settings and signs them (form ID, post type, status
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
   `df_after_insert_post ($formId, $postId)` too when the form turns it on.

With JavaScript, `assets/build/form.js` posts to `POST /wp-json/divi-active-form/v1/submit`
and shows errors beside each field. Without it, the form posts to `admin-post.php` and
returns to the page with the outcome.

There is no nonce, deliberately: visitors are not signed in and the page may be cached.
The signed schema, the honeypot and the rate limit take its place.

## Hooks

| Hook | Type | Purpose |
|---|---|---|
| `divi_active_form/after_insert_post` | action | After a submission is saved: `$formId, $postId, $values` |
| `df_after_insert_post` | action | The same moment, with Divi Form Builder's arguments, for code written against that plugin. Only when the form's Fire Divi Form Builder Hook is on |
| `divi_active_form/field_options` | filter | A dropdown's options, after normalising: `$options, FieldDefinition $field` |
| `divi_active_form/json_cache_ttl` | filter | Seconds to cache JSON fetched over HTTP (default 300; 0 = off) |
| `divi_active_form/groups` | filter | The groups endpoint's list |
| `divi_active_form/rate_limit` | filter | Submissions per IP per ten minutes (default 10; 0 = off) |
| `divi_active_form/loaded` | action | The plugin has hooked itself in |

Kill switch: `define( 'DIVI_ACTIVE_FORM_KILL', true );` in `wp-config.php`.

## Development

The dev site is the Local site **aa-bristol-future** (Divi 5, ACF and 12 Step Meeting
List). Its demo form saves submissions as draft posts.

```bash
pwsh bin/dev.ps1 setup     # build, junction into the site, activate, seed groups + demo page
pwsh bin/dev.ps1 composer install
npm run build              # or: npm run watch
vendor/bin/pest            # PHP tests (wp-mocks)
npm test                   # Vitest: form behaviour and the builder's field parsing
pwsh bin/dev.ps1 composer phpstan
pwsh bin/dev.ps1 composer phpcs
```

Demo page: <http://aa-bristol-future.local/divi-active-form-demo/> (builder: add `?et_fb=1`).

`composer build:production` builds `build/divi-active-form-production-<version>.zip`.
CI releases on merge to `main`, as for the rest of the suite.
