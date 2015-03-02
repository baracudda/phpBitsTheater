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

namespace BitsTheater\actors\Understudy;
use BitsTheater\Actor as BaseActor;
use BitsTheater\scenes\Account as MyScene;
	/* @var $v MyScene */
use BitsTheater\models\Accounts;
	/* @var $dbAccounts Accounts */
use BitsTheater\costumes\AccountInfoCache;
use com\blackmoonit\Strings;
{//namespace begin

abstract class ABitsAccount extends BaseActor {
	const DEFAULT_ACTION = 'view';
	
	public function view($aAcctId=null) {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		
		if ($this->isGuest()) {
			return $v->getSiteURL($this->config['auth/register_url']);
		}
		$v->ticket_info = $this->director->account_info;
		$dbAccounts = $this->getProp('Accounts');
		$v->dbAccounts = $dbAccounts;
		if (!empty($aAcctId) && $this->isAllowed('account','modify')) {
			/* @var $v->ticket_info AccountInfoCache */
			$v->ticket_info = AccountInfoCache::fromArray($dbAccounts->getAccount($aAcctId));
		}
		$v->action_modify = $this->getMyUrl('/account/modify');
		$v->redirect = $this->getMyUrl('/account/view');
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('account');
	}
	
	public function login() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		if (!$this->director->isGuest()) {
			if ($v->redirect)
				return $v->redirect;
			else
				return $this->getHomePage();
		} else {
			$v->action_url_register = $v->getSiteURL($this->config['auth/register_url']);
			$v->action_url_login = $v->getSiteURL($this->config['auth/login_url']);
			$v->redirect = $this->getHomePage();
		}
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('account');
	}
	
	public function logout() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		$s = $this->director->logout();
		if (!empty($v->redirect))
			$s = $v->redirect;
		return $s;
	}
	
	/**
	 * Renders the login/logout area of a page.
	 */
	protected function buildAuthArea() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		$v->action_url_register = $v->getSiteURL($this->config['auth/register_url']);
		$v->action_url_login = $v->getSiteURL($this->config['auth/login_url']);
		$v->action_url_logout = $v->getSiteURL($this->config['auth/logout_url']);
	}
	
}//end class

}//end namespace

