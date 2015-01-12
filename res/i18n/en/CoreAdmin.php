<?php
namespace BitsTheater\res\en;
use BitsTheater\res\Resources as BaseResources;
{//begin namespace

class CoreAdmin extends BaseResources {

	public $menu_website_status_label = 'Website Status';
	public $menu_website_status_subtext = '';
	
	public $title_websiteStatus = 'Website Status';
	
	public $colheader_feature = 'Feature';
	public $colheader_curr_version = 'Version In Use';
	public $colheader_new_version = 'Version Should Be';
	public $colheader_update = 'Update?';
	
	public $btn_label_update = '<span class="glyphicon glyphicon-wrench"></span> Update!';
	public $btn_label_uptodate = '<span class="glyphicon glyphicon-ok"></span> Already up to date';
	
	public $msg_warning_backup_db = "UPDATING COULD RESULT IN DATA LOSS IF A PROBLEM ARISES!<br>\nBACKUP YOUR DATABASE BEFORE UPDATING!";
	public $msg_update_success = 'Update successful!';
	public $msg_copy_cfg_fail = "Cannot copy over [cfg]/%s. Please make it writeable and try again.";
	
	public $dialog_update_warning_title = 'WARNING!';
	public $dialog_update_warning_msg = '<p class="lead">Are you SURE you want to update this feature?</p><p class="label-warning">Have you backed up the database?</p>';
	public $dialog_update_warning_btn_cancel = 'Cancel';
	public $dialog_update_warning_btn_update = 'Yes, I am sure; Update already!';
	
	public $field_value_unknown_version = 'Unknown';
	
}//end class

}//end namespace
