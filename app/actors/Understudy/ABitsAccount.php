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
use BitsTheater\costumes\AccountInfoCache;
use com\blackmoonit\Strings;
use com\blackmoonit\exceptions\DbException ;
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\APIResponse;
use BitsTheater\outtakes\AccountAdminException ;
use Exception;
use PDOException ;
{//namespace begin

abstract class ABitsAccount extends BaseActor {
	const DEFAULT_ACTION = 'view';
	
	public function view($aAcctId=null) {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		
		if ($this->isGuest() || empty($aAcctId)) {
			return $v->getSiteUrl($this->config['auth/register_url']);
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
	
	protected function setupLoginInfo($v) {
		$v->action_url_register = $v->getSiteUrl(
				$this->getConfigSetting('auth/register_url')
		);
		$v->action_url_requestpwreset = $v->getSiteUrl(
				$this->getConfigSetting('auth/request_pwd_reset_url')
		);
		$v->action_url_login = $v->getSiteUrl(
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
		$v->action_url_logout = $v->getSiteUrl(
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
		$this->viewToRender('results_as_json');
		if( ! $this->isGuest() )
		{
			$theData = $this->director->account_info ;
			$theData->account_id = intval( $theData->account_id ) ;
			//$theData->debugAuth = $v->debugAuth; unset($v->debugAuth); //DEBUG

			$theGroupID =
				( empty( $theData->groups ) ? false : $theData->groups[0] ) ;
			$theError = null ;
			if( $theGroupID ) try
			{
				$dbPerms = $this->getProp( 'Permissions' ) ;
				$theData->permissions = $dbPerms->getGrantedRights($theGroupID);
			}
			catch( Exception $x )
			{ throw BrokenLeg::tossException( $this, $x ) ; }

			$v->results = APIResponse::resultsWithData($theData) ;
		}
		else
		{ throw BrokenLeg::toss($this, 'NOT_AUTHENTICATED') ; }
	}
	
	/**
	 * Allows a site administrator to delete an inactive account. The service
	 * should verify that the account is truly inactive; that is, that the user
	 * has not committed any data to the system which should be saved.
	 * @param integer $aAccountID the account ID (if null, fetch from POST var
	 *  'account_id' instead)
	 * @throws BrokenLeg one of the following error codes
	 *  * 'MISSING_ARGUMENT' - if the account ID is not specified
	 *  * 'ENTITY_NOT_FOUND' - if no account with that ID exists
	 *  * 'FORBIDDEN' - if the requestor does not have appropriate rights
	 *  * 'DB_CONNECTION_FAILED' - if can't connect to the DB
	 * @since BitsTheater 3.6
	 */
	public function ajajDelete( $aAccountID=null )
	{
		$theAccountID = $this->getEntityID( $aAccountID, 'account_id' ) ;
		if( $this->checkCanDeleteAccount( $theAccountID ) )
			$this->deleteAccountData( $theAccountID ) ;
		$this->scene->results = APIResponse::noContentResponse() ;
	}

	/**
	 * Verifies that the requestor can delete the specified account.
	 * Descendant classes should override this method to perform additional
	 * checks of the "Should I really allow this?" variety.
	 * Consumed by ajajDelete().
	 * @param integer $aAccountID the account ID
	 * @throws BrokenLeg one of the following error codes
	 *  * 'FORBIDDEN' - if the requestor does not have appropriate rights
	 *  * 'CANNOT_DELETE_YOURSELF' - if the account ID matches the requestor's
	 *    account ID
	 * @return boolean - true if no exceptions are thrown
	 * @since BitsTheater 3.6
	 */
	protected function checkCanDeleteAccount( $aAccountID )
	{
		if( ! $this->isAllowed( 'accounts', 'delete' ) )
			throw BrokenLeg::toss( $this, 'FORBIDDEN' ) ;

		if( $aAccountID == $this->getMyAccountID() )
			throw AccountAdminException::toss( $this, 'CANNOT_DELETE_YOURSELF' ) ;

		$dbAccounts = $this->getProp( 'Accounts' ) ;
		if( ! $dbAccounts->isConnected() )
			throw BrokenLeg::toss( $this, 'DB_CONNECTION_FAILED' ) ;
		$theAccount = null ;
		try { $theAccount = $dbAccounts->getAccount($aAccountID) ; }
		catch( DbException $dbx )
		{ throw BrokenLeg::toss( $this, 'DB_EXCEPTION', $dbx->getMessage() ) ; }
		if( empty($theAccount) )
			throw BrokenLeg::toss( $this, 'ENTITY_NOT_FOUND', $aAccountID ) ;

		$dbGroups = $this->getProp( 'AuthGroups' ) ;
		$theGroups = null ;
		try { $theGroups = $dbGroups->getAcctGroups( $aAccountID ) ; }
		catch( DbException $dbx )
		{ throw BrokenLeg::toss( $this, 'DB_EXCEPTION', $dbx->getMessage() ) ; }
		if( in_array( $dbGroups::TITAN_GROUP_ID, $theGroups ) )
			throw AccountAdminException::toss( $this, 'CANNOT_DELETE_TITAN' ) ;
		$this->returnProp( $dbGroups ) ;

		return true ;
	}

	/**
	 * Deletes an account's data from the accounts table. Classes that override
	 * the ajajDelete() endpoint can call this method before or after performing
	 * other custom operations, such as deleting auth data, or verifying that
	 * the user is inactive.
	 * Consumed by ajajDelete().
	 * @param integer $aAccountID the account ID
	 * @return ABitsAccount $this
	 * @throws BrokenLeg 'DB_CONNECTION_FAILED' if can't connect to DB.
	 * @since BitsTheater 3.6
	 */
	protected function deleteAccountData( $aAccountID )
	{
		$this->debugLog( __METHOD__ . ' Deleting account ['
				. $aAccountID . ']...' ) ;

		$dbAccounts = $this->getProp( 'Accounts' ) ;
		if( ! $dbAccounts->isConnected() )
			throw BrokenLeg::toss( $this, 'DB_CONNECTION_FAILED' ) ;
		try { $dbAccounts->del($aAccountID) ; }
		catch( PDOException $pdox )
		{ throw BrokenLeg::toss( $this, 'DB_EXCEPTION', $pdox->getMessage() ) ; }
		catch( DbException $dbx )
		{ throw BrokenLeg::toss( $this, 'DB_EXCEPTION', $dbx->getMessage() ) ; }

		return $this ;
	}

}//end class

}//end namespace

