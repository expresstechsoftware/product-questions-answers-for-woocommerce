jQuery(document).ready(function(){	 
 	
 	jQuery('#ets-submit').click(function(e){
	    e.preventDefault();  
	    let submit = jQuery ("#ets-qus-form").serialize();  
	    jQuery('#ets-submit').prop('disabled', true);
        jQuery.ajax({ 
			url: etsWooQaParams.admin_ajax,
			type: 'POST', 
			dataType: "json",
			data: 'action=ets_post_qusetion_answer&' + submit + "&add_qustion_nonce=" + etsWooQaParams.add_qustion_nonce,
			success: function(res) {   
				jQuery('#ets-submit').prop('disabled', false);
				jQuery("#ques-text-ar").val("");
				if( res.status == 1 ) {
					jQuery(".ets-display-message").html(res.message);	
				} else {
					jQuery(".ets-dis-message-error").html(res.message);
				}
				jQuery("#ques-text-ar").on("click", function(){ 
				  jQuery(".ets-display-message").text(""); 
				  jQuery(".ets-dis-message-error").text("");
				});
				jQuery("#ets-submit").on("click", function(){ 
				  jQuery(".ets-display-message").text("");
				  jQuery(".ets-dis-message-error").text("");
				});    
            }
        }); 
	});

	jQuery("#ques-text-ar").on("click", function(){ 
		jQuery(".ets-display-message").text(""); 
		jQuery(".ets-dis-message-error").text("");
	});
	jQuery("#ets-submit").on("click", function(){ 
		jQuery(".ets-display-message").text("");
		jQuery(".ets-dis-message-error").text("");
	});

	function handleLoadMoreClick(buttonId, tableId, accordionId,QaLength,QaOffset) {
	    jQuery(buttonId).click(function(e) {
	        e.preventDefault();
	        let submit = jQuery("#ets-qus-form").serialize(); 
	        let qalength = jQuery(QaLength).find('p').text();
	        let offset = jQuery(QaOffset).find('p').text(); 
	        if (typeof offset == 'undefined') 
	            offset = 0; 
	        jQuery.ajax({ 
	            url: etsWooQaParams.admin_ajax,
	            type: 'GET',  
	            dataType: "JSON",
	            data: 'action=ets_product_qa_load_more&' + submit + '&offset=' + offset +'&load_qa_nonce=' + etsWooQaParams.load_qa_nonce,
	            success: function(res) {
	             
	                offset = res.offset;  
	                jQuery(tableId).append(res.htmlData);  
	                jQuery(accordionId).append(res.htmlData);
	                jQuery(QaOffset).find('p').html(offset).hide();
	                if(offset >= qalength ){
	              
	                    jQuery(buttonId).hide();
	                } 
	            }
	        }); 
	    });
	}

	handleLoadMoreClick('#ets-load-more-1', '.ets-list-table-1', '.ets-accordion-list-qa-1','.ets_pro_qa_length_1','#ets_product_qa_length_1');

	handleLoadMoreClick('#ets-load-more-2', '.ets-list-table-2', '.ets-accordion-list-qa-2','.ets_pro_qa_length_2','#ets_product_qa_length_2');
 
}); 

jQuery(document).on('click','.ets-accordion',function(){
    jQuery(".ets-panel").slideUp();
    jQuery(this).next().slideToggle();
})
