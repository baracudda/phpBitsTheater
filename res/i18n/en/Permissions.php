<?php
namespace BitsTheater\res\en;
use BitsTheater\res\Permissions as BaseResources;
{

class Permissions extends BaseResources {
	public $menu_rights_label = 'Permissions';
	public $menu_rights_subtext = '';
	
	public $title_groups = 'Assign Rights To Groups';
	public $colheader_group_id = '#';
	public $colheader_group_name = 'Rights Group';
	public $colheader_group_parent = 'Description';
	public $display_group_type_1 = 'always passes permission checks';
	public $display_parent_group = 'subset of %s';
	
	public $title_group = 'Assign Rights for Group: %s';
	public $colheader_right_name = 'Right';
	public $colheader_right_value = 'Assign';
	public $colheader_right_desc = 'Description';
	

	public $anonymous_group_name = 'anonymous'; //name of "group 0" when not logged in so you can still assign rights

	public $right_values = array(
			'allow' => array('label'=>'Allow', 'desc'=>'Access granted, if no parent group explicitly Denies.', ),
			'disallow' => array('label'=>'Disallow', 'desc'=>'Access denied unless a parent group explicitly Allows.', ),
			'deny' => array('label'=>'Deny', 'desc'=>'Access will be denied for this and all child groups.', ),
	);
	
	//when adding new rights, add their namespace info here
	public $namespace = array(
			'auth' => array('label'=>'Authorization', 'desc'=>'Authorization Rights', ),
			'config' => array('label'=>'Settings', 'desc'=>'Settings/Configuration/Preferences', ),
			'accounts' => array('label'=>'Accounts', 'desc'=>'Membership Account Rights', ),
			'roxy' => array('label'=>'Roxy', 'desc'=>'Roxy Database Access', ),
	);
			
	public $auth = array(
			'modify' => array('label'=>'Modify Permission Groups', 'desc'=>'Assign rights to groups.', ),
			'create' => array('label'=>'Create Permission Groups', 'desc'=>'Define new rights groups.', ),
			'delete' => array('label'=>'Delete Permission Groups', 'desc'=>'Remove existing rights groups.', ),
	);
	
	public $config = array(
			'modify' => array('label'=>'Modify System Settings', 'desc'=>'Modify system settings.', ),
	);
	
	public $accounts = array(
			'modify' => array('label'=>'Modify Accounts', 'desc'=>'Modify any existing account.', ),
			//no need for create right as everyone can create an account by registering
			'delete' => array('label'=>'Delete Accounts', 'desc'=>'Remove any existing account (requires Modify too).', ),
	);
	
	//new rights use their key as a variable name which is an array of the diff rights
	public $my_new_right_group = array(
			'my_new_right' => array('label'=>'New Right', 'desc'=>'Desc for my new right'),
			
	);

}//end class

}//end namespace
