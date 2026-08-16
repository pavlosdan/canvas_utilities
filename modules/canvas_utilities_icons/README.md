# Canvas Utilities Icons

Imports SVG sources into sanitized, reusable icon libraries and exposes them to
Drupal Core's experimental Icon API and to Canvas code components.

## Managing libraries

Libraries are managed in **Design system → Icons** inside Canvas.

- **Import library** creates a library from individual SVGs, an SVG-only ZIP
  archive, or an SVG sprite.
- **Add icons** uploads more icons into a library that already exists, so a
  library can be built up one file at a time. Re-uploading an unchanged file is
  a no-op; an upload whose icon name matches an existing icon but whose content
  differs replaces that icon and deletes the superseded file. The result of each
  upload is reported as added / replaced / already up to date.
- **Edit** changes the library name, license, and attribution. The library ID
  and prefix are fixed once icons are stored against them, because stored SVGs
  are namespaced with the ID.
- Individual icons can be removed by hovering an icon in the grid.

Deleting a library or an icon also deletes the managed files behind it.

## Usage checking

Before deleting a library or an icon, the confirmation dialog reports where
that icon is currently referenced. Four surfaces are searched:

- component trees stored on content entities;
- config entities that embed component trees (patterns, content templates,
  page regions);
- code component prop examples, which can default to an icon;
- unpublished Canvas auto-save drafts.

By default only the *active* revisions are considered — the published revision
and the newest draft — matching how Canvas itself audits component usage.
Pass `?scope=all` to include superseded revisions.

Usage is derived on demand rather than tracked in a table, because an icon
selection is stored verbatim as its URL inside a component instance's inputs.
The scan is narrowed by `component_id` first, which is indexed, so only
components that actually declare an icon-picker prop are examined.

**The result is advisory, and deletion is never blocked.** An icon URL written
directly into a component's own JavaScript or CSS, or assembled at runtime,
cannot be detected, so "no usage found" is a negative result rather than a
guarantee. If the check itself fails, the dialog says so instead of reporting
the icon as unused.

### API

| Method | Path |
| --- | --- |
| `GET` | `/canvas-utilities/api/v1/icons/{theme}` |
| `GET` | `/canvas-utilities/api/v1/icons/{theme}/{library}/usage` |
| `POST` | `/canvas-utilities/api/v1/icons/{theme}/import` |
| `POST` | `/canvas-utilities/api/v1/icons/{theme}/{library}/icons` |
| `PATCH` | `/canvas-utilities/api/v1/icons/{theme}/{library}` |
| `DELETE` | `/canvas-utilities/api/v1/icons/{theme}/{library}` |
| `DELETE` | `/canvas-utilities/api/v1/icons/{theme}/{library}/icons/{icon}` |

Writes require the `administer canvas utilities icons` permission and a
`X-CSRF-Token` header; reads require `access canvas utilities`.

## Icon props on code components

A code component gets an icon picker by declaring a string prop that references
this module's schema:

```json
{
  "title": "Icon",
  "type": "string",
  "$ref": "json-schema-definitions://canvas_utilities_icons.module/icon"
}
```

Canvas builds its code-editor Props panel from a hard-coded list of prop types
and hides any prop whose type it cannot derive, so icon props cannot be added —
or even seen — there. This module therefore ships an **Icon props** extension of
type `code-editor`. Open a code component, then open it from the Extensions
panel to list the component's props and add or remove icon props.

The extension writes to the component's auto-save draft, the same draft the code
editor reads. Two consequences follow:

- Canvas rejects writes that carry stale auto-save hashes. If the code editor is
  already open when a prop is added, its next save returns 409 until it is
  reloaded, so reload it before making further code changes. Nothing is lost —
  the conflict check is what prevents the editor from overwriting the new prop.
- Icon props still round-trip safely through the editor: the `$ref` is preserved
  on save even though the prop is not displayed.

Once the component is placed on a page, editors get the searchable icon picker
in its settings, and the selected value is stored as a root-relative URL to a
sanitized, content-addressed SVG, validated against the enabled libraries.
