jQuery(function(){
	(function($){
		// Methods definition
		window['cpis_remove'] = function(e){
            $(e).parents('.cpis-property-container').remove();
        };
		
		window['cpis_select_element'] = function(e, add_to, new_element_name){
			var v = e.options[e.selectedIndex].value,
				t = e.options[e.selectedIndex].text;
			if(v != 'none'){
				$('#'+add_to).append(
					'<div class="cpis-property-container"><input type="hidden" name="'+new_element_name+'[]" value="'+v+'" /><input type="button" onclick="cpis_remove(this);" class="button" value="'+t+' [x]"></div>'
				);
			}	
		};
		
		window['cpis_add_element'] = function(input_id, add_to, new_element_name){
			var n = $('#'+input_id),
				v = n.val();
			n.val('');	
			if( !/^\s*$/.test(v)){
				$('#'+add_to).append(
					'<div class="cpis-property-container"><input type="hidden" name="'+new_element_name+'[]" value="'+v+'" /><input type="button" onclick="cpis_remove(this);" class="button" value="'+v+' [x]"></div>'
				);
			}	
		};
		
		window['cpis_delete_purchase'] = function(id){
			if(confirm('Are you sure to delete the purchase record?')){
				var f = $('#purchase_form');
				f.append('<input type="hidden" name="purchase_id" value="'+id+'" />');
				f[0].submit();
			}	
		};
		
        window["cpis_new_file"] = function(){
            var nTr =   ' \
                            <tr> \
                                <td style="width:60%;"><input type="file" name="cpis_file_new[]" style="width:100%;" /></td> \
                                <td style="width:10%;"><input type="text" name="cpis_file_width_new[]" class="cpis-short-field" /></td>     \
                                <td style="width:10%;"><input type="text" name="cpis_file_height_new[]" class="cpis-short-field" /></td>    \
                                <td style="width:10%;"><input type="text" name="cpis_file_price_new[]" class="cpis-short-field" /></td>     \
                                <td style="width:10%;"> <input type="button" onclick="cpis_remove_file(this);" value="Remove" /></td> \
                            </tr> \
                        ';
            
            $('.cpis-file-list').append(nTr);
        };

        window["cpis_remove_file"] = function( e, id ){
            e = $(e);
            if(id){
                if( e.data( 'processing' ) == 1) return;
                e.data( 'processing', 1 );
                
                if( image_store && image_store.hurl ){
                    e.parents('tr').fadeTo( 'normal', 0.5 );
                    $.getJSON( 
                        image_store.hurl+'wp-admin/?cpis-action=remove-image&image='+id, 
                        function( data ){
                            if( data.error){ 
                                alert( data.error );
                                e.parents('tr').fadeTo( 'normal', 1 );
                                e.data( 'processing', 0 );
                            }else{
                                e.parents('tr').remove();
                            }    
                        }
                    );
                }
            }else{
                e.parents('tr').remove();
            }
        };        

        window ['cpis_insert_store'] = function(){
            if(send_to_editor) send_to_editor('[codepeople-image-store]');
		};
		
        window ['cpis_insert_product_window'] = function(){
        	var tags = ( image_store ) ? image_store.tags : '',
				cont = $(tags.replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&quot;/g, '"'));
			
			cont.dialog({
				dialogClass: 'wp-dialog',
				modal: true,
				closeOnEscape: true,
                close: function(){
                    $(this).remove();
                },
				buttons: [
					{text: 'OK', click: function() {
						var id  = $('#product_id').val(),
                            l   = $('#layout').val(),
							sc  = '[codepeople-image-store-product id="'+id+'" layout="'+l+'"]';

						if(send_to_editor) send_to_editor(sc);
						$(this).dialog("close"); 
					}}
				]
			});
		};
    })(jQuery)
})