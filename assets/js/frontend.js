jQuery(document).ready(function($) {
    'use strict';
 
    var data = {},
        opts = {
            'container': 'content',
            'itemSelector' : 'article'
        },
        xhr = null,
        cueRequest = null,  // local variable?
        filters = null,
        $container, $loader,
        $loaderHolder = $( '.content' ).parent(); // element to which to attach loading animation

    function fetchPosts ( filters, paged ) {
        xhr = $.ajax({
	        url: aasf.ajaxurl,
            type: 'post',
	        data: {
	            action : 'find-posts',
                aasfNonce : aasf.aasfNonce,
                isCategory: aasf.isCategory,
	            filters : filters,
                paged: paged
	        },
            beforeSend: function () {
                loaderShow();
                // disable checkboxes??
                //$(document).scrollTop();
            },
	        success:function( data ) {
                // if xhr still executing due to multiple clicks do not update until last req is complete
                if( xhr.readyState == 4 ) {
                    $('.content').html( data );
                    loaderHide();
                    bindUI();  // need to verify whether listeners are reemoved with DOM el
                }
	        },
	        error: function( err ) {
	            $container.html( '<p>There has been an error</p>' );
                loaderHide();
	        }
	    });  
    }

    // TODO: cache jQuery references
    function cacheElements() { 

    }

    function sendRequest( filters, page ) {
        var params = getQueryParams(),
            searchValue = $('input[type="search"]').val();
        
        if( params && params.hasOwnProperty("s") ) {
            filters['s'] = ( searchValue ) ? searchValue : params['s'];
        }

        fetchPosts ( filters, page );
    }

    function cancelSubmisson( evt ) {
        evt.preventDefault();
        evt.stopPropagation();
        evt.stopImmediatePropagation();
    }

    function bindSearchFilterMenu () {

        $( '.aasf-trigger' ).click( function( evt ) {
            var li = $(this).closest('li'),
                ul = li.find( 'ul' ),
                label = $(this).find( 'div' );

            ul.toggle();
            if(  ul.is(':visible') ) {
                label.removeClass('aasf-left');
                label.addClass('aasf-down');
            } else {
                label.removeClass('aasf-down');
                label.addClass('aasf-left');
            }
        }); 

    }

    function bindUI () {

        if( !cueRequest ) {
            cueRequest = _.debounce( function( filters, page ) {  
                sendRequest( filters, page );
            }, 500);
        }

        $( '.aasf-field input' ).change( function() {
            cueRequest( getFiltersFromInputs(), 1 );
        });

    	// $( '.aasf-field input' ).change( function( evt ) {
     //        //$els = $( '#aasf-filter input' );
     //        // update classes to reflect current selections??
     //        //filterArticles( getClassesFromInputsToFilterOn($els), filterWithClasses );
     //        //var cls = getClassesFromInputsToFilterOn( $els );

     //        //sendRequest();

     //        //console.dir(cls);
     //        // fetchPosts();
          
     //        // trigger ajax call
    	// });

    	$( '.aasf-tax-term a' ).click( function ( evt ) {
            cancelSubmisson ( evt );

            // set active state on clicked link
            var $link = $(evt.target),
                $links = $( 'a[data-terms]' ), 
                terms  = $link.data( "terms" );
            
            $links = $( 'a[data-terms]' ).removeClass('active-filter');
            $link.addClass('active-filter');

            cueRequest( getFiltersFromAttr(terms), 1 );

            // update classes to reflect current selections
            // filterArticles( getClassesFromLinkToFilterOn( $(this) ), filterWithClasses );

            // trigger ajax call 
    	});

        $( '.pages-filter a.page-numbers' ).click( function( evt ) {
            cancelSubmisson ( evt );
            
            var url = $(evt.target).attr('href'),
                page = url.match(/\/page\/(\d+)\//),  // assumes /page/ format and not &paged=
                $el,
                terms, filters;

            // this is not a scalable way to do this
            // if .active-filter is present, assume taxonomy links 
            $el = $( '.active-filter' );
           
            if( $el.length ) { 
                filters = getFiltersFromAttr( $el.data( "terms" ) );
            } 
            
            // if .aasf-tax-terms is present, assume taxonomy inputs 
            else if( $('.aasf-tax-terms').length ) {
                filters = getFiltersFromInputs();
            
            // assume there are no filters present, need to figure out query from url
            } else {
                 filters = getFiltersFromUrl();
            }
            
            page = ( page ) ? page[1] : 1;
            cueRequest( filters, page );    

            // filterArticles( getClassesFromLinkToFilterOn( $(this) ), filterWithClasses );
           
        });

        // stop default form submission?    http://publications.america.dev/page/2/?s
    }

    function getFiltersFromUrl() {
        var filters = {},
            cat = location.pathname.split('/'), 
            arr =  cat.filter( function(item) {
                return item.trim() !== '';
            });

        filters[arr[0]] = [];
        filters[arr[0]].push(arr[1]);

        return filters;
    }

    function getFiltersFromAttr( terms ) {
        var filters = {},
            taxonomies = terms.split( ',' ),
            term;

        _.each( taxonomies, function( taxonomy ) {
            term = taxonomy.split('__');

            if( !filters[term[0]] ) {
                filters[term[0]] = [];
            }
            filters[term[0]].push(term[1]);
        });
       
        return filters;
    }

    function getFiltersFromInputs() {
        var filters = {};
        var $inputs = $( '.aasf-field input:checked' );
       
        _.each( $inputs, function( input ) {     // need to handle urls as well
            var taxonomy = $(input).attr('rel'); 
            var term =  $(input).val();

            if( !filters[taxonomy] ) {
                filters[taxonomy] = [];
            }
            filters[taxonomy].push( term );
        });

        return filters;
    }

    function getQueryParams() {
        var params = {}, i;
        
        $.each( document.location.search.substr(1).split('&'), function( c, q ) {
            if( q ) {
                i = q.split('=');
                params[i[0].toString()] = i[1].toString();
            }
        });

        return params;
    }

    function initializeFilter () {
        opts = _.extend( opts, aasf );

        $container = $( opts.container ).isotope({
            itemSelector: opts.itemSelector,
            layoutMode: 'fitRows'
        });

        // give images time to load for proper layout
        $container.imagesLoaded().progress( function() {
          $container.isotope( 'layout' );
        });
    }

    function filterArticles( classes, filterFn ) {
        var filter = filterFn;
        $container.isotope({ 
            filter: function () {
                return filter( $(this), classes );
            }
        });
    }

    function filterWithClasses( el, collection ) {
        var flag = true,
            cls;
         
        if( collection ) {
            cls = el.attr( 'class' ).split( /\s+/ );

            $.each( collection, function( index, col ) {  // use regular expression
                if( !(_.contains( cls, col)) ) {
                    flag = false;
                }
            });
        } 
        return flag;
    }
    
    function getClassesFromInputsToFilterOn ( $els ) {
        var cls, el, tax,
            showCls = [];
       
        $.each( $els, function( index, cb ) {
            el =  $( cb );
            tax =  el.attr('name').replace('[]', '');
            cls = tax + '-' + el.val();
            if( el.is(':checked') ) {
                showCls.push( cls );            
            } 
        });

        return ( showCls.length ) ? showCls : null;
    }

    function getClassesFromLinkToFilterOn ( $el ) {
        var showCls = $el.data( 'terms' ).split( ',' ); 
        return ( showCls.length ) ? showCls : null;
    }


    function addLoadingAnimation() {
        $loaderHolder.append( '<div class="cssloader"><i></i><i></i><i></i></div>' );
        $loaderHolder.css( ' position', 'relative');

        $loader = $( '.cssloader' );
        loaderHide();
    }

    function loaderShow() {
        $( 'main' ).addClass( 'overlay' );
      
        //$loader.css( 'top', window.scrollY + 'px' );
        $loader.show();
    }

    function loaderHide() {
        $( 'main' ).removeClass( 'overlay' );
        $loader.hide();
    }

    function init() {
        bindUI();
        bindSearchFilterMenu();
        addLoadingAnimation();
        //initializeFilter();  // sent from wp via localize_script
    }

    init();

    //fetchPosts();  // this script should be loaded on localize
              
});
