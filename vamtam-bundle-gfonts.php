<?php
/**
 * Plugin Name: VamTam Bundle Google Fonts
 * Plugin URI: https://vamtam.com/
 * Description: Combines requests for Google Web Fonts into a single <link> element. No configuration needed.
 * Version: 1.0.0
 * Author: VamTam
 * Author URI: https://vamtam.com/
 * License: GPL2
 */

if ( version_compare( PHP_VERSION, '7.0.0' ) >= 0 ) {
	require_once 'lib/base.php';
} else {
	echo 'VamTam Bundle Google Fonts requires PHP >= 7.0.0';
}
