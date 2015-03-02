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

namespace BitsTheater\models\PropCloset; 
use BitsTheater\Model as BaseModel;
{//namespace begin

abstract class AuthBase extends BaseModel {
	const TYPE = 'abstract'; //decendants must override this
	const ALLOW_REGISTRATION = false; //only 1 type allows it, so far
	const KEY_userinfo = 'ticketholder'; //var name in checkTicket($aScene) for username
	const KEY_pwinput = 'pwinput'; //var name in checkTicket($aScene) for pw
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
	
	public function checkTicket($aScene) {
		if ($this->director->isInstalled()) {
			if ($this->director->app_id != \BitsTheater\configs\Settings::getAppId()) {
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
