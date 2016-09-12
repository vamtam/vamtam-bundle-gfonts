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
	/**
	 * Add actions and filters
	 */
	public static function setup_filters() {
		add_action( 'wp_print_styles', [ __CLASS__, 'clean_styles' ], 2 );
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

		if ( count( $used['family'] ) > 0 ) {
			$url = 'https://fonts.googleapis.com/css';

			$subsets = implode( ',', array_unique( call_user_func_array( 'array_merge', $used['subset'] ) ) );

			$url = add_query_arg( 'subsets', $subsets, $url );
			$url = add_query_arg( 'family', implode( '|', $used['family'] ), $url );

			wp_enqueue_style( 'vamtam-bundled-gfonts', $url );
		}
	}
}

Base::setup_filters();