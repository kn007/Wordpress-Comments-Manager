<?php

if ( 'POST' != $_SERVER['REQUEST_METHOD'] ) { die(); }

/** Sets up the WordPress Environment. */
require( dirname( dirname( dirname( dirname( __FILE__ ) ) ) ) . '/wp-load.php' );
if ( !current_user_can('moderate_comments') ) { die(); }

$order = $_POST;
if ( !isset( $order['cmd'] ) ) { die(); }
if ( !isset( $order['approved'] ) ) { $order['approved'] = 'all'; }

/** Main Functions. */
function get_item($order) {
	global $wpdb;
	$sql = "SELECT `comment_ID`,`comment_author`,`comment_author_email`,`comment_author_url`,`comment_content` FROM `".$wpdb->comments."` WHERE `comment_ID`=".addslashes($order['recid']);
	$record = $wpdb->get_row($sql);
	$res             = Array();
	$res['status']   = 'success';
	$res['record']   = $record;
	return $res;
}

function get_data($order) {
	global $wpdb;
	// prepare search
	$where = ($order['approved'] == "all" ? "" : "comment_approved='".$order['approved']."'");
	if (isset($order['search']) && is_array($order['search'])) {
		foreach ($order['search'] as $s => $search) {
			if ($where != "") $where .= " ".$order['searchLogic']." ";
			$field = $search['field'];
			switch (strtolower($search['operator'])) {
				case 'begins':
					$operator = "LIKE";
					$value	= "'".$search['value']."%'";
					break;
				case 'ends':
					$operator = "LIKE";
					$value	= "'%".$search['value']."'";
					break;
				case 'contains':
					$operator = "LIKE";
					$value	= "'%".$search['value']."%'";
					break;
				case 'is':
					$operator = "=";
					$value = "'".$search['value']."'";
					break;
				case 'between':
					$operator = "BETWEEN";
					$value	= "'".$search['value'][0]."' AND '".$search['value'][1]."'";
					break;
				case 'in':
					$operator = "IN";
					$value	= "(".$search['value'].")";
					break;
				case 'more':
					$operator = ">=";
					$value = "'".$search['value']."'";
					break;
				case 'less':
					$operator = "<=";
					$value = "'".$search['value']."'";
					break;
				default:
					$operator = "=";
					$value	= "'".$search['value']."'";
					break;
			}
			$where .= "`".$field."` ".$operator." ".$value;
		}
	}
	if ($where === "") $where = "1=1";
	// prepare sort
	$orderby = "";
	if (isset($order['sort']) && is_array($order['sort'])) {
		foreach ($order['sort'] as $s => $sort) {
			if ($orderby != "") $orderby .= ", ";
			$orderby .= $sort['field']." ".strtoupper($sort['direction']);
		}
	}
	if ($orderby !== "") $orderby = " ORDER BY ".$orderby;
	// prepare offset
	if (!isset($order['limit']))  $order['limit']  = 100;
	if (!isset($order['offset'])) $order['offset'] = 0;
	// process sql
	$sql = "SELECT `comment_ID`,`comment_author`,`comment_author_email`,`comment_author_url`,`comment_content`,`comment_author_IP`,`comment_date`,`comment_agent` FROM `".$wpdb->comments."` WHERE ".$where;
	$cql = "SELECT count(1) FROM ($sql) as grid_list_1";
	$sql .= $orderby." LIMIT ".$order['limit']." OFFSET ".$order['offset'];
	$count = $wpdb->get_var($cql);
	$records = $wpdb->get_results($sql,ARRAY_A);
	// fix data for w2ui
	$last = ($order['limit']>$count ? $count : $order['limit']);
	for($i=0; $i<$last; $i++){
		$records[$i]['recid'] = $records[$i]['comment_ID'];
	}
	// build result
	$res             = Array();
	$res['status']   = 'success';
	$res['message']  = '';
	$res['total']    = $count;
	$res['records']  = $records;
	return $res;
}

function status_changed($order) {
	$res             = Array();
	$success         = Array();
	$failed          = Array();
	$status          = "success";
	foreach ($order['selected'] as $k => $v) {
		$return  = wp_set_comment_status(addslashes($v),$order['status_changed']);
		if ($return === false) {
			$status = "error";
			$failed[] = addslashes($v);
		}else{
			$success[] = addslashes($v);
		}
	}
	$res['status']   = $status;
	$res['message']  = (isset($failed[0]) ? "Could not update comment-".implode( ', comment-', $failed )." status. <br>Maybe ".(isset($failed[1]) ? "these comments" : "this comment")." is already set to ".$order['status_changed'].'.' : '');
	$res['success']  = $success;
	return $res;
}

function get_view_url($order) {
	$res             = Array();
	$view_url        = get_comment_link(addslashes($order['selected'][0]));
	$res['status']   = "success";
	$res['message']  = "Always Return Success";
	$res['view']     = $view_url;
	return $res;
}

function change_comment($order) {
	global $wpdb;
	$res             = Array();
	$comment_ID      = addslashes($order['record']['comment_ID']);
	unset($order['record']['recid'],$order['record']['comment_ID'],$order['record']['comment_author_IP'],$order['record']['comment_date'],$order['record']['comment_agent']);
	$data            = $order['record'];
	$rval            = $wpdb->update( $wpdb->comments, $data, compact( 'comment_ID' ) );
	$res['status']   = ($rval !== false ? "success" : "error");
	$res['message']  = "";
	clean_comment_cache($comment_ID);
	return $res;
}

function delete_comments($order) {
	$res             = Array();
	$success         = Array();
	$failed          = Array();
	$status          = "success";
	foreach ($order['selected'] as $k => $v) {
		$return      = wp_delete_comment(addslashes($v),true);
		if ($return === false) {
			$status = "error";
			$failed[] = addslashes($v);
		}else{
			$success[] = addslashes($v);
		}
	}
	$res['status']   = $status;
	$res['message']  = (isset($failed[0]) ? "Could not delete comment-".implode( ', comment-', $failed ) : '');
	$res['success']  = $success;
	return $res;
}

switch ($order['cmd']) {
	case 'get':
		if ( array_key_exists('recid', $order) ) {
			$res = get_item($order);
		} else {
			$res = get_data($order);
		}
		break;
	case 'save':
		if ( isset($order['status_changed']) ) {
			$res = status_changed($order);
		} elseif ( isset($order['view']) ) {
			$res = get_view_url($order);
		} else {
			$res = change_comment($order);
		}
		break;
	case 'delete':
		$res = delete_comments($order);
		break;
	default:
		$res = Array();
		$res['status']   = 'error';
		$res['message']  = 'Command "'.$order['cmd'].'" is not recognized.';
		$res['postData'] = $order;
		break;
}
wp_send_json($res);
exit;
?>
