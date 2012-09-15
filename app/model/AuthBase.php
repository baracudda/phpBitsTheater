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

namespace com\blackmoonit\bits_theater\app\model; 
use com\blackmoonit\bits_theater\app\Model;
use com\blackmoonit\bits_theater\app\config\Settings;
{//namespace begin

abstract class AuthBase extends Model {
	const TYPE = 'abstract'; //decendants must override this
	const ALLOW_REGISTRATION = false; //only 1 type allows it
	protected $permissions = null;
	
	public function cleanup() {
		if (isset($this->director))
			$this->director->returnProp($this->permissions);
		parent::cleanup();
	}
	
	public function getType() {
		return static::TYPE;
	}
	
	public function isRegistrationAllowed() {
		return static::ALLOW_REGISTRATION;
	}
	
	public function checkTicket() {
		if ($this->director->isInstalled()) {
			if ($this->director['app_id'] != Settings::APP_ID) {
				$this->ripTicket();
			}
		}
	}
	
	public function ripTicket() {
		unset($this->director->account_info);
		$this->director->resetSession();
	}
	
	public function canRegister($aAcctName, $aEmailAddy) {
		return static::ALLOW_REGISTRATION;
	}
		
	public function registerAccount($aUserData) {
		//overwrite this
	}
	
	public function renderInstallOptions($anActor) {
		return $anActor->renderFragment('auth_'.static::TYPE.'_options');
	}
	
	public function isAllowed($aNamespace, $aPermission, $acctInfo=null) {
		if (empty($this->permissions))
			$this->permissions = $this->director->getProp('Permissions'); //cleanup will close this model
		return $this->permissions->isAllowed($aNamespace, $aPermission, $acctInfo);
	}
	
	abstract public function getGroupList();
	
}//end class

}//end namespace
