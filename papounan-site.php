<?php
/**
 * Plugin Name:       Papounan Site
 * Plugin URI:        https://github.com/jacamac/papounan2027-code
 * Description:       Site-specific features for chateaupapounan.fr (Château Papounan): room gallery and other modules formerly in the hello-elementor-child theme.
 * Version:           0.1.0
 * Requires at least: 6.6
 * Requires PHP:      7.4
 * Author:            Jacques Leisy
 * Author URI:        https://github.com/jacamac
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       papounan-site
 * Update URI:        https://github.com/jacamac/papounan2027-code
 *
 * @package PapounanSite
 */

defined( 'ABSPATH' ) || exit;

define( 'PAPOUNAN_SITE_VERSION', '0.1.0' );
define( 'PAPOUNAN_SITE_DIR', plugin_dir_path( __FILE__ ) );
define( 'PAPOUNAN_SITE_URL', plugin_dir_url( __FILE__ ) );

/*
 * Updates from GitHub Releases (Plugin Update Checker, bundled in vendor/ by the
 * release build). Production follows tagged releases on main. A dev site can
 * follow the rolling "dev-latest" build of the development branch instead:
 *     define( 'PAPOUNAN_SITE_UPDATE_CHANNEL', 'development' );  // in wp-config.php
 */
require_once PAPOUNAN_SITE_DIR . 'includes/updates.php';

/*
 * Modules. Each module is self-contained in modules/<name>/ and loads its own
 * assets only where it is used. Add new modules to this list.
 */
require_once PAPOUNAN_SITE_DIR . 'modules/room-gallery/room-gallery.php';
