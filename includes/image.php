<?php

if( !function_exists( 'cpis_watermarkImage' ) ){
    function cpis_watermarkImage ($SourceFile, $DestinationFile, $d_width, $d_height) { 
        try{
            $ext = pathinfo( $SourceFile, PATHINFO_EXTENSION );
            switch ( $ext ) {
                case "jpg":
                case "jpeg":
                    $image = imagecreatefromjpeg( $SourceFile );
                    break;
                case "gif":
                    $image = imagecreatefromgif( $SourceFile );
                    break;
                case "png":
                    $image = imagecreatefrompng( $SourceFile );
                    break;
                default:
                    return false;
            } 
           
           list($width, $height) = getimagesize($SourceFile);
           $t_width  = min($width, $d_width); 
           
           $t_height = $t_width*$height/$width;
           
           $n_height = min($t_height, $d_height);
           
           $n_width = $n_height*$t_width/$t_height;
           
           $image_p = imagecreatetruecolor($n_width, $n_height);
           imagecopyresampled($image_p, $image, 0, 0, 0, 0, $n_width, $n_height, $width, $height); 
           switch ( $ext ) {
                case "jpg":
                case "jpeg":
                    imagejpeg ($image_p, $DestinationFile, 100);
                    
                    break;
                case "gif":
                    imagegif ($image_p, $DestinationFile);
                    break;
                case "png":
                    imagepng ($image_p, $DestinationFile, 0);
                    break;
            }
            
           imagedestroy($image); 
           imagedestroy($image_p); 
       }catch ( Exception $e ){
            return false;
       }
       return true;
    };
} // cpis_watermarkImage

if( !function_exists( 'cpis_image_columns' ) ){
    function cpis_image_columns( $columns ){
        return array(
				'cb'	        => '<input type="checkbox" />',
                'id'       		=> __( 'Image Id', CPIS_TEXT_DOMAIN ),
				'preview'       => __( 'Preview', CPIS_TEXT_DOMAIN ),
				'title'	        => __( 'Image Name', CPIS_TEXT_DOMAIN ),
				'author'        => __( 'Post Author', CPIS_TEXT_DOMAIN ),
				'image_author'  => __( 'Image Author', CPIS_TEXT_DOMAIN ),
				'color'         => __( 'Colors Scheme', CPIS_TEXT_DOMAIN ),
				'type'          => __( 'Type', CPIS_TEXT_DOMAIN ),
				'purchases'     => __( 'Purchases', CPIS_TEXT_DOMAIN ),
				'date'	        => __( 'Date', CPIS_TEXT_DOMAIN )
		   );
    }
} // End cpis_image_columns

if( !function_exists( 'cpis_image_columns_data' ) ){
    function cpis_image_columns_data( $column ){
        global $post, $wpdb;
        $separator = '';
        
        switch ($column){
            case "preview":
                $preview = $wpdb->get_var( $wpdb->prepare( "SELECT preview FROM ".$wpdb->prefix.CPIS_IMAGE." WHERE id=%d", $post->ID ) );
                $str = '<img src="'.CPIS_PLUGIN_URL.'/images/empty.png" style="width:100%;" />';
                if( !empty( $preview ) ){
                    $preview = unserialize( $preview );
                    $str = '<img src="'.$preview[ 'thumbnail_url' ].'" style="width:100%;"/>';		
                }
                echo $str;
            break;
            
            case "purchases":
                $purchases = $wpdb->get_var( $wpdb->prepare( "SELECT purchases FROM ".$wpdb->prefix.CPIS_IMAGE." WHERE id=%d", $post->ID ) );
                echo ( !empty( $purchases ) ) ? $purchases : 0;
            break;
            
            case "id":
                echo $post->ID;
            break;
            
            case "color":
                $color_list = wp_get_object_terms( $post->ID, 'cpis_color' );
                $str = '';
                foreach( $color_list as $color ){
                    $str .= $separator.$color->name;
                    $separator = ', ';
                }
                echo $str;
            break;
            
            case "type":
                $type_list = wp_get_object_terms( $post->ID, 'cpis_type' );
                $str = '';
                foreach( $type_list as $type ){
                    $str .= $separator.$type->name;
                    $separator = ', ';
                }
                echo $str;
            break;
            
            case "image_author":
                $author_list = wp_get_object_terms( $post->ID, 'cpis_author' );
                $str = '';
                foreach( $author_list as $author ){
                    $str .= $separator.$author->name;
                    $separator = ', ';
                }
                echo $str;
            break;
        } // End switch
        
    }
} // End cpis_image_columns_data

// Set enctype to multipart to allow file uploads.
add_action('post_edit_form_tag', 'cpis_image_add_post_enctype');
if( !function_exists( 'cpis_image_add_post_enctype' ) ){
    function cpis_image_add_post_enctype(){
        global $post;
        if( 'cpis_image' == $post->post_type )
            echo ' enctype="multipart/form-data"';
    }
} // End cpis_image_add_post_enctype

if( !function_exists( 'cpis_image_metabox' ) ){
    function cpis_image_metabox(){
        global $wpdb, $post;
        $options = get_option( 'cpis_options' );
        
        $query = "SELECT * FROM ".$wpdb->prefix.CPIS_IMAGE." as data WHERE data.id = {$post->ID};";
        $data = $wpdb->get_row($query);
        
        $query = "SELECT file.* FROM ".$wpdb->prefix.CPIS_FILE." as file, ".$wpdb->prefix.CPIS_IMAGE_FILE." as image_file WHERE file.id=image_file.id_file AND image_file.id_image=".$post->ID;
        $files = $wpdb->get_results($query);

        $author_post_list = wp_get_object_terms($post->ID, 'cpis_author');
        $author_list = get_terms('cpis_author', array( 'hide_empty' => 0 ));
        
        $color_post_list = wp_get_object_terms($post->ID, 'cpis_color');
        $color_list = get_terms('cpis_color', array( 'hide_empty' => 0 ));
        
        $type_post_list = wp_get_object_terms($post->ID, 'cpis_type');
        $type_list = get_terms('cpis_type', array( 'hide_empty' => 0 ));
        
        wp_nonce_field( plugin_basename( __FILE__ ), 'cpis_image_box_content_nonce' );
        $currency = $options['paypal']['currency'];
        
        echo '
            <table class="form-table product-data">
                <tr>
                    <td valign="top">
                        '.__('Authors:', CPIS_TEXT_DOMAIN).'
                    </td>
                    <td><div id="cpis_author_list">';
                    
                    if($author_post_list){
                        foreach($author_post_list as $author){
                            echo '<div class="cpis-property-container"><input type="hidden" name="cpis_author[]" value="'.esc_attr($author->name).'" /><input type="button" onclick="cpis_remove(this);" class="button" value="'.esc_attr($author->name).' [x]"></div>';
                        }
                        
                    }
                    echo '</div><div style="clear:both;"><select onchange="cpis_select_element(this, \'cpis_author_list\', \'cpis_author\');"><option value="none">'.__('Select an Author', CPIS_TEXT_DOMAIN).'</option>';
                    if($author_list){
                        foreach($author_list as $author){
                            echo '<option value="'.esc_attr($author->name).'">'.$author->name.'</option>';
                        }
                    }	
                    echo '		
                            </select>
                            <input type="text" id="new_author" placeholder="'.__('Enter a new author', CPIS_TEXT_DOMAIN).'">
                             <input type="button" value="'.__('Add author', CPIS_TEXT_DOMAIN).'" class="button" onclick="cpis_add_element(\'new_author\', \'cpis_author_list\', \'cpis_author_new\');"/><br />
                             <span class="cpis-comment">'.__('Select an Author from the list or enter new one', CPIS_TEXT_DOMAIN).'</span>
                        </div>	
                    </td>
                </tr>
                <tr>
                    <td valign="top">
                        '.__('Type of Image:', CPIS_TEXT_DOMAIN).'
                    </td>
                    <td><div id="cpis_type_list">';
                    if($type_post_list){
                        foreach($type_post_list as $type){
                            echo '<div class="cpis-property-container"><input type="hidden" name="cpis_type[]" value="'.esc_attr($type->name).'" /><input type="button" onclick="cpis_remove(this);" class="button" value="'.esc_attr($type->name).' [x]"></div>';
                        }
                        
                    }
                    echo '</div><div style="clear:both;"><select onchange="cpis_select_element(this, \'cpis_type_list\', \'cpis_type\');"><option value="none">'.__('Select an Type', CPIS_TEXT_DOMAIN).'</option>';

                    if($type_list){
                        foreach($type_list as $type){
                            echo '<option value="'.esc_attr($type->name).'">'.$type->name.'</option>';
                        }
                    }	
                    echo '		
                             </select>
                             <input type="text" id="new_type" placeholder="'.__('Enter a new type', CPIS_TEXT_DOMAIN).'">
                             <input type="button" value="'.__('Add type', CPIS_TEXT_DOMAIN).'" class="button" onclick="cpis_add_element(\'new_type\', \'cpis_type_list\', \'cpis_type_new\');" /><br />
                             <span class="cpis-comment">'.__('Select an Type from the list or enter new one', CPIS_TEXT_DOMAIN).'</span>
                        </div>	
                    </td>
                </tr>
                <tr>
                    <td valign="top">
                        '.__('Scheme Color:', CPIS_TEXT_DOMAIN).'
                    </td>
                    <td><div id="cpis_color_list">';
                    if($color_post_list){
                        foreach($color_post_list as $color){
                            echo '<div class="cpis-property-container"><input type="hidden" name="cpis_color[]" value="'.esc_attr($color->name).'" /><input type="button" onclick="cpis_remove(this);" class="button" value="'.esc_attr($color->name).' [x]"></div>';
                        }
                        
                    }
                    echo '</div><div style="clear:both;"><select onchange="cpis_select_element(this, \'cpis_color_list\', \'cpis_color\');"><option value="none">'.__('Select an Color Scheme', CPIS_TEXT_DOMAIN).'</option>';

                    if($color_list){
                        foreach($color_list as $color){
                            echo '<option value="'.esc_attr($color->name).'">'.$color->name.'</option>';
                        }
                    }	
                    echo '		
                             </select>
                             <input type="text" id="new_color" placeholder="'.__('Enter a new color scheme', CPIS_TEXT_DOMAIN).'">
                             <input type="button" value="'.__('Add color scheme', CPIS_TEXT_DOMAIN).'" class="button" onclick="cpis_add_element(\'new_color\', \'cpis_color_list\', \'cpis_color_new\');" /><br />
                             <span class="cpis-comment">'.__('Select an Color Scheme from the list or enter new one', CPIS_TEXT_DOMAIN).'</span>
                        </div>	
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <p style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;">
                            For reporting an issue or to request a customization, <a href="http://wordpress.dwbooster.com/contact-us" target="_blank">CLICK HERE</a>
                        </p>
                    </td>
                </tr>
            </table>
        ';
        
        $preview = '<img src="'.CPIS_PLUGIN_URL.'/images/empty.png">';
        if ( isset( $data ) && !empty( $data->preview ) ){
            $preview_data = unserialize( $data->preview );
            $preview = '<img src="'.$preview_data[ 'thumbnail_url' ].'" style="width:100%;" /></div>';
        }
        echo '
            <table class="form-table">
                <tr>
                    <td colspan="2">'.__( 'Select images for preview', CPIS_TEXT_DOMAIN ).':</td>
                </tr>
                <tr>    
                    <td><div style="width:100px;">'.$preview.'</div></td>
                    <td><input type="file" name="cpis_preview" /></td>
                </tr>
            </table>
        ';
        
        echo '
            <input type="button" value="'.__('Add File', CPIS_TEXT_DOMAIN).'" onclick="cpis_new_file();" />
            <table class="form-table cpis-file-list">
                <tr>
                    <td>Image File</td>
                    <td>Width ('.$options[ 'image' ][ 'unit' ].')</td>
                    <td>Height ('.$options[ 'image' ][ 'unit' ].')</td>
                    <td>Price ('.$options[ 'paypal' ][ 'currency' ].')</td></td>
                    <td></td>
                </tr>
        ';
        
        if( !empty( $files ) ){
            foreach( $files as $file ){
                echo '
                    <tr>
                        <td style="width:55%;">
                            <div style="width:100px"><img src="'.$file->url.'" style="width:100%" /></div>
                            <input type="hidden" name="cpis_file['.$file->id.']" value="'.$file->id.'" />
                        </td>
                        <td style="width:10%;"><input type="text" name="cpis_file_width['.$file->id.']" value="'.esc_attr( $file->width ).'" class="cpis-short-field" /></td>
                        <td style="width:10%;"><input type="text" name="cpis_file_height['.$file->id.']" value="'.esc_attr( $file->height ).'" class="cpis-short-field" /></td>
                        <td style="width:10%;"><input type="text" name="cpis_file_price['.$file->id.']" value="'.esc_attr( esc_attr( sprintf( "%.2f", $file->price ) ) ).'" class="cpis-short-field" /> </td>
                        <td style="width:10%;"> <input type="button" onclick="cpis_remove_file(this, '.$file->id.');" value="'.__( 'Remove', CPIS_TEXT_DOMAIN ).'" /></td>
                    </tr>
                 ';
            }
        }
        
        echo '
                <tr>
                    <td style="width:55%;"><input type="file" name="cpis_file_new[]" style="width:100%;" /></td>
                    <td style="width:10%;"><input type="text" name="cpis_file_width_new[]" class="cpis-short-field" /></td>
                    <td style="width:10%;"><input type="text" name="cpis_file_height_new[]" class="cpis-short-field" /></td>
                    <td style="width:10%;"><input type="text" name="cpis_file_price_new[]" class="cpis-short-field" /></td>
                    <td style="width:10%;"> <input type="button" onclick="cpis_remove_file(this);" value="'.__( 'Remove', CPIS_TEXT_DOMAIN ).'" /></td>
                </tr>
            </table>
        ';
    }
 } // End cpis_image_metabox

if( !function_exists( 'cpis_save_image' ) ){
    function cpis_save_image(){
        global $wpdb, $post;

        if( !isset( $post ) ) return;
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) 
        return;

        if ( !isset($_POST['cpis_image_box_content_nonce']) || !wp_verify_nonce( $_POST['cpis_image_box_content_nonce'], plugin_basename( __FILE__ ) ) )
        return;

        if ( !current_user_can( 'edit_post', $post->ID ) )
        return;
        
        if( 'cpis_image' != $post->post_type )
        return;
        
        $id = $post->ID;
        
        // Get image data if exists
        $table = $wpdb->prefix.CPIS_IMAGE;
        $image = $wpdb->get_row( "SELECT * FROM $table WHERE id=$id;");
        
        if ( ! function_exists( 'wp_handle_upload' ) ) require_once( ABSPATH . 'wp-admin/includes/file.php' );
        $upload_overrides = array( 'test_form' => false );
        
        $data = array(  );
        
        // Create preview
        if( !empty( $_FILES ) && !empty( $_FILES[ 'cpis_preview' ] ) ){
            $movefile = wp_handle_upload( $_FILES[ 'cpis_preview' ], $upload_overrides );
            if( empty( $movefile[ 'error' ] ) ){
                $options = get_option( 'cpis_options' );
                $t = time();
                $ext = pathinfo( $movefile[ 'file' ], PATHINFO_EXTENSION );
                $thumbnail_path = CPIS_UPLOAD_DIR.'/previews/'.$t.'.'.$ext;
                $thumbnail_url  = CPIS_UPLOAD_URL.'/previews/'.$t.'.'.$ext;

                if( 
                    cpis_watermarkImage( $movefile[ 'file' ], $thumbnail_path,  $options[ 'image' ][ 'thumbnail' ][ 'width' ],  $options[ 'image' ][ 'thumbnail' ][ 'height' ] ) 
                ){
                    $t = time()+1;
                    $intermediate_path = CPIS_UPLOAD_DIR.'/previews/'.$t.'.'.$ext;
                    $intermediate_url  = CPIS_UPLOAD_URL.'/previews/'.$t.'.'.$ext;
                    if( 
                        cpis_watermarkImage( $movefile[ 'file' ], $intermediate_path,  $options[ 'image' ][ 'intermediate' ][ 'width' ],  $options[ 'image' ][ 'intermediate' ][ 'height' ] ) 
                    ){
                        // Remove previous preview file (thumbnail and intermediate) and insert the new once
                        $old_preview = $image->preview;
                        if( !empty( $old_preview ) ){
                            $old_preview = unserialize( $old_preview );
                            @unlink( $old_preview[ 'thumbnail_path' ] );
                            @unlink( $old_preview[ 'intermediate_path' ] );
                        }
                        
                        $new_preview = serialize(
                            array(
                                'thumbnail_path'    => $thumbnail_path,
                                'thumbnail_url'     => $thumbnail_url,
                                'intermediate_path' => $intermediate_path,
                                'intermediate_url'  => $intermediate_url
                            )
                        );
                        
                        $data[ 'preview' ] = $new_preview;
                    }
                }
                
                @unlink( $movefile[ 'file' ] );
            }
        }
        
        
        $format = array( '%s' );
        
        if( !empty( $image ) ){
            if( !empty( $data ) ){
                // Set an update query
                $wpdb->update(
                    $table, 
                    $data,
                    array('id'=>$id),
                    $format,
                    array('%d')
                );
            }
        }else{
            // Set an insert query
            $data['id'] = $id;
            array_push( $format, '%d' );
            $wpdb->insert(
                $table,
                $data,
                $format
            );
            
        }

        // Clear the artist and album lists and then set the new ones
        wp_set_object_terms($id, null, 'cpis_author');
        wp_set_object_terms($id, null, 'cpis_type');
        wp_set_object_terms($id, null, 'cpis_color');

        // Set the author list
        if(isset($_POST['cpis_author'])){
            wp_set_object_terms($id, $_POST['cpis_author'], 'cpis_author', true);
        }	
        
        if(isset($_POST['cpis_author_new'])){
            wp_set_object_terms($id, $_POST['cpis_author_new'], 'cpis_author', true);
        }

        // Set the type list
        if(isset($_POST['cpis_type'])){
            wp_set_object_terms($id, $_POST['cpis_type'], 'cpis_type', true);
        }	
        
        if(isset($_POST['cpis_type_new'])){
            wp_set_object_terms($id, $_POST['cpis_type_new'], 'cpis_type', true);
        }
        
        // Set the color scheme list
        if(isset($_POST['cpis_color'])){
            wp_set_object_terms($id, $_POST['cpis_color'], 'cpis_color', true);
        }	
        
        if(isset($_POST['cpis_color_new'])){
            wp_set_object_terms($id, $_POST['cpis_color_new'], 'cpis_color', true);
        }
        
        // Update files
        if( isset( $_POST[ 'cpis_file' ] ) ){
            foreach( $_POST[ 'cpis_file' ] as $key => $value ){
                $wpdb->update(
                    $wpdb->prefix.CPIS_FILE,
                    array(
                        'width'  => $_POST[ 'cpis_file_width' ][ $key ],
                        'height' => $_POST[ 'cpis_file_height' ][ $key ],
                        'price'  => $_POST[ 'cpis_file_price' ][ $key ],
                    ),
                    array( 'id' => $value ),
                    array( '%f', '%f', '%f' ),
                    array( '%d' )
                );
            }
        }
        
        // Save files
        if( !empty($_FILES) && isset( $_FILES[ 'cpis_file_new' ] ) ){
            $cpis_file_new = $_FILES[ 'cpis_file_new' ];
            
            foreach( $cpis_file_new['name'] as $key => $value){
                $file = array(
                    'name'     => $cpis_file_new['name'][$key],
                    'type'     => $cpis_file_new['type'][$key],
                    'tmp_name' => $cpis_file_new['tmp_name'][$key],
                    'error'    => $cpis_file_new['error'][$key],
                    'size'     => $cpis_file_new['size'][$key]
                );
    
    
                $movefile = wp_handle_upload( $file, $upload_overrides );
                if( empty( $movefile[ 'error' ] ) ){
                    $wpdb->insert(
                        $wpdb->prefix.CPIS_FILE,
                        array(
                            'url'    => $movefile[ 'url' ],
                            'path'   => $movefile[ 'file' ],
                            'width'  => $_POST[ 'cpis_file_width_new' ][ $key ],
                            'height' => $_POST[ 'cpis_file_height_new' ][ $key ],
                            'price'  => $_POST[ 'cpis_file_price_new' ][ $key ]
                        ),
                        array(
                            '%s',
                            '%s',
                            '%f',
                            '%f',
                            '%f'
                        )
                    );
                    
                    $wpdb->insert(
                        $wpdb->prefix.CPIS_IMAGE_FILE,
                        array(
                            'id_image' => $id,
                            'id_file'  => $wpdb->insert_id
                        ),
                        
                        array(
                            '%d',
                            '%d'
                        )
                    );
                     
                }
            }
            
        }
        
    }
 } // End cpis_save_image

 if( !function_exists( 'cpis_remove_image' ) ){
    function cpis_remove_image( $id ){
        global $wpdb;
        $path = $wpdb->get_var( $wpdb->prepare( 'SELECT path FROM '.$wpdb->prefix.CPIS_FILE.' WHERE id=%d', $id ) );
        if( isset( $path ) ){
            if ( 
                $wpdb->delete( 
                    $wpdb->prefix.CPIS_IMAGE_FILE,
                    array( 'id_file' => $id),
                    array( '%d' )
                )
            ){
                $wpdb->delete( 
                    $wpdb->prefix.CPIS_FILE,
                    array( 'id' => $id),
                    array( '%d' )
                );
            }    
            @unlink( $path );
            return '{ "success" : "OK" }';
        }else{
            return '{ "error" : "File not found" }';
        } 
    }
 } // End cpis_remove_image
 
 if( !function_exists( 'cpis_display_content' ) ){
    function _cpis_set_taxonomy_loop( &$data_arr, $list, $var, $title ){
        $arr = array();
        foreach($list as $item){
            $arr[] = '<a href="'.get_term_link($item).'">'.$item->name.'</a>';
        }
        $data_arr[ $var ] = $title.implode(', ', $arr);
    }
    
    function cpis_display_content( $id, $type = 'multiple', $output = 'echo' ){
        global $wpdb;
        $options = get_option( 'cpis_options' );
        $data_arr = array();
        
        $image = $wpdb->get_row( $wpdb->prepare( "SELECT post.*, post_data.* FROM ".$wpdb->prefix."posts AS post, ".$wpdb->prefix.CPIS_IMAGE." AS post_data WHERE post_data.id = post.ID AND post.ID=%d", $id ) );
        
        if( !is_null( $image ) ){
            $data_arr[ 'id' ] = $id;
            $data_arr[ 'link' ] = get_permalink($image->ID);
            if( !empty( $image->preview ) ){
                $preview = unserialize( $image->preview );
                $data_arr[ 'thumbnail' ] = $preview[ 'thumbnail_url' ];
                $data_arr[ 'intermediate' ] = $preview[ 'intermediate_url' ];
            }
            
            if( !empty( $image->post_content ) ){
                $data_arr[ 'description' ] = '<p>'.preg_replace( '/[\n\r]+/', '</p><p>', $image->post_content ).'</p>';
            }
            
            if( !empty( $image->post_title ) ){
                $data_arr[ 'title' ] = $image->post_title;
            }
            
            $author_list = wp_get_object_terms($id, 'cpis_author');
            $type_list = wp_get_object_terms($id, 'cpis_type');
            $color_list = wp_get_object_terms($id, 'cpis_color');
            $category_list = wp_get_object_terms($id, 'cpis_category');
            
            if( count( $author_list ) ){
                _cpis_set_taxonomy_loop( $data_arr, $author_list, 'author', __( 'Author(s): ', CPIS_TEXT_DOMAIN) );
            }
            
            if( count( $type_list ) ){
                _cpis_set_taxonomy_loop( $data_arr, $type_list, 'type', __( 'Type(s): ', CPIS_TEXT_DOMAIN) );
            }
            
            if( count( $color_list ) ){
                _cpis_set_taxonomy_loop( $data_arr, $color_list, 'color', __( 'Color Scheme(s): ', CPIS_TEXT_DOMAIN) );
            }
            
            if( count( $category_list ) ){
                _cpis_set_taxonomy_loop( $data_arr, $category_list, 'category', __( 'Category(s): ', CPIS_TEXT_DOMAIN) );
            }
            
            $files = $wpdb->get_results( $wpdb->prepare( "SELECT file.* FROM ".$wpdb->prefix.CPIS_FILE." AS file, ".$wpdb->prefix.CPIS_IMAGE_FILE." AS image_file WHERE file.id=image_file.id_file AND image_file.id_image=%d", $id ) );
            
            if( !empty( $files ) ){
                $data_arr[ 'file' ] = __( 'Image Version(s): ', CPIS_TEXT_DOMAIN );
                $data_arr[ 'file' ] .= '<ul>';
				$tmp_price_sum = 0;
                foreach( $files as $file ){
					$tmp_price_sum += floatval( $file->price );
					
                    $data_arr[ 'file' ] .= '<li><span style="width:20px;display:inline-block;">'.( ( $options[ 'paypal' ][ 'activate_paypal' ] &&  !empty( $file->price ) ) ? '<input type="checkbox" name="image_file[]" value="'.$file->id.'" />' : '' ).'</span><span class="cpis-image-size">['.$file->width.' x '.$file->height.' '.$options[ 'image' ][ 'unit' ].']</span>&nbsp;&nbsp;&nbsp;&nbsp;<span class="cpis-image-price">'.( ( $options[ 'paypal' ][ 'activate_paypal' ] &&  !empty( $file->price ) ) ? $options[ 'paypal' ][ 'currency_symbol' ].sprintf("%.2f", $file->price).' '.$options[ 'paypal' ][ 'currency' ] : '<a href="'.$file->url.'" target="_blank">'.__( 'download', CPIS_TEXT_DOMAIN ).'</a>' ).'</span></li>';
					
                }
                $data_arr[ 'file' ] .= '</ul>';

                // Set the buy now button
                $data_arr[ 'submit' ] = CPIS_H_URL.'?cpis-action=buynow';
                $data_arr[ 'button' ] = '<input type="button" id="cpis-paypal-button" class="cpis-paypal-button" value="'.__( 'Buy Now', CPIS_TEXT_DOMAIN ).'" onclick="cpis_buynow(this);" />';
				
				if( !$options[ 'paypal' ][ 'activate_paypal' ] || $tmp_price_sum == 0 )
				{
					$data_arr[ 'button' ] = '';
				}
            }
            
        }
 
        switch( $type ){
            case 'widget':
                $data_arr[ 'preview' ] = false;
                if( $output == 'echo' )
                    print cpis_print_multiple( $data_arr );
                else
                    return cpis_print_multiple( $data_arr );
            break;
            
            case 'multiple':
                if( isset( $data_arr[ 'description' ] ) ) $data_arr[ 'description' ] = wp_trim_words( strip_shortcodes( $data_arr[ 'description' ] ) );
                if( $options[ 'display' ][ 'preview' ] ) $data_arr[ 'preview' ] = true;
                if( $output == 'echo' )
                    print cpis_print_multiple( $data_arr );
                else
                    return cpis_print_multiple( $data_arr );
            break;
            
            case 'store':
                if( isset( $data_arr[ 'description' ] ) ) $data_arr[ 'description' ] = wp_trim_words( strip_shortcodes( $data_arr[ 'description' ] ) );
                if( $options[ 'display' ][ 'preview' ] ) $data_arr[ 'preview' ] = true;
                if( $output == 'echo' )
                    print cpis_print_store( $data_arr );
                else
                    return cpis_print_store( $data_arr );
            break;
            
            case 'single':
                if( !empty( $options[ 'image' ][ 'license' ][ 'description' ] ) ){
                    $license_title = "
                        <div class='cpis-license-title cpis-link'>".( ( !empty( $options[ 'image' ][ 'license' ][ 'title' ] ) ) ? __( $options[ 'image' ][ 'license' ][ 'title' ], CPIS_TEXT_DOMAIN ) : __( 'License', CPIS_TEXT_DOMAIN ) )."</div>";
                            
                    $data_arr[ 'license' ] = "
                        $license_title
                        <div class='cpis-license-container'>
                            <div class='cpis-license-close'>[x]</div>
                            <div style='clear:both;'></div>
                            $license_title
                            <div class='cpis-license-description'>".$options[ 'image' ][ 'license' ][ 'description' ]."</div>
                        </div>";
                }
                
                if( $options[ 'store' ][ 'social_buttons' ] ) $data_arr[ 'social' ] = true;
                
                if( !empty( $options[ 'store' ][ 'store_url' ] ) ) $data_arr[ 'store_url' ] = $options[ 'store' ][ 'store_url' ];
                
                // Get elements for carousel that belong to the same category
                if( $options[ 'display' ][ 'carousel' ][ 'activate' ] ){
                    $_select 	= "SELECT DISTINCT posts.ID";
                    $_from 		= "FROM ".$wpdb->prefix."posts as posts,".$wpdb->prefix.CPIS_IMAGE." as posts_data"; 
                    $_where 	= "WHERE posts.ID = posts_data.id AND posts.ID <> $id AND posts.post_status='publish' AND posts.post_type='cpis_image' ";
                    $_order_by 	= "ORDER BY posts_data.purchases DESC";
                    $_limit 	= "LIMIT 0, 10";
        
                    $category_post_list = wp_get_object_terms($id, 'cpis_category');
                    if($category_post_list){
                        $_from .= ", ".$wpdb->prefix."term_taxonomy as taxonomy, ".$wpdb->prefix."term_relationships as term_relationships, ".$wpdb->prefix."terms as terms";
            
                        $_where .= " AND taxonomy.term_taxonomy_id=term_relationships.term_taxonomy_id AND term_relationships.object_id=posts.ID AND taxonomy.term_id=terms.term_id AND (";
                        
                        $connector = '';
                        foreach( $category_post_list as $category ){
                            $_where .= _cpis_filter_by_taxonomy( 'cpis_category', $category->term_id, $connector, true );
                            $connector = " OR ";
                        }
                        
                        $_where .= ")"; 
                    }
                    
                    $thumb_width  = $options[ 'image' ][ 'thumbnail' ][ 'width' ];
                    $thumb_height = $options[ 'image' ][ 'thumbnail' ][ 'height' ];
                    
                    $carousel_query = $_select." ".$_from." ".$_where." ".$_order_by." ".$_limit;
                    $carousel_results = $wpdb->get_results( $carousel_query );
                    
                    if( count( $carousel_results ) ){
                        $top_ten_carousel = "";    
                        foreach ( $carousel_results as $result ){
                            $top_ten_carousel .= cpis_display_content( $result->ID, 'carousel', 'return' );
                        }    
                        $data_arr[ 'carousel' ] = "<div class='cpis-column-title'>Related images</div><div id='cpis-image-store-carousel'><div class='cpis-carousel-container'><ul>".$top_ten_carousel."</ul></div></div>";
                    }    
                }
                
                if( $output == 'echo' )
                    print cpis_print_single( $data_arr );
                else
                    return cpis_print_single( $data_arr );
            break;
            
            case 'carousel':
                $data_arr[ 'thumbnail_w' ] = $options[ 'image' ][ 'thumbnail' ][ 'width' ]+10;
                $data_arr[ 'thumbnail_h' ] = $options[ 'image' ][ 'thumbnail' ][ 'height' ];
                if( $output == 'echo' )
                    print cpis_print_carousel( $data_arr );
                else
                    return cpis_print_carousel( $data_arr );
            break;
        }
    }
 } // End cpis_display_content
 
 if( !function_exists( 'cpis_print_single' ) ){
    function cpis_print_single( $arr ){
        $code = '<div class="cpis-single-image-data">';
        
        if( isset( $arr[ 'intermediate' ] ) ){
            $code .= '<div class="cpis-image-left">';
            $code .= '<img src="'.$arr[ 'intermediate' ].'" />';
            $code .= '</div><div class="cpis-image-right with-left">';
            
        }else{
            $code .= '
                <div class="cpis-image-right">
            ';
        }
        
        $code .= ( ( isset( $arr[ 'author' ] ) )      ? '<div class="cpis-image-author">'.$arr[ 'author' ].'</div>' : '' ).
                 ( ( isset( $arr[ 'type' ] ) )        ? '<div class="cpis-image-type">'.$arr[ 'type' ].'</div>' : '' ).
                 ( ( isset( $arr[ 'color' ] ) )       ? '<div class="cpis-image-color">'.$arr[ 'color' ].'</div>' : '' ).
                 ( ( isset( $arr[ 'category' ] ) )    ? '<div class="cpis-image-category">'.$arr[ 'category' ].'</div>' : '' ).
                 ( ( isset( $arr[ 'file' ] ) )        ? '<div class="cpis-image-file">
                                                            <form action="'.$arr[ 'submit' ].'" method="POST" data-ajax="false">
                                                                '.$arr[ 'file' ].$arr[ 'button' ].'
                                                            </form>
                                                                '.$arr[ 'discount' ].'
                                                        </div>' : '' ).
                 ( ( isset( $arr[ 'license' ] ) )     ? $arr[ 'license' ] : '' );
        
        if( isset( $arr[ 'social' ] ) && $arr[ 'social' ] ){
        
            $code .='
                <div id="fb-root"></div>
                <script>(function(d, s, id) {
                  var js, fjs = d.getElementsByTagName(s)[0];
                  if (d.getElementById(id)) return;
                  js = d.createElement(s); js.id = id;
                  js.src = "//connect.facebook.net/en_US/all.js#xfbml=1&appId=55224759839";
                  fjs.parentNode.insertBefore(js, fjs);
                }(document, "script", "facebook-jssdk"));</script>
                <div><div class="fb-like" data-href="'.$arr[ 'link' ].'" data-send="true" data-layout="button_count" data-width="450" data-show-faces="false" style="overflow:hidden;"></div></div>
                
                <div><div class="g-plus" data-action="share" data-annotation="bubble"></div></div>
                <script type="text/javascript">
                  (function() {
                    var po = document.createElement("script"); po.type = "text/javascript"; po.async = true;
                    po.src = "https://apis.google.com/js/plusone.js";
                    var s = document.getElementsByTagName("script")[0]; s.parentNode.insertBefore(po, s);
                  })();
                </script>
                
                <div><a href="https://twitter.com/share" class="twitter-share-button" data-url="'.$arr[ 'link' ].'">Tweet</a></div>
                <script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0],p=/^http:/.test(d.location)?"http":"https";if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src=p+"://platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document, "script", "twitter-wjs");</script>
                <div class="clear"></div>
            ';
        }        
        
        $code .= '
            </div>
			<div style="clear:both;"></div>
			'.( ( isset( $arr[ 'description' ] ) ) ? '<div class="cpis-image-description">'.$arr[ 'description' ].'</div>' : '' ).'
            <div style="clear:both;"></div>
            '.( ( isset( $arr[ 'carousel' ] ) ) ? $arr[ 'carousel' ] : '' ).
            ( ( isset( $arr[ 'store_url' ] ) )  ? '<div class="cpis-store-page-link"><a href="'.$arr[ 'store_url' ].'">'.__( 'Return to the store page', CPIS_TEXT_DOMAIN ).'</a></div>' : '' ).'
            
        </div>
        '; // End
        
        return $code;
    }
 } // End cpis_print_single
 
 if( !function_exists( 'cpis_print_store' ) ){
     function cpis_print_store( $arr ){
        global $cpis_images_preview;
        $code = '
            <div class="cpis-image">
            <!-- IMAGE PREVIEW -->
            <div class="cpis-image-preview" >
                <a href="'.$arr[ 'link' ].'">
                    <img src="'.( ( isset( $arr[ 'thumbnail' ] ) ) ? $arr[ 'thumbnail' ] : '').'" onmouseover="cpis_display_dlg( this, '.$arr[ 'id' ].');" alt="'.( ( isset( $arr[ 'title' ] ) ) ? $arr[ 'title' ] : '' ).'" />
                </a>
            </div>
            
            <!-- IMAGE DATA -->
        ';
        if( isset( $arr[ 'preview' ] ) ){
            $cpis_images_preview .= '    
                <div id="cpis_image'.$arr[ 'id' ].'" class="cpis-image-data">
                    <table id="cpis_table">
                        <tr>
                            <td valign="top" class="cpis-image-left" >
                                '.
                                    ( ( isset( $arr[ 'intermediate' ] ) ) ? '<a href="'.$arr[ 'link' ].'"><img src="'.$arr[ 'intermediate' ].'" /></a>' : '' ).
                                    ( ( isset( $arr[ 'title' ] ) ) ? '<div class="cpis-image-title"><a href="'.$arr[ 'link' ].'">'.$arr[ 'title' ].'</a></div>' : '' ).
                                '
                            </td>
                            <td valign="top" class="cpis-image-right">
                                '.
                                    ( ( isset( $arr[ 'description' ] ) ) ? '<div class="cpis-image-description">'.$arr[ 'description' ].'</div>' : '' ).
                                    ( ( isset( $arr[ 'author' ] ) )      ? '<div class="cpis-image-author">'.$arr[ 'author' ].'</div>' : '' ).
                                    ( ( isset( $arr[ 'type' ] ) )        ? '<div class="cpis-image-type">'.$arr[ 'type' ].'</div>' : '' ).
                                    ( ( isset( $arr[ 'color' ] ) )       ? '<div class="cpis-image-color">'.$arr[ 'color' ].'</div>' : '' ).
                                    ( ( isset( $arr[ 'category' ] ) )    ? '<div class="cpis-image-category">'.$arr[ 'category' ].'</div>' : '' ).
                                    ( ( isset( $arr[ 'file' ] ) )        ? '<div class="cpis-image-file">
                                                                                <form action="'.$arr[ 'submit' ].'" method="POST" data-ajax="false">
                                                                                    '.$arr[ 'file' ].$arr[ 'button' ].'
                                                                                </form>
                                                                            </div>' : '' ).
                                    
                                '
                            </td>
                        </tr>
                    </table>    
                </div>
            ';    
        }
        $code .= '</div>';
        return $code;
     }
 } // End cpis_print_store

 if( !function_exists( 'cpis_print_multiple' ) ){
     function cpis_print_multiple( $arr ){
        global $cpis_images_preview;
        $code = '
            <div class="cpis-image-multiple">
                <!-- IMAGE PREVIEW -->
                <div class="cpis-image-preview" >
                    <a href="'.$arr[ 'link' ].'">
                        <img src="'.( ( isset( $arr[ 'thumbnail' ] ) ) ? $arr[ 'thumbnail' ] : '').'" onmouseover="cpis_display_dlg( this, '.$arr[ 'id' ].', true );" alt="'.( ( isset( $arr[ 'title' ] ) ) ? $arr[ 'title' ] : '' ).'" />
                    </a>
                </div>
                <div class="cpis-preview-data">'.
                    ( ( isset( $arr[ 'author' ] ) )      ? '<div class="cpis-image-author">'.$arr[ 'author' ].'</div>' : '' ).
                    ( ( isset( $arr[ 'type' ] ) )        ? '<div class="cpis-image-type">'.$arr[ 'type' ].'</div>' : '' ).
                    ( ( isset( $arr[ 'color' ] ) )       ? '<div class="cpis-image-color">'.$arr[ 'color' ].'</div>' : '' ).
                    ( ( isset( $arr[ 'category' ] ) )    ? '<div class="cpis-image-category">'.$arr[ 'category' ].'</div>' : '' ).
                '</div>
                <!-- IMAGE DATA -->
        ';
        
        if( isset( $arr[ 'preview' ] ) && $arr[ 'preview' ] ){
            $cpis_images_preview .= '    
                <div id="cpis_image'.$arr[ 'id' ].'" class="cpis-image-data">
                    <table id="cpis_table">
                        <tr>
                            <td valign="top" class="cpis-image-left" >
                                '.
                                    ( ( isset( $arr[ 'intermediate' ] ) ) ? '<a href="'.$arr[ 'link' ].'"><img src="'.$arr[ 'intermediate' ].'" /></a>' : '' ).
                                    ( ( isset( $arr[ 'title' ] ) ) ? '<div class="cpis-image-title"><a href="'.$arr[ 'link' ].'">'.$arr[ 'title' ].'</a></div>' : '' ).
                                '
                            </td>
                            <td valign="top" class="cpis-image-right">
                                '.
                                    ( ( isset( $arr[ 'description' ] ) ) ? '<div class="cpis-image-description">'.$arr[ 'description' ].'</div>' : '' ).
                                    ( ( isset( $arr[ 'author' ] ) )      ? '<div class="cpis-image-author">'.$arr[ 'author' ].'</div>' : '' ).
                                    ( ( isset( $arr[ 'type' ] ) )        ? '<div class="cpis-image-type">'.$arr[ 'type' ].'</div>' : '' ).
                                    ( ( isset( $arr[ 'color' ] ) )       ? '<div class="cpis-image-color">'.$arr[ 'color' ].'</div>' : '' ).
                                    ( ( isset( $arr[ 'category' ] ) )    ? '<div class="cpis-image-category">'.$arr[ 'category' ].'</div>' : '' ).
                                    ( ( isset( $arr[ 'file' ] ) )        ? '<div class="cpis-image-file">
                                                                                <form action="'.$arr[ 'submit' ].'" method="POST" data-ajax="false">
                                                                                    '.$arr[ 'file' ].$arr[ 'button' ].'
                                                                                </form>
                                                                                    '.$arr[ 'discount' ].'
                                                                            </div>' : '' ).
                                    
                                '
                            </td>
                        </tr>
                    </table>    
                </div>
            ';    
        }
        $code .= '</div>';
        return $code;
     }
 } // End cpis_print_multiple

 if( !function_exists( 'cpis_print_carousel' ) ){
    function cpis_print_carousel( $arr ){
        $code = '';
        if( isset( $arr[ 'thumbnail' ] ) ){
            $code .= '
            <li style="width:'.$arr[ 'thumbnail' ].'px; height:'.($arr[ 'thumbnail_h' ]+5).'px; text-align:center; " >
                <a href="'.$arr[ 'link' ].'">
                    <img src="'.$arr[ 'thumbnail' ].'" style="vertical-align: middle;" alt="'.
                    ( ( isset( $arr[ 'title' ] ) ) ? $arr[ 'title' ] : '' ).
                    '" />
                </a>
            </li>
            ';
        }    
        return $code;
    }
 } // End cpis_print_carousel
?>