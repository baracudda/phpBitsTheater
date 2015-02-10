<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
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

namespace BitsTheater\res;
use BitsTheater\res\BitsPermissions as BaseResources;
{//begin namespace

class Permissions extends BaseResources {

	public $enum_my_namespaces = array(
			'ns_a',
			'ns_b',
			'ns_c',
	);
	
	public $enum_ns_a = array(
			'view',
			'modify',
			'view_more_custom1',
			'send_stuff_custom2',
	);
	
	public $enum_ns_b = array(
			'view',
			'add',
			'modify',
			'delete',
			'copy',
	);
	
	public $enum_ns_c = array(
			'access',
	);
	
	/**
	 * Some resources need to be initialized by running code rather than a static definition.
	 */
	public function setup($aDirector) {
		$this->res_array_merge($this->enum_namespace, $this->enum_my_namespaces);
		//parent can handle the rest once "enum_namespace" is updated
		parent::setup($aDirector);
	}
	
}//end class

}//end namespace
