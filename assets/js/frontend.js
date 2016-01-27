jQuery(document).ready(function($) {
 
    var data = {},
        articles = $( 'main.content article' ),
        cbs = $( '#aasf-filter input' ),
        container;

     function fetchSearchData () {
 		$.ajax({
	        url: aasf.ajaxurl,
	        data: {
	            'action':'action',
	            'data' : data
	        },
	        success:function( data ) {
	            console.log( data );
	        },
	        error: function( err ) {
	            console.log( err );
	        }
	    });  
    }

    function bindUI () {
    	$( '.aasf-field input' ).change( function( evt ) {
            filterArticles( getClassesToFilterOn(cbs), topFilter );
    	});

    	$( '.aasf-tax-term a' ).click( function( event ) {
    		event.preventDefault();
            event.stopPropagation();
            event.stopImmediatePropagation();

            filterSearch(); 
    	});

        // stop default form submission
    }

    function initializeFilter ( selector ) {
        container = $( selector ).isotope({
            itemSelector: 'article',
            layoutMode: 'fitRows',
            animationEngine : 'css'
        });
    }

    function filterArticles( classes, filterFn ) {
        var filter = filterFn;
        container.isotope({ 
            filter: function () {
                return filter( $(this), classes );
            }
        });
    }

    function topFilter( el, col ) {
        var flag = true,
            cls;
         
        if( col ) {
            cls = el.attr('class').split(/\s+/);

            $.each( col, function( index, c ) {
                if( !(_.contains( cls, c)) ) {
                    flag = false;
                }
            });
        } else {
            flag = true;
        }
        return flag;
    }

    
    function getClassesToFilterOn ( els ) {
        var cls, el,
            showCls = [];
       
        $.each( els, function( index, cb ) {
            el =  $( cb );
            cls = el.attr('name') + '-' + el.val();
            if(el.is(':checked') ) {
                showCls.push( cls );            
            } 
        });

        return ( showCls.length) ? showCls : null;
    }

  

    function init() {
        bindUI();
        initializeFilter( '.content' );
    }

    init();

    //fetchSearchData();  this script should be loaded on localize
              
});

//post-15 publication type-publication status-publish has-post-thumbnail category-human-rights publication-type-books entry one-half pubs-search-post first