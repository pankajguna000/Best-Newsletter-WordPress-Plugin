<?php
if ( ! defined( 'ABSPATH' ) )
	exit; 
 if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
    }
class Bns_list_contact extends WP_List_Table{
    public function __construct($param =array())
    {
        parent::__construct( array(
            'singular' => 'en_temp_list_part',
            'plural'   => 'en_temp_list_parts',
            'ajax'     => false			
        ) );
        $this->prepare_items($param["lid"]);
        $this->display();
    }

    function get_columns(){
        $columns = array(
            'cb'        => '<input type="checkbox" />',
            'Name' => 'Name',
            'Email' => 'Email'
        );
        return $columns;
    }

    function column_default( $item, $column_name ) {
        switch( $column_name ) {
            case 'Name':
			 return $item['name'];
			 break;
            case 'Email':
			  return $item['email'];
			  break;
            default:
                return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
        }
    }

   	
	 public function get_bulk_actions() {
        return array(
                'delete' => __( 'Delete', 'your-textdomain' ),
                    );
    }
	function column_cb($item) {
		  return sprintf(
            '<input type="checkbox" name="%1$s[]" value="%2$s" />',
            $this->_args['singular'],
            $item['email']
        );
          
    
	}
	
	
	 function process_bulk_action() {

        global $wpdb;
      if ( isset( $_POST['wp_lnonce'] ) && ! empty( $_POST['wp_lnonce'] ) ) {
			$nonce  = filter_input( INPUT_POST, 'wp_lnonce', FILTER_SANITIZE_STRING );
            if (  wp_verify_nonce( $nonce, 'mg_delete_list_part' ) && current_user_can('manage_options') ) {

            
            $action = 'bulk-' . $this->_args['plural'];


        

        $action = $this->current_action();

        switch ( $action ) {

            case 'delete':
			$list_id = filter_var($_POST['en_list_part_id'], FILTER_SANITIZE_NUMBER_INT);
			if(!empty($list_id)){
				 $slot = 0;
			     foreach($_POST['en_temp_list_part'] as $email) {
					 $email = sanitize_email($email);
					$query = $wpdb->get_results( $wpdb->prepare( "SELECT contacts FROM " . $wpdb->prefix . "bns_email_news_list WHERE list_id = %d", $list_id ) );
                	
		     if(isset($query[0]->contacts) && !empty($query[0]->contacts)){
			$list_email_arr = json_decode(unserialize($query[0]->contacts));
              $list_email_arr = (array) $list_email_arr;
                if (array_key_exists($email, $list_email_arr)) {
                    unset($list_email_arr[$email]);
                }
			   else { 
			        $email_list_arr = $wpdb->get_results( $wpdb->prepare( "SELECT id,list_id FROM " . $wpdb->prefix . "bns_email_news_list_extra WHERE list_id = %d order by slot_id ASC", $list_id ) );
                    
                    if (!empty($email_list_arr)) {
                        foreach ($email_list_arr as $email_list_arr_row) {
                            $id = $email_list_arr_row->id;
                            $list_id = filter_var($email_list_arr_row->list_id,FILTER_SANITIZE_NUMBER_INT);
							    $this->db->select('emails,slot');
        $this->db->where('id', $id);
        $this->db->limit(1);
		                    $extra_list = $wpdb->get_results( $wpdb->prepare( "SELECT contacts,slot_id FROM " . $wpdb->prefix . "bns_email_news_list_extra WHERE list_id = %d LIMIT 1", $list_id ) );
                          
						    if (isset($extra_list) && !empty($extra_list)) {
                                $list_email = $extra_list[0]->contacts;
                                $slot = filter_var($extra_list[0]->slot_id,FILTER_SANITIZE_NUMBER_INT);
                                $list_email = (array) json_decode(unserialize($list_email));
                                if (array_key_exists($email, $list_email)) {
                                    unset($list_email[$email]);
                                    break;
                                }
                            }
                        }
                        $list_email_arr = $list_email;
                    }
                }
                $main_contact_arr_final = serialize(json_encode($list_email_arr));
                $new_size = sizeof($list_email_arr);
                $emails_arr = array(
                    'contacts' => $main_contact_arr_final,
                    'count' => $new_size
                );
                if ($slot == 0) {
					 $wpdb->update($wpdb->prefix ."bns_email_news_list",$emails_arr,array("list_id"=> $list_id));	
				} else {
					$wpdb->update($wpdb->prefix ."bns_email_news_list_extra", $emails_arr,array("list_id"=> $list_id,"slot_id"=>$slot));
                   
                }
            }

            
        }
				  
			}
                
			
    $current_url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    if (strpos($current_url, '_wp_http_referer') !== false) {
        $new_url = remove_query_arg( array( '_wp_http_referer', 'wp_lnonce' ), stripslashes($current_url));
       // wp_redirect ($new_url);
		exit();
    }
           break;

            default:
                // do nothing or something else
                return;
                break;
		}}}

        return;
    }
	
	
	function bns_list_table_data($list_id){
		global $wpdb;
 $data_arr = array();
  $query = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "bns_email_news_list WHERE list_id = %d LIMIT 1", $list_id ) );
		if(isset($query[0]->contacts) && !empty($query[0]->contacts)){
			$cont = json_decode(unserialize($query[0]->contacts));
			foreach($cont as $val){
			$data_arr[] = array("name" => $val->name,
			"email" => $val->email,
			"list_id" =>  $list_id
			);
			}
		}
		return $data_arr;
	}
	
	
   function prepare_items($list_id = "") {
	 global $wpdb, $_wp_column_headers;
	
	  $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
		 $this->_column_headers = array($columns, $hidden, $sortable);
		 $this->process_bulk_action();
	$count = 0;
	  $query = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM " . $wpdb->prefix . "bns_email_news_list WHERE list_id = %d LIMIT 1", $list_id ) );
	
	  $query1 = $wpdb->get_results( $wpdb->prepare( "SELECT  sum(count) as cnt FROM " . $wpdb->prefix . "bns_email_news_list_extra WHERE list_id = %d", $list_id ) );

	    	if(isset($query[0]->count) && $query[0]->count != ""){
			$count = $count + intval($query[0]->count);
			}
			if(isset($query1[0]->cnt) && $query1[0]->cnt != ""){
			 $count = $count + intval($query1[0]->cnt);
			
			}
			$totalitems = intval($count);
		        $per_page = 50;
        $currentPage = $this->get_pagenum();
		$totalpages = ceil($totalitems/$per_page);
			if(!empty($currentPage) && !empty($per_page)){
				$offset=($currentPage-1)*$per_page;
			}

		/* -- Register the pagination -- */
			$this->set_pagination_args( array(
				"total_items" => $totalitems,
				"total_pages" => $totalpages,
				"per_page" => $per_page,
			) );
			
		    $q_data = $this->bns_list_table_data($list_id);
            $q_data = array_slice($q_data,(($currentPage-1)*$per_page),$per_page);
			$this->items = $q_data;
	    }
}
?>
