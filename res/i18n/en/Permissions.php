<?php
namespace res\en;
use res\Permissions as ResPerms;
{

class Permissions extends ResPerms {

	public $anonymous_group_name = 'anonymous'; //name of "group 0" when not logged in so you can still assign rights

	public $right_values = array(
				'allow' => array('label'=>'Allow', 'desc'=>'Access granted, if no other group explicitly Denies.', ),
				'disallow' => array('label'=>'Disallow', 'desc'=>'Access granted only if another group explicitly Allows.', ),
				'deny' => array('label'=>'Deny', 'desc'=>'Access will not be granted even if another group Allows.', ),
			);
	
	public $namespace = array(
				'auth' => array('label'=>'Authorization', 'desc'=>'Authorization Rights', ),
				'config' => array('label'=>'Settings', 'desc'=>'Settings/Configuration/Preferences', ),
				'accounts' => array('label'=>'Accounts', 'desc'=>'Membership Account Rights', ),
				'sched' => array('label'=>'Schedule', 'desc'=>'Schedule of Events', ),
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
				'delete' => array('label'=>'Delete Accounts', 'desc'=>'Remove any existing account.', ),
			);
			
	public $sched = array(
				'view' => array('label'=>'View Schedule', 'desc'=>'Able to view event schedule.', ),
			);
			
			

}//end class

}//end namespace
