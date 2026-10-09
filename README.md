# papounan2027-code

Code for the 2027 rebuild of [chateaupapounan.fr](https://chateaupapounan.fr) (WordPress + CrocoBuilder + ACF PRO), developed on dev.chateaupapounan.fr.

CrocoBuilder's own data (pages, templates, design system) is versioned separately in **papounan2027-builder** by CrocoBuilder itself.

## Layout

| Folder | What | Deploy |
|---|---|---|
| `theme/papounan2027/` | Standalone minimal theme (no design; `<div id="primary">` so CrocoBuilder provides the single `<main>`) | Zip the folder → Appearance → Themes → Upload |
| `plugins/papounan-site/` | Site plugin: features formerly in the hello-elementor-child theme, one folder per module | Zip the folder → Plugins → Upload |
| `design-system/` | `papounan-design-system.css`: readable snapshot of the CrocoBuilder common styles | Reference only (regenerate with `tools/`) |
| `tools/` | Helper scripts | Not deployed |

Each deployable folder zips on its own, e.g. from the repo root:

```bash
cd plugins && zip -r ../papounan-site.zip papounan-site -x '*.DS_Store' && cd ..
cd theme && zip -r ../papounan2027.zip papounan2027 -x '*.DS_Store' && cd ..
```

## Site plugin modules

| Module | Provides | Notes |
|---|---|---|
| `room-gallery` | `[room_gallery]` | ACF repeater `pictures` (`picture`, `col_span`, `row_span`) as a 12-column grid; native `<dialog>` lightbox; one photo per row on mobile |

Still to port from `papounan-widgets` (old child theme): `chambre-booking`, `chateau-distance`, the TranslatePress capability filter, and a decision on `update-thumbnail`.

## Design system

CrocoBuilder (DB-first on dev) is the source of truth for tokens, classes and common styles; they are committed to papounan2027-builder. To refresh the readable CSS snapshot here:

```bash
python3 tools/export-common-styles.py ../papounan2027-builder > design-system/papounan-design-system.css
```

## Conventions

- BEM class names, design-system tokens (`--color-*`, `--space-*`, …) with fallbacks.
- No media queries except documented exceptions (header, footer, home review badges, gallery on mobile).
- No jQuery, no third-party JavaScript.
- Never edit files directly on the server; change them here, then deploy.
