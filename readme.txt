=== Papounan Site ===
Contributors: jacamac
Tags: chateaupapounan
Requires at least: 6.6
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 0.1.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Site-specific features for chateaupapounan.fr (Château Papounan, Saint-Estèphe).

== Description ==

Modules (one folder each in modules/):

* Room gallery — [room_gallery] shortcode: the room's ACF "pictures" repeater as a 12-column grid with a native lightbox.

Updates are delivered from GitHub Releases (Plugin Update Checker). A dev site can follow the development branch with:

  define( 'PAPOUNAN_SITE_UPDATE_CHANNEL', 'development' );

== Changelog ==

= 0.1.0 =
* New: room gallery module ([room_gallery]) ported from the hello-elementor-child theme: no Elementor dependency, design-system tokens, native <dialog> lightbox with keyboard navigation, one photo per row on mobile.
* New: update notifications from GitHub Releases, with a development channel for the dev site.
