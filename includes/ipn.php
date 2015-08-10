<?php
	/* Short and sweet */
    error_reporting( E_ERROR | E_PARSE ); 
	echo 'Start IPN';
    global $wpdb;
    
    function register_purchase( $product_id, $purchase_id, $email, $amount, $paypal_data, $purchase_note ){
        global $wpdb;
        return $wpdb->insert(
            $wpdb->prefix.CPIS_PURCHASE,
            array(
                'product_id'  => $product_id,
                'purchase_id' => $purchase_id,
                'date'		  => date( 'Y-m-d H:i:s'),
                'email'		  => $email,
                'amount'	  => $amount,
                'paypal_data' => $paypal_data,
                'note'        => $purchase_note
            ),
            array( '%d', '%s', '%s', '%s', '%f', '%s', '%s' )
        );
    }
    
	$item_name = $_POST[ 'item_name' ];
	$item_number = $_POST[ 'item_number' ];
	$payment_status = $_POST[ 'payment_status' ];
	$payment_amount = $_POST[ 'mc_gross' ];
	$payment_currency = $_POST[ 'mc_currency' ];
	$txn_id = $_POST[ 'txn_id' ];
	$receiver_email = $_POST[ 'receiver_email' ];
	$payer_email = $_POST[ 'payer_email' ];
	$payment_type = $_POST[ 'payment_type' ];

	if ( $payment_status != 'Completed' && $payment_type != 'echeck' ) exit;
	if ( $payment_type == 'echeck' && $payment_status == 'Completed' ) exit;
	
    $paypal_data = "";
	foreach ( $_POST as $item => $value ) $paypal_data .= $item."=".$value."\r\n";


    if( !isset( $_GET[ 'purchase_id' ] ) ) exit;
    $purchase_id = $_GET[ 'purchase_id' ];
    
    $options = get_option( 'cpis_options' );
    
    if(!isset($_GET['id'])) exit;
    
    $ids = $_GET['id'];
    $products = array();
    $total = 0;

    foreach( $ids as $id ){
    
        $file = $wpdb->get_row( 
            $wpdb->prepare(
                "SELECT file.price as price, image_file.id_image as id_image, file.id as id FROM ".$wpdb->prefix.CPIS_FILE." as file, ".$wpdb->prefix.CPIS_IMAGE_FILE." as image_file WHERE file.id=image_file.id_file AND image_file.id_file=%d",
                $id
            )
        );
        
        if( is_null( $file ) ) exit;
        $products[] = $file;    
        $total += $file->price;
    }
    
    $total = round( $total, 2 );
        
    if ( $payment_amount < $total && abs( $payment_amount - $total ) > 0.2 ) exit;
    
    foreach( $products as $product ){
        if( register_purchase( $product->id, $purchase_id, $payer_email, $payment_amount, $paypal_data,  '' ) ) { 
            $wpdb->query( $wpdb->prepare( "UPDATE ".$wpdb->prefix.CPIS_IMAGE." SET purchases=purchases+1 WHERE id=%d", $product->id_image ) );
        }    
    }    
    
	$notification_from_email = $options[ 'notification' ][ 'from' ];
	$notification_to_email = $options[ 'notification' ][ 'to' ];
	
	$notification_to_payer_subject = $options[ 'notification' ][ 'subject_payer' ];
	$notification_to_payer_message  = $options[ 'notification' ][ 'notification_payer' ];
	
	$notification_to_seller_subject = $options[ 'notification' ][ 'subject_seller' ];
	$notification_to_seller_message = $options[ 'notification' ][ 'notification_seller' ];
	
    $cpis_d_url = _cpis_create_pages( 'cpis-download-page', 'Download Page' );
    $cpis_d_url .= ( ( strpos( $cpis_d_url, '?' ) === false ) ? "?" : "&" )."cpis-action=download";
    
	$information_payer = "Product: {$item_name}\n".
						 "Amount: {$payment_amount} {$payment_currency}\n".
						 "Download Link: ".$cpis_d_url."&purchase_id={$_GET[ 'purchase_id' ]}\n";
						 
	$information_seller = "Product: {$item_name}\n".
						  "Amount: {$payment_amount} {$payment_currency}\n".
						  "Buyer Email: {$payer_email}\n".
						  "Download Link: ".$cpis_d_url."&purchase_id={$_GET['purchase_id']}\n";
						 
	$notification_to_payer_message  = str_replace("%INFORMATION%", $information_payer, $notification_to_payer_message);
	$notification_to_seller_message = str_replace("%INFORMATION%", $information_seller, $notification_to_seller_message);
	
	// Send email to payer
	wp_mail($payer_email, $notification_to_payer_subject, $notification_to_payer_message,
            "From: \"$notification_from_email\" <$notification_from_email>\r\n".
            "Content-Type: text/plain; charset=utf-8\n".
            "X-Mailer: PHP/" . phpversion());

    // Send email to seller
    if( !empty( $notification_to_email ) ){
        wp_mail($notification_to_email , $notification_to_seller_subject, $notification_to_seller_message,
                "From: \"$notification_from_email\" <$notification_from_email>\r\n".
                "Content-Type: text/plain; charset=utf-8\n".
                "X-Mailer: PHP/" . phpversion());
    }            

   echo 'OK';
   exit();
?>