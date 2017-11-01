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
	 * Given a list of use Google Fonts, enqueue them using wp_enqueue_style
	 */
	private static function enqueue( $used, $handle  ) {
		if ( count( $used['family'] ) > 0 ) {
			$url = 'https://fonts.googleapis.com/css';

			$subsets = implode( ',', array_unique( call_user_func_array( 'array_merge', $used['subset'] ) ) );

			$url = add_query_arg( 'subsets', $subsets, $url );
			$url = add_query_arg( 'family', implode( '|', $used['family'] ), $url );

			wp_enqueue_style( $handle, $url );
		}
	}
}

Base::setup_filters();