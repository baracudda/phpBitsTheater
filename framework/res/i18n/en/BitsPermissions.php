<?php
/*
 * Copyright (C) 2015 Blackmoon Info Tech Services
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace BitsTheater\res\en;
use BitsTheater\res\Permissions as BaseResources;
{//begin namespace

class BitsPermissions extends BaseResources {
	public $menu_rights_label = 'Permissions';
	public $menu_rights_subtext = '';
	
	public $title_groups = 'Assign Rights To Roles';
	public $colheader_group_id = '#';
	public $colheader_group_name = 'Role';
	public $colheader_group_parent = 'Description';
	public $display_group_0_desc = 'visitor that is not logged in';
	public $display_group_1_desc = 'always passes permission checks';
	public $display_parent_group = 'subset of %s';
	public $label_button_add_role = '<span class="glyphicon glyphicon-plus"></span> Add Role';
	
	public $title_group = 'Assign Rights for Role: %s';
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
			'allow' => 'Access granted, if no parent role explicitly denies it.',
			'disallow' => 'Access denied unless a parent group explicitly allows it.',
			'deny' => 'Access will be denied for this and all child roles.',
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
			'modify' => 'Modify Roles',
			'create' => 'Create Roles',
			'delete' => 'Delete Roles',
	);
	public $desc_auth = array(
			'modify' => 'Assign rights to roles.',
			'create' => 'Define new roles.',
			'delete' => 'Remove existing roles.',
	);
	
	public $label_config = array(
			'modify' => 'Modify System Settings',
	);
	public $desc_config = array(
			'modify' => 'Modify system settings.',
	);
	
	public $label_accounts = array(
			'create' => 'Create Accounts',
			'modify' => 'Modify Accounts',
			'view' => 'View Accounts',
			'activate' => 'Activate/Deactivate Accounts',
			'delete' => 'Delete Accounts',
	);

	public $desc_accounts = array(
			'create' => 'Create a new account.',
			'modify' => 'Modify any existing account.',
			'view' => 'Access information for other accounts.',
			'activate' => 'Activate or deactivate an existing account.',
			'delete' => 'Remove an inactive account.',
	);
	
}//end class

}//end namespace
