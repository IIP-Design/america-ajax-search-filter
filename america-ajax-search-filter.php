<?php
/**********************************************************************************************************

Plugin Name: 	 America Ajax Search & Filter
Description:     Javascript search and filter
Version:         dev-0.0.1
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
 * Returns the main instance of asf to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object America_Ajax_Search_Filter
 */
function america_ajax_search_filter () {
	$instance = America_Ajax_Search_Filter::instance( __FILE__, 'dev-0.0.1' );

	if ( is_null( $instance->settings ) ) {
		$instance->settings = America_Ajax_Search_Filter_Settings::instance( $instance );
	}

	return $instance;
}

america_ajax_search_filter();

//add_action( 'all', create_function( '', 'var_dump( current_filter() );' ) );