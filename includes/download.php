<?php
	error_reporting( E_ERROR || E_PARSE );
    
    if( !function_exists( 'cpis_checkMemory' ) ){
        // Check if the PHP memory is sufficient
        function cpis_checkMemory( $files = array() ){
            $required = 0;
            
            $m = ini_get( 'memory_limit' );
            $m = trim($m);
            $l = strtolower($m[strlen($m)-1]); // last
            switch($l) {
                // The 'G' modifier is available since PHP 5.1.0
                case 'g':
                    $m *= 1024;
                case 'm':
                    $m *= 1024;
                case 'k':
                    $m *= 1024;
            }

            foreach ( $files as $file ){
                $memory_available = $m - memory_get_usage(true);
                if( file_exists( $file ) ){
                    $required += filesize( $file );
                    if( $required >= $memory_available - 100 ) return false;
                }else return false;
            }
            return true;
        } 
    } // cpis_checkMemory
    
    global $htaccess_accepted, $options;
    
    $options = get_option( 'cpis_options' );
    $htaccess_accepted = false;
    
	function cpis_copy_download_links( $file ){
    	$ext  = pathinfo( $file, PATHINFO_EXTENSION );
		$new_file_name = md5( $file ).'.'.$ext;
		$file_path = CPIS_DOWNLOAD.'/'.$new_file_name;
		$rand = rand( 1000, 1000000 );
        if( file_exists( $file_path ) ) return CPIS_PLUGIN_URL.'/downloads/'.$new_file_name.'?param='.$rand;
        
        if( !cpis_checkMemory( array( $file  ) ) ) return $file;
        
        $content = file_get_contents( $file );
        if( $content && file_put_contents( $file_path, $content ) ) return CPIS_PLUGIN_URL.'/downloads/'.$new_file_name.'?param='.$rand;
		return $file;
	} // End cpis_copy_download_links
	
	function cpis_remove_download_links(){
        global $htaccess_accepted, $options;
        
		$now = time();
		$dif = ( ( !empty( $options[ 'store' ][ 'download_link' ] ) ) ? $options[ 'store' ][ 'download_link' ] : 3 ) * 86400;
		$d = @dir( CPIS_DOWNLOAD );
		while ( false !== ( $entry = $d->read() ) ) {
            // The image-store-icon.png file allow to know that htaccess file is supported, so it should not be deleted
			if($entry != '.' && $entry != '..' && $entry != 'image-store-icon.png'){
                if( $entry == '.htaccess' ){
                    if( !$htaccess_accepted ){ // Remove the htaccess if it is not accepted
                        @unlink( CPIS_DOWNLOAD.'/'.$entry );
                    }
                }else{
                    $file_name = CPIS_DOWNLOAD.'/'.$entry;
                    $date = filemtime( $file_name );
                    if( $now-$date >= $dif ){ // Delete file
                        @unlink( $file_name );
                    }
                }    
			}
		}
		$d->close();
	} // End cpis_remove_download_links
	
	function cpis_image_title( $obj ){
		if( isset( $obj->post_title ) ) return $obj->post_title;
		return pathinfo( $obj->path, PATHINFO_FILENAME );
	} // End cpis_image_title
	
	function cpis_generate_downloads(){
		global $wpdb, $download_links_str, $options;
		
        cpis_remove_download_links();
        
        $tmp_arr = array();
        
		$purchase_rows = $wpdb->get_results(
                            $wpdb->prepare(
                                "SELECT * FROM ".$wpdb->prefix.CPIS_PURCHASE." WHERE purchase_id=%s", 
                                $_GET['purchase_id']
                            )
                        );	

		if( $purchase_rows ){ // Exists the purchase
            $interval = ( ( !empty( $options[ 'store' ][ 'download_link' ] ) ) ? $options[ 'store' ][ 'download_link' ] : 3 )*86400;
            
            $urls = array();
            $download_links_str = '';
			
            foreach( $purchase_rows as $purchase ){

                if( !current_user_can( 'manage_options' ) ){
                    $diff = abs( strtotime( $purchase->date )-time() );
                    if($diff > $interval){
                        $download_links_str = __('The download link has expired, please contact to the vendor', CPIS_TEXT_DOMAIN);
                        break;
                    }
                }    
                
                $id = $purchase->product_id;
                
                $obj = $wpdb->get_row(
                    $wpdb->prepare(
                        "SELECT file.path as path, file.url as link, post.post_title as title
                        FROM ".$wpdb->prefix."posts as post, ".$wpdb->prefix.CPIS_IMAGE." as image, ".$wpdb->prefix.CPIS_IMAGE_FILE." as image_file, ".$wpdb->prefix.CPIS_FILE." as file
                        WHERE post.ID=image.id AND image.id=image_file.id_image AND image_file.id_file=file.id AND file.id=%d
                        ",
                        $id
                    )
                );
                
                if( is_null( $obj ) || in_array( $obj->path, $tmp_arr ) ) continue;
                
                $tmp_arr[] = $obj->path;
                
                $obj->title = cpis_image_title($obj);
                
                $urls[] = $obj;
            }
            
            if( count( $urls ) ){
            	if( empty( $download_links_str ) ){
					foreach($urls as $url){
						$download_link = cpis_copy_download_links( $url->path );
                        $download_links_str .= '<div> <a href="'.$download_link.'">'.$url->title.'</a></div>';
					}
				}
			}
			
			if(empty($download_links_str)){
				$download_links_str = __('The list of purchased products is empty', CPIS_TEXT_DOMAIN);
			}
            
            return $download_links_str;
		} // End purchase checking	
	} //cpis_generate_downloads
	
	
    if( isset( $_GET[ 'purchase_id' ] ) ) {
        global $download_links_str;
        $response = wp_remote_get( CPIS_PLUGIN_URL.'/downloads/image-store-icon.png' );
        $htaccess_accepted = ( !is_wp_error( $response ) && $response[ 'response' ][ 'code' ] == 200 );
        $download_links_str = cpis_generate_downloads();
    }
?>