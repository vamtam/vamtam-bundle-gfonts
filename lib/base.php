<?php

/*
	Copyright 2016  VamTam (support@vamtam.com)

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License, version 2, as
	published by the Free Software Foundation.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

namespace Vamtam\Budle_Gfonts;

class Base {
	private static $used_fonts;
	private static $transient_duration = 3600 * 24;

	/**
	 * Add actions and filters
	 */
	public static function setup_filters() {
		self::$used_fonts = [
			'family' => [],
			'subset' => [],
		];

		add_action( 'wp_print_styles', [ __CLASS__, 'clean_styles' ], 2 );
		add_action( 'wp_footer', [ __CLASS__, 'print_revslider_fonts' ], 2 );

		add_filter( 'revslider_printCleanFontImport', [ __CLASS__, 'clean_revslider_fonts' ] );
	}

	/**
	 * Extract styles with $src matching the Google Fonts domain,
	 * remove them from the queue and then enqueue their combined alternative
	 */
	public static function clean_styles() {
		global $wp_styles;

		$used = [
			'family' => [],
			'subset' => [],
		];

		// extract enqueued styles with $src matching the Google Fonts domain
		foreach ( $wp_styles->queue as $index => $handle ) {
			if ( count( $wp_styles->registered[ $handle ]->extra ) === 0 ) {
				// styles with extra data cannot be combined with others (yet)

				$parsed = wp_parse_url( $wp_styles->registered[ $handle ]->src );

				if ( isset( $parsed['host'] ) && 'fonts.googleapis.com' === $parsed['host'] ) {
					$args = wp_parse_args( $parsed['query'] );

					$used['family'][] = $args['family'];
					$used['subset'][] = explode( ',', $args['subset'] ?? '' );

					unset( $wp_styles->queue[ $index ] ); // remove this font from the queue
				}
			}
		}

		self::enqueue( $used, 'vamtam-bundled-gfonts' );
	}

	/**
	 * Same as clean_styles(), but for Slider Revolution fonts, which do not use wp_enqueue_style
	 */
	public static function clean_revslider_fonts( $link ) {
		if ( ! empty( $link ) ) {
			$link = str_replace( '<link href="', '', $link );
			$link = explode( '"', $link, 2 );

			$parsed = wp_parse_url( $link[0] );

			$args = wp_parse_args( $parsed['query'] );

			self::$used_fonts['family'][] = $args['family'];
			self::$used_fonts['subset'][] = explode( ',', $args['subset'] ?? '' );
		}
	}

	/**
	 * Wrapper around enqueue() for the Slider Revolution fonts
	 */
	public static function print_revslider_fonts() {
		self::enqueue( self::$used_fonts, 'vamtam-bundled-gfonts-late' );
	}

	/**
	 * Prefetches the fonts CSS, so that the user doesn't have to request fonts.googleapis.com
	 */
	private static function get_inline_css( $link ) {
		$contents = get_transient( 'vamtam-gfbundle-css' );

		if ( false === $contents ) {
			// If link is empty, early exit.
			if ( empty( $link ) ) {
				set_transient( 'vamtam-gfbundle-css', 'failed', self::$transient_duration );
				return false;
			}

			// Get remote HTML file.
			$response = wp_remote_get( $link );

			// Check for errors.
			if ( is_wp_error( $response ) ) {
				set_transient( 'vamtam-gfbundle-css', 'failed', self::$transient_duration );
				return false;
			}

			// Parse remote HTML file.
			$contents = wp_remote_retrieve_body( $response );
			// Check for error.
			if ( is_wp_error( $contents ) || ! $contents ) {
				set_transient( 'vamtam-gfbundle-css', 'failed', self::$transient_duration );
				return false;
			}

			// Store remote HTML file in transient, expire after 24 hours.
			set_transient( 'vamtam-gfbundle-css', $contents, self::$transient_duration );
		}

		// Return false if we were unable to get the contents of the googlefonts from remote.
		if ( 'failed' === $contents ) {
			return false;
		}

		// If we got this far then we can safely return the contents.
		return $contents;
	}

	/**
	 * Given a list of use Google Fonts, enqueue them using wp_enqueue_style
	 */
	private static function enqueue( $used, $handle  ) {
		if ( count( $used['family'] ) > 0 ) {
			$url = 'https://fonts.googleapis.com/css';

			$subsets = implode( ',', array_unique( call_user_func_array( 'array_merge', $used['subset'] ) ) );

			$url = add_query_arg( 'subsets', $subsets, $url );
			$url = add_query_arg( 'family', implode( '|', $used['family'] ), $url );

			$css = self::get_inline_css( $url );

			if ( empty( $css ) ) {
				wp_enqueue_style( $handle, $url );
			} else {
				wp_add_inline_style( 'front-all', $css );
			}
		}
	}
}

Base::setup_filters();