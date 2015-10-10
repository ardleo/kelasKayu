<?php 
/*
Plugin Name: Approval Workflow
Plugin URI: http://www.judenware.com/projects/wordpress/approval-workflow/
Description: Plugin that checks if user has permissions to publish pages/posts/custom post types. If they don't, it will send it to review by someone who can publish.
Author: ericjuden
Version: 1.3.2
Author URI: http://ericjuden.com
Site Wide only: false
Network: false
*/

define('AW_PLUGIN_DIR', WP_PLUGIN_DIR . '/approval-workflow');
define('AW_PLUGIN_URL', plugins_url($path = '/approval-workflow'));

require_once(ABSPATH . 'wp-admin/includes/plugin.php');    // Needed for is_plugin_active_for_network()
require_once('options.class.php');
require_once('/class/workflow-reviewer.php');

class Approval_Workflow {
    var $is_network;
    var $options;
    private $done;
    private $old_post_content;
    
    function Approval_Workflow(){
        $this->is_network = (function_exists('is_plugin_active_for_network') ? is_plugin_active_for_network('approval-workflow/approval-workflow.php') : false);
        $this->options = new Approval_Workflow_Options('approval-workflow', $this->is_network);
        
        add_action('add_meta_boxes', array(&$this, 'fix_content'), 1, 2);
        add_action('admin_init', array(&$this, 'admin_init'));
        if($this->is_network){
            add_action('network_admin_menu', array(&$this, 'admin_menu_network'));
        }
        add_action('admin_menu', array(&$this, 'admin_menu'));
        add_action('admin_notices', array(&$this, 'admin_notices'));
        add_action('post_submitbox_misc_actions', array(&$this, 'post_submitbox_misc_actions'));
        add_action('save_post', array(&$this, 'save_post'), 1, 2);
        add_action('wp_restore_post_revision', array(&$this, 'restore_post_revision'), 1, 2);        
    }
    
    function admin_init(){   
       
        if(!isset($this->options->approval_role)){
            $this->options->approval_role = 'administrator';
            $this->options->save();
        }
        
        if(!isset($this->options->force_workflow)){
            $this->options->force_workflow = 'true';
            $this->options->save();
        }
        
        //register stylesheet
        wp_register_style('approval-workflow-style', AW_PLUGIN_URL . '/css/style.css' );
        wp_enqueue_style( 'approval-workflow-style' );
    }
    
    
    function admin_menu(){
        global $current_user;
	   $user_roles = $current_user->roles;
        
        if(!$this->is_network){
            add_options_page(_('Approval Workflow Options'), _('Approval Workflow'), 'manage_options', 'approval-workflow-options', array(&$this, 'plugin_options'));
        }
        // we need allow Administrator to have access to the workflow
        // unfortunately wordpress doesn't allow to add multiple role on a page, so we need to assign only one role
        if ( in_array( 'administrator', $user_roles ) ){
            add_menu_page(_('Workflow'), _('Workflow'), 'administrator', 'approval-workflow', array(&$this, 'view_workflow'), AW_PLUGIN_URL . '/images/arrow_join.png');
        } else {
            
             add_menu_page(_('Workflow'), _('Workflow'), $this->options->approval_role, 'approval-workflow', array(&$this, 'view_workflow'), AW_PLUGIN_URL . '/images/arrow_join.png');
        }
        
        
    }
    
    function admin_menu_network(){
        add_submenu_page('settings.php', _('Approval Workflow Options'), _('Approval Workflow'), 'manage_network_options', 'approval-workflow-options', array(&$this, 'plugin_options'));
	    add_menu_page(_('Workflow'), _('Workflow'), 'manage_sites', 'approval-workflow', array(&$this, 'view_workflow'), AW_PLUGIN_URL . '/images/arrow_join.png');
    }
    
    function admin_notices(){
        global $pagenow, $post;
        
        if($pagenow == 'post.php'){
            $custom = get_post_custom($post->ID);
            
            $waiting_for_approval = __('This item is currently in the workflow. The content on this page might not reflect what is on the actual website.', 'approval-workflow');
            $in_progress = __('This item is currently in progress. The content on this page might not reflect what is on the actual website.', 'approval-workflow');
            
            if(isset($custom['_waiting_for_approval']) && $custom['_waiting_for_approval'][0]){
?>
				<div class='error'>
					<p><?php echo apply_filters('approval_workflow_message_waiting_for_approval', $waiting_for_approval, $post); ?></p>
				</div>
<?php
            } else {
                if(isset($custom['_in_progress']) && $custom['_in_progress'][0]){
?>
					<div class='updated'>
						<p><?php echo apply_filters('approval_workflow_message_in_progress', $in_progress, $post); ?></p>
					</div>
<?php
                }
            }
        }
    }
    
    function clear_post_workflow_settings($post_id){
        // Clear meta values for approval and editing in progress
        update_post_meta($post_id, '_in_progress', 0);
        update_post_meta($post_id, '_waiting_for_approval', 0);
    }
    
    /**
     * 
     * Using this as a hack to show the correct content on the edit page while editing
     * @param string $post_type
     * @param mixed $post
     */
    function fix_content($post_type, $post){
        $custom = get_post_custom($post->ID);
        
        if(isset($custom['_in_progress']) && $custom['_in_progress'][0] == 1){
            $last_revision = array_pop(wp_get_post_revisions($post->ID, array('posts_per_page' => 1)));
            if(!empty($last_revision) && !empty($last_revision->post_content)){
                $post->post_content = $last_revision->post_content;
            }
        }
    }
    
    function notify_approvers($post){
        global $current_user;
        
        // Get list of users in approval role...if none found, use administrator role instead
	    $approvers = get_users(array('role' => $this->options->approval_role));
	    if(empty($approvers)){    // no approvers found...use administrators role
            $approvers = get_users(array('role' => 'administrator'));
	    }
	    
	    $to_emails = array();
	    foreach($approvers as $approver){
	        $to_emails[$approver->ID] = $approver->user_email;
	    }
	    
	    if(empty($to_emails)){    // Nobody to notify...exit
	        return false;
	    }
	
		// Send email
		$headers = "From: {$current_user->display_name} <{$current_user->user_email}>\r\n";
		return wp_mail($to_emails, $this->notify_subject($post), $this->notify_body($post), $headers);
    }
    
    function notify_subject($post){
        $subject .= sprintf(__('[%s] New Modifications to %s'), get_bloginfo('name'), $post->post_title);
        return apply_filters('approval_workflow_notify_subject', $subject, $post);
    }
    
    function notify_body($post){
        global $wpdb, $current_user;
        
        $last_revision = $wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->posts WHERE post_parent=%d AND post_type=%s ORDER BY post_date DESC LIMIT 1", $post->ID, 'revision'));
        $revision_compare_link = admin_url( 'revision.php?action=diff&post_type=' . $post->post_type . '&right=' . $post->ID . '&left=' . $last_revision );
        
        $body .= sprintf(__( "New changes have been made to \"%s\" at <%s>. "), $post->post_title, get_permalink($post->ID));
        $body .= sprintf(__("%s has submitted changes to the workflow for review. These changes will not appear on the website until you approve them.\r\n\r\n"), $current_user->display_name);
        $body .= sprintf(__("The new content of the page is shown below if you would like to review it. You can also review/approve the changes at %s.\r\n\r\n"), $revision_compare_link);
        $body .= __("Revisions\r\n==========================================");
        $body .= $post->post_content;
        
        return apply_filters('approval_workflow_notify_body', $body, $post);
    }
    
    function plugin_options(){
?>
	<div class="wrap">
	<h2><?php _e('Approval Workflow Options')?></h2>
	
	<?php 
	$action = "";
	if(isset($_GET['action'])){
		$action = $_GET['action'];
	}
	
	$current_page = substr($_SERVER["SCRIPT_NAME"],strrpos($_SERVER["SCRIPT_NAME"],"/")+1);
	
	switch($action){	    
		case "update":
		    if(isset($_POST['approval_role'])){
		        $this->options->approval_role = $_POST['approval_role'];
		    }
            
            if(isset($_POST['force_workflow'])){
		        $this->options->force_workflow = $_POST['force_workflow'];
		    }else{
                $this->options->force_workflow = 'off';
            }
		    
            
		    $this->options->save();
    ?>
    		<script>
				window.location="<?php echo $current_page ?>?page=approval-workflow-options&updated=true&updatedmsg=<?php echo urlencode(__('Settings Saved')); ?>";
			</script>
    <?php
		    break;
		    
        default:
    ?>
    		<form method="post" action="<?php echo $current_page ?>?page=approval-workflow-options&action=update">
    		<?php wp_nonce_field('update-options'); ?>
    		<table class="form-table">
                <tr valign="top">
                    <th scope="row">
                        <strong><?php _e('Approval Role'); ?></strong><br />
                        <em><?php _e('Which role will be approving the edits?')?></em>
                    </th>
                    <td>
                        <select name="approval_role" id="approval_role">
                        <?php wp_dropdown_roles($this->options->approval_role); ?>
                        </select>
                    </td>
                </tr>
                <tr valign="top">
                    <th scope="row">
                        
                        <em><?php _e('Set to true if all post has to go to approval workflow')?></em>
                    </th>
                    <td>
                        <input name="force_workflow" id="force_workflow" type="checkbox" <?php echo $this->options->force_workflow =='on' ? 'checked' : '' ; ?> />
                        <strong><?php _e('Force Workflow'); ?></strong><br />
                    </td>
                </tr>
    		</table>
    		<input type="hidden" name="action" value="update" />
    		<input type="hidden" name="page_options" value="approval_role" />
    		<?php settings_fields('approval-workflow_group'); ?>
			<p class="submit"><input type="submit" class="button-primary" value="<?php _e('Save Changes'); ?>" /></p>
    		</form>
    <?php
        break;
	}
		    
    ?>
    </div>
<?php
    }
    
    function post_submitbox_misc_actions(){
        global $post;
        
        $currentScreen = get_current_screen();
        
		// Only show if people don't have publish permissions
		//if(!current_user_can('publish_' . $post->post_type . 's')){    // WordPress always appends an 's' to the end of the capability  )){
        
        if ($this->options->force_workflow != 'on'){
?>
		<div class="misc-pub-section misc-pub-section-last">
			<input type="checkbox" name="aw_submit_to_workflow" id="aw_submit_to_workflow" value="1" /><label for="aw_submit_to_workflow"><strong><?php _e('Submit to Workflow'); ?></strong></label> 
		</div>
<?php
		}else{
           ?>
		<div class="misc-pub-section misc-pub-section-last" style="display:none">
			<input type="checkbox" name="aw_submit_to_workflow" id="aw_submit_to_workflow" value="1" checked disabled /><label for="aw_submit_to_workflow" style="color: #999;"><strong><?php _e('Submit to Workflow'); ?></strong></label> 
		</div>
<?php 
        }
        
        
        if( $currentScreen->action != 'add' ) {
         // show reviewers on edit mode only
           
?>
        <div class="misc-pub-section misc-pub-section-last">
                <?php echo self::render_reviewer_list(  self::getReviewer( $post->ID, $this->options->approval_role ) ); ?> 
                </div>
<?php
        }
    }
    
    function save_post($post_id, $post){
        global $wpdb;
        $is_new = false; 
        
        //remove_action( 'save_post', array(&$this, 'save_post'), 1, 2 );
        
        if($this->done){
            return $post_id;
        }
        
        if(defined('DOING_AUTOSAVE') && DOING_AUTOSAVE){
    		return $post_id;
		}
		if($post->post_status == 'auto-draft' || $post->post_status == 'inherit'){
			return $post_id;
		}
		
		
            
		// if using workflow
        //if((isset($_POST['aw_submit_to_workflow']) && $_POST['aw_submit_to_workflow'] == 1)){
		if(!current_user_can('publish_' . $post->post_type . 's')){    // WordPress always appends an 's' to the end of the capability  
            
            
            if ( $post->post_status == 'pending' )  {
                $this->resetApproval($post_id);
            }
            
            
            // Get custom fields
            $custom = get_post_custom($post_id);
		    
            // Get revisions from db
            $revisions = wp_get_post_revisions($post_id, array('posts_per_page' => 1));
            $last_revision = array_pop($revisions);    		
    		
            // Is this a new post
            if(count($revisions) <= 1 || empty($last_revision) || empty($last_revision->post_content)){
    			$is_new = true;
    		}    		
    		
           // wp_update_post( array( 'ID' => $post_id, 'post_status' => 'pending' ) );
            
            
    		if(!$is_new){
        		// Set published content to previous version
        		$last_revision = $last_revision->ID;
        		wp_restore_post_revision($last_revision);
    		}
            
            
    		update_post_meta($post_id, '_in_progress', 1);
    		
    		// If submitting to workflow
            if((isset($_POST['aw_submit_to_workflow']) && $_POST['aw_submit_to_workflow'] == 1)){
                update_post_meta($post_id, '_waiting_for_approval', 1);
                $this->notify_approvers($post);
    		}
            
		} else {
            if ( $post->post_status == 'publish' ){
                if ( ($this->options->force_workflow == 'on') && !$this->isAllApproved($post_id) ){
                    // reset to pending if nobody yet approved
                    wp_update_post( array( 'ID' => $post_id, 'post_status' => 'pending' ) );
                }else{
                    $this->done = true;
                    $this->clear_post_workflow_settings($post_id);
                }               
            } else if (  $post->post_status == 'pending' ) {
                $this->resetApproval($post_id);
                update_post_meta($post_id, '_in_progress', 1);
                update_post_meta($post_id, '_waiting_for_approval', 1);
            }
		}
		
		return $post_id;
    }
    
    function restore_post_revision($post_id, $revision_id){
        $custom = get_post_custom($post_id);
        
        // Clear meta values for approval and editing in progress
        $this->clear_post_workflow_settings($post_id);
    }
    
    static function render_reviewer_list($reviewers){
        $output = '<div class="reviewer-list">';
        foreach( $reviewers as $reviewer ){
            $cssClass = $reviewer->approval_status ? 'badge-approved' : '';
            $output.= '<span class="reviewer tooltip" data-tip="'. $reviewer->display_name. '"><span class="'. $cssClass . '"></span>'. get_avatar($reviewer->ID,30). '</span>';
        }
        $output .= '</div>';
        return $output;
    }
    
    static function didIapprove($postId){
        return in_array(  get_current_user_id(), get_post_meta ( $postId, '_approved_by' ) );
    }
    
    function resetApproval($postId){
        return delete_post_meta($postId, '_approved_by');
    }
    
    function isAllApproved($postId){
        $reviewers = self::getReviewer($postId, $this->options->approval_role);
        $reviewers_that_already_approved = array_filter( $reviewers, function($reviewer) { var_dump($reviewer); if ( $reviewer->approval_status ){ return $reviewer; } });
      
        return (count($reviewers) == count($reviewers_that_already_approved) ? true : false);
    }
    
    static function getReviewer($postId, $approval_role = 'administrator'){
        $output = array();
        $reviewers = get_users( 'role=' . $approval_role );
        $reviewerIds_that_already_approved = get_post_meta( $postId, '_approved_by' );
        $reviewers_that_already_approved = array();   
        
        foreach( $reviewers as $reviewer ){   
            $_reviewer = new WorkflowReviewer();
            $_reviewer->ID = $reviewer->ID;
            $_reviewer->display_name = $reviewer->display_name;
            $_reviewer->post_id = $postId;
            
            if ( in_array( $reviewer->ID, $reviewerIds_that_already_approved ) ){
                $_reviewer->approval_status = true;
            } else {
                $_reviewer->approval_status = false;
            }
            
            array_push( $reviewers_that_already_approved, $_reviewer );
        }
                   
        // wordpress doesn't allow multiple roles apply to a user
        // so we may want to display the users that have permission to approve
        // the user shows up as reviewer only when he/she have approved the post review (the user role more likely a administrator)
        $extra_reviewers = array();
        foreach( $reviewerIds_that_already_approved as $reviewerId ){
            $isListedAsReviewer = false;
            foreach( $reviewers_that_already_approved as $reviewer ){
                if ( $reviewer->ID == $reviewerId ){
                    $isListedAsReviewer = true;
                    break;   
                }
            }
            
            if ( !$isListedAsReviewer ){
                $userdata = get_userdata($reviewerId);
                if ( $userdata ){
                    $_reviewer = new WorkflowReviewer();
                    $_reviewer->ID = $userdata->ID;
                    $_reviewer->display_name = $userdata->display_name;
                    $_reviewer->post_id = $postId;
                    $_reviewer->approval_status = true;
                    array_push( $extra_reviewers, $_reviewer );
                }                
            }
        }
        
        if ( count($extra_reviewers) > 0){
             $reviewers_that_already_approved = array_merge( $reviewers_that_already_approved, $extra_reviewers );
        }
        
        return $reviewers_that_already_approved;
    }
    
    function view_workflow(){
        require_once(AW_PLUGIN_DIR . '/class/workflow-list-table.php');
        $workflow_list = new Workflow_List_Table();
        $action = "";
        if(isset($_GET['action'])){
            $action = $_GET['action'];
        }
               
        switch($action){
            case 'approve':
                $postId = "";
                if(isset($_GET['postId'])){
                    $postId = $_GET['postId'];
                }
                $approvals = get_post_meta ( $postId, '_approved_by' );
                if ( !self::didIApprove($postId) ){
                    // add the approval
                    add_post_meta( $postId, '_approved_by', get_current_user_id() );
                }
                break;
            case 'reject':
                $postId = "";
                if(isset($_GET['postId'])){
                    $postId = $_GET['postId'];
                }
                
                $approvals = get_post_meta ( $postId, '_approved_by' );
                
                if ( self::didIApprove($postId) ){
                    // remove the approval
                    delete_post_meta($postId, '_approved_by', get_current_user_id() );
                }
                
                break;
            default:
                break;
        }
?>
	<div class="wrap">
		<h2><?php _e('Items in Workflow')?></h2>
		<?php $workflow_list->prepare_items(); ?>
		<form method="post">
			<input type="hidden" name="page" value="approval-workflow" />
		    <?php $workflow_list->display(); ?>
		</form>
	</div>
<?php        
    }
}

$approval_workflow = new Approval_Workflow();
?>