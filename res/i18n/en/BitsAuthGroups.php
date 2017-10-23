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
use BitsTheater\res\Resources as BaseResources;
{//begin namespace

class BitsAuthGroups extends BaseResources {

	//note, this could have been a static function that loaded its array from a file and returned it
	public $group_names = array(
			0=>'unregistered visitor',
			1=>'titan',  //super admin
			2=>'admin',
			3=>'privileged',
			4=>'restricted',
	);
	
	public $label_dialog_title_group_add = 'Add Role';
	public $label_dialog_title_group_edit = 'Edit Role';
	public $label_dialog_group_name = 'Name';
	public $label_dialog_group_parent = 'Parent';
	public $label_dialog_group_reg_code = 'Registration Code';
	public $hint_dialog_group_reg_code =
		'When registering, a user supplying this code will automatically be a member of this role.';

	public $errmsg_cannot_modify_titan = 'That user role cannot be modified.' ;
	public $errmsg_cannot_copy_from_titan =
		'That user role\'s permissions cannot be copied to another role.' ;
	public $errmsg_cannot_copy_to_titan =
		'Permissions cannot be copied into that role.' ;
	public $errmsg_group_not_found = 'Role [%1$s] not found.' ;
	
	public $placeholder_group_name = '    role name';
	public $placeholder_reg_code = '    map-to-role phrase';

	public $colheader_group_id = '#';
	public $colheader_group_name = 'Name';
	public $colheader_parent_group_id = 'Parent #';

}//end class

}//end namespace
