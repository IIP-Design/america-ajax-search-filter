<?php


class America_Ajax_Request {

    public function __construct() {

        add_action( "wp_ajax_nopriv_action", array ( $this, 'aasf_filter' ) );
        add_action( "wp_ajax_action",array( $this,'aasf_filter'));
        
        // modify query for non js return
        add_action( "pre_get_posts", array( $this, 'modify_search_query'));
    }

    public function aasf_filter () {
       
        $args = array(
            'post_type'      => 'publication',
            'posts_per_page' => -1
        );

        $posts = get_posts($args);

        $result = array();
        foreach( $posts as $post ) {
            $data = array();
            $data['post_title'] = $post->post_title;
            $data['post_excerpt'] = $post->post_excerpt;
            $data['post_taxonomies'] = $this->get_taxonomies( $post );
            $data['thumbnail'] = $this->get_thumbnail( $post );

            $result[] = $data;
        }

        echo json_encode( $result , JSON_PRETTY_PRINT );  // JSON_NUMERIC_CHECK
        wp_die();
    }

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

    function get_thumbnail( $post ) {
        $id = get_post_thumbnail_id( $post->ID) ;
        return wp_get_attachment_url( $id );
    }

    
     // http://10up.com/blog/2013/wordpress-mixed-relationship-taxonomy-queries/ 
     // TODO:  Deal with 'OR' & 'AND' operators
    public function modify_search_query( $query ) {  
       if( $query->is_main_query() && $query->is_search ) {
            
             $req_method = $_SERVER['REQUEST_METHOD'];
             $arr = ( $req_method == 'POST' ) ? $_POST : $_GET;
             
             $taxquery = array (
                 'relation' => 'AND'
             );

             foreach ( $arr as $name => $value ) {
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
             
             $query->set( 'post_status', 'publish' ); 
             $query->set( 'tax_query', $taxquery );
            
        } 
      
        return $query;
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

