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
use BitsTheater\costumes\AuthPasswordReset;
use BitsTheater\costumes\colspecs\CommonMySql;
use BitsTheater\outtakes\AccountAdminException;
use BitsTheater\outtakes\PasswordResetException;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\MailUtils;
use com\blackmoonit\MailUtilsException;
use com\blackmoonit\Arrays;
use com\blackmoonit\Strings;
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\APIResponse;
use BitsTheater\costumes\HttpAuthHeader;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\AuthAccount;
use BitsTheater\costumes\AuthAccountSet;
use BitsTheater\costumes\AuthGroup;
use BitsTheater\costumes\AuthGroupList;
use BitsTheater\costumes\WornForAuditFields;
use Exception;
use PDOStatement ;
{//namespace begin

class AuthBasicAccount extends BaseActor
{
	use WornForAuditFields;
	
	const DEFAULT_ACTION = 'register';

	/**
	 * The model that we expect to use for access to account data.
	 * @var string
	 * @since BitsTheater 3.6
	 */
	const CANONICAL_MODEL = 'Accounts' ;

	/**
	 * Token to use for "ping"; override in descendant.
	 * @see AuthBasicAccount::requestMobileAuth()
	 * @var string
	 */
	const MAGIC_PING_TOKEN = 'pInG';

	/**
	 * Fetches an instance of the model usually accessed by this actor, granting
	 * access to account data.
	 * @return Model - an instance of the model
	 * @throws BrokenLeg - 'DB_CONNECTION_FAILED' if the model can't connect to
	 *  the database
	 * @since BitsTheater 3.6
	 */
	protected function getCanonicalModel()
	{
		return $this->getProp( self::CANONICAL_MODEL ) ;
	}

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
				
				//CSRF protection via form data: we need to use a secret hidden form value
				$this->director['post_key'] = Strings::createUUID().Strings::createUUID();
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
							$dbAuth::KEY_userinfo => $aAcctName,
							'email' => $aAcctEmail,
							'account_id' => $theNewId,
							$dbAuth::KEY_pwinput => $aAcctPw,
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
		if (!empty($v->requested_by)) $this->getHomePage(); //if honeypot filled, ignore spambot
		//make sure user/pw reg fields will not interfere with any login user/pw field in header
		$userKey = $v->getUsernameKey().'_reg';
		$pwKey = $v->getPwInputKey().'_reg';
		$bPwOk = ($v->$pwKey===$v->password_confirm);
		$bRegCodeOk = (!empty($v->reg_code));
		$bPostKeyOk = ($this->director['post_key']===$v->post_key);
		//valid time >10sec, <30min
		$bPostKeyOldEnough = ($this->director['post_key_ts'] < time()) && ($this->director['post_key_ts']+(60*30) > time());
		unset($this->director['post_key']); unset($this->director['post_key_ts']);
		if ($bPostKeyOk && $bPwOk && $bRegCodeOk && $bPostKeyOldEnough) {
			$dbAuth = $this->getProp('Auth');
			$theRegResult = $this->registerNewAccount($v->$userKey, $v->$pwKey, $v->email, $v->reg_code);
			//$this->debugLog(__METHOD__.' '.$v->$userKey.' code='.$theRegResult.' redirect='.$v->redirect);
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
			$this->director['post_key'] = Strings::createUUID().Strings::createUUID();
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
					//since we are "logged in" via a non-standard mechanism, create an anti-CSRF token
					$dbAuth->setCsrfTokenCookie();
					//we may wish to return the newly created account data
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
	 * CSRF protection not necessary since password entry is required to change
	 * anything.
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
		unset($this->director['post_key']); unset($this->director['post_key_ts']);
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
		$bAuthorized = (
				//everyone is allowed to modify email/pw of their own account
				$theAcctId==$this->director->account_info->account_id ||
				//admins may be allowed to modify someone else's account
				$this->isAllowed('account','modify')
		);
		
		if ($bCurrentPwMatch && $bAuthorized) {
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
				throw BrokenLeg::tossException($this, $e);
			}
		} else
			throw BrokenLeg::toss($this, 'FORBIDDEN');
	}

	/**
	 * Called by requestPasswordReset() when the action is "proc" (process a
	 * request).
	 */
	protected function processPasswordResetRequest()
	{
		$v =& $this->scene ;
		if (!empty($v->requested_by)) $this->getHomePage(); //if honeypot filled, ignore spambot
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
		catch( PasswordResetException $prx )
		{
			//also remove all pw reset tokens for this account
			$theResetUtils->deleteAllTokens() ;
			$v->err_msg = $prx->getDisplayText() ;
			$this->errorLog($v->err_msg) ;
			return $this->requestPasswordReset(null) ;
		}
		catch( MailUtilsException $mue )
		{
			//also remove all pw reset tokens for this account
			$theResetUtils->deleteAllTokens() ;
			$v->err_msg = $mue->getMessage() ;
			$this->errorLog($v->err_msg) ;
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
		$utils = AuthPasswordReset::withModel($this->getProp('Auth')) ;
		
		$isAuthenticated = false ;
		try
		{
			$isAuthenticated =
				$utils->authenticateForReentry( $aAuthID, $aAuthToken ) ;
		}
		catch( PasswordResetException $prx )
		{ $this->errorLog( $prx->getDisplayText() ) ; }
		
		if( $isAuthenticated )
		{ // postcondition if true: user is now authenticated
//			$this->debugLog( 'Reentry authenticated for [' . $aAuthID . '].' ) ;
			$utils->clobberPassword() ;
			//since we are "logged in" via a non-standard mechanism, create an anti-CSRF token
			$utils->getModel()->setCsrfTokenCookie() ;
			//also remove all pw reset tokens for this account
			$utils->deleteAllTokens() ;
			return $this->getSiteUrl('account/view/' . $utils->getAccountID()) ;
		}
		else
		{
			$theFailureMsg = $this->getRes( 'account/err_pw_request_failed' ) ;
			$v->addUserMsg( $theFailureMsg, $v::USER_MSG_ERROR ) ;
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
			$theAuthHeader = HttpAuthHeader::fromHttpAuthHeader($this->getDirector(),
					(!empty($v->auth_header_data)) ? $v->auth_header_data : null
			);
			$theMobileRow = $dbAuth->registerMobileFingerprints(
					$dbAuth->getAuthByEmail($v->email), $theAuthHeader
			);
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
			//$this->debugLog(__METHOD__.$v->debugStr($v->auth_header_data));
			$theAuthHeader = HttpAuthHeader::fromHttpAuthHeader($this->getDirector(),
					(!empty($v->auth_header_data)) ? $v->auth_header_data : null
			);
			$dbAuth = $this->getProp('Auth');
			if (!$this->isGuest()) {
				//$this->debugLog(__METHOD__.' login account found.');
				$v->results = $dbAuth->requestMobileAuthAfterPwLogin(
						$this->director->account_info, $theAuthHeader
				);
			} else {
				//$this->debugLog(__METHOD__.' login using broadway auth unsuccessful');
				$v->results = $dbAuth->requestMobileAuthAutomatedByTokens(
						$v->auth_id, $v->user_token, $theAuthHeader
				);
			}
			if (empty($v->results)) {
				//$this->debugLog(__METHOD__.' mobile auth fail; logging out');
				$this->director->logout();
			}
		} else if ($aPing===static::MAGIC_PING_TOKEN) {
			$v->results = array(
					'challenge' => 'ping',
					'response' => 'pong',
					'api_version_seq' => $v->getRes('website/api_version_seq'),
			);
		}
		
		/* example of descendant code
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		parent::requestMobileAuth($aPing);
		if (empty($v->results) && $aPing==='MY_PING_STRING') {
			$v->results = array(
					'challenge' => $aPing,
					'response' => 'pong',
					'api_version_seq' => $this->getRes('website/api_version_seq'),
			);
		}
		*/
	}

	/**
	 * Allows a site administrator to provision an account on behalf of another
	 * user, device, or agent.
	 * @param string account_name POST body parameter. Required; name of account to
	 * create. Leading / ending whitespace will be trimmed.
	 * @param string account_password POST body parameter. Required; password for
	 * account to create. Leading / ending whitespace will be trimmed.
	 * @param string email POST body parameter. Required, must be unique;
	 * email address of account to create. Leading / ending whitespace will be
	 * trimmed.
	 * @param string account_group_id POST body parameter. Optional; group id to map
	 * to this new account. Leading / ending whitespace will be trimmed.
	 * @param string account_registration_code POST body parameter. Optional; registration
	 * code to be used to assign a group to this new account. Leading / ending
	 * whitespace will be trimmed. If aGroupId is specified, this parameter
	 * is ignored.
	 * @throws BrokenLeg
	 * * 'FORBIDDEN' - if user doesn't have accounts/create and accounts/view
	 * access.
	 * * 'MISSING_ARGUMENT' - if the account name is not specified.
	 * * 'UNIQUE_FIELD_ALREADY_EXISTS' - if unique field is specified to update,
	 *  but already exists in the system.
	 * * 'DB_EXCEPTION' - If another db exception occurs.
	 * @since BitsTheater 3.6
	 */
	public function ajajCreate()
	{
		// Check Permissions.
		if( !$this->isAllowed( 'accounts', 'create' ) || !$this->isAllowed( 'accounts', 'view' ))
			throw BrokenLeg::toss( $this, 'FORBIDDEN' );

		// Retrieve our passed-in values.
		$v =& $this->scene;
		$aName 		= trim ( $v->account_name );
		$aPassword 	= trim ( $v->account_password );
		$aEmail 	= trim ( $v->email );
		$aGroupId 	= (isset($v->account_group_id)) ? $v->account_group_id : $v->account_group_ids ;
		$aRegCode 	= trim ( $v->account_registration_code );

		// Ensure required parameters are specified.
		if ( empty ( $aName ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', "account_name" );
		if ( empty ( $aPassword ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', "account_password" );
		if ( empty ( $aEmail ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', "email" );

		// Reference respective models required.
		$dbAccounts = $this->getCanonicalModel();
		$dbAuth = $this->getProp('Auth');
		$dbAuthGroups = $this->getProp('AuthGroups');

		// Parse default group affiliation for this new account.
		if ( empty ( $aGroupId ) ) {
			if ( empty ( $aRegCode ) ) {
				// New account will have no default group affiliation.
				$accountGroup = 0;
			} else {
				// New account will have default group specified by supplied registration code.
				$accountGroup = $dbAuthGroups->findGroupIdByRegCode($this->getAppId(), $aRegCode);
			}
		} else {
			$filterOptions = array(
					'default' => $dbAuthGroups::UNREG_GROUP_ID, // value to return if the filter fails
					'flags' => FILTER_FLAG_NONE,
			);
			// New account will have the specified group id as its default group.
			if (is_array($aGroupId)) {
				$accountGroup = array();
				foreach ($aGroupId as $anID) {
					$theID = filter_var($anID, FILTER_VALIDATE_INT, $filterOptions);
					//if not TITAN group and not already in group, add to group list
					if ( ($theID!==$dbAuthGroups::TITAN_GROUP_ID) &&
						(array_search($theID, $accountGroup, true)===false) )
					{
						$accountGroup[] = $theID;
					}
				}
				// Ensure not trying to create an account affiliated with the special TITAN group.
				if ( array_search( $dbAuthGroups::TITAN_GROUP_ID, $accountGroup ) !== false )
					throw AccountAdminException::toss( $this, 'CANNOT_CREATE_TITAN_ACCOUNT' );
			} else {
				$accountGroup = filter_var($aGroupId, FILTER_VALIDATE_INT, $filterOptions);
				// Ensure not trying to create an account affiliated with the special TITAN group.
				if ( $accountGroup == $dbAuthGroups::TITAN_GROUP_ID )
					throw AccountAdminException::toss( $this, 'CANNOT_CREATE_TITAN_ACCOUNT' );
			}
		}

		// Verify new account can be registered.
		$canRegister = $dbAuth->canRegister( $aName, $aEmail );
		if ( $canRegister == $dbAuth::REGISTRATION_SUCCESS )
		{
			// Define verified time, if new account will be associated with a register group.
			$verifiedTimestamp = ( ( $accountGroup != $dbAuthGroups::UNREG_GROUP_ID )
					? $dbAccounts->utc_now()
					: null
			);
		} elseif ($canRegister == $dbAuth::REGISTRATION_NAME_TAKEN) {
			throw AccountAdminException::toss( $this,
					'UNIQUE_FIELD_ALREADY_EXISTS', $aName ) ;
			throw BrokenLeg::toss( $this, 'DB_EXCEPTION', "Name already exists in system." );
		} elseif ($canRegister == $dbAuth::REGISTRATION_EMAIL_TAKEN) {
			throw AccountAdminException::toss( $this,
					'UNIQUE_FIELD_ALREADY_EXISTS', $aEmail ) ;
		}

		// Add account to Accounts table, generating our account ID.
		$generatedAccountId = $this->addNewAccount( $aName );
		if ( !empty ( $generatedAccountId ) )
		{
			// Aggregate our account data for registration.
			$newAccountData = array(
					'email' => $aEmail,
					'account_id' => $generatedAccountId,
					$dbAuth::KEY_pwinput => $aPassword,
					'verified_timestamp' => $verifiedTimestamp,
			);
			if (isset($v->account_is_active))
				$newAccountData['account_is_active'] = $v->account_is_active;

			// Register account with affliated group.
			$registrationResult = $dbAuth->registerAccount( $newAccountData, $accountGroup );

			// Return successful APIResponse, generated by ajajGet().
			$this->ajajGet( $generatedAccountId );
		} else {
			// Handle problem with adding new account and generating account ID.
			throw BrokenLeg::toss( $this, 'DB_EXCEPTION', "Error in adding new account." );
		}
	}

	/**
	 * Allows a site administrator to update the details of an existing account
	 * on behalf of another user, device, or agent.
	 * @param integer $aAccountId the account ID (if null, fetch from POST var
	 *  'account_id' instead)
	 * @param string account_name Optional POST body parameter. Name to update of
	 * account. Leading / ending whitespace will be trimmed.
	 * @param string account_password Optional POST body parameter. Password to update
	 * of account. Leading / ending whitespace will be trimmed.
	 * @param string email Optional POST body parameter. Email to update of
	 * account. Leading / ending whitespace will be trimmed.
	 * @param string account_group_ids Optional POST body parameter. Array of group ids
	 * to update of account. All previous group affiliations for this account
	 * will be removed, replaced with updated group affiliations from this array.
	 * Be aware that attempting to update account to the group id of the TITAN
	 * group will result in an exception being thrown.
	 * @throws BrokenLeg
	 *  * 'MISSING_ARGUMENT' - if the account ID is not specified
	 *  * 'ENTITY_NOT_FOUND' - if no account with that ID exists
	 *  * 'FORBIDDEN' - if user doesn't have accounts/modify.
	 *  * 'UNIQUE_FIELD_ALREADY_EXISTS' - if unique field is specified to update,
	 *  but already exists in the system.
	 *  * 'CANNOT_UPDATE_TO_TITAN' - if group_id of the TITAN group is specified.
	 * @since BitsTheater 3.6
	 */
	public function ajajUpdate( $aAccountId = null )
	{
		// Check Permissions.
		if( ! $this->isAllowed( 'accounts', 'modify' ) )
			throw BrokenLeg::toss( $this, 'FORBIDDEN' );

		// Retrieve our passed-in values.
		$v =& $this->scene;
		$aAccountId = trim ( $this->getEntityID( $aAccountId, 'account_id' ));
		$aName 		= trim ( $v->account_name );
		$aPassword 	= trim ( $v->account_password );
		$aEmail 	= trim ( $v->email );
		$aGroupIds  = $v->account_group_ids;
		if (isset($v->account_is_active))
			$aIsActive  = (!empty($v->account_is_active)) ? 1 : 0;

		// Reference respective models required.
		$dbAccounts = $this->getCanonicalModel();
		$dbAuth = $this->getProp('Auth');
		$dbAuthGroups = $this->getProp('AuthGroups');
		try
		{
			// Retrieve existing account for user.
			$existingAccount = $dbAccounts->getAccount( $aAccountId );
			if( empty($existingAccount) )
				throw BrokenLeg::toss( $this, 'ENTITY_NOT_FOUND', $aAccountId );
			$fullAccountInfo = (( object )( $existingAccount ));
			$this->addAuthAndEmailTo( $fullAccountInfo );
			$fullAccountInfo->groups = $this->getGroupsForAccount( $fullAccountInfo->account_id );

			// Determine what values are different than existing values.
			if ( !empty ( $aName ))
				$updatedName = ( ( $aName === $fullAccountInfo->account_name ) ? null : $aName );
			if ( !empty ( $aPassword ))
				$updatedPassword = ( ( $dbAuth->cudo( $aAccountId, $aPassword ) ) ? null : $aPassword );
			if ( !empty ( $aEmail ))
				$updatedEmail = ( ( $aEmail === $fullAccountInfo->email ) ? null : $aEmail );
			if ( isset( $aIsActive ) && $this->isAllowed( 'accounts', 'activate' ) )
				$updatedIsActive = ( ( $aIsActive == $fullAccountInfo->is_active ) ? null : $aIsActive );

			// Update email, if applicable.
			if ( !empty ( $updatedEmail ))
			{
				// Verify new unique email update doesn't already exist in system.
				if ( $dbAuth->getAuthByEmail( $updatedEmail ) )
				{
					throw AccountAdminException::toss( $this,
						'UNIQUE_FIELD_ALREADY_EXISTS', $updatedEmail );
				} else {
					$theSql = SqlBuilder::withModel( $dbAuth )->obtainParamsFrom(
							array(
								'email' => $updatedEmail,
								'account_id' => $aAccountId
							));
					$theSql->startWith( 'UPDATE' )->add( $dbAuth->tnAuth );
					$this->setAuditFieldsOnUpdate($theSql)->mustAddParam( 'email' );
					$theSql->startWhereClause()->mustAddParam( 'account_id' )->endWhereClause();
					$theSql->execDML();
				}
			}

			// Update name, if applicable.
			if ( !empty ( $updatedName ))
			{
				// Verify new unique name update doesn't already exist in system.
				if ( $dbAccounts->getByName( $updatedName ) )
				{
					throw AccountAdminException::toss( $this,
							'UNIQUE_FIELD_ALREADY_EXISTS', $updatedName );
				} else {
					$theSql = SqlBuilder::withModel( $dbAccounts )->obtainParamsFrom(
							array(
								'account_name' => $updatedName,
								'account_id' => $aAccountId
							));
					$theSql->startWith( 'UPDATE' )->add( $dbAccounts->tnAccounts );
					$theSql->add( 'SET' )->mustAddParam( 'account_name' );
					$theSql->startWhereClause()->mustAddParam( 'account_id' )->endWhereClause();
					$theSql->execDML();
				}
			}

			// Update password, if applicable.
			if ( !empty ( $updatedPassword ))
			{
				$theSql = SqlBuilder::withModel( $dbAuth )->obtainParamsFrom(
						array(
							'pwhash' => Strings::hasher( $updatedPassword ),
							'account_id' => $aAccountId
						));
				$theSql->startWith( 'UPDATE' )->add( $dbAuth->tnAuth );
				$this->setAuditFieldsOnUpdate($theSql)->mustAddParam( 'pwhash' );
				$theSql->startWhereClause()->mustAddParam( 'account_id' )->endWhereClause();
				$theSql->execDML();
			}

			//update is_active, if applicable
			if ( isset($updatedIsActive) )
				$dbAuth->setInvitation( $aAccountId, $updatedIsActive );

			// Update account group, if applicable.
			if ( isset ( $aGroupIds ))
			{
				// First we want to remove existing mappings of group ids for this account.
				$currentAuthGroups = $dbAuthGroups->getAcctGroups( $aAccountId );
				foreach ($currentAuthGroups as &$thisGroupId)
				{
					// Ensure not trying to remove group affiliation to special TITAN group.
					if ( $thisGroupId != $dbAuthGroups::TITAN_GROUP_ID )
						$dbAuthGroups->delAcctMap($thisGroupId, $aAccountId);
				}
				// Now insert mapping of account with updated group id values.
				foreach ($aGroupIds as &$thisNewGroupId)
				{
					// Ensure not trying to set group affiliation to special TITAN group.
					if ( $thisNewGroupId == $dbAuthGroups::TITAN_GROUP_ID )
						throw AccountAdminException::toss( $this, 'CANNOT_UPDATE_TO_TITAN' );

					// Add mapping.
					$dbAuthGroups->addAcctMap( $thisNewGroupId, $aAccountId );
				}
			}
		}
		catch( Exception $x ) { throw BrokenLeg::tossException( $this, $x ); }

		// Print out successful APIResponse, generated by ajajGet().
		$this->ajajGet( $aAccountId );
	}

	/**
	 * Allows a site administrator to view the details of an existing account.
	 * @param integer $aAccountID the account ID (if null, fetch from POST var
	 *  'account_id' instead)
	 * @throws BrokenLeg
	 *  * 'FORBIDDEN' - if user doesn't have accounts/view access
	 *  * 'MISSING_ARGUMENT' - if the account ID is not specified
	 *  * 'ENTITY_NOT_FOUND' - if no account with that ID exists
	 * @since BitsTheater 3.6
	 */
	public function ajajGet( $aAccountID=null )
	{
		$theAccountID = $this->getEntityID( $aAccountID, 'account_id' ) ;
		if( ! $this->isAllowed( 'accounts', 'view' ) )
			throw BrokenLeg::toss( $this, 'FORBIDDEN' ) ;
		$dbAccounts = $this->getCanonicalModel() ;
		try
		{
			$theAccount = $dbAccounts->getAccount($theAccountID) ;
			if( $theAccount == null ) // also try by name
				$theAccount = $dbAccounts->getByName($theAccountID) ;
		}
		catch( Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
		if( $theAccount == null )
			throw BrokenLeg::toss( $this, 'ENTITY_NOT_FOUND', $theAccountID ) ;
		$theReturn = ((object)($theAccount)) ;
		$this->addAuthAndEmailTo( $theReturn ) ;
		$theReturn->groups =
				$this->getGroupsForAccount( $theReturn->account_id ) ;
		$this->addMobileHardwareIdsForAutoLogin( $theReturn );
		$this->scene->results = APIResponse::resultsWithData( $theReturn ) ;
	}

	/**
	 * Fetches the auth history and email address associated with the account,
	 * since it's in a separate table.
	 * Consumed by ajajGet() and ajajGetAll().
	 * @param object $aAccountInfo the information that we have about the
	 *  account so far, to which we will write more data
	 * @since BitsTheater 3.6
	 */
	protected function addAuthAndEmailTo( $aAccountInfo )
	{
		$dbAuth = $this->getProp( 'Auth' ) ;
		try
		{
			$theAuth = ((object)($dbAuth->getAuthByAccountId($aAccountInfo->account_id))) ;
			if( $theAuth != null )
			{
				$aAccountInfo->auth_id = $theAuth->auth_id;
				$aAccountInfo->email = $theAuth->email ;
				$aAccountInfo->is_active = ((boolean)($theAuth->is_active)) ;
				$aAccountInfo->verified_ts =
					CommonMySql::convertSQLTimestampToISOFormat($theAuth->verified_ts) ;
				$aAccountInfo->created_ts =
					CommonMySql::convertSQLTimestampToISOFormat($theAuth->created_ts) ;
				$aAccountInfo->updated_ts =
					CommonMySql::convertSQLTimestampToISOFormat($theAuth->updated_ts) ;
			}
		}
		catch( Exception $x )
		{
			$this->errorLog( __METHOD__
					. ' failed to fetch an email address for account ID ['
					. $aAccountID . '] because of an exception: '
					. $x->getMessage()
				);
		}
	}

	/**
	 * Fetches information about the groups to which an account belongs.
	 * Consumed by ajajGet() and ajajGetAll()
	 * @param integer $aAccountID the account ID
	 * @return array objects describing each permission group
	 * @since BitsTheater 3.6
	 */
	protected function getGroupsForAccount( $aAccountID=null )
	{
		$dbGroups = $this->getProp( 'AuthGroups' ) ;
		try
		{
			$theReturn = array() ;
			$theGroupIDs = $dbGroups->getAcctGroups($aAccountID) ;
			if( empty($theGroupIDs) )
				return $theReturn ;
			foreach( $theGroupIDs as $theGroupID )
			{
				$theGroup = $dbGroups->getGroup($theGroupID) ;
				if( $theGroup !== null )
					$theReturn[] = ((object)($theGroup)) ;
			}
			return $theReturn ;
		}
		catch( Exception $x )
		{
			$this->errorLog( __METHOD__
					. ' could not fetch groups for account ['
					. $aAccountID . '] because of an exception: '
					. $x->getMessage()
				);
			return null ;
		}
	}
	
	/**
	 * Fetches the hardware IDs for mobile auto-login associated with the
	 * account, since it's in a separate table.
	 * Consumed by ajajGet() and ajajGetAll().
	 * @param object $aAccountInfo - the information that we have about the
	 *  account so far, to which we will write more data
	 * @since BitsTheater 3.6.2
	 */
	protected function addMobileHardwareIdsForAutoLogin( $aAccountInfo )
	{
		$dbAuth = $this->getProp( 'Auth' ) ;
		try
		{
			$theIDs = $dbAuth->getMobileHardwareIdsForAutoLogin(
					$aAccountInfo->auth_id, $aAccountInfo->account_id
			);
			if (!empty($theIDs)) {
				$aAccountInfo->hardware_ids = $theIDs ;
			}
		}
		catch( Exception $x )
		{
			$this->errorLog( __METHOD__
					. ' failed to fetch Mobile Hardware IDs for account ID ['
					. $aAccountID . '] because of an exception: '
					. $x->getMessage()
			);
		}
	}
	
	/**
	 * Standard output for either getAll or getAllInGroup.
	 * @param PDOStatement $aRowSet - the result set to return.
	 * @return AuthAccountSet Returns the wrapper class used.
	 * @since BitsTheater 3.7.0
	 */
	protected function getAuthAccountSet($aRowSet) {
		$v =& $this->scene ;
		//get all fields, even the optional ones
		$theFieldList = AuthAccount::getDefinedFields();
		//construct our iterator object
		$theAccountSet = AuthAccountSet::create( $this->getDirector() )
				->setItemClass(AuthAccount::ITEM_CLASS,
						array($this->getProp('Auth'), $theFieldList) )
				->setDataFromPDO($aRowSet)
				;
		$theAccountSet->filter = $v->filter ;
		$theAccountSet->total_count = $v->getPagerTotalRowCount() ;
					
		//include group details.
		$theGroupFieldList = array('group_id', 'group_name');
		$theAccountSet->mGroupList = AuthGroupList::create( $this->getDirector() )
				->setFieldList($theGroupFieldList)
				->setItemClass(AuthGroup::ITEM_CLASS,
						array($this->getProp('AuthGroups'), $theGroupFieldList) )
				;
					
		return $theAccountSet;
	}

	/**
	 * Consumed by ajajGetAll().
	 * @param integer $aGroupID the ID of the account group
	 * @since BitsTheater 3.6.0
	 */
	protected function getAllInGroup( $aGroupID )
	{
		$dbGroups = $this->getProp('AuthGroups');
		if( ! $dbGroups->groupExists($aGroupID) )
			throw BrokenLeg::toss( $this, 'ENTITY_NOT_FOUND', $aGroupID ) ;
		$dbAuth = $this->getProp('Auth');
		try {
			$theRowSet = $dbAuth->getAccountsToDisplay($this->scene, $aGroupID);
			$this->scene->results = APIResponse::resultsWithData(
					$this->getAuthAccountSet($theRowSet)
			);
		}
		catch (Exception $x)
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}

	/**
	 * Allows a site administrator to view the details of all existing accounts
	 * on the system, or all accounts in a particular "role" (permission group).
	 * @param integer $aGroupID - (optional) the ID of a permission group
	 * @throws BrokenLeg 'ENTITY_NOT_FOUND' - if GroupID does not exist
	 * @since BitsTheater 3.5.3
	 */
	public function ajajGetAll( $aGroupID=null )
	{
		if( ! $this->isAllowed( 'accounts', 'view' ) )
			throw BrokenLeg::toss( $this, 'FORBIDDEN' ) ;
		$theGroupID = $this->getEntityID( $aGroupID, 'group_id', false ) ;
		if( ! empty($theGroupID) )
			return $this->getAllInGroup( $theGroupID ) ; // instead of "get all"
		$dbAuth = $this->getProp('Auth');
		try {
			$theRowSet = $dbAuth->getAccountsToDisplay($this->scene);
			$this->scene->results = APIResponse::resultsWithData(
					$this->getAuthAccountSet($theRowSet)
			);
		}
		catch (Exception $x)
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}

	/**
	 * Allows a site administrator to activate an existing account on behalf of
	 * another user, device, or agent.
	 * @param integer $aAccountID the account ID (if null, fetch from POST var
	 *  'account_id' instead)
	 * @throws BrokenLeg
	 *  * 'MISSING_ARGUMENT' - if the account ID is not specified
	 *  * 'ENTITY_NOT_FOUND' - if no account with that ID exists
	 * @since BitsTheater 3.6
	 */
	public function ajajActivate( $aAccountID=null )
	{
		$theAccountID = $this->getEntityID( $aAccountID, 'account_id' ) ;
		$this->setActiveStatus( $theAccountID, true ) ;
	}

	/**
	 * Allows a site administrator to deactivate the existing account of another
	 * user, device, or agent.
	 * @param integer $aAccountID the account ID (if null, fetch from POST var
	 *  'account_id' instead)
	 * @throws BrokenLeg
	 *  * 'MISSING_ARGUMENT' - if the account ID is not specified
	 *  * 'ENTITY_NOT_FOUND' - if no account with that ID exists
	 * @since BitsTheater 3.6
	 */
	public function ajajDeactivate( $aAccountID=null )
	{
		$theAccountID = $this->getEntityID( $aAccountID, 'account_id' ) ;
		$this->setActiveStatus( $theAccountID, false ) ;
		//TODO also remove tokens
	}

	/**
	 * Activates or deactivates an account.
	 * Consumed by ajajActivate() and ajajDeactivate().
	 * @param integer $aAccountID the account ID
	 * @param boolean $bActive set status to active (true) or inactive (false)
	 * @since BitsTheater 3.6
	 */
	protected function setActiveStatus( $aAccountID, $bActive )
	{
		if( ! $this->isAllowed( 'accounts', 'activate' ) )
			throw BrokenLeg::toss( $this, 'FORBIDDEN' ) ;
		$dbAuth = $this->getProp( 'Auth' ) ;
		$theAuth = null ;
		try { $theAuth = $dbAuth->getAuthByAccountId($aAccountID) ; }
		catch( DbException $dbx )
		{ throw BrokenLeg::toss( $this, 'DB_EXCEPTION', $dbx->getMessage() ) ; }
		if( empty($theAuth) )
			throw BrokenLeg::toss( $this, 'ENTITY_NOT_FOUND', $aAccountID ) ;
		try { $dbAuth->setInvitation( $aAccountID, $bActive ) ; }
		catch( DbException $dbx )
		{ throw BrokenLeg::toss( $this, 'DB_EXCEPTION', $dbx->getMessage() ) ; }

		$theResponse = new \stdClass() ;
		$theResponse->account_id = $aAccountID ;
		$theResponse->is_active = $bActive ;
		$this->scene->results = APIResponse::resultsWithData($theResponse) ;
	}

	/**
	 * (Override) As ABitsAccount::ajajDelete(), but also deletes data from the
	 * auth tables.
	 * @since BitsTheater 3.6
	 */
	public function ajajDelete( $aAccountID=null )
	{
		$theAccountID = $this->getEntityID( $aAccountID, 'account_id' ) ;
		$this->checkCanDeleteAccount( $theAccountID ) ;
		$this->deletePermissionData( $theAccountID ) ;
		$this->deleteAuthData( $theAccountID ) ;           // This the override.
		$this->deleteAccountData( $theAccountID ) ;  // Happens only on success.
		$this->scene->results = APIResponse::noContentResponse() ;
	}

	/**
	 * Deletes the permission data associated with an account ID.
	 * Consumed by ajajDelete().
	 * @param integer $aAccountID the account ID
	 * @throws BrokenLeg
	 * @since BitsTheater 3.8
	 */
	protected function deletePermissionData( $aAccountID )
	{
		$this->debugLog( __METHOD__ . ' - Deleting auth group map for account [' . $aAccountID . ']...' ) ;
		$dbAuthGroups = $this->getProp( 'AuthGroups' ) ;
		$theGroups = $dbAuthGroups->getAcctGroups( $aAccountID );
		if ( !empty($theGroups) )
		{
			try {
				foreach ($theGroups as $theGroupID)
					$dbAuthGroups->delAcctMap( $theGroupID, $aAccountID ) ;
			}
			catch( Exception $x )
			{ throw BrokenLeg::tossException( $this, $x ) ; }
		}
		return $this ;
	}

	/**
	 * Deletes the auth data associated with an account ID.
	 * Consumed by ajajDelete().
	 * @param integer $aAccountID the account ID
	 * @return AuthBasicAccount $this
	 * @throws BrokenLeg
	 * @since BitsTheater 3.6
	 */
	protected function deleteAuthData( $aAccountID )
	{
		$this->debugLog( __METHOD__ . ' - Deleting auth for account [' . $aAccountID . ']...' ) ;
		$dbAuth = $this->getProp( 'Auth' ) ;
		try { $dbAuth->deleteFor( $aAccountID ) ; }
		catch( Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
		return $this ;
	}

	/**
	 * Map a mobile device with an account to auto-login once configured.
	 * @return Returns NO CONTENT.
	 * @since BitsTheater 3.6.1
	 */
	public function ajajMapMobileToAccount() {
		$v =& $this->scene;
		$this->viewToRender('results_as_json');
		//what device are we trying to map an account to?
		if (empty($v->device_id))
			throw BrokenLeg::toss( $this, 'MISSING_VALUE', 'device_id' ) ;
		$dbAuth = $this->getProp('Auth');
		$theAuthRow = null;
		//we need either an account_id or an auth_id
		//  get the other one using whichever we were given
		if (!empty($v->account_id) && empty($v->auth_id)) {
			$theAuthRow = $dbAuth->getAuthByAccountId($v->account_id);
		}
		if (empty($v->account_id) && !empty($v->auth_id)) {
			$theAuthRow = $dbAuth->getAuthByAuthId($v->auth_id);
		}
		if (!empty($theAuthRow)) try {
			//once we have all 3 peices, create our one-time mapping token
			$dbAuth->generateAutoLoginForMobileDevice($theAuthRow['auth_id'],
					$theAuthRow['account_id'], $v->device_id
			);
			$v->results = APIResponse::noContentResponse() ;
		} catch (Exception $x) {
			throw BrokenLeg::tossException( $this, $x ) ;
		}
		else
			throw BrokenLeg::toss( $this, 'MISSING_VALUE', "'account_id' or 'auth_id'" ) ;
	}
	
	/**
	 * Before a pairing of device to auth account is attempted, what should occur?
	 * Default behavior is to delete stale tokens for enhanced security.
	 * @param string $aDeviceTokenFilter - the particular token prefix used.
	 */
	protected function beforeRequestMobileAuthAccount($aDeviceTokenFilter)
	{
		$dbAuth = $this->getProp('Auth');
		//remove any stale device tokens
		$dbAuth->removeStaleTokens($aDeviceTokenFilter, '3 MONTH');
		$this->returnProp($dbAuth);
	}
	
	/**
	 * Once a pairing of device to auth account succeeds, then what? Default behavior is to
	 * delete the token for enhanced security.
	 * @param string $aDeviceTokenFilter - the particular token prefix used.
	 */
	protected function afterSuccessfulRequestMobileAuthAccount($aDeviceTokenFilter)
	{
		$dbAuth = $this->getProp('Auth');
		//remove any lingering device tokens unless one was JUST created
		$dbAuth->removeStaleTokens($aDeviceTokenFilter, '1 SECOND');
		$this->returnProp($dbAuth);
	}
	
	/**
	 * Mobile devices might ask the server for what account should be used
	 * for authenticating mobile devices (which may be rooted).
	 * @return Returns JSON encoded array[account_name, auth_id, user_token, auth_token]
	 */
	public function requestMobileAuthAccount() {
		$v =& $this->scene;
		$this->viewToRender('results_as_json');
		
		//Auth Header IS NOT SET because we do not have an account, yet
		//  Most of the Auth Header data is in a POST param
		if (!empty($v->auth_header_data))
		{
			$theHttpAuthHeader = HttpAuthHeader::fromHttpAuthHeader(
					$this->getDirector(), $v->auth_header_data
			);
			$dbAuth = $this->getProp('Auth');
			$theDeviceTokenFilter = $dbAuth::TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT . ':';
			$theDeviceTokenFilter .= $theHttpAuthHeader->device_id . ':%';
			$this->beforeRequestMobileAuthAccount($theDeviceTokenFilter);
			
			$theTokenRows = $dbAuth->getAuthTokens(null, null, $theDeviceTokenFilter, true);
			//$this->debugLog(__METHOD__.' rows='.$this->debugStr($theTokenRows));
			if (!empty($theTokenRows)) {
				//just use the first one found
				$theTokenRow = $theTokenRows[0];
				$theAccountInfoCache = $dbAuth->getAccountInfoCache(
						$this->getProp('Accounts'), $theTokenRow['account_id']
				);
				$v->results = $dbAuth->requestMobileAuthAfterPwLogin(
						$theAccountInfoCache, $theHttpAuthHeader
				);
				//$this->debugLog(__METHOD__.' results='.$this->debugStr($v->results));
				$this->afterSuccessfulRequestMobileAuthAccount($theDeviceTokenFilter);
			}
		}
	}
	
	/**
	 * Render a page for viewing a table of accounts.
	 * @return NULL|string Return a re-direction URL, if any.
	 */
	public function viewAll()
	{
		if (!$this->isAllowed('accounts','view'))
			return $this->getHomePage();
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('admin');
		
		$dbAuth = $this->getProp('Auth');
		$theRowSet = $dbAuth->getAccountsToDisplay($v);
		$v->results = $this->getAuthAccountSet($theRowSet);
		$v->auth_groups = Arrays::array_column_as_key($dbAuth->getGroupList(), 'group_id');
		
		//display these fields in table
		$v->table_cols = array();
		if ( $this->isAllowed('accounts','modify') )
			$v->table_cols['edit_button'] = array( 'fieldname' => 'edit_button', 'style' => 'width:5ch' );
		$v->table_cols = array_merge($v->table_cols, array(
				'account_id'   => array( 'fieldname' => 'account_id',   'style' => 'width:4ch' ),
				'account_name' => array( 'fieldname' => 'account_name', 'style' => 'width:32ch' ),
				//'external_id'  => array( 'fieldname' => 'external_id',  'style' => 'width:4ch' ),
				//'auth_id'      => array( 'fieldname' => 'auth_id',      'style' => 'width:32ch' ),
				'email'        => array( 'fieldname' => 'email',        'style' => 'width:40ch' ),
				//everified_ts'  => array( 'fieldname' => 'verified_ts',  'style' => 'width:32ch' ),
				'is_active'    => array( 'fieldname' => 'is_active',    'style' => 'width:5ch' ),
				'created_by'   => array( 'fieldname' => 'created_by',   'style' => 'width:30ch' ),
				'created_ts'   => array( 'fieldname' => 'created_ts',   'style' => 'width:32ch' ),
				'updated_by'   => array( 'fieldname' => 'updated_by',   'style' => 'width:30ch' ),
				'updated_ts'   => array( 'fieldname' => 'updated_ts',   'style' => 'width:32ch' ),
		));
	}

}//end class

}//end namespace
