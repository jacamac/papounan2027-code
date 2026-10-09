# Papounan Site

WordPress site plugin for [chateaupapounan.fr](https://chateaupapounan.fr) (Château Papounan, chambres d'hôtes in Saint-Estèphe). It holds the site-specific features of the 2027 rebuild (WordPress + CrocoBuilder + ACF PRO), formerly in the hello-elementor-child theme.

Related repos: **papounan2027-theme** (minimal theme) and **papounan2027-builder** (CrocoBuilder data, committed by CrocoBuilder).

## Modules

| Module | Provides | Notes |
|---|---|---|
| `room-gallery` | `[room_gallery]` | ACF repeater `pictures` (`picture`, `col_span`, `row_span`) as a 12-column grid; native `<dialog>` lightbox; one photo per row on mobile |

Still to port from the old child theme (`papounan-widgets`): `chambre-booking`, `chateau-distance`, the TranslatePress capability filter, and a decision on `update-thumbnail`.

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

Conventions: BEM class names; design-system tokens (`--color-*`, `--space-*`, …) with fallbacks; no media queries except documented exceptions; no jQuery or third-party JavaScript; never edit files on the server.

## Design system snapshot

CrocoBuilder (DB-first on dev) is the source of truth for tokens, classes and common styles, committed to papounan2027-builder. `design-system/papounan-design-system.css` is a readable snapshot, regenerated with:

```bash
python3 tools/export-common-styles.py ../papounan2027-builder > design-system/papounan-design-system.css
```

## License

GPL-2.0-or-later.
