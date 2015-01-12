<?php
/*
 * Copyright (C) 2014 Blackmoon Info Tech Services
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
use BitsTheater\res\Resources as BaseResource;
{//begin namespace

class CorePermissions extends BaseResource {
	
	public $enum_right_values = array('allow','disallow','deny');

	public $enum_namespace = array('auth', 'config', 'accounts');
			
	public $enum_auth = array('modify','create','delete');
	
	public $enum_config = array('modify');
	
	public $enum_accounts = array('modify','delete'); //anyone can create/register a new account
	
	/**
	 * Some resources need to be initialized by running code rather than a static definition.
	 * Merging Enums with their UI counterparts is common.
	 */
	public function setup($aDirector) {
		parent::setup($aDirector);

		$this->mergeEnumEntryInfo('right_values');
		
		$this->mergeEnumEntryInfo('namespace');
		foreach ($this->namespace as $theEnumName => $theEnumEntry) {
			$this->mergeEnumEntryInfo($theEnumName);
		}
	}
	
}//end class

}//end namespace
