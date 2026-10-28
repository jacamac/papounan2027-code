=== Papounan Site ===
Contributors: jacamac
Tags: chateaupapounan
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.10
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Site-specific features for chateaupapounan.fr (Château Papounan, Saint-Estèphe).

== Description ==

Modules (one folder each in modules/):

* Room gallery — [room_gallery] shortcode: the room's ACF "pictures" repeater as a 12-column grid with a native lightbox.
* Language switcher — gives the TranslatePress flags a real alt (the language name) and hides the duplicate name from screen readers.
* Click tracking — clicks on elements carrying data-trk (set in CrocoBuilder) and the availability calendar's bac:book event become Matomo events (category Navigation).
* Rating schema — each review platform switched on (« Publier dans les données structurées ») is published on the front page as a JSON-LD AggregateRating for SEOPress's #lodging node.

Updates are delivered from GitHub Releases (Plugin Update Checker). A dev site can follow the development branch with:

  define( 'PAPOUNAN_SITE_UPDATE_CHANNEL', 'development' );

== Changelog ==

= 0.1.10 =
* Tweak: typography « Pierre douce ». Every room-gallery fallback is now neutral and generic instead of a copy of the site's value, so a palette or spacing change on the site never needs a plugin update: spacing in plain rem (`--space-xs` 0.625rem, `--space-s` 1rem, `--space-m` 1.25rem instead of 24px), `--radius-md` 4px, `--color-surface-muted` #fff, `--color-accent-strong` currentcolor, `--color-ink-inverse` #fff, `--color-overlay-strong` rgb(0 0 0 / 90%), `--color-border` #ccc. No font name in the CSS. No change where the site defines the tokens.
* Tweak: room-gallery uses the design-system overlay and glass tokens. The lightbox backdrop reads --color-overlay-strong and the close / previous / next buttons --color-glass (hover --color-glass-strong), each with the previous value as fallback. The fallbacks of --color-surface-muted, --color-accent-strong, --color-ink-inverse and --color-border now match the current Médoc palette instead of the old teal values. No markup or behaviour change.

= 0.1.9 =
* New: rating-schema module. On the front page, one JSON-LD AggregateRating node per review_platform entry whose ACF true/false show_in_schema (« Publier dans les données structurées », default off) is on: itemReviewed = SEOPress PRO's BedAndBreakfast node by @id (https://chateaupapounan.fr/#lodging), author = Organization with the platform name and profile_url, ratingValue / bestRating / ratingCount from rating / rating_scale / review_count exactly as stored (each platform's own scale, no conversion or average). An entry with a missing or invalid value is skipped whole; nothing is printed when no platform is on. SEOPress's own schema is untouched.

= 0.1.8 =
* New: click-tracking accepts other plugins' events. Optional data-trk-cat on [data-trk] elements, whitelisted (navigation → Navigation, guide → Guide; anything else → Navigation), and optional data-trk-ctx, appended to the name as " · <ctx>" after the room slug; both are read on the clicked element itself. Other plugins can dispatch a papounan:track CustomEvent on document with detail { cat, action, name, ctx }: same validation and same path as clicks (debug switch included), invalid input silently ignored. First user: the planner's guide pages. Elements without the new attributes are tracked exactly as before.

= 0.1.7 =
* New: click-tracking module. One deferred script on every front-end page turns clicks (and middle-clicks) on elements with data-trk="<placement>" into Matomo events Navigation / vers-reservation (or data-trk-action) / "<placement> · <room slug>", and the calendar's bac:book event into "calendrier · <room slug>". The room slug is the post_name, output as the body class papounan-room-<slug> on room pages: the same in every language and the same as the booking engine's events. Values other than [a-z0-9-] are ignored; nothing happens when Matomo is blocked; navigation is never delayed (Matomo sends with sendBeacon). Debug: localStorage pap.analytics.debug = 1.

= 0.1.6 =
* New: language-switcher module. TranslatePress flags in [language-switcher] get alt = language name (with lang) instead of alt="" role="presentation", and the visible name is aria-hidden, so screen readers hear it once. Clears SEOPress's "no alt text" warning on every page.

= 0.1.5 =
* Fix: room gallery lightbox no longer outputs an empty src="" on its image (invalid HTML flagged by SEO audits); room-gallery.js sets it when the lightbox opens.

= 0.1.4 =
* New: translation support. Loads the papounan-site text domain from /languages and ships the French (fr_FR) .po/.mo files.

= 0.1.3 =
* Remove: seo-schema module. Room schema image fixed in SEOPress schema settings instead.

= 0.1.2 =
* New: seo-schema module: fills empty %%post_thumbnail_url%% in SEOPress automatic schemas.
* Fix: version in source. The plugin header, PAPOUNAN_SITE_VERSION and Stable tag now match the release, so the update checker (which reads the header at the tag) sees new releases.

= 0.1.1 =
* New: seo-schema module: fixes the empty `image` in SEOPress PRO automatic schema #4139 (HotelRoom, `chambre`) by filling `%%post_thumbnail_url%%` from the featured image on singular views.

= 0.1.0 =
* New: room gallery module ([room_gallery]) ported from the hello-elementor-child theme: no Elementor dependency, design-system tokens, native <dialog> lightbox with keyboard navigation, one photo per row on mobile.
* New: update notifications from GitHub Releases, with a development channel for the dev site.
