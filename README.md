# Canvas Utilities

Canvas Utilities adds a **Design system** page extension to Drupal Canvas 1.10.
Site builders can manage theme-facing design foundations without leaving the
Canvas shell or editing source files.

## Feature modules

Enable only the features a site needs:

- `canvas_utilities_style_guide` discovers versioned theme definitions and
  supports visually authored definitions, private drafts, conflict detection,
  live theme-page preview, and explicit publishing.
- `canvas_utilities_palette` manages approved color palettes and emits CSS
  custom properties.
- `canvas_utilities_fonts` manages self-hosted WOFF2, WOFF, TTF, and OTF faces
  plus permission-gated HTTPS remote stylesheets.
- `canvas_utilities_icons` imports individual SVG files, SVG-only ZIP archives,
  and SVG sprites. It exposes sanitized libraries through Drupal Core's
  experimental Icon API.
- `canvas_utilities_custom_css` provides a restricted, parser-backed CSS editor
  for trusted administrators.

All feature modules depend only on the parent module. Their capability plugins
make the matching workspace appear in Canvas when the current account has the
required permission.

## Theme Style Guide definitions

Place `<theme>.canvas_utilities.yml` in a theme root. Schema version 1 uses
explicit contexts, groups, controls, and CSS custom-property targets:

```yaml
schema_version: 1
style_guides:
  foundations:
    label: Foundations
    description: Approved theme-level controls.
    preview:
      path: /
    contexts:
      default:
        label: Default
        selector: ':root'
      dark:
        label: Dark
        selector: '.theme-dark'
    groups:
      brand:
        label: Brand
        controls:
          accent:
            label: Accent
            type: color
            default:
              default: '#3057d5'
              dark: '#8da4ff'
            targets:
              default:
                property: '--brand-accent'
              dark:
                property: '--brand-accent'
```

Supported value types are `color`, `dimension`, `number`, `select`,
`boolean_map`, `text_token`, `palette_color`, and `font_family`. The last two
store references to enabled provider entities and are resolved when CSS is
compiled. Selectors are intentionally limited to `:root` and a single class;
targets must be CSS custom properties.

Definitions inherit through the active theme's base-theme chain. A visual
definition that collides with a discovered definition must explicitly declare
the definition it replaces.

## Canvas code-component prop controls

The palette and icon submodules each provide a Canvas-only prop control. These
are not Drupal Field API fields and are not available in Field UI. They are
matched only while Canvas builds a code-component prop form.

Use these JSON Schema references in a code component's `props` configuration:

```yaml
props:
  accentColor:
    title: Accent color
    type: string
    $ref: 'json-schema-definitions://canvas_utilities_palette.module/color'
  icon:
    title: Icon
    type: string
    $ref: 'json-schema-definitions://canvas_utilities_icons.module/icon'
```

`accentColor` receives either a palette CSS custom-property reference such as
`var(--brand-primary)` or, for accounts with `use unrestricted canvas utilities
colors`, a validated custom CSS color. The control offers grouped palette
swatches, a native custom color control, and a text value for CSS formats the
native browser control cannot express.

`icon` receives the root-relative URL of a sanitized, content-addressed SVG
managed by an enabled icon library. A React code component can use it directly,
for example `<img src={icon} alt="" />`, or as a CSS mask URL. The picker offers
preview, search, and library filtering; its stored text input is read-only.

Both values are regular Canvas component inputs, so edits participate in the
normal Canvas undo and pending-changes review workflow.

Canvas 1.10's code-component Props editor has a hard-coded type menu and cannot
yet create either custom `$ref` through that menu. Existing props using the
references are preserved and get the correct controls in the component
Settings panel. Until Canvas exposes an extension point for that menu, add the
references through component configuration or the code-component API.

## Icon source provider API

Add import formats with a plugin in `Plugin/IconSource` using the
`CanvasUtilitiesIconSource` attribute. Extend `IconSourceBase` and implement:

```php
public function extract(array $uploads): array;
```

Return normalized candidates containing `id`, `label`, `group`, and raw `svg`.
The central importer owns size-independent sanitization, internal-ID
namespacing, managed-file storage, rollback, and config entity creation. A
provider must enforce format-specific count, upload-size, and expansion limits
before returning candidates. It must never write untrusted source markup to a
public URI.

The Core Icon API bridge lives only in
`Plugin/IconExtractor/ConfigLibraryExtractor.php` because that Drupal API is
experimental. Stored libraries do not depend on that adapter and remain usable
if the compatibility class must change for a future Core minor.

## Security and permissions

The parent `access canvas utilities` permission only opens the application.
Every write route has its own feature permission and Drupal CSRF header check.
Custom CSS and remote font assets use restricted permissions. Custom CSS source
is not readable without its administration permission.

SVG imports reject document types, `foreignObject`, scripts and event handlers,
external references, oversized content, unsafe ZIP paths, symbolic links, high
compression ratios, and excessive entries. Sanitized SVG is stored as a
permanent managed file; original uploads are never served.

Custom CSS is parsed with `sabberworm/php-css-parser` and separately rejects
`@import`, markup, script URLs, remote/data URLs, unbalanced blocks, and sources
larger than 100 KB.

## Publishing and Canvas review

Style Guide changes use private per-user drafts with a live-state base hash.
Publishing returns a conflict instead of silently overwriting a newer live
configuration. Other feature APIs publish their config entities directly.

Canvas 1.10 does not expose a supported extension contract that satisfies the
requirements for adding arbitrary configuration to its pending-changes set.
`PendingChangesAdapterInterface` therefore ships with the explicit `direct`
adapter. The bootstrap API reports this mode and the Canvas workspace explains
it to users. A future adapter can replace the service without changing feature
APIs.

## Configuration and files

Definitions, values, palettes, font metadata, icon metadata, and custom CSS are
configuration entities and participate in Drupal configuration export/import.
Uploaded font and icon binaries are managed public files and are not contained
in a normal configuration export. Deployment tooling must copy those public
files with the matching configuration. Deleting a local font or icon library
deletes its owned managed files.

## Development and verification

From the project root:

```bash
ddev composer install
ddev drush pm:enable --yes canvas_utilities canvas_utilities_style_guide \
  canvas_utilities_palette canvas_utilities_fonts canvas_utilities_icons \
  canvas_utilities_custom_css
ddev exec vendor/bin/phpcs --standard=Drupal,DrupalPractice \
  web/modules/custom/canvas_utilities
ddev exec vendor/bin/phpstan analyse web/modules/custom/canvas_utilities
ddev exec vendor/bin/phpunit -c web/core/phpunit.xml.dist \
  web/modules/custom/canvas_utilities
npm test --prefix web/modules/custom/canvas_utilities/ui
npm run build --prefix web/modules/custom/canvas_utilities/ui
```

The built UI in `ui/dist` is a release asset and must be rebuilt whenever its
TypeScript or CSS source changes. Do not commit `ui/node_modules`.
