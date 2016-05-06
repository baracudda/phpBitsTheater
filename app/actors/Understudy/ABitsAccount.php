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
use BitsTheater\scenes\Account as MyScene; /* @var $v MyScene */
use BitsTheater\models\Accounts; /* @var $dbAccounts Accounts */
use BitsTheater\costumes\AccountInfoCache;
use com\blackmoonit\Strings;
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\APIResponse;
{//namespace begin

abstract class ABitsAccount extends BaseActor {
	const DEFAULT_ACTION = 'view';
	
	public function view($aAcctId=null) {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		
		if ($this->isGuest() || empty($aAcctId)) {
			return $v->getSiteURL($this->config['auth/register_url']);
		}
		$dbAccounts = $this->getProp('Accounts');
		$v->dbAccounts = $dbAccounts;
		$bAuthorizied = (
				//everyone is allowed to modify email/pw of their own account
				$aAcctId==$this->director->account_info->account_id ||
				//admins may be allowed to modify someone else's account
				$this->isAllowed('account','modify')
		);
		if ($bAuthorizied) {
			/* @var $v->ticket_info AccountInfoCache */
			$v->ticket_info = AccountInfoCache::fromArray($dbAccounts->getAccount($aAcctId));
		}
		$v->action_modify = $this->getMyUrl('/account/modify');
		$v->redirect = $this->getMyUrl('/account/view/'.$aAcctId);
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('account');
	}
	
	protected function setupLoginInfo(MyScene $v) {
		$v->action_url_register = $v->getSiteURL(
				$this->getConfigSetting('auth/register_url')
		);
		$v->action_url_requestpwreset = $v->getSiteUrl(
				$this->getConfigSetting('auth/request_pwd_reset_url')
		);
		$v->action_url_login = $v->getSiteURL(
				$this->getConfigSetting('auth/login_url')
		);		
	}
	
	public function login() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		if( !$this->isGuest() )
		{
			if ($v->redirect)
				return $v->redirect;
			else
				return $this->getHomePage();
		}
		else
		{
			$this->setupLoginInfo($v);
			$v->redirect = $this->getHomePage() ;
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
	protected function buildAuthArea()
	{
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		$this->setupLoginInfo($v);
		$v->action_url_logout = $v->getSiteURL(
				$this->config['auth/logout_url']
		);
	}
	
	/**
	 * API version of the login() URL.
	 * @return APIResponse Returns the same
	 *   object as ajajGetAccountInfo().
	 * @see ABitsAccount::ajajGetAccountInfo()
	 */
	public function loginAs() {
		$this->viewToRender('results_as_json');
		return $this->ajajGetAccountInfo();
	}
	
	/**
	 * If you are currently logged in, return the cached info about myself.
	 * JavaScript code may need current login info, too.
	 * @return APIResponse Returns the standard API response object with User info.
	 */
	public function ajajGetAccountInfo()
	{
		$v =& $this->scene;
		if( ! $this->isGuest() )
		{
			$theData = $this->director->account_info ;
			$theData->account_id = intval( $theData->account_id ) ;

			$theGroupID =
				( empty( $theData->groups ) ? false : $theData->groups[0] ) ;
			$theError = null ;
			if( $theGroupID ) try
			{
				$dbPerms = $this->getProp( 'Permissions' ) ;
				$theData->permissions = $dbPerms->getGrantedRights($theGroupID);
			}
			catch( Exception $x )
			{ $theError = BrokenLeg::toss( $this, $x ) ; }

			$v->results = APIResponse::resultsWithData($theData) ;
			if( $theError != null )
				$v->results->setError($theError) ;
		}
		else
		{ throw BrokenLeg::toss($this, 'NOT_AUTHENTICATED') ; }
	}
	
}//end class

}//end namespace

