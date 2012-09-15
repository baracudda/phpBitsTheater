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

namespace com\blackmoonit\bits_theater\app\actor;
use com\blackmoonit\bits_theater\app\Actor;
use com\blackmoonit\Strings;
{//namespace begin

class Account extends Actor {
	const DEFAULT_ACTION = 'register';

	public function view($aAcctId=null) {
		if ($this->director->isGuest()) {
			return $this->config['auth/register_url'];
		}
		$this->scene->dbAccounts = $this->getProp('Accounts');
		if (!empty($aAcctId) && $this->isAllowed('account','modify')) {
			$this->scene->ticket_info = $this->scene->dbAccounts->getAccount($aAcctId);
			$dbAuth = $this->getProp('Auth');
			$authdata = $dbAuth->getAuthById($aAcctId);
			$this->scene->ticket_info['email'] = $authdata['email'];
		} else {
			$this->scene->ticket_info = $this->director->account_info;
		}
		$this->scene->action_modify = $this->getMyUrl('/account/modify');
		$this->scene->redirect = $this->getMyUrl('/account/view');
	}
	
	public function register($aTask='data-entry') {
		$dbAccts = $this->getProp('Accounts');
		//make sure user/pw reg fields will not interfere with any login user/pw field in header
		$userKey = $this->scene->getUsernameKey().'_reg';
		$pwKey = $this->scene->getPwInputKey().'_reg';
		if ($aTask==='new' && $this->scene->reg_code===$this->getAppId() &&
				$this->scene->$pwKey===$this->scene->password_confirm) {
			$dbAuth = $this->getProp('Auth');
			$theRegResult = $dbAuth->canRegister($this->scene->$userKey,$this->scene->email);
			switch ($theRegResult) {
			case $dbAuth::REGISTRATION_EMAIL_TAKEN:
				return $this->getMyUrl('/account/register',
						array('err_msg'=>$this->getRes('account/msg_acctexists/'.$this->getRes('account/label_email'))));
			case $dbAuth::REGISTRATION_NAME_TAKEN:
				return $this->getMyUrl('/account/register',
						array('err_msg'=>$this->getRes('account/msg_acctexists/'.$this->getRes('account/label_name'))));
			default: //create new acct
				$theNewAcct['account_name'] = $this->scene->$userKey;
				$theNewId = $dbAccts->add($theNewAcct);
				if (!empty($theNewId)) {
					$verified_ts = null;
					if ($this->scene->reg_code===$this->getAppId()) {
						$verified_ts = $dbAccts->utc_now();
					}
					$theNewAcct = array(
							'email' => $this->scene->email,
							'account_id' => $theNewId,
							'pwinput' => $this->scene->$pwKey,
							'verified_timestamp' => $verified_ts,
					);
					$dbAuth->registerAccount($theNewAcct);
					return $this->getMyUrl('/rights');
				}
			}//end switch
		} else {
			//$this->scene->err_msg = $_SERVER['QUERY_STRING'];
			//$this->scene->err_msg = array_key_exists('err_msg',$_GET)?$_GET['err_msg']:null;
			$this->scene->form_name = 'register_user';
			$this->scene->action_register = $this->getMyUrl('/account/register/new');
			$this->scene->post_key = $this->getAppId();
			if ($dbAccts->isEmpty()) {
				$this->scene->redirect = $this->getMyUrl('/rights');
				$this->scene->reg_code = $this->getAppId();
			} else {
				$this->scene->redirect = $this->getHomePage();
			}
		}
	}
	
	public function login() {
		if (!$this->director->isGuest()) {
			if ($this->scene->redirect)
				return $this->scene->redirect;
			else
				return $this->getHomePage();
		} else {
			$this->scene->action_login = $this->config['auth/login_url'];
			$this->scene->redirect = $this->getHomePage();
		}
	}
	
	public function logout() {
		$s = $this->director->logout();
		if (!empty($this->scene->redirect))
			$s = $this->scene->redirect;
		return $s;
	}
	
	/**
	 * Renders the login/logout area of a page.
	 */
	protected function buildAuthArea() {
		$this->scene->action_login = $this->config['auth/login_url'];
		$this->scene->action_logout = $this->config['auth/logout_url'];
		
	}
	
	public function modify() {
		$dbAccts = $this->getProp('Accounts');
		$theAcctId = $this->scene->ticket_num;
		$dbAuth = $this->getProp('Auth');
		$pwKeyOld = $this->scene->getPwInputKey().'_old';
		if ($dbAuth->isCallable('cudo') && $dbAuth->cudo($theAcctId,$this->scene->$pwKeyOld)) {
			//if current pw checked out ok, see if its our own acct or have rights to modify other's accounts.
			if ($theAcctId==$this->director->account_info['account_id'] || $this->isAllowed('account','modify')) {
				$theOldEmail = trim($this->scene->ticket_email);
				$theNewEmail = trim($this->scene->email);
				/* !== returned TRUE, === retruend FALSE, but strcmp() returned 0 (meaning they are the same) O.o 
				$b1 = ($theOldEmail!==$theNewEmail);
				$b2 = ($theOldEmail===$theNewEmail);
				$b3 = (strcmp($theOldEmail,$theNewEmail)!=0);
				Strings::debugLog('b:'.$b1.','.$b2.',',$b3);
				Strings::debugLog(Strings::bin2hex($theOldEmail));
				Strings::debugLog(Strings::bin2hex($theNewEmail));
				/* */
				if (strcmp($theOldEmail,$theNewEmail)!=0) {
					//Strings::debugLog('email is not 0:'.strcmp($theOldEmail,$theNewEmail));
					if ($dbAuth->getAuthByEmail($theNewEmail)) {
						return $this->getMyUrl('/account/view',
								array('err_msg'=>$this->getRes('account/msg_acctexists/'.$this->getRes('account/label_email'))));
					} else {
						$theSql = 'UPDATE '.$dbAuth->tnAuth.' SET email = :email WHERE account_id=:acct_id';
						$dbAuth->execDML($theSql,array('acct_id'=>$theAcctId, 'email'=>$theNewEmail));
					}
				}
				$pwKeyNew = $this->scene->getPwInputKey().'_new';
				if (!empty($this->scene->$pwKeyNew) && $this->scene->$pwKeyNew===$this->scene->password_confirm) {
					$thePwHash = Strings::hasher($this->scene->$pwKeyNew);
					$theSql = 'UPDATE '.$dbAuth->tnAuth.' SET pwhash = :pwhash WHERE account_id=:acct_id';
					$dbAuth->execDML($theSql,array('acct_id'=>$theAcctId, 'pwhash'=>$thePwHash));
				}
				return $this->getMyUrl('/account/view',
						array('err_msg'=>$this->getRes('account/msg_update_success')));
			}
		} else {
			return $this->getMyUrl('/account/view',
					array('err_msg'=>$this->getRes('generic/msg_permission_denied')));
		}
	}
	
}//end class

}//end namespace

