# Papounan Site (plugin)

Site-specific features for chateaupapounan.fr. One folder per module in `modules/`, loaded from `papounan-site.php`. Each module registers its assets and enqueues them only where used.

## room-gallery — `[room_gallery]`

Place in a CrocoBuilder **Text** element with "Process shortcodes on frontend" enabled (room single template "Chambre – corps").

| Attribute | Default | |
|---|---|---|
| `id` | current room | Room post ID |
| `image_size` | `large` | Size shown in the grid (WordPress `srcset` still applies) |
| `full_size` | `full` | Size opened in the lightbox |
| `lightbox` | `true` | `false` = plain grid, no links |
| `field` | `pictures` | ACF repeater name |

Data: ACF repeater `pictures` on `chambre` with `picture` (image), `col_span` (1–12) and `row_span` (1–4).

Lightbox: native `<dialog>` (focus trapping and Esc handled by the browser), previous/next buttons and arrow keys, click on the backdrop closes, focus returns to the thumbnail. Without JavaScript the links open the full image.

Changes from the old child-theme version: no Elementor lightbox attributes, assets served from the plugin, tokens instead of hard-coded colours, no `!important`, alt text falls back to "Room – photo N", notices visible to editors only.
