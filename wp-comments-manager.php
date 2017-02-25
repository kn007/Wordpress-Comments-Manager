<?php 
/*
Plugin Name: Wordpress Comments Manager
Plugin URI: https://kn007.net/topics/new-wordpress-comment-management-system/
Version: 1.6
Description: Wordpress Comments Manager help you to quickly find comments and manage comments. It can be very convenient to review selected comments, open the comment in a new window, reply comment, edit comment and delete comments. See the screenshots for more details.
Author: kn007
Author URI: https://kn007.net/
*/

if ( !defined('ABSPATH') ) exit;

if ( version_compare( $GLOBALS['wp_version'], '3.6', '<' ) ) wp_die('Wordpress version too old. Please upgrade your Wordpress.');

define('WPCM_VERSION', '1.6');
define('WPCM_ENABLE_EXPERIMENTAL_FEATURES', false);

wp_register_script( 'wpcm', plugins_url("wpcm.js", __FILE__), 'jquery', WPCM_VERSION );
wp_register_style( 'wpcm', plugins_url("wpcm.css", __FILE__), array(), WPCM_VERSION );

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
    wp_enqueue_script( 'wpcm' );
}

function wpcm_style() {
    wp_enqueue_style( 'wpcm' );
}

function wpcm_bar($wp_admin_bar) {
    $args = array(
        'id'    => 'wordpress-comments-manager',
        'title' => 'Comments Manager',
        'href'  => admin_url("admin.php?page=wordpress-comments-manager")
    );
    if (wp_is_mobile()) $args = array_merge( $args, array('parent' => 'site-name') );
    $wp_admin_bar->add_node( $args );
}
add_action( 'admin_bar_menu', 'wpcm_bar', 99999 );

function wpcm_get_item($order) {
    global $wpdb;
    if (!isset($order['name'])) $order['name'] = '';
    switch ($order['name']) {
        case 'editform':
            $record = $wpdb->get_row( "SELECT `comment_ID`,`comment_post_ID`,`comment_author`,`comment_author_email`,`comment_author_url`,`comment_content` FROM `".$wpdb->comments."` WHERE `comment_ID`=".absint($order['recid']) );
            break;
        case 'replyform':
            $record = $wpdb->get_row( "SELECT `comment_ID`,`comment_post_ID`,`comment_content` FROM `".$wpdb->comments."` WHERE `comment_ID`=".absint($order['recid']) );
            break;
        default:
            $res            = Array();
            $res['status']  = 'error';
            $res['message'] = '';
            return $res;
    }
    $res              = Array();
    $res['status']    = 'success';
    $res['record']    = $record;
    return $res;
}

function wpcm_get_data($order, $_limited = false) {
    global $wpdb;
    // prepare search
    if ($_limited) {
        $order['approved'] = 'comment_approved="1"';
    } else {
        if (!isset($order['approved'])) $order['approved'] = '';
        switch ($order['approved']) {
            case 'unanswered':
                $order['approved'] = "comment_ID NOT IN (SELECT comment_parent FROM `".$wpdb->comments."` WHERE user_id != 0 AND comment_parent != 0) AND comment_approved IN (0,1) AND user_id = 0";
                break;
            case 'approve':
            case '1':
                $order['approved'] = 'comment_approved="1"';
                break;
            case 'hold':
            case '0':
                $order['approved'] = 'comment_approved="0"';
                break;
            case 'spam':
                $order['approved'] = 'comment_approved="spam"';
                break;
            case 'trash':
                $order['approved'] = 'comment_approved="trash"';
                break;
            case 'all':
            default:
                $order['approved'] = '';
                break;
        }
    }
    $where = $order['approved'];
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
    // prepare offset
    $order['limit']  = isset($order['limit'])  ? (int) $order['limit']  : 100;
    $order['offset'] = isset($order['offset']) ? (int) $order['offset'] : 0;
    // process sql
    if ($_limited) {
        $sql = "SELECT `comment_ID`,`comment_author`,`comment_content`,`comment_date` FROM `".$wpdb->comments."` WHERE ".$where;
    } else {
        $sql = "SELECT `comment_ID`,`comment_author`,`comment_author_email`,`comment_author_url`,`comment_content`,`comment_author_IP`,`comment_date`,`comment_agent` FROM `".$wpdb->comments."` WHERE ".$where;
    }
    $cql = "SELECT count(1) FROM ($sql) as grid_list_1";
    if ($order['offset'] == 0) {
        $sql .= " ORDER BY `comment_ID` DESC LIMIT ".$order['limit'];
    }else{
        $sql .= " AND `comment_ID`<=(";
        $sql .= "SELECT `comment_ID` FROM `".$wpdb->comments."` WHERE ".$where." ORDER BY `comment_ID` DESC LIMIT ".$order['offset'].",1";
        $sql .= ") ORDER BY `comment_ID` DESC LIMIT ".$order['limit'];
    }
    $count = $wpdb->get_var($cql);
    $records = $wpdb->get_results($sql,ARRAY_A);
    // fix some data for w2ui
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

function wpcm_status_changed($order) {
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

function wpcm_get_view_url($order) {
    $res                  = Array();
    $ID                   = absint($order['selected'][0]);
    if ($ID != '0') {
        $view_url         = get_comment_link(absint($order['selected'][0]));
        $res['status']    = "success";
        $res['message']   = "";
        $res['view']      = $view_url;
    } else {
        $res['status']    = "error";
        $res['message']   = "Wrong comment ID";
    }
    return $res;
}

function wpcm_filter_comment($commentdata,$flag=0) {
    if ($flag == 1) {
        global $wpdb;
        $user = wp_get_current_user();
        if ( $user->exists() ) {
            $user_ID = $user->ID;
            if ( empty( $user->display_name ) )
                $user->display_name=$user->user_login;
            $comment_author       = $wpdb->_escape($user->display_name);
            $comment_author_email = $wpdb->_escape($user->user_email);
            $comment_author_url   = $wpdb->_escape($user->user_url);
            if ( current_user_can('unfiltered_html') ) {
                if ( !isset( $comment_data['_wp_unfiltered_html_comment'] ) || !wp_verify_nonce( $comment_data['_wp_unfiltered_html_comment'], 'unfiltered-html-comment_' . $comment_post_ID ) ) {
                    kses_remove_filters(); // start with a clean slate
                    kses_init_filters(); // set up the filters
                }
            }
        } else {
            return false;
        }
        $comment_content = isset($commentdata['reply_content']) ? trim($commentdata['reply_content']) : "";
        $comment_post_ID = isset($commentdata['comment_post_ID']) ? (int) $commentdata['comment_post_ID'] : 0;
        $comment_parent  = isset($commentdata['comment_ID']) ? absint($commentdata['comment_ID']) : 0;
        do_action('pre_comment_on_post', $comment_post_ID);
        $data = compact('comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content', 'comment_parent','user_ID');
    }else{
        $comment_ID = isset($commentdata['comment_ID']) ? absint($commentdata['comment_ID']) : 0;
        $comment_post_ID = isset($commentdata['comment_post_ID']) ? (int) $commentdata['comment_post_ID'] : 0;
        $comment_author = isset($commentdata['comment_author']) ? trim(strip_tags($commentdata['comment_author'])) : "";
        $comment_author_email = isset($commentdata['comment_author_email']) ? trim($commentdata['comment_author_email']) : "";
        $comment_author_url = isset($commentdata['comment_author_url']) ? trim($commentdata['comment_author_url']) : "";
        $comment_content = isset($commentdata['comment_content']) ? trim($commentdata['comment_content']) : "";
        $data = compact('comment_ID', 'comment_post_ID', 'comment_author', 'comment_author_email', 'comment_author_url', 'comment_content');
    }
    return $data;
}

function wpcm_reply_comment($order) {
    $res                = Array();
    $data               = wpcm_filter_comment($order['record'],1);
    if ( !$data ) {
        $res['status']  = "error";
        $res['message'] = "User ID error";
        return $res;
    }
    $post               = get_post( $data['comment_post_ID'] );
    if ( !$post ) {
        $res['status']  = "error";
        $res['message'] = "Post ID error";
        return $res;
    }
    if ( empty( $post->post_status ) || empty( $post->comment_status ) ) {
        $res['status']  = "error";
        $res['message'] = "Post status error";
        return $res;
    } elseif ( in_array($post->post_status, array('draft', 'pending', 'trash') ) ) {
        $res['status']  = "error";
        $res['message'] = "You are replying to a comment on a draft post.";
        return $res;
    }
    if ( get_option( 'require_name_email' ) ) {
        if ( 6 > strlen( $data['comment_author_email'] ) || '' == $data['comment_author'] ) {
            $res['status']  = "error";
            $res['message'] = "Please fill the required fields (name, email).";
            return $res;
        }
    }
    if ( '' == $data['comment_content'] ) {
        $res['status']  = "error";
        $res['message'] = "Please type a comment.";
        return $res;
    }
    $parent = get_comment( $data['comment_parent'] );
    if ( $parent && $parent->comment_post_ID != $data['comment_post_ID'] ) {
        $res['status']  = "error";
        $res['message'] = "Parent comment post ID is not consistent with child comment post ID.";
        return $res;
    }
    if ( $parent->comment_approved !== '1' ) wp_set_comment_status( $parent, 'approve' );
    $comment_id         = wp_new_comment( $data, true );
    $res['status']      = ($comment_id ? "success" : "error");
    $res['message']     = "";
    return $res;
}

function wpcm_edit_comment($order) {
    $res                = Array();
    if ( get_option( 'require_name_email' ) ) {
        if ( 6 > strlen( $order['record']['comment_author_email'] ) || '' == $order['record']['comment_author'] ) {
            $res['status']  = "error";
            $res['message'] = "Please fill the required fields (name, email).";
            return $res;
        }
    }
    if ( '' == $order['record']['comment_content'] ) {
        $res['status']  = "error";
        $res['message'] = "Please type a comment.";
        return $res;
    }
    $data               = wpcm_filter_comment($order['record']);
    $rval               = wp_update_comment($data);
    $res['status']      = ($rval !== false ? "success" : "error");
    $res['message']     = "";
    return $res;
}

function wpcm_delete_comments($order) {
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

function wpcm_page() {
?>
<script type="text/javascript">
jQuery(document).ready(function($){var config={tabs:{name:"tabs",active:"tab1",tabs:[{id:"tab1",caption:"All"},{id:"tab2",caption:"Moderated"},{id:"tab3",caption:"Approved"},{id:"tab4",caption:"Spam"},{id:"tab5",caption:"Trash"},{id:"tab6",caption:"Unanswered"}],onClick:function(a){switch(w2ui.grid.reset(),a.target){case"tab1":w2ui.grid.postData.approved="all";break;case"tab2":w2ui.grid.postData.approved="0";break;case"tab3":w2ui.grid.postData.approved="1";break;case"tab4":w2ui.grid.postData.approved="spam";break;case"tab5":w2ui.grid.postData.approved="trash";break;case"tab6":w2ui.grid.postData.approved="unanswered"}w2ui.grid.searchReset()}},grid:{name:"grid",url:'<?php echo admin_url("admin.php?page=wordpress-comments-manager"); ?>',method:"POST",columns:[{field:"comment_author",caption:"Author",size:"80px",resizable:!0},{field:"comment_author_email",caption:"Email",size:"130px",resizable:!0},{field:"comment_author_url",caption:"Site",size:"120px",resizable:!0,render:function(a){return'<div><a href="'+a.comment_author_url+'" target="_blank">'+a.comment_author_url+"</a></div>"}},{field:"comment_content",caption:"Content",size:"250px"},{field:"comment_author_IP",caption:"IP",size:"120px",resizable:!0},{field:"comment_date",caption:"Date",size:"150px",render:"datetime:yyyy-mm-dd|hh24:mm:ss",resizable:!0},{field:"comment_agent",caption:"Agent",size:"30%"}],searches:[{field:"comment_author",caption:"Author",type:"text",operator:"contains"},{field:"comment_author_email",caption:"Email",type:"text",operator:"contains"},{field:"comment_author_url",caption:"Site",type:"text",operator:"contains"},{field:"comment_content",caption:"Content",type:"text",operator:"contains"},{field:"comment_author_IP",caption:"IP",type:"text",operator:"contains"},{field:"comment_agent",caption:"Agent",type:"text",operator:"contains"},{field:"comment_date",caption:"Date",type:"datetime",operator:"less"}],postData:{approved:"all",wpcm_nonce:"<?php echo wp_create_nonce('wordpress-comments-manager'); ?>"},toolbar:{items:[{type:"break",id:"break1"},{type:"button",id:"w2ui-reply",text:"Reply",icon:"w2ui-icon-pencil",tooltip:"Reply This Comment",disabled:!0},{type:"button",id:"w2ui-open",text:"<?php if (!wp_is_mobile()) echo 'View'; ?>",icon:"w2ui-icon-info",tooltip:"View This Comment",disabled:!0},{type:"menu",id:"w2ui-status",text:"<?php if (!wp_is_mobile()) echo 'Set Status'; ?>",icon:"w2ui-icon-settings",tooltip:"Change Comment Status",disabled:!0,items:[{id:"approve",text:"Approve",icon:"w2ui-icon-check"},{id:"hold",text:"Hold",icon:"w2ui-icon-reload"},{id:"spam",text:"Spam",icon:"w2ui-icon-colors"},{text:"--"},{id:"trash",text:"Trash",icon:"w2ui-icon-cross"}]},{type:"break",id:"break2"},{type:"button",id:"w2ui-edit",text:"Edit",icon:"w2ui-icon-pencil",tooltip:"Edit This Comment",disabled:!0},{type:"button",id:"w2ui-delete",text:"<?php if (!wp_is_mobile()) echo 'Delete'; ?>",icon:"w2ui-icon-cross",tooltip:"Delete Comments",disabled:!0}],onClick:function(a){switch(a.target){case"w2ui-status:approve":w2ui.grid.postData.status_changed="approve",w2ui.grid.save(),delete w2ui.grid.postData.status_changed;break;case"w2ui-status:hold":w2ui.grid.postData.status_changed="hold",w2ui.grid.save(),delete w2ui.grid.postData.status_changed;break;case"w2ui-status:spam":w2ui.grid.postData.status_changed="spam",w2ui.grid.save(),delete w2ui.grid.postData.status_changed;break;case"w2ui-status:trash":w2ui.grid.postData.status_changed="trash",w2ui.grid.save(),delete w2ui.grid.postData.status_changed;break;case"w2ui-reply":w2ui.replyform.clear();var sel=w2ui.grid.getSelection();w2ui.replyform.recid=sel[0],w2popup.open({body:'<div id="form" style="width: 100%; height: 100%;position: absolute; left: 0px; top: 0px; right: 0px; bottom: 0px;"></div>',width:500,height:360,onOpen:function(a){a.onComplete=function(){$("#w2ui-popup #form").w2render("replyform")}},onKeydown:function(a){null!=a.originalEvent&&a.originalEvent.ctrlKey&&13==a.originalEvent.keyCode&&$('#w2ui-popup .w2ui-form-box .w2ui-btn-blue').focus().click()}});break;case"w2ui-open":w2ui.grid.postData.view="get",w2ui.grid.save(),delete w2ui.grid.postData.view}}},onDblClick:function(a){w2ui.grid_toolbar.click('w2ui-reply')},onEdit:function(a){w2ui.editform.clear(),w2ui.editform.recid=a.recid,w2popup.open({body:'<div id="form" style="width:100%;height:100%;position:absolute;left:0px;top:0px;right:0px;bottom:0px;"></div>',width:380,height:420,onOpen:function(a){a.onComplete=function(){$("#w2ui-popup #form").w2render("editform")}},onKeydown:function(a){null!=a.originalEvent&&a.originalEvent.ctrlKey&&13==a.originalEvent.keyCode&&$('#w2ui-popup .w2ui-form-box .w2ui-btn-blue').focus().click()}})},onSave:function(event){event.onComplete=function(){void 0!==event.xhr&&(data=eval("("+event.xhr.responseText+")"),void 0!==data.view?window.open(data.view):"success"==data.status&&(w2ui.grid.selectNone(),"all"!==w2ui.grid.postData.approved&&w2ui.grid.reload()))}}},editform:{name:"editform",header:"Edit Comment",url:'<?php echo admin_url("admin.php?page=wordpress-comments-manager"); ?>',method:"POST",fields:[{name:"comment_author",type:"text",required:!0,html:{caption:"Author",attr:'style="width:90%"'}},{name:"comment_author_email",type:"email",<?php if (get_option('require_name_email')) echo 'required:!0,'; ?>html:{caption:"Email",attr:'style="width:90%"'}},{name:"comment_author_url",type:"text",html:{caption:"Site",attr:'style="width:90%"'}},{name:"comment_content",type:"textarea",required:!0,html:{caption:"Content",attr:'style="width:90%;height:180px;resize:none"'}}],postData:{wpcm_nonce:"<?php echo wp_create_nonce('wordpress-comments-manager'); ?>"},actions:{Close:function(){this.clear(),w2popup.close()},Update:function(){var a=this.validate();a.length>0||this.save({},function(a){w2popup.close(),"success"==a.status?(w2ui.grid.reload(w2ui.grid.select(w2ui.editform.recid),w2ui.grid.scrollIntoView(w2ui.grid.get(w2ui.editform.recid,!0)))):w2alert("Update Failed!"),w2ui.editform.clear()})}}},replyform:{name:"replyform",header:"Reply Comment",url:'<?php echo admin_url("admin.php?page=wordpress-comments-manager"); ?>',method:"POST",focus:2,fields:[{name:"comment_content",type:"textarea",disabled:!0,html:{caption:"Comment",attr:'style="width:90%;height:100px;resize:none" readonly'}},{name:"reply_content",type:"textarea",required:!0,html:{caption:"Response",attr:'style="width:90%;height:125px;resize:none" autofocus'}}],postData:{reply:"action",wpcm_nonce:"<?php echo wp_create_nonce('wordpress-comments-manager'); ?>"},actions:{Close:function(){this.clear(),w2popup.close()},Reply:function(){var a=this.validate();a.length>0||this.save({},function(a){w2popup.close(),"success"==a.status?w2ui.grid.reload():w2alert("Submit Failed!"),w2ui.replyform.clear()})}}}};$("#tabs").w2tabs(config.tabs),$("#tab").show(),$("#grid").w2grid(config.grid),$().w2form(config.editform),$().w2form(config.replyform)<?php if (wp_is_mobile()) echo ",w2ui.grid.toolbar.hide('w2ui-column-on-off','w2ui-search-advanced','w2ui-reply','w2ui-edit');"; ?>});
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
<div><p><strong>Version:</strong> <?php echo WPCM_VERSION; ?> (<a target="_blank" href="https://wordpress.org/plugins/wp-comments-manager/changelog/">Changelog</a>)</p></div>
<?php 
}

function wpcm_cmd_hook() {
    if( current_user_can('moderate_comments') && isset($_GET['page']) && $_GET['page'] == 'wordpress-comments-manager' && isset($_POST['cmd']) ) {
        if ( isset($_POST['wpcm_nonce']) && wp_verify_nonce($_POST['wpcm_nonce'], 'wordpress-comments-manager') ) {
            unset($_POST['wpcm_nonce']);
            switch ($_POST['cmd']) {
                case 'get':
                    if ( array_key_exists('recid', $_POST) ) {
                        $res = wpcm_get_item($_POST);
                    } else {
                        $res = wpcm_get_data($_POST);
                    }
                    break;
                case 'save':
                    if ( isset($_POST['status_changed']) ) {
                        $res = wpcm_status_changed($_POST);
                    } elseif ( isset($_POST['view']) ) {
                        $res = wpcm_get_view_url($_POST);
                    } elseif ( isset($_POST['reply']) ) {
                        $res = wpcm_reply_comment($_POST);
                    } else {
                        $res = wpcm_edit_comment($_POST);
                    }
                    break;
                case 'delete':
                    $res = wpcm_delete_comments($_POST);
                    break;
                default:
                    $res = Array();
                    $res['status']   = 'error';
                    $res['message']  = 'This command is not recognized.';
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

// ============== experimental features ==============
if (WPCM_ENABLE_EXPERIMENTAL_FEATURES) {
    $wpcm_shortcode = new wpcm_Shortcode;
    $wpcm_shortcode->init();
}

class wpcm_Shortcode {
    protected $instance = 0;
 
    public function init() {
        add_action( 'init', 'wpcm_cmd_hook_for_shortcode' );
        add_shortcode( 'wpcm_search_box', array( $this, 'wpcm_search_comments' ) );
    }
     
    public function wpcm_search_comments( $atts = array(), $content = '' ) {
        $this->instance++;
        $atts = shortcode_atts( 
            array(
                'height'    => 480
            ), $atts, 'wpcm_search_comments' );
        $atts['height']     = absint( $atts['height'] );

        if ( 1 === $this->instance ) {
            wp_enqueue_style( 'wpcm' );
            wp_enqueue_script( 'jquery' );
            wp_enqueue_script( 'wpcm' );
        }

        $content = '<style>#wpcm_comments_search{width:99%;height:'.$atts['height'].'px;}</style><script type="text/javascript">jQuery(document).ready(function(a){a("#wpcm_comments_search").w2grid({name:"box",header:"Comments Search Box",url:"'.home_url().'/",method:"POST",show:{header:!0,toolbarColumns:!1,toolbarInput:!1,toolbarReload:!1},columns:[{field:"comment_author",caption:"Author",size:"80px",resizable:!0},{field:"comment_content",caption:"Content",size:"100%",resizable:!0},{field:"comment_date",caption:"Date",size:"150px",render:"datetime:yyyy-mm-dd|hh24:mm:ss",resizable:!0}],searches:[{field:"comment_author",caption:"Author",type:"text",operator:"contains"},{field:"comment_content",caption:"Content",type:"text",operator:"contains"},{field:"comment_date",caption:"Date",type:"datetime",operator:"less"}],sortData:[{field:"comment_ID",direction:"desc"}],postData:{wpcm_box:"wpcm_search_comments",wpcm_nonce:"'.wp_create_nonce('wordpress-comments-manager').'"}})});</script><div id="wpcm_comments_search"></div>';

        return $content;
    }
}

function wpcm_cmd_hook_for_shortcode() {
    if( isset($_POST['wpcm_nonce']) && isset($_POST['wpcm_box']) && isset($_POST['cmd']) ) {
        if ( wp_verify_nonce($_POST['wpcm_nonce'], 'wordpress-comments-manager') ) {
            unset($_POST['wpcm_nonce']);
            switch ($_POST['cmd']) {
                case 'get':
                    if (isset($_POST['searchLogic'])) {
                        $res = wpcm_get_data($_POST, true);
                    } else {
                        $res = Array();
                        $res['status']  = 'success';
                        $res['message'] = '';
                        $res['total']   = '';
                        $res['records'] = Array();
                    }
                    break;
                default:
                    $res = Array();
                    $res['status']   = 'error';
                    $res['message']  = 'You don\'t have enough clearance to access.';
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

?>