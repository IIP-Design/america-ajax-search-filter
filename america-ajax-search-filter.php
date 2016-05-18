<?php
/**********************************************************************************************************

Plugin Name: 	 America Ajax Search & Filter
Description:     Javascript search and filter
Version:         1.0
Author:          By Office of Design, Bureau of International Information Programs
License:         GPLv3
Text Domain:     america
Domain Path:     /languages/
 
 ************************************************************************************************************/

if ( ! defined( 'ABSPATH' ) ) exit;

define("AASF_PLUGIN_DIR", plugin_dir_path( dirname( __FILE__ ) ) . 'america-ajax-search/' );

// Load plugin class files
require_once( 'includes/class-america-ajax-search-filter.php' );
require_once( 'includes/class-america-ajax-search-filter-settings.php' );
require_once( 'includes/class-gamajo-template-loader.php' );
require_once( 'includes/class-america-template-loader.php' );
require_once( 'includes/class-america-ajax-request.php' );

/**
 * Returns the main instance of aasf to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object America_Ajax_Search_Filter
 */
function america_ajax_search_filter () {
	$instance = America_Ajax_Search_Filter::instance( __FILE__, '1.0' );

	if ( is_null( $instance->settings ) ) {
		// TODO: create plugin settings screens
		$instance->settings = America_Ajax_Search_Filter_Settings::instance( $instance );
	}

	return $instance;
}

// initialize
america_ajax_search_filter();