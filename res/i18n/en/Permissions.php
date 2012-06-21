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
				'casnodes' => array('label'=>'CAS Nodes', 'desc'=>'Nodes used for CAS', ),
				'casnode_api' => array('label'=>'CAS Node REST API', 'desc'=>'Remote app REST server for CAS Nodes', ),
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
			
	public $casnodes = array(
				'create' => array('label'=>'Create Nodes', 'desc'=>'Create new Nodes.', ),
				'delete' => array('label'=>'Delete Nodes', 'desc'=>'Remove existing Nodes.', ),
				'modify' => array('label'=>'Modify Nodes', 'desc'=>'Change details of a Node.', ),
				'status' => array('label'=>'Update Node Status', 'desc'=>'Add status records to a Node, thus updating its current status', ),
	);
			
	public $casnode_api = array(
				'get' => array('label'=>'Get', 'desc'=>'Able to retrieve node data.', ),
				'set' => array('label'=>'Set', 'desc'=>'Able to create/modify node data.', ),
				'del' => array('label'=>'Delete', 'desc'=>'Able to delete node data.', ),
	);
			
			

}//end class

}//end namespace
