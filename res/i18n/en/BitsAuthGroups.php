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

	public $errmsg_cannot_modify_titan = 'That user group cannot be modified.' ;
	public $errmsg_cannot_copy_from_titan =
		'That user group\'s permissions cannot be copied to another group.' ;
	public $errmsg_cannot_copy_to_titan =
		'Permissions cannot be copied into that user group.' ;
	public $errmsg_group_not_found = 'User group [%1$s] not found.' ;

}//end class

}//end namespace
