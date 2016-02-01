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

namespace BitsTheater\res;
use BitsTheater\res\Resources as BaseResources;
{//begin namespace

/**
 * DO NOT TRANSLATE!
 */
class BitsConfig extends BaseResources {

	public $enum_namespace = array(
			'site',
			'auth',
			'email_out',
	);
			
	public $enum_site = array(
			'mode',
			'csrfCookieName',
			'csrfHeaderName',
			'mmr', //managed media root
			'maxfilesize',
	);
	
	public $enum_email_out = array(
			'host',
			'port',
			'user',
			'pwd',
			'security',
			'default_from',
	);
	
	public $enum_auth = array(
			'register_url',
			'request_pwd_reset_url',
			'login_url',
			'logout_url',
			'cookie_freshness_duration',
			'login_fail_attempts',
	);
	
	/**
	 * Some resources need to be initialized by running code rather than a static definition.
	 * Merging Enums with their UI counterparts is common.
	 */
	public function setup($aDirector) {
		parent::setup($aDirector);

		$this->mergeConfigEntryInfo('namespace');
		foreach ($this->namespace as $theEnumName => $theEnumEntry) {
			$this->mergeConfigEntryInfo($theEnumName);
		}
	}
	
}//end class

}//end namespace
