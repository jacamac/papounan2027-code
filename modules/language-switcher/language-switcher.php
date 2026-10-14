<?php
/**
 * Language switcher: accessible flag alt texts for the TranslatePress switcher.
 *
 * TranslatePress (3.3.x, "v2" switcher) outputs every flag as
 *   <img class="trp-flag-image" alt="" role="presentation">
 * followed by <span class="trp-language-item-name">Name</span>. That is valid
 * (decorative image), but SEOPress's content analysis reports empty alts as
 * missing, on every page since the switcher is in the header.
 *
 * This module moves the language name into the flag instead of duplicating it:
 *   - flag: alt="<visible name>", lang="<code>", role removed;
 *   - name span: aria-hidden="true" (still visible, read once via the alt).
 * The link keeps a name on phones, where the span is hidden by CSS.
 *
 * Hook: core filter `do_shortcode_tag`, limited to [language-switcher], so the
 * rest of the page is untouched. If TranslatePress changes its markup (class
 * names, flag file names), nothing matches and the output is returned as is.
 *
 * @package PapounanSite
 */

defined( 'ABSPATH' ) || exit;

/**
 * Languages served by the site: flag file code => visible name and BCP 47 tag.
 * Names must match what TranslatePress displays (WCAG 2.5.3, label in name).
 * Update this list when a language is added in TranslatePress.
 *
 * @return array<string, array{0: string, 1: string}>
 */
function papounan_langsw_languages(): array {
	return array(
		'fr_FR' => array( 'Français', 'fr' ),
		'en_GB' => array( 'English (UK)', 'en' ),
		'de_DE' => array( 'Deutsch', 'de' ),
		'es_ES' => array( 'Español', 'es' ),
		'it_IT' => array( 'Italiano', 'it' ),
	);
}

/**
 * Rewrite the flags in the [language-switcher] shortcode output.
 *
 * @param string|false $output Shortcode output (false if short-circuited).
 * @param string       $tag    Shortcode name.
 * @return string|false
 */
function papounan_langsw_flag_alts( $output, $tag ) {
	if ( 'language-switcher' !== $tag || ! is_string( $output ) || false === strpos( $output, 'trp-flag-image' ) ) {
		return $output;
	}

	$languages = papounan_langsw_languages();
	$processor = new WP_HTML_Tag_Processor( $output );

	// Walk opening tags in document order. A flag "arms" the next name span of
	// the same item; a new language item disarms it, so an item without a name
	// never hides the name of the next one.
	$armed = false;
	while ( $processor->next_tag() ) {
		if ( $processor->has_class( 'trp-language-item' ) ) {
			$armed = false;
			continue;
		}

		if ( 'IMG' === $processor->get_tag() && $processor->has_class( 'trp-flag-image' ) ) {
			$src = (string) $processor->get_attribute( 'src' );
			if ( ! preg_match( '#/([a-z]{2}_[A-Z]{2})\.svg(?:\?|$)#', $src, $match ) || ! isset( $languages[ $match[1] ] ) ) {
				continue;
			}
			list( $name, $lang ) = $languages[ $match[1] ];
			$processor->set_attribute( 'alt', $name );
			$processor->set_attribute( 'lang', $lang );
			$processor->remove_attribute( 'role' );
			$armed = true;
			continue;
		}

		if ( $armed && 'SPAN' === $processor->get_tag() && $processor->has_class( 'trp-language-item-name' ) ) {
			$processor->set_attribute( 'aria-hidden', 'true' );
			$armed = false;
		}
	}

	return $processor->get_updated_html();
}
add_filter( 'do_shortcode_tag', 'papounan_langsw_flag_alts', 10, 2 );
