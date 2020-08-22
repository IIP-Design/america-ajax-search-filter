<?php

 
//* Prevent loading this file directly
defined( 'ABSPATH' ) || exit;

class America_Ajax_Search_Filter {

	private static $_instance = null;

	private $_version;
	private $_token;
	private $_show_filters = true;   // only show url filters if a more than 1 taxonomy term has posts

	public $settings = null;		

	public $dir;
	public $assets_dir;
	public $assets_url;

	/**
	 * Main aasf Instance
	 *
	 * Ensures only one instance of America_Ajax_Search_Filter is loaded or can be loaded
	 *
	 * @since 1.0.0
	 * @static
	 * @param string $file plugin path
	 * @param number $version version number
	 * 
	 * @return Main America_Ajax_Search_Filter instance
	 */
	public static function instance( $file = '', $version = '1.2.1' ) {

		if ( is_null( self::$_instance ) ) {
			self::$_instance = new self( $file, $version );
		}
		return self::$_instance;
	} 

	/**
	 * Class constructor
	 * @param string $file plugin path
	 * @param number $version version number
	 *
	 * @return  void
	 */
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

		add_action( 'genesis_after_loop', array( $this,'pagination') );

		// allow shortcodes to work in text widgets
		// add_filter( 'widget_text', array( $this, 'shortcode_unautop' ));  
		// add_filter( 'widget_text', array( $this, 'do_shortcode') );

		add_shortcode( 'aasf', array($this, 'aasf_shortcode') );
	}

	/**
	 * Show filter submit button if javascript is turned off
	 * @return void 
	 */
	function aasf_no_script() {
		$output = '<noscript><style type="text/css"> .aasf-btn--submit { display:block; }</style></noscript>';
		echo $output;
	}

	/**
	 * Load scripts
	 * @return void
	 */
	function aasf_enqueue_scripts () {
		if( is_search() || is_archive() ) {
			wp_register_style( 'frontend-css', $this->assets_url . 'dist/frontend.min.css', array(), false, 'all' );
			wp_enqueue_style( 'frontend-css' );

			wp_register_script( 'images-loaded', 'https://npmcdn.com/imagesloaded@4.1/imagesloaded.pkgd.min.js', array(), false, true );
			//wp_enqueue_script( 'images-loaded' );

			wp_register_script( 'isotope', 'https://cdnjs.cloudflare.com/ajax/libs/jquery.isotope/2.2.2/isotope.pkgd.min.js', array(), false, true );
			//wp_enqueue_script( 'isotope' );

			wp_register_script( 'frontend-js', $this->assets_url . 'dist/frontend.min.js', array('jquery'), false, true );
			wp_enqueue_script( 'frontend-js' );

			wp_enqueue_script( 'underscore' );

			// Set up variables to pass to the client side script
			wp_localize_script('frontend-js', 'aasf', array (
					'ajaxurl' => admin_url( 'admin-ajax.php' ), 
					'aasfNonce' => wp_create_nonce( 'aasf-ajax-post-nonce' ),
					'container' => '.content',  // TODO: eventually fetch from «
					'itemSelector' => 'article',
					'isCategory' => is_category()
 				)
			);  // TODO: localize after init data return
		}
	}
	
	/**
	 * Shortcode entry method - call supporting methods
	 * @param  array $atts Configuration variables added in via shortcode:
     *               filter_by   			taxonomy and layout to filter by (i.e. category|checkbox), layout can be either url, checkbox or radio
     *               label 					show label (i.e. 'Filter by')
     *               show_count				show the number of items in filter set
     *               show_taxonomy_name 	show the taxonomy name
	 * @example [aasf filter_by="publication_type|checkbox, category|url" show_taxonomy_name="true", label="Filter by:"]                 
	 * @return string Generated html to display
	 */
	function aasf_shortcode( $atts ) {    
		$merged_atts = shortcode_atts( array (
			'filter_by' => [],
			'label' => '',
			'show_count' => true,
			'show_taxonomy_name' => false      // add a displayname? i.e. topics instead of categories
			), $atts );
		
		$output = $this->render( $merged_atts );

		return $output;
	}

	/**
	 * Parses configuration variables and renders applicable html
	 * @param  array $atts  Configuratin variables
	 * 
	 * @return string Generated html to display
	 */
	function render( $atts ) {
		extract( $atts );

		$filters = preg_split( "/[\s,]+/", $filter_by );
		$options = array (
			'show_count' => $show_count,
			'show_taxonomy_name' => $show_taxonomy_name
		);

		$html = '<div class="aasf-wrapper">';

		// make sure that there are taxonomy terms present in any of the filters before we write out any html
		if ( $this->has_taxonomy_terms($filters) ) {  
			if( trim($label) ) {
				$html .= '<span class="aasf-label">' . $label . '</span>';
			}

			$html .= '<ul class="aasf-terms-parent">';
			foreach ( $filters as $filter ) {	
				$f = explode( '|', $filter );   // get layout and filter
				$options['view'] = $f[1];		// store view in options obj for use in other methods
				
				
 				// check to see if there are terms available for the taxonomy and if so display
 				if( $terms = get_terms( $f[0] ) ) {
					if( $show_taxonomy_name ) {			
						$html .= '<li class="aasf-tax-name"><a class="aasf-trigger" href="#"><div class="aasf-tax-label aasf-down">' . $this->get_taxonomy_name( $f[0] ) . '</div></a>';
						$html .= '<ul class="aasf-tax-terms">' . $this->render_terms( $terms, $options ) . '</ul>';
						$html .= '</li>';
					} else {
						$html .= $this->render_terms( $terms, $options );
					}
				}
			}
			$html .= '</ul></div>';
			
			// if we are on a search page get search query and store in hidden field for use when js is disabled
			if( is_search() ) {
				$html .= '<input type="hidden" name="s" value="' . get_query_var('s') .'">';
			}
			
			// wrap complete html in a form 
			$html = $this->render_form_wrapper( $html, $options );
		}

		// only show filters if there are multiple taxonomies
		return ( $this->_show_filters ) ? $html : '';
	}

	/**
	 * @param  string $taxonomy 
	 */
	function get_taxonomy_name( $taxonomy ) {
		$tax = get_taxonomy( $taxonomy );
		return $tax->labels->name;
	}

	/**
	 * Check to make sure that ANY taxonomy exist for any filters
	 * @param  array  $taxonomies  array of taxonomies
	 * @return boolean             are there taxonomies?
	 */
	function has_taxonomy_terms( $taxonomies ) {
		foreach ( $taxonomies as $taxonomy ) {
			if( get_terms( $taxonomy ) ) {     // if no terms  then  WP_Error object is returned ?? use is_wp_error
				return true;
			}
		}
		return false;
	}

	/**
	 * Sends terms to applicable method based on the layout configuration  var
	 * @param   array $terms    taxonomy terms to render
	 * @param   array $options  configuration variables
	 * @return  string          html for terms
	 */
	function render_terms( $terms, $options ) {
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

	/**
	 * Renders terms as a urls
	 * @param   array $terms    taxonomy terms to render
	 * @param   array $options  configuration variables
	 * @return  string          html for url
	 */
	function render_url( $terms, $options ) {
		$html = '';
		$cat =  get_query_var( 'category_name'); // TODO: assumes category, update to be more generic, i.e. taxonomy page
		$all = '';
		$num_terms = count($terms); 
		$num_terms_with_posts = 0; 
		
		foreach ( $terms as $term ) {
	
			$num = $this->get_term_count( $term->taxonomy, $term->name );
			
			$data = 'category__' . $cat . ',';   // TODO: assumes category but need to parse/analyize url
			$data .= $term->taxonomy . '__' . $term->slug;
			
			// if terms slug is present in url then it is the active filter so display as active
			$cls = ( strpos($_SERVER['REQUEST_URI'], $term->slug ) !== false ) ? 'active-filter' : '';  

			if( $num ) {  // only show terms that have posts
				$num_terms_with_posts++;
				$query = ( is_category() ) ? "?category_name=" . $cat : '';  // if category page, what about taxonomy page?
				$url = get_term_link( $term ) . $query;
				$html .= '<li class="aasf-tax-term"><a href="' . esc_url($url) . '" data-terms="' . $data . '" class="' . $cls . '">' . $term->name . 's</a></li>'; 
			}

			$all .= $data . ',';
		}

		$all = implode(',', array_unique(explode( ',', $all )));

		// we only need to show the filter menu if there are more than one taxonomy terms with posts
		if( $num_terms_with_posts > 1 ) {
			$html .= '<li class="aasf-tax-term"><a href="' . get_site_url() . $query . '" data-terms="'. $all . '" class="active-filter">All</a></li>';
		} else {
			$this->_show_filters = false;
		}

		return $html;
	}

	/**
	 * Renders terms as either checkboxes or radio buttons
	 * @param   array $terms    taxonomy terms to render
	 * @param   array $options  configuration variables
	 * @return  string          html for inputs
	 */
	function render_inputs( $terms, $options ) {   // need full $options?
		$totalTermsInTax = 0;  // total terms in a taxonomy

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
				$html .= '<label for="' . $id . '">' . $name . '</label>';
				if( $options['show_count'] ) {
					$html .= '<div class="aasf-num">' . $num . '</div>';
				}
				$html .= '</div></li>';
			}
			$totalTermsInTax += $num;
		}

		// $html .= '<input type="hidden" name="' . $tax . '-filter" value="' . $type .'">';

		// if( $view == 'radio' ) {  // add shortcode to trn this off?
		// 	$html .= $this->add_input_all( $tax, $totalTermsInTax, $options['show_count'] );
		// }

		return $html;
	}
	
	/**
	 * Checks to see if a term is checked. Uses POST for non js 
	 * @param  [type]  $tax  [description]
	 * @param  [type]  $slug [description]
	 * @return boolean       [description]
	 */
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

	/**
	 * Add an all link.  Currently not beig used
	 */
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

	/**
	 * Wrap generated html in a form
	 * @param  string $html    generated html
	 * @param  array  $options config vars
	 * @return string          generated html wrapped in form
	 */
	function render_form_wrapper( $html, $options ) {
		if( $options['view'] != 'url') { 
			$html = '<form id="aasf-filter" method="post" action="">' . $html . '<button class="aasf-btn--submit" type="submit">Filter</button>' . '</form>';
		}
		return $html;
	}

	/**
	 * Returns the number of posts in taxonomy term
	 * Notes: Is this the most efficient way of doing this as could be hitting db numerous times. Can we query the default loop instead?
	 * @param  string $tax   taxonomy
	 * @param  string $term  term
	 * @return number        num posts
	 */
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

		// TODO:  Term count is incorrect due to sub categories
		return $query->post_count;
	}


	/**
	 * Parse request vars to add term/tax id as an argument to assist in pagination
	 * @param  [type] &$ids Passed by reference so popluates array in calling method
	 * @return void      
	 */
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
				$str_ids = implode(' ', $ids );  	// what is this for?
				
				echo '<div class="pages-filter">';
				echo paginate_links( array (
					//'base'	 => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
					'format'	 =>  $format,
					'current'	 =>  max( 1, get_query_var('paged') ),
					'prev_text'  => __('« Previous'),
					'next_text'  => __('Next »'),
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






