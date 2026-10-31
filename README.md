# Papounan Site

WordPress site plugin for [chateaupapounan.fr](https://chateaupapounan.fr) (Château Papounan, chambres d'hôtes in Saint-Estèphe). It holds the site-specific features of the 2027 rebuild (WordPress + CrocoBuilder + ACF PRO), formerly in the hello-elementor-child theme.

Related repos: **papounan2027-theme** (minimal theme) and **papounan2027-builder** (CrocoBuilder data, committed by CrocoBuilder).

## Modules

| Module | Provides | Notes |
|---|---|---|
| `room-gallery` | `[room_gallery]` | ACF repeater `pictures` (`picture`, `col_span`, `row_span`) as a 12-column grid; native `<dialog>` lightbox; one photo per row on mobile |
| `language-switcher` | filter on `[language-switcher]` | TranslatePress flags: `alt` = language name + `lang`, `role` removed; name span `aria-hidden`. Language list in `papounan_langsw_languages()` — update when a language is added |
| `click-tracking` | `data-trk` attributes → Matomo events | One deferred script on every front-end page; body class `papounan-room-<slug>` on room pages. See below |
| `rating-schema` | JSON-LD `AggregateRating` per rating platform | Front page only; one switch per platform in the `review_platform` CPT. See below |
| `media-replace` | REST `papounan/v1/media/<id>/replace` and `/revert` | Replaces an image attachment's file in place: same id, file name and URL. Backup in `uploads/papounan-replaced/`. See below |

Still to port from the old child theme (`papounan-widgets`): `chambre-booking`, `chateau-distance`, the TranslatePress capability filter, and a decision on `update-thumbnail`.

## Translations

The plugin loads the `papounan-site` text domain from `languages/` (header `Domain Path: /languages`). French is shipped as `papounan-site-fr_FR.po` and `.mo`; recompile the `.mo` after editing the `.po`.

### `[room_gallery]`

Place it in a CrocoBuilder **Text** element with "Process shortcodes on frontend" enabled (room template "Chambre – corps").

| Attribute | Default | |
|---|---|---|
| `id` | current room | Room post ID |
| `image_size` | `large` | Size shown in the grid (`srcset` still applies) |
| `full_size` | `full` | Size opened in the lightbox |
| `lightbox` | `true` | `false` = plain grid, no links |
| `field` | `pictures` | ACF repeater name |

Lightbox: native `<dialog>` (focus trap and Esc handled by the browser), previous/next buttons and arrow keys, click outside closes, focus returns to the thumbnail. Without JavaScript the links open the full image.

### Click tracking (`data-trk`)

**The rule:** in CrocoBuilder a trackable element only carries an attribute; the logic lives in this plugin (`modules/click-tracking/`). No JS in the builder, no list of buttons in the plugin.

```html
<a href="/booking/?room_id=621164" data-trk="prix">Réserver Chambre Domeyne</a>
```

**Event** (on click, Ctrl/Cmd-click, or middle-click):

| | |
|---|---|
| Category | the element's `data-trk-cat` through a whitelist (`navigation` → `Navigation`, `guide` → `Guide`), else `Navigation` (separate from the booking engine's `Réservation`) |
| Action | the element's `data-trk-action`, else `vers-reservation` |
| Name | `<data-trk> · <room slug>` on a room page (`prix · chambre-domeyne`), `<data-trk>` alone elsewhere; then ` · <data-trk-ctx>` when the element carries one (`carte-lieu · saint-emilion`) |
| Value | none |

The room slug is the room's `post_name`, output by PHP as the body class `papounan-room-<slug>` on single `chambre` pages: the same in every language (`/en/rooms/…`, `/de/zimmer/…` are the same post) and the same value the booking engine sends in `chambre-demandee` and `resultat` (e.g. `suite-l-estuaire`), so both can be matched in segments.

**Placements in use:**

| `data-trk` | Page | Placement |
|---|---|---|
| `intro` | room pages (template 7) | secondary button after the « Pourquoi choisir » list |
| `prix` | room pages (template 7) | main button under the price |
| `calendrier` | room pages | not an attribute: the availability calendar's `bac:book` event (bookable day clicked) |
| `header` | every page | planned: header « Réserver en direct » button |

**Add one:** in CrocoBuilder, give the element the attribute `data-trk` with a new name (only `a-z`, `0-9`, `-`; anything else is ignored), and `data-trk-action` if it isn't a link to the booking page. Add it to the table above. Nothing to change in the plugin.

`data-trk-cat` and `data-trk-ctx` are read on the clicked `[data-trk]` element itself, never on an ancestor (the planner clones its cards into a dialog). An unknown category falls back to `Navigation`; an invalid context is dropped.

**From another plugin** (no `_paq` in your code): dispatch a `papounan:track` event on `document`.

```js
document.dispatchEvent( new CustomEvent( 'papounan:track', {
	detail: { cat: 'guide', action: 'filtre', name: 'vignobles', ctx: 'medoc' },
} ) );
```

`action` and `name` are required and follow the same rule as `data-trk`; `cat` goes through the same whitelist; `ctx` is optional. Invalid input is silently ignored. The event goes through the same path as clicks (room suffix, then context; debug switch below). First user: the planner's guide pages.

Safeguards: the script never prevents or delays the navigation (Matomo sends the event with `sendBeacon`), does nothing when Matomo is blocked or not loaded (`window._paq` missing), and never throws.

**Debug:** `localStorage['pap.analytics.debug'] = '1'` (same switch as the booking engine) prints each event with `console.debug('[papounan tracking]', …)`.

### Rating schema (`AggregateRating`)

Each **Plateforme d'avis** (`review_platform`) entry whose ACF true/false **« Publier dans les données structurées »** (`show_in_schema`, default off) is on becomes one JSON-LD `AggregateRating` node, printed in `<head>` on the front page (every language):

| Property | Source |
|---|---|
| `itemReviewed` | `{ "@id": "https://chateaupapounan.fr/#lodging" }`, SEOPress PRO's BedAndBreakfast node (constant `PAPOUNAN_RATING_SCHEMA_ITEM_ID`: SEOPress exposes no API for it, update it if the @id changes) |
| `author` | `Organization`: entry title; `url` = `profile_url` when set |
| `ratingValue` / `bestRating` / `ratingCount` | `rating` / `rating_scale` / `review_count`, exactly as stored (each platform's own scale, no conversion, no average) |

An entry with a missing or invalid value (no title, rating not a number, rating above the scale, no count) is skipped whole. The nodes are separate and only point to SEOPress's node by `@id`: SEOPress's own schema is untouched. There is no `reviewCount` (the CPT has a single count).

**Why front page only:** the badges are also on Témoignages, Chambres and every room page, but they come from CrocoBuilder query loops (some with the query cache on), so whether one was rendered can't be detected reliably server-side; Témoignages and Chambres also have no `#lodging` node to point to.

**Switch a platform on or off:** edit the entry in WordPress, no code change. Then purge the page cache (Cloudflare) so the homepage is regenerated.

### Media replace (REST)

For the photo service (papounan-photos, on the NAS): put a better rendition of a library photo in place of the current file. **The file is never renamed**: the attachment id, `_wp_attached_file` and the URL stay the same, so every page and indexed image URL keeps working. A new photo with an SEO file name is a normal upload (`POST /wp/v2/media`), not this route; alt, title, caption and description are set through `POST /wp/v2/media/<id>` and are not touched here.

Authentication: application password of a dedicated user with `upload_files` who can edit the attachment (an Editor also passes ShortPixel's `edit_others_posts` check; an Author does not, and the response then says `shortpixel.status: "error"`). The password is never in this repo.

| Route (POST) | Parameters | |
|---|---|---|
| `/wp-json/papounan/v1/media/<id>/replace` | multipart `file`; `dry_run` (default `true`); `optimize` (default `true`) | `dry_run=true` validates and reports what would change, writes nothing |
| `/wp-json/papounan/v1/media/<id>/revert` | `dry_run` (default `true`); `optimize` (default `false`) | Undoes the latest replace not yet reverted (a stack: each revert undoes one replace) |

**Accepted:** JPEG, PNG, WebP up to 20 MB (`PAPOUNAN_MEDIA_REPLACE_MAX_BYTES`; PHP's `upload_max_filesize` may be lower), a real image (type read from the content), and **the same type as the current file**, otherwise 409 (a JPEG cannot replace a WebP). SVG, GIF and AVIF are refused. Same bytes as the current file (sha256): nothing is done, `identical: true`.

**What a replace does:**

1. Backs up the main file, the unscaled original (`-scaled` attachments) and every sub-size, with the stored metadata, in `uploads/papounan-replaced/<id>/<Ymd-His>/` (`manifest.json`: user, time, sha256 of each file).
2. Fires `wp_handle_replace` (Enable Media Replace's action). ShortPixel (verified in 6.6.1, `handleReplaceHook` → `onDelete()`) then drops its data for the image (optimization record, its own backup of the original, `.webp` / `.avif` copies, queue entry) and deletes the image files, which are in our backup and written again.
3. Writes the new bytes to the same path (and to the unscaled original, if any), makes the sub-sizes with the image editor (core's names, `<original>-<w>x<h>.<ext>`, after the `intermediate_image_sizes_advanced` filter), updates the metadata, deletes the old sub-sizes that no longer exist. If a step fails, the backup is put back (500 with `restored: true`).
4. ShortPixel: enqueues the image with the `shortpixel/optimize-media` ability (`process: false`; ShortPixel processes its queue later, or the caller runs `shortpixel/run-queue`). Not for PNG: ShortPixel's PNG → JPG conversion would rename the file.

Sub-sizes are deliberately not made with `wp_generate_attachment_metadata()` / `wp_update_image_subsizes()`: they apply the `wp_generate_attachment_metadata` filter, where ShortPixel converts PNGs and runs its AI SEO (alt text, file name). No big-image scaling or EXIF rotation either (both save a `-scaled` / `-rotated` copy and change the URL); sub-sizes are made upright, browsers rotate the main file. Send renditions of at most 2560 px.

**Response** (JSON): `id`, `dry_run`, `identical`, `url` (unchanged), `file`, `mime`, `old` / `new` (`width`, `height`, `filesize`, `sha256`), `original_image`, `sub_sizes.regenerated` (size → file; predicted on a dry run) and `sub_sizes.removed`, `backup` (relative to uploads), `shortpixel` (`status` queued / skipped / unavailable / error, `message`), `cache_hint` (`url` and `urls`: main, original and every old and new sub-size, to purge in the Copilhost / Cloudflare cache; nothing is purged here), `warnings` (dimensions change → some sub-size names change: a page that hard-codes an old sub-size URL breaks; aspect ratio change). Errors: 400 no file, 404 not an image attachment, 409 type mismatch / locked / file moved, 413 too large, 415 not an accepted image.

**Revert:** checks every backup file against its sha256 and that `_wp_attached_file` did not change, backs up the current state (`pre-revert`), fires `wp_handle_replace`, puts back the files and the metadata, deletes the sub-sizes of the replaced rendition and marks the backup reverted. ShortPixel's data for the image is gone after a replace (see step 2); `optimize=true` re-enqueues it.

Nothing ever deletes `uploads/papounan-replaced/`: clean it by hand once a replace is confirmed. One replace or revert at a time per attachment (lock folder `.lock`, taken over after 10 minutes).

## Releases and updates

| Branch / tag | What happens |
|---|---|
| Pull request → `main` | CI: PHPCS (WordPress-Extra, PHP 7.4+), PHPStan level 6, `composer audit`, ESLint, Stylelint |
| Push to `development` | CI, then `papounan-site.zip` is attached to the rolling **dev-latest** pre-release |
| Tag `vX.Y.Z` on `main` | CI, then a release with the version injected from the tag and notes from `readme.txt` |

WordPress shows updates through [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker), bundled in the zip. Production follows releases; the dev site follows `dev-latest` with this line in `wp-config.php`:

```php
define( 'PAPOUNAN_SITE_UPDATE_CHANNEL', 'development' );
```

### Release checklist

1. On `development`: add a `= X.Y.Z =` section at the top of the changelog in `readme.txt`.
2. Open a pull request `development` → `main`; merge when CI is green.
3. Tag `main`: `git tag vX.Y.Z && git push origin vX.Y.Z` (the release fails if the changelog entry is missing).

## Development

```bash
composer install          # PHP tools + Plugin Update Checker
npm install               # ESLint + Stylelint (commit package-lock.json)
composer lint             # PHPCS + PHPStan
npm run lint              # JS + CSS
bash tools/build-zip.sh   # build/papounan-site.zip, as CI does
```

Conventions: BEM class names; design-system tokens (`--color-*`, `--space-*`, …) with neutral, generic fallbacks (never a font name or a copy of the site’s value); no media queries except documented exceptions; no jQuery or third-party JavaScript; never edit files on the server.

## Design system snapshot

CrocoBuilder (DB-first on dev) is the source of truth for tokens, classes and common styles, committed to papounan2027-builder. `design-system/papounan-design-system.css` is a readable snapshot, regenerated with:

```bash
python3 tools/export-common-styles.py ../papounan2027-builder > design-system/papounan-design-system.css
```

## License

GPL-2.0-or-later.
