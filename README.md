# SASS Compiler for TYPO3 (ws_scss)

Compiles SCSS files to CSS at runtime. Uses [SCSSPHP](https://scssphp.github.io/scssphp/) as compiler. Compiled CSS is cached and only recompiled when source files or variables change.

## Installation

```bash
composer require wapplersystems/ws-scss
```

## Requirements

- TYPO3 v14
- PHP 8.2+

## Usage via TypoScript

Include SCSS files with the standard `page.includeCSS` — the extension automatically compiles any `.scss` file:

```typoscript
page.includeCSS {
  main = EXT:my_sitepackage/Resources/Private/Scss/main.scss
}
```

### Custom output file

```typoscript
page.includeCSS {
  bootstrap = fileadmin/bootstrap/sass/bootstrap.scss
  bootstrap.outputfile = fileadmin/bootstrap/css/mybootstrap.css
}
```

### Output style

`compressed` (default) or `expanded`:

```typoscript
page.includeCSS {
  main = EXT:my_sitepackage/Resources/Private/Scss/main.scss
  main.outputStyle = expanded
}
```

### Source maps

Enables SCSS file/line references in browser DevTools:

```typoscript
page.includeCSS {
  main = EXT:my_sitepackage/Resources/Private/Scss/main.scss
  main.sourceMap = true
}
```

### Inline output

Renders CSS inline in a `<style>` tag instead of a `<link>` reference:

```typoscript
page.includeCSS {
  critical = EXT:my_sitepackage/Resources/Private/Scss/critical.scss
  critical.inlineOutput = true
}
```

## Variables

### Global variables

Available in all SCSS files:

```typoscript
plugin.tx_wsscss.variables {
  primaryColor = #007bff
  secondaryColor = #6c757d
  baseFontSize = 16px
}
```

### Per-file variables

Override or extend global variables for a specific file:

```typoscript
page.includeCSS {
  main = EXT:my_sitepackage/Resources/Private/Scss/main.scss
  main.variables {
    primaryColor = #ff6600
    containerWidth = 1200px
  }
}
```

### Using variables in SCSS

Variables defined via TypoScript are directly available as SCSS variables:

```scss
body {
  color: $primaryColor;
  font-size: $baseFontSize;
}
```

### Variable type conversion

The extension automatically converts TypoScript values to proper SCSS types:

| TypoScript value | SCSS type |
|---|---|
| `#007bff` | Color (RGB) |
| `16px` | Number with px unit |
| `1.5rem` | Number with rem unit |
| `"Arial, sans-serif"` | String |
| Other values | Generic value |

## SCSS imports

Standard SCSS imports work as expected. Additionally, the extension supports TYPO3's `EXT:` paths:

```scss
@import "variables";
@import "mixins";
@import "EXT:bootstrap/Resources/Public/Scss/bootstrap";
```

File resolution order: `filename.scss`, `_filename.scss`, `filename.css`.

## Usage via Fluid ViewHelper

The extension registers a ViewHelper for compiling SCSS in Fluid templates.

### File-based

```html
<wsscss:asset.scss
    identifier="main"
    href="EXT:my_sitepackage/Resources/Private/Scss/main.scss"
/>
```

### With variables

```html
<wsscss:asset.scss
    identifier="styled"
    href="EXT:my_sitepackage/Resources/Private/Scss/styles.scss"
    scssVariables="{primaryColor: '#0066cc', borderRadius: '4px'}"
/>
```

### Inline SCSS

```html
<wsscss:asset.scss identifier="inline">
    $color: red;
    body { background: $color; }
</wsscss:asset.scss>
```

### ViewHelper arguments

| Argument | Type | Required | Description |
|---|---|---|---|
| `identifier` | string | yes | Unique ID for asset deduplication |
| `href` | string | no | Path to SCSS file (`EXT:` or `fileadmin/`) |
| `scssVariables` | array | no | Variables to pass to the compiler |
| `outputfile` | string | no | Custom path for compiled CSS output |
| `forcedOutputLocation` | string | no | Force `inline` or `file` output |
| `priority` | bool | no | Load before other stylesheets |
| `disabled` | bool | no | Add `disabled` attribute |

## Events

### AfterVariableDefinitionEvent

Modify variables before compilation:

```php
use WapplerSystems\WsScss\Event\AfterVariableDefinitionEvent;

#[AsEventListener]
final class AddDynamicVariables
{
    public function __invoke(AfterVariableDefinitionEvent $event): void
    {
        $variables = $event->getVariables();
        $variables['dynamicColor'] = '#ff0000';
        $event->setVariables($variables);
    }
}
```

### AfterScssCompilationEvent

Post-process compiled CSS:

```php
use WapplerSystems\WsScss\Event\AfterScssCompilationEvent;

#[AsEventListener]
final class PostProcessCss
{
    public function __invoke(AfterScssCompilationEvent $event): void
    {
        $css = $event->getCssCode();
        $css .= "\n/* Compiled at " . date('c') . " */";
        $event->setCssCode($css);
    }
}
```

## Caching

Compiled CSS is cached in `typo3temp/assets/css/`. Bookkeeping for that cache lives in a separate
TYPO3 cache identifier, `ws_scss`, registered with `NonFlushableFileBackend` -- a generic
`cache:flush` (CLI, deploy hook, Backend "Clear all caches") deliberately does **not** clear it, in
any environment. This is intentional: it used to get wiped by every unrelated flush, which forced a
full recompile of every configured SCSS entry on the very next request -- for every visitor hitting
the site concurrently right after a deploy/flush, since nothing about the .scss sources had actually
changed.

To invalidate it on purpose (i.e. after an actual `.scss` source change), use one of:

```bash
vendor/bin/typo3 wsscss:flush
```

- the Backend's "Flush SCSS cache" entry in the "Clear cache" toolbar menu, or
- wire the CLI command into your deploy pipeline, e.g. a composer `post-autoload-dump`/`post-update-cmd`
  script, so it runs automatically on every `composer install`/`update`.

### `trustCacheWithoutRevalidation`

By default (`true`), a cache hit is trusted **without** re-validating the `.scss` source -- no
`file_get_contents()`/`sha1()` walk of the whole `@import` tree, no scssphp `Compiler`/importer setup,
just a cache-existence check. This is what removes the per-request revalidation cost entirely (not
just the recompile-after-flush cost above).

**Trade-off:** with this on, editing a `.scss` source -- including a transitively `@import`-ed
partial, or (for `wapplersystems/ws-components`) a component `.scss` file that isn't the top-level
synthesized bundle string -- no longer self-heals on the next request. An explicit `wsscss:flush`
is required after **every** real `.scss` change, in every environment (this is **not** gated by
Production vs. Development context) -- otherwise the previously compiled CSS keeps being served
indefinitely, silently, without error or warning.

To restore the old always-revalidate-by-content-hash behaviour (safe for local development without
remembering to flush, at the cost of the full `@import`-tree walk on every call, hit or miss), disable
it per project -- either in the Backend under **Admin Tools > Settings > Extension Configuration >
ws_scss**, or via config file:

```php
// config/system/settings.php
$GLOBALS['TYPO3_CONF_VARS']['EXTENSIONS']['ws_scss']['trustCacheWithoutRevalidation'] = '0';
```

## Complete example

```typoscript
plugin.tx_wsscss.variables {
  brandColor = #0066cc
  fontFamily = "Open Sans, sans-serif"
  baseFontSize = 16px
}

page.includeCSS {
  bootstrap = EXT:my_sitepackage/Resources/Private/Scss/bootstrap.scss
  bootstrap.outputStyle = compressed

  theme = EXT:my_sitepackage/Resources/Private/Scss/theme.scss
  theme.outputStyle = compressed
  theme.variables {
    headerHeight = 80px
    sidebarWidth = 300px
  }
}
```

## License

GPL-2.0-or-later