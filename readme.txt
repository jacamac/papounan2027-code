=== Papounan Site ===
Contributors: jacamac
Tags: chateaupapounan
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.5
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Site-specific features for chateaupapounan.fr (Château Papounan, Saint-Estèphe).

== Description ==

Modules (one folder each in modules/):

* Room gallery — [room_gallery] shortcode: the room's ACF "pictures" repeater as a 12-column grid with a native lightbox.

Updates are delivered from GitHub Releases (Plugin Update Checker). A dev site can follow the development branch with:

  define( 'PAPOUNAN_SITE_UPDATE_CHANNEL', 'development' );

== Changelog ==

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
