(function($){
	var cpis_mousemove = function( e ) {
        if( 
            e.pageX < e.data.x1 ||
            e.pageX > e.data.x2 ||
            e.pageY < e.data.y1 ||
            e.pageY > e.data.y2 
        ){
            $( document ).unbind( 'mousemove', cpis_mousemove );
            $( '.cpis-image-data' ).hide();
        }    
    };
    
	window[ 'cpis_display_dlg' ] = function( e, id, incorner ){
    
        // Close all image data panels
        $( '.cpis-image-data' ).hide();
        
        var d = $( '#cpis_image'+id );
        
        e = $( e );
        
        if(d.length){
            if( incorner ){
                d.show().position(
                    {
                        my: "left top",
                        at: "left top",
                        of: e
                    }
                );
            }else{
                d.show().position(
                    {
                        my: "center",
                        at: "center",
                        of: e
                    }
                );
            }
            
        
            var o = d.offset();
            $( document ).bind( 
                'mousemove', 
                {
                    x1: o.left,
                    y1: o.top,
                    x2: o.left + d.width(),
                    y2: o.top + d.height()
                },
                cpis_mousemove
            );
        }    
    };
    
    window[ 'cpis_buynow' ] = function( e ){
        if( $( e ).parents( 'form' ).find( 'input:checked' ).length ) e.form.submit();
        else if( image_store && image_store[ 'file_required_str' ] ) alert( image_store[ 'file_required_str' ] );
    };
    
    // Main Code
    $(
        function(){
    
            // Carousel routines
            
            // Set carousel
            
            var c = $('[id="cpis-image-store-carousel"]');
            if( c.length ){
                var li = c.find( 'li' );
                if( li.length ){
                    var l = $('<div class="cpis-carousel-left" style="" ></div>'),
                        r = $('<div class="cpis-carousel-right" style="" ></div>'),
                        i = c.find( '.cpis-carousel-container' ),
                        u = $(c.find('ul').get(0));
                    
                    if( image_store && image_store[ 'thumbnail_h' ] ){    
                        l.css( 'top', ( ( image_store[ 'thumbnail_h' ]-60 ) / 2) + 'px' );
                        r.css( 'top', ( ( image_store[ 'thumbnail_h' ]-60 ) / 2) + 'px' );
                    }
                    i.width( c.width() -  42 );
                    c.append( l ).append( r );
                    var _auto = false;
                    if( image_store && image_store[ 'carousel_autorun' ] != 0){
                        _auto = { pauseOnHover: 'resume' };
                    }            
                    
                    u.carouFredSel({
                        width: '100%',
                        height: 'auto',
                        prev: '.cpis-carousel-left',
                        next: '.cpis-carousel-right',
                        auto: _auto
                    });
                        
                }
            }
            
            // Set license handle
            $( '.cpis-license-title.cpis-link' ).click(
                function(){
                    var e = $( this );
                    $( '.cpis-license-container' )
                        .toggle()
                        .position(
                            {
                                my: 'left top',
                                at: 'left top',
                                of: e
                            }
                        );
                }
            );
            
            $( '.cpis-license-close' ).click( 
                function(){
                    $( '.cpis-license-container' ).hide();
                } 
            );
        }
    );    
})(jQuery);