<?php
/*
Plugin Name: Best Newsletter 
Plugin URI: http://www.formget.com/mailget/
Description: The Best Email Newsletter Plugin for WordPress. Allows Email Newsletter Creation, Sending, Opens and Click Tracking.
Version: 1.0.3
Author: MailGet
Author URI: http://www.formget.com/mailget/
*/

global $wpdb, $phpmailer;

require_once("bns_template.php");
require_once("bns_list.php");
require_once("bns_list_contact.php");
require_once("bns_camp_stats.php");
function bns_ajax_action()
{
    add_action('wp_ajax_bns_import', 'bns_import');
    add_action('wp_ajax_bns_preview', 'bns_preview');
    add_action('wp_ajax_bns_add_list', 'bns_add_list');
    add_action('wp_ajax_bns_confirm_send', 'bns_confirm_send');
    add_action('wp_ajax_bns_send_final', 'bns_send_final');
    add_action('wp_ajax_bns_save_smtp_db', 'bns_save_smtp_db');
    add_action('wp_ajax_bns_update_name_sub', 'bns_update_name_sub');
}

add_action('admin_init', 'bns_ajax_action');
add_action('phpmailer_init', 'phpmailer_init_smtp');
//Add filters to replace the mail from name and emailaddress
add_filter('wp_mail_from', 'bns_smtp_mail_from');
add_filter('wp_mail_from_name', 'bns_mail_from_name');

function best_news_widget_script() {
    wp_enqueue_style('email_news_widget_stylesheet', plugins_url('css/bns_widget.css', __FILE__));	
	wp_enqueue_script("email_script_widget", plugins_url('js/bns_script_widget.js', __FILE__), array('jquery'));
    wp_localize_script('email_script_widget', 'email_widget_option', array('ajaxurl' => admin_url('admin-ajax.php'), 'email_widget_option_nonce' => wp_create_nonce('email_widget_option_nonce')));
}

add_action('wp_enqueue_scripts', 'best_news_widget_script');

if (!function_exists('phpmailer_init_smtp')):
    function phpmailer_init_smtp($phpmailer)
    {
        $options = get_option("email_news_smtp_options");
        // Check that mailer is not blank, and if mailer=smtp, host is not blank
        if (!isset($options['mailer']) || (isset($options["mailer"]) && $options['mailer'] == 'smtp' && !isset($options['smtp_host']))) {
            return;
        }
        
        // Set the mailer type as per config above, this overrides the already called isMail method
        $phpmailer->Mailer = $options['mailer'];
        
        // Set the Sender (return-path) if required
        if (isset($options["mail_set_return_path"]) && $options['mail_set_return_path'])
            $phpmailer->Sender = $phpmailer->From;
        
        // Set the SMTPSecure value, if set to none, leave this blank
        $phpmailer->SMTPSecure = $options['smtp_encryption'] == 'none' ? '' : $options['smtp_encryption'];
        
        // If we're sending via SMTP, set the host
        if ($options['mailer'] == "smtp") {
            
            // Set the SMTPSecure value, if set to none, leave this blank
            $phpmailer->SMTPSecure = $options['smtp_encryption'] == 'none' ? '' : $options['smtp_encryption'];
            
            // Set the other options
            $phpmailer->Host = $options['smtp_host'];
            $phpmailer->Port = $options['smtp_port'];
            
            // If we're using smtp auth, set the username & password
            if ($options['smtp_authentication'] == "true") {
                $phpmailer->SMTPAuth = true;
                $phpmailer->Username = $options['smtp_username'];
                $phpmailer->Password = $options['smtp_password'];
            }
        }
        $phpmailer = apply_filters('wp_email_smtp_custom_options', $phpmailer);
    }
endif;

function bns_smtp_mail_from($orig)
{
    // Get the site domain and get rid of www.
    $options  = get_option("email_news_smtp_options");
    $sitename = strtolower($_SERVER['SERVER_NAME']);
    if (substr($sitename, 0, 4) == 'www.') {
        $sitename = substr($sitename, 4);
    }
    
    $default_from = 'wordpress@' . $sitename;
    
    // If the from email is not the default, return it unchanged
    if ($orig != $default_from) {
        return $orig;
    }
    if (isset($options['from_email']) && is_email($options['from_email'], false)) {
        return $options['from_email'];
    }
    
    // If in doubt, return the original value
    return $orig;
}

function bns_mail_from_name($orig)
{
    $options = get_option("email_news_smtp_options");
    // Only filter if the from name is the default
    if ($orig == 'WordPress') {
        if (isset($options['from_name']) && $options['from_name'] != "" && is_string($options['from_name'])) {
            return $options['from_name'];
        }
    }
    
    // If in doubt, return the original value
    return $orig;
}

register_activation_hook(__FILE__, 'bns_activation_tbl');

function bns_activation_tbl()
{
    global $wpdb;
    $table_prefix = $wpdb->prefix;
    define('TBL_PREFIX', $table_prefix);
    
    $email_list     = TBL_PREFIX . "bns_email_news_list";
    $tbl_email_list = $wpdb->get_var("show tables like '$email_list'");
    if (!isset($tbl_email_list) && $tbl_email_list != $email_list) {
        $tbl_structure1 = "CREATE TABLE $email_list (
   					   list_id INT PRIMARY KEY AUTO_INCREMENT, 
   					   list_name varchar(255) NOT NULL, 
   					   contacts longtext NOT NULL,
   					   count int(11) NOT NULL,
   					   date datetime DEFAULT NULL
   					   );";
        $wpdb->query($tbl_structure1);
    }
    $email_list_extra     = TBL_PREFIX . "bns_email_news_list_extra";
    $tbl_email_list_extra = $wpdb->get_var("show tables like '$email_list_extra'");
    if (!isset($tbl_email_list_extra) && $tbl_email_list_extra != $email_list_extra) {
        $tbl_structure2 = "CREATE TABLE $email_list_extra (
   id INT PRIMARY KEY AUTO_INCREMENT,
   list_id int(11) NOT NULL,
   list_name varchar(255) NOT NULL, 
   contacts longtext NOT NULL,
   slot_id int(11) NOT NULL,
   count int(11) NOT NULL,
   date datetime DEFAULT NULL
   );";
        $wpdb->query($tbl_structure2);
    }
    $templ_track   = TBL_PREFIX . "bns_email_news_templ_track";
    $tbl_email_trk = $wpdb->get_var("show tables like '$templ_track'");
    if (!isset($tbl_email_trk) && $tbl_email_trk != $templ_track) {
        $tbl_templ_track = "CREATE TABLE $templ_track (
   id INT PRIMARY KEY AUTO_INCREMENT, 
   temp_id int(11) NOT NULL,
   sender_id varchar(255) NOT NULL,
   link_enc_id varchar(255) NOT NULL,
   count int(11) NOT NULL,
   contacts longtext NOT NULL,
   date datetime DEFAULT NULL					   
   );";
        $wpdb->query($tbl_templ_track);
    }
    
    $templ        = TBL_PREFIX . "bns_email_news_templ";
    $tbl_templ_tb = $wpdb->get_var("show tables like '$templ'");
    if (!isset($tbl_templ_tb) && $tbl_templ_tb != $templ) {
        $tbl_templ = "CREATE TABLE $templ (
   id INT PRIMARY KEY AUTO_INCREMENT, 
   temp_id int(11) NOT NULL,
   sender_id varchar(255) NOT NULL,
   links longtext NOT NULL,
  date datetime DEFAULT NULL					   
   );";
        $wpdb->query($tbl_templ);
    }
    $all_track = TBL_PREFIX . "bns_email_new_temp_all_track";
    $tbl_all   = $wpdb->get_var("show tables like '$all_track'");
    if (!isset($tbl_all) && $tbl_all != $all_track) {
        $tbl_all_track = "CREATE TABLE $all_track (
   id INT PRIMARY KEY AUTO_INCREMENT, 
   temp_id int(11) NOT NULL,
   sent int(11) NOT NULL,
	  click int(11) NOT NULL,					   
   unsub int(11) NOT NULL,
   open int(11) NOT NULL					  			   
   );";
        $wpdb->query($tbl_all_track);
    }
    $new_send        = TBL_PREFIX . "bns_email_new_sending";
    $tbl_new_send_tb = $wpdb->get_var("show tables like '$new_send'");
    if (!isset($tbl_new_send_tb) && $tbl_new_send_tb != $new_send) {
        $tbl_new_send = "CREATE TABLE $new_send (
   send_id INT PRIMARY KEY AUTO_INCREMENT, 
   temp_id int(11) NOT NULL,
   sender_id varchar(255) NOT NULL,
	  ip_address varchar(255) NOT NULL,					   
   contacts longtext NOT NULL,
   count int(11) NOT NULL,
   date datetime DEFAULT NULL					   
   );";
        $wpdb->query($tbl_new_send);
    }
    
    $unsub  = TBL_PREFIX . "bns_email_news_unsubscribe_track";
    $tbl_un = $wpdb->get_var("show tables like '$unsub'");
    if (!isset($tbl_un) && $tbl_un != $unsub) {
        $tbl_unsub = "CREATE TABLE $unsub (
   id INT PRIMARY KEY AUTO_INCREMENT, 
   temp_id int(11) NOT NULL,
   sender_id varchar(255) NOT NULL, 
   contacts longtext NOT NULL,
   date datetime DEFAULT NULL					   
   );";
        $wpdb->query($tbl_unsub);
    }
    
    $send_slot    = TBL_PREFIX . "bns_email_news_sending_slots";
    $tbl_send_slt = $wpdb->get_var("show tables like '$send_slot'");
    if (!isset($tbl_send_slt) && $tbl_send_slt != $send_slot) {
        $tbl_send_slot = "CREATE TABLE $send_slot (
   s_id INT PRIMARY KEY AUTO_INCREMENT,
   send_id int(11) NOT NULL, 
   temp_id int(11) NOT NULL,
   sender_id varchar(255) NOT NULL,
	  contacts longtext NOT NULL,					   
   count int(11) NOT NULL,
   s_index int(11) NOT NULL,
   status int(11) NOT NULL,
   date datetime DEFAULT NULL					   
   );";
        $wpdb->query($tbl_send_slot);
    }
    
    $open        = TBL_PREFIX . "bns_email_news_open_track";
    $tbl_open_tb = $wpdb->get_var("show tables like '$open'");
    if (!isset($tbl_open_tb) && $tbl_open_tb != $open) {
        $tbl_open = "CREATE TABLE $open (
   id INT PRIMARY KEY AUTO_INCREMENT,
   temp_id int(11) NOT NULL,
   sender_id varchar(255) NOT NULL, 
   contacts longtext NOT NULL,
   date datetime DEFAULT NULL					   
   );";
        $wpdb->query($tbl_open);
    }
    
    $temp_link    = TBL_PREFIX . "bns_email_new_temp_link";
    $tbl_tmp_link = $wpdb->get_var("show tables like '$temp_link'");
    if (!isset($tbl_tmp_link) && $tbl_tmp_link != $temp_link) {
        $temp_link_tbl = "CREATE TABLE $temp_link (
   id INT PRIMARY KEY AUTO_INCREMENT,
   temp_id int(11) NOT NULL,
   temp_link longtext NOT NULL					   
   );";
        $wpdb->query($temp_link_tbl);
    }
    $send_extra     = TBL_PREFIX . "bns_email_new_sending_extra";
    $tbl_send_extra = $wpdb->get_var("show tables like '$send_extra'");
    if (!isset($tbl_send_extra) && $tbl_send_extra != $send_extra) {
        $send_extra_tbl = "CREATE TABLE $send_extra (
   id INT PRIMARY KEY AUTO_INCREMENT, 
   s_id int(11) NOT NULL,
   temp_id int(11) NOT NULL,
   sender_id varchar(255) NOT NULL, 
   ip_address varchar(255) NOT NULL,
   contacts longtext NOT NULL,
   count int(11) NOT NULL,
   slot_id int(11) NOT NULL,
   date datetime DEFAULT NULL)";
        $wpdb->query($send_extra_tbl);
    }
}

/**
 * css loaded for dashboard page
 */

if (is_admin() && isset($_GET['page']) && (sanitize_text_field($_GET['page']) == 'bns_email_newsletter')) {
    
    function bns_add_style()
    {
        wp_enqueue_style('bns_admin_stylesheet', plugins_url('css/bns_style.css', __FILE__));
    }
    
    add_action("admin_enqueue_scripts", "bns_add_style");
    
    function bns_embeded_script()
    {
        wp_enqueue_script('bns_email_script', plugins_url('js/bns_script.js', __FILE__), array(
            'jquery'
        ));
        wp_localize_script('bns_email_script', 'email_news_option', array(
            'ajaxurl' => admin_url('admin-ajax.php'),
            'email_news_option_nonce' => wp_create_nonce('email_news_option_nonce')
        ));
    }
    
    add_action('init', 'bns_embeded_script');
}


add_action('admin_menu', 'bns_menu_page');

function bns_menu_page()
{
    add_menu_page('wpen', 'Best Newsletter', 'manage_options', 'bns_email_newsletter', 'bns_setting_page', plugins_url('image/post_email_favicon.png', __FILE__));
}


function bns_footer_call($imageBaseUrl)
{
    $unsubscribe_txt_dark = 'If you are no longer interested, you can <a id="mg-unsub" href="' . $imageBaseUrl . '?en_type=email_new_unsubs&temp_id=mailget_temp_id_replace&email_id=mailget_email_id_replace&s_id=mailget_s_id_replace&type=replace_drip_type" class="unsubscribelink" style="font-family: Helvetica Neue, Helvetica, Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 12px; color: #444040; text-decoration: underline; margin: 0; padding: 0;"> unsubscribe instantly</a>';
    
    $mailget_link_html_dark = '<p style="font-family: Helvetica Neue, Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 12px; font-weight: bold; color: #444040; line-height: 1.7; text-transform: uppercase; margin: 0; padding: 0;"></p>';
    
    
    $footer = '<div class="mg-append-footer" style="font-family: Helvetica Neue, Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 16px; font-weight: normal; color: #444040; line-height: 1.7; width: 100%; clear: both; margin: 0; padding: 20px 0;"><table width="100%" style="font-family: Helvetica Neue, Helvetica, Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 16px; margin: 0; padding: 0;"><tr style="font-family: Helvetica Neue, Helvetica, Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 16px; margin: 0; padding: 0;"><td class="aligncenter footer-td" style="font-family: Helvetica Neue, Helvetica, Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 16px; vertical-align: top; color: #444040; text-align: center; margin: 0 auto; padding: 0 20px;" align="center" valign="top"><p class="unsubscribe" style="font-family: Helvetica Neue, Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 12px; font-weight: normal; color: #444040; line-height: 1.7; margin: 0; padding: 0;">' . $unsubscribe_txt_dark . '<img src="' . $imageBaseUrl . '?en_type=email_new_open&temp_id=mailget_temp_id_replace&email_id=mailget_email_id_replace&s_id=mailget_s_id_replace&type=open" width="1" height="1" border="0" style="font-family: Helvetica Neue, Helvetica, Helvetica, Arial, sans-serif; box-sizing: border-box; font-size: 16px; max-width: 100%; display: block; margin: 0; padding: 0;"/></p>' . $mailget_link_html_dark . '</td></tr></table></div>';
    
    return $footer;
}


function bns_setting_page()
{
?>
<div class="mg-main-container123">
   <div id="mg_header78">
      <div class="mg-logo444">
         <h2>Create and send Email Newsletter to Imported List</h2>
      </div>
	  <div>
	   <a href="https://www.formget.com/mailget-bolt/?utm_source=wpplugin&amp;utm_campaign=wpplugin&amp;utm_medium=wpplugin" target="_blank">
		<img src="<?php echo plugins_url('image/m_bolt_img.png', __FILE__); ?>"/>
	   </a>
	 </div>
      <div class="clear"></div>
   </div>
   <div class="mg-nav wrap" >
      <h2 class="nav-tab-wrapper">
         <span id="en_temp" class="nav-tab nav-tab-active ">Design Newsletter</span>
         <span id="en_listing" class="nav-tab">Contacts</span>
         <span id="en_smtp" class="nav-tab">SMTP Settings</span>
      </h2>
   </div>
   
   <div class="mg-content">
      <div class="mg-group" id="email_new_design_panel">
         <div class="mg-section section-text">
            <h3 class="mg-heading">Add new or view all designed newsletter/ template.</h3>
         </div>
         <div id = "mg_content_box">
            <input type="button" id="en_add_newsletter" class="button-primary mg-submit-btn" name="en_add_newsletter" value="Create New Newsletter" style="width:auto;float: right;" onclick="new_email_add();">
			<p class="mg-box-text"><b>Email Newsletter</b></p>
            <div class="mg-form-text">
			<form id="events-temp" method="POST" action="?page=bns_email_newsletter&tab=en_temp_tb">
			<input type="hidden" name="wpnonce" value="<?php
    echo wp_create_nonce("mg_delete_temp");
?>"/>

             <input type="hidden" name="page" value="<?php
    echo esc_html($_REQUEST['page']);
?>" /> 
               <?php
    $obj = new Bns_template();
?>
				</form>
            </div>
         </div>
      </div>
      <div class="mg-group" id="email_newletter_new" style = "display:none;">
         <div class="mg-section section-text">
            <h3 class="mg-heading">Create HTML or Plain newsletter/template</h3>
         </div>
         <div id="mg_msg_popup_option" class="mg-msg-popup-box" style="display: none;">Option Saved</div>
         <div id = "mg_content_box">
            <input type="button" id="en_new_design_temp" class="button-primary mg-submit-btn" name="en_new_design_temp" value="View All Newsletter" style="width:auto;float: right;" onclick="view_email_list();">
            <?php
    if (isset($_POST["save_newletter"])) {
        global $wpdb;
        $_POST = stripslashes_deep($_POST);
        global $user_ID;
        $new_post    = array(
            'post_title' => sanitize_text_field($_POST['email_news_subject']),
            'post_content' => "",
            'post_status' => 'publish',
            'post_date' => date('Y-m-d H:i:s'),
            'post_author' => filter_var($user_ID, FILTER_SANITIZE_NUMBER_INT),
            'post_type' => 'mg_newsletter',
            'post_category' => array(
                0
            )
        );
        $post_id     = wp_insert_post($new_post);
        $temp1       = wp_kses_post($_POST["emailnew_editor"]);
        $objLinkId   = 0;
        $arrlink     = array();
        $linkBaseUrl = $imageBaseUrl = site_url();
        $footer      = bns_footer_call($imageBaseUrl);
        $temp        = $temp1 . $footer;
        $dom         = new DOMDocument();
        //@$dom->loadHTML($temp);
		@$dom->loadHTML(mb_convert_encoding($temp, 'HTML-ENTITIES', 'UTF-8'));
        $xpath = new DOMXPath($dom);
        
        
        $images = $xpath->evaluate("/html/body//a");
        if (isset($images) && $images->length != 0) {
            for ($i = 0; $i < $images->length; $i++) {
                //foreach($img1 as $images){
                $img  = $images->item($i);
                $link = $img->getAttribute('href');
                
                if (gettype($img) !== NULL && trim($link) != '' && $img->getAttribute('id') !== 'mg-link' && $img->getAttribute('id') !== 'mg-unsub') {
                    if (strpos($link, "?utm_source=MailGet&utm_medium=email&utm_content") !== false) {
                        $new_link = $link . substr(0, strpos($link, '?utm_source=MailGet&utm_medium=email&utm_content'));
                        $img->setAttribute("href", $new_link);
                    }
                    if (strpos($link, "&utm_source=MailGet&utm_medium=email&utm_content") !== false) {
                        $new_link = $link . substr(0, strpos($link, '&utm_source=MailGet&utm_medium=email&utm_content'));
                        $img->setAttribute("href", $new_link);
                    }
                    $singleLink = array();
                    $rString    = bns_rand_str(6);
                    $img->setAttribute("id", $rString . (++$objLinkId));
                    $single_id           = $rString . $objLinkId;
                    $singleLink["id"]    = $single_id;
                    $single_url          = $img->getAttribute('href');
                    $singleLink["value"] = urlencode($single_url);
                    if (!empty($singleLink)) {
                        array_push($arrlink, $singleLink);
                        $img->setAttribute('href', ($linkBaseUrl . '?en_type=email_new_links&link_id=' . ($rString . $objLinkId) . '&temp_id=mailget_temp_id_replace&email_id=mailget_email_id_replace&s_id=mailget_s_id_replace&type=link'));
                        $img->setAttribute('id', $rString . $objLinkId);
                    }
                }
            }
        }
        $temp = $dom->saveHTML();
        
        $args      = array(
            'order' => 'DESC',
            'post_content' => "",
            'post_type' => 'mg_newsletter'
        );
        $temp_data = get_posts($args);
        if (isset($temp_data[0]->ID) && $temp_data[0]->ID != "") {
            $mailget_arr_value_static = array(
                "mailget_temp_id_replace"
            );
            $mailget_arr_replace      = array(
                $temp_data[0]->ID
            );
            $get_temp_html            = str_replace($mailget_arr_value_static, $mailget_arr_replace, $temp);
        } else {
            $get_temp_html = $temp;
            
        }
        
		$wpdb->query( $wpdb->prepare( 
	"
		INSERT INTO ". $wpdb->prefix . "bns_email_new_temp_link
		( temp_id, temp_link )
		VALUES ( %d, %s )
	", 
       array($temp_data[0]->ID,
             serialize(json_encode($arrlink))
        )
) );

			
        $new_post = array(
            'ID' => $temp_data[0]->ID,
            'post_content' => $get_temp_html
        );
        $post_id  = wp_update_post($new_post);
        echo "<meta http-equiv='refresh' content='0'>";
        
    }
?>
            <form method="post" enctype="multipart/form-data" action="?page=bns_email_newsletter&tab=en_temp_tb" onSubmit="return validate_en_temp();">
               <p class="mg-box-text"><b>Enter Email Subject</b></p>
               <div class="mg-form-text">
                  <input class="mg-box-input" type="text" name="email_news_subject" id="email_news_subject" value="" >
                  <p class="post_help_txt">Enter subject that used with this template</p>
                  <div id="email_news_subject_err" class="email-news-form-help-box" style="display: none;">Please enter email subject</div>
               </div>
               <p class="mg-box-text"><b>Enter Email Content</b></p>
               <div class="mg-form-text">
                  <div class="editor">
                     <?php
    // remove_filter( 'the_content', 'wpautop' );
    
    $settings = array(
        'textarea_rows' => 50,
        'editor_height' => 350
        //'wpautop'=>true
    );
    wp_editor('', 'emailnew_editor', $settings);
?>
                  </div>
				   <div id="email_news_text_err" class="email-news-form-help-box" style="display: none;">Please enter email content</div>
				   <div class="mg-form-text"><b>Note:</b> You can use following tags in email body and subject for personalization as <b>"{name}", "{firstname}", "{lastname}"</b></div>
               
               </div>
         </div>
         <div class="mg-section section-text" style="height: 45px;margin-bottom: 0;">
         <input type = "submit" id="save_newletter" class = "button-primary mg-submit-btn en_float_right" name = "save_newletter" value = "Save Newsletter">
         </div>
         </form>
      </div>
      <?php
    if (isset($_GET["pid"]) && $_GET["pid"] != "") {
?>
      <div class="mg-group" id="email_newletter_edit" >
         <div class="mg-section section-text">
            <h3 class="mg-heading">Edit saved HTML or Plain newsletter/template.</h3>
         </div>
         <div id="mg_msg_popup_option" class="mg-msg-popup-box" style="display: none;">Option Saved</div>
         <div id = "mg_content_box">
		    <input type="button" id="en_edit_temp" class="button-primary mg-submit-btn" name="en_edit_temp" value="View All Newsletter" style="width:auto;float: right;" onclick="view_email_list();">
            <?php
        if (isset($_POST["edit_newletter"])) {
            global $wpdb;
            $_POST       = stripslashes_deep($_POST);
            $temp        = wp_kses_post($_POST["edit_emailnew_editor"]);
            $arrlink     = array();
            $objLinkId   = 0;
            $linkBaseUrl = $imageBaseUrl = site_url();
            $footer      = bns_footer_call($imageBaseUrl);
            $temp_new    = $temp . $footer;
            $dom         = new DOMDocument();
            //@$dom->loadHTML($temp_new);
			@$dom->loadHTML(mb_convert_encoding($temp_new, 'HTML-ENTITIES', 'UTF-8'));
            $xpath  = new DOMXPath($dom);
            $images = $xpath->evaluate("/html/body//a");
            if (isset($images) && $images->length != 0) {
                for ($i = 0; $i < $images->length; $i++) {
                    $img  = $images->item($i);
                    $link = $img->getAttribute('href');
                    if (gettype($img) !== NULL && trim($link) != '' && $img->getAttribute('id') !== 'mg-link' && $img->getAttribute('id') !== 'mg-unsub') {
                        if (strpos($link, "?utm_source=MailGet&utm_medium=email&utm_content") !== false) {
                            $new_link = $link . substr(0, strpos($link, '?utm_source=MailGet&utm_medium=email&utm_content'));
                            $img->setAttribute("href", $new_link);
                        }
                        if (strpos($link, "&utm_source=MailGet&utm_medium=email&utm_content") !== false) {
                            $new_link = $link . substr(0, strpos($link, '&utm_source=MailGet&utm_medium=email&utm_content'));
                            $img->setAttribute("href", $new_link);
                        }
                        $singleLink = array();
                        $rString    = bns_rand_str(6);
                        $img->setAttribute("id", $rString . (++$objLinkId));
                        $single_id           = $rString . $objLinkId;
                        $singleLink["id"]    = sanitize_text_field($single_id);
                        $single_url          = filter_var($img->getAttribute('href'), FILTER_SANITIZE_URL);
                        $singleLink["value"] = urlencode($single_url);
                        if (!empty($singleLink)) {
                            array_push($arrlink, $singleLink);
                            $img->setAttribute('href', ($linkBaseUrl . '?en_type=email_new_links&link_id=' . ($rString . $objLinkId) . '&temp_id=mailget_temp_id_replace&email_id=mailget_email_id_replace&s_id=mailget_s_id_replace&type=link'));
                            $img->setAttribute('id', $rString . $objLinkId);
                        }
                    }
                }
            }
            
            $temp_new = $dom->saveHTML();
            
            //$temp_data = $wpdb->get_results("SELECT ID FROM ". $wpdb->posts ." WHERE (post_type = 'mg_newsletter') order_by desc ID limit 1");
            if (isset($_POST['edit_email_pid']) && $_POST['edit_email_pid'] != "") {
                $mailget_arr_value_static = array(
                    "mailget_temp_id_replace"
                );
                $mailget_arr_replace      = array(
                    filter_var($_POST['edit_email_pid'],FILTER_SANITIZE_NUMBER_INT)
                );
                $get_temp_html            = str_replace($mailget_arr_value_static, $mailget_arr_replace, $temp);
            } else {
                $get_temp_html = $temp;
                
            }
			$templateid =  filter_var($_POST['edit_email_pid'],FILTER_SANITIZE_NUMBER_INT);
			$temp_result = bns_get_all_links_of_temp_id($templateid);
			if(isset($temp_result) && !empty($temp_result)){
            $wpdb->update($wpdb->prefix . "bns_email_new_temp_link", array(
                "temp_link" => serialize(json_encode($arrlink))
            ), array(
                "temp_id" => filter_var($_POST['edit_email_pid'], FILTER_SANITIZE_NUMBER_INT)
            ));
			}
			else{
				$wpdb->query( $wpdb->prepare( 
	"
		INSERT INTO ". $wpdb->prefix . "bns_email_new_temp_link
		( temp_id, temp_link )
		VALUES ( %d, %s )
	", 
       array($templateid,
             serialize(json_encode($arrlink))
        )
) );
			}
            $new_post = array(
                'ID' => filter_var($_POST['edit_email_pid'], FILTER_SANITIZE_NUMBER_INT),
                'post_title' => sanitize_text_field($_POST['edit_email_news_subject']),
                'post_content' => $temp_new
            );
            $post_id  = wp_update_post($new_post);
            
        }
        $pid    = filter_var($_GET["pid"],FILTER_SANITIZE_NUMBER_INT);
        $p_data = get_post($pid);
        
        $cont          = apply_filters('the_content', $p_data->post_content);
        //$cont =  $p_data->post_content;
        $temp_link_arr = array();
        $arr_t_link    = array();
        $temp_link     = bns_get_all_links_of_temp_id($pid);
        if (isset($temp_link) && !empty($temp_link)) {
            $temp_link_arr = json_decode(unserialize($temp_link));
            foreach ($temp_link_arr as $t_link_k => $t_link_val) {
                $arr_t_link[$t_link_val->id] = urldecode($t_link_val->value);
            }
        }
        
        $dom = new DOMDocument();
		@$dom->loadHTML(mb_convert_encoding($cont, 'HTML-ENTITIES', 'UTF-8'));
        //@$dom->loadHTML($cont);
        $xpath     = new DOMXPath($dom);
        $cont_link = $xpath->evaluate("/html/body//a");
        if (isset($cont_link) && $cont_link->length != 0) {
            for ($i = 0; $i < $cont_link->length; $i++) {
                $links    = $cont_link->item($i);
                $linkid   = $links->getAttribute('id');
                $linkhref = $links->getAttribute('href');
                
                if (gettype($links) !== NULL && trim($linkhref) != '' && $linkid !== 'mg-link' && $linkid !== 'mg-unsub') {
                    
                    if (array_key_exists($linkid, $arr_t_link) && $arr_t_link[$linkid] != "") {
                        $links->setAttribute('href', $arr_t_link[$linkid]);
                    }
                }
            }
        }
        $cont = $dom->saveHTML();
        
        // $dom = new DOMDocument();
        // @$dom->loadHTML($cont);
        // $xpath = new DOMXPath($dom);
        
        $div_data = $xpath->evaluate("/html/body//div[@class='mg-append-footer']");
        if (isset($div_data) && $div_data->length != 0) {
            foreach ($div_data as $domElement) {
                // $domElemsToRemove .= $dom->saveHTML($domElement); // concat them
                $domElement->parentNode->removeChild($domElement); // then remove
            }
            
        }
        $cont = $dom->saveHTML();
        
        
?>
            <form method="post" enctype="multipart/form-data">
               <p class="mg-box-text"><b>Update Email Subject</b></p>
               <div class="mg-form-text">
                  <input class="mg-box-input" type="text" name="edit_email_news_subject" id="edit_email_news_subject" value="<?php
        echo esc_html($p_data->post_title);
?>" >
                  <p class="post_help_txt">Update subject that used with this template</p>
                  <div id="email_news_subject_err" class="email-news-form-help-box" style="display: none;">Please enter email subject</div>
               </div>
               <input type="hidden" value="<?php
        echo esc_html($_GET["pid"]);
?>" name = "edit_email_pid">
               <p class="mg-box-text"><b>Update Email Content</b></p>
               <div class="mg-form-text">
                  <div class="editor">
                     <?php
        
        
        $settings = array(
            'textarea_rows' => 50,
            'editor_height' => 350,
            'wpautop' => true
        );
		$cont = preg_replace('~<(?:!DOCTYPE|/?(?:html|head|body))[^>]*>\s*~i', '', $cont);
        wp_editor($cont, 'edit_emailnew_editor', $settings);
?>
                  </div>
				  <div class="mg-form-text"><b>Note:</b> You can use following tags in email body and subject for personalization as <b>"{name}", "{firstname}", "{lastname}"</b></div>
               </div>
         </div>
         <div class="mg-section section-text" style="height: 45px;margin-bottom: 0;">
         <input type = "submit" id="edit_newletter" class = "button-primary mg-submit-btn" name = "edit_newletter" value = "Update Newsletter" onclick="edit_newsletter()">
         </div>
         </form>
      </div>
      <?php
    }
?>
      <div class="mg-group" id="send_email_choose_list"  style = "display:none;">
         <div class="mg-section section-text">
            <h3 class="mg-heading">Send email by selecting the list.</h3>
         </div>
         <div id="email_news_design_option" class="mg-msg-popup-box" style="display: none;">Option Saved</div>
         <div id = "mg_content_box">
		 <p class="mg-box-text"><b>From Name:</b></p>
		 <div class="mg-form-text">
		  <?php
    $option = get_option("email_news_smtp_options");
    if (isset($option["from_name"]))
        $f_name = $option["from_name"];
    else
        $f_name = "";
?>
		      <input class="mg-box-input" type="text" name="email_new_fname" id="email_new_fname" value="<?php
    echo esc_html($f_name);
?>">
			  <p class="post_help_txt">Can change the set From Name</p>
			  <div id="email_news_fname_err" class="email-news-form-help-box" style="display: none;">Please enter from name</div>
		  </div>
		 <p class="mg-box-text"><b>Subject:</b></p>
		 <div class="mg-form-text">
		  <?php
    if (isset($_GET["temp_id"]) && $_GET["temp_id"] != "") {
        $f_sub    = get_post($_GET["temp_id"]);
        $from_sub = $f_sub->post_title;
    } else {
        $from_sub = "";
    }
?>
		      <input class="mg-box-input" type="text" name="email_new_sub" id="email_new_sub" value="<?php
    echo esc_html($from_sub);
?>">
			  <p class="post_help_txt">Can change the set Subject</p>
			  <div id="email_news_sub_err" class="email-news-form-help-box" style="display: none;">Please enter email subject</div>
		  </div>
            <p class="mg-box-text"><b>Select a list to Send mails:</b></p>
            <div class="mg-form-text">
               <ul class="element">
                  <?php
    global $wpdb;
    
     $query = $wpdb->get_results("SELECT list_id,list_name,count as total FROM " . $wpdb->prefix . "bns_email_news_list");
    
    if (isset($query[0]->list_id) && $query[0]->list_id != "") {
        foreach ($query as $val) {
            $tot = 0;
            if (isset($val->count))
                $tot = $tot + $val->count;
            if (isset($val->total) && $val->total != null)
                $tot = $tot + $val->total;
            
?>
                  <li>
                     <input type="checkbox"  name = "selected_list" value = "<?php
            echo esc_html($val->list_id);
?>"/>
                     <label class="checkbox_label" ><?php
            echo esc_html($val->list_name);
?><span class="chk_span"><?php
            echo "(" . esc_html($tot) . ")";
?></span></label>
                  </li>
                  <?php
        }
    }
?>
               </ul>
              
               <div id="send_list_error" class="post-email-form-help-box" style="display: none;">Please select list for sending email</div>
            </div>
         </div>
         <div class="mg-section section-text" style="height: 45px;margin-bottom: 0;">
            <?php
    if (isset($_GET["temp_id"])) {
        $post  = get_post($_GET["temp_id"]);
        $title = $post->post_title;
?><input type="hidden" value="<?php
        echo esc_html($title);
?>" name="hidden_subj" id="hidden_subj">
            <input type = "button" id="import_contact" class = "button-primary mg-submit-btn" name = "submit_mg_options" data-tempid="<?php
        echo esc_html($_GET['temp_id']);
?>" data-send = "data-send-temp" onclick="prev_email_temp(this);" value="Send">
            <?php
    }
?>
         </div>
      </div>
	       <div class="mg-group" id="add_email_new_list_panel" style="display:none;">
         <div class="mg-section section-text">
            <h3 class="mg-heading">Setting for Subcribers</h3>
         </div>
		 <?php
    if (isset($_GET["lid"]) && $_GET["lid"] != "") {
        $lid = filter_var($_GET["lid"],FILTER_SANITIZE_NUMBER_INT);
    } else {
        $lid = "";
    }
?>
		 <div id="mg_content_box">
		     <input type="button" class="button-primary mg-submit-btn en_float_right" name="en_all_list_view" value="View All List" style="width:auto;float: right;" onclick="email_new_view_all_list();">
			  <input type="button" class="btn button-primary mg-submit-btn " data-tempid="<?php
    echo esc_html($lid);
?>" value="Import" onclick="email_all_list_view(this);" id="email_import1" style="float:right;width:auto;margin-right:5px;">
			<?php
    $action = "";
    $lid    = "";
    if (isset($_GET["lid"]) && $_GET["lid"] != "") {
        global $wpdb;
        $lid    = filter_var($_GET['lid'], FILTER_SANITIZE_NUMBER_INT);
	    $res = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "bns_email_news_list where list_id = %d", $lid) );
	    $action = '?page=bns_email_newsletter&lid=' . $lid;
        if (isset($res[0]->list_name))
            $list_name = sanitize_text_field($res[0]->list_name);
        else
            $list_name = "";
?>
            <p class="mg-box-text"><b>View all Contacts in the List named as "<?php
        echo esc_html($list_name);
?>"</b></p>
			 
			<?php
    }
?>
			
            <div class="mg-form-text">
			<form id="events-list-cont" method="POST" action="<?php
    echo esc_url($action);
?>">			 
			  <input type="hidden" name="en_list_part_id" value="<?php
    echo esc_html($lid);
?>"/>

    <input type="hidden" name="page" value="<?php
    echo esc_html($_REQUEST['page']);
?>" />
	 <input type="hidden" name="wp_lnonce" value="<?php
    echo wp_create_nonce("mg_delete_list_part");
?>"/>
               <?php
    if (isset($_GET["lid"]) && $_GET["lid"] != "") {
        $obj = new Bns_list_contact(array(
            "lid" => filter_var($_GET["lid"],FILTER_SANITIZE_NUMBER_INT)
        ));
    }
    ?>	
				  </form>
            </div>
			
			</div>
         </div>
   
	  
	  
      <div class="mg-group" id="add_email_new_list_view_panel" style="display:none;">
         <div class="mg-section section-text">
            <h3 class="mg-heading">Setting for Subcribers</h3>
         </div>
           <div id = "mg_content_box">
            <input type="button" class="button-primary mg-submit-btn en_float_right" name="en_add_new_list" value="Add New List" style="width:auto;float: right;" onclick="email_add_new_list();">
		
			<p class="mg-box-text"><b>Add Contact List</b></p>
			  <div class="mg-form-text">
			  <form id="events-filter" method="POST" action="?page=bns_email_newsletter&tab=en_list_tb">
			  <input type="hidden" name="nonce" value="<?php
    echo wp_create_nonce("mg_delete_list");
?>"/>

    <input type="hidden" name="page" value="<?php
    echo esc_html($_REQUEST['page']);
?>" />
               <?php
    $obj = new Bns_list();
    
?>
</form>				
            </div>
         </div>
      </div>
	  
	  
	     <div class="mg-group" id="email_news_add_new_list" style="display:none;">
         <div class="mg-section section-text">
            <h3 class="mg-heading">Setting for Subcribers</h3>
         </div>
         <div id="email_news_list_sect" class="mg-msg-popup-box" style="display: none;">Option Saved</div>
         <div id = "mg_content_box">
	     <p class="mg-box-text"><b>Enter List Name</b></p>
          		
            <div class="mg-form-text" id="add_new_list">
               <input class="mg-box-input" type="text" name="add_list_name" id="add_list_name" value="" >
			    <div id="list_name_error" class="post-email-form-help-box" style="display: none;">Please enter list name</div>
               <input type = "button" class = "button-primary mg-submit-btn" name = "submit_mg_options" onclick="email_new_add_list()" value="ADD">
               <p class="post_help_txt">Please Enter Name of List </p>
               <div id="email_news_subject_err" class="email-news-form-help-box" style="display: none;">Please enter list name</div>
            </div>
			</div>
			<div class="mg-section section-text" style="height: 45px;margin-bottom: 0;">
            
         </div>
	  </div>
	  
	  
	  <?php
    if (isset($_GET["list_id"]) && $_GET["list_id"] != "") {
?>
      <div class="mg-group" id="email_view_all_list">
         <div class="mg-section section-text">
            <h3 class="mg-heading">Setting for Subcribers</h3>
         </div>
         <div id="email_news_imp_sect" class="mg-msg-popup-box" style="display: none;">Option Saved</div>
         <div id = "mg_content_box">
            <?php
        global $wpdb;
        $lid = filter_var($_GET["list_id"], FILTER_SANITIZE_NUMBER_INT);
		$res = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "bns_email_news_list where list_id = %d", $lid) );
          if (isset($res[0]->list_name) && $res[0]->list_name != "") {
            $list_id = filter_var($res[0]->list_id, FILTER_SANITIZE_NUMBER_INT);
?>
            <p class="mg-box-text"><b>Add Contact in the List named as "<?php
            echo sanitize_text_field($res[0]->list_name);
?>"</b></p>
            <?php
        }
?>
			  
            <div id="import_div" style="display:none;">
               <p class="mg-box-text">Import Contacts<a href="//www.formget.com/mailget/sample_file/SampleImport.csv" class="sample_csv">
                  (Download Sample CSV)</a>
               </p>
               <div class="mg-section section-text mg-form-text" style="height: 45px;margin-bottom: 0; width:375px;">
                  <div class="fg-upload-parent">
                     <input id="up_file" type="file" class="file1" name="up_file" style="visibility:hidden; height:0px !important;" onchange="document.getElementById('import_file_text').value = this.value;">	
                     <input class="fg-input text inline_path" id="import_file_text" placeholder="Import From (.csv) File" type="text" readonly="readonly">
                     <span class="fg-upload-btn" onclick="document.getElementById('up_file').click();"><i class="icon-folder"></i>Choose File</span>
                     <div class="fg-clear"></div>
                     <div id="post_email_import_csv" class="post-email-form-help-box" style="display: none;">Please upload csv file only</div>
                  </div>
               </div>
               <p class="mg-box-text">Add contact in Textarea</p>
               <div class="mg-form-text">
                  <textarea placeholder="Import email contacts directly by pasting here. Check sample format below. Paste names and emails separated by commas and individual contacts separated by semicolon. " 
                     cols="58" rows="6" id="import_emails_textarea"></textarea>
                  <div id="mg_form_description_help" class="mg-form-help-box" style="display: block;">	
                     <b style="color:black;">For Example</b>
                     <br>John Delavare, john.delavare@gmail.com;
                     <br>Mark Peters, mark@hotmail.com;
                     <br>jay.rony@gmail.com;
                  </div>
                  <div id="post_email_txt_import" class="post-email-form-help-box" style="display: none;"></div>
               </div>
            </div>
         </div>
         <div class="mg-section section-text" style="height: 45px;margin-bottom: 0;">
            <input type="hidden" value="<?php
        echo esc_html($list_id);
?>" name="list_id_hidden" id ="list_id_hidden"/>
            <input type = "button" id="import_contact" class = "button-primary mg-submit-btn" name = "submit_mg_options" onclick="import_contacts()" value="Import Contacts" style="display:none;">
         </div>
		
				
      </div>
	  	<?php
    }
?>
     
	  
	        <div class="mg-group" id="email_news_stats" style="display:none;">
         <div class="mg-section section-text">
            <h3 class="mg-heading">Stats of opens, clicks and unsubscribes.</h3>
         </div>
         <div id="email_news_design_option" class="mg-msg-popup-box" style="display: none;">Option Saved</div>
         <div id = "mg_content_box" class="mg_content_box">
            <input type="button" id="en_new_design_temp" class="button-primary mg-submit-btn" name="en_new_design_temp" value="View All Newsletter" style="width:auto;float: right;" onclick="view_email_list();">
            <p class="mg-box-text"><b>Email Tracking</b></p>
            <div class="mg-form-text all-stats">
<?php
    if (isset($_GET["temp_stats"]))
        $open_temp = filter_var($_GET['temp_stats'], FILTER_SANITIZE_NUMBER_INT);
    else
        $open_temp = "";
    
    $open = $click = $sent = $click = $links = $unsub = "";
    if (isset($_GET["temp_type"])) {
        $type = $_GET["temp_type"];
        if ($type == "open")
            $open = "active";
        elseif ($type == "click")
            $click = "active";
        elseif ($type == "sent")
            $sent = "active";
        elseif ($type == "links")
            $links = "active";
        elseif ($type == "unsub")
            $unsub = "active";
        else {
            $type = "sent";
            $sent = "active";
        }
    }
	$sel_count = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "bns_email_new_temp_all_track where temp_id = %d LIMIT 1", $open_temp) );
        
	$link_count = $wpdb->get_results( $wpdb->prepare( "SELECT temp_link FROM " . $wpdb->prefix . "bns_email_new_temp_link where temp_id = %d LIMIT 1", $open_temp) );	
   
    
    $sent_cnt = $click_cnt = $open_cnt = $unsub_cnt = $link_cnt = 0;
    
    if (isset($link_count[0]->temp_link) && $link_count[0]->temp_link != "") {
        $link_temp = json_decode(unserialize($link_count[0]->temp_link));
        $link_cnt  = count($link_temp);
    }
    
    if (isset($sel_count[0]->sent) && $sel_count[0]->sent != "")
        $sent_cnt = $sel_count[0]->sent;
    if (isset($sel_count[0]->click) && $sel_count[0]->click != "")
        $click_cnt = $sel_count[0]->click;
    if (isset($sel_count[0]->open) && $sel_count[0]->open != "")
        $open_cnt = $sel_count[0]->open;
    if (isset($sel_count[0]->unsub) && $sel_count[0]->unsub != "")
        $unsub_cnt = $sel_count[0]->unsub;
    
    
?>
            <!---- new,hold,solve and total message ---->
         <div class="col-md-3">
                <div id="new_entry" class="message-stat new-messages <?php
    echo esc_html($sent);
?>" onclick="en_call_stat(<?php
    echo esc_html($open_temp);
?>,'sent')">
                    <span id="new" class="stat"><?php
    echo esc_html($sent_cnt);
?></span>
                    <span class="block-title">Sent</span>
                </div>  
            </div>
            <div class="col-md-3 col-second"> 
			
                <div id="hold_entry" class="message-stat on-hold <?php
    echo $open;
?>" onclick="en_call_stat(<?php
    echo $open_temp;
?>,'open')">
                    <span id="hold" class="stat"><?php
    echo $open_cnt;
?></span>
                    <span class="block-title">Open</span>
                </div>
            </div>
            <div class="col-md-3 col-third"> 
                <div id="solve_entry" class="message-stat replied <?php
    echo esc_html($click);
?>" onclick="en_call_stat(<?php
    echo esc_html($open_temp);
?>,'click')">
                    <span id="solve" class="stat"><?php
    echo esc_html($click_cnt);
?></span>
                    <span class="block-title">Click</span>
                </div>
            </div>
			 <div class="col-md-3 col-fourth">
                <div id="all_entry" class="message-stat all-messages <?php
    echo esc_html($links);
?>" onclick="en_call_stat(<?php
    echo esc_html($open_temp);
?>,'links')">
                    <span id="all" class="stat"><?php
    echo esc_html($link_cnt);
?></span>
                    <span class="block-title">Links</span>
                </div>
            </div>
            <div class="col-md-3 last">
                <div id="all_entry" class="message-stat all-messages <?php
    echo esc_html($unsub);
?>" onclick="en_call_stat(<?php
    echo esc_html($open_temp);
?>,'unsub')">
                    <span id="all" class="stat"><?php
    echo esc_html($unsub_cnt);
?></span>
                    <span class="block-title">Unsubscribed</span>
                </div>
            </div>
            </div>
			<p class="mg-box-text"><b>Choose Campaign:</b></p>
            <div class="mg-form-text">
			 			 <?php
    $camp_id = "";
    if (isset($_GET["camp_id"]) && $_GET["camp_id"] != "" && $_GET["camp_id"] != "default") {
        $camp_id = sanitize_text_field($_GET["camp_id"]);
    }
	$res = $wpdb->get_results( $wpdb->prepare( "SELECT sender_id,date FROM " . $wpdb->prefix . "bns_email_new_sending where temp_id = %d order by send_id desc", $open_temp) );	
    if (isset($res[0]->sender_id) && $res[0]->sender_id != "") {
        $cnt           = 1;
        $select_option = '<select id="en_select_camp" class="mg-box-input" name="en_select_camp" onchange="en_call_stat(' . $open_temp . ',\'' . $type . '\')">';
        
        foreach ($res as $res_data) {
            $sending_id   = $res_data->sender_id;
            $f_c_date     = $res_data->date;
            // if ($gmt_str != '') {
            // $sending_date = date('d-M-Y | h:i A', strtotime('' . $f_c_date . ' ' . $gmt_str . ''));
            // } else {
            $sending_date = date('d-M-Y | h:i A', strtotime($f_c_date));
            //}
            if (isset($camp_id) && $camp_id == $sending_id)
                $selected = "selected";
            else
                $selected = "";
            if ($cnt == 1) {
                $sending_id_1 = $sending_id;
                $select_option .= '<option value="' . $sending_id . '"' . $selected . '> Campaign Sent on: ' . $sending_date . '</option>';
            } else {
                $select_option .= '<option value="' . $sending_id . '"' . $selected . '> Campaign Sent on: ' . $sending_date . '</option>';
            }
            ++$cnt;
        }
        $select_option .= "</select>";
        echo $select_option;
    }
    
?>
			 
			</div>
			
            <div class="mg-form-text">
			<?php
    if (isset($_GET["temp_stats"]) && isset($_GET["camp_id"]) && isset($_GET["temp_type"])) {
        $arr = array(
            "temp_id" => filter_var($_GET["temp_stats"],FILTER_SANITIZE_NUMBER_INT),
            "camp_id" => sanitize_text_field($_GET["camp_id"]),
            "temp_type" => sanitize_text_field($_GET["temp_type"])
        );
        $obj = new Bns_camp_stats($arr);
    }
    
?>
				  </div>
		 </div>
		  <div class="mg-section section-text" style="height: 45px;margin-bottom: 0;">
           
         </div>
      </div>
      <!-- smtp view -->			
      <div class="mg-group" id="email_new_smtp_setting" style="display:none;">
         <div class="mg-section section-text">
            <h3 class="mg-heading">Setting for Email SMTP</h3>
         </div>
		 
         <div id="email_new_smtp_setting_opt" class="mg-msg-popup-box" style="display: none;">Option Saved</div>
         <div id = "mg_content_box">
		   <input type="button" class="button-primary mg-submit-btn en_float_right" name="en_send_test" value="Send Test Mail" style="width:auto;float: right;" onclick="en_send_test_mail();">
		 <?php
    $en_type = "";
    $en_type = get_option("email_news_smtp_options");
?>
            <p class="mg-box-text"><b>From Email</b></p>
            <div class="mg-form-text">
               <input class="mg-box-input" type="text" name="email_new_f_email" id="email_new_f_email" value="<?php
    if (isset($en_type['from_email']))
        echo sanitize_email($en_type['from_email']);
?>" placeholder="Enter Your From Email" >
               <div id="email_new_f_email_err" class="email-news-form-help-box" style="display: none;">Please enter your From Email here</div>
            </div>
            <p class="mg-box-text"><b>Default From Name</b></p>
            <div class="mg-form-text">
               <input class="mg-box-input" type="text" name="email_new_f_name" id="email_new_f_name" value="<?php
    if (isset($en_type['from_name']))
        echo esc_html($en_type['from_name']);
?>" placeholder="Enter Your From Name" >
               <div id="email_new_f_name_err" class="email-news-form-help-box" style="display: none;">Please enter your From Name here </div>
            </div>
            <p class="mg-box-text"><b>Mailer</b></p>
            <div class="mg-form-text">
			  <?php
    if (isset($en_type['mailer']) && $en_type['mailer'] == "smtp") {
?>
               <input type="radio"  name = "mailer_option" value = "smtp" checked="checked"/>
			  <?php
    } else {
?>
			   <input type="radio"  name = "mailer_option" value = "smtp"/>
			 
			  <?php
    }
?>
				   
               <label class="mailer_smtp_label" > Send all WordPress emails via SMTP</label>
            </div>
            <div class="mg-form-text">
			 <?php
    if (isset($en_type['mailer']) && $en_type['mailer'] == "mail") {
?>
               <input type="radio"  name = "mailer_option" value = "mail" checked="checked"/>
			 <?php
    } else {
?>
				<input type="radio"  name = "mailer_option" value = "mail"/> 
			 <?php
    }
?>
			   <label class="mailer_mail_label" > Use the PHP mail() function to send emails</label>
               <div id="mailer_option_err" class="email-news-form-help-box" style="display: none;">Please choose mailing option</div>
            </div>
			<p class="description">Want to send emails to your customers in bulk. <a href="https://www.formget.com/mailget-bolt/">Try MailGet here</a></p>
            <p class="mg-box-text"><b>Return Path</b></p>
            <div class="mg-form-text">
			<?php
    if (isset($en_type["mail_set_return_path"]) && $en_type["mail_set_return_path"] == "true") {
?>
               <input type="checkbox"  name = "return_path" value = "true" checked="checked"/>
			<?php
    } else {
?>
			   <input type="checkbox"  name = "return_path" value = "true"/>
			<?php
    }
?>
                <label class="mailer_return_label" > Set the return-path to match the From Email</label>
            </div>
            <p class="mg-box-text"><b>SMTP OPTIONS</b></p>
            These options only apply if you have chosen to send mail by SMTP above.
            <p class="mg-box-text">SMTP Host</p>
            <div class="mg-form-text">
               <input class="mg-box-input" type="text" name="email_new_smtp_host" id="email_new_smtp_host" value="<?php
    if (isset($en_type['smtp_host']))
        echo sanitize_text_field($en_type['smtp_host']);
?>" placeholder="Enter Your SMTP Host" >                        
               <div id="email_new_smtp_host_err" class="email-news-form-help-box" style="display: none;">Please enter SMTP Host</div>
            </div>
            <p class="mg-box-text">SMTP Port</p>
            <div class="mg-form-text">
               <input class="mg-box-input" type="text" name="email_new_smtp_port" id="email_new_smtp_port" value="<?php
    if (isset($en_type['smtp_port']))
        echo filter_var($en_type['smtp_port'], FILTER_SANITIZE_NUMBER_INT);
?>" placeholder="Enter Your SMTP Port" >                        
               <div id="email_new_smtp_port_err" class="email-news-form-help-box" style="display: none;">Please enter SMTP Port</div>
            </div>
            <p class="mg-box-text"><b>Encryption</b></p>
            <div class="mg-form-text">
			<?php
    $en_none = $en_ssl = $en_tls = "";
    if (isset($en_type['smtp_encryption']) && $en_type['smtp_encryption'] == "none")
        $en_none = "checked=checked";
    else if (isset($en_type['smtp_encryption']) && $en_type['smtp_encryption'] == "ssl")
        $en_ssl = "checked=checked";
    else if (isset($en_type['smtp_encryption']) && $en_type['smtp_encryption'] == "tls")
        $en_tls = "checked=checked";
?>
               <input type="radio"  name="mailer_encrypt" value = "none" <?php
    echo esc_html($en_none);
?> />
               <label class="mailer_enc_label" > No encryption.</label>
            </div>
            <div class="mg-form-text">
               <input type="radio"  name = "mailer_encrypt" value = "ssl"  <?php
    echo esc_html($en_ssl);
?>/>
               <label class="mailer_enc_label" > Use SSL encryption.</label>
            </div>
            <div class="mg-form-text">
               <input type="radio"  name = "mailer_encrypt" value = "tls"  <?php
    echo esc_html($en_tls);
?>/>
               <label class="mailer_enc_label" > Use TLS encryption. This is not the same as STARTTLS. For most servers SSL is the recommended option.</label>
            </div>
            <p class="mg-box-text"><b>Authentication</b></p>
			<?php
    $en_auth1 = $en_auth2 = "";
    if (isset($en_type['smtp_authentication']) && $en_type['smtp_authentication'] == "false")
        $en_auth1 = "checked=checked";
    elseif (isset($en_type['smtp_authentication']) && $en_type['smtp_authentication'] == "true")
        $en_auth2 = "checked=checked";
?>
            <div class="mg-form-text">
			   <input type="radio"  name = "mailer_auth" value = "false" <?php
    echo esc_html($en_auth1);
?>/>
               <label class="mailer_auth_label" > No: Do not use SMTP authentication.</label>
            </div>
            <div class="mg-form-text">
               <input type="radio"  name = "mailer_auth" value = "true" <?php
    echo esc_html($en_auth2);
?>/>
               <label class="mailer_auth_label" > Yes: Use SMTP authentication.</label>
               <p class="mg-help-text">If this is set to no, the values below are ignored.</p>
            </div>
            <p class="mg-box-text">Username</p>
            <div class="mg-form-text">
               <input class="mg-box-input" type="text" name="email_news_uname" id="email_news_uname" value="<?php
    if (isset($en_type['smtp_username']))
        echo esc_html($en_type['smtp_username']);
?>" placeholder="Enter Your SMTP Username" >                        
               <div id="email_news_uname_err" class="email-news-form-help-box" style="display: none;">Please enter SMTP Username here</div>
            </div>
            <p class="mg-box-text">Password</p>
            <div class="mg-form-text">
               <input class="mg-box-input" type="text" name="email_news_pass" id="email_news_pass" value="<?php
    if (isset($en_type['smtp_password']))
        echo esc_html($en_type['smtp_password']);
?>" placeholder="Enter Your SMTP Password" >                        
               <div id="email_news_pass_err" class="email-news-form-help-box" style="display: none;">Please enter SMTP Password here</div>
            </div>
         </div>
		 <?php
    if (isset($en_type) && !empty($en_type))
        $val = "Update";
    else
        $val = "Save";
?>
         <div class="mg-section section-text" style="height: 45px;margin-bottom: 0;">
            <input type="button" id="submit_mg_smtp" class = "button-primary mg-submit-btn" name = "submit_mg_smtp" value ="<?php
    echo esc_html($val);
?>" style="display:block" onclick="email_new_save_smtp();">
         </div>
      </div>
	  
	  
	   <div class="mg-group" id="email_new_send_test_mail" style="display:none;">
         <div class="mg-section section-text">
            <h3 class="mg-heading">Send testing mail with SMTP settings</h3>
         </div>
		 
         <div id="email_new_smtp_test_loader" class="mg-msg-popup-box" style="display: none;">Sending...</div>
         <div id = "mg_content_box">
		   <input type="button" class="button-primary mg-submit-btn en_float_right" name="en_view_smtp" value="View SMTP" style="width:auto;float: right;" onclick="en_view_smtp_setting();">
		    <p class="mg-box-text"><b>Send to Email</b></p>
            <div class="mg-form-text">
		     <form method="post" enctype="multipart/form-data" action="" onSubmit="return email_new_send_test();">
               <input class="mg-box-input" type="text" name="email_new_to_email" id="email_new_to_email" value="" placeholder="Enter Email Address" >
               <div id="email_new_smtp_test" class="email-news-form-help-box" style="display: none; float:right;">Please enter Email to send mail to</div>
			    <input type = "submit" class = "button-primary mg-submit-btn" name = "email_send_test" value="Send" onclick="email_new_send_test();">
				</form>
			             </div>
						
						  <div class="mg-form-text" id="en_test_res">
						  
						 
						   <?php
    
    if (isset($_POST['email_send_test'])) {
        echo '<p class="mg-box-text">The SMTP debugging output is shown below:</p><textarea cols="130" rows="30">';
        if (isset($_POST["email_new_to_email"]) && $_POST["email_new_to_email"]) {
			$to_email = sanitize_email($_POST['email_new_to_email']);
            global $phpmailer;
            if (!is_object($phpmailer) || !is_a($phpmailer, 'PHPMailer')) {
                require_once ABSPATH . WPINC . '/class-phpmailer.php';
                require_once ABSPATH . WPINC . '/class-smtp.php';
                $phpmailer = new PHPMailer(true);
            }
            $subject              = 'Best Newsletter Plugin: ' . __('Test mail to ', 'wp-email-smtp') . $to_email;
            $message              = __('This is a test email generated by the Best Newsletter Plugin.', 'wp-email-smtp');
            $phpmailer->SMTPDebug = true;
            
            // Start output buffering to grab smtp debugging output
            ob_start();
            
            // Send the test mail
            $result     = wp_mail($to_email, $subject, $message);
            $smtp_debug = ob_get_clean();
            echo $smtp_debug;
            unset($phpmailer);
        }
        echo '</textarea>';
    }
    
?>
						
						 </div>
		  </div>
		  <div class="mg-section section-text" style="height: 45px;margin-bottom: 0;">
            
         </div>
		 </div>
		
        
   <div id="send-confirm" class="post-email-preview fg-hidden">
      <div style="display: block;" class="post-email-inner-prev email-prev fg-hidden">
         <h3 class="ipu-heading">Email Template</h3>
         <div id="allWrapper">
            <div id="frameHolder">
               <div class="tempPreview" id="tempPreview" data-id= "klopppp">
               </div>
               <button class="button-primary mg-submit-btn" id="temp_prev_can" onclick="close_temp_prev(this);">Cancel</button>
               <button class="button-primary mg-submit-btn fg-right blue" id="temp_prev_cont" onclick="confirm_send(this);" style="position: relative; float:right;">Yes, Continue</button>
               <span class="clearfix"></span>   
            </div>
            <div id="sendDetails" class="send-details fg-hidden">
               <div class="ipu-row">
                  <div class="l-head">Subject : </div>
                  <div class="r-text" id="ipu-subject"></div>
                  <div class="clearfix"></div>
               </div>
               <div class="ipu-row to-row">
                  <div class="l-head">To : </div>
                  <div class="r-text" id="ipu-to">          
                  </div>
                  <div class="clearfix"></div>
               </div>
               <div class="ipu-row">
                  <div class="l-head">Total Recipients : </div>
                  <div class="r-text">
                     <span class="tot-aud" id="ipu-tot"></span>
                  </div>
                  <div class="clearfix"></div>
               </div>
              <div style="margin:10px 0 10px 0">Total Recipients, After removing all the spam, bounce, unsubscribed and exclusions.</div>
			
               <button class="button-primary mg-submit-btn" onclick="bns_close_ipu(this);">Cancel</button>
               <button class="button-primary mg-submit-btn" onclick="send_list_emails(this);">Send It</button>
			   
                   
            </div>
         </div>
      </div>
   </div>
</div>
<?php
}


function bns_import()
{
    if (!check_ajax_referer('email_news_option_nonce', 'email_news_option_nonce') && !is_user_logged_in() && !current_user_can('manage_options')) {
        return;
    }
    global $wpdb;
    $$txt_email             = "no";
    $output                 = 0;
    $table_name             = '';
    $list_email_cnt         = 0;
    $cnt                    = 0;
    $list_count             = 0;
    $index                  = 0;
    $data_trim              = array();
    $main_contact_arr       = array();
    $list_email_arr         = array();
    $main_contact_arr_final = array();
    $output_arr             = array();
    $name                   = '';
    $email                  = '';
    $list_id                = $list_select_id = filter_var($_POST['list_id_to_import'],FILTER_SANITIZE_NUMBER_INT);
    $ip_address             = bns_get_client_ip();
    $contact_arr            = $main_contact_arr1 = array();
    $saved_arr              = array();
    $mimes                  = array(
        'application/vnd.ms-excel',
        'text/csv'
    );
    $list_email_arr         = array();
    $list_email_arr         = bns_array_intialization($list_id);
    if (isset($list_email_arr['table']) && isset($list_email_arr['list_email_arr'])) {
        $table_name     = $list_email_arr['table'];
        $list_email_arr = $list_email_arr['list_email_arr'];
    }
    
    if (isset($_FILES['up_file']['tmp_name']) && ($_FILES['up_file']['size'] > 0)) {
        $file               = $_FILES['up_file']['tmp_name'];
        $selected_delemeter = bns_analyse_csv_file($file, 1);
        ini_set("auto_detect_line_endings", "1");
        $handle = fopen($file, "r");
        $data   = fgetcsv($handle, "", $selected_delemeter);
        $data   = array_map('trim', $data);
        $data   = array_map('strtolower', $data);
        if (count($data) >= 1) {
            if (in_array("name", $data) && in_array("email", $data)) {
                $key_name  = array_search('name', $data);
                $key_name  = intval($key_name);
                $key_email = array_search('email', $data);
                $key_email = intval($key_email);
            } else if (in_array("email", $data)) {
                $key_name  = 'no_name';
                $key_email = array_search('email', $data);
                $key_email = intval($key_email);
            } else {
                $key_name  = 'no_name';
                $key_email = 'no_email';
            }
        }
        if ($key_email !== 'no_email') {
            $index      = 1;
            $split_size = 0;
            while ($data = fgetcsv($handle, "", $selected_delemeter)) {
                if ($key_name !== 'no_name' && $key_email !== 'no_email' && isset($data[$key_name]) && isset($data[$key_email])) {
                    $name  = trim($data[$key_name]);
                    $email = trim($data[$key_email]);
                    
                } else if ($key_email !== 'no_email' && isset($data[$key_email])) {
                    $name  = '';
                    $email = trim($data[$key_email]);
                    
                }
                if (filter_var(trim($email), FILTER_VALIDATE_EMAIL)) {
                    
                    $contact_arr['name']  = htmlentities($name);
                    $contact_arr['email'] = $email;
                    if (isset($list_email_arr[$email]->date)) {
                        $contact_arr['date'] = $list_email_arr[$email]->date;
                    } else {
                        $contact_arr['date'] = date('Y-m-d H:i:s');
                    }
                    
                    $contact_arr['ip']        = $ip_address;
                    $main_contact_arr[$email] = $contact_arr;
                    $list_email_cnt           = count($list_email_arr);
                    $split_size               = intval($index + count($list_email_arr));
                    if ($split_size % 50000 === 0) {
                        $output_arr = bns_update_contacts_list($main_contact_arr, $list_email_arr, $list_select_id, $cnt, $table_name);
                        if (isset($output_arr['import_cnt'])) {
                            
                            $list_count = $output_arr['import_cnt'];
                            $output     = intval($list_count) + $output;
                            
                        }
                        $main_contact_arr = array();
                        $list_email_arr   = array();
                        $index            = 0;
                        $cnt++;
                        $list_email_arr = bns_array_intialization($list_select_id);
                        if (isset($list_email_arr['table']) && isset($list_email_arr['list_email_arr'])) {
                            $table_name     = $list_email_arr['table'];
                            $list_email_arr = $list_email_arr['list_email_arr'];
                        }
                    }
                }
                $index++;
            }
        } else {
            $csv        = bns_readCSV($file);
            $index      = 1;
            $split_size = 0;
            foreach ($csv as $single) {
                for ($i = 0; $i < sizeof($single); $i++) {
                    if (filter_var(trim($single[$i]), FILTER_VALIDATE_EMAIL)) {
                        $email                = trim($single[$i]);
                        $contact_arr['name']  = '';
                        $contact_arr['email'] = $email;
                        if (isset($list_email_arr[$email]->date)) {
                            $contact_arr['date'] = $list_email_arr[$email]->date;
                        } else {
                            $contact_arr['date'] = date('Y-m-d H:i:s');
                        }
                        $contact_arr['ip']        = $ip_address;
                        $main_contact_arr[$email] = $contact_arr;
                        $list_email_cnt           = count($list_email_arr);
                        $split_size               = intval($index + count($list_email_arr));
                        if ($split_size % 50000 === 0) {
                            $output_arr = bns_update_contacts_list($main_contact_arr, $list_email_arr, $list_select_id, $cnt, $table_name);
                            if (isset($output_arr['import_cnt'])) {
                                $list_count = $output_arr['import_cnt'];
                                $output     = intval($list_count) + $output;
                            }
                            $main_contact_arr = array();
                            $list_email_arr   = array();
                            $index            = 0;
                            $cnt++;
                            $list_email_arr = bns_array_intialization($list_select_id);
                            if (isset($list_email_arr['table']) && isset($list_email_arr['list_email_arr'])) {
                                $table_name     = $list_email_arr['table'];
                                $list_email_arr = $list_email_arr['list_email_arr'];
                            }
                        }
                        // $main_contact_arr = $contact_arr;
                        // $main_contact_arr1[$email] = $contact_arr;
                        
                    }
                    $index++;
                }
            }
        }
    }
    $import_emails_textarea = sanitize_text_field($_POST['import_emails_textarea']);
    if ($import_emails_textarea != '') {
        $import_emails_textarea_arr = explode(';', $import_emails_textarea);
        if (count($import_emails_textarea_arr) > 10000) {
            $output = 'textarea_limit_over';
            echo $output;
            return;
        }
        if (!empty($import_emails_textarea_arr)) {
            $index      = 1;
            $split_size = 0;
            for ($i = 0; $i < sizeof($import_emails_textarea_arr); $i++) {
                $email      = 'no';
                $name       = '';
                $email_data = explode(',', $import_emails_textarea_arr[$i]);
                if (isset($email_data[0]) && filter_var(trim($email_data[0]), FILTER_VALIDATE_EMAIL)) {
                    $email = trim($email_data[0]);
                    $name  = '';
                } else if (isset($email_data[1]) && filter_var(trim($email_data[1]), FILTER_VALIDATE_EMAIL)) {
                    $name  = trim($email_data[0]);
                    $email = trim($email_data[1]);
                } else {
                    $email == "no";
                }
                if ($email != "no") {
                    $contact_arr['name']  = $name;
                    $contact_arr['email'] = $email;
                    if (isset($list_email_arr[$email]->date)) {
                        $contact_arr['date'] = $list_email_arr[$email]->date;
                    } else {
                        $contact_arr['date'] = date('Y-m-d H:i:s');
                    }
                    $contact_arr['ip']        = $ip_address;
                    $main_contact_arr[$email] = $contact_arr;
                    $split_size               = intval($index + count($list_email_arr));
                    
                    if ($split_size % 50000 == 0) {
                        $output_arr = bns_update_contacts_list($main_contact_arr, $list_email_arr, $list_select_id, $cnt, $table_name);
                        if (isset($output_arr['import_cnt'])) {
                            $list_count = $output_arr['import_cnt'];
                            $output     = intval($list_count) + $output;
                        }
                        $main_contact_arr = array();
                        $index            = 0;
                        $cnt++;
                        $list_email_arr = bns_array_intialization($list_select_id);
                        if (isset($list_email_arr['table']) && isset($list_email_arr['list_email_arr'])) {
                            $table_name     = $list_email_arr['table'];
                            $list_email_arr = $list_email_arr['list_email_arr'];
                        }
                    }
                }
                $index++;
            }
        }
    }
    if (isset($main_contact_arr) && !empty($main_contact_arr)) {
        
        $list_email_arr = bns_array_intialization($list_select_id);
        if (isset($list_email_arr['table']) && isset($list_email_arr['list_email_arr'])) {
            $table_name     = $list_email_arr['table'];
            $list_email_arr = $list_email_arr['list_email_arr'];
            
        }
        
        $list_email_cnt = count($list_email_arr);
        $output_arr     = bns_update_contacts_list($main_contact_arr, $list_email_arr, $list_select_id, $cnt, $table_name);
        if (isset($output_arr['import_cnt'])) {
            $list_count = $output_arr['import_cnt'];
            $output     = intval($list_count) + $output;
            if ($list_count === 'exist') {
                echo 'exist';
                return;
            }
        }
    }
    if ($output === 0 && $index > 1) {
        echo 'error';
        return;
    }
    echo 'list_updated';
    die();
}

function bns_get_client_ip()
{
    $ipaddress = '';
    if (isset($_SERVER['HTTP_CLIENT_IP']))
        $ipaddress = $_SERVER['HTTP_CLIENT_IP'];
    else if (isset($_SERVER['HTTP_X_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED_FOR'];
    else if (isset($_SERVER['HTTP_X_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_X_FORWARDED'];
    else if (isset($_SERVER['HTTP_FORWARDED_FOR']))
        $ipaddress = $_SERVER['HTTP_FORWARDED_FOR'];
    else if (isset($_SERVER['HTTP_FORWARDED']))
        $ipaddress = $_SERVER['HTTP_FORWARDED'];
    else if (isset($_SERVER['REMOTE_ADDR']))
        $ipaddress = $_SERVER['REMOTE_ADDR'];
    else
        $ipaddress = 'UNKNOWN';
    return $ipaddress;
}
function bns_update_contacts_list($main_contact_arr, $list_email_arr, $list_select_id, $cnt, $table_name)
{
    
    $import_cnt                   = 0;
    $repeat_cnt                   = 0;
    $new_cnt                      = 0;
    $list_name                    = '';
    $limit_status                 = '';
    $id                           = 0;
    $list_email_arr_counter       = 0;
    $total_email_count_by_uid     = 0;
    $get_subs_plan_limit          = 0;
    $this_email_count_up          = 0;
    $slice_arr_size               = 0;
    $existing_unique_emails_count = 0;
    $new_unique_emails_count      = 0;
    $repeat_email_count           = 0;
    $get_total_subscribers        = 0;
    $main_contact_arr_return_size = 0;
    $email_count_up               = 0;
    $slice_status                 = 'no';
    $main_contact_arr_repeat      = array();
    $main_contact_arr_new         = array();
    $main_contact_arr_final       = array();
    $return_arr                   = array(
        'import_cnt' => 0
    );
    
    
    /** variable initialization  * */
    /** get unique emails from all list and filter repeat emails from new list  * */
    $main_contact_arr = (array) $main_contact_arr;
    
    if (!empty($main_contact_arr)) {
        $main_contact_arr_size = sizeof($main_contact_arr);
        
        if ($main_contact_arr_size > 0) {
            $count_data_arr = bns_insert_update_unique_emails($main_contact_arr, $list_select_id);
        }
        if (!empty($count_data_arr)) {
            $main_contact_arr_new    = $count_data_arr['new_email_arr'];
            $new_unique_emails_count = $count_data_arr['new_list'];
        }
        $main_contact_arr_return_size = intval($new_unique_emails_count);
        if ($main_contact_arr_return_size > 0) {
            $main_contact_arr_new = (array) $main_contact_arr_new;
            /** if users subscription limit valid * */
            if ((!empty($main_contact_arr)) && (!empty($list_email_arr))) {
                
                $list_email_arr         = (array) $list_email_arr;
                //$main_contact_arr_final = $list_email_arr;
                $main_contact_arr_final = array_merge($list_email_arr, $main_contact_arr_new);
                $list_email_arr_counter = sizeof($list_email_arr);
            } else if (!empty($main_contact_arr) && empty($list_email_arr)) {
                //$main_contact_arr_final = array_merge($main_contact_arr_final, $main_contact_arr_repeat);
                
                $main_contact_arr_final = array_merge($list_email_arr, $main_contact_arr_new);
            }
            
            
            $new_cnt                 = sizeof($main_contact_arr_new);
            $import_cnt              = $new_cnt;
            $email_count_up          = intval(sizeof($main_contact_arr_final));
            /** release variable memory * */
            $main_contact_arr_new    = '';
            $main_contact_arr_repeat = '';
            $main_contact_arr        = '';
            $list_email_arr          = '';
            if ($email_count_up > 0) {
                
                $response                 = bns_update_insert_emails_in_users_contact_list($main_contact_arr_final, $email_count_up, $table_name, $list_select_id);
                $return_arr['import_cnt'] = $import_cnt;
                return $return_arr;
            } else {
                return $return_arr;
            }
        } else {
            
            return $return_arr;
        }
        
    } else {
        $return_arr['import_cnt'] = 'exist';
        return $return_arr; //spam bounce already exist
    }
}
function bns_insert_update_unique_emails($email_arr, $list_id)
{
    $email_arr     = (array) $email_arr;
    $email_arr_new = array();
    $return_arr    = array(
        'new_email_arr' => array()
    );
    
    
    if (!empty($email_arr)) {
        // 	filter new and old emails from unique_emails table
        $email_arr_new = bns_check_unique_emails_table($email_arr, $list_id);
        
        $email_arr_new_size          = sizeof($email_arr_new);
        $return_arr['new_list']      = $email_arr_new_size;
        $return_arr['new_email_arr'] = $email_arr_new;
    }
    return $return_arr;
    
}
function bns_check_unique_emails_table($main_contact_arr, $list_id)
{
    $main_contact_arr = (array) $main_contact_arr;
    
    $get_selected_list_arr = '';
    $get_list_arr          = '';
    $email_list_str        = '';
    $email_list            = array();
    global $wpdb;
	$get_email = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ". $wpdb->prefix . "bns_email_news_list WHERE list_id = %d", $list_id) );
    if (isset($get_email) && $get_email[0]->contacts != "") {
        $email_list_str   = $email_list[0]->contacts;
        $email_list       = json_decode(unserialize($email_list_str));
        $email_list       = (array) $email_list;
        $main_contact_arr = array_diff_key($main_contact_arr, $email_list);
        $email_list_str   = '';
        $email_list       = array();
        
        $main_contact_arr = (array) $main_contact_arr;
		$get_email_extra = $wpdb->get_results( $wpdb->prepare( "SELECT slot_id FROM ". $wpdb->prefix . "bns_email_news_list_extra WHERE list_id = %d", $list_id) );
        if (isset($get_email_extra) && $get_email_extra[0]->slot_id != "") {
            foreach ($get_email_extra as $extra_data) {
                $sid = $extra_data->slot_id;
                
			    $get_email_extra_data = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM ". $wpdb->prefix . "bns_email_news_list_extra WHERE list_id = %d and slot_id = %d", $list_id, $sid) );
                $email_list_str       = $get_email_extra_data[0]->contacts;
                $email_list           = json_decode($email_list_str);
                $email_list           = (array) $email_list;
                $main_contact_arr     = array_diff_key($main_contact_arr, $email_list);
                $email_list_str       = '';
                $email_list           = array();
            }
            $main_contact_arr = (array) $main_contact_arr;
        }
        
    }
    return $main_contact_arr;
    
}

function bns_get_last_list_from_extra($lid)
{
    global $wpdb;
	$create_list = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM ". $wpdb->prefix . "bns_email_news_list_extra WHERE list_id = %d order by list_id desc limit 1", $list_id) );
   
    
    if (!empty($create_list) && isset($create_list[0]->id)) {
        $return = $create_list[0]->id;
    } else {
        $return = $return;
    }
    
    return $return;
}

function bns_update_insert_emails_in_users_contact_list($main_contact_arr_final, $email_count_up, $table_name, $list_select_id)
{
    if (!empty($main_contact_arr_final)) {
        $id                     = 0;
        $main_contact_arr_final = serialize(json_encode($main_contact_arr_final));
        $emails_arr             = array(
            'contacts' => $main_contact_arr_final,
            'count' => $email_count_up
        );
        if ($table_name == 'main') {
            bns_update_emails_in_list($list_select_id, $emails_arr);
        } else {
            if ($table_name == 'extra_empty') {
                $extra_rows_exist = bns_check_extra_emails_exist($list_select_id);
                if ($extra_rows_exist > 0) {
                    $id = bns_get_last_list_cnt_from_extra($list_select_id);
                    if (isset($id) && !empty($id) && $id > 0) {
                        bns_update_extra_emails_in_list($list_select_id, $emails_arr, $id);
                    }
                } else {
                    
                    $id = bns_create_new_contact_list($list_select_id);
                    if (isset($id) && !empty($id) && $id > 0) {
                        bns_update_extra_emails_in_list($list_select_id, $emails_arr, $id);
                    }
                }
            } elseif ($table_name == 'extra') {
                $id = bns_get_last_list_from_extra($list_select_id);
                if (isset($id) && !empty($id) && $id > 0) {
                    bns_update_extra_emails_in_list($list_select_id, $emails_arr, $id);
                }
            } else {
                $id = bns_create_new_contact_list($list_select_id);
                if (isset($id) && !empty($id) && $id > 0) {
                   bns_update_extra_emails_in_list($list_select_id, $emails_arr, $id);
                }
            }
        }
        return 'list_updated';
    } else {
        return 'error';
    }
}

function bns_get_emails_from_list($list_id)
{
    global $wpdb;
	$get_email = $wpdb->get_results( $wpdb->prepare( "SELECT contacts FROM ". $wpdb->prefix . "bns_email_news_list WHERE list_id = %d", $list_id) );
    if (isset($get_email) && $get_email[0]->contacts != "") {
        return $get_email[0]->contacts;
    }
}


function bns_check_extra_emails_exist($list_id)
{
    global $wpdb;
	$get_email = $wpdb->get_results( $wpdb->prepare( "SELECT list_id FROM ". $wpdb->prefix . "bns_email_news_list_extra WHERE list_id = %d", $list_id) );
    if (isset($get_email) && $get_email[0]->list_id != "") {
        return count($get_email);
    } else {
        return 0;
    }
}
function bns_get_extra_emails_from_list($lid)
{
    global $wpdb;
    $list_data = $wpdb->get_results( $wpdb->prepare( "SELECT contacts FROM ". $wpdb->prefix . "bns_email_news_list_extra WHERE list_id = %d", $lid) );
	if (isset($list_data[0]->contacts)) {
        return $list_data[0]->contacts;
    }
}

function bns_get_last_list_cnt_from_extra($lid)
{
    global $wpdb;
	$get_email = $wpdb->get_results( $wpdb->prepare( "SELECT id FROM ". $wpdb->prefix . "bns_email_news_list_extra WHERE list_id = %d order by id desc limit 1", $lid) );
    if (isset($get_email) && $get_email[0]->id != "") {
        return $get_email[0]->id;
    } else {
        return 0;
    }
    
}

function bns_create_new_contact_list_model($create_new_list_arr)
{
    $return = 0;
    global $wpdb;
    
  
	$query = $wpdb->query( $wpdb->prepare(
	"
		INSERT INTO ".$wpdb->prefix . "bns_email_news_list_extra
		( list_id, slot_id )
		VALUES ( %d, %d )
	", 
       array($create_new_list_arr["list_id"], $create_new_list_arr["slot_id"])
) );
   // $query = $wpdb->insert($wpdb->prefix . "email_news_list_extra", $create_new_list_arr);
    
    $id = $wpdb->insert_id;
    if (isset($id) && $id != "") {
        return $id;
    } else {
        return $return;
    }
    
}


function bns_check_list_slot_exist($list_id, $slot)
{
    global $wpdb;
	$list_cnt = $wpdb->get_results( $wpdb->prepare( "SELECT slot_id FROM ". $wpdb->prefix . "bns_email_news_list_extra WHERE list_id = %d and slot_id = %d order by slot_id desc", $list_id, $slot) );
     if (isset($list_cnt[0]->slot_id) && $list_cnt[0]->slot_id != "") {
        
        return count($list_cnt);
    } else {
        return 0;
    }
}

function bns_create_new_contact_list($list_select_id)
{
    $cnt             = 1;
    $list_slot_exist = bns_check_list_slot_exist($list_select_id, $cnt);
    
    if ($list_slot_exist > 0) {
        while ($list_slot_exist != 0) {
            $list_slot_exist = bns_check_list_slot_exist($list_select_id, $cnt);
            if ($list_slot_exist === 0) {
                $create_new_list_arr = array(
                    'list_id' => filter_var($list_select_id, FILTER_SANITIZE_NUMBER_INT),
                    'slot_id' => filter_var($cnt, FILTER_SANITIZE_NUMBER_INT)
                );
                $id                  = bns_create_new_contact_list_model($create_new_list_arr);
            }
            $cnt++;
        }
    } else {
        $create_new_list_arr = array(
            'list_id' => filter_var($list_select_id, FILTER_SANITIZE_NUMBER_INT),
            'slot_id' => filter_var($cnt, FILTER_SANITIZE_NUMBER_INT)
        );
        $id                  = bns_create_new_contact_list_model($create_new_list_arr);
    }
    return $id;
}

function bns_update_emails_in_list($lid, $arr)
{
    global $wpdb;
    $query = $wpdb->update($wpdb->prefix . "bns_email_news_list", $arr, array(
        'list_id' => filter_var($lid, FILTER_SANITIZE_NUMBER_INT)
    ));
    
}
function bns_update_extra_emails_in_list($lid, $arr, $id)
{
    global $wpdb;
    
    $query = $wpdb->update($wpdb->prefix . "bns_email_news_list_extra", $arr, array(
        "id" => filter_var($id, FILTER_SANITIZE_NUMBER_INT)
    ));
}


function bns_array_intialization($list_select_id)
{
    $list_arr       = array();
    $list_email     = bns_get_emails_from_list($list_select_id);
    $list_email_arr = json_decode(unserialize($list_email));
    $list_email_arr = (array) $list_email_arr;
    if (count($list_email_arr) >= 50000) {
        $extra_rows_exist = bns_check_extra_emails_exist($list_select_id);
        if ($extra_rows_exist > 0) {
            $list_email = bns_get_extra_emails_from_list($list_select_id);
            $list_arr   = json_decode(unserialize($list_email));
            $list_arr   = (array) $list_arr;
            if (count($list_arr) == 50000) {
                $list_arr       = array();
                $list_email_arr = array(
                    'list_email_arr' => $list_arr,
                    'table' => 'extra_full'
                );
                return $list_email_arr;
            } elseif (count($list_arr) > 0 && count($list_arr) < 50000) {
                $list_email_arr = array(
                    'list_email_arr' => $list_arr,
                    'table' => 'extra'
                );
                return $list_email_arr;
            } else {
                $list_arr       = array();
                $list_email_arr = array(
                    'list_email_arr' => $list_arr,
                    'table' => 'extra_empty'
                );
                return $list_email_arr;
            }
        } else {
            $list_arr       = array();
            $list_email_arr = array(
                'list_email_arr' => $list_arr,
                'table' => 'extra_empty'
            );
            return $list_email_arr;
        }
    } elseif (count($list_email_arr) < 50000) {
        $list_email_arr = array(
            'list_email_arr' => $list_email_arr,
            'table' => 'main'
        );
        return $list_email_arr;
    }
}

function bns_analyse_csv_file($file, $capture_limit_in_kb = 1)
{
    // capture starting memory usage
    $output['peak_mem']['start'] = memory_get_peak_usage(true);
    // log the limit how much of the file was sampled (in Kb)
    $output['read_kb']           = $capture_limit_in_kb;
    // read in file
    $fh                          = fopen($file, 'r');
    $contents                    = fread($fh, ($capture_limit_in_kb * 1024)); // in KB
    fclose($fh);
    // specify allowed field delimiters
    $delimiters   = array(
        'comma' => ',',
        'semicolon' => ';',
        'tab' => "\t",
        'pipe' => '|',
        'colon' => ':',
        'space' => ' '
    );
    // specify allowed line endings
    $line_endings = array(
        'rn' => "\r\n",
        'n' => "\n",
        'r' => "\r",
        'nr' => "\n\r"
    );
    // loop and count each line ending instance
    foreach ($line_endings as $key => $value) {
        $line_result[$key] = substr_count($contents, $value);
    }
    // sort by largest array value
    $line_result_demo = $line_result;
    asort($line_result);
    rsort($line_result_demo);
    //echo $line_result_demo[0];
    // log to output array
    $output['line_ending']['results'] = $line_result;
    $output['line_ending']['count']   = end($line_result);
    $output['line_ending']['key']     = key($line_result);
    $output['line_ending']['value']   = $line_endings[$output['line_ending']['key']];
    $lines                            = explode($output['line_ending']['value'], $contents);
    // remove last line of array, as this maybe incomplete?
    array_pop($lines);
    // create a string from the legal lines
    $complete_lines            = implode(' ', $lines);
    // log statistics to output array
    $output['lines']['count']  = count($lines);
    $output['lines']['length'] = strlen($complete_lines);
    // loop and count each delimiter instance
    foreach ($delimiters as $delimiter_key => $delimiter) {
        $delimiter_result[$delimiter_key] = substr_count($complete_lines, $delimiter);
    }
    // sort by largest array value
    $delimiter_result_demo = $delimiter_result;
    asort($delimiter_result);
    rsort($delimiter_result_demo);
    //echo $delimiter_result_demo[1];
    // log statistics to output array with largest counts as the value
    $output['delimiter']['results'] = $delimiter_result;
    $output['delimiter']['count']   = end($delimiter_result);
    $output['delimiter']['key']     = key($delimiter_result);
    $output['delimiter']['value']   = $delimiters[$output['delimiter']['key']];
    // capture ending memory usage
    $output['peak_mem']['end']      = memory_get_peak_usage(true);
    //return $output;
    if (($line_result_demo[0] == $delimiter_result_demo[1]) || ($line_result_demo[0] * 2 == $delimiter_result_demo[1])) {
        foreach ($delimiter_result as $key => $value) {
            if ($value == $delimiter_result_demo[1]) {
                $selected_delemeter_main = $delimiters[$key];
            }
        }
    } else {
        $selected_delemeter_main = $output['delimiter']['value'];
    }
    return $selected_delemeter_main;
}


function bns_readCSV($csvFile)
{
    $file_handle = fopen($csvFile, 'r');
    while (!feof($file_handle)) {
        $line_of_text[] = fgetcsv($file_handle, 1024);
    }
    fclose($file_handle);
    return $line_of_text;
}


function bns_preview()
{
    global $wpdb;
    $temp = "";
    if (!check_ajax_referer('email_news_option_nonce', 'email_news_option_nonce') && !is_user_logged_in() && !current_user_can('manage_options')) {
        return;
    }
    $id   = filter_var($_POST["temp_id"],FILTER_SANITIZE_NUMBER_INT);
    $post = get_post($id);
    if (isset($post->post_content)) {
        $temp = apply_filters('the_content', $post->post_content);
    }
    echo $temp;
    die();
}

function bns_add_list()
{
    if (!check_ajax_referer('email_news_option_nonce', 'email_news_option_nonce') && !is_user_logged_in() && !current_user_can('manage_options')) {
        return;
    }
    $lname = sanitize_text_field($_POST['list_name']);
    if (isset($lname) && $lname != "") {
        global $wpdb;
        $contlist  = array();
        $cont_list = serialize(json_encode($contlist));
		$query  = $wpdb->query( $wpdb->prepare( 
	"
		INSERT INTO ". $wpdb->prefix . "bns_email_news_list
		( list_name, contacts, count, date )
		VALUES ( %s, %s, %d, %s )
	", 
        array(
		$lname, $cont_list, 0, date('Y-m-d H:i:s')
		) 
) );
    }
    
    die();
    
}

add_filter('template_include', 'bns_tracking_all');

function bns_tracking_all($template)
{
    
    //  echo $_GET[en_type];
    if (isset($_GET["en_type"]) && $_GET["en_type"] != "") {
        if (trim($_GET["en_type"]) == "email_new_unsubs") {
            if (isset($_GET["temp_id"]) && isset($_GET["email_id"]) && isset($_GET["s_id"])) {
                $type = "";
                if (isset($_GET['type']))
                    $type = sanitize_text_field($_GET['type']);
				$get_temp_id = filter_var($_GET['temp_id'],FILTER_SANITIZE_NUMBER_INT);
				$get_s_id =  sanitize_text_field($_GET['s_id']);
				$get_email =  sanitize_email($_GET['email_id']);
                bns_unsubscribe_track($get_temp_id, $get_s_id, $get_email, $type);
                $url = site_url() . '?en_type=email_new_resub&temp_id=' . $get_temp_id . '&email_id=' . $get_email . '&s_id=' . $get_s_id . '&type=yes';
				$url = filter_var($url,FILTER_SANITIZE_URL);
                $mg  = bns_en_track_msg_view("unsub", $url);
                echo $mg;
                exit();
                
            }
        }
        if (trim($_GET["en_type"]) == "email_new_open") {
            
            if (isset($_GET["temp_id"]) && isset($_GET["email_id"]) && isset($_GET["s_id"])) {
                $type = "";
                if (isset($_GET['type']))
                    $type = sanitize_text_field($_GET['type']);
                $get_temp_id = filter_var($_GET['temp_id'],FILTER_SANITIZE_NUMBER_INT);
				$get_s_id =  sanitize_text_field($_GET['s_id']);
				$get_email =  sanitize_email($_GET['email_id']);
				bns_open_track($get_temp_id, $get_email, $get_s_id, $type);
            }
        }
        if (trim($_GET["en_type"]) == "email_new_links") {
            if (isset($_GET["link_id"]) && isset($_GET["temp_id"]) && isset($_GET["email_id"]) && isset($_GET["s_id"])) {
                $type = "";
                if (isset($_GET['type']))
                    $type = sanitize_text_field($_GET['type']);
                $get_temp_id = filter_var($_GET['temp_id'],FILTER_SANITIZE_NUMBER_INT);
				$get_lid =  sanitize_text_field($_GET['link_id']);
				$get_s_id =  sanitize_text_field($_GET['s_id']);
				$get_email =  sanitize_email($_GET['email_id']);
                bns_link_click($get_temp_id, $get_lid, $get_email, $get_s_id, $type);
            }
        }
        if (trim($_GET["en_type"]) == "email_new_resub") {
            if (isset($_GET["temp_id"]) && isset($_GET["email_id"]) && isset($_GET["s_id"]) && isset($_GET['type'])) {
                 $get_temp_id = filter_var($_GET['temp_id'],FILTER_SANITIZE_NUMBER_INT);
				$get_s_id =  sanitize_text_field($_GET['s_id']);
				$get_email =  sanitize_email($_GET['email_id']);
				$get_type =  sanitize_text_field($_GET['type']);
                bns_unsubscribe_track($get_temp_id, $get_s_id, $get_email, $get_type);
                
                $url = site_url() . '?en_type=email_new_unsubs&temp_id=' . $get_temp_id . '&email_id=' . $get_email . '&s_id=' . $get_s_id . '&type=replace_drip_type';
				$url = filter_var($url,FILTER_SANITIZE_URL);
                $msg = bns_en_track_msg_view("resub", $url);
                echo $msg;
                exit();
            }
        }
        
    } else {
        return $template;
    }
}

function bns_open_track($temp_id, $email_id, $sid, $type)
{
    
    if (isset($temp_id) && $temp_id != '' && $temp_id != 'mailget_temp_id_replace' && isset($email_id) && $email_id != "" && trim($email_id) != 'mailget_email_id_replace' ) {
        $temp_id_enc              = $temp_id;
        $temp_id                  = $temp_id_enc;
        $email_id                 = trim($email_id);
        $s_id                     = $sid;
        $temp_type                = $type;
        $chk_data_arr             = array();
        $chk_data_arr['temp_id']  = $temp_id;
        $chk_data_arr['email_id'] = $email_id;
        $chk_data_arr['s_id']     = $s_id;
        $chk_data_arr['type']     = 'open';
        
        $chk_val = bns_counter_update_check($chk_data_arr, $temp_type);
        if ($chk_val == 'no') {
            $server_val = '';
            bns_update_open_email_in_list($temp_id, $email_id, $s_id, $server_val, $temp_type);
            
            $temp_id_chk = bns_get_template_id_check($temp_id);
            if ($temp_id_chk > 0) {
                bns_update_track_for_template_by_temp_id($temp_id, 'open');
            } else if ($temp_id_chk == 0) {
                bns_insert_track_for_template_by_temp_id($temp_id, 'open');
            }
            
        }
        
    }
}
function bns_get_link_track_data_from_db($temp_id, $s_id, $link_id)
{
    $s_id = trim($s_id);
    global $wpdb;
	$get_arr = $wpdb->get_results( $wpdb->prepare( "SELECT count, contacts FROM " . $wpdb->prefix . "bns_email_news_templ_track  WHERE sender_id = %s and link_enc_id = %s LIMIT 1", $s_id, $link_id ) );
    return $get_arr;
}

function bns_select_link_by_link_id($temp_id, $s_id, $link_id)
{
    global $wpdb;
	$get_arr = $wpdb->get_results( $wpdb->prepare( "SELECT links FROM " . $wpdb->prefix . "bns_email_news_templ  WHERE temp_id = %d and sender_id = %s LIMIT 1", $temp_id, $s_id ) );
    
    if (isset($get_arr[0]->links)) {
        $links_arr = json_decode(unserialize($get_arr[0]->links));
        
        if (isset($links_arr->$link_id)) {
            return $links_arr->$link_id;
        } else {
            return '';
        }
    } else {
        return '';
    }
}



function bns_link_click($temp_id, $link_id, $email_id, $s_id, $temp_type)
{
    if (trim($email_id) == 'mailget_email_id_replace') {
        $temp_id_enc   = $temp_id;
        $redirect_link = '';
        /* decryption start */
        
        $links_arr_json = bns_get_all_links_of_temp_id($temp_id);
        $links_arr      = json_decode(unserialize($links_arr_json));
        if (isset($links_arr)) {
            foreach ($links_arr as $all_link_arr_row) {
                $id    = $all_link_arr_row->id;
                $value = $all_link_arr_row->value;
                if (trim($id) == trim($link_id)) {
                    $redirect_link = (urldecode($value));
                    break;
                }
            }
            
            if ($redirect_link != '') {
                $redirect_link = bns_valid_redirect_url($redirect_link);
                header('Location:' . $redirect_link . '');
            }
            
        }
    } elseif (isset($temp_id) && $temp_id != 'mailget_temp_id_replace' && $email_id != "" && trim($email_id) != 'mailget_email_id_replace') {
        
        $temp_id_enc = $temp_id;
        $email_id    = trim($email_id);
        
        $temp_id_chk              = bns_get_template_id_check($temp_id);
        $chk_data_arr             = array();
        $chk_data_arr['temp_id']  = $temp_id;
        $chk_data_arr['email_id'] = $email_id;
        $chk_data_arr['s_id']     = $s_id;
        $chk_data_arr['type']     = 'link';
        $chk_data_arr['link_id']  = $link_id;
        
        $chk_val = bns_counter_update_check($chk_data_arr, $temp_type);
        
        if ($chk_val == 'no') {
            if ($temp_id_chk > 0) {
                bns_update_track_for_template_by_temp_id($temp_id, 'click');
            } else if ($temp_id_chk == 0) {
                bns_insert_track_for_template_by_temp_id($temp_id, 'click');
            }
            $link_track_data = bns_get_link_track_data_from_db($temp_id, $s_id, $link_id);
            
            if (isset($link_track_data[0]->count)) {
                $old_count           = $link_track_data[0]->count;
                $counter             = $old_count + 1;
                $emails_arr          = $link_track_data[0]->contacts;
                $emails_arr          = json_decode(unserialize($emails_arr));
                $new_open[$email_id] = array(
                    'email' => sanitize_email($email_id)
                );
                
                $new_open             = (array) $new_open;
                $emails_arr           = (array) $emails_arr;
                $list_email_arr_final = array_merge($emails_arr, $new_open);
                $list_email_arr_final = serialize(json_encode($list_email_arr_final));
                $track_arr            = array(
                    'count' => filter_var($counter, FILTER_SANITIZE_NUMBER_INT),
                    'contacts' => $list_email_arr_final
                );
                bns_update_link_track_data_from_db($temp_id, $s_id, $link_id, $track_arr);
                
            }
        }
        $get_link = bns_select_link_by_link_id($temp_id, $s_id, $link_id);
        $get_link = urldecode($get_link);
        $get_link = ($get_link);
        header('Location:' . $get_link . '');
    }
}

function bns_valid_redirect_url($redirect_link)
{
    $get_parse_url = parse_url($redirect_link);
    $scheme        = 'http';
    $path          = '';
    $query         = '';
    if (isset($get_parse_url["scheme"])) {
        if ($get_parse_url["scheme"] == 'http' || $get_parse_url["scheme"] == 'https') {
            $return_url = $redirect_link;
        } else {
            if (isset($get_parse_url['path'])) {
                $path = $get_parse_url['path'];
            }
            if (isset($get_parse_url['query'])) {
                $query = $get_parse_url['query'];
            }
            $return_url = $scheme . '://' . $get_parse_url['host'] . $path . '?' . $query;
        }
    } else {
        $return_url = $scheme . '://' . $redirect_link;
    }
    return $return_url;
}

function bns_update_link_track_data_from_db($temp_id, $s_id, $link_id, $track_arr)
{
    global $wpdb;
    $where   = array(
        'temp_id' => $temp_id,
        'sender_id' => $s_id,
        'link_enc_id' => $link_id
    );
    $get_arr = $wpdb->update($wpdb->prefix . 'bns_email_news_templ_track', $track_arr, $where);
}

function bns_counter_update_check($chk_data_arr, $temp_type)
{
    $temp_id  = '';
    $email_id = '';
    $s_id     = '';
    $type     = '';
    $link_id  = '';
    if (!empty($chk_data_arr)) {
        $temp_id  = isset($chk_data_arr['temp_id']) ? $chk_data_arr['temp_id'] : '';
        $email_id = isset($chk_data_arr['email_id']) ? $chk_data_arr['email_id'] : '';
        $s_id     = isset($chk_data_arr['s_id']) ? $chk_data_arr['s_id'] : '';
        $type     = isset($chk_data_arr['type']) ? $chk_data_arr['type'] : '';
        $link_id  = isset($chk_data_arr['link_id']) ? $chk_data_arr['link_id'] : '';
    }
    $res = 'no';
    if ($type != '') {
        switch ($type) {
            case "open":
                if ($temp_id != '' && $email_id != '' && $s_id != '') {
                    $email_id       = trim($email_id);
                    $open_email_arr = bns_select_email_id_opens_track($temp_id, $s_id);
                    
                    $open_email_arr             = (array) $open_email_arr;
                    $count_previous_ele_in_list = count($open_email_arr);
                    if ($count_previous_ele_in_list > 0) {
                        if (array_key_exists($email_id, $open_email_arr)) {
                            $res = 'yes';
                        }
                    }
                }
                break;
            case "link":
                if ($temp_id != '' && $email_id != '' && $s_id != '' && $link_id != '') {
                    
                    $link_track_data = bns_get_link_track_data_from_db($temp_id, $s_id, $link_id);
                    
                    if (isset($link_track_data[0]->count)) {
                        $old_count = $link_track_data[0]->count;
                        if ($old_count > 0) {
                            $emails_arr = $link_track_data[0]->contacts;
                            $emails_arr = json_decode(unserialize($emails_arr));
                            $emails_arr = (array) $emails_arr;
                        }
                        if (!empty($emails_arr) && array_key_exists($email_id, $emails_arr)) {
                            $res = 'yes';
                        }
                    }
                }
                break;
        }
        return $res;
    }
}

function bns_select_email_id_opens_track($temp_id, $s_id)
{
    global $wpdb;
	$get_arr = $wpdb->get_results( $wpdb->prepare( "SELECT contacts FROM " . $wpdb->prefix . "bns_email_news_open_track  WHERE temp_id = %d and sender_id = %s LIMIT 1", $temp_id, $s_id ) );
    if (isset($get_arr[0]->contacts)) {
        $suspended_list = $get_arr[0]->contacts;
        $suspended_list = json_decode(unserialize($suspended_list));
    } else {
        $suspended_list = array();
    }
    return $suspended_list;
}

function bns_update_open_email_in_list($temp_id, $email_id, $s_id, $server = '', $temp_type = '')
{
    global $wpdb;
    $email_id       = trim($email_id);
    $open_email_arr = bns_select_email_id_opens_track($temp_id, $s_id);
    
    $open_email_arr = (array) $open_email_arr;
    
    $count_previous_ele_in_list = count($open_email_arr);
    
    $new_opens[$email_id] = array(
        'email' => sanitize_email($email_id),
        'date' => date("Y-m-d H:i:s")
    );
    
    $new_opens      = (array) $new_opens;
    $open_email_arr = array_merge($open_email_arr, $new_opens);
    $open_email_arr = serialize(json_encode($open_email_arr));
    
    $json_get_email_arr      = json_decode(unserialize($open_email_arr));
    $json_get_email_arr      = (array) $json_get_email_arr;
    $count_total_ele_in_list = count($json_get_email_arr);
    
    if ($count_previous_ele_in_list < $count_total_ele_in_list) {
        
        $arr_track_email = array(
            'contacts' => $open_email_arr
        );
        
        $wpdb->update($wpdb->prefix . "bns_email_news_open_track", $arr_track_email, array(
            "sender_id" => $s_id,
            "temp_id" => $temp_id
        ));
        
        
    }
}



function bns_confirm_send()
{
    global $wpdb;
    $arr      = array();
    $temp_id  = filter_var($_POST['temp_id'],FILTER_SANITIZE_NUMBER_INT);
    $list_arr = $_POST['list_id'];
	
    if (isset($temp_id) && $temp_id != "" && isset($list_arr) && !empty($list_arr)) {
        $temp            = get_post($temp_id);
        $temp_sub        = $temp->post_title;
        $arr["sub"]      = $temp_sub;
        $list_arr_filter = implode(',', $list_arr);
        $cnt             = 0;
        foreach ($list_arr as $list_id) {
            $list_id = filter_var($list_id, FILTER_SANITIZE_NUMBER_INT);
            $email_list = bns_get_emails_from_list($list_id);
            if (!empty($email_list)) {
                $email_list = json_decode(unserialize($email_list));
                $email_list = (array) $email_list;
                $email_list = bns_exclude_unsubscriber_list($email_list);
                $cnt        = $cnt + count($email_list);
            }
            $email_list_ext = bns_get_extra_emails_from_list($list_id);
            if (!empty($email_list_ext)) {
                $email_list_ext = json_decode(unserialize($email_list_ext));
                $email_list_ext = (array) $email_list_ext;
                $email_list_ext = bns_exclude_unsubscriber_list($email_list_ext);
                $cnt            = $cnt + count($email_list_ext);
            }
        }
        $arr["total"] = $cnt;
		
		$res = $wpdb->get_results( $wpdb->prepare( "SELECT list_name FROM " . $wpdb->prefix . "bns_email_news_list  WHERE list_id in(". str_repeat("%d,", count($list_arr_filter)-1) . "%d)", $list_arr_filter ) );
    
        if (isset($res) && !empty($res)) {
            $arr["list_info"] = $res;
            
        }
        print_r(json_encode($arr));
    } else {
        echo "no data";
    }
    die();
}

function bns_rand_str($length = 4)
{
    $characters   = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
    $randomString = '';
    for ($i = 0; $i < $length; $i++) {
        $randomString .= $characters[rand(0, strlen($characters) - 1)];
    }
    return $randomString;
}

function bns_send_final()
{
    $l_arr               = $_POST["list_id"];
    $temp_id             = filter_var($_POST['temp_id'],FILTER_SANITIZE_NUMBER_INT);
    $total_email_count   = 0;
    $sending_email_extra = 0;
    $max_email_in_list   = 50000;
    $p_data              = get_posts($temp_id);
    $subject             = $p_data->post_title;
    $rand_str            = bns_rand_str(6);
    $send_list_status    = true;
    
    $email_list       = array();
    $email_list_merge = array();
    $decode_arr       = array();
    
    //$email_list_merge = array_merge($email_list_merge, $individual_arr);
    
    $decode_arr = $l_arr;
    
    if (isset($decode_arr) && !empty($decode_arr)) {
        foreach ($decode_arr as $decode_arr_id) {
			$decode_arr_id      = filter_var($decode_arr_id,FILTER_SANITIZE_NUMBER_INT);
            $email_list         = bns_get_list_emails($decode_arr_id);
            $email_list         = json_decode(unserialize($email_list));
            $email_list         = (array) $email_list;
            $email_list         = bns_exclude_unsubscriber_list($email_list);
            $email_list         = array_diff_key($email_list, $email_list_merge);
            $size_of_merge_list = sizeof($email_list_merge);
            $size_of_email_list = sizeof($email_list);
            if (($size_of_merge_list + $size_of_email_list) <= $max_email_in_list) {
                $email_list_merge = array_merge($email_list_merge, $email_list);
            } else {
                while (($size_of_email_list + $size_of_merge_list) > $max_email_in_list) {
                    $slice             = $max_email_in_list - $size_of_merge_list;
                    $slice_email       = array_slice($email_list, 0, $slice);
                    $email_list        = array_slice($email_list, $slice);
                    $email_list_merge  = array_merge($email_list_merge, $slice_email);
                    $total_email_count = $total_email_count + sizeof($email_list_merge);
                    
                    
                    if ($send_list_status) {
                        
                        //bns_multiple_send_list_for_compose($email_list_merge,$temp_id, $rand_str, $sending_email_extra);
                        $sending_email_extra++;
                    }
                    $email_list_merge   = array();
                    $size_of_email_list = sizeof($email_list);
                    $size_of_merge_list = sizeof($email_list_merge);
                }
                if ($size_of_email_list > 0) {
                    $email_list_merge = array_merge($email_list_merge, $email_list);
                }
            }
            // builder_list_extra start 
            $extra_rows_exist = bns_email_new_check_list_extra($decode_arr_id);
            if ($extra_rows_exist > 0) {
                $extra_id_arr = get_extra_emails_id_by_list_id($decode_arr_id);
                if (isset($extra_id_arr) && !empty($extra_id_arr)) {
                    foreach ($extra_id_arr as $extra_id) {
                        
                        $id               = $extra_id->id;
                        $extra_email_list = bns_get_email_new_list_extra($id, $decode_arr_id);
                        $email_list       = $extra_email_list[0]->emails;
                        $email_list       = json_decode(unserialize($email_list));
                        $email_list       = (array) $email_list;
                        
                        
                        $email_list         = array_diff_key($email_list, $email_list_merge);
                        $size_of_merge_list = sizeof($email_list_merge);
                        $size_of_email_list = sizeof($email_list);
                        if (($size_of_merge_list + $size_of_email_list) <= $max_email_in_list) {
                            $email_list_merge = array_merge($email_list_merge, $email_list);
                        } else {
                            while (($size_of_email_list + $size_of_merge_list) > $max_email_in_list) {
                                $slice             = $max_email_in_list - $size_of_merge_list;
                                $slice_email       = array_slice($email_list, 0, $slice);
                                $email_list        = array_slice($email_list, $slice);
                                $email_list_merge  = array_merge($email_list_merge, $slice_email);
                                $total_email_count = $total_email_count + sizeof($email_list_merge);
                                
                                
                                if ($send_list_status) {
                                    bns_multiple_send_list_for_compose($email_list_merge, $temp_id_enc, $rand_str, $sending_email_extra);
                                    $sending_email_extra++;
                                }
                                $email_list_merge   = array();
                                $size_of_email_list = sizeof($email_list);
                                $size_of_merge_list = sizeof($email_list_merge);
                            }
                            if ($size_of_email_list > 0) {
                                $email_list_merge = array_merge($email_list_merge, $email_list);
                            }
                        }
                    }
                }
            }
            // builder_list_extra end 
        }
        if (sizeof($email_list_merge) > 0) {
            $total_email_count = $total_email_count + sizeof($email_list_merge);
            
            if ($send_list_status) {
                
                bns_multiple_send_list_for_compose($email_list_merge, $temp_id, $rand_str, $sending_email_extra);
                $sending_email_extra++;
            }
        }
    } else if (isset($email_list_merge) && !empty($email_list_merge)) {
        bns_multiple_send_list_for_compose($email_list_merge, $temp_id_enc, $rand_str, $sending_email_extra);
    }
    bns_final_send_list_for_compose($total_email_count, $subject, $temp_id, $rand_str);
    die();
}

function bns_exclude_unsubscriber_list($email_list)
{
    global $wpdb;
	$unsub = $wpdb->get_results("SELECT contacts FROM " . $wpdb->prefix . "bns_email_news_unsubscribe_track");
 
    if (isset($unsub) && !empty($unsub)) {
        foreach ($unsub as $data) {
            $sus_list_email_arr = json_decode(unserialize($data->contacts));
            $sus_list_email_arr = (array) $sus_list_email_arr;
            if (!empty($sus_list_email_arr)) {
                $email_list = array_diff_key($email_list, $sus_list_email_arr);
            }
        }
    }
    
    return $email_list;
}

function bns_multiple_send_list_for_compose($email_list_merge, $temp_id_enc, $rand_str, $sending_email_extra)
{
    $all_email_count = sizeof($email_list_merge);
    $email_send      = serialize(json_encode($email_list_merge));
    // decryption start 
    $temp_id         = ($temp_id_enc);
    $userip          = bns_get_client_ip();
    $email_send      = serialize(json_encode($email_list_merge));
    
    $email_insert_arr_old = array(
        'temp_id' => filter_var($temp_id, FILTER_SANITIZE_NUMBER_INT),
        'sender_id' => sanitize_text_field($rand_str),
        'ip_address' => sanitize_text_field($userip),
        'contacts' => $email_send,
        'count' => filter_var($all_email_count, FILTER_SANITIZE_NUMBER_INT),
        'date' => date('Y-m-d H:i:s')
    );
	 $email_insert_arr = array(
        filter_var($temp_id, FILTER_SANITIZE_NUMBER_INT),
        sanitize_text_field($rand_str),
        sanitize_text_field($userip),
        $email_send,
        filter_var($all_email_count, FILTER_SANITIZE_NUMBER_INT),
        date('Y-m-d H:i:s')
    );
    
    
    if ($sending_email_extra == 0) {
        bns_insert_sending_email_list($email_insert_arr);
        
        $send_id = bns_select_sending_email_list();
    } else {
        
        $send_id                     = bns_select_sending_email_list();
     
		  $email_insert_arr[] = $send_id;
         $email_insert_arr[]    = $sending_email_extra;
        bns_insert_sending_email_extra_list($email_insert_arr);
        
    }
    // routing table code start 
    
    $list_slots_arr = array(
        'send_id' => filter_var($send_id, FILTER_SANITIZE_NUMBER_INT),
        'template_id' => filter_var($temp_id, FILTER_SANITIZE_NUMBER_INT),
        'sender_id' => sanitize_text_field($rand_str),
        'contacts' => $email_send,
        'count' => filter_var($all_email_count, FILTER_SANITIZE_NUMBER_INT)
    );
	 
    bns_insert_sending_email_list_slots($list_slots_arr, $sending_email_extra);
    
}



function bns_insert_sending_email_list($arr)
{
    global $wpdb;
	$wpdb->query( $wpdb->prepare( 
	"
		INSERT INTO ". $wpdb->prefix . "bns_email_new_sending
		(temp_id, sender_id, ip_address, contacts, count, date )
		VALUES ( %d, %s, %s, %s, %d, %s )
	", 
       $arr
) );
    //$wpdb->insert($wpdb->prefix . "email_new_sending", $arr);
    
}

function bns_insert_sending_email_extra_list($arr)
{
    global $wpdb;
	$wpdb->query( $wpdb->prepare( 
	"
		INSERT INTO ". $wpdb->prefix . "bns_email_new_sending
		(temp_id, sender_id, ip_address, contacts, count, date, send_id, slot )
		VALUES ( %d, %s, %s, %s, %d, %s, %d, %d )
	", 
       $arr
) );
    //$wpdb->insert($wpdb->prefix . "email_new_sending_extra", $arr);
    
}
function bns_select_sending_email_list()
{
    global $wpdb;
	
    $res = $wpdb->get_results("SELECT send_id from " . $wpdb->prefix . "bns_email_new_sending order by send_id desc limit 1");
    if (isset($res[0]->send_id) && $res[0]->send_id != "") {
        return $res[0]->send_id;
    } else {
        return 0;
    }
    
}

function bns_select_latest_sending_email_list($sender_id, $template_id)
{
    $result = '';
    global $wpdb;
	$res = $wpdb->get_results( $wpdb->prepare( "SELECT send_id FROM " . $wpdb->prefix . "bns_email_new_sending  WHERE temp_id = %d and sender_id = %s order by send_id desc limit 1", $template_id, $sender_id ) );
    
    if (isset($res[0]->send_id)) {
        $result = $res[0]->send_id;
    }
    
    
    return $result;
}

function bns_insert_template_email_unsubscribe_track_array($temp_id, $rand_str)
{
    global $wpdb;
    $unsub_email_list = array();
    $unsub_email_list = serialize(json_encode($unsub_email_list));
    $un_subs_arr      = array(
        filter_var($temp_id, FILTER_SANITIZE_NUMBER_INT),
        sanitize_text_field($rand_str),
        $unsub_email_list,
        date('Y-m-d H:i:s')
    );
	$wpdb->query( $wpdb->prepare( 
	"
		INSERT INTO ". $wpdb->prefix . "bns_email_news_unsubscribe_track
		( temp_id, sender_id, contacts, date )
		VALUES ( %d, %s, %s, %s )
	", 
       $un_subs_arr
) );
        
}
function bns_insert_template_email_open_track_array($temp_id, $rand_str)
{
    global $wpdb;
    $open_email_list = array();
    $open_email_list = serialize(json_encode($open_email_list));
    $arr_track_email = array(
         filter_var($temp_id, FILTER_SANITIZE_NUMBER_INT),
         sanitize_text_field($rand_str),
         $open_email_list,
         date('Y-m-d H:i:s')
    );
	$wpdb->query( $wpdb->prepare( 
	"
		INSERT INTO ". $wpdb->prefix . "bns_email_news_open_track
		( temp_id, sender_id, contacts, date )
		VALUES ( %d, %s, %s, %s )
	", 
       $arr_track_email
) );
   
}


function bns_get_all_links_of_temp_id($temp_id)
{
    global $wpdb;
	
	$res = $wpdb->get_results( $wpdb->prepare( "SELECT temp_link FROM " . $wpdb->prefix . "bns_email_new_temp_link WHERE temp_id = %d", $temp_id ) );
    if (isset($res[0]->temp_link) && !empty($res[0]->temp_link)) {
        return $res[0]->temp_link;
        
    }
}
function bns_insert_links_tracking_rows($temp_id, $rand_str)
{
    global $wpdb;
    $arr_links_get = array();
    
    $links_arr_json = bns_get_all_links_of_temp_id($temp_id);
    $links_arr      = json_decode(unserialize($links_arr_json));
    if (isset($links_arr)) {
        foreach ($links_arr as $all_link_arr_row) {
            $id                 = $all_link_arr_row->id;
            $value              = $all_link_arr_row->value;
            $arr_links_get[$id] = $value;
        }
        $arr_links       = serialize(json_encode($arr_links_get));
        $link_arr_insert = array(
            filter_var($temp_id, FILTER_SANITIZE_NUMBER_INT),
            sanitize_text_field($rand_str),
            $arr_links,
            date('Y-m-d H:i:s')
        );
		
		$wpdb->query( $wpdb->prepare( 
				"
					INSERT INTO ". $wpdb->prefix . "bns_email_news_templ
					( temp_id, sender_id, links, date )
					VALUES ( %d, %s, %s, %s )
				", 
				   $link_arr_insert
			) );
      
        $email_arr = array();
        $email_arr = serialize(json_encode($email_arr));
        $old_count = 0;
        $count     = 0;
        $count     = $old_count + $count;
        
        foreach ($arr_links_get as $row_link_k => $row_link_v) {
            $l_id = trim($row_link_k);
            
            $link_id_click_track_insert = array(
                filter_var($temp_id, FILTER_SANITIZE_NUMBER_INT),
                sanitize_text_field($rand_str),
                sanitize_text_field($l_id),
                $email_arr,
                0,
                date('Y-m-d H:i:s')
            );
			$wpdb->query( $wpdb->prepare( 
				"
					INSERT INTO ". $wpdb->prefix . "bns_email_news_templ_track
					( temp_id, sender_id, link_enc_id, contacts, count, date )
					VALUES ( %d, %s, %s, %s, %d, %s )
				", 
				   $link_id_click_track_insert
			) );
			
                       
        }
    }
}

function bns_get_template_id_check($temp_id)
{
    global $wpdb;
    $res = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "bns_email_new_temp_all_track WHERE temp_id = %d", $temp_id ) );
    
    if (isset($res[0]->temp_id) && $res[0]->temp_id != "") {
        return 1;
    } else {
        return 0;
    }
    
}
function bns_get_template_track_for_update($temp_id, $track_col)
{
    global $wpdb;
	$res = $wpdb->get_results( $wpdb->prepare( "SELECT $track_col FROM " . $wpdb->prefix . "bns_email_new_temp_all_track WHERE temp_id = %d LIMIT 1", $temp_id ) );
    
    if (isset($res[0]->$track_col)) {
        
        return $res;
    }
}
function bns_update_template_track_alltime($temp_id, $track_array)
{
    
    global $wpdb;
    $res = $wpdb->update($wpdb->prefix . "bns_email_new_temp_all_track", $track_array, array(
        'temp_id' => $temp_id
    ));
}
function bns_update_track_for_template_by_temp_id($temp_id, $track_up_col, $counter = 1)
{
    
    $track_up_col_get = bns_get_template_track_for_update($temp_id, $track_up_col);
    
    $track_up_col_get = ($track_up_col_get[0]->$track_up_col) + intval($counter);
    $track_array      = array(
        $track_up_col => filter_var($track_up_col_get, FILTER_SANITIZE_NUMBER_INT)
    );
    bns_update_template_track_alltime($temp_id, $track_array);
}
function bns_insert_track_for_template_by_temp_id($temp_id, $track_up_col, $counter = 1)
{
    global $wpdb;
    
	$wpdb->query( $wpdb->prepare( 
				"
					INSERT INTO ". $wpdb->prefix . "bns_email_new_temp_all_track
					( temp_id, sent, click, unsub, open )
					VALUES ( %d, %d, %d, %d, %d )
				", 
				   array($temp_id, 0, 0, 0, 0)
			) );
			
   
    bns_update_track_for_template_by_temp_id($temp_id, $track_up_col, $counter);
}
function bns_sent_track($temp_id, $all_email_count)
{
    if (isset($temp_id) && $temp_id != '') {
        $temp_id_chk = bns_get_template_id_check($temp_id);
        if ($temp_id_chk > 0) {
            bns_update_track_for_template_by_temp_id($temp_id, 'sent', $all_email_count);
        } else if ($temp_id_chk == 0) {
            bns_insert_track_for_template_by_temp_id($temp_id, 'sent', $all_email_count);
        }
    }
}
function bns_final_send_list_for_compose($all_email_count, $subject, $temp_id_enc, $rand_str)
{
    $temp_id = ($temp_id_enc);
    
    $send_id = bns_select_latest_sending_email_list($rand_str, $temp_id);
    
    
    // template email tracking  
    bns_insert_template_email_unsubscribe_track_array($temp_id, $rand_str);
    bns_insert_template_email_open_track_array($temp_id, $rand_str);
    bns_insert_links_tracking_rows($temp_id, $rand_str);
    bns_sent_track($temp_id, $all_email_count);
    
    $sent_list_arr = array(
        'temp_id' => $temp_id,
        'sender_id' => $rand_str,
        'sent_list' => $included_list,
        'date' => date('Y-m-d H:i:s')
    );
    
    
    $max_limit = 50000;
    if ($all_email_count > 0) {
	       bns_send_email_multithread($send_id, $max_limit, $temp_id_enc, $rand_str);
        
    }
}



function bns_get_list_emails($list_id)
{
    global $wpdb;
	$res = $wpdb->get_results( $wpdb->prepare( "SELECT contacts FROM " . $wpdb->prefix . "bns_email_news_list WHERE list_id = %d", $list_id ) );
    if (isset($res[0]->contacts) && !empty($res[0]->contacts))
        return $res[0]->contacts;
    else
        return 0;
}

function bns_email_new_check_list_extra($list_id)
{
    global $wpdb;
	$res = $wpdb->get_results( $wpdb->prepare( "SELECT list_id FROM " . $wpdb->prefix . "bns_email_news_list_extra WHERE list_id = %d", $list_id ) );
    return $wpdb->num_rows;
}
function bns_get_email_new_list_extra($list_id)
{
    global $wpdb;
	$res = $wpdb->get_results( $wpdb->prepare( "SELECT contacts FROM " . $wpdb->prefix . "bns_email_news_list_extra WHERE list_id = %d", $list_id ) );
    return $res;
}

function bns_get_latest_index_of_sending_email_slot($send_id, $template_id, $sender_id)
{
    global $wpdb;
	$res = $wpdb->get_results( $wpdb->prepare( "SELECT s_index FROM " . $wpdb->prefix . "bns_email_news_sending_slots WHERE send_id = %d and temp_id = %d and sender_id = %s LIMIT 1", $send_id, $template_id, $sender_id ) );
    
    if (isset($res[0]->s_index)) {
        $slot_index = $res[0]->s_index;
    } else {
        $slot_index = 0;
    }
    return $slot_index;
}

function bns_insert_sending_email_list_slots($list_slots_arr, $sending_email_extra = 0)
{
    global $wpdb;
    if (!empty($list_slots_arr)) {
        $send_id       = $list_slots_arr['send_id'];
        $template_id   = $list_slots_arr['template_id'];
        $sender_id     = $list_slots_arr['sender_id'];
        $email_list    = $list_slots_arr['contacts'];
        $count         = intval($list_slots_arr['count']);
        $initial_index = 0;
        $email_arr     = json_decode(unserialize($email_list));
        $email_arr     = (array) $email_arr;
        $email_arr     = array_values($email_arr);
        $start_index   = 0;
        $list_size     = 5000;
        if ($count < 10000) {
            $list_size = 1000;
        } else if ($count < 20000) {
            $list_size = 2000;
        } else if ($count < 30000) {
            $list_size = 3000;
        } else if ($count < 40000) {
            $list_size = 4000;
        }
        if ($count > 0) {
            $initial_index = bns_get_latest_index_of_sending_email_slot($send_id, $template_id, $sender_id);
            if (($count % $list_size) > 0) {
                $loop_length = intval($count / $list_size) + 1;
            } else {
                $loop_length = intval($count / $list_size);
            }
            $loop_length = $loop_length + $initial_index;
            for ($i = $initial_index; $i < $loop_length; $i++) {
                $arr_get      = array_slice($email_arr, $start_index, $list_size);
                $start_index  = $start_index + $list_size;
                $arr_get_size = sizeof($arr_get);
                if ($arr_get_size > 0) {
                    $arr_get_json = serialize(json_encode($arr_get));
                    $insert_arr_old  = array(
                        'send_id' => filter_var($send_id, FILTER_SANITIZE_NUMBER_INT),
                        'temp_id' => filter_var($template_id, FILTER_SANITIZE_NUMBER_INT),
                        'sender_id' => sanitize_text_field($sender_id),
                        'contacts' => $arr_get_json,
                        'count' => filter_var($arr_get_size, FILTER_SANITIZE_NUMBER_INT),
                        's_index' => $i + 1,
                        'status' => 0,
                        'date' => date('Y-m-d H:i:s')
                    );
					 $insert_arr   = array(
                        filter_var($send_id, FILTER_SANITIZE_NUMBER_INT),
                        filter_var($template_id, FILTER_SANITIZE_NUMBER_INT),
                        sanitize_text_field($sender_id),
                        $arr_get_json,
                        filter_var($arr_get_size, FILTER_SANITIZE_NUMBER_INT),
                        $i + 1,
                        0,
                        date('Y-m-d H:i:s')
                    );
				    $wpdb->query( $wpdb->prepare( 
						"
							INSERT INTO ". $wpdb->prefix . "bns_email_news_sending_slots
							(send_id, temp_id, sender_id, contacts, count, s_index, status, date )
							VALUES ( %d, %d, %s, %s, %d, %d, %d, %s )
						", 
						   $insert_arr
						) );
                   // $wpdb->insert($wpdb->prefix . 'email_news_sending_slots', $insert_arr);
                }
            }
            
        }
    }
}

function bns_select_email_sending_list_slot($send_id)
{
    global $wpdb;
	$res = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "bns_email_news_sending_slots WHERE send_id = %d and status = %d", $send_id, 0 ) );
    
    return $res;
}
function bns_select_sending_email_list_by_slot_id($slot_id)
{
    global $wpdb;
	$res = $wpdb->get_results( $wpdb->prepare( "SELECT contacts FROM " . $wpdb->prefix . "bns_email_news_sending_slots WHERE send_id = %d LIMIT 1", $slot_id ) );
    if (isset($res[0]->contacts)) {
        return $res[0]->contacts;
    }
    //print_r($res[0]->email_list);
    
}
function bns_update_sending_email_list_slot_status($slot_id, $status_val)
{
    global $wpdb;
    $update_arr = array(
        "status" => filter_var($status_val, FILTER_SANITIZE_NUMBER_INT)
    );
    $wpdb->update($wpdb->prefix . 'bns_email_news_sending_slots', $update_arr, array(
        "send_id" => $slot_id
    ));
}
function bns_get_name_arr($name)
{
    $firstname       = '';
    $lastname        = '';
    $name_return_arr = array();
    $name            = ucwords(strtolower($name));
    $name_arr        = explode(' ', $name);
    if (isset($name_arr[0]) && $name_arr[0] != '') {
        $firstname     = $name_arr[0];
        $name_arr_size = sizeof($name_arr);
        $last_index    = $name_arr_size - 1;
        if ($last_index >= 1) {
            $lastname = $name_arr[$last_index];
        }
    } else {
        $firstname = $name;
    }
    $name_return_arr['name']      = $name;
    $name_return_arr['firstname'] = $firstname;
    $name_return_arr['lastname']  = $lastname;
    return $name_return_arr;
}

function bns_send_email_multithread($send_id, $max_limit, $temp_id, $rand_str)
{
	$select_slots = bns_select_email_sending_list_slot($send_id);
	if (!empty($select_slots)) {
        foreach ($select_slots as $select_slots_row) {
            $slot_id       = $select_slots_row->send_id;
            $slot_index    = $select_slots_row->s_index;
            $status        = $select_slots_row->status;
            $default_val   = 'default';
            $get_email_arr = bns_select_sending_email_list_by_slot_id($slot_id);
            
            $email_arr    = json_decode(unserialize($get_email_arr));
            $arr_max_size = intval(sizeof($email_arr));
            
            if ($arr_max_size > 0) {
                
                $get_temp_html1 = get_post($temp_id);
                $get_temp_html  = $get_temp_html1->post_content;
                $get_subject    = $get_temp_html1->post_title;
                
                $mailget_arr_value_static  = array(
                    "mailget_temp_id_replace",
                    "mailget_s_id_replace"
                );
                $mailget_arr_value_dynamic = array(
                    "mailget_email_id_replace",
                    "{MG_NAME}",
                    "{name}",
                    "{firstname}",
                    "{lastname}"
                );
                $mailget_arr_replace       = array(
                    $temp_id,
                    $rand_str
                );
                $get_temp_html             = str_replace($mailget_arr_value_static, $mailget_arr_replace, $get_temp_html);
                $arr_get                   = $email_arr;
                if (!empty($arr_get)) {
                    bns_update_sending_email_list_slot_status($slot_id, 1);
                    for ($i = 0; $i < $arr_max_size; $i++) {
                      
                        if (isset($arr_get[$i]->email)) {
                        
						$to = trim($arr_get[$i]->email);
                            
                            $name         = ucfirst(strtolower(trim($arr_get[$i]->name)));
                            $get_name_arr = bns_get_name_arr($name);
                            $fullname     = $get_name_arr['name'];
                            $firstname    = $get_name_arr['firstname'];
                            $lastname     = $get_name_arr['lastname'];
                            
                            $mailget_arr_replace_d = array(
                                $to,
                                $name,
                                $fullname,
                                $firstname,
                                $lastname
                            );
                            $subject               = str_replace($mailget_arr_value_dynamic, $mailget_arr_replace_d, $get_subject);
                            $body                  = str_replace($mailget_arr_value_dynamic, $mailget_arr_replace_d, $get_temp_html);
                            $headers               = array(
                                'Content-Type: text/html; charset=UTF-8'
                            );
                            add_filter('wp_mail_content_type', create_function('', 'return "text/html"; '));
                            wp_mail($to, $subject, $body, $headers);
                        }
                        bns_update_sending_email_list_slot_status($slot_id, 2);
                    }
                }
            }
        }
        
    }
}


function bns_get_unsubs_template_list_arr($temp_id, $s_id)
{
    $get_res_email = array();
    global $wpdb;
	$get_res = $wpdb->get_results( $wpdb->prepare( "SELECT contacts FROM " . $wpdb->prefix . "bns_email_news_unsubscribe_track WHERE temp_id = %d and sender_id = %s LIMIT 1", $temp_id, $s_id ) );
    if (isset($get_res[0]->contacts)) {
        $get_res_email = $get_res[0]->contacts;
    } else {
        $get_res_email = '';
    }
    return $get_res_email;
}


function bns_add_email_to_unsubs_by_template($temp_id, $get_email, $s_id, $temp_type = '')
{
    global $wpdb;
    $get_email      = trim($get_email);
    $list_email     = bns_get_unsubs_template_list_arr($temp_id, $s_id);
    $list_email_arr = '';
    if ($list_email != '') {
        $list_email_arr = json_decode(unserialize($list_email));
    }
    $new_blocked[$get_email] = array(
        'email' => sanitize_email($get_email)
    );
    
    $list_email_arr = (array) $list_email_arr;
    if ((in_array($get_email, $list_email_arr))) {
        
        return 'no';
    } else {
        
        $new_blocked          = (array) $new_blocked;
        $list_email_arr_final = array_merge($list_email_arr, $new_blocked);
        $list_email_arr_final = serialize(json_encode($list_email_arr_final));
        $email_count          = sizeof($list_email_arr_final);
        $sus_email_arr        = array(
            'contacts' => $list_email_arr_final
        );
        $wpdb->update($wpdb->prefix . "bns_email_news_unsubscribe_track", $sus_email_arr, array(
            "sender_id" => $s_id
        ));
        
        return 'yes';
        
    }
}

function bns_remove_email_from_unsubscribe_data($temp_id, $s_id, $get_email)
{
    global $wpdb;
    $get_email      = trim($get_email);
    $list_email     = bns_get_unsubs_template_list_arr($temp_id, $s_id);
    $list_email_arr = json_decode(unserialize($list_email));
    $list_email_arr = (array) $list_email_arr;
    if (array_key_exists($get_email, $list_email_arr)) {
        unset($list_email_arr[$get_email]);
        $email_count           = sizeof($list_email_arr);
        $list_email_arr_final  = serialize(json_encode($list_email_arr));
        $unsubscribe_email_arr = array(
            'contacts' => $list_email_arr_final
        );
        
        $wpdb->update($wpdb->prefix . "bns_email_news_unsubscribe_track", $unsubscribe_email_arr, array(
            "sender_id" => $s_id,
            "temp_id" => $temp_id
        ));
        
        return 'yes';
    } else {
        return 'no';
    }
    
    
}

function bns_unsubscribe_track($temp_id, $s_id, $email_id, $temp_type)
{
    if (isset($temp_id) && $temp_id != 'mailget_temp_id_replace' && $email_id != "" && trim($email_id) != 'mailget_email_id_replace' && $s_id != 'mailget_s_id_replace') {
        $temp_id_enc = $temp_id;
        $get_email   = trim($email_id);
        $temp_id_chk = bns_get_template_id_check($temp_id);
        
        if (isset($temp_type) && trim($temp_type) == 'yes') {
            
            $check_delete = bns_remove_email_from_unsubscribe_data($temp_id, $s_id, $get_email);
            if ($check_delete == 'yes') {
                bns_update_track_for_template_by_temp_id($temp_id, 'unsub', -1);
            }
            
        } else {
            $check_insertion = bns_add_email_to_unsubs_by_template($temp_id, $get_email, $s_id);
            
            if ($check_insertion == 'yes') {
                if ($temp_id_chk > 0) {
                    bns_update_track_for_template_by_temp_id($temp_id, 'unsub');
                } else if ($temp_id_chk == 0) {
                    bns_insert_track_for_template_by_temp_id($temp_id, 'unsub');
                }
                
            }
            
        }
    }
}

function bns_save_smtp_db()
{
    $data_arr = array();
    if (isset($_POST["f_email"])) {
        $data_arr['from_email'] = sanitize_email($_POST['f_email']);
    }
    
    if (isset($_POST['f_name'])) {
        $data_arr['from_name'] = sanitize_text_field($_POST['f_name']);
    }
    if (isset($_POST['mail'])) {
        $data_arr['mailer'] = sanitize_text_field($_POST['mail']);
    }
    if (isset($_POST['return_path'])) {
        $data_arr['mail_set_return_path'] = sanitize_text_field($_POST['return_path']);
    }
    if (isset($_POST['host'])) {
        $data_arr['smtp_host'] = sanitize_text_field($_POST['host']);
    }
    if (isset($_POST['port'])) {
        $data_arr['smtp_port'] = filter_var($_POST['port'], FILTER_SANITIZE_NUMBER_INT);
    }
    if (isset($_POST['encrypt'])) {
        $data_arr['smtp_encryption'] = sanitize_text_field($_POST['encrypt']);
    }
    if (isset($_POST['auth'])) {
        $data_arr['smtp_authentication'] = sanitize_text_field($_POST['auth']);
    }
    if (isset($_POST['uname'])) {
        $data_arr['smtp_username'] = sanitize_text_field($_POST['uname']);
    }
    if (isset($_POST['upass'])) {
        $data_arr['smtp_password'] = sanitize_text_field($_POST['upass']);
    }
    $data = update_option("email_news_smtp_options", $data_arr);
    die();
}

function bns_update_name_sub()
{
    if (!check_ajax_referer('email_news_option_nonce', 'email_news_option_nonce') && !is_user_logged_in() && !current_user_can('manage_options')) {
        return;
    }
    $cnt = 0;
    global $wpdb;
    if (isset($_POST['f_name']) && $_POST['f_name'] != "") {
        $options = get_option("email_news_smtp_options");
        if (isset($options["from_name"]))
            $options["from_name"] = sanitize_text_field($_POST["f_name"]);
        
        update_option("email_news_smtp_options", $options);
        $cnt++;
    }
    
    if (isset($_POST['f_sub']) && $_POST['f_sub'] != "" && isset($_POST["temp_id"]) && $_POST["temp_id"] != "") {
        $temp_id  = filter_var($_POST['temp_id'], FILTER_SANITIZE_NUMBER_INT);
        $post_sub = sanitize_text_field($_POST['f_sub']);
        $my_post  = array(
            'ID' => $temp_id,
            'post_title' => $post_sub
        );
        wp_update_post($my_post);
        $cnt++;
    }
    if (isset($cnt) && $cnt != 2) {
        if (isset($_POST['f_sub']) && $_POST['f_sub'] == "" && isset($_POST['f_name']) && $_POST['f_name'] == "") {
            echo "both";
        } else if (isset($_POST['f_name']) && $_POST['f_name'] == "") {
            echo "fname";
        } elseif (isset($_POST['f_sub']) && $_POST['f_sub'] == "") {
            echo "sub";
        }
    }
    
    
    die();
}


function bns_en_track_msg_view($chk, $url)
{
    if ($chk == "unsub") {
        $btn_txt = "Resubscribe";
        $msg     = "You have successfully unsubscribed.";
    } elseif ($chk == "resub") {
        $btn_txt = "Unsubscribe";
        $msg     = "You have successfully resubscribed.";
    }
    
    
    $html_cont = '<!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
    <head>
        <title>Email Newsletter Unsubscribe</title>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
        <meta http-equiv="Content-Type" content="text/html;charset=iso-8859-1" >
            <meta name="robots" content="noindex, nofollow" />
            <style>
body {
    background: #dddddd;
    font-family: "proxima_nova_rgregular", sans-serif;
    font-size: 16px;
    line-height: 28px; 
}
#en_mail_auth_wrap{
    margin: 75px auto 0;
    width: 364px;
    margin-top: 90px;
    text-align: center;
}
#en_mail_auth_cont{
    position: relative;
    width: 375px;
    margin: 20px auto 0;
    padding: 25px;
    display: inline-block;
    vertical-align: top;
    background: #fff;
    border-bottom: 2px solid #ccc;
    border-radius: 5px;
    text-align: center;
}
#en_mail_auth_cont h2.signup_heading{
    margin: 0 0 20px 0;
    font-family: "proxima_novasemibold", sans-serif;
    font-size: 19px;
    font-weight: 600;
    color: #333;
    padding-bottom: 12px;
    line-height: normal;
}
#en_mail_auth_cont ul {
    padding: 0;
    list-style: none;
    margin-bottom: 0;
}
#en_mail_auth_cont ul li {
    margin-left: 0;
    margin-bottom: 8px;
}
#en_mail_auth_cont ul li span {
    font-size: 12px;
    color: #5885c2;
}
#en_mail_auth_cont .submit {
    width: 100% !important;
	background: #00a0d2;
border-color: #0073aa;
padding:7px;
font-size:16px;
background: #00a0d2;
    border-color: #0073aa;
    padding: 15px;
    font-size: 18px;
    color: #fff;
    font-weight: bold;
    cursor: pointer;
	border-radius:4px;

}

h2.signup_heading{
    margin-bottom: 20px;
}
#en_mail_auth_cont .submit {
    margin-top: 6px;
}

#en_mail_auth_cont .submit:hover {
    background: #0A7FB9;
}

			</style>
    </head>
    <body><div id = "en_mail_auth_wrap">
   <div id = "en_mail_auth_cont">
          <div>
            <h2 class = "signup_heading">Unsubscribe/Resubscribe</h2>    
            <form action="' . esc_url($url) . '" method="POST">
                <ul>
                    <li>
                        <label>' . sanitize_text_field($msg) . '</label>
                    </li>
                    <li>
                        <div class="clear"></div>
                        <input class="submit" id="submit_button" type="submit" value="' . $btn_txt . '" />
                    </li>
                </ul>
            </form> 
        </div>
    </div>                   		
</div>
</body>
</html>';
    
    return $html_cont;
}

function bns_widget_sub(){
	$arr =array();
	$best_email = $_POST["best_email"];
	$best_name = $_POST["best_name"];
	if(isset($best_email) && isset($best_name)){
		$arr[$best_email] = array("email" => sanitize_email($best_email), "name"=> sanitize_text_field($best_name));
		$cont_arr = serialize(json_encode($arr));
	}
	
	$get_list_id = get_option("widget_bestnewsplugin");
	if(isset($get_list_id) && !empty($get_list_id)){
		foreach($get_list_id as $key => $val){
			if(isset($val["best_news_sub_list"]) && $val["best_news_sub_list"] != ""){
		$list_email_arr         = array();
		$list_select_id = filter_var($val["best_news_sub_list"],FILTER_SANITIZE_NUMBER_INT);
	
$list_email_arr         = bns_array_intialization($list_select_id);
if (isset($list_email_arr['table']) && isset($list_email_arr['list_email_arr'])) {
$table_name     = $list_email_arr['table'];
$list_email_arr = $list_email_arr['list_email_arr'];
}
$index = $cnt = 1;

$output_arr = bns_update_contacts_list($arr, $list_email_arr, $list_select_id, $cnt, $table_name);
		}}

}
echo "updated";
	
	die();
}
add_action('wp_ajax_bns_widget_sub','bns_widget_sub');
add_action('wp_ajax_nopriv_bns_widget_sub', 'bns_widget_sub' );

class Bns_Form_Widget extends WP_Widget {

   	private $instance = '';
    private $options  = array();
   public function __construct() {
		/* Widget settings. */
		$widget_ops = array( 'description' => __( 'Displays a subscriber form in the sidebar.', 'best-news-plugin' ) );

		/* Create the widget. */
		parent::__construct( 'bestnewsplugin', __( 'Best Newsletter Plugin Widget', 'best-news-plugin' ), $widget_ops );
    }

    /**
     * bns_define_options function.
     */
    public function define_options() {
	    // Define options for widget
		$this->options       = array(
			'best_news_sub_title' => array(
				'label'           => __( 'Subscriber Form Title', 'best-news-plugin' ),
				'default'         => __( 'Subscriber Form', 'best-news-plugin' ),
				'type'            => 'text'
			),
			'best_news_sub_btn'  => array(
				'label'           => __( 'Subscriber Button Text', 'best-news-plugin' ),
				'default'         => __( 'Subscribe Now', 'best-news-plugin' ),
				'type'            => 'text'
			),
			'best_news_sub_list'  => array(
				'label'           => __( 'Select List to add subscribers', 'best-news-plugin' ),
				'default'         => "",
				'type'            => "select"
				
			)
			
		);
    }

   
    /**
     * widget function.
     *
     * @param mixed $args
     * @param mixed $instance
     */
	
    public function widget( $args, $instance ) {
		// Record $instance
		 
		$this->instance = $instance;

		
		$defaults = array(
			'best_news_sub_title'  => ! empty( $instance['best_news_sub_title'] ) ? $instance['best_news_sub_title'] : __( 'Subscriber Form', 'best-news-plugin' ),
			'best_news_sub_btn' => ! empty( $instance['best_news_sub_btn'] ) ? $instance['best_news_sub_btn'] : __( 'Subscribe Now', 'best-news-plugin' ),
            'best_news_sub_list' => ! empty( $instance['best_news_sub_list'] ) ? $instance['best_news_sub_list']	: __('','best-news-plugin')	
		);

		$args = array_merge( $defaults, $args );

		extract( $args );
        $title = apply_filters('widget_title', $best_news_sub_title);
     
		  echo $args['before_widget'];
    if ( ! empty( $title ) )
    echo $args['before_title'] . $title . $args['after_title'];
		 ?>
		 <form action="#" method="post">
		 <div class="bns-widget-container">
	     <p>
                <label for="best_new_for_name"><?php _e('Your Name:', 'Name'); ?></label>
                <input id="best_news_name" name="best_news_name" placeholder="Enter Your Name" value ="" class="best_input" />
        </p>

        <!-- Your Name: Text Input -->
        <p>
                <label for="best_new_for_email"><?php _e('Your Email', 'Email'); ?></label>
                <input id="best_news_email" name="best_news_email" placeholder="Enter Your Email" value="" class="best_input" />
				<p class="bns_error_widget" style="display:none; color:#ff0000;">Please enter valid email</p>
        </p>
 <p>
                <input type="button" id="best_news_save" class="best_news_save" name="best_new_save" value="<?php echo $best_news_sub_btn; ?>" />
        </p>
		</div>
		<p id="bns_widget_succ_msg" style="display:none;">
		Thanks for subscribing.
		</p>
		
		</form>
       
    <?php

    }

	
	/**
	 * update function.
	 *
	 * @see WP_Widget->update
	 * @access public
	 * @param array $new_instance
	 * @param array $old_instance
	 * @return array
	 */
	function update( $new_instance, $old_instance ) {
		$this->define_options();

		foreach ( $this->options as $name => $option ) {
			if ( $option['type'] == 'break' ) {
				continue;
			}
            if($option["type"] == "select"){
				if(strip_tags( stripslashes( $new_instance[ $name ] ) ) == "default"){
					 global $wpdb;
					 $contlist  = array();
                     $cont_list = serialize(json_encode($contlist));
					 $ins_arr =array("Default List",$cont_list,0,date('Y-m-d H:i:s'));
					/* $query = $wpdb->query( $wpdb->prepare( 
	"
		INSERT INTO ". $wpdb->prefix . "bns_email_news_list
		(list_name, contacts, count, date )
		VALUES ( %s, %s, %d, %s )
	", 
       $ins_arr
) );*/
			$query   = $wpdb->insert($wpdb->prefix . "bns_email_news_list", array(
														"list_name" => "Default List",
														"contacts" => $cont_list,
														"count" => 0,
														"date" => date('Y-m-d H:i:s')));
					$list_id =  $wpdb->insert_id;
					$instance[$name] = filter_var($list_id, FILTER_SANITIZE_NUMBER_INT);
				}else{
					$instance[ $name ] = strip_tags( stripslashes( $new_instance[ $name ] ) );
				}
					
			}else{
			$instance[ $name ] = strip_tags( stripslashes( $new_instance[ $name ] ) );
			}
		}
		return $instance;
	}

	/**
	 * form function.
	 *
	 * @see WP_Widget->form
	 * @param array $instance
	 */
	function form( $instance ) {
		$this->define_options();

		foreach ( $this->options as $name => $option ) {

			if ( $option['type'] == 'break' ) {
				echo '<hr style="border: 1px solid #ddd; margin: 1em 0" />';
				continue;
			}

			if ( ! isset( $instance[ $name ] ) ) {
				$instance[ $name ] = $option['default'];
			}

			if ( empty( $option['placeholder'] ) ) {
				$option['placeholder'] = '';
			}

			echo '<p>';

			switch ( $option['type'] ) {
				case "text" :
					?>
					<label for="<?php echo esc_attr( $this->get_field_id( $name ) ); ?>"><?php echo wp_kses_post( $option['label'] ) ?>:</label>
					<input type="text" class="widefat" id="<?php echo esc_attr( $this->get_field_id( $name ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $name ) ); ?>" placeholder="<?php echo esc_attr( $option['placeholder'] ); ?>" value="<?php echo esc_attr( $instance[ $name ] ); ?>" />
					<?php
				break;
				case "select" :
				global $wpdb;
				$option_arr = "";
			    $res_list = $wpdb->get_results("SELECT * FROM ". $wpdb->prefix ."bns_email_news_list");
				
				if(isset($res_list) && !empty($res_list)){
					foreach($res_list as $list_data){
						$option_arr .= "<option value='".$list_data->list_id ."'>".$list_data->list_name ."</option>";
					}
				}
				else{
						$option_arr .= "<option value='default'>Default List</option>";
				}
					?>
					<label for="<?php echo esc_attr( $this->get_field_id( $name ) ); ?>"><?php echo wp_kses_post( $option['label'] ) ?>:</label><select class="widefat" id="<?php echo esc_attr( $this->get_field_id( $name ) ); ?>" name="<?php echo esc_attr( $this->get_field_name( $name ) ); ?>">
					<?php echo $option_arr; ?>
					</select>
					
					<?php
				break;
			}

			if ( ! empty( $option['description'] ) ) {
				echo '<span class="description" style="display:block; padding-top:.25em">' . wp_kses_post( $option['description'] ) . '</span>';
			}

			echo '</p>';
		}
	}
} 


function bns_register_widget() {
    register_widget( 'Bns_Form_Widget' );
}
add_action( 'widgets_init', 'bns_register_widget' );

?>