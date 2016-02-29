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
	 * Ensures only one instance of America_Ajax_Search_Filter is loaded or can be loaded.d
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

		// genesis actions need to be removed so plugin can be used w/o
		//add_action( 'genesis_before_footer', array( $this,'pagination') );
		add_action( 'genesis_after_loop', array( $this,'pagination') );

		//add_filter( 'widget_text', array( $this, 'shortcode_unautop' ));  // allow shortcodes to work in text widgets
		//add_filter( 'widget_text', array( $this, 'do_shortcode') );

		add_shortcode( 'aasf', array($this, 'aasf_shortcode') );
	}

	/**
	 * Show filter submit button if javascript is turned off
	 */
	function aasf_no_script() {
		$output = '<noscript><style type="text/css"> .aasf-btn--submit { display:block; }</style></noscript>';
		// TESTING $output = '<style type="text/css"> .aasf-btn--submit { display:block; }</style>';
		echo $output;
	}

	function aasf_enqueue_scripts () {
		if( is_search() || is_archive() ) {
			wp_register_style( 'frontend-css', $this->assets_url . 'css/frontend.css', array(), false, 'all' );
			wp_enqueue_style( 'frontend-css' );

			wp_register_script( 'images-loaded', 'https://npmcdn.com/imagesloaded@4.1/imagesloaded.pkgd.min.js', array(), false, true );
			wp_enqueue_script( 'images-loaded' );

			wp_register_script( 'isotope', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.isotope/2.2.2/isotope.pkgd.min.js', array(), false, true );
			wp_enqueue_script( 'isotope' );

			wp_register_script( 'frontend-js', $this->assets_url . 'js/frontend.js', array('jquery'), false, true );
			wp_enqueue_script( 'frontend-js' );

			wp_enqueue_script( 'underscore' );

			wp_localize_script('frontend-js', 'aasf', array (
					'ajaxurl' => admin_url('admin-ajax.php'), 
					'aasfNonce' => wp_create_nonce( 'aasf-ajax-post-nonce' ),
					'container' => '.content',  // eventually fetch from «
					'itemSelector' => 'article'
				)
			);  // localize after init data return
		}
	}

	
	// for php version, will need to handle css display for selected item on page load/reload
	// add class to shortcode return
	// filterBy, label, layout (checkbox, radio, link), show results number, base result- cat, taxonomy, search result
	function aasf_shortcode( $atts ) {     // possible atts: header, position (top, side), all (show all results), show if empty--i.e. no results for 'about america', result set
		$merged_atts = shortcode_atts( array (
			'filter_by' => [],
			'label' => '',
			'show_count' => true,
			'show_taxonomy_name' => false      // add a displayname? i.e. topics instead of categories
			), $atts );
		
		$output = $this->render( $merged_atts );

		return $output;
	}

	function render( $atts ) {
		extract( $atts );

		$filters = preg_split( "/[\s,]+/", $filter_by );
		$options = array (
			'show_count' => $show_count,
			'show_taxonomy_name' => $show_taxonomy_name
		);

		$html = '';

		if ( $this->has_taxonomy_terms($filters) ) {  // make sure that there are taxonomy terms present in any of the filters before we write out any html
			if( trim($label) ) {
				$html .= '<span class="aasf-label">' . $label . '</span>';
			}

			$html .= '<ul class="aasf-terms-parent">';
			foreach ( $filters as $filter ) {	
				$f = explode( '|', $filter );   // get view, search type
				$options['view'] = $f[1];
				//$options['search_type'] = $f[2];
				if( $terms = get_terms( $f[0] ) ) {
					if( $show_taxonomy_name ) {			// what is title? taxonomy name or something else?  May need to adjust pub custom post type, check that empty php string returns true
						$html .= '<li class="aasf-tax-name"><div class="aasf-tax-label">' . $this->get_taxonomy_name( $f[0] ) . '</div>';
						$html .= '<ul class="aasf-tax-terms">' . $this->render_terms( $terms, $options ) . '</ul>';
						$html .= '</li>';
					} else {
						$html .= $this->render_terms( $terms, $options );
					}
				}
			}
			$html .= '</ul>';
			if( is_search() ) {
				$html .= '<input type="hidden" name="s" value="' . get_query_var('s') .'">';
			}
			
			$html = $this->render_form_wrapper( $html, $options );
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
	function has_taxonomy_terms( $taxonomies ) {
		foreach ( $taxonomies as $taxonomy ) {
			if( get_terms( $taxonomy ) ) {     // if no terms  then  WP_Error object is returned ?? use is_wp_error
				return true;
			}
		}
		return false;
	}

	function render_terms( $terms, $options ) {
		$count = $options['show_count'];

		$html = '';
		switch ( $options['view'] ) {
			case 'url':
				$html .= $this->render_url( $terms, $options );
				break;
			case 'checkbox':
			case 'radio':
				$html .= $this->render_inputs( $terms, $options );
				break;
			default:
				$html .= $term->name;
		}
		
		return $html;
	}

	function render_url( $terms, $options ) {
		$html = '';
		$cat =  get_query_var( 'category_name'); // assuming category, update to be more generic, i.e. taxonomy page
		$all = '';
		
		foreach ( $terms as $term ) {
	
			$num = $this->get_term_count( $term->taxonomy, $term->name );
			$data = 'category__' . $cat . ',';   // assuming category but need to analyize url
			$data .= $term->taxonomy . '__' . $term->slug;
			$cls = ( strpos($_SERVER['REQUEST_URI'], $term->slug ) !== false ) ? 'active-filter' : '';

			if( $num ) {  // only show terms that have posts
				$query = ( is_category() ) ? "?category_name=" . $cat : '';  // if category page, what about taxonomy page?
				$url = get_term_link( $term ) . $query;
				$html .= '<li class="aasf-tax-term"><a href="' . esc_url($url) . '" data-terms="' . $data . '" class="' . $cls . '">' . $term->name . 's</a></li>'; 
			}

			$all .= $data . ',';
		}

		$all = implode(',', array_unique(explode( ',', $all )));
		$html .= '<li class="aasf-tax-term"><a href="' . get_site_url() . $query . '" data-terms="'. $all . '" class="active-filter">All</a></li>';

		return $html;
	}

	function render_inputs( $terms, $options ) {   // need full $options?
		$totalTermsInTax = 0;  // total terms in taxonomy

		$html = '';

		foreach ( $terms as $term ) {
			$id = $term->term_id;
			$name = $term->name;
			$slug = $term->slug;
			$tax = $term->taxonomy;
			$num = $this->get_term_count( $tax, $name );		// only show if has posts?
			$view = $options['view'];
			$checked = ( $this->is_checked( $tax, $slug) ) ? 'checked' : '';  
			//$type = ( $options['search_type'] == 'OR' ) ? 'OR' : 'AND';

			if( $name != 'Uncategorized') { 			// only show if has posts?
				$html .= '<li class="aasf-tax-term">';
				$html .= '<div class="aasf-field"><input id="' . $id .'" type="' . $view . '" name="' . $tax . '[]" value="' . $slug . '" ' . $checked  . ' rel="' . $tax .  '">'; //[] breaking js
				$html .= '<label for="' . $id . '">' . $name . '</label></div>';
				if( $options['show_count'] ) {
					$html .= '<div class="aasf-num">' . $num . '</div>';
				}
				$html .= '</li>';
			}
			$totalTermsInTax += $num;
		}

		// $html .= '<input type="hidden" name="' . $tax . '-filter" value="' . $type .'">';

		// if( $view == 'radio' ) {  // add shortcode to trn this off?
		// 	$html .= $this->add_input_all( $tax, $totalTermsInTax, $options['show_count'] );
		// }

		return $html;
	}
	
	function is_checked ( $tax, $slug ) {
		$req_method = $_SERVER['REQUEST_METHOD'];
		
		if( $req_method == 'POST' ) {
			if( isset($_POST[$tax]) ) {
				foreach ( $_POST[$tax] as $term ) {
					if( $slug == $term ) {
						return true;
					}
				}
			}
		} else if ( $req_method == 'GET' ) {
			if( isset($_GET[$tax]) ) {
				foreach ( $_GET[$tax] as $term ) {
					if( $slug == $term ) {
						return true;
					}
				}
			} 
		}
        return false;
    }

	function add_input_all( $tax, $num, $show ) {
		$html = '';

		$html .= '<li class="aasf-tax-term" data-tax="' . $tax . '" data-term="All">';
		$html .= '<div class="aasf-field"><input id="' . $tax . '" type="radio" name="' . $tax . '" value="all">';
		$html .= '<label for="' . $tax . '">All</label></div>';
		if( $show ) {
			$html .= '<div class="aasf-num">' . $num . '</div>';
		}
		$html .= '</li>';

		return $html;
	}

	function render_form_wrapper( $html, $options ) {
		if( $options['view'] != 'url') { 
			$html = '<form id="aasf-filter" method="post" action="">' . $html . '<button class="aasf-btn--submit" type="submit">Filter</button>' . '</form>';
		}
		return $html;
	}

	// is this the most efficient way of doing this as could be hitting db numerous times
	// can we query the default loop instead?
	function get_term_count( $tax, $term ) {  
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


	function parse_request_vars( &$ids ) {
		if( is_search() || is_archive() ) {
    		 if( !empty($_POST) ) {
    		 	foreach ($_POST as $tax => $terms ) { 
    		 		if( taxonomy_exists($tax) ) {
    		 			$ids[$tax] = array();
    		 			foreach ( $terms as $term ) { 
    		 				$ids[$tax][] = $term;
    		 			}
    		 		}
    		 	}
    		 }
    	}
    }
    
	/**
	 * Write out pagination to include selected filters for query args
	 * @return void
	 */
	function pagination() {
		global $wp_query;
		
		// this needs to be fixed as other archive types may be used
		if( $wp_query->is_search() || $wp_query->is_category() ) {  
			$total = $wp_query->max_num_pages;
			$big = 999999999; // need an unlikely integer
			if( $total > 1 )  {
				if( !$current_page = get_query_var('paged') )
					$current_page = 1;
				if( get_option('permalink_structure') ) {
					$format = 'page/%#%/';
				} else {
					$format = '&paged=%#%';
				}

				$ids = array();
				$this->parse_request_vars( $ids );  // what if no POST?
				$str_ids = implode(' ', $ids );
				
				echo '<div class="pages-filter">';
				echo paginate_links( array (
					//'base'	 => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
					'format'	 =>  $format,
					'current'	 =>  max( 1, get_query_var('paged') ),
					'prev_text'  => __('« Previous Page'),
					'next_text'  => __('Next Page »'),
					'total' 	 =>  $total,
					'mid_size'	 =>  3,
					'type' 		 =>  'list',
					'add_args'	 =>  $ids
 				) );
 				echo '</div>';
			}
		}
	}
} // end America_Ajax_Search_Filter class






