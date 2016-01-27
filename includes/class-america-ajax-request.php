<?php


class America_Ajax_Request {

    public function __construct() {

        add_action( "wp_ajax_nopriv_action", array ( $this, 'aasf_filter' ) );
        add_action( "wp_ajax_action",array( $this,'aasf_filter'));
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
        return implode(',',$cats);
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

}

new America_Ajax_Request ();

