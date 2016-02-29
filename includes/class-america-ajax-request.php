<?php

// pass # of columns in via admin
class America_Ajax_Request {

    protected $tmpl_loader; 

    public function __construct() {

        add_action( "wp_ajax_nopriv_find-posts", array ( $this, 'fetch_posts' ) );
        add_action( "wp_ajax_find-posts",array( $this,'fetch_posts' ));
        
        // modify query for non js return
        add_action( "pre_get_posts", array( $this, 'modify_search_query'));

        $this->tmpl_loader = new America_Template_Loader( 'content.php', false, false );  
    }

    // process Ajax data and send response function boj_myplugin_process_ajax() {
    // check authority and permissions: current_user_can()
    // check intention: wp_verify_nonce()
    // process data sent by the Ajax request
    // echo data response that the Ajax function callback will process die();
    // esc attributes or incoming args
    //}

    public function fetch_posts () {
        $query_data = $_POST;
        $nonce = $query_data['aasfNonce'];

        if ( ! wp_verify_nonce( $nonce, 'aasf-ajax-post-nonce' ) ) {
            die ( 'Busted!');
        }
        
        //$query_vars = json_decode( stripslashes( $_POST['query_vars'] ), true );
        $paged = intval( $query_data['paged'] );
        $filters = $query_data['filters'];
        
        $args = array (
            'post_type'  => 'publication',  // needs to be sent in
            'posts_per_page' => 12,  // use 3 for testing -- fetch posts-per-page from intial load
            'tax_query' => $this->get_taxonomy_query( $filters ),
            'paged' => $paged,
            'post_status' => 'publish' 
        );

        if( isset($filters['s']) ) {
           $args['s'] = $filters['s'];
        }

        $qry = new WP_Query( $args );
        //$GLOBALS['wp_query'] = $posts;
        
        ob_start();
        
        if ( $qry->have_posts() ) {
            while ( $qry->have_posts() ) {
                $qry->the_post();
                $this->tmpl_loader->get_template_part( 'content' );
            }
        } else {
            echo '<div>There are no posts.</div>';
        }

        // this is repeated as it also appears in the main filter class, consolidate
        echo '<div class="pages-filter">';
        echo paginate_links( array (  
            'base'       => home_url( '/%_%' ),
            'format'     => 'page/%#%/',
            'current'    =>  $paged,
            'total'      =>  $qry->max_num_pages,
            'prev_text'  => __('« Previous Page'),
            'next_text'  => __('Next Page »'),
            'mid_size'   =>  3,
            'type'       =>  'list',
           // 'add_args'   =>  array('s' => '')
        ) );
        echo '</div>';
      
        echo ob_get_clean();
    
        wp_reset_postdata();
      
        wp_die();
    }
 
    // public function aasf_filter () {
       
    //     $args = array (
    //         'post_type'      => 'publication',
    //         'posts_per_page' => -1
    //     );

    //     $posts = get_posts($args);

    //     $result = array();
    //     foreach( $posts as $post ) {
    //         $data = array();
    //         $data['post_title'] = $post->post_title;
    //         $data['post_excerpt'] = $post->post_excerpt;
    //         $data['post_taxonomies'] = $this->get_taxonomies( $post );
    //         $data['thumbnail'] = $this->get_thumbnail( $post );

    //         $result[] = $data;
    //     }

    //     echo json_encode( $result , JSON_PRETTY_PRINT );  // JSON_NUMERIC_CHECK
    //     wp_die();
    // }

    function get_categories( $post_id ) {
        $cats = array();
        
        foreach( wp_get_post_categories($post_id) as $c ) {
            $cat = get_category($c);
            $cats[] = $cat->name;
        }
        return implode( ',', $cats );
    }

    function get_taxonomies( $post ) {
        $result = array();

        $taxonomies = get_object_taxonomies( $post );
        
        foreach( $taxonomies as $taxonomy ) {
            $terms = wp_get_post_terms( $post->ID, $taxonomy );
            if( sizeof($terms) ) {
                $result[$taxonomy] = array();

                foreach( $terms as $term ) {
                    $result[$taxonomy][] = array ( 
                        'name' => $term->name,
                        'slug' => $term->slug,
                        'link' => get_term_link($term)
                    );
                }
            }
        }
        return $result;
    }

    // function get_thumbnail( $post ) {
    //     $id = get_post_thumbnail_id( $post->ID) ;
    //     return wp_get_attachment_url( $id );
    // }

   
    // http://10up.com/blog/2013/wordpress-mixed-relationship-taxonomy-queries/ 
    // TODO:  Deal with 'OR' & 'AND' operators
    public function modify_search_query( $query ) {  
       if( $query->is_main_query() && $query->is_search ) {
            
            $req_method = $_SERVER['REQUEST_METHOD'];
            $filters = ( $req_method == 'POST' ) ? $_POST : $_GET;
            $taxquery = $this->get_taxonomy_query( $filters ); 
          
            $query->set( 'post_status', 'publish' ); 
            $query->set( 'tax_query', $taxquery );
        } 
      
        return $query;
    }

    function get_taxonomy_query( $filters ) {
        $taxquery = array (
            'relation' => 'AND'
        );

        foreach ( $filters as $name => $value ) {
             if( $name != 's' ) {
                 //$operator = $_POST[$name . '-filter'];
                 //$operator = ( $operator == 'AND' ) ? 'AND' : 'IN';

                 $taxquery[] = array (
                    'taxonomy' => $name,
                    'terms' => $this->get_tt_ids( $name, $value ),
                    'field' => 'term_taxonomy_id',
                    'operator' => 'IN',
                    'include_children' => false
                 );
             }
        }

        return $taxquery;
    }
   
    function get_tt_ids( $taxonomy, $terms ) {
        $ids = array();
   
        foreach ( $terms as $term ) {
            $id = get_term_by( 'slug', $term, $taxonomy )->term_taxonomy_id;
            if( $id ) {
                $ids[] = $id;
            }
        }
        return $ids;
    }

} // end of  America_Ajax_Request

$aasf_request = new America_Ajax_Request ();

