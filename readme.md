# Backdrop Languages
Backdrop Lang includes a Component class for handling the backdrop textdomain when building a theme. This should be used alongside any packages that uses text strings. It also includes locale-specific helper functions.

## Usage

### 1. Require the package

```json
"require": {
	"php": ">=7.4",
	"backdrop-dev/framework": "^2.0",
	"backdrop-dev/languages": "^1.0"
}
```

### 2. Register the provider

In the theme's `app/framework.php`, alongside the other Backdrop providers:

```php
$theme->provider( Backdrop\Languages\Provider::class );
```

### 3. Set the text domain and domain path in `style.css`

```css
Text Domain: my-theme
Domain Path: /public/lang
```

`Domain Path` is the folder (relative to the theme) that holds the theme's
translation files. If it is not set, the package falls back to the
conventional `languages` folder in the theme.

The theme itself must load its own textdomain, for example in
`app/functions-setup.php`:

```php
load_theme_textdomain( 'my-theme', get_parent_theme_file_path( 'public/lang' ) );
```

### How the single `.mo` file works

The framework's own strings use the `backdrop` text domain, while the theme
uses its own domain (for example, `my-theme`). Instead of shipping a
separate `backdrop` translation file, this package aliases the two domains
together: when the `backdrop` domain is loaded, the component points it at
the theme's already-loaded translations (`$l10n['backdrop']` is set to
`$l10n['my-theme']`). Framework strings are therefore translated from the
theme's single `.mo` file, for example
`public/lang/my-theme-en_US.mo`.

The alias is applied when the `backdrop` domain loads, and re-synced late
in `after_setup_theme` and on `init`, so it also works for themes that load
their own textdomain after the framework.

### Translation template (`.pot`)

The theme's `.pot` file must contain **both** the theme's strings and the
framework's `backdrop` strings, or the framework strings will stay in
English even with the aliasing in place. Generate it with WP-CLI from the
theme root (after `composer install`, so `vendor/backdrop-dev` exists):

```bash
composer install --no-dev
wp i18n make-pot . public/lang/my-theme.pot --domain=my-theme --include="app,public/views,config,functions.php,index.php,vendor/backdrop-dev" --exclude="node_modules,public/assets,public/lang"
```

`--domain` only sets the file header; `wp i18n make-pot` extracts strings
from every domain it scans, so the framework strings land in the same file.
Translators then work from that `.pot` to produce
`my-theme-{locale}.po` / `.mo` files in the Domain Path folder.

### Locale-specific function files

A file named after the site's locale (lowercase, hyphenated) in the Domain
Path folder is loaded automatically, child theme first, then parent theme.
For example, with a locale of `en_US`, `public/lang/en-us.php` is loaded on
`after_setup_theme`. Use it for locale-specific functions or overrides.

### Helper functions

The package also provides locale helpers in the `Backdrop\Languages`
namespace (loaded automatically via Composer's `files` autoload):

- `language( $locale = '' )` — the language code, for example `en` for `en_US`.
- `region( $locale = '' )` — the region code, for example `us` for `en_US`,
  or an empty string when the locale has no region (for example, `en`).
- `hierarchy()` — a hierarchy based on locale, language, region, and text
  direction, for example `[ 'en-us', 'us', 'en', 'ltr' ]` for `en_US`.

## Tests

The package has a small test script for the WP-free helper logic
(`language()`, `region()`, `hierarchy()`), using stubs for the WordPress
functions they depend on. Run it with:

```bash
php tests/run.php
```

It exits non-zero if any case fails.

# Copyright and License
This project is licensed under the GNU GPL, version 2 or later.

2019–2023 © Benjamin Lu
