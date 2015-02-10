<?php
namespace BitsTheater\res\en;
use BitsTheater\res\Permissions as BaseResources;
{//begin namespace

class BitsPermissions extends BaseResources {
	public $menu_rights_label = 'Permissions';
	public $menu_rights_subtext = '';
	
	public $title_groups = 'Assign Rights To Groups';
	public $colheader_group_id = '#';
	public $colheader_group_name = 'Rights Group';
	public $colheader_group_parent = 'Description';
	public $display_group_0_desc = 'visitor that is not logged in';
	public $display_group_1_desc = 'always passes permission checks';
	public $display_parent_group = 'subset of %s';
	
	public $title_group = 'Assign Rights for Group: %s';
	public $colheader_right_name = 'Right';
	public $colheader_right_value = 'Assign';
	public $colheader_right_desc = 'Description';
	public $colheader_group_reg_code = 'Matches Registration Code';
	
	public $anonymous_group_name = 'anonymous'; //name of "group 0" when not logged in so you can still assign rights

	public $label_right_values = array(
			'allow' => 'Allow',
			'disallow' => 'Disallow',
			'deny' => 'Deny',
	);
	public $desc_right_values = array(
			'allow' => 'Access granted, if no parent group explicitly Denies.',
			'disallow' => 'Access denied unless a parent group explicitly Allows.',
			'deny' => 'Access will be denied for this and all child groups.',
	);
	
	public $label_namespace = array(
			'auth' => 'Authorization',
			'config' => 'Settings',
			'accounts' => 'Accounts',
	);
	public $desc_namespace = array(
			'auth' => 'Authorization Rights',
			'config' => 'Settings/Configuration/Preferences',
			'accounts' => 'Membership Account Rights',
	);
	
	public $label_auth = array(
			'modify' => 'Modify Permission Groups',
			'create' => 'Create Permission Groups',
			'delete' => 'Delete Permission Groups',
	);
	public $desc_auth = array(
			'modify' => 'Assign rights to groups.',
			'create' => 'Define new rights groups.',
			'delete' => 'Remove existing rights groups.',
	);
	
	public $label_config = array(
			'modify' => 'Modify System Settings',
	);
	public $desc_config = array(
			'modify' => 'Modify system settings.',
	);
	
	public $label_accounts = array(
			'modify' => 'Modify Accounts',
			//no need for create right as everyone can create an account by registering
			'delete' => 'Delete Accounts',
	);
	public $desc_accounts = array(
			'modify' => 'Modify any existing account.',
			//no need for create right as everyone can create an account by registering
			'delete' => 'Remove any existing account (requires Modify too).',
	);
	
}//end class

}//end namespace
