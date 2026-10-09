# CLAUDE.md — Papounan Site plugin

Site plugin for chateaupapounan.fr (WordPress + CrocoBuilder + ACF PRO). Owner: Jacques Leisy. Read README.md first.

## Ground rules
- Distinguish what you know, infer and assume. Never invent WordPress/ACF/CrocoBuilder APIs; say what to verify.
- Jacques commits, merges and tags. Work on `development`; never push to `main`, never create tags.
- Never edit files on the server; everything goes through this repo and a release.

## Structure
- `papounan-site.php` — header, constants (`PAPOUNAN_SITE_VERSION/DIR/URL`), module list.
- `includes/updates.php` — Plugin Update Checker wiring (GitHub Releases; `PAPOUNAN_SITE_UPDATE_CHANNEL` = `development` follows dev-latest).
- `modules/<name>/` — one self-contained feature per folder: `<name>.php`, `.css`, `.js`. Register assets on `init`, enqueue only where used.
- `tools/` and `design-system/` are not shipped (see `.distignore`).

## Conventions
- PHP 7.4+, WordPress Coding Standards (WordPress-Extra). Prefix globals `papounan_site_` / `papounan_<module>_` / `PAPOUNAN_`. Text domain `papounan-site`.
- Escape on output, sanitise on input; shortcodes return strings.
- CSS: BEM, design-system tokens with fallbacks (`var(--space-xs, 10px)`), no `!important`, no media queries unless documented in the module header.
- JS: vanilla, no jQuery, no dependencies; progressive enhancement.
- CrocoBuilder: shortcodes go in a Text element with "Process shortcodes on frontend". Inside CrocoBuilder templates `get_the_ID()` may not be the current post — use `get_queried_object_id()` on singular views.

## Before proposing a change
- `composer lint` and `npm run lint` pass.
- Bump nothing by hand for releases (the tag sets the version), but add a `= X.Y.Z =` changelog entry in `readme.txt`.
- Update README.md when a module or attribute changes.
