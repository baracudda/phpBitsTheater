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
use BitsTheater\actors\Understudy\ABitsAccount as BaseActor;
use BitsTheater\scenes\Account as MyScene; /* @var $v MyScene */
use BitsTheater\models\Accounts; /* @var $dbAccounts Accounts */
use BitsTheater\models\Auth; /* @var $dbAuth Auth */
use BitsTheater\models\AuthGroups; /* @var $dbAuthGroups AuthGroups */
use BitsTheater\costumes\AuthPasswordReset ;
use BitsTheater\costumes\AuthPasswordResetException ;
use com\blackmoonit\MailUtils ;
use com\blackmoonit\MailUtilsException ;
use com\blackmoonit\Strings ;
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\APIResponse;
use BitsTheater\costumes\SqlBuilder;
use Exception;
{//namespace begin

class AuthBasicAccount extends BaseActor {
	const DEFAULT_ACTION = 'register';

	public function view($aAcctId=null) {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		
		$theImmediateRedirect = parent::view($aAcctId);
		if (empty($theImmediateRedirect)) {
			$bAuthorizied = !empty($aAcctId) && !empty($v->ticket_info) && (
					//everyone is allowed to modify email/pw of their own account
					$aAcctId==$this->director->account_info->account_id ||
					//admins may be allowed to modify someone else's account
					$this->isAllowed('account','modify')
			);
			if ($bAuthorizied) {
				$dbAuth = $this->getProp('Auth');
				$theAuthRow = $dbAuth->getAuthByAccountId($aAcctId);
				$v->ticket_info->email = $theAuthRow['email'];
				
				//post_key needed to actually register (prevent mass bot-fueled registries)
				$this->director['post_key'] = Strings::createUUID();
				$this->director['post_key_ts'] = time()+2; //you can only update your account 2 seconds after the page loads
				$v->post_key = $this->director['post_key'];
			}
		}
		return $theImmediateRedirect;
	}
	
	protected function addNewAccount($aAcctName) {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		$dbAccounts = $this->getProp('Accounts');
		$theResult = $dbAccounts->add(array(
				'account_name' => $aAcctName,
		));
		$this->returnProp($dbAccounts);
		return $theResult;
	}
	
	/**
	 * Register a new user account.
	 * @param string $aAcctName - name of the account (username).
	 * @param string $aAcctPw - password to use.
	 * @param string $aAcctEmail - email to use for the account.
	 * @param string $aRegCode - registration code to match default group.
	 * @param boolean $bAllowGroup0toAutoRegister - only register if default group_id > 0.
	 * @return string Return one of the REGISTRATION_REG_CODE_* constants.
	 */
	protected function registerNewAccount($aAcctName, $aAcctPw, $aAcctEmail, $aRegCode, $bAllowGroup0toAutoRegister=true) {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		if (!empty($aAcctName) && !empty($aAcctPw) && !empty($aAcctEmail) && !empty($aRegCode)) {
			$dbAccounts = $this->getProp('Accounts');
			$dbAuth = $this->getProp('Auth');
			$theCanRegisterResult = $dbAuth->canRegister($aAcctName, $aAcctEmail);
			if ($theCanRegisterResult==$dbAuth::REGISTRATION_SUCCESS) {
				//see if there is a registration code that maps to a particular group
				$dbAuthGroups = $this->getProp('AuthGroups');
				$theDefaultGroup = $dbAuthGroups->findGroupIdByRegCode($this->getAppId(), $aRegCode);
				if ($theDefaultGroup!=0) {
					$theVerifiedTs = $dbAccounts->utc_now();
				} else if (!$bAllowGroup0toAutoRegister) {
					return $dbAuth::REGISTRATION_REG_CODE_FAIL;
				} else {
					$theVerifiedTs = null;
				}
				//now that we have a proper group and account name, lets save stuff!
				$theNewId = $this->addNewAccount($aAcctName);
				if (!empty($theNewId)) {
					$theNewAcct = array(
							'email' => $aAcctEmail,
							'account_id' => $theNewId,
							'pwinput' => $aAcctPw,
							'verified_timestamp' => $theVerifiedTs,
					);
					$dbAuth->registerAccount($theNewAcct,$theDefaultGroup);
					return $dbAuth::REGISTRATION_SUCCESS;
				} else {
					return $dbAuth::REGISTRATION_UNKNOWN_ERROR;
				}
			} else {
				return $theCanRegisterResult;
			}
		}
	}
	
	/**
	 * Process the registration form data and honor the post_key so we protect against bot spam.
	 * @return string Return the URL to redirect to, if any.
	 */
	protected function processRegistrationForm() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		//make sure user/pw reg fields will not interfere with any login user/pw field in header
		$userKey = $v->getUsernameKey().'_reg';
		$pwKey = $v->getPwInputKey().'_reg';
		$bPwOk = ($v->$pwKey===$v->password_confirm);
		$bRegCodeOk = (!empty($v->reg_code));
		$bPostKeyOk = ($this->director['post_key']===$v->post_key);
		//valid time >10sec, <30min
		$bPostKeyOldEnough = ($this->director['post_key_ts'] < time()) && ($this->director['post_key_ts']+(60*30) > time());
		if ($bPostKeyOk && $bPwOk && $bRegCodeOk && $bPostKeyOldEnough) {
			$dbAuth = $this->getProp('Auth');
			$theRegResult = $this->registerNewAccount($v->$userKey, $v->$pwKey, $v->email, $v->reg_code);
			$this->debugLog(__METHOD__.' '.$v->$userKey.' code='.$theRegResult.' redirect='.$v->redirect);
			if (isset($theRegResult)) {
				if ($theRegResult===$dbAuth::REGISTRATION_EMAIL_TAKEN) {
					$v->addUserMsg($this->getRes('account/msg_acctexists/'.$this->getRes('account/label_email')),$v::USER_MSG_ERROR);
				} else if ($theRegResult===$dbAuth::REGISTRATION_NAME_TAKEN) {
					$v->addUserMsg($this->getRes('account/msg_acctexists/'.$this->getRes('account/label_name')),$v::USER_MSG_ERROR);
				} else if ($theRegResult===$dbAuth::REGISTRATION_SUCCESS) {
					if (!empty($v->redirect)) {
						//registration succeeded, save the account id in session cache
						$theAuthRow = $dbAuth->getAuthByEmail($v->email);
						$this->director[$v->getUsernameKey()] = $theAuthRow['account_id'];
						//if the above session cache is not set, the redirect will probably fail (since they are not logged in)
						return $v->redirect;
					} else {
						return $this->getHomePage();
					}
				}
			}
		} else {
			//$this->debugLog('registration failed for '.$v->$userKey.' regok='.($bRegCodeOk?'yes':'no'). ' postkeyok='.($bPostKeyOk?'yes':'no'));
			if ($bPostKeyOk && !$bPostKeyOldEnough) {
				$v->addUserMsg($this->getRes('account/msg_reg_too_fast'));
			}
		}
		return $this->getMyUrl('register');
	}
	
	public function register($aTask='data-entry') {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		if ($aTask==='new') {
			return $this->processRegistrationForm();
		} else {
			//indicate what top menu we are currently in
			$this->setCurrentMenuKey('account');
			
			//make sure user/pw reg fields will not interfere with any login user/pw field in header
			$userKey = $v->getUsernameKey().'_reg';
			$pwKey = $v->getPwInputKey().'_reg';
			
			//post_key needed to actually register (prevent mass bot-fueled registries)
			$this->director['post_key'] = Strings::createUUID();
			$this->director['post_key_ts'] = time()+10; //you can only register 10 seconds after the page loads
			$v->post_key = $this->director['post_key'];
			
			$v->form_name = 'register_user'; //used to prevent login area from displaying
			
			$v->action_url_register = $this->getMyUrl('register/new');
			$dbAccounts = $this->getProp('Accounts');
			if ($dbAccounts->isEmpty()) {
				$v->redirect = $this->getMyUrl('/rights');
				$v->reg_code = $this->getAppId();
			} else {
				$v->redirect = $this->getHomePage();
			}
		}
	}
	
	/**
	 * API for registration returning JSON rather than render a page.
	 * @return APIResponse Returns the standard API response object with User info.
	 */
	public function registerUsingRegCode() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		$this->viewToRender('results_as_json');
		
		//make sure user/pw reg fields will not interfere with any login user/pw field in header
		$userKey = $v->getUsernameKey().'_reg';
		$pwKey = $v->getPwInputKey().'_reg';
		$bPwOk = ($v->$pwKey===$v->password_confirm);
		$bRegCodeOk = (!empty($v->reg_code));
		if ($bPwOk && $bRegCodeOk) {
			$dbAuth = $this->getProp('Auth');
			$theRegResult = $this->registerNewAccount($v->$userKey, $v->$pwKey, $v->email,
					$v->reg_code, filter_var($v->bAllowGroup0toAutoRegister, FILTER_VALIDATE_BOOLEAN));
			//$this->debugLog(__METHOD__.' '.$v->$userKey.' code='.$theRegResult);
			switch ($theRegResult) {
				case $dbAuth::REGISTRATION_SUCCESS :
					//registration succeeded, save the account id in session cache
					$theAuthRow = $dbAuth->getAuthByEmail($v->email);
					//cache the account_id in session data
					$this->getDirector()[$v->getUsernameKey()] = $theAuthRow['account_id'];
					//get account info so we can return it via APIResponse data
					$dbAccounts = $this->getProp('Accounts');
					$this->getDirector()->account_info = $dbAuth->getAccountInfoCache($dbAccounts, $theAuthRow['account_id']);
					$this->ajajGetAccountInfo();
					break;
				case $dbAuth::REGISTRATION_REG_CODE_FAIL :
					throw BrokenLeg::toss($this, 'FORBIDDEN');
				case $dbAuth::REGISTRATION_EMAIL_TAKEN :
					throw BrokenLeg::pratfallRes($this, 'EMAIL_EXISTS', 400,
							'account/msg_acctexists/'.$this->getRes('account/label_email')
					);
				case $dbAuth::REGISTRATION_NAME_TAKEN :
					throw BrokenLeg::pratfallRes($this, 'USERNAME_EXISTS', 400,
							'account/msg_acctexists/'.$this->getRes('account/label_name')
					);
				case $dbAuth::REGISTRATION_CAP_EXCEEDED :
					throw BrokenLeg::toss($this, 'TOO_MANY_REQUESTS');
				default :
					throw BrokenLeg::toss($this, 'DEFAULT');
			}//switch
		} else {
			throw BrokenLeg::toss($this, 'NOT_AUTHENTICATED');
		}
	}

	/**
	 * Process account form input; post_key check required.
	 * @return string Returns the redirect URL, if defined.
	 */
	public function modify() {
		$v =& $this->scene;
		$dbAccounts = $this->getProp('Accounts');
		$theAcctName = $this->scene->ticket_name;
		$theAcctInfo = $dbAccounts->getByName($theAcctName);
		if (!empty($theAcctInfo))
			$theAcctId = $theAcctInfo['account_id'];
		else {
			$v->addUserMsg($this->getRes('generic/msg_permission_denied'), $v::USER_MSG_ERROR);
			return $this->getMyUrl('view');
		}
		$dbAuth = $this->getProp('Auth');
		$pwKeyOld = $this->scene->getPwInputKey().'_old';
		$bPostKeyOk = ($this->director['post_key']===$v->post_key);
		//valid time >10sec, <30min
		$theMinTime = $this->director['post_key_ts'];
		$theNowTime = time();
		$theMaxTime = $theMinTime+(60*30);
		$bPostKeyOldEnough = ($theMinTime < $theNowTime) && ($theNowTime < $theMaxTime);
		if ($dbAuth->isCallable('cudo') && $dbAuth->cudo($theAcctId, $this->scene->$pwKeyOld) && $bPostKeyOk && $bPostKeyOldEnough) {
			//if current pw checked out ok, see if its our own acct or have rights to modify other's accounts.
			if ($theAcctId==$this->director->account_info->account_id || $this->isAllowed('account','modify')) {
				$theOldEmail = trim($this->scene->ticket_email);
				$theNewEmail = trim($this->scene->email);
				/* !== returned TRUE, === returned FALSE, but strcmp() returned 0 (meaning they are the same) O.o
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
						$v->addUserMsg($this->getRes('account/msg_acctexists/'.$this->getRes('account/label_email')), $v::USER_MSG_ERROR);
						return $this->getMyUrl('view/'.$theAcctId);
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
				$v->addUserMsg($this->getRes('account/msg_update_success'), $v::USER_MSG_NOTICE);
				return $this->getMyUrl('view/'.$theAcctId);
			}
		} else {
			$v->addUserMsg($this->getRes('generic/msg_permission_denied'), $v::USER_MSG_ERROR);
			return $this->getMyUrl('view/'.$theAcctId);
		}
	}
	
	/**
	 * API for changing account information returning JSON rather than render a page.
	 * @return APIResponse Returns the standard API response object with User info.
	 */
	public function ajajModify() {
		//do not use the form vars being used for the modify() endpoint, treat like login().
		$v =& $this->scene;
		if ($this->isGuest())
			throw BrokenLeg::toss($this, 'NOT_AUTHENTICATED');
		
		$dbAccounts = $this->getProp('Accounts');
		$dbAuth = $this->getProp('Auth');
		//get username and convert to account_id, if possible
		$theAcctName = $v->account_name;
		$theAcctInfo = $dbAccounts->getByName($theAcctName);
		if (!empty($theAcctInfo))
			$theAcctId = $theAcctInfo['account_id'];
		else
			throw BrokenLeg::toss($this, 'FORBIDDEN');
		
		//check pw
		$pwKeyOld = $v->getPwInputKey().'_old';
		$bCurrentPwMatch = (
				$dbAuth->isCallable('cudo') &&
				$dbAuth->cudo($theAcctId, $v->$pwKeyOld)
		);
		//check permissions
		$bAuthorizied = (
				//everyone is allowed to modify email/pw of their own account
				$theAcctId==$this->director->account_info->account_id ||
				//admins may be allowed to modify someone else's account
				$this->isAllowed('account','modify')
		);
		
		if ($bCurrentPwMatch && $bAuthorizied) {
			try {
				//update EMAIL
				$theOldEmail = trim($v->email_old);
				$theNewEmail = trim($v->email_new);
				if (strcmp($theOldEmail,$theNewEmail)!=0) {
					//Strings::debugLog('email is not 0:'.strcmp($theOldEmail,$theNewEmail));
					if ($dbAuth->getAuthByEmail($theNewEmail)) {
						throw BrokenLeg::pratfallRes($this, 'EMAIL_EXISTS', 400,
								'account/msg_acctexists/'.$this->getRes('account/label_email')
						);
					} else {
						$theSql = SqlBuilder::withModel($dbAuth)->obtainParamsFrom(array(
								'email' => $theNewEmail,
								'account_id' => $theAcctId,
						));
						$theSql->startWith('UPDATE')->add($dbAuth->tnAuth);
						$theSql->add('SET')->mustAddParam('email');
						$theSql->startWhereClause()->mustAddParam('account_id')->endWhereClause();
						$theSql->execDML();
					}
				}
				
				//update PASSWORD
				$pwKeyNew = $v->getPwInputKey().'_new';
				$pwKeyConfirm = $v->getPwInputKey().'_confirm';
				if (!empty($v->$pwKeyNew) && ($v->$pwKeyNew===$v->$pwKeyConfirm)) {
					$theSql = SqlBuilder::withModel($dbAuth)->obtainParamsFrom(array(
							'pwhash' => Strings::hasher($v->$pwKeyNew),
							'account_id' => $theAcctId,
					));
					$theSql->startWith('UPDATE')->add($dbAuth->tnAuth);
					$theSql->add('SET')->mustAddParam('pwhash');
					$theSql->startWhereClause()->mustAddParam('account_id')->endWhereClause();
					$theSql->execDML();
				}

				//all modifications went ok, get the account info and return it
				$theChangedAccountInfo = $dbAuth->getAccountInfoCache($dbAccounts, $theAcctId);
				if ($theAcctId==$this->getDirector()->account_info->account_id) {
					//if changing my own account, update my account cache
					$this->getDirector()->account_info = $theChangedAccountInfo;
				}
				$theChangedAccountInfo->account_id += 0; //ensure what is returned is not a string
				$v->results = APIResponse::resultsWithData($theChangedAccountInfo);
			} catch (Exception $e) {
				BrokenLeg::tossException($this, $e);
			}
		} else
			throw BrokenLeg::toss($this, 'FORBIDDEN');
	}

	/**
	 * Called by requestPasswordReset() when the action is "proc" (process a
	 * request).
	 */
	private function processPasswordResetRequest()
	{
		$v =& $this->scene ;
		$theAddr =& $v->send_to_email ;
		$dbAuth = $this->getProp('Auth') ;
		$theResetUtils = AuthPasswordReset::withModel($dbAuth) ;
		
		if( ! $dbAuth->isPasswordResetAllowedFor( $theAddr, $theResetUtils ) )
		{ // Deny the request.
			$v->err_msg = $v->getRes( 'account/msg_pw_request_denied' ) ;
			return $this->requestPasswordReset(null) ;
		}
		
		try
		{
			$dbAuth->generatePasswordRequestFor( $theResetUtils ) ;
			$theMailer = MailUtils::buildMailerFromBitsConfig(
					$this->config, 'email_out' ) ;
			$theMailer->setFrom( $this->config['email_out/default_from'] ) ;
			$theResetUtils->dispatchEmailToUser( $theMailer ) ;
		}
		catch( AuthPasswordResetException $aprx )
		{
			$v->err_msg = $aprx->getDisplayText() ;
			$this->debugLog($v->err_msg) ;
			return $this->requestPasswordReset(null) ;
		}
		catch( MailUtilsException $mue )
		{
			$v->err_msg = $mue->getMessage() ;
			$this->debugLog($v->err_msg) ;
			return $this->requestPasswordReset(null) ;
		}
		
		$theSuccessMsg = $this->getRes(
				'account/msg_pw_reset_email_sent/' . $v->send_to_email ) ;
		$v->addUserMsg( $theSuccessMsg ) ;
		return $this->getHomePage() ;
	}
	
	/**
	 * Allows an end user to request a password change.
	 * @param string $aAction an action indicator passed from the request form,
	 *   if any; currently supports null or "proc"
	 */
	public function requestPasswordReset( $aAction=null )
	{
		$v =& $this->scene ;
		if( $aAction == 'proc' && !empty( $v->send_to_email ) )
			return $this->processPasswordResetRequest() ;
		else
		{ // Display the request form.
			$v->action_url_requestpwreset =
				$v->getSiteUrl( $this->config['auth/request_pwd_reset_url'] ) ;
			$this->setCurrentMenuKey( 'account' ) ;
		}
	}
	
	/**
	 * Catches a password reentry, verifies that it matches an existing token,
	 * and redirects to password entry if successful.
	 * @param string $aAuthID (from URL) the auth ID 
	 * @param string $aAuthToken (from URL) the auth token
	 */
	public function passwordResetReentry( $aAuthID, $aAuthToken )
	{
		$v =& $this->scene ;
		$utils = AuthPasswordReset::withModel($this->getProp('auth')) ;
		
		$isAuthenticated = false ;
		try
		{
			$isAuthenticated =
				$utils->authenticateForReentry( $aAuthID, $aAuthToken ) ;
		}
		catch( AuthPasswordResetException $aprx )
		{ $this->debugLog( $aprx->getDisplayText() ) ; }
		
		if( $isAuthenticated )
		{ // postcondition if true: user is now authenticated
//			$this->debugLog( 'Reentry authenticated for [' . $aAuthID . '].' ) ;
			$utils->clobberPassword() ;
			return $this->getSiteUrl('account/view/' . $utils->getAccountID()) ;
		}
		else
		{
			$theFailureMsg = $this->getRes( 'account/err_pw_request_failed' ) ;
			$v->addUserMsg( $theFailureMsg, MyScene::USER_MSG_ERROR ) ;
			return $this->getHomePage() ;
		}
	}
	
	/**
     * Register a user via mobile app rather than on web page.
	 * POST vars expected: name, salt, email, code, fingerprints
     * @return Returns JSON encoded array[code, user_token, auth_token]
	 */
	public function registerViaMobile() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		$this->renderThisView = 'results_as_json';
		$dbAuth = $this->getProp('Auth');
		//$this->debugLog('regargs='.$v->name.', '.$v->salt.', '.$v->email.', '.$v->code);
		$theRegResult = $this->registerNewAccount($v->name, $v->salt, $v->email, $v->code, false);
		if ($theRegResult===$dbAuth::REGISTRATION_SUCCESS) {
			$theMobileRow = $dbAuth->registerMobileFingerprints($dbAuth->getAuthByEmail($v->email),
					$v->fingerprints, $v->circumstances);
			$v->results = array(
					'code' => $theRegResult,
					'auth_id' => $theMobileRow['auth_id'],
					'user_token' => $theMobileRow['account_token'],
			);
			$this->debugLog($v->name.' successfully registered an account via mobile: '.$theMobileRow['account_token']);
		} else {
			if (!isset($theRegResult))
				$theRegResult = $dbAuth::REGISTRATION_UNKNOWN_ERROR;
			$v->results = array('code' => $theRegResult);
			$this->debugLog($v->name.' unsuccessfully tried to register an account via mobile. ('.$theRegResult.')');
		}
	}
	
	/**
	 * Mobile auth is a bit more involved than Basic HTTP auth, use this mechanism
	 * for authenticating mobile devices (which may be rooted).
	 * @param string $aPing - (optional) ping string which could be used to pong a response.
     * @return Returns JSON encoded array[account_name, user_token, auth_token, api_version_seq]
	 */
	public function requestMobileAuth($aPing=null) {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		$this->renderThisView = 'results_as_json';
		if (empty($aPing)) {
			$dbAuth = $this->getProp('Auth');
			if ($v->no_session) {
				$this->director->destroySessionOnCleanup();
			}
			if (!$this->isGuest()) {
				$v->results = $dbAuth->requestMobileAuthAfterPwLogin(
						$this->director->account_info, $v->fingerprints, $v->circumstances);
			} else {
				$v->results = $dbAuth->requestMobileAuthAutomatedByTokens(
						$v->auth_id, $v->user_token, $v->fingerprints, $v->circumstances);
			}
			if (empty($v->results)) {
				$this->director->logout();
			}
		}
		
		/* exmple of descendant code
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		parent::requestMobileAuth($aPing);
		if (empty($v->results) && $aPing==='ping') {
			$v->results = array(
					'user_token' => $aPing,
					'auth_token' => 'pong',
					'api_version_seq' => $this->getRes('website/api_version_seq'),
			);
		}
		*/
	}

}//end class

}//end namespace
