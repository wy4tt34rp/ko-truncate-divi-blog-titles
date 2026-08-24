<?php
/**
 * Plugin Name: KO: Truncate Divi Blog Titles
 * Description: Truncates rendered Divi Blog titles at a word boundary when a class such as ko-truncate-divi-blog-titles-30 is assigned to the module.
 * Version: 1.0.0
 * Author: KO
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: ko-truncate-divi-blog-titles
 */

defined( 'ABSPATH' ) || exit;

final class KO_Truncate_Divi_Blog_Titles {
	private const CLASS_PATTERN = '/(?:^|\s)ko-truncate-divi-blog-titles-(\d{1,3})(?:\s|$)/';

	/**
	 * Register both Divi 5 block and legacy Divi module filters.
	 */
	public static function init(): void {
		add_filter( 'render_block', array( __CLASS__, 'filter_render_block' ), 999, 2 );
		add_filter( 'et_pb_module_shortcode_output', array( __CLASS__, 'filter_legacy_module' ), 999, 3 );
	}

	/**
	 * Filter rendered blocks. The class guard keeps non-target blocks untouched.
	 *
	 * @param string $block_content Rendered block HTML.
	 * @param array  $block         Parsed block data.
	 * @return string
	 */
	public static function filter_render_block( string $block_content, array $block ): string {
		if ( false === strpos( $block_content, 'ko-truncate-divi-blog-titles-' ) ) {
			return $block_content;
		}

		return self::truncate_markup( $block_content );
	}

	/**
	 * Support Divi's legacy shortcode renderer without changing other modules.
	 *
	 * @param string $output      Module HTML.
	 * @param string $render_slug Divi render slug.
	 * @param object $module      Module instance.
	 * @return string
	 */
	public static function filter_legacy_module( string $output, string $render_slug, $module ): string {
		if ( 'et_pb_blog' !== $render_slug || false === strpos( $output, 'ko-truncate-divi-blog-titles-' ) ) {
			return $output;
		}

		return self::truncate_markup( $output );
	}

	/**
	 * Truncate targeted title links in a rendered Blog module fragment.
	 *
	 * @param string $html Rendered module HTML.
	 * @return string
	 */
	public static function truncate_markup( string $html ): string {
		if ( '' === $html || false === strpos( $html, 'ko-truncate-divi-blog-titles-' ) ) {
			return $html;
		}

		if ( class_exists( 'DOMDocument' ) && class_exists( 'DOMXPath' ) ) {
			$processed = self::truncate_with_dom( $html );
			if ( null !== $processed ) {
				return $processed;
			}
		}

		return self::truncate_with_regex( $html );
	}

	/**
	 * DOM-based processor used when the PHP DOM extension is available.
	 *
	 * @param string $html Module HTML.
	 * @return string|null Null indicates that parsing failed.
	 */
	private static function truncate_with_dom( string $html ): ?string {
		$dom = new DOMDocument( '1.0', 'UTF-8' );
		$previous_errors = libxml_use_internal_errors( true );
		$wrapper_id = 'ko-truncate-divi-blog-titles-root';
		$source = '<?xml encoding="utf-8" ?><div id="' . $wrapper_id . '">' . $html . '</div>';
		$loaded = $dom->loadHTML( $source, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
		libxml_clear_errors();
		libxml_use_internal_errors( $previous_errors );

		if ( ! $loaded ) {
			return null;
		}

		foreach ( iterator_to_array( $dom->childNodes ) as $child ) {
			if ( XML_PI_NODE === $child->nodeType ) {
				$dom->removeChild( $child );
			}
		}

		$root = $dom->getElementById( $wrapper_id );
		if ( ! $root ) {
			return null;
		}

		$xpath = new DOMXPath( $dom );
		$modules = $xpath->query( './/*[contains(@class, "ko-truncate-divi-blog-titles-")]', $root );
		if ( false === $modules ) {
			return null;
		}

		$changed = false;
		foreach ( $modules as $module ) {
			$limit = self::limit_from_classes( $module->getAttribute( 'class' ) );
			if ( null === $limit ) {
				continue;
			}

			$title_links = $xpath->query(
				'.//*[contains(concat(" ", normalize-space(@class), " "), " entry-title ")]//a',
				$module
			);
			if ( false === $title_links ) {
				continue;
			}

			foreach ( $title_links as $link ) {
				$full_title = self::normalize_text( $link->textContent );
				$short_title = self::truncate_text( $full_title, $limit );
				if ( $short_title === $full_title ) {
					continue;
				}

				while ( $link->firstChild ) {
					$link->removeChild( $link->firstChild );
				}
				$link->appendChild( $dom->createTextNode( $short_title ) );
				$link->setAttribute( 'aria-label', $full_title );
				if ( ! $link->hasAttribute( 'title' ) ) {
					$link->setAttribute( 'title', $full_title );
				}
				$changed = true;
			}
		}

		if ( ! $changed ) {
			return $html;
		}

		$output = '';
		foreach ( iterator_to_array( $root->childNodes ) as $child ) {
			$output .= $dom->saveHTML( $child );
		}

		return $output;
	}

	/**
	 * Conservative fallback for hosts without the DOM extension.
	 *
	 * @param string $html Module HTML.
	 * @return string
	 */
	private static function truncate_with_regex( string $html ): string {
		if ( ! preg_match( '/ko-truncate-divi-blog-titles-(\d{1,3})/', $html, $limit_match ) ) {
			return $html;
		}

		$limit = self::sanitize_limit( (int) $limit_match[1] );
		if ( null === $limit ) {
			return $html;
		}

		return (string) preg_replace_callback(
			'#(<h[1-6]\b[^>]*class="[^"]*\bentry-title\b[^"]*"[^>]*>\s*<a\b[^>]*>)(.*?)(</a>\s*</h[1-6]>)#isu',
			static function ( array $matches ) use ( $limit ): string {
				$full_title = self::normalize_text( html_entity_decode( strip_tags( $matches[2] ), ENT_QUOTES | ENT_HTML5, 'UTF-8' ) );
				$short_title = self::truncate_text( $full_title, $limit );
				if ( $short_title === $full_title ) {
					return $matches[0];
				}

				$opening = $matches[1];
				if ( ! preg_match( '/\baria-label\s*=/i', $opening ) ) {
					$opening = preg_replace(
						'/<a\b/i',
						'<a aria-label="' . esc_attr( $full_title ) . '"',
						$opening,
						1
					);
				}
				if ( ! preg_match( '/\btitle\s*=/i', $opening ) ) {
					$opening = preg_replace(
						'/<a\b/i',
						'<a title="' . esc_attr( $full_title ) . '"',
						$opening,
						1
					);
				}

				return $opening . esc_html( $short_title ) . $matches[3];
			},
			$html
		);
	}

	/**
	 * Parse and validate the numeric limit encoded in the CSS class.
	 *
	 * @param string $classes Space-delimited classes.
	 * @return int|null
	 */
	private static function limit_from_classes( string $classes ): ?int {
		if ( ! preg_match( self::CLASS_PATTERN, trim( $classes ), $matches ) ) {
			return null;
		}

		return self::sanitize_limit( (int) $matches[1] );
	}

	/**
	 * Keep class-provided limits within a sensible range.
	 *
	 * @param int $limit Requested limit.
	 * @return int|null
	 */
	private static function sanitize_limit( int $limit ): ?int {
		if ( $limit < 10 || $limit > 500 ) {
			return null;
		}

		return (int) apply_filters( 'ko_truncate_divi_blog_titles_limit', $limit );
	}

	/**
	 * Truncate without splitting the final word whenever possible.
	 *
	 * @param string $text  Full title.
	 * @param int    $limit Maximum displayed character count, including ellipsis.
	 * @return string
	 */
	public static function truncate_text( string $text, int $limit ): string {
		$text = self::normalize_text( $text );
		if ( self::length( $text ) <= $limit ) {
			return $text;
		}

		$ellipsis = (string) apply_filters( 'ko_truncate_divi_blog_titles_ellipsis', '...' );
		$budget = max( 1, $limit - self::length( $ellipsis ) );
		$candidate = rtrim( self::substring( $text, 0, $budget ) );

		if ( '' !== $candidate && ! preg_match( '/\s$/u', self::substring( $text, 0, $budget ) ) ) {
			$word_safe = preg_replace( '/\s+\S*$/u', '', $candidate );
			if ( is_string( $word_safe ) && '' !== trim( $word_safe ) ) {
				$candidate = trim( $word_safe );
			}
		}

		$candidate = rtrim( $candidate, " \t\n\r\0\x0B,.;:!?-–—" );
		if ( '' === $candidate ) {
			$candidate = rtrim( self::substring( $text, 0, $budget ) );
		}

		return $candidate . $ellipsis;
	}

	/**
	 * Normalize whitespace without changing punctuation or letter case.
	 */
	private static function normalize_text( string $text ): string {
		$normalized = preg_replace( '/\s+/u', ' ', trim( $text ) );
		return is_string( $normalized ) ? $normalized : trim( $text );
	}

	private static function length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : strlen( $text );
	}

	private static function substring( string $text, int $start, int $length ): string {
		return function_exists( 'mb_substr' )
			? mb_substr( $text, $start, $length, 'UTF-8' )
			: substr( $text, $start, $length );
	}
}

KO_Truncate_Divi_Blog_Titles::init();
