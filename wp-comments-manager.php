<?php 
/*
Plugin Name:Wordpress Comments Manager
Plugin URI: https://kn007.net/topics/new-wordpress-comment-management-system/
Version: 1.2
Description: Wordpress Comments Manager help you to quickly find comments and manage comments. It can be very convenient to review selected comments, open the comment in a new window, edit comment and delete comments. See the screenshots for more details.
Author: kn007
Author URI: https://kn007.net/
*/

if ( !defined('ABSPATH') ) exit;

function wpcm_menu() {
    if (current_user_can('moderate_comments')) {
        $page_hook = add_menu_page("Comments Manager","Comments Manager", 'manage_options', 'wordpress-comments-manager', 'wpcm_page');
        add_action( 'admin_print_scripts-' . $page_hook, 'wpcm_script' );
        add_action( 'admin_print_styles-' . $page_hook, 'wpcm_style' );
    }
}
add_action('admin_menu','wpcm_menu');

function wpcm_script() {
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'wpcm', plugins_url("wpcm.js", __FILE__), 'jquery', "1.2" );
}

function wpcm_style() {
    wp_enqueue_style( 'wpcm', plugins_url("wpcm.css", __FILE__), array(), "1.2" );
}

function wpcm_bar( $wp_admin_bar ) {
    $wp_admin_bar->add_node( array(
        'id'    => 'wordpress-comments-manager',
        'title' => 'Comments Manager',
        'href'  => admin_url("admin.php?page=wordpress-comments-manager"),
        'parent'=> 'site-name'
    ) );
}
add_action( 'admin_bar_menu', 'wpcm_bar', 99999 );

/** Main Functions. */
function get_item($order) {
    global $wpdb;
    $sql = "SELECT `comment_ID`,`comment_author`,`comment_author_email`,`comment_author_url`,`comment_content` FROM `".$wpdb->comments."` WHERE `comment_ID`=".absint($order['recid']);
    $record = $wpdb->get_row($sql);
    $res              = Array();
    $res['status']    = 'success';
    $res['record']    = $record;
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
                    $value    = "'".$search['value']."%'";
                    break;
                case 'ends':
                    $operator = "LIKE";
                    $value    = "'%".$search['value']."'";
                    break;
                case 'contains':
                    $operator = "LIKE";
                    $value    = "'%".$search['value']."%'";
                    break;
                case 'is':
                    $operator = "=";
                    $value    = "'".$search['value']."'";
                    break;
                case 'between':
                    $operator = "BETWEEN";
                    $value    = "'".$search['value'][0]."' AND '".$search['value'][1]."'";
                    break;
                case 'in':
                    $operator = "IN";
                    $value    = "(".$search['value'].")";
                    break;
                case 'more':
                    $operator = ">=";
                    $value    = "'".$search['value']."'";
                    break;
                case 'less':
                    $operator = "<=";
                    $value    = "'".$search['value']."'";
                    break;
                default:
                    $operator = "=";
                    $value    = "'".$search['value']."'";
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
    $res              = Array();
    $res['status']    = 'success';
    $res['message']   = '';
    $res['total']     = $count;
    $res['records']   = $records;
    return $res;
}

function status_changed($order) {
    $res              = Array();
    $success          = Array();
    $failed           = Array();
    $status           = "success";
    foreach ($order['selected'] as $k => $v) {
        $return       = wp_set_comment_status(absint($v),$order['status_changed']);
        if ($return === false) {
            $status   = "error";
            $failed[] = absint($v);
        }else{
            $success[]= absint($v);
        }
    }
    $res['status']    = $status;
    $res['message']   = (isset($failed[0]) ? "Could not update comment-".implode( ', comment-', $failed )." status. <br>Maybe ".(isset($failed[1]) ? "these comments" : "this comment")." is already set to ".$order['status_changed'].'.' : '');
    $res['success']   = $success;
    return $res;
}

function get_view_url($order) {
    $res              = Array();
    $view_url         = get_comment_link(absint($order['selected'][0]));
    $res['status']    = "success";
    $res['message']   = "Always Return Success";
    $res['view']      = $view_url;
    return $res;
}

function change_comment($order) {
    global $wpdb;
    $res              = Array();
    $comment_ID       = absint($order['record']['comment_ID']);
    $data             = filter_comment($order['record']);
    $rval             = $wpdb->update( $wpdb->comments, $data, compact( 'comment_ID' ) );
    $res['status']    = ($rval !== false ? "success" : "error");
    $res['message']   = "";
    clean_comment_cache($comment_ID);
    return $res;
}

function filter_comment($commentdata) {
    $comment_author = trim(strip_tags($commentdata['comment_author']));
    $comment_author_email = trim($commentdata['comment_author_email']);
    $comment_author_url = trim($commentdata['comment_author_url']);
    $comment_content = trim($commentdata['comment_content']);
    $comment_author = apply_filters( 'pre_comment_author_name', $comment_author );
    $comment_author_email = apply_filters( 'pre_comment_author_email', $comment_author_email );
    $comment_author_url = apply_filters( 'pre_comment_author_url', $comment_author_url );
    $comment_content = apply_filters( 'pre_comment_content', $comment_content );
    $comment_content = apply_filters( 'comment_save_pre', strip_tags($comment_content) );
    $data = compact('comment_author', 'comment_author_email', 'comment_author_url', 'comment_content');
    $data = wp_unslash($data);
    return $data;
}

function delete_comments($order) {
    $res              = Array();
    $success          = Array();
    $failed           = Array();
    $status           = "success";
    foreach ($order['selected'] as $k => $v) {
        $return       = wp_delete_comment(absint($v),true);
        if ($return === false) {
            $status   = "error";
            $failed[] = absint($v);
        }else{
            $success[]= absint($v);
        }
    }
    $res['status']    = $status;
    $res['message']   = (isset($failed[0]) ? "Could not delete comment-".implode( ', comment-', $failed ) : '');
    $res['success']   = $success;
    return $res;
}

function wpcm_page(){
?>
<script type="text/javascript">
jQuery(document).ready(function($){var config={tabs:{name:"tabs",active:"tab1",tabs:[{id:"tab1",caption:"All"},{id:"tab2",caption:"Moderated"},{id:"tab3",caption:"Approved"},{id:"tab4",caption:"Spam"},{id:"tab5",caption:"Trash"}],onClick:function(a){switch(w2ui.grid.reset(),a.target){case"tab1":w2ui.grid.postData.approved="all";break;case"tab2":w2ui.grid.postData.approved=0;break;case"tab3":w2ui.grid.postData.approved=1;break;case"tab4":w2ui.grid.postData.approved="spam";break;case"tab5":w2ui.grid.postData.approved="trash"}w2ui.grid.searchReset()}},grid:{name:"grid",url:'<?php echo admin_url("admin.php?page=wordpress-comments-manager"); ?>',method:"POST",columns:[{field:"comment_author",caption:"Author",size:"80px",resizable:!0},{field:"comment_author_email",caption:"Email",size:"130px",resizable:!0},{field:"comment_author_url",caption:"Site",size:"120px",resizable:!0,render:function(a){return'<div><a href="'+a.comment_author_url+'" target="_blank">'+a.comment_author_url+"</a></div>"}},{field:"comment_content",caption:"Content",size:"250px"},{field:"comment_author_IP",caption:"IP",size:"120px",resizable:!0},{field:"comment_date",caption:"Date",size:"150px",render:"datetime:yyyy-mm-dd|hh24:mm:ss",resizable:!0},{field:"comment_agent",caption:"Agent",size:"30%"}],searches:[{field:"comment_author",caption:"Author",type:"text",operator:"contains"},{field:"comment_author_email",caption:"Email",type:"text",operator:"contains"},{field:"comment_author_url",caption:"Site",type:"text",operator:"contains"},{field:"comment_content",caption:"Content",type:"text",operator:"contains"},{field:"comment_author_IP",caption:"IP",type:"text",operator:"contains"},{field:"comment_agent",caption:"Agent",type:"text",operator:"contains"},{field:"comment_date",caption:"Date",type:"datetime",operator:"less"}],sortData:[{field:"comment_ID",direction:"desc"}],postData:{approved:"all",wpcm_nonce:"<?php echo wp_create_nonce('wordpress-comments-manager'); ?>"},toolbar:{items:[{type:"break",id:"break1"},{type:"button",id:"w2ui-open",text:"<?php if (!wp_is_mobile()) echo 'View'; ?>",icon:"w2ui-icon-info",tooltip:"View This Comment",disabled:!0},{type:"button",id:"w2ui-edit",text:"Edit",icon:"w2ui-icon-pencil",tooltip:"Edit This Comment",disabled:!0},{type:"menu",id:"w2ui-status",text:"<?php if (!wp_is_mobile()) echo 'Set Status'; ?>",icon:"w2ui-icon-settings",tooltip:"Change Comment Status",disabled:!0,items:[{id:"approve",text:"Approve",icon:"w2ui-icon-check"},{id:"hold",text:"Hold",icon:"w2ui-icon-reload"},{id:"spam",text:"Spam",icon:"w2ui-icon-colors"},{text:"--"},{id:"trash",text:"Trash",icon:"w2ui-icon-cross"}]},{type:"break",id:"break2"},{type:"button",id:"w2ui-delete",text:"<?php if (!wp_is_mobile()) echo 'Delete'; ?>",icon:"w2ui-icon-cross",tooltip:"Delete Comments",disabled:!0}],onClick:function(a){switch(a.target){case"w2ui-status:approve":w2ui.grid.postData.status_changed="approve",w2ui.grid.save(),delete w2ui.grid.postData.status_changed;break;case"w2ui-status:hold":w2ui.grid.postData.status_changed="hold",w2ui.grid.save(),delete w2ui.grid.postData.status_changed;break;case"w2ui-status:spam":w2ui.grid.postData.status_changed="spam",w2ui.grid.save(),delete w2ui.grid.postData.status_changed;break;case"w2ui-status:trash":w2ui.grid.postData.status_changed="trash",w2ui.grid.save(),delete w2ui.grid.postData.status_changed;break;case"w2ui-open":w2ui.grid.postData.view="get",w2ui.grid.save(),delete w2ui.grid.postData.view}}},onDblClick:function(a){w2alert(this.get(a.recid).comment_content)},onEdit:function(a){w2ui.form.clear(),w2ui.form.recid=a.recid,w2popup.open({body:'<div id="form" style="width:100%;height:100%;position:absolute;left:0px;top:0px;right:0px;bottom:0px;"></div>',width:380,height:420,onOpen:function(a){a.onComplete=function(){$("#w2ui-popup #form").w2render("form")}}})},onSave:function(event){event.onComplete=function(){void 0!==event.xhr&&(data=eval("("+event.xhr.responseText+")"),void 0!==data.view?window.open(data.view):"success"==data.status&&(w2ui.grid.selectNone(),"all"!==w2ui.grid.postData.approved&&w2ui.grid.reload()))}}},form:{name:"form",header:"Edit Comment",url:'<?php echo admin_url("admin.php?page=wordpress-comments-manager"); ?>',method:"POST",fields:[{name:"comment_author",type:"text",required:!0,html:{caption:"Author",attr:'style="width:90%"'}},{name:"comment_author_email",type:"email",required:!0,html:{caption:"Email",attr:'style="width:90%"'}},{name:"comment_author_url",type:"text",html:{caption:"Site",attr:'style="width:90%"'}},{name:"comment_content",type:"textarea",required:!0,html:{caption:"Content",attr:'style="width:90%;height:180px;resize:none"'}}],postData:{wpcm_nonce:"<?php echo wp_create_nonce('wordpress-comments-manager'); ?>"},actions:{Close:function(){this.clear(),w2popup.close()},Update:function(){var a=this.validate();a.length>0||this.save({},function(a){w2popup.close(),"success"==a.status?(w2ui.grid.reload(w2ui.grid.select(w2ui.form.recid),w2ui.grid.scrollIntoView(w2ui.grid.get(w2ui.form.recid,!0))),w2alert("Update Successful!")):w2alert("Update Failed!"),w2ui.form.clear()})}}}};$("#tabs").w2tabs(config.tabs),$("#tab").show(),$("#grid").w2grid(config.grid),$().w2form(config.form)<?php if (wp_is_mobile()) echo ",w2ui.grid.toolbar.hide('w2ui-column-on-off', 'w2ui-search-advanced', 'w2ui-edit')"; ?>});
</script>
<div class="wrap wpcm">
    <h2>Wordpress Comments Manager</h2>
    <br>
    <div id="tab-layout">
        <div id="tabs"></div>
        <div id="tab" class="tab">
            <div id="grid"></div>
        </div>
    </div>
</div>
<?php 
}

function wpcm_cmd_hook(){
    if( current_user_can('moderate_comments') && isset($_GET['page']) && $_GET['page'] == 'wordpress-comments-manager' && isset($_POST['cmd']) ){
        if ( isset($_POST['wpcm_nonce']) && wp_verify_nonce($_POST['wpcm_nonce'], 'wordpress-comments-manager') ) {
            unset($_POST['wpcm_nonce']);
            switch ($_POST['cmd']) {
                case 'get':
                    if ( array_key_exists('recid', $_POST) ) {
                        $res = get_item($_POST);
                    } else {
                        $res = get_data($_POST);
                    }
                    break;
                case 'save':
                    if ( isset($_POST['status_changed']) ) {
                        $res = status_changed($_POST);
                    } elseif ( isset($_POST['view']) ) {
                        $res = get_view_url($_POST);
                    } else {
                        $res = change_comment($_POST);
                    }
                    break;
                case 'delete':
                    $res = delete_comments($_POST);
                    break;
                default:
                    $res = Array();
                    $res['status']   = 'error';
                    $res['message']  = 'Command "'.$_POST['cmd'].'" is not recognized.';
                    $res['postData'] = $_POST;
                    break;
            }
        } else {
            $res = Array();
            $res['status']  = 'error';
            $res['message'] = 'Access Deny';
        }
        wp_send_json($res);
        exit;
    }
}
add_action('admin_init', 'wpcm_cmd_hook');

?>