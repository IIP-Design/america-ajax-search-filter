<?php

 //* Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

class America_Ajax_Search_Filter {

	private static $_instance = null;

	private $_version;
	private $_token;

	public $settings = null;

	public $dir;
	public $assets_dir;
	public $assets_url;

	/**
	 * Main aasf Instance
	 *
	 * Ensures only one instance of America_Ajax_Search_Filter is loaded or can be loaded.
	 *
	 * @since 1.0.0
	 * @static
	 * @return Main America_Ajax_Search_Filter instance
	 */
	public static function instance( $file = '', $version = '1.0.0' ) {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	} 


	function __construct( $file, $version ) {
		$this->_version = $version;
		$this->_token = 'aasf_plugin';
		
		// Load plugin environment variables
		$this->file = $file;
		$this->dir = dirname( $this->file );
		$this->assets_dir = trailingslashit( $this->dir ) . 'assets';
		$this->assets_url = esc_url( trailingslashit( plugins_url( '/assets/', $this->file ) ) );

		add_action( 'wp_head', array( $this , 'aasf_no_script' ) );
		add_action( 'wp_enqueue_scripts', array( $this , 'aasf_enqueue_scripts' ) );

		// add_filter( 'pre_get_posts', array($this,'iip_debug_query') );
		// add_filter( 'widget_text', 'shortcode_unautop');  -- allow shortcodes to work in text widgets
		// add_filter( 'widget_text', 'do_shortcode');

		add_shortcode( 'aasf', array($this, 'aasf_shortcode') );
	}

	/**
	 * Show filter submit button if javascript is turned off
	 * @return [type] [description]
	 */
	function aasf_no_script() {
		$output = '<noscript><style type="text/css"> .aasf-btn--submit { display:block; }</style></noscript>';
		echo $output;
	}

	function aasf_enqueue_scripts () {
		wp_register_style( 'frontend-css', $this->assets_url . 'css/frontend.css', array(), false, 'all' );
        wp_enqueue_style( 'frontend-css' );

        wp_register_script( 'frontend-js', $this->assets_url . 'js/frontend.js', array('jquery'), false, true );
        wp_enqueue_script( 'frontend-js' );

        wp_localize_script('frontend-js', 'aasf', array('ajaxurl' => admin_url('admin-ajax.php') ));
	}

	
	// for php version, will need to handle css display for selected item on page load/reload
	// add class to shortcode return
	// filterBy, label, layout (checkbox, radio, link), show results number, base result- cat, taxonomu, search result
	function aasf_shortcode( $atts ) {     // possible atts: header, position (top, side), all (show all results), show if empty--i.e. no results for 'about america', result set
		$merged_atts = shortcode_atts( array (
			'filter_by' => [],
			'label' => '',
			'layout' => 'url',
			'show_count' => false,
			'show_taxonomy_name' => true      // add a displayname? i.e. topics instead of categories
			), $atts );
		
		$output = $this->render( $merged_atts );

		//echo 'has_posts ' . has_posts();

		return $output;
	}

	function render( $atts ) {
		extract( $atts );
		
		$filters = preg_split( "/[\s,]+/", $filter_by );
		$options = array (
			'layout' => $layout,
			'show_count' => $show_count
		);

		$html = '';

		if ( $this->hasTaxonomyTerms($filters) ) {  // make sure that there are taxonomy terms present in any of the filters before we write out any html
			if( trim($label) ) {
				$html .= '<p>' . $label . '</p>';
			}
			$html .= '<ul>';
			foreach ( $filters as $filter ) {
				if( $terms = get_terms( $filter ) ) {
					if( $show_taxonomy_name ) {			// what is title? taxonomy name or something else?  Ma need to adjust pub custom post type, check that empty php string returns true
						$html .= '<li>' . $this->get_taxonomy_name( $filter );
						$html .= '<ul>' . $this->renderTerms( $terms, $options ) . '</ul>';
						$html .= '</li>';
					} else {
						$html .= $this->renderTerms( $terms, $options );
					}
				}
			}
			$html .= '</ul>';

			$html .= $this->renderFormWrapper( $html, $options );
		}
		return $html;
	}

	function get_taxonomy_name( $taxonomy ) {
		$tax = get_taxonomy( $taxonomy );
		return $tax->labels->name;
	}

	/**
	 * Check to make sure that ANY taxonomy exist for any filters
	 * @param  [type]  $taxonomies [description]
	 * @return boolean             has taxonomies?
	 */
	function hasTaxonomyTerms( $taxonomies ) {
		foreach ( $taxonomies as $taxonomy ) {
			if( get_terms( $taxonomy ) ) {     // if no terms  then  WP_Error object is returned ?? use is_wp_error
				return true;
			}
		}
		return false;
	}

	function renderTerms( $terms, $options ) {
		$count = $options['$show_count'];

		$html = '';
		switch ( $options['layout'] ) {
			case 'url':
				$html .= $this->renderUrl( $terms );
				break;
			case 'checkbox':
				$html .= $this->renderCheckbox( $terms, $count );
				break;
			case 'radio':
				$html .= $this->renderRadio( $term , $count );
				break;
			default:
				$html .= $term->name;
		}
		
		return $html;
	}

	function renderUrl( $terms ) {
		$html = '';
		
		foreach ( $terms as $term ) {
			$num = $this->getTermCount( $term->taxonomy, $term->name );

			if( $num ) {  // only show terms that have posts
				$query = ( is_category() ) ? "?category_name=" . get_query_var( 'category_name') : '';  // if category page, what about taxonomy page?
				$url = get_term_link( $term ) . $query;
				$html .= '<li><a href="' . $url . '">' . $term->name . '</a></li>';  // use esc_url($url) and sanitize_term?
			}
		}
		$html .= "<li><a href='" . get_site_url() . $query . "'>All</a></li>";

		return $html;
	}

	function renderCheckbox( $terms, $count) {
		$html = '';

		foreach ( $terms as $term ) {
			$id = $term->term_id;
			$name = $term->name;
			$tax = $term->taxonomy;
			$num = $this->getTermCount( $tax, $name );

			if( $num ) { // only show if has posts?
				$html .= '<li>';
				$html .= '<input id="' . $id .'" type="checkbox" name="' . $term->taxonomy . '" value="' . $name .'">';
				$html .= '<label for="' . $id . '">' . $name . '</label>';
				$html .= $num;
				$html .= '</li>';
			}
		}

		return $html;
	}

	function renderRadio( $terms, $count ) {
		$html = '';
		
		foreach ( $terms as $term ) {
		}
		return $html;
	}

	function renderFormWrapper( $html, $options ) {
		if( $options->layout != 'url ') { 
			$html .= '<form action="">' . $html . '<button class="aasf-btn--submit" type="submit">Filter</button>' . '</form>';
		}
		return $html;
	}

	// is this the most efficient way of doing this as could be hitting db numerous times
	// can we query the default loop instead?
	function getTermCount( $tax, $term ) {  
		$args = array();

		if( $s = get_query_var('s') ) {  // search param present?
			$args['s'] = $s;
		}

		if( $cat = get_query_var('category_name') ) {  // on category page? should check for other archive pages
			$args['category_name'] = $cat;
		}

		$args['tax_query'] = array(
			'tax_query' => array(
				'taxonomy' => $tax,
				'field'    => 'slug',
				'terms'    => $term
			)
		);
		$query = new WP_Query( $args );
		return $query->post_count;
	}


	function america_pre_posts( $query ) {

	}

	function debug_query () {
		global $wp_query;
		echo '<pre>';
		var_dump($wp_query->query_vars);
		echo '</pre>';
	}

} // end America_Ajax_Search_Filter class


