<?php
/*
Plugin Name: CP Image Store with Slideshow
Plugin URI: http://wordpress.dwbooster.com/content-tools/cp-image-store
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


// CONST
define( 'CPIS_PLUGIN_DIR', dirname( __FILE__ ) );
define( 'CPIS_PLUGIN_URL', plugins_url( '', __FILE__ ) );
define( 'CPIS_H_URL', cpis_get_site_url() );
define( 'CPIS_DOWNLOAD', dirname( __FILE__ ).'/downloads' );
define( 'CPIS_IMAGE_STORE_SLUG', 'image-store-menu' );
define( 'CPIS_IMAGES_URL',  CPIS_PLUGIN_URL.'/images' );
define( 'CPIS_TEXT_DOMAIN',  'cpis-text-domain' );
define( 'CPIS_SC_EXPIRE', 3); // Time for shopping cart expiration, default 3 days


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
            email VARCHAR(255) NOT NULL,
            amount FLOAT NOT NULL DEFAULT 0,
            paypal_data TEXT,
            note TEXT,
            UNIQUE KEY id (id)
         );";             
        $wpdb->query($sql); 
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
add_action('init', 'cpis_init', 0);
if( !function_exists( 'cpis_init' ) ){
    function cpis_init(){
        // Create post types
        cpis_init_post_types();
        
        // Create taxonomies
        cpis_init_taxonomies();
        
        if( !is_admin() ){
            
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
                    
                    case 'download':
                        if( !empty( $_REQUEST[ 'purchase_id' ] ) ){
                            add_filter( 'the_title', 'cpis_the_title' );
                        }else{
                            exit;
                        }    
                    break;
                }
            }
            add_shortcode( 'codepeople-image-store', 'cpis_replace_shortcode' );
            add_shortcode( 'codepeople-image-store-product', 'cpis_replace_product_shortcode' );
            add_filter( 'the_content', 'cpis_the_content' );
        }
    }
} // End cpis_ini

add_action('admin_init', 'cpis_admin_init', 0);
if( !function_exists( 'cpis_admin_init' ) ){
    
    function _cpis_create_pages( $slug, $title ){
        $page = get_page_by_path( $slug ); 
        if( is_null( $page ) ){
            if( wp_insert_post(
                    array(
                        'comment_status' => 'closed',
                        'post_name' => $slug,
                        'post_title' => __( $title, CPIS_TEXT_DOMAIN ),
                        'post_status' => 'publish',
                        'post_type' => 'page'
                    )
                )    
            ){
                $page = get_page_by_path( $slug ); 
            }
        }else{
            $page->post_status = 'publish';
            wp_update_post( $page );
        }
        
        return ( !is_null( $page ) ) ? get_permalink($page->ID) : CPIS_H_URL;
    }
    
    function cpis_admin_init(){
        
        $plugin = plugin_basename(__FILE__);
        add_filter("plugin_action_links_".$plugin, 'cpis_customAdjustmentsLink');
        
        // Create database
        cpis_create_db();
        
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
        $help_link = '<a href="http://wordpress.dwbooster.com/content-tools/cp-image-store" target="_blank">'.__( 'Help', CPIS_TEXT_DOMAIN ).'</a>';
        array_unshift( $links, $help_link );
        return $links;
    }
} // End cpis_customAdjustmentsLink

if( !function_exists( 'cpis_store_button' ) ){
    function cpis_store_button(){
        global $post;
			
        if( isset( $post ) && $post->post_type != 'cpis_image' ){
            print '<a href="javascript:cpis_insert_store();" title="'.__( 'Insert Image Store', CPIS_TEXT_DOMAIN ).'"><img src="'.CPIS_PLUGIN_URL.'/images/image-store-menu-icon.png'.'" alt="'.__( 'Insert Image Store', CPIS_TEXT_DOMAIN ).'" /></a>';
            
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
            $page = & $pages[$i];
            
            if ( in_array( $page->ID, $exclude ) ) {
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
        global $wpdb;
        $options = get_option( 'cpis_options' );
        
        if (isset($_POST['cpis_settings']) && wp_verify_nonce( $_POST['cpis_settings'], plugin_basename( __FILE__ ) ) ){
            $noptions = array();
            
            $cpis_currency_symbol   = trim( $_POST['cpis_currency_symbol'] );
            $cpis_currency          = trim( $_POST['cpis_currency'] );
            $cpis_language          = trim( $_POST['cpis_language'] );
            
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
            $options = $noptions;
?>				
            <div class="updated" style="margin:5px 0;"><strong><?php _e("Settings Updated", CPIS_TEXT_DOMAIN); ?></strong></div>
<?php				
        }
        
        $options = get_option( 'cpis_options' );
?>        
        <p style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;">
            For reporting an issue or to request a customization, <a href="http://wordpress.dwbooster.com/contact-us" target="_blank">CLICK HERE</a>
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
                            <th><?php _e( 'Allow filtering by type', CPIS_TEXT_DOMAIN ); ?></th>
                            <td><input type="checkbox" name="cpis_show_type_filters" size="40" value="1" <?php if ( $options[ 'store' ][ 'show_type_filters' ] ) echo 'checked'; ?> /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e( 'Allow filtering by color', CPIS_TEXT_DOMAIN ); ?></th>
                            <td><input type="checkbox" name="cpis_show_color_filters" size="40" value="1" <?php if ( $options[ 'store' ][ 'show_color_filters'] ) echo 'checked'; ?> /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e( 'Allow filtering by author', CPIS_TEXT_DOMAIN ); ?></th>
                            <td><input type="checkbox" name="cpis_show_author_filters" size="40" value="1" <?php if ( $options[ 'store' ][ 'show_author_filters'] ) echo 'checked'; ?> /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e( 'Allow filtering by category', CPIS_TEXT_DOMAIN ); ?></th>
                            <td><input type="checkbox" name="cpis_show_category_filters" size="40" value="1" <?php if ( $options[ 'store' ][ 'show_category_filters'] ) echo 'checked'; ?> /></td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e( 'Allow pagination', CPIS_TEXT_DOMAIN ); ?></th>
                            <td><input type="checkbox" name="cpis_show_pagination" size="40" value="1" <?php if ( $options[ 'store' ][ 'show_pagination' ] ) echo 'checked'; ?> /></td>
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
                                <input type="checkbox" name="cpis_social_buttons" <?php echo ( ( $options[ 'store' ][ 'social_buttons' ] ) ? 'CHECKED' : '' ); ?> /><br />
                                <em><?php _e('The option enables the buttons for share the pages of songs and collections in social networks', CPIS_TEXT_DOMAIN); ?></em>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e('Allow sorting results', CPIS_TEXT_DOMAIN); ?></th>
                            <td>
                                <input type="checkbox" name="cpis_show_ordering" <?php echo ( ( $options[ 'store' ][ 'show_ordering' ] ) ? 'CHECKED' : '' ); ?> /><br />
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
                                <br /><?php _e( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/cp-image-store" target="_blank" >premium version</a> of plugin.', CPIS_TEXT_DOMAIN ); ?>
                            </td>
                        </tr>
                        <tr valign="top">
                            <th><?php _e('Watermark text', CPIS_TEXT_DOMAIN); ?></th>
                            <td><input type="text" placeholder="Image Store" disabled />
                            <br /><?php _e( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/cp-image-store" target="_blank" >premium version</a> of plugin.', CPIS_TEXT_DOMAIN ); ?>
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
                        <td><input type="checkbox" name="cpis_activate_sandbox" value="1" <?php if ( $options[ 'paypal' ][ 'activate_sandbox' ] ) echo 'checked'; ?> /></td>
                        </tr>    
                    
                        <tr valign="top">        
                        <th scope="row"><?php _e( 'Paypal email', CPIS_TEXT_DOMAIN ); ?></th>
                        <td><input type="text" name="cpis_paypal_email" size="40" value="<?php echo esc_attr( $options[ 'paypal' ]['paypal_email'] ); ?>" /></td>
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
                        <br /><?php _e( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/cp-image-store" target="_blank" >premium version</a> of plugin.', CPIS_TEXT_DOMAIN ); ?>
                        </td>
                        </tr> 
                        
                        <tr valign="top">
                        <th scope="row"><?php _e( 'Download link valid for', CPIS_TEXT_DOMAIN ); ?></th>
                        <td><input type="text" name="cpis_download_link" value="<?php echo esc_attr( $options[ 'store' ][ 'download_link' ] ); ?>" /> <?php _e( 'day(s)', CPIS_TEXT_DOMAIN )?></td>
                        </tr>  
                        
                        <tr valign="top">
                        <th scope="row"><?php _e( 'Pack all purchased audio files as a single ZIP file', CPIS_TEXT_DOMAIN ); ?></th>
                        <td><input type="checkbox" disabled >
                        <?php
                            if(!class_exists('ZipArchive'))
                                echo '<br /><span class="explain-text">'.__("Your server can't create Zipped files dynamically. Please, contact to your hosting provider for enable ZipArchive in the PHP script", CPIS_TEXT_DOMAIN).'</span>';
                        ?>
                        <br /><?php _e( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/cp-image-store" target="_blank" >premium version</a> of plugin.', CPIS_TEXT_DOMAIN ); ?>
                        </td>
                        </tr>
                     </table>  
                </div>
            </div>
            
            <!--DISCOUNT BOX -->
            <div class="postbox">
                <h3 class='hndle' style="padding:5px;"><span><?php _e('Discount Settings', CPIS_TEXT_DOMAIN); ?></span></h3>
                <div class="inside">
                    <div style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;"><?php _e( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/cp-image-store" target="_blank" >premium version</a> of plugin.', CPIS_TEXT_DOMAIN ); ?></div><br />
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
                    <div style="border:1px solid #E6DB55;margin-bottom:10px;padding:5px;background-color: #FFFFE0;"><?php _e( 'Available in <a href="http://wordpress.dwbooster.com/content-tools/cp-image-store" target="_blank" >premium version</a> of plugin.', CPIS_TEXT_DOMAIN ); ?></div>
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
            if( isset( $_POST[ 'purchase_id' ] ) ){ // Delete the purchase
                $wpdb->query(
                    $wpdb->prepare(
                        "DELETE FROM ".$wpdb->prefix.CPIS_PURCHASE." WHERE id=%d",
                        $_POST[ 'purchase_id' ]
                    )
                );
            }
        }
					
        $from_day   = ( isset( $_POST[ 'from_day' ] ) )   ? $_POST[ 'from_day' ]   : date( 'j' );
        $from_month = ( isset( $_POST[ 'from_month' ] ) ) ? $_POST[ 'from_month' ] : date( 'm' );
        $from_year  = ( isset( $_POST[ 'from_year' ] ) )  ? $_POST[ 'from_year' ]  : date( 'Y' );
        
        $to_day   = ( isset( $_POST[ 'to_day' ] ) )   ? $_POST[ 'to_day' ]   : date( 'j' );
        $to_month = ( isset( $_POST[ 'to_month' ] ) ) ? $_POST[ 'to_month' ] : date( 'm' );
        $to_year  = ( isset( $_POST[ 'to_year' ] ) )  ? $_POST[ 'to_year' ]  : date( 'Y' );
        
        $purchase_list = $wpdb->get_results(
            "SELECT purchase.*, posts.* FROM ".$wpdb->prefix.CPIS_PURCHASE." AS purchase, ".$wpdb->prefix."posts AS posts WHERE posts.ID = purchase.product_id AND DATEDIFF(purchase.date, '{$from_year}-{$from_month}-{$from_day}')>=0 AND DATEDIFF(purchase.date, '{$to_year}-{$to_month}-{$to_day}')<=0;"
        );
        
?>
        <form method="post" action="<?php echo $_SERVER[ 'REQUEST_URI' ]; ?>" id="purchase_form">
        <?php wp_nonce_field( plugin_basename( __FILE__ ), 'cpis_purchase_stats' ); ?>
        <input type="hidden" name="tab" value="reports" />
        <!-- FILTER REPORT -->
        <div class="postbox">
            <h3 class='hndle' style="padding:5px;"><span><?php _e( 'Filter from date', CPIS_TEXT_DOMAIN ); ?></span></h3>
            <div class="inside">
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
                <input type="text" name="form_year" value="<?php print $from_year; ?>" />
                
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
                <input type="text" name="to_year" value="<?php print $from_year; ?>" />
                
                <input type="submit" value="<?php _e('Search', CPIS_TEXT_DOMAIN); ?>" class="button-primary" />
            </div>
        </div>	
        <!-- PURCHASE LIST -->
        <div class="postbox">
            <h3 class='hndle' style="padding:5px;"><span><?php _e( 'Store sales report', CPIS_TEXT_DOMAIN ); ?></span></h3>
            <div class="inside">
                <table class="form-table" style="border-bottom:1px solid #CCC;margin-bottom:10px;">
                    <THEAD>
                        <TR style="border-bottom:1px solid #CCC;">
                            <TH>Product</TH><TH>Buyer</TH><TH>Amount</TH><TH>Currency</TH><TH>Download link</TH><TH>Notes</TH><TH></TH>
                        </TR>
                    </THEAD>
                    <TBODY>
                    <?php
                    $totals = array( 'UNDEFINED' => 0 );
                    if( count( $purchase_list ) ){	
                        foreach( $purchase_list as $purchase ){
                            
                            if( preg_match( '/mc_currency=([^\s]*)/', $purchase->paypal_data, $matches ) ){
                                $currency = strtoupper( $matches[1] );
                                if( !isset( $totals[ $currency ] ) ) $totals[ $currency ] = $purchase->amount;
                                else $totals[ $currency ] += $purchase->amount;
                            }else{
                                $currency = '';
                                $totals[ 'UNDEFINED' ] += $purchase->amount;
                            }
                            echo '
                                <TR>
                                    <TD><a href="'.get_permalink($purchase->ID).'" target="_blank">'.$purchase->post_title.'</a></TD>
                                    <TD>'.$purchase->email.'</TD>
                                    <TD>'.$purchase->amount.'</TD>
                                    <TD>'.$currency.'</TD>
                                    <TD><a href="'.CPIS_H_URL.'?cpis-action=download&purchase_id='.$purchase->purchase_id.'" target="_blank">Download Link</a></TD>
                                    <TD>'.$purchase->note.'</TD>
                                    <TD><input type="button" class="button-primary" onclick="cpis_delete_purchase('.$purchase->id.');" value="Delete"></TD>
                                </TR>
                            ';
                        }
                    }else{
                        echo '
                            <TR>
                                <TD COLSPAN="6">
                                    '.__('No sales yet', CPIS_TEXT_DOMAIN).'
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
            wp_enqueue_script('cpis-admin-script', CPIS_PLUGIN_URL.'/js/admin.js', array('jquery'), null, true);
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
            $options = get_option( 'cpis_options' );
            
            wp_enqueue_style('jquery-style', 'http://ajax.googleapis.com/ajax/libs/jqueryui/1.8.3/themes/smoothness/jquery-ui.css');
            wp_enqueue_style('cpis-style', CPIS_PLUGIN_URL.'/css/public.css');
            
            wp_enqueue_script('json2');
            wp_enqueue_script('jquery-ui-core');
            wp_enqueue_script('jquery-ui-position');
            wp_enqueue_script('cpis-carousel', CPIS_PLUGIN_URL.'/js/jquery.carouFredSel-6.2.1-packed.js');
            wp_enqueue_script('cpis-script', CPIS_PLUGIN_URL.'/js/public.js', array('jquery', 'json2', 'jquery-ui-core', 'cpis-carousel', 'jquery-ui-position'), null, true);
                
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
        if( 'cpis_image' == $post->post_type ){
            $path[ 'path' ] = CPIS_PLUGIN_DIR.'/uploads/files'.$path[ 'subdir' ];
            $path[ 'url' ] = CPIS_PLUGIN_URL.'/uploads/files'.$path[ 'subdir' ];
            $path[ 'basedir' ] = CPIS_PLUGIN_DIR.'/uploads/files';
            $path[ 'baseurl' ] = CPIS_PLUGIN_URL.'/uploads/files';
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
 
 if( !function_exists( 'cpis_the_title' ) ){
    function cpis_the_title( $the_title ){
        global $id;
        
        if( in_the_loop() && isset( $id ) && isset( $_REQUEST ) && isset( $_REQUEST[ 'cpis-action' ] ) ){
            switch( strtolower( $_REQUEST[ 'cpis-action' ] ) ){
                case 'download':
                    return 'Download Page';
                break;
            }
        }
        
        return $the_title;
    }
 } // End cpis_the_title
 
 if( !function_exists( 'cpis_the_content' ) ){
    function cpis_the_content( $the_content  ){
        global $post;
			
        if( in_the_loop() && $post && ( $post->post_type == 'cpis_image' ) ){
            return cpis_display_content( $post->ID, ( ( is_singular() ) ? 'single' : 'multiple' ), 'return' );
        }else{
            if( isset( $_REQUEST ) && isset( $_REQUEST[ 'cpis-action' ] ) ){
                switch( strtolower( $_REQUEST[ 'cpis-action' ] ) ){
                    case 'download':
                        include CPIS_PLUGIN_DIR.'/includes/download.php';
                        return $download_links_str;
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
        $option_none = __( $option_none, CPIS_TEXT_DOMAIN );
        $select = wp_dropdown_categories('name='.$name.'&show_option_none='.$option_none.'&orderby=name&echo=0&taxonomy='.$taxonomy.'&hide_if_empty=1&hierarchical='.$hierarchical.( ( isset( $_SESSION[ $taxonomy ] ) ) ? '&selected='.$_SESSION[ $taxonomy ] : '' ) );
        $select = preg_replace("#<select([^>]*)>#", "<select$1 onchange='return this.form.submit()'>", $select);
        return $select;
    }
    
    function cpis_replace_shortcode( $atts, $content, $tag ){
        global $wpdb;
		
        $options = get_option( 'cpis_options' );
        
        // Generated music store
        $top_ten_carousel = "";
        $page_links = "";
        $header = "";
        $left = "";
        $right = "";
        
        // Set session variable for pagination
        if( !isset( $_SESSION[ 'cpis_page' ] ) ) $_SESSION[ 'cpis_page' ] = 0;
        if( isset( $_REQUEST ) && isset( $_REQUEST[ 'cpis_page' ] ) ){
            $_SESSION[ 'cpis_page' ] = $_REQUEST[ 'cpis_page' ];
        }
        
        // Extract product filters
        
        if( isset( $_REQUEST[ 'filter_by_type' ] ) ){
            $_SESSION[ 'cpis_type' ] = $_REQUEST[ 'filter_by_type' ];
            $_SESSION[ 'cpis_page' ] = 0;
        }
        
        if( isset( $_REQUEST[ 'filter_by_category' ] ) ){
            $_SESSION[ 'cpis_category' ] = $_REQUEST[ 'filter_by_category' ];
            $_SESSION[ 'cpis_page' ] = 0;
        }
        
        if( isset( $_REQUEST[ 'filter_by_author' ] ) ){
            $_SESSION[ 'cpis_author' ] = $_REQUEST[ 'filter_by_author' ];
            $_SESSION[ 'cpis_page' ] = 0;
        }
        
        if( isset( $_REQUEST[ 'filter_by_color' ] ) ){
            $_SESSION[ 'cpis_color' ] = $_REQUEST[ 'filter_by_color' ];
            $_SESSION[ 'cpis_page' ] = 0;
        }
        
        if( isset( $_REQUEST[ 'ordering_by' ] ) ){
            $_SESSION[ 'cpis_ordering' ] = $_REQUEST[ 'ordering_by' ];
            $_SESSION[ 'cpis_page' ] = 0;
        }else{
            $_SESSION[ 'cpis_ordering' ] = "post_title";
        }
        
        // Query clauses 
        $_select 	= "SELECT SQL_CALC_FOUND_ROWS DISTINCT posts.ID";
        $_from 		= "FROM ".$wpdb->prefix."posts as posts,".$wpdb->prefix.CPIS_IMAGE." as posts_data"; 
        $_where 	= "WHERE posts.ID = posts_data.id AND posts.post_status='publish' AND posts.post_type='cpis_image' ";
        $_order_by 	= "ORDER BY ".( ( $_SESSION['cpis_ordering'] != 'purchases' ) ? "posts" : "posts_data" ).".".$_SESSION['cpis_ordering']." ".( ( $_SESSION['cpis_ordering'] == 'post_title' ) ? "ASC" : "DESC" );
        $_limit 	= "";
        
        if( ( !empty( $_SESSION['cpis_type'] )     && $_SESSION['cpis_type'] != -1 )   ||
            ( !empty( $_SESSION['cpis_color'] )    && $_SESSION['cpis_color'] != -1 )  ||
            ( !empty( $_SESSION['cpis_author'] )   && $_SESSION['cpis_author'] != -1 ) || 
            ( !empty( $_SESSION['cpis_category'] ) && $_SESSION['cpis_category'] != -1 )
        ){
            $_select_sub 	= "SELECT DISTINCT posts.ID";
            
            // Load the taxonomy tables
            $_from_sub = "$_from, ".$wpdb->prefix."term_taxonomy as taxonomy, ".$wpdb->prefix."term_relationships as term_relationships, ".$wpdb->prefix."terms as terms";
            
            $_where_sub = "$_where AND taxonomy.term_taxonomy_id=term_relationships.term_taxonomy_id AND term_relationships.object_id=posts.ID AND taxonomy.term_id=terms.term_id ";
            
            // Filter by type 
            if( !empty( $_SESSION['cpis_type'] ) && $_SESSION['cpis_type'] != -1 ){
                $_where .= " AND posts.ID IN ( $_select_sub $_from_sub $_where_sub AND "._cpis_filter_by_taxonomy( 'cpis_type', $_SESSION['cpis_type'] ).")";
            }
            
            if( !empty( $_SESSION['cpis_author'] ) && $_SESSION[ 'cpis_author' ] != -1 ){
                $_where .= " AND posts.ID IN ( $_select_sub $_from_sub $_where_sub AND "._cpis_filter_by_taxonomy( 'cpis_author', $_SESSION['cpis_author'] ).")";
            }
            
            if( !empty( $_SESSION['cpis_color'] ) && $_SESSION[ 'cpis_color' ] != -1 ){
                $_where .= " AND posts.ID IN ( $_select_sub $_from_sub $_where_sub AND "._cpis_filter_by_taxonomy( 'cpis_color', $_SESSION['cpis_color'] ).")";
            }
            
            if( !empty( $_SESSION['cpis_category'] ) && $_SESSION[ 'cpis_category' ] != -1 ){
                    $_where .= " AND posts.ID IN ( $_select_sub $_from_sub $_where_sub AND "._cpis_filter_by_taxonomy( 'cpis_category', $_SESSION['cpis_category'], true ).")";
            }
            
            // End taxonomies
        } 
        
        $query = $_select." ".$_from." ".$_where." ".$_order_by." ".$_limit;

        if( $options[ 'store' ][ 'show_pagination' ] && is_numeric( $options[ 'store' ][ 'items_page' ] ) &&  $options[ 'store' ][ 'items_page' ] > 1 ){
            $page = $_SESSION[ 'cpis_page' ];
            
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
        if( $options[ 'store' ][ 'show_type_filters' ] || 
            $options[ 'store' ][ 'show_color_filters' ] || 
            $options[ 'store' ][ 'show_author_filters' ] ||
            $options[ 'store' ][ 'show_category_filters' ] ||
            !empty( $options[ 'image' ][ 'license' ][ 'description' ] )
        ){
            $left .= "
                    <div class='cpis-image-store-left'>
                        <form method='post' data-ajax='false'>
                ";
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
                                        <option value='post_title' ".( ( $_SESSION[ 'cpis_ordering' ] == 'post_title') ? "SELECTED" : "").">".__( 'Title', CPIS_TEXT_DOMAIN )."</option>
                                        <option value='purchases' ".( ( $_SESSION['cpis_ordering'] == 'purchases' ) ? "SELECTED" : "" ).">".__( 'Popularity', CPIS_TEXT_DOMAIN )."</option>
                                        <option value='post_date' ".( ( $_SESSION['cpis_ordering'] == 'post_date') ? "SELECTED" : "" ).">".__( 'Date', CPIS_TEXT_DOMAIN )."</option>
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
        
        $width = floor( 100/min( $options[ 'store' ][ 'columns' ], max( count( $results ), 1 ) ) );
        
        $right .= "<div class='cpis-image-store-items'>";
        $item_counter = 0;
        foreach($results as $result){
            $right .= "<div style='width:{$width}%;' class='cpis-image-store-item'>".cpis_display_content( $result->ID, 'store', 'return' )."</div>";
            $item_counter++;
            if($item_counter % $options[ 'store' ][ 'columns' ] == 0)
                $right .= "<div style='clear:both;'></div>";
        }
        $right .= "<div style='clear:both;'></div>";
        $right .= "</div>";
        
        // End right column
        $right .= $page_links."</div>";
        
        return $left.$right."<div style='clear:both;' ></div>";
    }
 } // End cpis_replace_shortcode
 
?>