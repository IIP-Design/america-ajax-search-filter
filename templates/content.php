<?php
/**
 * The default template for displaying post content
 * TODO: This file should go in the theme.  Default template should be generic
 */
?>

<article id="post-<?php the_ID(); ?>" <?php post_class(); ?>>
    <div class="entry-content">
        <div class="publication-featured-image">
            <?php 
                if ( has_post_thumbnail() ) {
                    $image = get_the_post_thumbnail( get_the_ID(), 'post-thumbnail', array( 'alt' => the_title_attribute( 'echo=0' ) ));
                } else {
                    $image = 'http://dummyimage.com/150x188/ddd/aaa.png&text=placeholder';
                }
                echo sprintf( '<a href="%s" rel="bookmark">', esc_url( get_permalink() ) ) . $image . '</a>';
            ?>
        </div>
        <div class="publication-content">
            <?php
                if ( is_single() ) :
                    the_title( '<h2 class="entry-title">', '</h2>' );
                else :
                    the_title( sprintf( '<h2 class="entry-title"><a href="%s" rel="bookmark">', esc_url( get_permalink() ) ), '</a></h2>' );
                endif;
            ?>
            <div>
                <?php 
                    if( taxonomy_exists('publication_type') ) { 
                        $formats = get_the_term_list( get_the_ID(), 'publication_type', '<div><span class="aasf-label">Format:</span> ', ', ', '</div>' );
                        if( $formats ) {
                            echo $formats;
                        }
                    } 
                    the_excerpt(); 
              
                    $cats = get_the_term_list( get_the_ID(), 'category', '<div><span class="aasf-label">Subject:</span> ', ', ', '</div>' );
                    if( $cats && !$GLOBALS['isCategory'] ) { 
                        echo $cats;
                    }
                       
                ?>
            </div>
        </div>
       
    </div>
   
    
</article><!-- #post-## -->