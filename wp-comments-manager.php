<?php 
/*
Plugin Name:Wordpress Comments Manager
Plugin URI: https://kn007.net/topics/new-wordpress-comment-management-system/
Version: 1.0
Description: Wordpress Comments Manager help you to quickly find comments and manage comments. It can be very convenient to review selected comments, open the comment in a new window, edit comment and delete comments. See the screenshots for more details.
Author: kn007
Author URI: https://kn007.net/
*/

if ( !function_exists( 'add_action' ) ) { die(); }

function wp_comments_manager_menu() {
   if (current_user_can('manage_options')) 				
 		add_menu_page("Comments Manager","Comments Manager", 'manage_options', 'wordpress-comments-manager', 'wp_comments_manager_page');
}
add_action('admin_menu','wp_comments_manager_menu');

function wp_comments_manager_bar( $wp_admin_bar ) {
	$wp_admin_bar->add_node( array(
		'id'	=> 'wordpress-comments-manager',
		'title' => 'Comments Manager',
		'href'  => admin_url("admin.php?page=wordpress-comments-manager"),
		'parent'=> 'site-name'
	) );
}
add_action( 'admin_bar_menu', 'wp_comments_manager_bar', 99999 );

function wp_comments_manager_head(){
	global $plugin_page;
	if($plugin_page==='wordpress-comments-manager'){
		wp_dequeue_script('jquery');
?>
<script type="text/javascript" src="<?php echo plugins_url("jquery.min.js", __FILE__); ?>"></script>
<script type="text/javascript" src="<?php echo plugins_url("wpcm.js", __FILE__); ?>"></script>
<link rel="stylesheet" type="text/css" href="<?php echo plugins_url("wpcm.css", __FILE__); ?>" />
<script type="text/javascript">
$(function(){var config={tabs:{name:"tabs",active:"tab1",tabs:[{id:"tab1",caption:"All"},{id:"tab2",caption:"Moderated"},{id:"tab3",caption:"Approved"},{id:"tab4",caption:"Spam"},{id:"tab5",caption:"Trash"}],onClick:function(a){switch(w2ui.grid.reset(),a.target){case"tab1":w2ui.grid.postData.approved="all";break;case"tab2":w2ui.grid.postData.approved=0;break;case"tab3":w2ui.grid.postData.approved=1;break;case"tab4":w2ui.grid.postData.approved="spam";break;case"tab5":w2ui.grid.postData.approved="trash"}w2ui.grid.searchReset()}},grid:{name:"grid",url:'<?php echo plugins_url("wp-data.php", __FILE__); ?>',method:"POST",columns:[{field:"comment_author",caption:"Author",size:"80px",resizable:!0},{field:"comment_author_email",caption:"Email",size:"130px",resizable:!0},{field:"comment_author_url",caption:"Site",size:"120px",resizable:!0},{field:"comment_content",caption:"Content",size:"250px"},{field:"comment_author_IP",caption:"IP",size:"120px",resizable:!0},{field:"comment_date",caption:"Date",size:"150px",render:"datetime:yyyy-mm-dd|hh24:mm:ss",resizable:!0},{field:"comment_agent",caption:"Agent",size:"30%"}],searches:[{field:"comment_author",caption:"Author",type:"text",operator:"contains"},{field:"comment_author_email",caption:"Email",type:"text",operator:"contains"},{field:"comment_author_url",caption:"Site",type:"text",operator:"contains"},{field:"comment_content",caption:"Content",type:"text",operator:"contains"},{field:"comment_author_IP",caption:"IP",type:"text",operator:"contains"},{field:"comment_agent",caption:"Agent",type:"text",operator:"contains"},{field:"comment_date",caption:"Date",type:"datetime",operator:"less"}],sortData:[{field:"comment_ID",direction:"desc"}],postData:{approved:"all"},toolbar:{items:[{type:"break",id:"break1"},{type:"button",id:"w2ui-open",text:"<?php if (!wp_is_mobile()) echo 'View';?>",icon:"w2ui-icon-info",tooltip:"View This Comment",disabled:!0},{type:"button",id:"w2ui-edit",text:"Edit",icon:"w2ui-icon-pencil",tooltip:"Edit This Comment",disabled:!0},{type:"menu",id:"w2ui-status",text:"<?php if (!wp_is_mobile()) echo 'Set Status';?>",icon:"w2ui-icon-settings",tooltip:"Change Comment Status",disabled:!0,items:[{id:"approve",text:"Approve",icon:"w2ui-icon-check"},{id:"hold",text:"Hold",icon:"w2ui-icon-reload"},{id:"spam",text:"Spam",icon:"w2ui-icon-colors"},{text:"--"},{id:"trash",text:"Trash",icon:"w2ui-icon-cross"}]},{type:"break",id:"break2"},{type:"button",id:"w2ui-delete",text:"<?php if (!wp_is_mobile()) echo 'Delete';?>",icon:"w2ui-icon-cross",tooltip:"Delete Comments",disabled:!0}],onClick:function(a){switch(a.target){case"w2ui-status:approve":w2ui.grid.postData.status_changed="approve",w2ui.grid.save(),delete w2ui.grid.postData.status_changed;break;case"w2ui-status:hold":w2ui.grid.postData.status_changed="hold",w2ui.grid.save(),delete w2ui.grid.postData.status_changed;break;case"w2ui-status:spam":w2ui.grid.postData.status_changed="spam",w2ui.grid.save(),delete w2ui.grid.postData.status_changed;break;case"w2ui-status:trash":w2ui.grid.postData.status_changed="trash",w2ui.grid.save(),delete w2ui.grid.postData.status_changed;break;case"w2ui-open":w2ui.grid.postData.view="get",w2ui.grid.save(),delete w2ui.grid.postData.view}}},onEdit:function(a){w2ui.form.clear(),w2ui.form.recid=a.recid,w2popup.open({body:'<div id="form" style="width: 100%; height: 100%;position: absolute; left: 0px; top: 0px; right: 0px; bottom: 0px;"></div>',width:380,height:420,onOpen:function(a){a.onComplete=function(){$("#w2ui-popup #form").w2render("form")}}})},onSave:function(event){event.onComplete=function(){void 0!==event.xhr&&(data=eval("("+event.xhr.responseText+")"),void 0!==data.view?window.open(data.view):"success"==data.status&&(w2ui.grid.selectNone(),"all"!==w2ui.grid.postData.approved&&w2ui.grid.reload()))}}},form:{name:"form",header:"Edit Comment",url:'<?php echo plugins_url("wp-data.php", __FILE__); ?>',method:"POST",fields:[{name:"comment_author",type:"text",required:!0,html:{caption:"Author",attr:'style="width:90%"'}},{name:"comment_author_email",type:"email",required:!0,html:{caption:"Email",attr:'style="width:90%"'}},{name:"comment_author_url",type:"text",html:{caption:"Site",attr:'style="width:90%"'}},{name:"comment_content",type:"textarea",required:!0,html:{caption:"Content",attr:'style="width:90%;height:180px;resize:none"'}}],actions:{Close:function(){this.clear(),w2popup.close()},Update:function(){var a=this.validate();a.length>0||this.save({},function(a){w2popup.close(),"success"==a.status?(w2ui.grid.set(w2ui.form.recid,w2ui.form.record),w2alert("Update Successful!")):w2alert("Update Failed!"),w2ui.form.clear()})}}}};$("#tabs").w2tabs(config.tabs),$("#tab").show(),$("#grid").w2grid(config.grid),$().w2form(config.form)<?php if (wp_is_mobile()) echo ",w2ui.grid.toolbar.hide('w2ui-column-on-off', 'w2ui-search-advanced', 'w2ui-edit')";?>});
</script>
<?php 
	}
}
add_action('admin_head','wp_comments_manager_head');

function wp_comments_manager_page(){
?>
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
?>