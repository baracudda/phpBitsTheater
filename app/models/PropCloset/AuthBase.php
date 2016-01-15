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
	protected $dbPermissions = null;
	
	public function cleanup() {
		if (isset($this->director))
			$this->director->returnProp($this->dbPermissions);
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
	
	/**
	 * Check your authority mechanism to determine if a permission is allowed.
	 * @param string $aNamespace - namespace of the permission.
	 * @param string $aPermission - name of the permission.
	 * @param string $acctInfo - (optional) check this account instead of current user.
	 * @return boolean Return TRUE if the permission is granted, FALSE otherwise.
	 */
	abstract public function isPermissionAllowed($aNamespace, $aPermission, $acctInfo=null);
	
	/**
	 * Return the defined permission groups.
	 */
	abstract public function getGroupList();
	
	/**
	 * Checks the given account information for membership.
	 * @param AccountInfoCache $aAccountInfo - the account info to check.
	 * @return boolean Returns FALSE if the account info matches a member account.
	 */
	abstract public function isGuestAccount($aAccountInfo);

}//end class

}//end namespace
