jQuery(document).ready(function() {
	
    jQuery('.mg-group').hide();
    var param = get_url_param();
    if (param == "pid") {
        jQuery("#email_newletter_edit").css("display", "block");
    } else if (param == "temp_id") {
        to_show_tabs("send_email_choose_list", "en_temp");
        //jQuery("#send_email_choose_list").css("display","block");
    } else if (param == "lid") {
        //jQuery("#add_email_new_list_panel").css("display","block");
        to_show_tabs("add_email_new_list_panel", "en_listing");
    } else if (param == "list_id") {
       // jQuery("#add_email_new_list_panel").css("display", "none");
        to_show_tabs("email_view_all_list", "en_listing");
        jQuery('#import_div').css("display", "block");
        jQuery("#import_contact").css("display", "block");
    } else if (param == "temp_stats") {

        //to_show_tabs("email_news_stats"view_email_list();,"en_temp");
        jQuery("#email_news_stats").css("display", "block");
    } 
	else if(param == "en_temp_add"){
	    to_show_tabs("email_newletter_new", "en_temp");
	}
	else if(param == "en_temp_tb"){
		to_show_tabs("email_new_design_panel", "en_temp");
	}
	else if(param == "en_list_tb"){
		to_show_tabs("add_email_new_list_view_panel", "en_listing");
	}
	else if(param == "en_smtp_tb"){
		to_show_tabs("email_new_smtp_setting", "en_smtp");
	}
	else if(param == "en_smtp_test"){
		to_show_tabs("email_new_send_test_mail", "en_smtp");
	}
	else if(param == "en_list_add"){
		to_show_tabs("email_news_add_new_list", "en_listing");
	}
	else {
        to_show_tabs("email_new_design_panel", "en_temp");
     }

    function get_url_param() {
		 var url = window.location.href.slice(window.location.href.indexOf('?') + 1).split('&');
        for (var i = 0; i < url.length; i++) {
            var urlparam = url[i].split('=');
            if (urlparam[0] == "pid") {
                return "pid";
            }
            if (urlparam[0] == "temp_id") {
                return "temp_id";
            }
            if (urlparam[0] == "list_id") {
                return "list_id";
            }
            if (urlparam[0] == "lid") {
                return "lid";
            }
            if (urlparam[0] == "temp_stats") {
                return "temp_stats";
            }
			if(urlparam[0] == "tab"){
				return urlparam[1];
			}

        }

    }
    jQuery('#en_temp').click(function() {
    window.location.search = "?page=bns_email_newsletter&tab=en_temp_tb";
    window.location.href.replace(window.location.search);
    });

    jQuery('#en_listing').click(function() {  
    window.location.search = "?page=bns_email_newsletter&tab=en_list_tb";
    window.location.href.replace(window.location.search);
   
    });

    jQuery('#en_smtp').click(function() {
    window.location.search = "?page=bns_email_newsletter&tab=en_smtp_tb";
    window.location.href.replace(window.location.search);
   });


    jQuery("#post_email_post_cnt").keyup(function() {
        var numericExpression = /^[0-9]+$/;
        var elem = jQuery("#post_email_post_cnt");
        if (jQuery.isNumeric(elem.val())) {
            return true;
        } else {
            email_news_error_popup("#post_email_post_cnt_err", 3000);
            return false;
        }
    });



});

function to_show_tabs(ele, tab) {
    jQuery('.mg-group').hide();
    jQuery('.nav-tab').removeClass('nav-tab-active');
    jQuery("#" + tab).addClass('nav-tab-active');
	 jQuery('#' + ele).css("display", "block");

}

function email_news_error_popup(mg_id, mg_time) {
    var success = jQuery(mg_id);
    success.fadeIn().css('display', 'inline-table');
    window.setTimeout(function() {
        success.fadeOut();
    }, mg_time);
}

function new_email_add() {
	window.location.search = "?page=bns_email_newsletter&tab=en_temp_add";
    window.location.href.replace(window.location.search);
    
	}

function view_email_list() {
    window.location.search = "?page=bns_email_newsletter&tab=en_temp_tb";
    window.location.href.replace(window.location.search);
    
	}
	
	function validate_en_temp(e){
		if(jQuery("#email_news_subject").val() == ""){ 
			//e.preventDefault();
			email_news_error_popup("#email_news_subject_err", 3000);
            return false;
		}
		/*if(jQuery("#emailnew_editor").text() == ""){
			//e.preventDefault();
			email_news_error_popup("#email_news_text_err", 4000);
            return false;
		}*/
		//if(jQuery("#email_news_subject").val() != "" && jQuery("#emailnew_editor").text() != "" ){
		if(jQuery("#email_news_subject").val() != ""){
		return true;
		}
		
	}


var checkedValues = [];

function prev_email_temp(ele) {
    var id = jQuery(ele).attr("data-tempid");
    var chk = jQuery(ele).attr("data-send");
    if (chk) {
        checkedValues = jQuery('input[name=selected_list]:checkbox:checked').map(function() {
            return this.value;
        }).get();
        
			var f_name= jQuery("#email_new_fname").val();
			var f_sub = jQuery("#email_new_sub").val();
			var data = {
        'action': 'bns_update_name_sub',
        'temp_id': id,
		'f_name' : f_name,
		'f_sub'  : f_sub,
        'email_news_option_nonce': email_news_option.email_news_option_nonce
    };
		jQuery.post(ajaxurl, data, function(response) {
				if(jQuery.trim(response) == "fname"){
				 email_news_error_popup("#email_news_fname_err", 3000);
			}else if(jQuery.trim(response) == "sub"){
				jQuery("#email_news_sub_err").text("Please enter email subject");
				email_news_error_popup("#email_news_sub_err", 3000);
			}else if(jQuery.trim(response) == "both"){
				jQuery("#email_news_sub_err").text("Please enter details");
				email_news_error_popup("#email_news_sub_err", 3000);
			}else{
				if (checkedValues != "") {
			actual_prev_email_tmp(id, "send");
			 } else {
            email_news_error_popup("#send_list_error", 3000);
        }
				}
			
        });
		
        // } else {
            // email_news_error_popup("#send_list_error", 3000);
        // }
    } else {
        actual_prev_email_tmp(id, "view");
    }



}

function actual_prev_email_tmp(id, val) {
    var data = {
        'action': 'bns_preview',
        'temp_id': id,
        'email_news_option_nonce': email_news_option.email_news_option_nonce
    };
    jQuery.post(ajaxurl, data, function(response) {
       
  jQuery("#tempPreview").html(response);
  jQuery("#tempPreview").find("p").filter(function () {
			if(jQuery.trim(jQuery(this).html()) === '<br>')
				return jQuery.trim(jQuery(this).html()) === '<br>';
  }).remove();
        jQuery("#tempPreview").find('.mg-body').addClass('prev-mg-body');
        jQuery("#tempPreview").find('.bottom-button').remove();
        jQuery("#tempPreview").attr("data-mg-tempid", id);

        if (val == "view") {
            jQuery('#temp_prev_cont').css("display", "none");
        }
        jQuery('#send-confirm').fadeIn('slow', function() {
            jQuery('.post-email-inner-prev').slideDown('600', function() {});
        });

    });
}

var close_temp_prev = function(elem) {
    jQuery(elem).parents('.post-email-inner-prev').slideUp('600', function() {
        jQuery(this).parent().fadeOut('slow');
        jQuery("#tempPreview").html("");
        jQuery("#tempPreview").removeAttr("data-mg-tempid");
        jQuery('#frameHolder').css('display', 'block');
        jQuery('#sendDetails').addClass('fg-hidden');
        jQuery('#allWrapper').css('height', 'auto');
    });
};

function edit_email_news(ele) {
    var id = jQuery(ele).attr("data-tempid");
    var path = window.location.href.split("?");
    var path1 = path[0] + "?page=bns_email_newsletter";
    jQuery('.mg-group').hide();
    jQuery('.nav-tab').removeClass('nav-tab-active');
    jQuery(this).addClass('nav-tab-active');
    jQuery('#email_newletter_edit').css("display", "block");
    window.location.assign(path1 + '&pid=' + id);

}

function email_add_new_list() {
	window.location.search = "?page=bns_email_newsletter&tab=en_list_add";
    window.location.href.replace(window.location.search);
   
}

function email_all_list_view(ele) {
    jQuery('.mg-group').hide();
    jQuery('.nav-tab').removeClass('nav-tab-active');
    jQuery(this).addClass('nav-tab-active');
    if (jQuery(ele).attr("id") == "email_import" || jQuery(ele).attr("id") == "email_import1") {
        var path = window.location.href.split("?");
        var path1 = path[0] + "?page=bns_email_newsletter";
        window.location.assign(path1 + '&list_id=' + jQuery(ele).attr("data-tempid"));
        jQuery("#email_view_all_list").css("display", "block");
        jQuery("#add_new_list").css("display", "none");
        jQuery('#import_div').css("display", "block");
        jQuery("#import_contact").css("display", "block");

    } else {
        jQuery('#add_email_new_list_panel').css("display", "block");
    }
}

function email_new_add_list() {
    var list_name = jQuery("#add_list_name").val();
	    if (list_name != "") {
	        var data = {
            'action': 'bns_add_list',
            'list_name': list_name,
            'email_news_option_nonce': email_news_option.email_news_option_nonce
        };

        jQuery.post(ajaxurl, data, function(response) {
            var success = jQuery('#email_news_list_sect');
            var maskWidth = jQuery(window).width();
            var maskHeight = jQuery(window).height();
            var dialogLeft = (maskWidth / 2) - (success.width()) / 2;
            var dialogTop = (maskHeight / 2) - (success.width()) / 2;
            success.css({
                top: dialogTop,
                left: dialogLeft,
                position: 'fixed'
            });
            email_news_error_popup(success, 2000);
            window.location.search = "?page=bns_email_newsletter&tab=en_list_tb";
            window.location.href.replace(window.location.search);
            //to_show_tabs("add_email_new_list_panel", "en_listing");
        });
    } else {

        email_news_error_popup("#list_name_error", 2000);
    }



}
var importExist = false;

function import_contacts() {
    if (importExist) {
        alert("Please Wait another list is importing.");
    } else {
        var uploadfile = document.getElementById('up_file');
        var import_emails_textarea = document.getElementById("import_emails_textarea").value;
        var list_id_to_import = document.getElementById("list_id_hidden").value;
        var upload_file = uploadfile.files[0];
        if (upload_file != 'null' && upload_file != null && upload_file != '') {
            if (typeof upload_file !== "undefined") {

                var file_extention1 = (/[.]/.exec(upload_file.name)) ? /[^.]+jQuery/.exec(upload_file.name) : 'undefined';

                var file_extention = (/[.]/.exec(upload_file.name)) ? /[^.]+$/.exec(upload_file.name) : 'undefined';

            } else {

                var file_extention = 'undefined';
            }
        } else {
            var file_extention = 'undefined';
        }
        if ((file_extention == 'csv') || (import_emails_textarea != '' && file_extention == 'undefined') || (import_emails_textarea != '' && file_extention == 'csv') && list_id_to_import != "") {
            var postfile = new FormData();
            postfile.append("up_file", upload_file);
            postfile.append("import_emails_textarea", import_emails_textarea);
            postfile.append("list_id_to_import", list_id_to_import);
            postfile.append('action', 'bns_import');
            postfile.append('email_news_option_nonce', email_news_option.email_news_option_nonce);
            //postfile.append('post_email_option_nonce', post_email_option.post_email_option_nonce);
            jQuery.ajax({
                url: ajaxurl,
                type: "POST",
                data: postfile,
                processData: false,
                contentType: false,
                beforeSend: function() {
                    importExist = true;
                    //jQuery('#show_to_block .fg-full-overlay').fadeIn('slow');
                },
                xhr: function() {
                    var xhr = new window.XMLHttpRequest();
                    xhr.upload.addEventListener("progress", function(evt) {
                        if (evt.lengthComputable) {}
                    }, false);
                    return xhr;
                },
                complete: function(res) {
                    importExist = false;
                       if ((res.responseText).trim() == 'list_updated') {

                        jQuery("#post_email_txt_import").text("List imported successfully");
                        jQuery("#post_email_txt_import").css({
                            "color": "#268429",
                            "background-color": "#dae8c8"
                        });
                        jQuery("#post_email_txt_import").fadeIn().css('display', 'inline-table');
                        window.setTimeout(function() {
                            jQuery("#post_email_txt_import").fadeOut();
                        }, 2000);


                    } else if ((res.responseText).trim() == 'error') {
                        jQuery("#post_email_txt_import").text("Please enter valid format of contacts");
                        jQuery("#post_email_txt_import").css({
                            "color": "#da0000",
                            "background-color": "#ffcfd1"
                        });
                        jQuery("#post_email_txt_import").fadeIn().css('display', 'inline-table');
                        window.setTimeout(function() {
                            jQuery("#post_email_txt_import").fadeOut();
                        }, 2000);


                    }
                    jQuery('#up_file').val('');
                    jQuery('#import_file_text').val('');
                    jQuery('#import_emails_textarea').val('');
                }
            });
        } else {
            jQuery("#post_email_import_csv").fadeIn().css('display', 'inline-table');
            window.setTimeout(function() {
                jQuery("#post_email_import_csv").fadeOut();
            }, 2000);
            jQuery('#up_file').val('');

        }
    }
}

var list_arr = [];

function email_new_template_to_send(ele) {
    var id = jQuery(ele).attr("data-tempid");
    var path = window.location.href.split("?");
    var path1 = path[0] + "?page=bns_email_newsletter";
    jQuery('.mg-group').hide();
    jQuery('.nav-tab').removeClass('nav-tab-active');
    jQuery(this).addClass('nav-tab-active');
    jQuery('#send_email_choose_list').css("display", "block");
    window.location.assign(path1 + '&temp_id=' + id);

}

function selected_list_to_send() {
    var checkedValues = jQuery('input[name=selected_list]:checkbox:checked').map(function() {
        return this.value;
    }).get();
}


function confirm_send(ele) {
    var temp_id = jQuery(ele).parent().find("#tempPreview").attr("data-mg-tempid");
	 if (temp_id != "") {
        var list_name = "";
        var total_cnt = 0;
        var data = {
            'action': 'bns_confirm_send',
            'list_id': checkedValues,
            'temp_id': temp_id,
            'email_news_option_nonce': email_news_option.email_news_option_nonce
        };

        jQuery.post(ajaxurl, data, function(response) {
             var data1 = jQuery.parseJSON(response);
            jQuery("#ipu-subject").text(data1.sub);
            jQuery.each(data1.list_info, function(index, element) {

                if (((data1.list_info).length - 1) == index)
                    list_name += element.list_name;
                else
                    list_name += element.list_name + ",";
                });
			
            jQuery("#ipu-to").text(list_name);
            jQuery("#ipu-tot").text(data1.total);

        });
        var tempHeight = document.getElementById('allWrapper').clientHeight;


        document.getElementById('allWrapper').style.height = '150px';
        jQuery('#frameHolder').fadeOut(400);
        jQuery('#sendDetails').css('opacity', 0).removeClass('fg-hidden');
        tempHeight = document.getElementById('sendDetails').clientHeight;

        jQuery('#allWrapper').animate({
            'height': '150px'
        }, 500, function() {
            jQuery('#sendDetails').delay(100).animate({
                'opacity': 1
            }, 300);
        });
    } else {
        alert("please enter it once");
    }

}

function send_list_emails(ele) {
    var temp_id = jQuery(ele).parent().parent().children(':first-child').find("#tempPreview").attr("data-mg-tempid");
     var data = {
        'action': 'bns_send_final',
        'list_id': checkedValues,
        'temp_id': temp_id,
        'email_news_option_nonce': email_news_option.email_news_option_nonce
    };

    jQuery.ajax({
        type: 'POST',
        url: ajaxurl,
        data: data,
        beforeSend: function() {
            setInterval(function() {
                jQuery('.post-email-inner-prev.email-prev').html('<h3 class="ipu-heading" style="padding: 20px 10px;text-align: center;line-height: 30px;">We are sending your emails. You can Go to Stats Panel to access the sending details.</h3> <div style="text-align:center; margin:20px 0 10px;"><a href="?page=bns_email_newsletter&temp_stats='+temp_id+'&camp_id=default&temp_type=sent" class="button-primary mg-submit-btn">Go to Sending Stats</button></div>');
            }, 1000);
        },
        success: function(data) {
        }
    });

}

var bns_close_ipu = function(elem) {
    jQuery(elem).parents('.post-email-inner-prev').slideUp('600', function() {
        jQuery(this).parent().fadeOut('slow');
        jQuery('#frameHolder').css('display', 'block');
        jQuery('#sendDetails').addClass('fg-hidden');
        jQuery('#allWrapper').css('height', 'auto');
        jQuery('input:checkbox[name=selected_list]').attr('checked', false);
        checkedValues.length = 0;
    });
};

function view_contact_list(ele) {
    var id = jQuery(ele).attr("data-tempid");
    var url = window.location.href.slice(window.location.href.indexOf('?'));
    var url1 = window.location.href;
    var url2 = url1.replace(window.location.search, "?page=bns_email_newsletter&lid=" + id);
    window.location.href = (url2);
}

function email_new_stat_track(ele) {
    var id = jQuery(ele).attr("data-tempid");
    var url = window.location.href.slice(window.location.href.indexOf('?'));
    var url1 = window.location.href;
    var url2 = url1.replace(window.location.search, "?page=bns_email_newsletter&temp_stats=" + id);
    window.location.href = (url2);
}

function email_new_save_smtp() {

    var data = {
        'action': 'bns_save_smtp_db',
        'f_email': jQuery("#email_new_f_email").val(),
        'f_name': jQuery("#email_new_f_name").val(),
        'mail': jQuery("input[name='mailer_option']:checked").val(),
        'auth': jQuery("input[name='mailer_auth']:checked").val(),
        'encrypt': jQuery("input[name='mailer_encrypt']:checked").val(),
        'port': jQuery("#email_new_smtp_port").val(),
        'host': jQuery("#email_new_smtp_host").val(),
        'uname': jQuery("#email_news_uname").val(),
        'upass': jQuery("#email_news_pass").val(),
        'return_path': jQuery("input[name='return_path']:checked").val(),
        'email_news_option_nonce': email_news_option.email_news_option_nonce
    };

    jQuery.post(ajaxurl, data, function(response) {
        var success = jQuery('#email_new_smtp_setting_opt');
        var maskWidth = jQuery(window).width();
        var maskHeight = jQuery(window).height();
        var dialogLeft = (maskWidth / 2) - (success.width()) / 2;
        var dialogTop = (maskHeight / 2) - (success.width()) / 2;
        success.css({
            top: dialogTop,
            left: dialogLeft,
            position: 'fixed'
        });
        email_news_error_popup(success, 2000);
    });
}

function email_new_view_all_list(){
window.location.search = "?page=bns_email_newsletter&tab=en_list_tb";
            window.location.href.replace(window.location.search);
	}
	
	function en_view_smtp_setting(){
		window.location.search = "?page=bns_email_newsletter&tab=en_smtp_tb";
            window.location.href.replace(window.location.search);
	}
	
	function en_send_test_mail(){
		window.location.search = "?page=bns_email_newsletter&tab=en_smtp_test";
            window.location.href.replace(window.location.search);
	}
	
	function email_new_send_test(){
		  var email = jQuery("#email_new_to_email").val();
	      if(email != ""){
		    var success = jQuery('#email_new_smtp_test_loader');
        var maskWidth = jQuery(window).width();
        var maskHeight = jQuery(window).height();
        var dialogLeft = (maskWidth / 2) - (success.width()) / 2;
        var dialogTop = (maskHeight / 2) - (success.width()) / 2;
        success.css({
            top: dialogTop,
            left: dialogLeft,
            position: 'fixed'
        });
        email_news_error_popup(success, 2000);
		return true;
    	  }
		  else{
			email_news_error_popup("#email_new_smtp_test", 2000);  
			return false;
		  }
	}
	
	
	function en_call_stat(temp_id,type,chk=""){
	
		if(chk != "stats"){
	   var sel_opt = jQuery( "select#en_select_camp option:selected").val();
	   if(sel_opt == "" || typeof(sel_opt)  === "undefined") 
		   var sel_opt = "default";
		}
		else{
		var sel_opt	="default";
		}
		
	window.location.search = "?page=bns_email_newsletter&temp_stats="+temp_id+"&camp_id="+sel_opt+"&temp_type="+type;
    window.location.href.replace(window.location.search);
	}
	
	//en_change_eve_call_stat