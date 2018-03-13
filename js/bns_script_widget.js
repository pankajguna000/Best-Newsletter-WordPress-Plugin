jQuery(document).ready(function(){
jQuery("#best_news_save").click(function(){
var best_name = jQuery('#best_news_name').val();
var best_email = jQuery('#best_news_email').val();

if(best_email != ""){
	var filter = /^[\w\-\.\+]+\@[a-zA-Z0-9\.\-]+\.[a-zA-z0-9]{2,4}$/;
if (filter.test(best_email)) {
 var data = {
        'action': 'bns_widget_sub',
		'best_name': best_name,
        'best_email': best_email,
        'email_widget_option_nonce': email_widget_option.email_widget_option_nonce
    };
	jQuery.post(email_widget_option.ajaxurl, data, function(response) {
		
		if(jQuery.trim(response)){
		jQuery(".bns-widget-container").fadeOut();
		 jQuery("#bns_widget_succ_msg").fadeIn().css('display', 'block');
    window.setTimeout(function() {
jQuery("#best_news_email").val("");
  jQuery("#best_news_name").val("");  
    jQuery("#bns_widget_succ_msg").fadeOut();
	
		jQuery(".bns-widget-container").fadeIn();
    }, 3000);
		}
	
	
		
    });
}
else{
	jQuery(".bns_error_widget").fadeIn().css('display', 'block');
	 window.setTimeout(function() {
        jQuery(".bns_error_widget").fadeOut();
		 }, 3000);
}
}else{
	jQuery(".bns_error_widget").fadeIn().css('display', 'block');
	 window.setTimeout(function() {
        jQuery(".bns_error_widget").fadeOut();
		 }, 3000);
}
});
});