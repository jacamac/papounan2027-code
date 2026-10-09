<?php
/**
 * Update notifications from GitHub Releases, via Plugin Update Checker (PUC).
 *
 * - Production: tagged releases (vX.Y.Z) on main; the release zip attached by
 *   the "Release" workflow is installed.
 * - Dev site: add `define( 'PAPOUNAN_SITE_UPDATE_CHANNEL', 'development' );`
 *   to wp-config.php to follow the rolling "dev-latest" pre-release built by
 *   the "Dev Release" workflow on every push to the development branch.
 *
 * PUC is only present in built zips (vendor/ is not committed); when it is
 * missing (e.g. a git checkout) this file does nothing.
 *
 * @package PapounanSite
 */

defined( 'ABSPATH' ) || exit;

const PAPOUNAN_SITE_REPO = 'https://github.com/jacamac/papounan2027-code/';
const PAPOUNAN_SITE_SLUG = 'papounan-site';

$papounan_site_puc = PAPOUNAN_SITE_DIR . 'vendor/yahnis-elsts/plugin-update-checker/plugin-update-checker.php';
if ( ! file_exists( $papounan_site_puc ) ) {
	return;
}
require_once $papounan_site_puc;

$papounan_site_checker = YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
	PAPOUNAN_SITE_REPO,
	PAPOUNAN_SITE_DIR . 'papounan-site.php',
	PAPOUNAN_SITE_SLUG
);

$papounan_site_dev = defined( 'PAPOUNAN_SITE_UPDATE_CHANNEL' ) && 'development' === PAPOUNAN_SITE_UPDATE_CHANNEL;

if ( $papounan_site_dev ) {
	$papounan_site_checker->setBranch( 'development' );

	/*
	 * Branch mode: GitHub's branch zipball unpacks to "{repo}-{branch}/", which
	 * WordPress would install as a separate plugin. Point the download at the
	 * correctly named zip that CI attaches to the "dev-latest" pre-release.
	 */
	add_filter(
		'puc_request_info_result-' . PAPOUNAN_SITE_SLUG,
		static function ( $info ) {
			if ( null !== $info ) {
				$info->download_url = PAPOUNAN_SITE_REPO . 'releases/download/dev-latest/' . PAPOUNAN_SITE_SLUG . '.zip';
			}
			return $info;
		}
	);
} else {
	// Install the zip attached to the release, not GitHub's source archive.
	$papounan_site_checker->getVcsApi()->enableReleaseAssets(); // @phpstan-ignore method.notFound (GitHubApi uses the ReleaseAssetSupport trait)
}
