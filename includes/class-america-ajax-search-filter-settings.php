<?php

if ( ! defined( 'ABSPATH' ) ) exit;

class America_Ajax_Search_Filter_Settings {

	private static $_instance = null;


	public function __construct ( $parent ) {
	}


	/**
	 * Main America_Ajax_Search_Filter_Settings Instance
	 *
	 * Ensures only one instance of asf_Settings is loaded or can be loaded.
	 * 
	 * @return Main America_Ajax_Search_Filter_Settings instance
	 */
	public static function instance ( $parent ) {
		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $parent );
		}
		return self::$_instance;
	} 

}
