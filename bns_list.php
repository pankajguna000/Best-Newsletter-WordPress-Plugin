<?php
if ( ! defined( 'ABSPATH' ) )
	exit; 
 if ( ! class_exists( 'WP_List_Table' ) ) {
    require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
}
class Bns_list extends WP_List_Table{
    public function __construct()
    {
        parent::__construct( array(
            'singular' => 'list_table',
            'plural'   => 'list_tables',
            'ajax'     => false
        ) );
        $this->prepare_items();
        $this->display();
    }

    function get_columns(){
        $columns = array(
            'cb'        => '<input type="checkbox" />',
            'List Name' => 'List Name',
            'Count' => 'Count',
            'actions'    => 'Action',
        );
        return $columns;
    }

    function column_default( $item, $column_name ) {
        switch( $column_name ) {
            case 'List Name':
			 return sanitize_text_field($item["list_name"]);
			 break;
            case 'Count':
			  return filter_var($item["count"],FILTER_SANITIZE_NUMBER_INT);
			  break;
            case 'actions':
			 //return $item[ $column_name ];
			 	$a = '<div class="inline-buttons">';
				$a .= '<input type="button" class="btn button-primary" data-tempid="'. esc_attr($item["list_id"]) .'" onclick="view_contact_list(this);" value="View"><span class="glyphicon  glyphicon-eye-open"></span> ';
				$a .= '<input type="button" class="btn button-primary" data-tempid="'. esc_attr($item["list_id"]) .'" value="Import" onclick="email_all_list_view(this);" id="email_import"><span class="glyphicon glyphicon-send"></span>';
				$a .= '</div>';
			
				return $a;
			
			 break;
           default:
                return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
        }
    }


	 function process_bulk_action_new() {
  global $wpdb;
           if ( isset( $_POST['nonce'] ) && ! empty( $_POST['nonce'] ) ) {
			 $nonce  = filter_input( INPUT_POST, 'nonce', FILTER_SANITIZE_STRING );
			if (  wp_verify_nonce( $nonce, 'mg_delete_list' ) && current_user_can('manage_options')) {
            $action = 'bulk-' . $this->_args['plural'];
        $action = $this->current_action();

        switch ( $action ) {

            case 'delete':
			     foreach($_POST['list_table'] as $id) {
				 $id =  filter_var($id, FILTER_SANITIZE_NUMBER_INT);
                $wpdb->query( 
	$wpdb->prepare( 
		"DELETE FROM ".$wpdb->prefix ."bns_email_news_list
		 WHERE list_id = %d
		",
	       $id   
        ));  
		    $wpdb->query( 
	$wpdb->prepare( 
		"DELETE FROM ".$wpdb->prefix ."bns_email_news_list_extra
		 WHERE list_id = %d
		",
	       $id   
        ));  
           
            }
                
			
   $current_url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	if (strpos($current_url, '_wp_http_referer') !== false) {
        $new_url = remove_query_arg( array( '_wp_http_referer', 'nonce' ), stripslashes($current_url));
        wp_safe_redirect ($new_url);
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
	
	 public function get_bulk_actions() {

        return array(
                'delete' => __( 'Delete', 'your-textdomain' ),
                    );

    }
	function column_cb($item) {
      
return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],  // Let's simply repurpose the table's singular label ("movie").
			$item["list_id"]
			// The value of the checkbox should be the record's ID.
		);		
    }
	
   function prepare_items() {
		global $wpdb, $_wp_column_headers;
	    $columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);
		$this->process_bulk_action_new();
	    $query_cnt = $wpdb->get_results("SELECT count(list_id) as cnt FROM " . $wpdb->prefix . "bns_email_news_list");
	  	  
 $arr =array();
       	$query = $wpdb->get_results("SELECT list_id,list_name,count as total FROM " . $wpdb->prefix . "bns_email_news_list");				
    			
                         if(isset($query[0]->list_id) && $query[0]->list_id != ""){
                     	 foreach($query as $val){
							 $tot = 0;
							 if(isset($val->count))
								 $tot = $tot + $val->count;
							 if(isset($val->total) && $val->total != null)
							     $tot = $tot + $val->total;
							 $arr[] = array("list_id" => $val->list_id,
							               "list_name" =>$val->list_name,
							               "count" => $tot);
						 }}
	   $totalitems = $query_cnt[0]->cnt;
		$per_page = 50;
        $currentPage = $this->get_pagenum();
       	$totalpages = ceil($totalitems/$per_page);
			//adjust the query to take pagination into account
			if(!empty($currentPage) && !empty($per_page)){
				$offset=($currentPage-1)*$per_page;
			  
			}

		/* -- Register the pagination -- */
			$this->set_pagination_args( array(
				"total_items" => $totalitems,
				"total_pages" => $totalpages,
				"per_page" => $per_page,
			) );
		$this->items = $arr;
	
    }
	
}
?>
