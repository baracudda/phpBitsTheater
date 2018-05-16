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
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\APIResponse;
use BitsTheater\outtakes\AccountAdminException ;
use BitsTheater\scenes\Account as MyScene;
use com\blackmoonit\exceptions\DbException ;
use Exception;
{//namespace begin

abstract class ABitsAccount extends BaseActor {
	const DEFAULT_ACTION = 'view';
	
	/**
	 * {@inheritDoc}
	 * @return MyScene Returns a newly created scene descendant.
	 * @see \BitsTheater\Actor::createMyScene()
	 */
	protected function createMyScene($anAction)
	{ return new MyScene($this, $anAction); }
	
	/**
	 * {@inheritDoc}
	 * @see \BitsTheater\Actor::isApiResult()
	 */
	protected function isApiResult( $aAction, $aQuery )
	{
		return ( parent::isApiResult($aAction, $aQuery) ||
				$aAction == 'loginAs' );
	}
	
	/**
	 * View the currently logged in account information. (page render)
	 * @param number $aAcctId - the account to view, if allowed to see one besides the current.
	 * @return string|null Returns a string if client is redirected to a new URL.
	 */
	public function view($aAcctId=null) {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		
		if ($this->isGuest() || empty($aAcctId)) {
			return $v->getSiteUrl($this->getConfigSetting('auth/register_url'));
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
			$v->ticket_info = $this->getProp('Auth')->getAccountInfoCache(
					$dbAccounts, $aAcctId
			);
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
	
	/**
	 * Web form based login endpoint.
	 * @return string|null Returns a string if client is redirected to a new URL.
	 */
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
	
	/**
	 * Disposes of any cached info about the currently logged in user for this session.
	 * @return string|null Returns a string if client is redirected to a new URL.
	 */
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
				$this->getConfigSetting('auth/logout_url')
		);
	}
	
	/**
	 * API version of the login() URL.
	 * @return APIResponse Returns the same
	 *   object as ajajGetAccountInfo().
	 * @see ABitsAccount::ajajGetAccountInfo()
	 * @return string|null Returns a string if client is redirected to a new URL.
	 */
	public function loginAs() {
		return $this->ajajGetAccountInfo();
	}
	
	/**
	 * If you are currently logged in, return the cached info about myself.
	 * JavaScript code may need current login info, too.
	 * $this->scene->result is set as an APIResponse object with User info.
	 * @return string|null Returns a string if client is redirected to a new URL.
	 */
	public function ajajGetAccountInfo()
	{
		$v =& $this->scene;
		$this->viewToRender('results_as_json');
		if( ! $this->isGuest() )
		{
			$theData = $this->getDirector()->getMyAccountInfo()->exportData() ;
			if( !empty($theData->groups) ) try
			{
				$dbPerms = $this->getProp( 'Permissions' ) ;
				$theData->permissions = $dbPerms->getGrantedRights($theData->groups);
			}
			catch( Exception $x )
			{ throw BrokenLeg::tossException( $this, $x ) ; }

			$v->results = APIResponse::resultsWithData($theData) ;
		}
		else
		{ throw BrokenLeg::toss($this, BrokenLeg::ACT_NOT_AUTHENTICATED) ; }
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
	 * @return string|null Returns a string if client is redirected to a new URL.
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
		$this->checkAllowed( 'accounts', 'delete' );

		if( $aAccountID == $this->getMyAccountID() )
			throw AccountAdminException::toss( $this, 'CANNOT_DELETE_YOURSELF' ) ;

		$dbAccounts = $this->getProp( 'Accounts' ) ;
		$theAccount = null ;
		try { $theAccount = $dbAccounts->getAccount($aAccountID) ; }
		catch( DbException $dbx )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_DB_EXCEPTION, $dbx->getMessage() ) ; }
		if( empty($theAccount) )
			throw BrokenLeg::toss( $this, BrokenLeg::ACT_ENTITY_NOT_FOUND, $aAccountID ) ;

		$dbGroups = $this->getProp( 'AuthGroups' ) ;
		$theGroups = null ;
		try { $theGroups = $dbGroups->getAcctGroups( $aAccountID ) ; }
		catch( DbException $dbx )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_DB_EXCEPTION, $dbx->getMessage() ) ; }
		if( in_array( $dbGroups->getTitanGroupID(), $theGroups ) )
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
		$this->debugLog( __METHOD__ . ' Deleting account [' . $aAccountID . ']...' ) ;

		$dbAccounts = $this->getProp( 'Accounts' ) ;
		try { $dbAccounts->del($aAccountID) ; }
		catch( DbException $dbx )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_DB_EXCEPTION, $dbx->getMessage() ) ; }

		return $this ;
	}
	
	/**
	 * Executes a series of checks to validate an input received from a user as
	 * part of a password change request.
	 * Each check is a separate child function, and each may throw its own
	 * sorts of exceptions.
	 * An implementation/descendant class may override this method to select a
	 * different list of checks, perform them in a different order, or add new
	 * custom checks.
	 * @param string $aSecret the unencrypted user input
	 * @since BitsTheater [NEXT]
	 * @link https://pages.nist.gov/800-63-3/sp800-63b.html#memsecret NIST 800-63-3 Digital Identity Guidelines &sect;5.1.1 Memorized Secrets
	 */
	protected function validatePswdChangeInput( $aSecret )
	{
		$this->validatePswdMinLength($aSecret) ;
		return true ;
	}
	
	/**
	 * Hard-coded minimum length for a new password.
	 * @var integer
	 * @since BitsTheater [NEXT]
	 */
	const PSWD_DEFAULT_MIN_LENGTH = 8 ;
	/**
	 * Configurable minimum length for a new password.
	 * Initialized with PSWD_DEFAULT_MIN_LENGTH but may be overwritten by a
	 * configuration item in in an implementation class.
	 * @var integer
	 * @since BitsTheater [NEXT]
	 */
	protected $myPswdMinLength = self::PSWD_DEFAULT_MIN_LENGTH ;
	
	/**
	 * Verifies that a password input's length exceeds the minimum.
	 * @param string $aSecret - the password.
	 * @since BitsTheater [NEXT]
	 */
	protected function validatePswdMinLength( $aSecret )
	{
		$theLength = mb_strlen( $aSecret, 'UTF-8' ) ;
		if( $theLength < $this->myPswdMinLength )
		{
			throw AccountAdminException::toss( $this,
					AccountAdminException::ACT_PASSWORD_MINIMUM_LENGTH,
					array( $this->myPswdMinLength )
				);
		}
		return true ;
	}

}//end class

}//end namespace

