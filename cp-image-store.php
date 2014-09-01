<?php
/*
Plugin Name: CP Image Store with Slideshow
Plugin URI: http://wordpress.dwbooster.com/content-tools/image-store#download
Description: Image Store is an online store for the sale of image files: images, predefined pictures, clipart, drawings, vector images. For payment processing, Image Store uses PayPal, which is the most widely used payment gateway, safe and easy to use.
Version: 1.0.1
Author: CodePeople
Author URI: http://www.codepeople.net
License: GPLv2
*/

@session_start();
if(!function_exists('cpis_get_site_url')){
    function cpis_get_site_url(){
        $url_parts = parse_url(get_site_url());
        return rtrim( 
                        ((!empty($url_parts["scheme"])) ? $url_parts["scheme"] : "http")."://".
                        $_SERVER["HTTP_HOST"].
                        ((!empty($url_parts["path"])) ? $url_parts["path"] : ""),
                        "/"
                    )."/";
    }
}

// Global variable used to print the images preview in the website footer

global $cpis_images_preview, $cpis_errors, $cpis_layout, $cpis_layouts;
$cpis_errors = array();

$cpis_images_preview = '';
$cpis_upload_path = wp_upload_dir();
$cpis_layouts = array();
$cpis_layout  = array();

// CONST
define( 'CPIS_PLUGIN_DIR', dirname( __FILE__ ) );
define( 'CPIS_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'CPIS_H_URL', cpis_get_site_url() );

define( 'CPIS_UPLOAD_DIR', ( ( file_exists( CPIS_PLUGIN_DIR.'/uploads' ) ) ? CPIS_PLUGIN_DIR.'/uploads' : $cpis_upload_path[ 'basedir' ].'/cpis_uploads' ) );
define( 'CPIS_UPLOAD_URL', ( ( file_exists( CPIS_PLUGIN_DIR.'/uploads' ) ) ? CPIS_PLUGIN_URL.'/uploads' : $cpis_upload_path[ 'baseurl' ].'/cpis_uploads' ) );

define( 'CPIS_DOWNLOAD', dirname( __FILE__ ).'/downloads' );
define( 'CPIS_IMAGE_STORE_SLUG', 'image-store-menu' );
define( 'CPIS_IMAGES_URL',  CPIS_PLUGIN_URL.'/images' );
define( 'CPIS_TEXT_DOMAIN',  'cpis-text-domain' );
define( 'CPIS_SC_EXPIRE', 3); // Time for shopping cart expiration, default 3 days
define( 'CPIS_SAFE_DOWNLOAD', false);

// TABLE NAMES
define( 'CPIS_IMAGE', 'cpis_image');
define( 'CPIS_FILE',  'cpis_file');
define( 'CPIS_IMAGE_FILE',  'cpis_image_file');
define( 'CPIS_PURCHASE', 'cpis_purchase');

// INCLUDES
require_once CPIS_PLUGIN_DIR.'/includes/image.php';

/**
* Plugin activation
*/
register_activation_hook( __FILE__, 'cpis_install' );
if( !function_exists( 'cpis_install' ) ){
	function cpis_install( $networkwide ) {

        global $wpdb;
        if (function_exists('is_multisite') && is_multisite()) {
			if ($networkwide) {
            
				$old_blog = $wpdb->blogid;
                
                // Get all blog ids
                $blogids = $wpdb->get_col($wpdb->prepare("SELECT blog_id FROM $wpdb->blogs"));
                foreach ($blogids as $blog_id) {
                    switch_to_blog($blog_id);
                    
                    // Set default options
                    cpis_default_options();
                
                    // Create database structure
                    cpis_create_db();
                }
                
                switch_to_blog($old_blog);
                return;
            }
		}
		
        cpis_default_options();
        cpis_create_db();
		
	} // End cpis_install
} // End plugin activation

if( !function_exists( 'cpis_default_options' ) ){
    function cpis_default_options(){
        $cpis_defaul_options = array(
            // PayPal settings
            'paypal' => array(
                'activate_paypal'   => true,
                'activate_sandbox'  => false,
                'paypal_email'      => '',
                'currency_symbol'   => '$',
                'currency'          => 'USD',
                'language'          => 'Eng',
                'shopping_cart'     => false
            ),
            
            // Images settings
            'image' => array(
                'thumbnail'         => array(
                                            'width'  => 150,
                                            'height' => 150
                                        ),
                'intermediate'      => array(
                                            'width'  => 400,
                                            'height' => 400
                                        ),
                'unit'              => 'In',
                'set_watermark'     => true,
                'watermark_text'    => 'Image Store',
                'license'           => array(
                                        'title' => '',
                                        'description' => ''
                                       )
            ),
            
            // Display settings
            'display' => array(
                'carousel' => array(
                    'activate'          => true,
                    'autorun'           => false,
                    'transition_time'   => 5 // In seconds
                ),
                'preview' => true
            ),
            
            // Store settings
            'store' => array(
                'store_url'         => '',
                'show_search_box'   => true,
                'show_type_filters' => true,
                'show_color_filters'=> true,
                'show_author_filters'   => true,
                'show_category_filters' => true,
                'show_ordering'     => true,
                'show_pagination'   => true,
                'items_page'        => 12,     
                'columns'           => 3,
                'social_buttons'    => true,
                'pack_files'        => false,
                'download_link'     => 3,
                'download_limit'	=> 3,
                'display_promotion' => true
            ),
            
            // Payment notifications
            'notification' => array(
                'from'                  => 'put_your@email_here.com',
                'to'                    => 'put_your@email_here.com',
                'subject_payer'         => 'Thank you for your purchase...',
                'subject_seller'        => 'New product purchased...',
                'notification_payer'    => "We have received your purchase notification with the following information:\n\n%INFORMATION%\n\nThe download link is assigned an expiration time, please download the purchased product now.\n\nThank you.\n\nBest regards.",
                'notification_seller'   => "New purchase made with the following information:\n\n%INFORMATION%\n\nBest regards."
            )
        );
        update_option('cpis_options', $cpis_defaul_options);
    }
} // End cpis_default_options

if( !function_exists( 'cpis_create_db' ) ){
    function cpis_create_db(){
        global $wpdb;
			
        if( !empty( $_SESSION[ 'cpis_created_db' ] ) )
		{
			return;
		}	
		
		$_SESSION[ 'cpis_created_db' ] = true;
			
		$sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.CPIS_IMAGE." (
            id mediumint(9) NOT NULL,
            purchases mediumint(9) NOT NULL DEFAULT 0,
            preview TEXT,
            UNIQUE KEY id (id)
         );";             
        $wpdb->query($sql); 
        
        $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.CPIS_FILE." (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            path VARCHAR(255) NOT NULL,
            url VARCHAR(255) NOT NULL,
            width FLOAT,
            height FLOAT,
            price FLOAT,
            UNIQUE KEY id (id)
         );";             
        $wpdb->query($sql); 
        
        $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.CPIS_IMAGE_FILE." (
            id_image mediumint(9) NOT NULL,
            id_file mediumint(9) NOT NULL,
            UNIQUE KEY (id_image, id_file)
         );";             
        $wpdb->query($sql); 
        
        
        $sql = "CREATE TABLE IF NOT EXISTS ".$wpdb->prefix.CPIS_PURCHASE." (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            product_id mediumint(9) NOT NULL,
            purchase_id varchar(50) NOT NULL,
            date DATETIME NOT NULL,
			checking_date DATETIME,
            email VARCHAR(255) NOT NULL,
            amount FLOAT NOT NULL DEFAULT 0,
            downloads INT NOT NULL DEFAULT 0,
            paypal_data TEXT,
            note TEXT,
            UNIQUE KEY id (id)
         );";             
        $wpdb->query($sql); 
		
		$result = $wpdb->get_results("SHOW COLUMNS FROM ".$wpdb->prefix.CPIS_PURCHASE." LIKE 'checking_date'");
		if(empty($result)){
			$sql = "ALTER TABLE ".$wpdb->prefix.CPIS_PURCHASE." ADD checking_date DATETIME";
			$wpdb->query($sql);
		}    
        
		$result = $wpdb->get_results("SHOW COLUMNS FROM ".$wpdb->prefix.CPIS_PURCHASE." LIKE 'downloads'");
		if(empty($result)){
			$sql = "ALTER TABLE ".$wpdb->prefix.CPIS_PURCHASE." ADD downloads INT NOT NULL DEFAULT 0";
			$wpdb->query($sql);
		}    
            	
	}
} // End cpis_create_db

/** REGISTER POST TYPES AND TAXONOMIES **/
		
/**
* Init Image Store post types
*/
if( !function_exists( 'cpis_init_post_types' ) ){
    function cpis_init_post_types(){
        if(!post_type_exists('cpis_image')){
            // Post Types
            // Create image post type
            register_post_type( 'cpis_image', 
                array(
                    'description'		   => __('This is where you can add new image to your store.', CPIS_TEXT_DOMAIN),		
                    'capability_type'      => 'post',
                    'supports'             => array( 'title', 'editor' ),
                    'exclude_from_search'  => false,
                    'taxonomies'           => array(),
                    'public'               => true,
                    'show_ui'              => true,
                    'show_in_nav_menus'    => true,
                    'show_in_menu'    	   => CPIS_IMAGE_STORE_SLUG,
                    'labels'               => array(
                        'name'               => __( 'Images', CPIS_TEXT_DOMAIN),
                        'singular_name'      => __( 'Image', CPIS_TEXT_DOMAIN),
                        'add_new'            => __( 'Add New', CPIS_TEXT_DOMAIN),
                        'add_new_item'       => __( 'Add New Image', CPIS_TEXT_DOMAIN),
                        'edit_item'          => __( 'Edit Image', CPIS_TEXT_DOMAIN),
                        'new_item'           => __( 'New Image', CPIS_TEXT_DOMAIN),
                        'view_item'          => __( 'View Image', CPIS_TEXT_DOMAIN),
                        'search_items'       => __( 'Search Images', CPIS_TEXT_DOMAIN),
                        'not_found'          => __( 'No images found', CPIS_TEXT_DOMAIN),
                        'not_found_in_trash' => __( 'No images found in Trash', CPIS_TEXT_DOMAIN),
                        'menu_name'          => __( 'Images for Sale', CPIS_TEXT_DOMAIN),
                        'parent_item_colon'  => '',
                    ),
                    'query_var'            => true,
                    'has_archive'		   => true,	
                    'rewrite'              => false
                )
            );			
            
            add_filter('manage_cpis_image_posts_columns' , 'cpis_image_columns');
            add_action('manage_cpis_image_posts_custom_column', 'cpis_image_columns_data', 2 );
        }    
    }
}// End cpis_init_post_types

/**
* Init Image Store taxonomies
*/
if( !function_exists( 'cpis_init_taxonomies' ) ){
    function cpis_init_taxonomies(){
        
        if ( !taxonomy_exists('cpis_category') ) {
            // Create Author taxonomy
            register_taxonomy(
                'cpis_category',
                array(
                    'cpis_image'
                ),
                array(
                    'hierarchical'	=> true,
                    'label' 	   	=> __('Images Categories', CPIS_TEXT_DOMAIN),
                    'labels' 		=> array(
                        'name' 				=> __( 'Images Categories', CPIS_TEXT_DOMAIN),
                        'singular_name' 	=> __( 'Images Category', CPIS_TEXT_DOMAIN),
                        'search_items' 		=> __( 'Search by Categories', CPIS_TEXT_DOMAIN),
                        'all_items' 		=> __( 'All Images Categories', CPIS_TEXT_DOMAIN),
                        'edit_item' 		=> __( 'Edit Category', CPIS_TEXT_DOMAIN),
                        'update_item' 		=> __( 'Update Category', CPIS_TEXT_DOMAIN),
                        'add_new_item' 		=> __( 'Add New Image Category', CPIS_TEXT_DOMAIN),
                        'new_item_name' 	=> __( 'New Category Name', CPIS_TEXT_DOMAIN),
                        'menu_name'			=> __( 'Images Categories', CPIS_TEXT_DOMAIN)
                    ),
                    'public'  => true,
                    'show_ui' => true,
                    'show_admin_column' => true,
                    'query_var' => true,
                    'sort'  => false
                )
            );
        }
        
        if ( !taxonomy_exists('cpis_author') ) {
            // Create Author taxonomy
            register_taxonomy(
                'cpis_author',
                array(
                    'cpis_image'
                ),
                array(
                    'hierarchical'	=> false,
                    'label' 	   	=> __('Authors', CPIS_TEXT_DOMAIN),
                    'labels' 		=> array(
                        'name' 				=> __( 'Authors', CPIS_TEXT_DOMAIN),
                        'singular_name' 	=> __( 'Author', CPIS_TEXT_DOMAIN),
                        'search_items' 		=> __( 'Search by Authors', CPIS_TEXT_DOMAIN),
                        'all_items' 		=> __( 'All Authors', CPIS_TEXT_DOMAIN),
                        'edit_item' 		=> __( 'Edit Author', CPIS_TEXT_DOMAIN),
                        'update_item' 		=> __( 'Update Author', CPIS_TEXT_DOMAIN),
                        'add_new_item' 		=> __( 'Add New Author', CPIS_TEXT_DOMAIN),
                        'new_item_name' 	=> __( 'New Author Name', CPIS_TEXT_DOMAIN),
                        'menu_name'			=> __( 'Authors', CPIS_TEXT_DOMAIN)
                    ),
                    'public'  => true,
                    'show_ui' => true,
                    'show_admin_column' => true,
                    'query_var' => true
                )
            );
        }
        
        if ( !taxonomy_exists('cpis_color') ) {
            // Create Color taxonomy
            register_taxonomy(
                'cpis_color',
                array(
                    'cpis_image'
                ),
                array(
                    'hierarchical'	=> false,
                    'label' 	   	=> __('Colors Scheme', CPIS_TEXT_DOMAIN),
                    'labels' 		=> array(
                        'name' 				=> __( 'Colors Schemes', CPIS_TEXT_DOMAIN),
                        'singular_name' 	=> __( 'Color Scheme', CPIS_TEXT_DOMAIN),
                        'search_items' 		=> __( 'Search by Colors', CPIS_TEXT_DOMAIN),
                        'all_items' 		=> __( 'All Colors Schemes', CPIS_TEXT_DOMAIN),
                        'edit_item' 		=> __( 'Edit Color Scheme', CPIS_TEXT_DOMAIN),
                        'update_item' 		=> __( 'Update Color Scheme', CPIS_TEXT_DOMAIN),
                        'add_new_item' 		=> __( 'Add New Color Scheme', CPIS_TEXT_DOMAIN),
                        'new_item_name' 	=> __( 'New Color Scheme Name', CPIS_TEXT_DOMAIN),
                        'menu_name'			=> __( 'Colors Schemes', CPIS_TEXT_DOMAIN)
                    ),
                    'public'  => true,
                    'show_ui' => true,
                    'show_admin_column' => true,
                    'query_var' => true
                )
            );
            
            wp_insert_term(
              'Black and white',
              'cpis_color'
            );
            wp_insert_term(
              'Full color',
              'cpis_color'
            );
        }
        
        if ( !taxonomy_exists('cpis_type') ) {
            // Register artist taxonomy
            register_taxonomy(
                'cpis_type',
                array(
                    'cpis_image'
                ),
                array(
                    'hierarchical'	=> false,
                    'label' 	   	=> __('Types', CPIS_TEXT_DOMAIN),
                    'labels' 		=> array(
                        'name' 				=> __( 'Types', CPIS_TEXT_DOMAIN),
                        'singular_name' 	=> __( 'Type', CPIS_TEXT_DOMAIN),
                        'search_items' 		=> __( 'Search Types', CPIS_TEXT_DOMAIN),
                        'all_items' 		=> __( 'All Types', CPIS_TEXT_DOMAIN),
                        'edit_item' 		=> __( 'Edit Type', CPIS_TEXT_DOMAIN),
                        'update_item' 		=> __( 'Update Type', CPIS_TEXT_DOMAIN),
                        'add_new_item' 		=> __( 'Add New Type', CPIS_TEXT_DOMAIN),
                        'new_item_name' 	=> __( 'New Type Name', CPIS_TEXT_DOMAIN),
                        'menu_name'			=> __( 'Types', CPIS_TEXT_DOMAIN)
                    ),
                    'public' => true,
                    'show_ui' => true,
                    'show_admin_column' => true,
                    'query_var' => true
                )
            );
            
            wp_insert_term(
              'Photo',
              'cpis_type'
            );
            
            wp_insert_term(
              'Clip art',
              'cpis_type'
            );
            
            wp_insert_term(
              'Line drawing',
              'cpis_type'
            );
        }
        
        do_action( 'image_store_register_taxonomy' );
    } 
} // End cpis_init_taxonomies

// The plugin ini
add_action('init', 'cpis_init', 1);
if( !function_exists( 'cpis_init' ) ){
    function cpis_init(){
        global $cpis_layout;
        
        load_plugin_textdomain(CPIS_TEXT_DOMAIN, false, dirname(plugin_basename(__FILE__)) . '/languages/');
        
        // Load selected layout
        if ( false !== get_option( 'cpis_layout' ) )
        {
            $cpis_layout = get_option( 'cpis_layout' );
        }

        // Create post types
        cpis_init_post_types();
        
        // Create taxonomies
        cpis_init_taxonomies();
        
        add_action( 'widgets_init', 'cpis_load_widgets' );
        
        if( !is_admin() ){
            add_action( 'wp_footer', 'cpis_footer' );
            add_filter('get_pages','cpis_exclude_pages');
            if( isset( $_REQUEST ) && isset( $_REQUEST[ 'cpis-action' ] ) ){
                $options = get_option( 'cpis_options' );
                switch( strtolower( $_REQUEST[ 'cpis-action' ] ) ){
                    case 'buynow':
                        include CPIS_PLUGIN_DIR.'/includes/submit.php';
                        exit;
                    break;
                    
                    case 'ipn':
                        include CPIS_PLUGIN_DIR.'/includes/ipn.php';
                        exit;
                    break;
                    
                    case 'f-download':
                        cpis_download_file();
                    break;
                }
            }
            add_shortcode( 'codepeople-image-store', 'cpis_replace_shortcode' );
            add_shortcode( 'codepeople-image-store-product', 'cpis_replace_product_shortcode' );
            add_filter( 'the_content', 'cpis_the_content', 1 );
			add_filter( 'the_excerpt', 'cpis_the_excerpt', 1 );
        }
    }
} // End cpis_ini

if( !function_exists( 'cpis_load_layouts' ) ){
    /**
    * Get the list of available layouts
    */
    function cpis_load_layouts(){	
        global $cpis_layouts;
        
        $tpls_dir = dir( CPIS_PLUGIN_DIR.'/layouts' );
        while( false !== ( $entry = $tpls_dir->read() ) ) 
        {    
            if ( $entry != '.' && $entry != '..' && is_dir( $tpls_dir->path.'/'.$entry ) && file_exists( $tpls_dir->path.'/'.$entry.'/config.ini' ) )
            {
                if( ( $ini_array = parse_ini_file( $tpls_dir->path.'/'.$entry.'/config.ini' ) ) !== false )
                {
                    if( !empty( $ini_array[ 'style_file' ] ) ) $ini_array[ 'style_file' ] = CPIS_PLUGIN_URL.'/layouts/'.$entry.'/'.$ini_array[ 'style_file' ];
                    if( !empty( $ini_array[ 'script_file' ] ) ) $ini_array[ 'script_file' ] = CPIS_PLUGIN_URL.'/layouts/'.$entry.'/'.$ini_array[ 'script_file' ];
                    if( !empty( $ini_array[ 'thumbnail' ] ) ) $ini_array[ 'thumbnail' ] = CPIS_PLUGIN_URL.'/layouts/'.$entry.'/'.$ini_array[ 'thumbnail' ];
                    $cpis_layouts[ $ini_array[ 'id' ] ] = $ini_array;
                }
            }			
        }
    }
}

if( !function_exists( 'cpis_load_widgets' ) ){
    function cpis_load_widgets(){
        register_widget( 'CPISProductWidget' );
    }
}        


if( !function_exists( 'cpis_footer' ) ){
    function cpis_footer(){
        global $cpis_images_preview;
        print $cpis_images_preview;
    }
}
        
add_action('admin_init', 'cpis_admin_init', 1);
if( !function_exists( 'cpis_admin_init' ) ){
    
    function _cpis_create_pages( $slug, $title ){
		if( session_id() == "" ) session_start();
		if( isset( $_SESSION[ $slug ] ) ) return $_SESSION[ $slug ];
		
		$page = get_page_by_path( $slug ); 
		if( is_null( $page ) ){
			if( is_admin() ){
				if( false != ($id = wp_insert_post(
							array(
								'comment_status' => 'closed',
								'post_name' => $slug,
								'post_title' => __( $title, CPIS_TEXT_DOMAIN ),
								'post_status' => 'publish',
								'post_type' => 'page'
							)
						)
					)    
				){
					$_SESSION[ $slug ] =  get_permalink($id);
				}
			}    
		}else{
			if( is_admin() && $page->post_status != 'publish' )
			{
				$page->post_status = 'publish';
				wp_update_post( $page );
			}
			$_SESSION[ $slug ] =  get_permalink($page->ID);
		}	
		
		$_SESSION[ $slug ] = ( isset( $_SESSION[ $slug ] ) ) ? $_SESSION[ $slug ] : CPIS_H_URL;
		return $_SESSION[ $slug ];
	}
    
    function cpis_admin_init(){
		global $wpdb;
		
        $plugin = plugin_basename(__FILE__);
        add_filter("plugin_action_links_".$plugin, 'cpis_customAdjustmentsLink');
        
        // Create database
        cpis_create_db();
		
		if( isset( $_REQUEST[ 'cpis-action' ] ) && $_REQUEST[ 'cpis-action' ] == 'paypal-data' ){
			if( isset( $_REQUEST[ 'data' ] ) && isset( $_REQUEST[ 'from' ] ) && isset( $_REQUEST[ 'to' ] ) ){
				$where = 'DATEDIFF(date, "'.$_REQUEST[ 'from' ].'")>=0 AND DATEDIFF(date, "'.$_REQUEST[ 'to' ].'")<=0';
				switch( $_REQUEST[ 'data' ] ){
					case 'residence_country':
						print cpis_getFromPayPalData( array( 'residence_country' => 'residence_country'), 'COUNT(*) AS count', '', $where, array( 'residence_country' ), array( 'count' => 'DESC' ) );
					break;	
					case 'mc_currency':
						print cpis_getFromPayPalData( array( 'mc_currency' => 'mc_currency'), 'SUM(amount) AS sum', '', $where, array( 'mc_currency' ), array( 'sum' => 'DESC' ) );
					break;	
					case 'product_name':
						$from   = $wpdb->posts.' AS posts,'.$wpdb->prefix.CPIS_IMAGE_FILE.' AS image_file';
						$where .= ' AND product_id = image_file.id_file AND posts.ID = image_file.id_image';
						
						$json =  cpis_getFromPayPalData( array( 'mc_currency' => 'mc_currency'), 'SUM(amount) AS sum, post_title', $from, $where, array( 'product_id', 'mc_currency' ) );
						$obj = json_decode( $json );
						foreach( $obj as $key => $value){
							$obj[ $key ]->post_title .= ' ['.$value->mc_currency.']';
						}
						print json_encode( $obj );
					break;
				}
			}
			exit;
		}
            
        // Init the metaboxs for images
		add_meta_box( 'cpis_image_metabox', __( "Image's data", CPIS_TEXT_DOMAIN ), 'cpis_image_metabox', 'cpis_image', 'normal', 'high' );
        
        // Save images
        add_action( 'save_post', 'cpis_save_image' );
        
        add_action( 'admin_head','cpis_removemediabuttons' );
        
        // Changing upload directory for cpis_image
        add_filter( 'upload_dir', 'cpis_upload_dir' );
        
        // Set a new media button for store insertion
        add_action( 'media_buttons', 'cpis_store_button', 100 );
        
        _cpis_create_pages( 'cpis-download-page', 'Download Page' );
        
        if( isset( $_REQUEST[ 'cpis-action' ] ) ){
            switch( strtolower( $_REQUEST[ 'cpis-action' ] ) ){
                case 'remove-image':
                    if( !empty( $_REQUEST[ 'image' ] ) ){
                        print cpis_remove_image( $_REQUEST[ 'image' ] );
                    }else{
                        print '{ "error" : "Image ID is required" }';
                    }    
                    exit;
                break;
            }
        }
    }
} // End cpis_admin_ini

if( !function_exists( 'cpis_customAdjustmentsLink' ) ){
    function cpis_customAdjustmentsLink( $links ){
        $customAdjustments_link = '<a href="http://wordpress.dwbooster.com/contact-us" target="_blank">'.__( 'Request custom changes', CPIS_TEXT_DOMAIN ).'</a>';
        array_unshift( $links, $customAdjustments_link );
        $help_link = '<a href="http://wordpress.dwbooster.com/content-tools/image-store#download" target="_blank">'.__( 'Help', CPIS_TEXT_DOMAIN ).'</a>';
        array_unshift( $links, $help_link );
        return $links;
    }
} // End cpis_customAdjustmentsLink

if( !function_exists( 'cpis_store_button' ) ){
    function cpis_store_button(){
        global $post;
			
        if( isset( $post ) && $post->post_type != 'cpis_image' ){
            print '<a href="javascript:cpis_insert_store();" title="'.__( 'Insert Image Store', CPIS_TEXT_DOMAIN ).'"><img src="'.CPIS_PLUGIN_URL.'/images/image-store-icon.png'.'" alt="'.__( 'Insert Image Store', CPIS_TEXT_DOMAIN ).'" /></a>';
            
            print '<a href="javascript:cpis_insert_product_window();" title="'.__( 'Insert Image Product', CPIS_TEXT_DOMAIN ).'"><img src="'.CPIS_PLUGIN_URL.'/images/image-store-insert-product.png'.'" alt="'.__( 'Insert Image Product', CPIS_TEXT_DOMAIN ).'" /></a>';
        }
    }
} // End cpis_store_button
			
add_action('admin_menu', 'cpis_menu_links', 10);
if( !function_exists( 'cpis_menu_links' ) ){
    function cpis_menu_links(){
        if(is_admin()){
            
            add_options_page('Image Store', 'Image Store', 'manage_options', CPIS_IMAGE_STORE_SLUG.'-settings-page', 'cpis_settings_page');
            
            add_menu_page('Image Store', 'Image Store', 'edit_pages', CPIS_IMAGE_STORE_SLUG, null, CPIS_IMAGES_URL.'/image-store-menu-icon.png');
            
            // Settings Submenu
            add_submenu_page(CPIS_IMAGE_STORE_SLUG, 'Image Store Settings', 'Store Settings', 'edit_pages', CPIS_IMAGE_STORE_SLUG.'-settings', 'cpis_settings_page');
            
            // Sales report submenu
            add_submenu_page(CPIS_IMAGE_STORE_SLUG, 'Image Store Sales Report', 'Sales Report', 'edit_pages', CPIS_IMAGE_STORE_SLUG.'-reports', 'cpis_reports_page');
         
            //Submenu for taxonomies
            add_submenu_page(CPIS_IMAGE_STORE_SLUG, __( 'Author', CPIS_TEXT_DOMAIN), __( 'Authors', CPIS_TEXT_DOMAIN), 'edit_pages', 'edit-tags.php?taxonomy=cpis_author');
            
            add_submenu_page(CPIS_IMAGE_STORE_SLUG, __( 'Color Scheme', CPIS_TEXT_DOMAIN), __( 'Color Schemes', CPIS_TEXT_DOMAIN), 'edit_pages', 'edit-tags.php?taxonomy=cpis_color');
            
            add_submenu_page(CPIS_IMAGE_STORE_SLUG, __( 'Type', CPIS_TEXT_DOMAIN), __( 'Types', CPIS_TEXT_DOMAIN), 'edit_pages', 'edit-tags.php?taxonomy=cpis_type');
            
            add_submenu_page(CPIS_IMAGE_STORE_SLUG, __( 'Category', CPIS_TEXT_DOMAIN), __( 'Categories', CPIS_TEXT_DOMAIN), 'edit_pages', 'edit-tags.php?taxonomy=cpis_category');
            
            add_action( 'parent_file', 'cpis_tax_menu_correction' );
            
            // Remove the taxonomies box from side column
            remove_meta_box( 'tagsdiv-cpis_type', 'cpis_image', 'side' );
            remove_meta_box( 'tagsdiv-cpis_color', 'cpis_image', 'side' );
            remove_meta_box( 'tagsdiv-cpis_author', 'cpis_image', 'side' );
		
        }
    }
} // End cpis_ini

// highlight the proper top level menu for taxonomies submenus
if( !function_exists( 'cpis_tax_menu_correction' ) ){
    function cpis_tax_menu_correction( $parent_file ) {
        global $current_screen;
        $taxonomy = $current_screen->taxonomy;
        if ($taxonomy == 'cpis_author' || $taxonomy == 'cpis_color' || $taxonomy == 'cpis_type')
            $parent_file = CPIS_IMAGE_STORE_SLUG;
        return $parent_file;
    } // End tax_menu_correction
} // End cpis_tax_menu_correction

if( !function_exists( 'cpis_exclude_pages' ) ){
    function cpis_exclude_pages( $pages ){
        $exclude = array();
        $length = count( $pages );
        
        $p = get_page_by_path( 'cpis-download-page' );
        if( !is_null( $p ) ) $exclude[] = $p->ID;
        
        for ( $i=0; $i<$length; $i++ ) {
            $page = &$pages[$i];
            
            if ( isset( $page ) && in_array( $page->ID, $exclude ) ) {
                // Finally, delete something(s)
                unset( $pages[$i] );
            }
        }
        
        return $pages;
    }
} // End cpis_exclude_pages

/**
 * Settings form of store
 */
 if( !function_exists( 'cpis_settings_page' ) ){
    function cpis_settings_page(){
        global $wpdb, $cpis_layouts, $cpis_layout;
        
        cpis_load_layouts();

        $options = get_option( 'cpis_options' );
        
        if (isset($_POST['cpis_settings']) && wp_verify_nonce( $_POST['cpis_settings'], plugin_basename( __FILE__ ) ) ){
            $noptions = array();
            
            $cpis_currency_symbol   = trim( $_POST['cpis_currency_symbol'] );
            $cpis_currency          = trim( $_POST['cpis_currency'] );
            $cpis_language          = trim( $_POST['cpis_language'] );
            
            if( !empty( $_POST[ 'cpis_layout' ] ) )
            {
                $cpis_layout = $cpis_layouts[ $_POST[ 'cpis_layout' ] ];
                update_option( 'cpis_layout', $cpis_layout );
            }
            else
            {
                delete_option( 'cpis_layout' );
                $cpis_layout = array();
            }

            $noptions['paypal'] = array(
                'activate_paypal'   => ( ( isset( $_POST['cpis_activate_paypal'] ) ) ? true : false ),
                'activate_sandbox'  => ( ( isset( $_POST['cpis_activate_sandbox'] ) ) ? true : false ),
                'paypal_email'      => $_POST['cpis_paypal_email'],
                'currency_symbol'   => ( ( !empty( $cpis_currency_symbol ) ) ? $_POST['cpis_currency_symbol'] : '$' ),
                'currency'          => ( ( !empty( $cpis_currency ) ) ? $_POST['cpis_currency'] : 'USD' ),
                'language'          => ( ( !empty( $cpis_language ) ) ? $_POST['cpis_language'] : 'Eng' ),
                'shopping_cart'     => false
            );
            
            $thumbnail_w = trim( $_POST[ 'cpis_thumbnail_width' ] );
            $thumbnail_h = trim( $_POST[ 'cpis_thumbnail_height' ] );
            
            $intermediate_w = trim( $_POST[ 'cpis_intermediate_width' ] );
            $intermediate_h = trim( $_POST[ 'cpis_intermediate_height' ] );
            
            $noptions['image'] = array(
                'unit'              => $_POST['cpis_unit'],
                'set_watermark'     => false,
                'watermark_text'    => 'Image Store',
                'license'           => array(
                                            'title'       => $_POST['cpis_license_title'],
                                            'description' => $_POST['cpis_license_description']
                                       ),
                'thumbnail'         => array(
                                            'width'  => ( ( is_numeric( $thumbnail_w ) && $thumbnail_w > 0 ) ? $thumbnail_w : $options[ 'image' ][ 'thumbnail' ][ 'width' ] ),
                                            'height' =>  ( ( is_numeric( $thumbnail_h ) && $thumbnail_h > 0 ) ? $thumbnail_h : $options[ 'image' ][ 'thumbnail' ][ 'height' ] )
                                        ),
                'intermediate'      => array(
                                            'width'  =>  ( ( is_numeric( $intermediate_w ) && $intermediate_w > 0 ) ? $intermediate_w : $options[ 'image' ][ 'intermediate' ][ 'width' ] ),
                                            'height' => ( ( is_numeric( $intermediate_h ) && $intermediate_h > 0 ) ? $intermediate_h : $options[ 'image' ][ 'intermediate' ][ 'height' ] )
                                        )
            );
            
            $noptions['display'] = array(
                'carousel' => array(
                    'activate'          => ( ( isset( $_POST['cpis_activate_carousel'] ) ) ? true : false ),
                    'autorun'           => ( ( isset( $_POST['cpis_autorun_carousel'] ) ) ? true : false ),
                    'transition_time'   => $_POST['cpis_carousel_transition_time'],
                ),
                'preview' => ( ( isset( $_POST['cpis_activate_preview'] ) ) ? true : false )
            );
            
            $noptions['store'] = array(
                'store_url'          => $_POST['cpis_store_url'],
                'show_search_box' => ( ( isset( $_POST['cpis_show_search_box'] ) ) ? true : false ),
                'show_color_filters' => ( ( isset( $_POST['cpis_show_color_filters'] ) ) ? true : false ),
                'show_type_filters'  => ( ( isset( $_POST['cpis_show_type_filters'] ) ) ? true : false ),
                'show_author_filters'    => ( ( isset( $_POST['cpis_show_author_filters'] ) ) ? true : false ),
                'show_category_filters'  => ( ( isset( $_POST['cpis_show_category_filters'] ) ) ? true : false ),
                'show_ordering'      => ( ( isset( $_POST['cpis_show_ordering'] ) ) ? true : false ),
                'show_pagination'    => ( ( isset( $_POST['cpis_show_pagination'] ) ) ? true : false ),
                'items_page'         => $_POST['cpis_items_page'],     
                'social_buttons'     => ( ( isset( $_POST['cpis_social_buttons'] ) ) ? true : false ),
                'columns'            => $_POST['cpis_columns'],
                'pack_files'         => false,
                'download_link'      => $_POST['cpis_download_link'],
                'download_limit'     => $_POST['cpis_download_limit'],
                'display_promotion'  => false
            );

            $noptions['notification'] = array(
                'from'                  => $_POST['cpis_from'],
                'to'                    => $_POST['cpis_to'],
                'subject_payer'         => $_POST['cpis_subject_payer'],
                'subject_seller'        => $_POST['cpis_subject_seller'],
                'notification_payer'    => $_POST['cpis_notification_payer'],
                'notification_seller'   => $_POST['cpis_notification_seller']
            );
				
            update_option( 'cpis_options', $noptions );
			update_option( 'cpis_safe_download', ( ( isset( $_POST[ 'cpis_safe_download' ] ) ) ? true : false ) );
			
            $options = $noptions;
?>				
            <div class="updated" style="margin:5px 0;"><strong><?php _e("Settings Updated", CPIS_TEXT_DOMAIN); ?></strong></div>
<?php		
            if ( empty( $noptions['paypal']['paypal_email'] ) )
                print '<div class="updated" style="margin:5px 0;"><strong>'.__("If you want to sell the images, must enter the email associated to your PayPal account.", CPIS_TEXT_DOMAIN).'</strong></div>';
        }
        
        $options = get_option( 'cpis_options' );
?>        
        <p style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;">
            For reporting an issue or to request a customization, <a href="http://wordpress.dwbooster.com/contact-us" target="_blank">CLICK HERE</a>
			<br />If you want test the premium version of Image Store go to the following links:<br/> <a href="http://demos.net-factor.com/image-store/wp-login.php" target="_blank">Administration area: Click to access the administration area demo</a><br/> 
			<a href="http://demos.net-factor.com/image-store/" target="_blank">Public page: Click to access the Store Page</a>
        </p>

        <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
            <input type="hidden" name="tab" value="settings" />
                
            <!-- STORE CONFIG -->
            <div class="postbox">
                <h3 class='hndle' style="padding:5px;"><span><?php _e( 'Store page config', CPIS_TEXT_DOMAIN ); ?></span></h3>
                <div class="inside">
                    <table class="form-table">
                        <tr valign="top">
                            <th><?php _e( 'URL of store page', CPIS_TEXT_DOMAIN ); ?></th>
                            <td>
                                <input type="text" name="cpis_store_url" size="40" value="<?php echo esc_attr( $options[ 'store' ]['store_url'] ); ?>" />
                                <br />
                                <em><?php _e( 'Set the URL of page where the store was inserted', CPIS_TEXT_DOMAIN ); ?></em>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e('Store layout', CPIS_TEXT_DOMAIN); ?></th>
                            <td>
                                <select name="cpis_layout" id="cpis_layout">
                                    <option value=""><?php _e( 'Default layout', CPIS_TEXT_DOMAIN ); ?></option>
                                <?php
                                    foreach( $cpis_layouts as $id => $layout )
                                    {
                                        print '<option value="'.$id.'" '.( ( !empty( $cpis_layout ) && $id == $cpis_layout[ 'id' ] ) ? 'SELECTED' : '' ).' thumbnail="'.$layout[ 'thumbnail' ].'">'.$layout[ 'title' ].'</option>';
                                    }
                                ?>
                                </select>
                                <div id="cpis_layout_thumbnail">
                                <?php
                                    if( !empty( $cpis_layout ) )
                                    {
                                        print '<img src="'.$cpis_layout[ 'thumbnail' ].'" title="'.$cpis_layout[ 'title' ].'" />';
                                    }
                                ?>
                                </div>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e( 'Display a search box', CPIS_TEXT_DOMAIN ); ?></th>
                            <td><input type="checkbox" name="cpis_show_search_box" size="40" value="1" <?php if ( isset( $options[ 'store' ][ 'show_search_box' ] ) && $options[ 'store' ][ 'show_search_box' ] ) echo 'checked'; ?> /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e( 'Allow filtering by type', CPIS_TEXT_DOMAIN ); ?></th>
                            <td><input type="checkbox" name="cpis_show_type_filters" size="40" value="1" <?php if ( isset( $options[ 'store' ][ 'show_type_filters' ] ) && $options[ 'store' ][ 'show_type_filters' ] ) echo 'checked'; ?> /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e( 'Allow filtering by color', CPIS_TEXT_DOMAIN ); ?></th>
                            <td><input type="checkbox" name="cpis_show_color_filters" size="40" value="1" <?php if ( isset( $options[ 'store' ][ 'show_color_filters'] ) && $options[ 'store' ][ 'show_color_filters'] ) echo 'checked'; ?> /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e( 'Allow filtering by author', CPIS_TEXT_DOMAIN ); ?></th>
                            <td><input type="checkbox" name="cpis_show_author_filters" size="40" value="1" <?php if ( isset( $options[ 'store' ][ 'show_author_filters'] ) && $options[ 'store' ][ 'show_author_filters'] ) echo 'checked'; ?> /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e( 'Allow filtering by category', CPIS_TEXT_DOMAIN ); ?></th>
                            <td><input type="checkbox" name="cpis_show_category_filters" size="40" value="1" <?php if ( isset( $options[ 'store' ][ 'show_category_filters'] ) && $options[ 'store' ][ 'show_category_filters'] ) echo 'checked'; ?> /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e( 'Allow pagination', CPIS_TEXT_DOMAIN ); ?></th>
                            <td><input type="checkbox" name="cpis_show_pagination" size="40" value="1" <?php if ( isset( $options[ 'store' ][ 'show_pagination' ] ) && $options[ 'store' ][ 'show_pagination' ] ) echo 'checked'; ?> /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e('Items per page', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" name="cpis_items_page" value="<?php echo esc_attr( $options[ 'store' ][ 'items_page' ] ); ?>" /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e('Number of columns', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" name="cpis_columns" value="<?php echo esc_attr( $options[ 'store' ][ 'columns' ] ); ?>" /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e('Show buttons for sharing in social networks', CPIS_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="checkbox" name="cpis_social_buttons" <?php echo ( ( isset( $options[ 'store' ][ 'social_buttons' ] ) && $options[ 'store' ][ 'social_buttons' ] ) ? 'CHECKED' : '' ); ?> /><br />
                                <em><?php _e('The option enables the buttons for share the pages of songs and collections in social networks', CPIS_TEXT_DOMAIN); ?></em>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e('Allow sorting results', CPIS_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="checkbox" name="cpis_show_ordering" <?php echo ( ( isset( $options[ 'store' ][ 'show_ordering' ] ) && $options[ 'store' ][ 'show_ordering' ] ) ? 'CHECKED' : '' ); ?> /><br />
                                <em><?php _e( 'The option enables the buttons for share the pages of songs and collections in social networks', CPIS_TEXT_DOMAIN ); ?></em>
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
            
            <!-- IMAGES CONFIG -->
            <div class="postbox">
                <h3 class='hndle' style="padding:5px;"><span><?php _e( 'Images config', CPIS_TEXT_DOMAIN ); ?></span></h3>
                <div class="inside">
                    <table class="form-table">
                        <tr valign="top">
                            <th><?php _e( 'Units', CPIS_TEXT_DOMAIN ); ?></th>
                            <td><input type="text" name="cpis_unit" value=<?php echo esc_attr( $options[ 'image' ][ 'unit' ] ); ?> /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e( 'Set watermark', CPIS_TEXT_DOMAIN ); ?></th>
                            <td><input type="checkbox" disabled />
                                <br /><?php _e( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/image-store#download" target="_blank" >premium version</a> of plugin.', CPIS_TEXT_DOMAIN ); ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e('Watermark text', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" placeholder="Image Store" disabled />
                            <br /><?php _e( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/image-store#download" target="_blank" >premium version</a> of plugin.', CPIS_TEXT_DOMAIN ); ?>
                            </td>
                        </tr>
                        
                        <tr>
                            <td colspan="2">
                                <h2><?php _e( 'Thumbnail', CPIS_TEXT_DOMAIN ); ?></h2>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th><?php _e('Width', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" name="cpis_thumbnail_width" value="<?php echo esc_attr( $options[ 'image' ][ 'thumbnail' ]['width'] ); ?>" /> px</td>
                        </tr>
                        
                        <tr valign="top">
                            <th><?php _e('Height', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" name="cpis_thumbnail_height" value="<?php echo esc_attr( $options[ 'image' ][ 'thumbnail' ]['height'] ); ?>" /> px</td>
                        </tr>
                        
                        <tr>
                            <td colspan="2">
                                <h2><?php _e( 'Intermediate', CPIS_TEXT_DOMAIN ); ?></h2>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th><?php _e('Width', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" name="cpis_intermediate_width" value="<?php echo esc_attr( $options[ 'image' ][ 'intermediate' ]['width'] ); ?>" /> px</td>
                        </tr>
                        
                        <tr valign="top">
                            <th><?php _e('Height', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" name="cpis_intermediate_height" value="<?php echo esc_attr( $options[ 'image' ][ 'intermediate' ]['height'] ); ?>" /> px</td>
                        </tr>
                        
                        
                        <tr>
                            <td colspan="2">
                                <h2><?php _e( 'Images license' ); ?></h2>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th><?php _e('Images license title', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" name="cpis_license_title" value="<?php echo esc_attr( $options[ 'image' ][ 'license' ]['title'] ); ?>" /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e('Images license description', CPIS_TEXT_DOMAIN); ?></th>
                            <td><textarea name="cpis_license_description" cols="60" rows="5"><?php echo esc_textarea( $options[ 'image' ][ 'license' ]['description'] ); ?></textarea></td>
                        </tr>
                        
                        <tr>
                            <td colspan="2">
                                <h2><?php _e( 'Images effects' ); ?></h2>
                            </td>
                        </tr>
                        
                        <tr valign="top">
                            <th><?php _e('Show carousel of related images', CPIS_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="checkbox" name="cpis_activate_carousel" <?php echo ( ( $options[ 'display' ][ 'carousel' ][ 'activate' ] ) ? 'CHECKED' : '' ); ?> /><br />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e('Set carousel autorun', CPIS_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="checkbox" name="cpis_autorun_carousel" <?php echo ( ( $options[ 'display' ][ 'carousel' ][ 'autorun' ] ) ? 'CHECKED' : '' ); ?> /><br />
                            </td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e('Carousel transition time', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" name="cpis_carousel_transition_time" value="<?php echo esc_attr( $options[ 'display' ][ 'carousel' ]['transition_time'] ); ?>" /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e('Display preview on mouse over', CPIS_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="checkbox" name="cpis_activate_preview" <?php echo ( ( $options[ 'display' ][ 'preview' ] ) ? 'CHECKED' : '' ); ?> />
                            </td>
                        </tr>
                    </table>
                </div>
            </div>
                
            <!-- PAYPAL BOX -->
            <div class="postbox">
                <h3 class='hndle' style="padding:5px;"><span><?php _e( 'Paypal Payment Configuration', CPIS_TEXT_DOMAIN ); ?></span></h3>
                <div class="inside">
					<table class="form-table">
                        <tr valign="top">        
                        <th scope="row"><?php _e( 'Enable Paypal Payments?', CPIS_TEXT_DOMAIN ); ?></th>
                        <td><input type="checkbox" name="cpis_activate_paypal" value="1" <?php if ( $options[ 'paypal' ][ 'activate_paypal' ] ) echo 'checked'; ?> /></td>
                        </tr>    
                    
                        <tr valign="top">        
                        <th scope="row"><?php _e( 'Enable Paypal Sandbox?', CPIS_TEXT_DOMAIN ); ?></th>
                        <td><input type="checkbox" name="cpis_activate_sandbox" value="1" <?php if ( $options[ 'paypal' ][ 'activate_sandbox' ] ) echo 'checked'; ?> /><br />
                        <?php _e( 'For testing the selling process, use the PayPal sandbox, but don\'t forget uncheck it in the final website', CPIS_TEXT_DOMAIN ); ?>
                        </td>
                        </tr>    
                    
                        <tr valign="top">        
                        <th scope="row"><?php _e( 'Paypal email', CPIS_TEXT_DOMAIN ); ?></th>
                        <td><input type="text" name="cpis_paypal_email" size="40" value="<?php echo esc_attr( $options[ 'paypal' ]['paypal_email'] ); ?>" /><br />
                        <?php _e("If you want to sell the images, must enter the email associated to your PayPal account.", CPIS_TEXT_DOMAIN); ?>
                        </td>
                        </tr>
                         
                        <tr valign="top">
                        <th scope="row"><?php _e( 'Currency', CPIS_TEXT_DOMAIN ); ?></th>
                        <td><input type="text" name="cpis_currency" value="<?php echo esc_attr( $options[ 'paypal' ][ 'currency' ] ); ?>" /></td>
                        </tr>
                        
                        <tr valign="top">
                        <th scope="row"><?php _e( 'Currency Symbol', CPIS_TEXT_DOMAIN ); ?></th>
                        <td><input type="text" name="cpis_currency_symbol" value="<?php echo esc_attr( $options[ 'paypal' ][ 'currency_symbol' ] ); ?>" /></td>
                        </tr>
                        
                        <tr valign="top">
                        <th scope="row"><?php _e( 'Paypal language', CPIS_TEXT_DOMAIN ); ?></th>
                        <td><input type="text" name="cpis_language" value="<?php echo esc_attr( $options [ 'paypal' ][ 'language' ] ); ?>" /></td>
                        </tr>  
 							
                        <tr valign="top">
                        <th scope="row"><?php _e( 'Use shopping cart', CPIS_TEXT_DOMAIN ); ?></th>
                        <td><input type="checkbox" disabled />
                        <br /><?php _e( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/image-store#download" target="_blank" >premium version</a> of plugin.', CPIS_TEXT_DOMAIN ); ?>
                        </td>
                        </tr> 
                        
                        <tr valign="top">
                        <th scope="row"><?php _e( 'Download link valid for', CPIS_TEXT_DOMAIN ); ?></th>
                        <td><input type="text" name="cpis_download_link" value="<?php echo esc_attr( $options[ 'store' ][ 'download_link' ] ); ?>" /> <?php _e( 'day(s)', CPIS_TEXT_DOMAIN )?></td>
                        </tr>  
                        
                        <tr valign="top">
                        <th scope="row"><?php _e( 'Number of downloads allowed by purchase', CPIS_TEXT_DOMAIN ); ?></th>
                        <td><input type="text" name="cpis_download_limit" value="<?php echo esc_attr( $options[ 'store' ][ 'download_limit' ] ); ?>" /></td>
                        </tr>  
                        
                        <tr valign="top">
                        <th scope="row"><?php _e( 'Use safe downloads', CPIS_TEXT_DOMAIN ); ?></th>
                        <td><input type="checkbox" name="cpis_safe_download" 
						<?php 
						$cpis_safe_download = get_option( 'cpis_safe_download' );
						if( !empty( $cpis_safe_download ) && $cpis_safe_download  ) echo 'CHECKED'; 
						?> /></td>
                        </tr>  
                        
                        <tr valign="top">
                        <th scope="row"><?php _e( 'Pack all purchased audio files as a single ZIP file', CPIS_TEXT_DOMAIN ); ?></th>
                        <td><input type="checkbox" disabled >
                        <?php
                            if(!class_exists('ZipArchive'))
                                echo '<br /><span class="explain-text">'.__("Your server can't create Zipped files dynamically. Please, contact to your hosting provider for enable ZipArchive in the PHP script", CPIS_TEXT_DOMAIN).'</span>';
                        ?>
                        <br /><?php _e( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/image-store#download" target="_blank" >premium version</a> of plugin.', CPIS_TEXT_DOMAIN ); ?>
                        </td>
                        </tr>
                     </table>  
                </div>
            </div>
            
            <!--DISCOUNT BOX -->
            <div class="postbox">
                <h3 class='hndle' style="padding:5px;"><span><?php _e('Discount Settings', CPIS_TEXT_DOMAIN); ?></span></h3>
                <div class="inside">
                    <div style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;"><?php _e( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/image-store#download" target="_blank" >premium version</a> of plugin.', CPIS_TEXT_DOMAIN ); ?></div><br />
                    <div><input type="checkbox" disabled /> <?php _e('Display discount promotions in the music store page', CPIS_TEXT_DOMAIN)?></div>
                    <h4><?php _e('Scheduled Discounts', CPIS_TEXT_DOMAIN);?></h4>
                    <table class="form-table cpis_discount_table" style="border:1px dotted #dfdfdf;">
                        <tr>
                            <td style="font-weight:bold;"><?php _e('Percent of discount', CPIS_TEXT_DOMAIN); ?></td>
                            <td style="font-weight:bold;"><?php _e('In Sales over than ... ', CPIS_TEXT_DOMAIN); echo($currency); ?></td>
                            <td style="font-weight:bold;"><?php _e('Valid from dd/mm/yyyy', CPIS_TEXT_DOMAIN); ?></td>
                            <td style="font-weight:bold;"><?php _e('Valid to dd/mm/yyyy', CPIS_TEXT_DOMAIN); ?></td>
                            <td style="font-weight:bold;"><?php _e('Promotional text', CPIS_TEXT_DOMAIN); ?></td>
                            <td style="font-weight:bold;"><?php _e('Status', CPIS_TEXT_DOMAIN); ?></td>
                            <td></td>
                        </tr>
                    </table>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php _e('Percent of discount (*)', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" disabled /> %</td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Valid for sales over than (*)', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" disabled /> <?php echo $currency; ?></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Valid from (dd/mm/yyyy)', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" disabled /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Valid to (dd/mm/yyyy)', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" disabled /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Promotional text', CPIS_TEXT_DOMAIN); ?></th>
                            <td><textarea cols="60" disabled></textarea></td>
                        </tr>
                        <tr><td colspan="2"><input type="button" class="button" value="<?php _e('Add/Update Discount'); ?>" disabled /></td></tr>
                    </table>
                </div>
            </div>
            
            <!--COUPONS BOX -->
            <div class="postbox">
                <h3 class='hndle' style="padding:5px;"><span><?php _e('Coupons Settings', CPIS_TEXT_DOMAIN); ?></span></h3>
                <div class="inside">
                    <div style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;"><?php _e( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/image-store#download" target="_blank" >premium version</a> of plugin.', CPIS_TEXT_DOMAIN ); ?></div>
                    <h4><?php _e('Coupons List', CPIS_TEXT_DOMAIN);?></h4>
                    <table class="form-table cpis_coupon_table" style="border:1px dotted #dfdfdf;">
                        <tr>
                            <td style="font-weight:bold;"><?php _e('Percent of discount', CPIS_TEXT_DOMAIN); ?></td>
                            <td style="font-weight:bold;"><?php _e('Coupon', CPIS_TEXT_DOMAIN);?></td>
                            <td style="font-weight:bold;"><?php _e('Valid from dd/mm/yyyy', CPIS_TEXT_DOMAIN); ?></td>
                            <td style="font-weight:bold;"><?php _e('Valid to dd/mm/yyyy', CPIS_TEXT_DOMAIN); ?></td>
                            <td style="font-weight:bold;"><?php _e('Status', CPIS_TEXT_DOMAIN); ?></td>
                            <td></td>
                        </tr>
                    </table>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row"><?php _e('Percent of discount (*)', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" disabled /> %</td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Coupon (*)', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" disabled /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Valid from (dd/mm/yyyy)', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" disabled /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row"><?php _e('Valid to (dd/mm/yyyy)', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" disabled /></td>
                        </tr>
                        <tr><td colspan="2"><input type="button" class="button" value="<?php _e('Add/Update Coupon'); ?>" disabled /></td></tr>
                    </table>
                </div>
            </div>
                
            <!-- NOTIFICATIONS BOX -->
            <div class="postbox">
                <h3 class='hndle' style="padding:5px;"><span><?php _e('Notification Settings', CPIS_TEXT_DOMAIN); ?></span></h3>
                <div class="inside">
                    <table class="form-table">
                        <tr valign="top">        
                        <th scope="row"><?php _e('Notification "from" email', CPIS_TEXT_DOMAIN); ?></th>
                        <td><input type="text" name="cpis_from" size="40" value="<?php echo esc_attr( $options[ 'notification' ][ 'from' ] ); ?>" /></td>
                        </tr>    
                    
                        <tr valign="top">        
                        <th scope="row"><?php _e('Send notification to email', CPIS_TEXT_DOMAIN); ?></th>
                        <td><input type="text" name="cpis_to" size="40" value="<?php echo esc_attr( $options[ 'notification' ][ 'to' ] ); ?>" /></td>
                        </tr>
                         
                        <tr valign="top">
                        <th scope="row"><?php _e('Email subject confirmation to user', CPIS_TEXT_DOMAIN); ?></th>
                        <td><input type="text" name="cpis_subject_payer" size="40" value="<?php echo esc_attr( $options[ 'notification' ][ 'subject_payer' ] ); ?>" /></td>
                        </tr>
                        
                        <tr valign="top">
                        <th scope="row"><?php _e('Email confirmation to user', CPIS_TEXT_DOMAIN); ?></th>
                        <td><textarea name="cpis_notification_payer" cols="60" rows="5"><?php echo esc_textarea( $options[ 'notification' ][ 'notification_payer' ] ); ?></textarea></td>
                        </tr>
                        
                        <tr valign="top">
                        <th scope="row"><?php _e('Email subject notification to admin', CPIS_TEXT_DOMAIN); ?></th>
                        <td><input type="text" name="cpis_subject_seller" size="40" value="<?php echo esc_attr( $options[ 'notification' ][ 'subject_seller' ] ); ?>" /></td>
                        </tr>
                        
                        <tr valign="top">
                        <th scope="row"><?php _e('Email notification to admin', CPIS_TEXT_DOMAIN); ?></th>
                        <td><textarea name="cpis_notification_seller"  cols="60" rows="5"><?php echo esc_textarea( $options[ 'notification' ][ 'notification_seller' ] ); ?></textarea></td>
                        </tr>
                    </table>  
                </div>
            </div>
            <?php wp_nonce_field( plugin_basename( __FILE__ ), 'cpis_settings' ); ?>
            <div class="submit"><input type="submit" class="button-primary" value="<?php _e('Update Settings', CPIS_TEXT_DOMAIN); ?>" /></div>
            </form>
<?php
    }
 } // End cpis_settings_page

 if( !function_exists( 'cpis_reports_page' ) ){
    function cpis_reports_page(){
        global $wpdb;
        if ( isset( $_POST[ 'cpis_purchase_stats' ] ) && wp_verify_nonce( $_POST[ 'cpis_purchase_stats' ], plugin_basename( __FILE__ ) ) ){
            if(isset($_POST['delete_purchase_id'])){ // Delete the purchase
				$wpdb->query($wpdb->prepare(
					"DELETE FROM ".$wpdb->prefix.CPIS_PURCHASE." WHERE id=%d",
					$_POST['delete_purchase_id']
				));
			}
			
			if(isset($_POST['reset_purchase_id'])){ // Delete the purchase
				$wpdb->query($wpdb->prepare(
					"UPDATE ".$wpdb->prefix.CPIS_PURCHASE." SET checking_date = NOW(), downloads = 0 WHERE id=%d",
					$_POST['reset_purchase_id']
				));
			}
			
			if(isset($_POST['show_purchase_id'])){ // Delete the purchase
				$paypal_data = '<div class="cpis-paypal-data"><h3>' . __( 'PayPal data', CPIS_TEXT_DOMAIN ) . '</h3>' . $wpdb->get_var($wpdb->prepare(
					"SELECT paypal_data FROM ".$wpdb->prefix.CPIS_PURCHASE." WHERE id=%d",
					$_POST['show_purchase_id']
				)) . '</div>';
				$paypal_data = preg_replace( '/\n+/', '<br />', $paypal_data );
			}
        }

		$group_by_arr = array( 
			'no_group'  => 'Group by',
			'cpis_category' => 'Categories', 
			'cpis_author' 	=> 'Authors', 
			'cpis_color' 	=> 'Colors Schemes',
			'cpis_type' 	=> 'Type of Image'
		);
						
		
        $from_day   = ( isset( $_POST[ 'from_day' ] ) )   ? $_POST[ 'from_day' ]   : date( 'j' );
        $from_month = ( isset( $_POST[ 'from_month' ] ) ) ? $_POST[ 'from_month' ] : date( 'm' );
        $from_year  = ( isset( $_POST[ 'from_year' ] ) )  ? $_POST[ 'from_year' ]  : date( 'Y' );
        
        $to_day   = ( isset( $_POST[ 'to_day' ] ) )   ? $_POST[ 'to_day' ]   : date( 'j' );
        $to_month = ( isset( $_POST[ 'to_month' ] ) ) ? $_POST[ 'to_month' ] : date( 'm' );
        $to_year  = ( isset( $_POST[ 'to_year' ] ) )  ? $_POST[ 'to_year' ]  : date( 'Y' );
        
		$group_by = (isset($_POST['group_by'])) ? $_POST['group_by'] : 'no_group';
		$to_display = (isset($_POST['to_display'])) ? $_POST['to_display'] : 'sales';
	
		$_select = "";
		$_from 	 = " FROM ".$wpdb->prefix.CPIS_PURCHASE." AS purchase, ".$wpdb->prefix."posts AS posts, ".$wpdb->prefix.CPIS_IMAGE_FILE." AS image_file";
		$_where  = " WHERE posts.ID = image_file.id_image 
						  AND image_file.id_file = purchase.product_id 
						  AND DATEDIFF(purchase.date, '{$from_year}-{$from_month}-{$from_day}')>=0 
						  AND DATEDIFF(purchase.date, '{$to_year}-{$to_month}-{$to_day}')<=0 ";
		$_group  = "";
		$_order  = "";
		$_date_dif = floor( max( abs( strtotime( $to_year.'-'.$to_month.'-'.$to_day ) - strtotime( $from_year.'-'.$from_month.'-'.$from_day ) ) / ( 60*60*24 ), 1 ) );
		$_table_header = array( 'Product', 'Buyer', 'Amount', 'Currency', 'Download link', '' );
		
		if( $group_by == 'no_group' )	
		{
			if( $to_display == 'sales' )
			{
				$_select .= "SELECT purchase.*, posts.*";
			}
			else
			{
				$_select .= "SELECT SUM(purchase.amount)/{$_date_dif} as purchase_average, SUM(purchase.amount) as purchase_total, COUNT(posts.ID) as purchase_count, posts.*";
				$_group   = " GROUP BY posts.ID";
				if( $to_display == 'amount' )
				{
					$_table_header = array( 'Product', 'Amount of Sales', 'Total' );
					$_order = " ORDER BY purchase_count DESC";
				}
				else
				{
					$_table_header = array( 'Product', 'Daily Average', 'Total' );
					$order =  " ORDER BY purchase_average DESC";
				}	
			}
		}
		else
		{
			$_select .= "SELECT SUM(purchase.amount)/{$_date_dif} as purchase_average, SUM(purchase.amount) as purchase_total, COUNT(posts.ID) as purchase_count, terms.name as term_name, terms.slug as term_slug";
			
			$_from   .= ", {$wpdb->prefix}term_taxonomy as taxonomy, 
						 {$wpdb->prefix}term_relationships as term_relationships, 
						 {$wpdb->prefix}terms as terms";
			$_where  .=" AND taxonomy.taxonomy = '{$group_by}'
						 AND taxonomy.term_taxonomy_id=term_relationships.term_taxonomy_id 
						 AND term_relationships.object_id=posts.ID 
						 AND taxonomy.term_id=terms.term_id";
			$_group  = " GROUP BY terms.term_id";
			$_order  = " ORDER BY terms.slug;";
			
			if( $to_display == 'amount' )
			{
				$_order = " ORDER BY purchase_count DESC";
				$_table_header = array( $group_by_arr[ $group_by ], 'Amount of Sales', 'Total' );
			}
			else
			{
				$order =  " ORDER BY purchase_average DESC";
				if( $to_display == 'sales' )
				{	
					$_table_header = array( $group_by_arr[ $group_by ], 'Total' );
				}
				else
				{
					$_table_header = array( $group_by_arr[ $group_by ], 'Daily Average', 'Total' );
				}
			}	
		}
		
		$purchase_list = $wpdb->get_results( $_select.$_from.$_where.$_group.$_order );
        
?>
        <form method="post" action="<?php echo $_SERVER[ 'REQUEST_URI' ]; ?>" id="purchase_form">
        <?php wp_nonce_field( plugin_basename( __FILE__ ), 'cpis_purchase_stats' ); ?>
        <input type="hidden" name="tab" value="reports" />
        <!-- FILTER REPORT -->
        <div class="postbox" style="margin-top:20px;">
            <h3 class='hndle' style="padding:5px;"><span><?php _e( 'Filter the sales reports', CPIS_TEXT_DOMAIN ); ?></span></h3>
            <div class="inside">
				<div>
					<h4><?php _e('Filter by date', CPIS_TEXT_DOMAIN); ?></h4>
							
					<?php
						$months_list = array(
							'01' => __( 'January', CPIS_TEXT_DOMAIN ),
							'02' => __( 'February', CPIS_TEXT_DOMAIN ),
							'03' => __( 'March', CPIS_TEXT_DOMAIN ),
							'04' => __( 'April', CPIS_TEXT_DOMAIN ),
							'05' => __( 'May', CPIS_TEXT_DOMAIN ),
							'06' => __( 'June', CPIS_TEXT_DOMAIN ),
							'07' => __( 'July', CPIS_TEXT_DOMAIN ),
							'08' => __( 'August', CPIS_TEXT_DOMAIN ),
							'09' => __( 'September', CPIS_TEXT_DOMAIN ),
							'10' => __( 'October', CPIS_TEXT_DOMAIN ),
							'11' => __( 'November', CPIS_TEXT_DOMAIN ),
							'12' => __( 'December', CPIS_TEXT_DOMAIN ),
						);
					?>
					<label><?php _e( 'From: ', CPIS_TEXT_DOMAIN ); ?></label>
					<select name="from_day">
					<?php
						for( $i=1; $i <=31; $i++ ) print '<option value="'.$i.'"'.( ( $from_day == $i ) ? ' SELECTED' : '' ).'>'.$i.'</option>';
					?>
					</select>
					<select name="from_month">
					<?php
						foreach( $months_list as $month => $name ) print '<option value="'.$month.'"'.( ( $from_month == $month ) ? ' SELECTED' : '' ).'>'.$name.'</option>';
					?>
					</select>
					<input type="text" name="from_year" value="<?php print $from_year; ?>" />
					
					<label><?php _e( 'To: ', CPIS_TEXT_DOMAIN ); ?></label>
					<select name="to_day">
					<?php
						for( $i=1; $i <=31; $i++ ) print '<option value="'.$i.'"'.( ( $to_day == $i ) ? ' SELECTED' : '').'>'.$i.'</option>';
					?>
					</select>
					<select name="to_month">
					<?php
						foreach( $months_list as $month => $name ) print '<option value="'.$month.'"'.( ( $to_month == $month ) ? ' SELECTED' : '' ).'>'.$name.'</option>';
					?>
					</select>
					<input type="text" name="to_year" value="<?php print $to_year; ?>" />
					
					<input type="submit" value="<?php _e('Search', CPIS_TEXT_DOMAIN); ?>" class="button-primary" />
				</div>
				
				<div style="float:left;margin-right:20px;">
					<h4><?php _e('Grouping the sales', CPIS_TEXT_DOMAIN); ?></h4>
					<label><?php _e('By: ', CPIS_TEXT_DOMAIN); ?></label>
					<select name="group_by">
					<?php
						foreach( $group_by_arr as $key => $value ) 
						{
							print '<option value="'.$key.'"'.( ( isset( $group_by ) && $group_by == $key ) ? ' SELECTED' : '' ).'>'.$value.'</option>';
						}
					?>
					</select>
				</div>	
				<div style="float:left;margin-right:20px;">
					<h4><?php _e('Display', CPIS_TEXT_DOMAIN); ?></h4>
					<label><input type="radio" name="to_display" <?php echo ( ( !isset( $to_display ) || $to_display == 'sales' ) ? 'CHECKED' : '' ); ?> value="sales" /> <?php _e('Sales', CPIS_TEXT_DOMAIN); ?></label>
					<label><input type="radio" name="to_display" <?php echo ( ( isset( $to_display ) && $to_display == 'amount' ) ? 'CHECKED' : '' ); ?> value="amount" /> <?php _e('Amount of sales', CPIS_TEXT_DOMAIN); ?></label>
					<label><input type="radio" name="to_display" <?php echo ( ( isset( $to_display ) && $to_display == 'average' ) ? 'CHECKED' : '' ); ?> value="average" /> <?php _e('Daily average', CPIS_TEXT_DOMAIN); ?></label>
				</div>
				<div style="clear:both;"></div>	
            </div>
        </div>	
        <!-- PURCHASE LIST -->
        <div class="postbox">
            <h3 class='hndle' style="padding:5px;"><span><?php _e( 'Store sales report', CPIS_TEXT_DOMAIN ); ?></span></h3>
            <div class="inside">
				<?php 
					if( !empty( $paypal_data ) ) print $paypal_data;
					if(count($purchase_list)){	
						print '
							<div>
								<label style="margin-right: 20px;" ><input type="checkbox" onclick="cpis_load_report(this, \'sales_by_country\', \''.__( 'Sales by country', CPIS_TEXT_DOMAIN ).'\', \'residence_country\', \'Pie\', \'residence_country\', \'count\');" /> '.__( 'Sales by country', CPIS_TEXT_DOMAIN ).'</label>
								<label style="margin-right: 20px;" ><input type="checkbox" onclick="cpis_load_report(this, \'sales_by_currency\', \''.__( 'Sales by currency', CPIS_TEXT_DOMAIN ).'\', \'mc_currency\', \'Bar\', \'mc_currency\', \'sum\');" /> '.__( 'Sales by currency', CPIS_TEXT_DOMAIN ).'</label>
								<label><input type="checkbox" onclick="cpis_load_report(this, \'sales_by_product\', \''.__( 'Sales by product', CPIS_TEXT_DOMAIN ).'\', \'product_name\', \'Bar\', \'post_title\', \'sum\');" /> '.__( 'Sales by product', CPIS_TEXT_DOMAIN ).'</label>
							</div>';
					}
				?>
						    
				<div id="charts_content" >
					<div id="sales_by_country"></div>
					<div id="sales_by_currency"></div>
					<div id="sales_by_product"></div>
				</div>
							
                <table class="form-table" style="border-bottom:1px solid #CCC;margin-bottom:10px;">
                    <THEAD>
                        <TR style="border-bottom:1px solid #CCC;">
                        <?php 
							foreach( $_table_header as $_header )
							{
								print "<TH>{$_header}</TH>";
							}
						?>
                        </TR>
                    </THEAD>
                    <TBODY>
                    <?php
                    $totals = array( 'UNDEFINED' => 0 );
					
					$dlurl = _cpis_create_pages( 'cpis-download-page', 'Download Page' );
					$dlurl .= ( ( strpos( $dlurl, '?' ) === false ) ? '?' : '&' ).'cpis-action=download&purchase_id=';
			
                    if( count( $purchase_list ) ){	
                        foreach( $purchase_list as $purchase ){
                            if( $group_by == 'no_group' )
							{
							
								if( $to_display == 'sales' )
								{
									if(preg_match('/mc_currency=([^\s]*)/', $purchase->paypal_data, $matches)){
										$currency = strtoupper($matches[1]);
										if(!isset($totals[$currency])) $totals[$currency] = $purchase->amount;
											else $totals[$currency] += $purchase->amount;
									}else{
										$currency = '';
										$totals['UNDEFINED'] += $purchase->amount;
									}
									
									echo '
										<TR>
											<TD><a href="'.get_permalink($purchase->ID).'" target="_blank">'.$purchase->post_title.'</a></TD>
											<TD>'.$purchase->email.'</TD>
											<TD>'.$purchase->amount.'</TD>
											<TD>'.$currency.'</TD>
											<TD><a href="'.$dlurl.$purchase->purchase_id.'" target="_blank">Download Link</a></TD>
											<TD style="white-space:nowrap;">
												<input type="button" class="button-primary" onclick="cpis_delete_purchase('.$purchase->id.');" value="Delete"> 
												<input type="button" class="button-primary" onclick="cpis_reset_purchase('.$purchase->id.');" value="Reset Time and Downloads"> 
												<input type="button" class="button-primary" onclick="cpis_show_purchase('.$purchase->id.');" value="PayPal Info">
											</TD>
										</TR>
									';
								}elseif( $to_display == 'amount' ){
									echo '
										<TR>
											<TD><a href="'.get_permalink($purchase->ID).'" target="_blank">'.$purchase->post_title.'</a></TD>
											<TD>'.(round( $purchase->purchase_count*100 )/100).'</TD>
											<TD>'.(round( $purchase->purchase_total*100)/100).'</TD>
										</TR>
									';
								}else{
									echo '
										<TR>
											<TD><a href="'.get_permalink($purchase->ID).'" target="_blank">'.$purchase->post_title.'</a></TD>
											<TD>'.$purchase->purchase_average.'</TD>
											<TD>'.(round($purchase->purchase_total*100)/100).'</TD>
										</TR>
									';
								}
							}
							else
							{

								if( $to_display == 'sales' ){
									echo '
											<TR>
												<TD><a href="'.get_term_link($purchase->term_slug, $group_by ).'" target="_blank">'.$purchase->term_name.'</a></TD>
												<TD>'.(round( $purchase->purchase_total*100)/100).'</TD>
											</TR>
										';
								}elseif(  $to_display == 'amount'  ){
									echo '
											<TR>
												<TD><a href="'.get_term_link($purchase->term_slug, $group_by ).'" target="_blank">'.$purchase->term_name.'</a></TD>
												<TD>'.(round( $purchase->purchase_count*100)/100).'</TD>
												<TD>'.(round( $purchase->purchase_total*100)/100).'</TD>
											</TR>
										';
								}else{
									echo '
											<TR>
												<TD><a href="'.get_term_link($purchase->term_slug, $group_by ).'" target="_blank">'.$purchase->term_name.'</a></TD>
												<TD>'.$purchase->purchase_average.'</TD>
												<TD>'.(round( $purchase->purchase_total*100)/100).'</TD>
											</TR>
										';
								}											
							}
                        }
                    }else{
                        echo '
                            <TR>
                                <TD COLSPAN="6">
                                    '.__('There are not sales registered with those filter options', CPIS_TEXT_DOMAIN).'
                                </TD>
                            </TR>
                        ';
                    }	
                    ?>
                    </TBODY>
                </table>
                
                <?php
                    if( count( $totals ) > 1 || $totals[ 'UNDEFINED' ] ){
                ?>
                        <table style="border: 1px solid #CCC;">
                            <TR><TD COLSPAN="2" style="border-bottom:1px solid #CCC;">TOTALS</TD></TR>
                            <TR><TD style="border-bottom:1px solid #CCC;">CURRENCY</TD><TD style="border-bottom:1px solid #CCC;">AMOUNT</TD></TR>
                        <?php
                            foreach( $totals as $currency => $amount )
                                if( $amount )
                                    print "<TR><TD><b>{$currency}</b></TD><TD>{$amount}</TD></TR>";
                        ?>	
                        </table>
                <?php	
                    }
                ?>
            </div>
        </div>
        </form>
<?php
    }
 } // End cpis_reports_page
 
 add_action( 'admin_enqueue_scripts', 'cpis_admin_enqueue_scripts' );
 if( !function_exists( 'cpis_admin_enqueue_scripts' ) ){
    function cpis_admin_enqueue_scripts( $hook ){
        global $post;
        
        if(
            $hook == 'image-store_page_image-store-menu-settings'
        ){
            wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.3/themes/smoothness/jquery-ui.css');
            wp_enqueue_style('cpis-admin-style', CPIS_PLUGIN_URL.'/css/admin.css');
            wp_enqueue_script('json2');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_script('cpis-admin-script', CPIS_PLUGIN_URL.'/js/admin.js', array('jquery', 'json2', 'jquery-ui-core', 'jquery-ui-datepicker'), null, true);
        }else if( 
            isset( $post ) && $post->post_type == "cpis_image" 
        ){
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-datepicker');
            wp_enqueue_script('jquery-ui-dialog');
            wp_enqueue_script('cpis-admin-script', CPIS_PLUGIN_URL.'/js/admin.js', array('jquery', 'jquery-ui-core', 'jquery-ui-dialog', 'media-upload', 'json2', 'jquery-ui-datepicker'), null, true);
			
            // Scripts and styles required for metaboxs
            wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.3/themes/smoothness/jquery-ui.css');
            wp_enqueue_style('cpis-admin-style', CPIS_PLUGIN_URL.'/css/admin.css');
            wp_localize_script( 'cpis-admin-script', 'image_store', array( 'post_id' => $post->ID, 'hurl' => CPIS_H_URL) );					
        }else if(
            $hook == 'image-store_page_image-store-menu-reports'
        ){
			wp_enqueue_style('cpis-admin-style', CPIS_PLUGIN_URL.'/css/admin.css');
			wp_enqueue_script('cpis-admin-script-chart', CPIS_PLUGIN_URL.'/js/Chart.min.js', array('jquery'), null, true);
            wp_enqueue_script('cpis-admin-script', CPIS_PLUGIN_URL.'/js/admin.js', array('jquery'), null, true);
			wp_localize_script('cpis-admin-script', 'cpis_global', array( 'aurl' => admin_url() ));
        }else if( isset( $post ) ){
            
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-dialog');
            wp_enqueue_script('cpis-admin-script', CPIS_PLUGIN_URL.'/js/admin.js', array('jquery', 'jquery-ui-core', 'jquery-ui-dialog'), null, true);
			
            $tags  = '<div title="'.__( 'Insert a Product', CPIS_TEXT_DOMAIN ).'"><div style="padding:20px;">';
            $tags .= '<div>'.__( 'Enter the Image ID:', CPIS_TEXT_DOMAIN ).'<br /><input id="product_id" name="product_id" style="width:100%" /></div>';
            $tags .= '<div>'.__( 'Select the Layout:', CPIS_TEXT_DOMAIN ).'<br /><select id="layout" name="layout" style="width:100%"><option value="single">Single</option><option value="multiple">Multiple</option></select><br /><em>'.__( 'If the product is inserted in a page with other products, it is recommended the use of Multiple layout.', CPIS_TEXT_DOMAIN ).'</em></div>';
            
            $tags .= '</div></div>';
					
			wp_localize_script('cpis-admin-script', 'image_store', array('tags' => $tags));
        }
    }
 } // End cpis_admin_enqueue_scripts
 
 add_action( 'wp_enqueue_scripts', 'cpis_enqueue_scripts' );
 if( !function_exists( 'cpis_enqueue_scripts' ) ){
    function cpis_enqueue_scripts( $hook ){
        if(
            !is_admin()
        ){
            global $cpis_layout;
            $options = get_option( 'cpis_options' );
            
            wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.3/themes/smoothness/jquery-ui.css');
            wp_enqueue_style('cpis-style', CPIS_PLUGIN_URL.'/css/public.css');
            
            wp_enqueue_script('json2');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-position');
            wp_enqueue_script('cpis-carousel', CPIS_PLUGIN_URL.'/js/jquery.carouFredSel-6.2.1-packed.js');
            wp_enqueue_script('cpis-script', CPIS_PLUGIN_URL.'/js/public.js', array('jquery', 'json2', 'jquery-ui-core', 'cpis-carousel', 'jquery-ui-position'), null, true);
            
            // Load resources of layout
			if( !empty( $cpis_layout ) )
			{
				if( !empty( $cpis_layout[ 'style_file' ] ) )  wp_enqueue_style('cpis-css-layout', $cpis_layout[ 'style_file' ] , array( 'cpis-style' ) );
				if( !empty( $cpis_layout[ 'script_file' ] ) ) wp_enqueue_script('cpis-js-layout', $cpis_layout[ 'script_file'] , array( 'cpis-script' ), false, true );
			}
    
            $cpis_sc_url = _cpis_create_pages( 'cpis-shopping-cart', 'Shopping Cart' );
            $cpis_sc_url .= ( ( strpos( $cpis_sc_url, '?' ) === false ) ? '?' : '&' ).'cpis-action=viewcart';
                    
            $arr = array( 
                    'scurl' => $cpis_sc_url,
                    'hurl' => CPIS_H_URL, 
                    'thumbnail_w' => $options[ 'image' ][ 'thumbnail' ][ 'width' ],
                    'thumbnail_h' => $options[ 'image' ][ 'thumbnail' ][ 'height' ],
                    'file_required_str' => __( 'It is required select at least a file', CPIS_TEXT_DOMAIN )
            );
            if( $options[ 'display' ][ 'carousel' ][ 'activate' ] ){
                $arr[ 'carousel_autorun' ] = ( $options[ 'display' ][ 'carousel' ][ 'autorun' ] ) ? 1 : 0;
                $arr[ 'carousel_transition_time' ] = $options[ 'display' ][ 'carousel' ][ 'transition_time' ];
            }
           
            wp_localize_script( 'cpis-script', 'image_store', $arr );
        }    
    }
 } // End cpis_enqueue_scripts
 
 if( !function_exists( 'cpis_upload_dir' ) ){
    function cpis_upload_dir( $path ){
        global $post;
        
		if( !file_exists( CPIS_UPLOAD_DIR ) ) @mkdir( CPIS_UPLOAD_DIR, 0755 );
		if( !file_exists( CPIS_UPLOAD_DIR.'/files' ) ) @mkdir( CPIS_UPLOAD_DIR.'/files', 0755 );
		if( !file_exists( CPIS_UPLOAD_DIR.'/previews' ) ) @mkdir( CPIS_UPLOAD_DIR.'/previews', 0755 );
		
        if( isset( $post ) && 'cpis_image' == $post->post_type ){
            $path[ 'path' ] = CPIS_UPLOAD_DIR.'/files'.$path[ 'subdir' ];
            $path[ 'url' ] = CPIS_UPLOAD_URL.'/files'.$path[ 'subdir' ];
            $path[ 'basedir' ] = CPIS_UPLOAD_DIR.'/files';
            $path[ 'baseurl' ] = CPIS_UPLOAD_URL.'/files';
            return $path;
        }
        return $path;
    }
 }// End cpis_upload_dir
 
 if( !function_exists( 'cpis_removemediabuttons' ) ){
     function cpis_removemediabuttons()
     {
        global $post;
        if( isset($post) && 'cpis_image' == $post->post_type )
            remove_action( 'media_buttons', 'media_buttons' );
     }
 } // End cpis_removemediabuttons
 
 if( !function_exists( 'cpis_the_excerpt' ) ){
	function cpis_the_excerpt( $the_excerpt ){
		global $post;
		if( is_search() && isset( $post ) && $post->post_type == 'cpis_image' ){
			cpis_save_continue_shopping_url();
            return cpis_display_content( $post->ID, 'multiple', 'return' );
		}
		
		return $the_excerpt;
	}
 } // End cpis_the_excerpt
 
 if( !function_exists( 'cpis_the_content' ) ){
    function cpis_the_content( $the_content  ){
        global $post;
			
        if( in_the_loop() && $post && ( $post->post_type == 'cpis_image' ) ){
            return cpis_display_content( $post->ID, ( ( is_singular() ) ? 'single' : 'multiple' ), 'return' );
        }else{
            if( isset( $_REQUEST ) && isset( $_REQUEST[ 'cpis-action' ] ) ){
                switch( strtolower( $_REQUEST[ 'cpis-action' ] ) ){
                    case 'download':
						global $cpis_errors;
					
                        include CPIS_PLUGIN_DIR.'/includes/download.php';
						if( empty( $cpis_errors ) ){
							$the_content .= '<div>'.$download_links_str.'</div>';
						}else{
							$error = ( !empty( $_REQUEST[ 'error_mssg' ] ) ) ? $_REQUEST[ 'error_mssg' ] : '';
							
							if( ( !get_option( 'cpis_safe_download', CPIS_SAFE_DOWNLOAD ) && !empty( $cpis_errors ) ) || !empty( $_SESSION[ 'cpis_user_email' ] ) ){
								$error .= '<li>'.implode( '</li><li>', $cpis_errors ).'</li>';
							}
							
							$the_content .= ( !empty( $error ) )  ? '<div class="cpis-error-mssg"><ul>'.$error.'</ul></div>' : '';

							if( get_option( 'cpis_safe_download', CPIS_SAFE_DOWNLOAD ) ){
								$dlurl = _cpis_create_pages( 'cpis-download-page', 'Download Page' );
								$dlurl .= ( ( strpos( $dlurl, '?' ) === false ) ? '?' : '&' ).'cpis-action=download'.( ( isset( $_REQUEST[ 'purchase_id' ] ) ) ? '&purchase_id='.$_REQUEST[ 'purchase_id' ] : '' );	
								$the_content .= '
									<form action="'.$dlurl.'" method="POST" >
										<div style="text-align:center;">
											<div>
												'.__( 'Type the email address used to purchase our products', CPIS_TEXT_DOMAIN ).'
											</div>
											<div>
												<input type="text" name="cpis_user_email" /> <input type="submit" value="Get Products" />
											</div>	
										</div>
									</form>
								';
							}	
						}
                    break;
                }
            }
        }
        return $the_content;
    }
 } // End cpis_the_content
 
 if( !function_exists( 'cpis_replace_product_shortcode' ) ){
    function cpis_replace_product_shortcode( $atts ){
        extract( shortcode_atts( array( 'id' => '', 'layout' => 'single' ), $atts ) );
        $id = trim( $id );
        if( !empty( $id ) ){
            $p = get_post( $id );
            if( !empty( $p ) && $p->post_type == 'cpis_image' ){
                return cpis_display_content( $id, $layout, 'return' );
            }
        } 
        return '';
    }
 } // End cpis_replace_product_shortcode
 
 if( !function_exists( 'cpis_replace_shortcode' ) ){
    // Private functions to create the query for products selection
    
    function _cpis_filter_by_taxonomy( $taxonomy, $taxonomy_value, $hierarchical = false ){
        if( $hierarchical ){
            
            $args = array(
                    'hide_empty' => 1,
                    'hierarchical' => 1
                );
            
            if( !is_numeric($taxonomy_value) ){
                $args[ 'child_of' ] = get_cat_ID( $taxonomy_value );
            }else{
                $args[ 'child_of' ] = $taxonomy_value;
            }
            
            $terms = get_terms( $taxonomy, $args );
        }
        
        $_where = "(taxonomy.taxonomy='$taxonomy' AND ";
        
        if( $hierarchical && $terms ){
            $_where .= "(".( ( is_numeric( $taxonomy_value ) ) ?  "terms.term_id=$taxonomy_value" : "terms.slug='$taxonomy_value'" );
            
            foreach( $terms as $term ){
                $_where .= " OR terms.term_id=".$term->term_id;
            }
            $_where .= ")";
        }else{

            if( is_numeric($taxonomy_value) )
                $_where .= "terms.term_id=$taxonomy_value";
            else
                $_where .= "terms.slug='$taxonomy_value'";	
        }
        
        $_where .= ")";
        return $_where;
    }
    
    function _cpis_create_select_filter( $name, $option_none, $taxonomy, $hierarchical = 0 ){
        $page_id = 'cpis_page_'.get_the_ID();
        $option_none = __( $option_none, CPIS_TEXT_DOMAIN );
        
        if( !is_null( $_SESSION[ $page_id ][ $taxonomy ] ) && !is_numeric( $_SESSION[ $page_id ][ $taxonomy ] ) )
        {
           $obj = get_term_by( 'slug', $_SESSION[ $page_id ][ $taxonomy ], $taxonomy );
           $_SESSION[ $page_id ][ $taxonomy ] = $obj->term_id;
        }
        
        $select = wp_dropdown_categories('name='.$name.'&show_option_none='.$option_none.'&orderby=name&echo=0&taxonomy='.$taxonomy.'&hide_if_empty=1&hierarchical='.$hierarchical.( ( isset( $_SESSION[ $page_id ][ $taxonomy ] ) ) ? '&selected='.$_SESSION[ $page_id ][ $taxonomy ] : '' ) );
        $select = preg_replace("#<select([^>]*)>#", "<select$1 onchange='return this.form.submit()'>", $select);
        return $select;
    }
    
    function _cpis_create_search_filter( $str ){
        $filter = '';
        $str = trim( preg_replace( "/\s+/", " ", $str ) );
        $terms = explode( " ", $str );
        if( count( $terms ) ){
            foreach( $terms as $term ){
                $filter .= "( post_title LIKE '%$term%' OR ";
                $filter .= "post_content LIKE '%$term%' OR ";
                $filter .= "post_excerpt LIKE '%$term%' ) AND ";
            }
        }
        
        return $filter;
    }
    
    function cpis_replace_shortcode( $atts, $content, $tag ){
        global $wpdb;
		
        $page_id = 'cpis_page_'.get_the_ID();
        if( !isset( $_SESSION[ $page_id ] ) ) $_SESSION[ $page_id ] = array();

        $options = get_option( 'cpis_options' );
        
        // Generated music store
        $top_ten_carousel = "";
        $page_links = "";
        $header = "";
        $left = "";
        $right = "";
        
        // Set session variable for pagination
        if( !isset( $_SESSION[ $page_id ][ 'cpis_page' ] ) ) $_SESSION[ $page_id ][ 'cpis_page' ] = 0;
        if( isset( $_REQUEST ) && isset( $_REQUEST[ 'cpis_page' ] ) ){
            $_SESSION[ $page_id ][ 'cpis_page' ] = $_REQUEST[ 'cpis_page' ];
        }
        
        // Create session variables from attributes
        if( !isset( $_SESSION[ $page_id ][ 'cpis_search_terms' ] ) && !empty( $atts[ 'search' ] ) ) $_SESSION[ $page_id ][ 'cpis_search_terms' ] = $atts[ 'search' ];
        if( !isset( $_SESSION[ $page_id ][ 'cpis_type' ] ) && !empty( $atts[ 'type' ] ) ) $_SESSION[ $page_id ][ 'cpis_type' ] = $atts[ 'type' ];
        if( !isset( $_SESSION[ $page_id ][ 'cpis_category' ] ) && !empty( $atts[ 'category' ] ) ) $_SESSION[ $page_id ][ 'cpis_category' ] = $atts[ 'category' ];
        if( !isset( $_SESSION[ $page_id ][ 'cpis_author' ] ) && !empty( $atts[ 'author' ] ) ) $_SESSION[ $page_id ][ 'cpis_author' ] = $atts[ 'author' ];
        if( !isset( $_SESSION[ $page_id ][ 'cpis_color' ] ) && !empty( $atts[ 'color' ] ) ) $_SESSION[ $page_id ][ 'cpis_color' ] = $atts[ 'color' ];
        if( !isset( $_SESSION[ $page_id ][ 'cpis_ordering' ] ) && !empty( $atts[ 'orderby' ] ) ) $_SESSION[ $page_id ][ 'cpis_ordering' ] = $atts[ 'orderby' ];

        // Extract search terms
        if( isset( $_REQUEST[ 'search_terms' ] ) ){
            $_SESSION[ $page_id ][ 'cpis_search_terms' ] = $_REQUEST[ 'search_terms' ];
            $_SESSION[ $page_id ][ 'cpis_page' ] = 0;
            $filter = _cpis_create_search_filter( $_REQUEST[ 'search_terms' ] );
        }else{
            $filter = "";
        }
        
        // Extract product filters
        
        if( isset( $_REQUEST[ 'filter_by_type' ] ) ){
            $_SESSION[ $page_id ][ 'cpis_type' ] = $_REQUEST[ 'filter_by_type' ];
            $_SESSION[ $page_id ][ 'cpis_page' ] = 0;
        }
        
        if( isset( $_REQUEST[ 'filter_by_category' ] ) ){
            $_SESSION[ $page_id ][ 'cpis_category' ] = $_REQUEST[ 'filter_by_category' ];
            $_SESSION[ $page_id ][ 'cpis_page' ] = 0;
        }
        
        if( isset( $_REQUEST[ 'filter_by_author' ] ) ){
            $_SESSION[ $page_id ][ 'cpis_author' ] = $_REQUEST[ 'filter_by_author' ];
            $_SESSION[ $page_id ][ 'cpis_page' ] = 0;
        }
        
        if( isset( $_REQUEST[ 'filter_by_color' ] ) ){
            $_SESSION[ $page_id ][ 'cpis_color' ] = $_REQUEST[ 'filter_by_color' ];
            $_SESSION[ $page_id ][ 'cpis_page' ] = 0;
        }
        
        if( isset( $_REQUEST[ 'ordering_by' ] ) ){
            $_SESSION[ $page_id ][ 'cpis_ordering' ] = $_REQUEST[ 'ordering_by' ];
            $_SESSION[ $page_id ][ 'cpis_page' ] = 0;
        }else{
            $_SESSION[ $page_id ][ 'cpis_ordering' ] = "post_title";
        }
        
        // Query clauses 
        $_select 	= "SELECT SQL_CALC_FOUND_ROWS DISTINCT posts.ID";
        $_from 		= "FROM ".$wpdb->prefix."posts as posts,".$wpdb->prefix.CPIS_IMAGE." as posts_data"; 
        $_where 	= "WHERE  $filter posts.ID = posts_data.id AND posts.post_status='publish' AND posts.post_type='cpis_image' ";
        $_order_by 	= "ORDER BY ".( ( $_SESSION[ $page_id ]['cpis_ordering'] != 'purchases' ) ? "posts" : "posts_data" ).".".$_SESSION[ $page_id ]['cpis_ordering']." ".( ( $_SESSION[ $page_id ]['cpis_ordering'] == 'post_title' ) ? "ASC" : "DESC" );
        $_limit 	= "";
        
        if( ( !empty( $_SESSION[ $page_id ]['cpis_type'] )     && $_SESSION[ $page_id ]['cpis_type'] != -1 )   ||
            ( !empty( $_SESSION[ $page_id ]['cpis_color'] )    && $_SESSION[ $page_id ]['cpis_color'] != -1 )  ||
            ( !empty( $_SESSION[ $page_id ]['cpis_author'] )   && $_SESSION[ $page_id ]['cpis_author'] != -1 ) || 
            ( !empty( $_SESSION[ $page_id ]['cpis_category'] ) && $_SESSION[ $page_id ]['cpis_category'] != -1 )
        ){
            $_select_sub 	= "SELECT DISTINCT posts.ID";
            
            // Load the taxonomy tables
            $_from_sub = "$_from, ".$wpdb->prefix."term_taxonomy as taxonomy, ".$wpdb->prefix."term_relationships as term_relationships, ".$wpdb->prefix."terms as terms";
            
            $_where_sub = "$_where AND taxonomy.term_taxonomy_id=term_relationships.term_taxonomy_id AND term_relationships.object_id=posts.ID AND taxonomy.term_id=terms.term_id ";
            
            // Filter by type 
            if( !empty( $_SESSION[ $page_id ]['cpis_type'] ) && $_SESSION[ $page_id ]['cpis_type'] != -1 ){
                $_where .= " AND posts.ID IN ( $_select_sub $_from_sub $_where_sub AND "._cpis_filter_by_taxonomy( 'cpis_type', $_SESSION[ $page_id ]['cpis_type'] ).")";
            }
            
            if( !empty( $_SESSION[ $page_id ]['cpis_author'] ) && $_SESSION[ $page_id ][ 'cpis_author' ] != -1 ){
                $_where .= " AND posts.ID IN ( $_select_sub $_from_sub $_where_sub AND "._cpis_filter_by_taxonomy( 'cpis_author', $_SESSION[ $page_id ]['cpis_author'] ).")";
            }
            
            if( !empty( $_SESSION[ $page_id ]['cpis_color'] ) && $_SESSION[ $page_id ][ 'cpis_color' ] != -1 ){
                $_where .= " AND posts.ID IN ( $_select_sub $_from_sub $_where_sub AND "._cpis_filter_by_taxonomy( 'cpis_color', $_SESSION[ $page_id ]['cpis_color'] ).")";
            }
            
            if( !empty( $_SESSION[ $page_id ]['cpis_category'] ) && $_SESSION[ $page_id ][ 'cpis_category' ] != -1 ){
                    $_where .= " AND posts.ID IN ( $_select_sub $_from_sub $_where_sub AND "._cpis_filter_by_taxonomy( 'cpis_category', $_SESSION[ $page_id ]['cpis_category'], true ).")";
            }
            
            // End taxonomies
        } 
        
        $query = $_select." ".$_from." ".$_where." ".$_order_by." ".$_limit;

        if( $options[ 'store' ][ 'show_pagination' ] && is_numeric( $options[ 'store' ][ 'items_page' ] ) &&  $options[ 'store' ][ 'items_page' ] > 1 ){
            $page = $_SESSION[ $page_id ][ 'cpis_page' ];
            
            $_limit = "LIMIT ".( $page * $options[ 'store' ][ 'items_page' ]).", ".$options[ 'store' ][ 'items_page' ];
            
            $query .= " ".$_limit;
            $results = $wpdb->get_results($query);
            
            $total = $wpdb->get_var( "SELECT FOUND_ROWS();" );
            $total_pages = ceil( $total/$options[ 'store' ][ 'items_page' ] );
            
            // Make page links
            $page_links .= "<DIV class='cpis-image-store-pagination'>";
            $page_href = '?'.((strlen($_SERVER['QUERY_STRING'])) ? preg_replace('/(&)?cpis_page=\d+/', '', $_SERVER['QUERY_STRING']).'&' : '');	
            
            for($i=0, $h = $total_pages; $i < $h; $i++){
                if($page == $i)
                    $page_links .= "<span class='page-selected'>".($i+1)."</span>";
                else	
                    $page_links .= "<a class='page-link' href='".$page_href."cpis_page=".$i."'>".($i+1)."</a>";
            }
            $page_links .= "</DIV>";
        }else{
            $results = $wpdb->get_results($query);
        }
        
        // Create carousel
        if( $options[ 'display' ][ 'carousel' ][ 'activate' ] ){
            
            $thumb_width  = $options[ 'image' ][ 'thumbnail' ][ 'width' ];
            $thumb_height = $options[ 'image' ][ 'thumbnail' ][ 'height' ];
            
            $carousel_order_by 	= "ORDER BY posts_data.purchases DESC";
            $carousel_limit 	= "LIMIT 0, 10";
            $carousel_query = $_select." ".$_from." ".$_where." ".$carousel_order_by." ".$carousel_limit;
            $carousel_results = $wpdb->get_results( $carousel_query );
            
            if( count( $carousel_results ) ){
                foreach ( $carousel_results as $result ){
                    $top_ten_carousel .= cpis_display_content( $result->ID, 'carousel', 'return' );
                }

                $top_ten_carousel = "
                <div class='cpis-column-title'>".__( 'Top-Ten of filtered images', CPIS_TEXT_DOMAIN )."</div>
                <div id='cpis-image-store-carousel'><div class='cpis-carousel-container' ><ul>".$top_ten_carousel."</ul></div></div>
                ";
            }    
        }    
        
        // Create filters and sorting fields
        
        // Create filter section
        if( $options[ 'store' ][ 'show_search_box' ] || 
            $options[ 'store' ][ 'show_type_filters' ] || 
            $options[ 'store' ][ 'show_color_filters' ] || 
            $options[ 'store' ][ 'show_author_filters' ] ||
            $options[ 'store' ][ 'show_category_filters' ] ||
            !empty( $options[ 'image' ][ 'license' ][ 'description' ] )
        ){
            $left .= "
                    <div class='cpis-image-store-left'>
                        <form method='post' data-ajax='false'>
                ";
            
            if( $options[ 'store' ][ 'show_search_box' ] ){
                $left .= "<div class='cpis-column-title'>".__('Search by', CPIS_TEXT_DOMAIN)."</div>";    
                $left .= "
                <div class='cpis-filter'>
                    <input type='search' name='search_terms' placeholder='".__( 'Search...', CPIS_TEXT_DOMAIN )."' value='".( ( isset( $_SESSION[ $page_id ][ 'cpis_search_terms' ] ) ) ? $_SESSION[ $page_id ][ 'cpis_search_terms' ] : '' )."' style='width:100%;' />
                </div>
                ";
            }    
    
            $left .= "<div class='cpis-column-title'>".__('Filter by', CPIS_TEXT_DOMAIN)."</div>";
            if( $options[ 'store' ][ 'show_type_filters' ] ){
                $str = _cpis_create_select_filter( 'filter_by_type', 'All images', 'cpis_type' );
                if( !empty( $str ) ) $left .= "<div class='cpis-filter'><label>".__(' type: ', CPIS_TEXT_DOMAIN)."</label>$str</div>";
            }
            
            if( $options[ 'store' ][ 'show_color_filters' ] ){
                $str = _cpis_create_select_filter( 'filter_by_color', 'All color schemes', 'cpis_color' );
                if( !empty( $str ) ) $left .= "<div class='cpis-filter'><label>".__(' color scheme: ', CPIS_TEXT_DOMAIN)."</label>$str</div>";
            }
            
            if( $options[ 'store' ][ 'show_author_filters' ] ){
                $str = _cpis_create_select_filter( 'filter_by_author', 'All authors', 'cpis_author' );
                if( !empty( $str ) ) $left .= "<div class='cpis-filter'><label>".__(' authors: ', CPIS_TEXT_DOMAIN)."</label>$str</div>";
            }
            
            if( $options[ 'store' ][ 'show_category_filters' ] ){
                $str = _cpis_create_select_filter( 'filter_by_category', 'All categories', 'cpis_category', 1 );
                if( !empty( $str ) ) $left .= "<div class='cpis-filter'><label>".__(' categories: ', CPIS_TEXT_DOMAIN)."</label>$str</div>";
            }
            
            $left .="
                    </form>
                ";
                
            if( !empty( $options[ 'image' ][ 'license' ][ 'description' ] ) ){
                $license_title = "<div class='cpis-license-title cpis-link'>".( ( !empty( $options[ 'image' ][ 'license' ][ 'title' ] ) ) ? __( $options[ 'image' ][ 'license' ][ 'title' ], CPIS_TEXT_DOMAIN ) : __( 'License', CPIS_TEXT_DOMAIN ) )."</div>";
                
                $left .="
                        $license_title
                        </div>
                    ";    
                
                $left .= "<div class='cpis-license-container'>
                                <div class='cpis-license-close'>[x]</div>
                                <div style='clear:both;'></div>
                                $license_title
                                <div class='cpis-license-description'>".$options[ 'image' ][ 'license' ][ 'description' ]."</div>
                         </div>";
            }else{
                $left .="
                    </div>
                ";
            }
        }
        
        // Create header
        if( $options[ 'store' ][ 'show_ordering' ] ){
            $header .= "<div class='cpis-image-store-header'>";
            
            if( $options[ 'store' ][ 'show_ordering' ] ){
                // Create sorting
                $header .= "
                            <div class='cpis-image-store-ordering'>
                                <form method='POST' data-ajax='false'>
                        ".
                                    __('Order by: ', CPIS_TEXT_DOMAIN).
                        "
                                    <select id='ordering_by' name='ordering_by' onchange='this.form.submit();'>
                                        <option value='post_title' ".( ( $_SESSION[ $page_id ][ 'cpis_ordering' ] == 'post_title') ? "SELECTED" : "").">".__( 'Title', CPIS_TEXT_DOMAIN )."</option>
                                        <option value='purchases' ".( ( $_SESSION[ $page_id ]['cpis_ordering'] == 'purchases' ) ? "SELECTED" : "" ).">".__( 'Popularity', CPIS_TEXT_DOMAIN )."</option>
                                        <option value='post_date' ".( ( $_SESSION[ $page_id ]['cpis_ordering'] == 'post_date') ? "SELECTED" : "" ).">".__( 'Date', CPIS_TEXT_DOMAIN )."</option>
                                    </select>
                                </form>
                            </div>
                        ";
            }
            
            $header .= "<div style='clear:both;'></div></div>";
            
        }                    
        
        // Create items section
        $right .= "
            <div class='cpis-image-store-right'>
        ".$header.$top_ten_carousel;
        
        $width = (100 - 2*( min( $options[ 'store' ][ 'columns' ], max( count( $results ), 1 ) ) -1 ) )/min( $options[ 'store' ][ 'columns' ], max( count( $results ), 1 ) )  - 1;
        
        $right .= "<div class='cpis-image-store-items'>";
        $item_counter = 0;
        $margin = "";
        foreach($results as $result){
            $right .= "<div style='width:{$width}%;{$margin}' class='cpis-image-store-item'>".cpis_display_content( $result->ID, 'store', 'return' )."</div>";
            $item_counter++;
            $margin = "margin-left:2%;";
            if($item_counter % $options[ 'store' ][ 'columns' ] == 0)
            {
                $right .= "<div style='clear:both;'></div>";
                $margin = "";
            }    
                
        }
        $right .= "<div style='clear:both;'></div>";
        $right .= "</div>";
        
        // End right column
        $right .= $page_links."</div>";
        
        return "<div class='cpis-image-store'>".$left.$right."<div style='clear:both;' ></div></div>";
    }
 } // End cpis_replace_shortcode
 
 if( !function_exists( 'cpis_setError' ) ){
    function cpis_setError( $error_text ){
        global $cpis_errors;
        $cpis_errors[] = __( $error_text, CPIS_TEXT_DOMAIN );
    }
 } // End cpis_setError
 
 if( !function_exists( 'cpis_check_download_permissions' ) ){
	function cpis_check_download_permissions(){

		global $wpdb;
		
		// If not session, create it
		if( session_id() == "" ) session_start();

		// Check if download for free or the user is an admin
		if(	!empty( $_SESSION[ 'cpis_download_for_free' ] ) || current_user_can( 'manage_options' ) ) return true;

		// and check the existence of a parameter with the purchase_id
		if( empty( $_REQUEST[ 'purchase_id' ] ) ){ 
			cpis_setError( 'The purchase id is required' );
			return false;
		}	

		if( get_option( 'cpis_safe_download', CPIS_SAFE_DOWNLOAD ) ){
			
			if( session_id() == "" ) session_start();
			if( !empty( $_REQUEST[ 'cpis_user_email' ] ) ) $_SESSION[ 'cpis_user_email' ] =  $_REQUEST[ 'cpis_user_email' ];
			
			// Check if the user has typed the email used to purchase the product 
			if( empty( $_SESSION[ 'cpis_user_email' ] ) ){ 
				$dlurl = _cpis_create_pages( 'cpis-download-page', 'Download Page' );
				$dlurl .= ( ( strpos( $dlurl, '?' ) === false ) ? '?' : '&' ).'cpis-action=download&purchase_id='.$_REQUEST[ 'purchase_id' ];
				cpis_setError( "Please, go to the download page, and enter the email address used in products purchasing" );
				return false;
			}	
			$data = $wpdb->get_row( $wpdb->prepare( 'SELECT CASE WHEN checking_date IS NULL THEN DATEDIFF(NOW(), date) ELSE DATEDIFF(NOW(), checking_date) END AS days, downloads, id FROM '.$wpdb->prefix.CPIS_PURCHASE.' WHERE purchase_id=%s AND email=%s ORDER BY checking_date DESC, date DESC', array( $_REQUEST[ 'purchase_id' ], $_SESSION[ 'cpis_user_email' ] ) ) );
		}else{
			$data = $wpdb->get_row( $wpdb->prepare( 'SELECT CASE WHEN checking_date IS NULL THEN DATEDIFF(NOW(), date) ELSE DATEDIFF(NOW(), checking_date) END AS days, downloads, id FROM '.$wpdb->prefix.CPIS_PURCHASE.' WHERE purchase_id=%s ORDER BY checking_date DESC, date DESC', array( $_REQUEST[ 'purchase_id' ] ) ) );
		}
		
		$options = get_option( 'cpis_options' );
		$valid_download = ( !empty( $options[ 'store' ][ 'download_link' ] ) ) ? $options[ 'store' ][ 'download_link' ] : 3 ;
		if( is_null( $data ) ){
			cpis_setError( 'There is no product associated with the entered data' );
			return false;
		}elseif( $valid_download < $data->days ){ 
			cpis_setError( 'The download link has expired, please contact to the vendor' );
			return false;	
		}elseif( $options[ 'store' ][ 'download_limit' ] > 0 &&  $options[ 'store' ][ 'download_limit' ] <= $data->downloads ){
			cpis_setError( 'The number of downloads has reached its limit, please contact to the vendor' );
			return false;
		}

		if( isset( $_REQUEST[ 'f' ] ) )
		{
			$wpdb->query( $wpdb->prepare( 'UPDATE '.$wpdb->prefix.CPIS_PURCHASE.' SET downloads=downloads+1 WHERE id=%d', $data->id ) );
		}
		
		return true;
	} 
 } // End cpis_check_download_permissions

if (!function_exists('cpis_mime_content_type')) {
	function cpis_mime_content_type($filename) {
		$idx = strtolower(end( explode( '.', $filename )) );
		$mimet = array(	'ai' =>'application/postscript',
			'3gp' =>'audio/3gpp',
			'flv' =>'video/x-flv',
			'aif' =>'audio/x-aiff',
			'aifc' =>'audio/x-aiff',
			'aiff' =>'audio/x-aiff',
			'asc' =>'text/plain',
			'atom' =>'application/atom+xml',
			'avi' =>'video/x-msvideo',
			'bcpio' =>'application/x-bcpio',
			'bmp' =>'image/bmp',
			'cdf' =>'application/x-netcdf',
			'cgm' =>'image/cgm',
			'cpio' =>'application/x-cpio',
			'cpt' =>'application/mac-compactpro',
			'crl' =>'application/x-pkcs7-crl',
			'crt' =>'application/x-x509-ca-cert',
			'csh' =>'application/x-csh',
			'css' =>'text/css',
			'dcr' =>'application/x-director',
			'dir' =>'application/x-director',
			'djv' =>'image/vnd.djvu',
			'djvu' =>'image/vnd.djvu',
			'doc' =>'application/msword',
			'dtd' =>'application/xml-dtd',
			'dvi' =>'application/x-dvi',
			'dxr' =>'application/x-director',
			'eps' =>'application/postscript',
			'etx' =>'text/x-setext',
			'ez' =>'application/andrew-inset',
			'gif' =>'image/gif',
			'gram' =>'application/srgs',
			'grxml' =>'application/srgs+xml',
			'gtar' =>'application/x-gtar',
			'hdf' =>'application/x-hdf',
			'hqx' =>'application/mac-binhex40',
			'html' =>'text/html',
			'html' =>'text/html',
			'ice' =>'x-conference/x-cooltalk',
			'ico' =>'image/x-icon',
			'ics' =>'text/calendar',
			'ief' =>'image/ief',
			'ifb' =>'text/calendar',
			'iges' =>'model/iges',
			'igs' =>'model/iges',
			'jpe' =>'image/jpeg',
			'jpeg' =>'image/jpeg',
			'jpg' =>'image/jpeg',
			'js' =>'application/x-javascript',
			'kar' =>'audio/midi',
			'latex' =>'application/x-latex',
			'm3u' =>'audio/x-mpegurl',
			'man' =>'application/x-troff-man',
			'mathml' =>'application/mathml+xml',
			'me' =>'application/x-troff-me',
			'mesh' =>'model/mesh',
			'm4a' =>'audio/x-m4a',
			'mid' =>'audio/midi',
			'midi' =>'audio/midi',
			'mif' =>'application/vnd.mif',
			'mov' =>'video/quicktime',
			'movie' =>'video/x-sgi-movie',
			'mp2' =>'audio/mpeg',
			'mp3' =>'audio/mpeg',
			'mp4' =>'video/mp4',
			'm4v' =>'video/x-m4v',
			'mpe' =>'video/mpeg',
			'mpeg' =>'video/mpeg',
			'mpg' =>'video/mpeg',
			'mpga' =>'audio/mpeg',
			'ms' =>'application/x-troff-ms',
			'msh' =>'model/mesh',
			'mxu m4u' =>'video/vnd.mpegurl',
			'nc' =>'application/x-netcdf',
			'oda' =>'application/oda',
			'ogg' =>'application/ogg',
			'pbm' =>'image/x-portable-bitmap',
			'pdb' =>'chemical/x-pdb',
			'pdf' =>'application/pdf',
			'pgm' =>'image/x-portable-graymap',
			'pgn' =>'application/x-chess-pgn',
			'php' =>'application/x-httpd-php',
			'php4' =>'application/x-httpd-php',
			'php3' =>'application/x-httpd-php',
			'phtml' =>'application/x-httpd-php',
			'phps' =>'application/x-httpd-php-source',
			'png' =>'image/png',
			'pnm' =>'image/x-portable-anymap',
			'ppm' =>'image/x-portable-pixmap',
			'ppt' =>'application/vnd.ms-powerpoint',
			'ps' =>'application/postscript',
			'qt' =>'video/quicktime',
			'ra' =>'audio/x-pn-realaudio',
			'ram' =>'audio/x-pn-realaudio',
			'ras' =>'image/x-cmu-raster',
			'rdf' =>'application/rdf+xml',
			'rgb' =>'image/x-rgb',
			'rm' =>'application/vnd.rn-realmedia',
			'roff' =>'application/x-troff',
			'rtf' =>'text/rtf',
			'rtx' =>'text/richtext',
			'sgm' =>'text/sgml',
			'sgml' =>'text/sgml',
			'sh' =>'application/x-sh',
			'shar' =>'application/x-shar',
			'shtml' =>'text/html',
			'silo' =>'model/mesh',
			'sit' =>'application/x-stuffit',
			'skd' =>'application/x-koan',
			'skm' =>'application/x-koan',
			'skp' =>'application/x-koan',
			'skt' =>'application/x-koan',
			'smi' =>'application/smil',
			'smil' =>'application/smil',
			'snd' =>'audio/basic',
			'spl' =>'application/x-futuresplash',
			'src' =>'application/x-wais-source',
			'sv4cpio' =>'application/x-sv4cpio',
			'sv4crc' =>'application/x-sv4crc',
			'svg' =>'image/svg+xml',
			'swf' =>'application/x-shockwave-flash',
			't' =>'application/x-troff',
			'tar' =>'application/x-tar',
			'tcl' =>'application/x-tcl',
			'tex' =>'application/x-tex',
			'texi' =>'application/x-texinfo',
			'texinfo' =>'application/x-texinfo',
			'tgz' =>'application/x-tar',
			'tif' =>'image/tiff',
			'tiff' =>'image/tiff',
			'tr' =>'application/x-troff',
			'tsv' =>'text/tab-separated-values',
			'txt' =>'text/plain',
			'ustar' =>'application/x-ustar',
			'vcd' =>'application/x-cdlink',
			'vrml' =>'model/vrml',
			'vxml' =>'application/voicexml+xml',
			'wav' =>'audio/x-wav',
			'wbmp' =>'image/vnd.wap.wbmp',
			'wbxml' =>'application/vnd.wap.wbxml',
			'wml' =>'text/vnd.wap.wml',
			'wmlc' =>'application/vnd.wap.wmlc',
			'wmlc' =>'application/vnd.wap.wmlc',
			'wmls' =>'text/vnd.wap.wmlscript',
			'wmlsc' =>'application/vnd.wap.wmlscriptc',
			'wmlsc' =>'application/vnd.wap.wmlscriptc',
			'wrl' =>'model/vrml',
			'xbm' =>'image/x-xbitmap',
			'xht' =>'application/xhtml+xml',
			'xhtml' =>'application/xhtml+xml',
			'xls' =>'application/vnd.ms-excel',
			'xml xsl' =>'application/xml',
			'xpm' =>'image/x-xpixmap',
			'xslt' =>'application/xslt+xml',
			'xul' =>'application/vnd.mozilla.xul+xml',
			'xwd' =>'image/x-xwindowdump',
			'xyz' =>'chemical/x-xyz',
			'zip' =>'application/zip'
		);

		if (isset( $mimet[$idx] )) {
			return $mimet[$idx];
		} else {
			return 'application/octet-stream';
		}
	}
} 

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
 
if( !function_exists( 'cpis_download_file' ) ){
	function cpis_download_file(){
		global $wpdb, $cpis_errors;
		
		if( isset( $_REQUEST[ 'f' ] ) && cpis_check_download_permissions() ){
			header( 'Content-Type: '.cpis_mime_content_type( basename( $_REQUEST[ 'f' ] ) ) );
			header( 'Content-Disposition: attachment; filename="'.$_REQUEST[ 'f' ].'"' );
			if( cpis_checkMemory( array( CPIS_DOWNLOAD.'/'.$_REQUEST[ 'f' ] ) ) ){
				readfile( CPIS_DOWNLOAD.'/'.$_REQUEST[ 'f' ] );
			}else{
				@unlink( CPIS_DOWNLOAD.'/.htaccess');
				header( 'location:'.CPIS_PLUGIN_URL.'/downloads/'.$_REQUEST[ 'f' ] );
			}
		}else{
			$dlurl = _cpis_create_pages( 'cpis-download-page', 'Download Page' );
			$dlurl .= ( ( strpos( $dlurl, '?' ) === false ) ? '?' : '&' ).'cpis-action=download'.( ( !empty( $_REQUEST[ 'purchase_id' ] ) ) ? '&purchase_id='.$_REQUEST[ 'purchase_id' ] : '' );
			header( 'location: '.$dlurl );
		}
		exit;
	} 
} // End cpis_download_file
 
 
// From PayPal Data RAW
/*
  $fieldsArr, array( 'fields name' => 'alias', ... )
  $selectAdd, used if is required complete the results like: COUNT(*) as count
  $groupBy, array( 'alias', ... ) the alias used in the $fieldsArr parameter
  $orderBy, array( 'alias' => 'direction', ... ) the alias used in the $fieldsArr parameter, direction = ASC or DESC
*/
if( !function_exists( 'cpis_getFromPayPalData' ) ){
	function cpis_getFromPayPalData( $fieldsArr, $selectAdd = '', $from = '', $where = '', $groupBy = array(), $orderBy = array(), $returnAs = 'json' ){
		global $wpdb;
		
		$_select = 'SELECT ';
		$_from = 'FROM '.$wpdb->prefix.CPIS_PURCHASE.( ( !empty( $from ) ) ? ','.$from : '' );
		$_where = 'WHERE '.( ( !empty( $where ) ) ? $where : 1 );
		$_groupBy = ( !empty( $groupBy ) ) ? 'GROUP BY ' : '';
		$_orderBy = ( !empty( $orderBy ) ) ? 'ORDER BY ' : '';
		
		$separator = '';
		foreach( $fieldsArr as $key => $value ){
			$length = strlen( $key )+1;
			$_select .= $separator.' 
							SUBSTRING(paypal_data, 
							LOCATE("'.$key.'", paypal_data)+'.$length.', 
							LOCATE("\r\n", paypal_data, LOCATE("'.$key.'", paypal_data))-(LOCATE("'.$key.'", paypal_data)+'.$length.')) AS '.$value; 
			$separator = ',';
		}
		
		if( !empty( $selectAdd ) ){
			$_select .= $separator.$selectAdd; 
		}
		
		$separator = '';
		foreach( $groupBy as $value ){
			$_groupBy .= $separator.$value;
			$separator = ',';
		}
		
		$separator = '';
		foreach( $orderBy as $key => $value ){
			$_orderBy .= $separator.$key.' '.$value;
			$separator = ',';
		}
		
		$query = $_select.' '.$_from.' '.$_where.' '.$_groupBy.' '.$_orderBy;

		$result = $wpdb->get_results( $query );
		
		if( !empty( $result ) ){
			switch( $returnAs ){
				case 'json':
					return json_encode( $result );
				break;
				default:
					return $result;
				break;
			}
		}
	} 
} // End cpis_getFromPayPalData
 
 /**
 * CPISProductWidget Class
 */
 class CPISProductWidget extends WP_Widget {
    
    /** constructor */
    function CPISProductWidget() {
        parent::WP_Widget(false, $name = 'Image Store Product');	        
    }

    function widget($args, $instance) {		
        extract( $args );
        $title = apply_filters('widget_title', $instance['title']);
        
        $defaults = array( 'product_id' => '' );
		$instance_p = wp_parse_args( (array) $instance, $defaults ); 
        
        $product_id  = $instance_p[ 'product_id' ];
        
        $atts = array( 'id' => $product_id );
        
        ?>
              <?php echo $before_widget; 
                    if ( $title ) echo $before_title . $title . $after_title; 
                    $atts[ 'layout' ] = 'widget';
                    print cpis_replace_product_shortcode($atts);
              ?>
              
              <?php echo $after_widget; ?>
        <?php
    }

    function update($new_instance, $old_instance) {				
        $instance = $old_instance;

		/* Strip tags (if needed) and update the widget settings. */
		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['product_id'] = $new_instance['product_id']*1;
		
		return $instance;
    }

    function form( $instance ) {
    
        /* Set up some default widget settings. */
		$defaults = array( 'title' => '', 'product_id' => '' );
		$instance = wp_parse_args( (array) $instance, $defaults ); 
        
        $title       = $instance[ 'title' ];
        $product_id  = $instance[ 'product_id' ];
        
        
        ?>
            <p><label for="<?php echo $this->get_field_id('title'); ?>"><?php _e('Title:'); ?> <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" name="<?php echo $this->get_field_name('title'); ?>" type="text" value="<?php echo $title; ?>" /></label></p>
            
            <p>
                <label for="<?php echo $this->get_field_id('product_id'); ?>"><?php _e('Enter the product ID:', CPIS_TEXT_DOMAIN); ?><br />
                    <input class="widefat" id="<?php echo $this->get_field_id( 'product_id' ); ?>" name="<?php echo $this->get_field_name( 'product_id' ); ?>" value="<?php echo $product_id; ?>" />
                </label>
            </p>   
        <?php 
    }
  } // clase CPISProductWidget 

?>