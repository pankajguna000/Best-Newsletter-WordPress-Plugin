<?php
if ( ! defined( 'ABSPATH' ) )
	exit; 
if ( ! class_exists( 'WP_List_Table' ) ) {
	require_once( ABSPATH . 'wp-admin/includes/class-wp-list-table.php' );
 }
class Bns_camp_stats extends WP_List_Table{
   public function __construct($param =array())
    {
		parent::__construct( array(
            'singular' => 'table example',
            'plural'   => 'table examples',
            'ajax'     => false			
        ) );
	$this->prepare_items($param);		
	 $this->display();
	 
    }

    function get_columns(){
        $columns = array(
		    'S.no.' => 'S.no.',
            'Email' => 'Email'			
        );
        return $columns;
    }

	function bns_get_columns_click(){
        $columns = array(
		    'S.no.' => 'S.no.',
            'Email' => 'Email',
'Links' => 'Links'			
        );
        return $columns;
    }
	
	function bns_get_columns_link(){
        $columns = array(
		    'S.no.' => 'S.no.',
            'Links' => 'Links'			
        );
        return $columns;
    }
	
	
    function column_default( $item, $column_name ) {
        switch( $column_name ) {
			case 'S.no.':
			  return filter_var($item['id'],FILTER_SANITIZE_NUMBER_INT);
            case 'Email':
			 return sanitize_email($item["email"]);
			break;
			case 'Links':
		    return $item['links'];
			break;
		    default:
                return print_r( $item, true ) ; //Show the whole array for troubleshooting purposes
        }
    }

	
	function bns_table_data($temp_id, $temp_type, $camp_id){
		$query = array();
		if(isset($temp_id) && $temp_id != ""){
		 if(isset($temp_type) && isset($camp_id) && $temp_type != "" && $camp_id != ""){
		switch($temp_type){
		   case "sent":
		      $query = $this->bns_table_sent_data($temp_id,$camp_id);
		   break;
		   case "open":
		      $query = $this->bns_table_open_data($temp_id,$camp_id);
		   break;
		   case "links":
		      $query = $this->bns_table_links_data($temp_id, $camp_id);
		   break;
		   case "click":
		    $query = $this->bns_table_click_data($temp_id, $camp_id);
		   break;
		   case "unsub":
		    $query = $this->bns_table_unsub_data($temp_id, $camp_id);
		   break;
		     case "default":
		   break;
		  
		 }
		 return $query;
		}
		else{
			return array("query"=>array(),"count"=>0);
		}
		
    }
		
		
	}
	function bns_table_sent_data($temp_id, $camp_id){
		global $wpdb;
	
		$data_arr = array();
		$data_arr_new = array("query"=>$data_arr,"count"=>0);
		if($camp_id != "default" && $camp_id != ""){
		    $query = $wpdb->get_results( $wpdb->prepare( "SELECT contacts,send_id FROM " . $wpdb->prefix . "bns_email_new_sending where temp_id= %d and sender_id = %s limit 1", $temp_id, $camp_id) );	
		if(isset($query[0]->contacts) && !empty($query[0]->contacts)){
			$cont = json_decode(unserialize($query[0]->contacts));
			$i=1;
			foreach($cont as $val){
				
			$data_arr[] = array("id" => $i++,
			"name" => $val->name,
			"email" => $val->email
			);
			}
		
		}
	
		 $data_arr_new["query"] =$data_arr;
		if(isset($query[0]->send_id) && $query[0]->send_id != "" && $query[0]->send_id != 0){
			 $query_cnt = $wpdb->get_results("select a.count, sum(b.count) as total from ". $wpdb->prefix ."bns_email_new_sending a left join ".$wpdb->prefix ."bns_email_new_sending_extra b on a.send_id=b.s_id group by b.s_id");
			 $count =0;
			 if(isset($query_cnt[0]->count)){
				 $count = $count + $query_cnt[0]->count; 
			 }
			 if(isset($query_cnt[0]->total)){
				 $count = $count + $query_cnt[0]->total;
			 }
			 $data_arr_new["count"] =$count;
		}
		}
		else{
			$count = 0;
			$query = $wpdb->get_results( $wpdb->prepare( "SELECT contacts,send_id,count as cnt FROM " . $wpdb->prefix . "bns_email_new_sending where temp_id= %d order by send_id desc limit 1", $temp_id) );	
		  
			 if(isset($query[0]->contacts) && !empty($query[0]->contacts)){
			$cont = json_decode(unserialize($query[0]->contacts));
				$i=1;
			foreach($cont as $val){
				
			$data_arr[] = array(
			"id" => $i++,
			"name" => $val->name,
			"email" => $val->email
			);
			}
			if(isset($query[0]->cnt) && $query[0]->cnt != ""){
				$count = $count + $query[0]->cnt;
			}
		 $data_arr_new["query"] =$data_arr;
		 $data_arr_new["count"] = 0;
		}
		 
		if(isset($query[0]->send_id) && $query[0]->send_id != "" && $query[0]->send_id != 0){
			$sendid = filter_var($query[0]->send_id,FILTER_SANITIZE_NUMBER_INT);
			 $query_cnt = $wpdb->get_results($wpdb->prepare( "select sum(b.count) as total from ". $wpdb->prefix ."bns_email_new_sending_extra b where b.s_id =%d group by b.s_id",$sendid));
			
			 if(isset($query_cnt[0]->total)){
				 $count = $count + $query_cnt[0]->total; 
			 }
			 

			  $data_arr_new["count"] =$count;
		}
			 }return $data_arr_new;
	}
	
	
	
	function bns_table_open_data($temp_id, $camp_id){
		global $wpdb;
	
		$data_arr = $data_arr_new = array();
		if($camp_id != "default" && $camp_id != ""){
			$query = $wpdb->get_results( $wpdb->prepare( "SELECT contacts FROM " . $wpdb->prefix . "bns_email_news_open_track where temp_id= %d and sender_id = %s limit 1", $temp_id, $camp_id) );	
			if(isset($query[0]->contacts) && !empty($query[0]->contacts)){
			$cont = json_decode(unserialize($query[0]->contacts));
			$i=1;
			foreach($cont as $val){
				
			$data_arr[] = array("id" => $i++,
			"name" => "",
			"email" => $val->email
			);
			}
			$data_arr_new["query"] =$data_arr;
			$data_arr_new["count"] = count($query[0]->contacts);
		}
	
		}
		else{
			$query = $wpdb->get_results( $wpdb->prepare( "SELECT contacts FROM " . $wpdb->prefix . "bns_email_news_open_track where temp_id= %d  order by id desc limit 1", $temp_id) );
			if(isset($query[0]->contacts) && !empty($query[0]->contacts)){
			$cont = json_decode(unserialize($query[0]->contacts));
			$i=1;
			foreach($cont as $val){
				
			$data_arr[] = array("id" => $i++,
			"name" => "",
			"email" => $val->email
			);
			}
			$data_arr_new["query"] = $data_arr;
			$data_arr_new["count"] = count($query[0]->contacts);
		}
		else{
			$data_arr_new = array("query" => $data_arr, "count" =>0);
		}
		}
		return $data_arr_new;
	}
	
	function bns_table_links_data($temp_id, $camp_id){
		global $wpdb;
		$data_arr = $data_arr_new = array();
		if($camp_id != "default" && $camp_id != ""){
		$query = $wpdb->get_results( $wpdb->prepare( "SELECT links FROM " . $wpdb->prefix . "bns_email_news_templ where temp_id= %d  and sender_id = %s limit 1", $temp_id,$camp_id) );	
			if(isset($query[0]->links) && !empty($query[0]->links)){
				$cont = json_decode(unserialize($query[0]->links));
			$i=1;
			foreach($cont as $key =>$val){
				$val = urldecode($val);
				$val = filter_var($val,FILTER_SANITIZE_URL);
			$data_arr[] = array("id" => $i++,
			"links" => $val
			);
		
			}
			$data_arr_new =array("query"=>$data_arr, "count" => count($cont));
			}	
			else{
			$data_arr_new =array("query"=>array(), "count" => 0);	
			}
		}
		else{
		$query = $wpdb->get_results( $wpdb->prepare( "SELECT links FROM " . $wpdb->prefix . "bns_email_news_templ where temp_id= %d limit 1", $temp_id) );		
		
			if(isset($query[0]->links) && !empty($query[0]->links)){
				$cont = json_decode(unserialize($query[0]->links));
			$i=1;
			foreach($cont as $key=>$val){
			$val =urldecode($val);
			$val = filter_var($val,FILTER_SANITIZE_URL);
			$data_arr[] = array("id" => $i++,
			"links" => $val
			);
		
			}
			$data_arr_new =array("query"=>$data_arr, "count" => count($cont));
			}	
			else{
				$query = $wpdb->get_results( $wpdb->prepare( "SELECT temp_link FROM " . $wpdb->prefix . "bns_email_new_temp_link where temp_id= %d limit 1", $temp_id) );		
				
			if(isset($query[0]->temp_link) && !empty($query[0]->temp_link)){
				$cont = json_decode(unserialize($query[0]->temp_link));
			$i=1;
			foreach($cont as $key=>$val){
			$val_link = urldecode($val->value);
            $val_link = filter_var($val_link,FILTER_SANITIZE_URL);			
			$data_arr[] = array("id" => $i++,
			"links" => $val_link
			);
		
			}
			$data_arr_new =array("query"=>$data_arr, "count" => count($cont));
				
			}
			else{
				$data_arr_new =array("query"=>$data_arr, "count" =>0);
			}
		}}
			return $data_arr_new;
	}
	
	function bns_table_click_data($temp_id, $camp_id){
		global $wpdb;
		$data_arr = $data_arr_new = array();
		$email_click_arr_main = array();
                $email_click_link = array();
                $email_click_link_multi = array(); 
if($camp_id != ""){
	$track_array = $wpdb->get_results( $wpdb->prepare( "SELECT sender_id, link_enc_id, contacts FROM " . $wpdb->prefix . "bns_email_news_templ_track where temp_id= %d and sender_id = %s", $temp_id, $camp_id) );		
			
    if (!empty($track_array)) {
                    foreach ($track_array as $track_array_row) {
                        $click_emails_arr = $track_array_row->contacts;
                        $json_get_email_arr = json_decode(unserialize($click_emails_arr));
                        $click_link_id = $track_array_row->link_enc_id;
						$s_id = $track_array_row->sender_id;
						$get_link = $wpdb->get_results( $wpdb->prepare( "SELECT links FROM " . $wpdb->prefix . "bns_email_news_templ where temp_id= %d and sender_id = %s LIMIT 1", $temp_id, $s_id) );		
                     
						
						   if (isset($get_link[0]->links)) {
            $links_arr = json_decode(unserialize($get_link[0]->links));
               if (isset($links_arr->$click_link_id)) {
                $g_link =  $links_arr->$click_link_id;
            } else {
                $g_link = "";
            }
        } else {
           $g_link = "";
        }
			 $getlink = urldecode($g_link);
foreach ($json_get_email_arr as $json_get_email_arr_k => $json_get_email_arr_v) {
                            if (array_key_exists($json_get_email_arr_k, $email_click_arr_main)) {
                                $email_click_link_multi['link'] = $email_click_arr_main[$json_get_email_arr_k]['link'] . ',' . $getlink;
                                $email_click_arr_main[$json_get_email_arr_k] = $email_click_link_multi;
                            } else {
                                $email_click_link['link'] = $getlink;
                                $email_click_arr_main[$json_get_email_arr_k] = $email_click_link;
                            }
                        }
					}
		  $j=1;
		 foreach($email_click_arr_main as $key=>$val){
			                     $anchor = '';
                                $explode_arr = explode(',', $val['link']);
                                if (!empty($explode_arr)) {
                                    for ($i = 0; $i < sizeof($explode_arr); $i++) {
										$explode_arr[$i] = filter_var($explode_arr[$i],FILTER_SANITIZE_URL);
                                        $anchor .= "<a href='" . $explode_arr[$i] . "' target='_blank' >" . $explode_arr[$i] . "</a><br/>";
                                    }
                                } else {
									$a_link =  filter_var($val['link'],FILTER_SANITIZE_URL);
                                    $anchor .= "<a href='".$a_link."'  target='_blank' >" . $a_link . "</a><br/>";
                                }
                    			 $data_arr[] = array(
								 "id" => $j,
								 "email" => $key,
								 "links" => $anchor 
								 );
					  $j++;
                            }
							

		 
}
$data_arr_new["query"] = $data_arr;
$data_arr_new["count"] = count($data_arr);

}
return $data_arr_new;
	}
	
	
		function bns_table_unsub_data($temp_id, $camp_id){
		global $wpdb;
		$data_arr = $data_arr_new = array();
		if($camp_id != "default" && $camp_id !=""){
	   $query = $wpdb->get_results( $wpdb->prepare( "SELECT contacts FROM " . $wpdb->prefix . "bns_email_news_unsubscribe_track where temp_id= %d and sender_id = %s LIMIT 1", $temp_id, $camp_id) );			
	  
			if(isset($query[0]->contacts) && !empty($query[0]->contacts)){
			$cont = json_decode(unserialize($query[0]->contacts));
			$i=1;
			foreach($cont as $key =>$val){
				
			$data_arr[] = array("id" => $i++,
			"name" => "",
			"email" => $val->email
			);
		
			}
			$data_arr_new =array("query"=>$data_arr, "count" => count($query[0]->contacts));
			}	
		}
		else{
		$query = $wpdb->get_results( $wpdb->prepare( "SELECT sender_id, contacts FROM " . $wpdb->prefix . "bns_email_news_unsubscribe_track where temp_id= %d LIMIT 1", $temp_id) );			
		
			if(isset($query[0]->contacts) && !empty($query[0]->contacts)){
			$cont = json_decode(unserialize($query[0]->contacts));
			$i=1;
			foreach($cont as $key =>$val){
				
			$data_arr[] = array("id" => $i++,
			"name" => "",
			"email" => $val->email
			);
		
			}
			$data_arr_new =array("query"=>$data_arr, "count" => count($query[0]->links));
			}	
			else{
			$data_arr_new =array("query"=>$data_arr, "count" => 0);	
			}
		}
			return $data_arr_new;
	}
	
  //function prepare_items($temp_id,$camp_id,$temp_type) {
	   function prepare_items($param=array()){
      global $wpdb;
	 if(isset($param["temp_type"]) && $param["temp_type"] == "click"){
		 $columns = $this->bns_get_columns_click();
	 }
	 elseif(isset($param["temp_type"]) && $param["temp_type"] == "links"){
		  $columns = $this->bns_get_columns_link();
	 }
	 else{
	    $columns = $this->get_columns();
	 }
        $hidden = array();
        $sortable = $this->get_sortable_columns();
		$this->_column_headers = array($columns, $hidden, $sortable);

	$query = $this->bns_table_data($param["temp_id"], $param["temp_type"], $param["camp_id"]);

	 $totalitems = intval($query["count"]);
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
				
	$q_data = array_slice($query["query"],(($currentPage-1)*$per_page),$per_page);	
	 $this->items = $q_data;
}

}

