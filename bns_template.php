<?php
if ( ! defined( 'ABSPATH' ) )
	exit; 
 if ( ! class_exists( 'WP_List_Table' ) ) {
	 require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
 }
class Bns_template extends WP_List_Table{
    public function __construct()
    {
        parent::__construct( array(
            'singular' => 'en_tempid',
            'plural'   => 'en_tempids',
            'ajax'     => false
        ) );
        $this->prepare_items();
        $this->display();
    }

    function get_columns(){
        $columns = array(
            'cb'        => '<input type="checkbox" />',
            'Subject' => 'Subject',
            'Created' => 'Created',
            'actions'    => 'Action',
        );
        return $columns;
    }

    function column_default( $item, $column_name ) {
        switch( $column_name ) {
            case 'Subject':
			 return sanitize_text_field($item->post_title);
			 break;
            case 'Created':
			  return $item->post_date;
			  break;
            case 'actions':
			 //return $item[ $column_name ];
			 	$a = '<div class="inline-buttons">';
				$a .= '<input type="button" class="btn button-primary" data-tempid="'. esc_attr($item->ID) .'" onclick="prev_email_temp(this);" value="View"><span class="glyphicon  glyphicon-eye-open"></span> ';
				$a .= '<input type="button" class="btn button-primary" data-tempid="'. esc_attr($item->ID) .'" onclick="edit_email_news(this);" value="Edit"><span class="glyphicon glyphicon-edit"></span>  ';
				$a .= '<input type="button" class="btn button-primary" data-tempid="'. esc_attr($item->ID) .'" onclick="email_new_template_to_send(this);" value="Send"><span  class="glyphicon glyphicon-send"></span>';
			    $a .= '<input type="button" class="btn button-primary"  data-tempid="'. esc_attr($item->ID) .'" onclick="en_call_stat('.esc_attr($item->ID) .',\'sent\',\'stats\');" value="Stats" style="margin-left: 3px;"><span  class="glyphicon glyphicon-send"></span>';
			
				$a .= '</div>';
			
				return $a;
			
			 break;
           default:
                return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
        }
    }

   	 function process_bulk_action() {
  global $wpdb;
        // security check!
		
        if ( isset( $_POST['wpnonce'] ) && ! empty( $_POST['wpnonce'] ) ) {
			$nonce  = filter_input( INPUT_POST, 'wpnonce', FILTER_SANITIZE_STRING );
            if (  wp_verify_nonce( $nonce, 'mg_delete_temp' ) && current_user_can('manage_options')) {
            
            $action = 'bulk-' . $this->_args['plural'];

        $action = $this->current_action();

        switch ( $action ) {

            case 'delete':
			     foreach($_POST['en_tempid'] as $id) {
					$id = filter_var($id,FILTER_SANITIZE_NUMBER_INT); 
                   $post_data  = get_post($id);
				   if(isset($post_data->post_type) && $post_data->post_type == "mg_newsletter"){
					   wp_delete_post($id);
				   }
			    }
                
			
    $current_url = "http://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";

    if (strpos($current_url, '_wp_http_referer') !== false) {
        $new_url = remove_query_arg( array( '_wp_http_referer', 'wpnonce' ), stripslashes($current_url));
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
	
	
	
	 public function get_bulk_actions() {

        return array(
                'delete' => __( 'Delete', 'your-textdomain' ),
                    );

    }
	function column_cb($item) {
		
       return sprintf(
			'<input type="checkbox" name="%1$s[]" value="%2$s" />',
			$this->_args['singular'],  // Let's simply repurpose the table's singular label ("movie").
			$item->ID
			// The value of the checkbox should be the record's ID.
		);	 
    }
	
	
	
    function prepare_items() {
		global $wpdb, $_wp_column_headers;
		$columns = $this->get_columns();
        $hidden = array();
        $sortable = $this->get_sortable_columns();
		 $this->_column_headers = array($columns, $hidden, $sortable);
		$this->process_bulk_action();
$args = array(
			'post_type' => 'mg_newsletter',
			'post_status' => "publish"
			);

			$query = new WP_Query( $args );
			
			$totalitems = $query->found_posts;
		        $per_page = 50;
        $currentPage = $this->get_pagenum();
     		$totalpages = ceil($totalitems/$per_page);
			//adjust the query to take pagination into account
			if(!empty($currentPage) && !empty($per_page)){
				$offset=($currentPage-1)*$per_page;
			   // $query.=' LIMIT '.(int)$offset.','.(int)$per_page;
			}

		/* -- Register the pagination -- */
			$this->set_pagination_args( array(
				"total_items" => $totalitems,
				"total_pages" => $totalpages,
				"per_page" => $per_page,
			) );
			

			$args = array(
			'post_type' => 'mg_newsletter' ,
			'post_status' => "publish",
			'posts_per_page' => $per_page,
			'paged'=> $currentPage,
			);
	        $query2 = new WP_Query( $args );

			$this->items = $query2->posts;
	
    }
	
}
?>
