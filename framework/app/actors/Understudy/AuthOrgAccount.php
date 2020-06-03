<?php
/*
 * Copyright (C) 2017 Blackmoon Info Tech Services
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
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\APIResponse;
use BitsTheater\costumes\AuthPasswordReset;
use BitsTheater\costumes\AuthAccount;
use BitsTheater\costumes\AuthAccountSet;
use BitsTheater\costumes\AuthGroupList;
use BitsTheater\costumes\AuthOrgSet;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\WornForAuditFields;
use BitsTheater\costumes\CursorCloset\AuthOrg ;
use BitsTheater\models\AccountPrefs as PrefsDB ;
use BitsTheater\models\Auth as AuthDB; /* @var $dbAuth AuthDB */
use BitsTheater\models\AuthGroups as AuthGroupsDB; /* @var $dbAuthGroups AuthGroupsDB */
use BitsTheater\outtakes\AccountAdminException;
use BitsTheater\outtakes\PasswordResetException;
use BitsTheater\scenes\Account as MyScene;
use com\blackmoonit\Arrays;
use com\blackmoonit\MailUtils;
use com\blackmoonit\MailUtilsException;
use com\blackmoonit\Strings;
use Exception;
{//namespace begin

/**
 * Endpoints when using AuthOrg model for auth accounts.
 * @since BitsTheater 4.0.0
 */
class AuthOrgAccount extends BaseActor
{
	use WornForAuditFields;
	
	/** @var string The default URL method to call if not specified. */
	const DEFAULT_ACTION = 'register';

	/**
	 * The model that we expect to use for access to account data.
	 * @var string
	 */
	const CANONICAL_MODEL = 'Auth' ;

	/**
	 * Token to use for "ping"; override in descendant.
	 * @see requestMobileAuth()
	 * @var string
	 */
	const MAGIC_PING_TOKEN = 'pInG';

	/**
	 * {@inheritDoc}
	 * @return MyScene Returns a newly created scene descendant.
	 * @see \BitsTheater\Actor::createMyScene()
	 */
	protected function createMyScene($anAction)
	{ return new MyScene($this, $anAction); }

	/** @return MyScene Returns my scene object. */
	public function getMyScene()
	{ return $this->scene; }

	/**
	 * @return AuthDB Returns the database model reference.
	 */
	protected function getMyModel()
	{ return $this->getProp(AuthDB::MODEL_NAME); }

	/**
	 * @return AuthGroupsDB Returns the database model reference.
	 */
	protected function getAuthGroupsModel()
	{ return $this->getProp(AuthGroupsDB::MODEL_NAME); }
	
	/**
	 * Fetches an instance of the model usually accessed by this actor, granting
	 * access to account data.
	 * @return AuthDB - an instance of the model
	 * @throws BrokenLeg - 'DB_CONNECTION_FAILED' if the model can't connect to
	 *  the database
	 */
	protected function getCanonicalModel()
	{ return $this->getProp( self::CANONICAL_MODEL ) ; }
	
	/**
	 * HTML page to view the currently logged in account info.
	 * {@inheritDoc}
	 * @see \BitsTheater\actors\Understudy\ABitsAccount::view()
	 */
	public function view( $aAcctId=null )
	{
		$theImmediateRedirect = parent::view($aAcctId);
		if ( empty($theImmediateRedirect) ) {
			$v = $this->getMyScene();
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
	
	/**
	 * Register a new user account.
	 * @param string $aAcctName - name of the account (username).
	 * @param string $aAcctPw - password to use.
	 * @param string $aAcctEmail - email to use for the account.
	 * @param string $aRegCode - registration code to match default group.
	 * @param boolean $bAllowUnregGroupToAutoRegister - only register if reg code is valid.
	 * @return string Return one of the REGISTRATION_REG_CODE_* constants.
	 */
	protected function registerNewAccount($aAcctName, $aAcctPw, $aAcctEmail, $aRegCode,
			$bAllowUnregGroupToAutoRegister=true)
	{
		//shortcut variable $v also in scope in our view php file.
		$v = $this->getMyScene();
		if (!empty($aAcctName) && !empty($aAcctPw) && !empty($aAcctEmail) && !empty($aRegCode)) {
			$dbAuth = $this->getProp('Auth');
			$theCanRegisterResult = $dbAuth->canRegister($aAcctName, $aAcctEmail);
			if ( $theCanRegisterResult==$dbAuth::REGISTRATION_SUCCESS )
			{
				//see if there is a registration code that maps to a particular group
				$dbAuthGroups = $this->getProp('AuthGroups');
				$theDefaultGroup = $dbAuthGroups->findGroupIdByRegCode($aRegCode);
				if ( $theDefaultGroup != $dbAuthGroups::UNREG_GROUP_ID )
				{ $theVerifiedTs = 'now'; }
				else if ( !$bAllowUnregGroupToAutoRegister )
				{ return $dbAuth::REGISTRATION_REG_CODE_FAIL; }
				else
				{ $theVerifiedTs = null; }
				$this->validatePswdChangeInput($aAcctPw) ; // Throws exception if rejected.
				//TODO rework add/register
				//now that we have a proper group and account name, lets save stuff!
				$theNewAcct = array(
						$v->getUsernameKey() => $aAcctName,
						'email' => $aAcctEmail,
						$v->getPwInputKey() => $aAcctPw,
						'verified_timestamp' => $theVerifiedTs,
				);
				// Also make sure we choose the correct org for this reg code.
				$theGroup = $dbAuthGroups->getGroup($theDefaultGroup) ;
				$theOrgToAdd = ( empty($theGroup['org_id']) ? null : $theGroup['org_id'] ) ;
				if( !empty($theOrgToAdd) )
					$theNewAcct['org_ids'] = array( $theOrgToAdd ) ;
				$dbAuth->registerAccount($theNewAcct, $theDefaultGroup);
				return $dbAuth::REGISTRATION_SUCCESS;
			} else {
				return $theCanRegisterResult;
			}
		}
	}
	
	/**
	 * Process the registration form data and honor the post_key so we protect against bot spam.
	 * @return string Return the URL to redirect to, if any.
	 */
	protected function processRegistrationForm()
	{
		//shortcut variable $v also in scope in our view php file.
		$v = $this->getMyScene();
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
				if ( $theRegResult===$dbAuth::REGISTRATION_EMAIL_TAKEN ) {
					$v->addUserMsg($this->getRes('account', 'msg_acctexists',
							$this->getRes('account', 'label_email')), $v::USER_MSG_ERROR
					);
				} else if ( $theRegResult===$dbAuth::REGISTRATION_NAME_TAKEN ) {
					$v->addUserMsg($this->getRes('account', 'msg_acctexists',
							$this->getRes('account', 'label_name')), $v::USER_MSG_ERROR
					);
				} else if ( $theRegResult===$dbAuth::REGISTRATION_SUCCESS ) {
					//registration succeeded, save the account in session cache
					$this->getDirector()->setMyAccountInfo(
							$dbAuth->getAccountInfoCacheByEmail($v->email)
					);
					//since we are "logged in" via a non-standard mechanism, create an anti-CSRF token
					$dbAuth->setCsrfTokenCookie();
					return ( !empty($v->redirect) ) ? $v->redirect : $this->getHomePage();
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
	
	public function register($aTask='data-entry')
	{
		//shortcut variable $v also in scope in our view php file.
		$v = $this->getMyScene();
		if ($aTask==='new') {
			return $this->processRegistrationForm();
		} else {
			//indicate what top menu we are currently in
			$this->setCurrentMenuKey('account');
			/* in case its needed...
			//make sure user/pw reg fields will not interfere with any login user/pw field in header
			$userKey = $v->getUsernameKey().'_reg';
			$pwKey = $v->getPwInputKey().'_reg';
			*/
			//post_key needed to actually register (prevent mass bot-fueled registries)
			$this->director['post_key'] = Strings::createUUID().Strings::createUUID();
			$this->director['post_key_ts'] = time()+10; //you can only register 10 seconds after the page loads
			$v->post_key = $this->director['post_key'];
			
			$v->form_name = 'register_user'; //used to prevent login area from displaying
			
			$v->action_url_register = $this->getMyUrl('register/new');
			$dbAccounts = $this->getProp('Accounts');
			if ($dbAccounts->isEmpty()) {
				$v->redirect = $this->getSiteUrl('/rights');
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
	public function registerUsingRegCode()
	{
		//shortcut variable $v also in scope in our view php file.
		$v = $this->getMyScene();
		$this->viewToRender('results_as_json');
		
		//Check session registration cooldown timestamp to prevent mass bot-fueled registries
		$bPostKeyOldEnough = true;
		if ( !empty($this->director['register_cooldown_ts']) ) {
			//post time is older than cooldown period
			$bPostKeyOldEnough = ( $this->director['register_cooldown_ts'] < time() );
		}
		$this->director['register_cooldown_ts'] = time()+10; //10 second cooldown period
		
		//make sure user/pw reg fields will not interfere with any login user/pw field in header
		$userKey = $v->getUsernameKey().'_reg';
		$pwKey = $v->getPwInputKey().'_reg';
		$bPwOk = ($v->$pwKey===$v->password_confirm);
		$bRegCodeOk = ( !empty($v->reg_code) );
		if ( $bPwOk && $bRegCodeOk && $bPostKeyOldEnough ) {
			$dbAuth = $this->getProp('Auth');
			$theRegResult = $this->registerNewAccount($v->$userKey, $v->$pwKey,
					$v->email, $v->reg_code, false
			);
			//$this->debugLog(__METHOD__.' ['.$v->$userKey.'] code='.$theRegResult); //DEBUG
			switch ($theRegResult) {
				case $dbAuth::REGISTRATION_SUCCESS :
					//registration succeeded, now pretend to login with it
					$v->{$dbAuth::KEY_userinfo} = $v->{$userKey};
					$v->{$dbAuth::KEY_pwinput} = $v->{$pwKey};
					$dbAuth->checkTicket($v);
					//we may wish to return the newly created account data
					return $this->ajajGetAccountInfo();
				case $dbAuth::REGISTRATION_REG_CODE_FAIL :
					throw BrokenLeg::toss($this, BrokenLeg::ACT_FORBIDDEN)->putExtra(
							'reason', $this->getRes('account', 'msg_reg_code_mismatch')
					);
				case $dbAuth::REGISTRATION_EMAIL_TAKEN :
					throw BrokenLeg::pratfallRes($this, 'EMAIL_EXISTS', 400,
							'account/msg_acctexists/'.$this->getRes('account/label_email')
					);
				case $dbAuth::REGISTRATION_NAME_TAKEN :
					throw BrokenLeg::pratfallRes($this, 'USERNAME_EXISTS', 400,
							'account/msg_acctexists/'.$this->getRes('account/label_name')
					);
				case $dbAuth::REGISTRATION_CAP_EXCEEDED :
					throw BrokenLeg::toss($this, BrokenLeg::ACT_TOO_MANY_REQUESTS);
				default :
					throw BrokenLeg::toss($this, BrokenLeg::ACT_DEFAULT);
			}//switch
		} else {
			$theX = BrokenLeg::toss($this, BrokenLeg::ACT_NOT_AUTHENTICATED);
			if ( !$bPwOk )
			{ $theX->putExtra('reason', $this->getRes('account', 'msg_pw_nomatch')); }
			else if ( !$bRegCodeOk )
			{ $theX->putExtra('reason', $this->getRes('account', 'msg_reg_code_mismatch')); }
			else if ( !$bPostKeyOldEnough )
			{ $theX->putExtra('reason', $this->getRes('account', 'msg_reg_too_fast')); }
			throw $theX;
		}
	}

	/**
	 * Process account form input; post_key check required.
	 * CSRF protection not necessary since password entry is required to change
	 * anything.
	 * @return string Returns the redirect URL, if defined.
	 */
	public function modify()
	{
		$v = $this->getMyScene();
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
						$dbAuth->updateEmail($theAcctId, $theNewEmail);
					}
				}
				$pwKeyNew = $this->scene->getPwInputKey().'_new';
				if (!empty($this->scene->$pwKeyNew) && $this->scene->$pwKeyNew===$this->scene->password_confirm)
				{ // Verify that the input is acceptable, and if so, use it.
					$this->validatePswdChangeInput($this->scene->$pwKeyNew) ;
					$dbAuth->updatePassword($theAcctId, $this->scene->$pwKeyNew);
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
	 * Endpoint will return the standard API response object with User info.
	 * @return string Returns the redirect URL, if defined.
	 */
	public function ajajModify()
	{
		//do not use the form vars being used for the modify() endpoint, treat like login().
		$v = $this->getMyScene();
		if ($this->isGuest())
			throw BrokenLeg::toss($this, BrokenLeg::ACT_NOT_AUTHENTICATED);
		
		$dbAccounts = $this->getProp('Accounts');
		$dbAuth = $this->getProp('Auth');
		//get username and convert to account_id, if possible
		$theAcctName = $v->account_name;
		$theAcctInfo = $dbAccounts->getByName($theAcctName);
		if (!empty($theAcctInfo))
			$theAcctId = $theAcctInfo['account_id'];
		else
			throw BrokenLeg::toss($this, BrokenLeg::ACT_FORBIDDEN);
		
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
						$dbAuth->updateEmail($theAcctId, $theNewEmail);
					}
				}
				
				//update PASSWORD
				$pwKeyNew = $v->getPwInputKey().'_new';
				$pwKeyConfirm = $v->getPwInputKey().'_confirm';
				if (!empty($v->$pwKeyNew) && ($v->$pwKeyNew===$v->$pwKeyConfirm))
				{ // Verify that the input is acceptable, and if so, use it.
					$this->validatePswdChangeInput( $v->$pwKeyNew ) ;
					$dbAuth->updatePassword($theAcctId, $v->$pwKeyNew);
				}

				//all modifications went ok, get the account info and return it
				$theChangedAccountInfo = $dbAuth->getAccountInfoCache($dbAuth, $theAcctId);
				if ($theAcctId==$this->getDirector()->account_info->account_id) {
					//if changing my own account, update my account cache
					$this->getDirector()->setMyAccountInfo( $theChangedAccountInfo );
				}
				$theChangedAccountInfo->account_id += 0; //ensure what is returned is not a string
				$v->results = APIResponse::resultsWithData($theChangedAccountInfo);
			} catch (Exception $e) {
				throw BrokenLeg::tossException($this, $e);
			}
		} else
			throw BrokenLeg::toss($this, BrokenLeg::ACT_FORBIDDEN);
	}

	/**
	 * Called by requestPasswordReset() when the action is "proc" (process a request).
	 */
	protected function processPasswordResetRequest()
	{
		$v = $this->getMyScene();
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
		$v = $this->getMyScene();
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
		$v = $this->getMyScene();
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
     * Register a user via mobile app rather than on web page.<br>
	 * POST vars expected: name, salt, email, code, fingerprints<br>
     * Returns JSON encoded array[code, user_token, auth_token]<br>
	 * @return string Returns the redirect URL, if defined.
	 */
	public function registerViaMobile()
	{
		$v = $this->getMyScene();
		$this->viewToRender('results_as_json');
		$dbAuth = $this->getCanonicalModel();
		//$this->debugLog('regargs='.$v->name.', '.$v->salt.', '.$v->email.', '.$v->code);
		$theRegResult = $this->registerNewAccount($v->name, $v->salt, $v->email, $v->code, false);
		if ( $theRegResult===$dbAuth::REGISTRATION_SUCCESS ) {
			//pretend we are just now logging in with the newly registered account
			$v->{$dbAuth::KEY_userinfo} = $v->name;
			$v->{$dbAuth::KEY_pwinput} = $v->salt;
			$dbAuth->checkTicket($v);
			$theMobileRow = $this->getMyMobileRow();
			if ( !empty($theMobileRow) ) {
				$v->results = array(
						'code' => $theRegResult,
						'auth_id' => $theMobileRow['auth_id'],
						'user_token' => $theMobileRow['account_token'],
				);
				$this->logStuff($v->name,
						' successfully registered an account via mobile: [',
						$theMobileRow['account_token'], ']'
				);
				return;
			}
		}
		if ( !isset($theRegResult) )
		{ $theRegResult = $dbAuth::REGISTRATION_UNKNOWN_ERROR; }
		$v->results = array(
				'code' => $theRegResult,
		);
		$this->logStuff($v->name,
				' unsuccessfully tried to register an account via mobile. [',
				$theRegResult, ']'
		);
	}
	
	/**
	 * Get my mobile data, if known.
	 * @return array Returns the mobile data as an array.
	 */
	protected function getMyMobileRow()
	{ return $this->getCanonicalModel()->getMyMobileRow(); }
	
	/**
	 * Mobile auth is a bit more involved than Basic HTTP auth, use this mechanism
	 * for authenticating mobile devices (which may be rooted).<br>
     * Returns JSON encoded array[account_name, user_token, auth_token, api_version_seq]
	 * @param string $aPing - (optional) ping string which could be used to pong a response.
	 * @return string Returns the redirect URL, if defined.
	 */
	public function requestMobileAuth($aPing=null)
	{
		//shortcut variable $v also in scope in our view php file.
		$v = $this->getMyScene();
		$this->viewToRender('results_as_json');
		if ( empty($aPing) ) {
			//do not just check "isGuest()" as mobile may be part of an org with no rights yet
			$myAuth = $this->getDirector()->getMyAccountInfo();
			if ( empty($myAuth) ) {
				throw BrokenLeg::toss($this, BrokenLeg::ACT_NOT_AUTHENTICATED);
			}
			$theMobileRow = $this->getMyMobileRow();
			if ( !empty($theMobileRow) ) {
				$theMobileRow = (object)$theMobileRow;
				$dbAuth = $this->getCanonicalModel();
				$theAuthToken = $dbAuth->generateAuthTokenForMobile(
						$myAuth->account_id, $myAuth->auth_id, $theMobileRow->mobile_id
				);
				$v->results = array(
						'account_name' => $myAuth->account_name,
						'auth_id' => $myAuth->auth_id,
						'user_token' => $theMobileRow->account_token,
						'auth_token' => $theAuthToken,
						'api_version_seq' => $this->getRes('website/api_version_seq'),
				);
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
		$v = $this->getMyScene();
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
	 * * 'PERMISSION_DENIED' - if user doesn't have accounts/create and accounts/view access.
	 * * 'MISSING_ARGUMENT' - if the account name is not specified.
	 * * 'UNIQUE_FIELD_ALREADY_EXISTS' - if unique field is specified to update,
	 *  but already exists in the system.
	 * * 'DB_EXCEPTION' - If another db exception occurs.
	 * @return string Returns the redirect URL, if defined.
	 * @since BitsTheater 3.6
	 */
	public function ajajCreate()
	{
		// Check Permissions.
		$this->checkAllowed( 'accounts', 'create' );
		
		// Retrieve our passed-in values.
		$v = $this->getMyScene();
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
		$this->validatePswdChangeInput( $aPassword ) ;
		if ( empty ( $aEmail ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', "email" );

		// Reference respective models required.
		$dbAuth = $this->getProp('Auth');
		$dbAuthGroups = $this->getProp('AuthGroups');

		// Parse default group affiliation for this new account.
		$accountGroup = null;
		if ( empty($aGroupId) ) {
			if ( !empty($aRegCode) )
			{
				//see if there is a registration code that maps to a particular group
				$dbAuthGroups = $this->getProp('AuthGroups');
				$theDefaultGroup = $dbAuthGroups->findGroupIdByRegCode($aRegCode);
				if ( $theDefaultGroup != $dbAuthGroups::UNREG_GROUP_ID )
				{ $accountGroup = $theDefaultGroup; }
			}
		} else {
			// New account will have the specified group id as its default group.
			if ( is_array($aGroupId) ) {
				$accountGroup = array();
				foreach ($aGroupId as $anID) {
					$theID = trim($anID);
					//if not already in group, add to group list
					if ( !in_array($theID, $accountGroup, true) )
					{ $accountGroup[] = $theID; }
				}
			} else {
				$accountGroup = trim($aGroupId);
			}
		}

		// Verify new account can be registered.
		switch ( $dbAuth->canRegister($aName, $aEmail) ) {
			case $dbAuth::REGISTRATION_NAME_TAKEN:
				throw AccountAdminException::toss( $this,
						'UNIQUE_FIELD_ALREADY_EXISTS', $aName ) ;
			case $dbAuth::REGISTRATION_EMAIL_TAKEN:
				throw AccountAdminException::toss( $this,
						'UNIQUE_FIELD_ALREADY_EXISTS', $aEmail ) ;
			//case $dbAuth::REGISTRATION_SUCCESS:
		}
		// Aggregate our account data for registration.
		$newAccountData = array(
				$v->getUsernameKey() => $aName,
				'email' => $aEmail,
				$v->getPwInputKey() => $aPassword,
				'verified_ts' => 'now', //admin creating account, assume valid email.
		);
		if (isset($v->account_is_active))
			$newAccountData['account_is_active'] = $v->account_is_active;
		
		//AuthOrg info
		$theOrgList = (isset($v->account_org_ids)) ? $v->account_org_ids : null;
		//limit org list to only ones I belong to, unless I have permission
		if ( !$this->isAllowed('auth','assign-to-any-org') ) {
			$myOrgList = $dbAuth->getOrgsForAuthCursor(
					$this->getDirector()->account_info->auth_id
			)->fetchAll();
			if ( !empty($myOrgList) ) {
				$theOrgList = array_intersect($theOrgList, $myOrgList);
			}
		}
		if ( isset($theOrgList) )
			$newAccountData['org_ids'] = $theOrgList;
		
		// Register account with affliated group.
		try {
			$registrationResult = $dbAuth->registerAccount(
					$newAccountData, $accountGroup
			);
		}
		catch( \Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ); }
		$theResult = $this->getAuthAccount($registrationResult['auth_id']);
		$this->setApiResults($theResult->exportData());
	}

	/**
	 * Allows a site administrator to update the details of an existing account
	 * on behalf of another user, device, or agent.
	 * POST params<ul>
	 * <li><b>auth_id</b> - (required unless account_id is specified or param defined)
	 *   The ID of the account to update.
	 * </li>
	 * <li><b>account_id</b> - (required unless auth_id is specified or param defined)
	 *   The ID of the account to update.
	 * </li>
	 * <li><b>account_name</b> - (optional)
	 *   New name. Leading / ending whitespace will be trimmed.
	 * </li>
	 * <li><b>account_password</b> - (optional)
	 *   New password to the account. Leading / ending whitespace will be trimmed.
	 * </li>
	 * <li><b>email</b> - (optional)
	 *   New email to the account. Leading / ending whitespace will be trimmed.
	 * </li>
	 * <li><b>account_group_ids</b> - (optional)
	 *   Array of authgroup ids to map to the account. All previous authgroup
	 *   affiliations for this account will be removed and replaced with this
	 *   list of group affiliations.
	 * </li>
	 * </ul>
	 * @param integer|string $aID - (optional) the auth or account ID
	 * @throws AccountAdminException<ul>
	 * <li>MISSING_ARGUMENT - if the account/auth ID is not specified</li>
	 * <li>ENTITY_NOT_FOUND - if no account with that ID exists</li>
	 * <li>PERMISSION_DENIED - if user lacks permission to modify accounts.</li>
	 * <li>UNIQUE_FIELD_ALREADY_EXISTS - if a unique field is specified to update,
	 *   but already exists in the system.</li>
	 * </ul>
	 * @return string Returns the redirect URL, if defined.
	 * @since BitsTheater 3.6
	 */
	public function ajajUpdate( $aID = null )
	{
		// Check Permissions.
		$this->checkAllowed( 'accounts', 'modify' );
		
		// Reference respective models required.
		$dbAuth = $this->getProp('Auth');
		$dbAuthGroups = $this->getProp('AuthGroups');
		
		// Retrieve our passed-in values.
		$v = $this->getMyScene();
		$theID = trim($this->getRequestData($aID, 'account_id', false));
		$theID = trim($this->getRequestData($theID, 'auth_id', false));
		$dbAuth->checkIsNotEmpty('auth_id, account_id, or URL/id', $theID);
		$aName 		= trim ( $v->account_name );
		$aPassword 	= trim ( $v->account_password );
		$aEmail 	= trim ( $v->email );
		$aIsActive  = (isset($v->account_is_active)) ? $v->account_is_active : null;
		$aGroupIds  = $v->account_group_ids;

		$theAuthAccount = $this->getAuthAccount($theID);
		if ( !empty($theAuthAccount) ) try {
			// Determine what values are different than existing values.
			if ( !empty($aName) && ($aName !== $theAuthAccount->account_name) )
				$updatedName = $aName;
			if ( !empty($aPassword) && $dbAuth->cudo($theAuthAccount->account_id, $aPassword) )
				$updatedPassword = $aPassword;
			if ( !empty($aEmail) && $aEmail !== $theAuthAccount->email )
				$updatedEmail = $aEmail;
			if ( isset( $aIsActive ) && $aIsActive != $theAuthAccount->is_active )
				$updatedIsActive = $aIsActive;

			// Update email, if applicable.
			if ( !empty ( $updatedEmail )) {
				// Verify new unique email update doesn't already exist in system.
				if ( $dbAuth->getAuthByEmail( $updatedEmail ) ) {
					throw AccountAdminException::toss( $this,
						'UNIQUE_FIELD_ALREADY_EXISTS', $updatedEmail
					);
				}
				else {
					$dbAuth->updateEmail($theAuthAccount->account_id, $updatedEmail);
				}
			}

			// Update name, if applicable.
			if ( !empty ( $updatedName )) {
				// Verify new unique name update doesn't already exist in system.
				if ( $dbAuth->getByName( $updatedName ) ) {
					throw AccountAdminException::toss( $this,
							'UNIQUE_FIELD_ALREADY_EXISTS', $updatedName
					);
				}
				else {
					$dbAuth->updateName($theAuthAccount->account_id, $updatedName);
				}
			}

			// Update password, if applicable.
			if ( !empty ( $updatedPassword )) {
				$dbAuth->updatePassword($theAuthAccount->account_id, $updatedPassword);
			}

			//update is_active, if applicable
			if ( isset($updatedIsActive) ) {
				$dbAuth->setAuthIsActive($updatedIsActive, $theAuthAccount->auth_id);
			}

			// Update account group, if applicable.
			if ( isset ( $aGroupIds )) {
				// First we want to remove existing mappings of group ids for this account.
				foreach ($theAuthAccount->groups as $thisGroupId) {
					$dbAuthGroups->delMap(
							$thisGroupId, $theAuthAccount->auth_id
					);
				}
				// Now insert mapping of account with updated group id values.
				foreach ($aGroupIds as $thisNewGroupId) {
					// Add mapping.
					$dbAuthGroups->addMap(
							$thisNewGroupId, $theAuthAccount->auth_id
					);
				}
			}
			
			// update AuthOrg info, if different
			$theOrgList = (isset($v->account_org_ids)) ? $v->account_org_ids : array();
			$theRemovedOrgs = array_diff($theAuthAccount->org_ids, $theOrgList);
			$theAddedOrgs = array_diff($theOrgList, $theAuthAccount->org_ids);
			//removed orgs can just be safely removed
			if ( !empty($theRemovedOrgs) ) {
				foreach( $theRemovedOrgs as $theRemovedOrg ) {
					$theRolesToRemove = $dbAuthGroups->getAcctGroupsForOrg($theAuthAccount->auth_id, $theRemovedOrg);
					foreach( $theRolesToRemove as $theRemovedRoleID ) {
						$dbAuthGroups->delMap($theRemovedRoleID, $theAuthAccount->auth_id);
					}
				}
				$dbAuth->delOrgsForAuth($theAuthAccount->auth_id, $theRemovedOrgs);
			}
			if ( !empty($theAddedOrgs) ) {
				//limit org list to only ones I can access, unless I have permission
				if ( !$this->isAllowed('auth','assign-to-any-org') ) {
					$theLimitedOrgSet = $this->getOrgs();
					if ( !empty($theLimitedOrgSet) ) {
						$theLimitedOrgs = array();
						while ( ($theOrg = $theLimitedOrgSet->fetch()) !== false ) {
							$theLimitedOrgs[] = $theOrg->org_id;
						}
						$theOrgsToAdd = array_intersect($theAddedOrgs, $theLimitedOrgs);
						if ( !empty($theOrgsToAdd) ) {
							$dbAuth->addOrgsToAuth($theAuthAccount->auth_id, $theOrgsToAdd);
						}
					}
				}
				else {
					$dbAuth->addOrgsToAuth($theAuthAccount->auth_id, $theAddedOrgs);
				}
			}
		}
		catch( Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ); }
		$theResult = $this->getAuthAccount($theAuthAccount->auth_id);
		$this->setApiResults($theResult->exportData());
	}
	
	/**
	 * Retrieve the details of an existing account.
	 * @param string $aAccountLookup - (OPTIONAL) get the account associated
	 *   with either the auth_id, name, email, IMEI, or account_id. If this
	 *   parameter is NULL, it will be fetched from GET or POST var instead.
	 * @throws BrokenLeg
	 *  * 'PERMISSION_DENIED' - if user doesn't have accounts/view access
	 *  * 'MISSING_ARGUMENT' - if the account ID is not specified
	 *  * 'ENTITY_NOT_FOUND' - if no account with that ID exists
	 * @return AuthAccount Returns the account details.
	 * @since BitsTheater 4.3.1
	 */
	protected function getAuthAccount( $aAccountLookup=null )
	{
		//try auth_id first
		try {
			$dbAuth = $this->getMyModel();
			$theAuthRow = $dbAuth->getAuthByAuthId($aAccountLookup);
			if ( empty($theAuthRow) ) { // also try by name
				$theAuthRow = $dbAuth->getAuthByName($aAccountLookup);
			}
			if ( empty($theAuthRow) ) { // also try by email
				$theAuthRow = $dbAuth->getAuthByEmail($aAccountLookup);
			}
			if ( empty($theAuthRow) ) { // try by old int-based account_id
				$theAuthRow = $dbAuth->getAuthByAccountId($aAccountLookup);
			}
			if ( empty($theAuthRow) ) {
				throw BrokenLeg::toss($this, BrokenLeg::ACT_ENTITY_NOT_FOUND, $aAccountLookup);
			}
			//emulate how $dbAuth->getAccountsToDisplay() would retrieve hardware_ids
			//  which is how the AuthAccount costume would decode it.
			$theAuthRow['hardware_ids'] = implode(AuthAccount::HARDWARE_IDS_SEPARATOR,
					Arrays::array_column(
						$dbAuth->getAuthTokens($theAuthRow['auth_id'], $theAuthRow['account_id'],
								$dbAuth::TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT . ':%', true
						),
						'token'
					)
			);
			$theResult = AuthAccount::fetchInstanceFromRow($theAuthRow, $dbAuth, array(
					'with_map_info',
					'limited_orgs',
			));
			return $theResult;
		}
		catch( \Exception $x )
		{ throw BrokenLeg::tossException($this, $x); }
	}

	/**
	 * Allows a site administrator to view the details of an existing account.
	 * @param string $aAccountLookup - (OPTIONAL) get the account associated
	 *   with either the auth_id, name, email, IMEI, or account_id. If this
	 *   parameter is NULL, it will be fetched from GET or POST var instead.
	 * @throws BrokenLeg
	 *  * 'PERMISSION_DENIED' - if user doesn't have accounts/view access
	 *  * 'MISSING_ARGUMENT' - if the account ID is not specified
	 *  * 'ENTITY_NOT_FOUND' - if no account with that ID exists
	 * @return string Returns the redirect URL, if defined.
	 * @since BitsTheater 4.3.1
	 */
	public function ajajGetAccount( $aAccountLookup=null )
	{
		$this->viewToRender('results_as_json');
		$this->checkAllowed( 'accounts', 'view' );
		$theLookup = $this->getRequestData($aAccountLookup, 'account_id', true);
		$theResult = $this->getAuthAccount($theLookup);
		$this->setApiResults($theResult->exportData());
	}
	
	/**
	 * Allows a site administrator to view the details of an existing account.
	 * This is an alias for ajajGetAccount().
	 * @param string $aAccountLookup - (OPTIONAL) get the account associated
	 *   with either the auth_id, name, email, IMEI, or account_id. If this
	 *   parameter is NULL, it will be fetched from GET or POST var instead.
	 * @throws BrokenLeg
	 *  * 'PERMISSION_DENIED' - if user doesn't have accounts/view access
	 *  * 'MISSING_ARGUMENT' - if the account ID is not specified
	 *  * 'ENTITY_NOT_FOUND' - if no account with that ID exists
	 * @return string Returns the redirect URL, if defined.
	 * @since BitsTheater 3.6
	 * @see AuthOrgAccount::ajajGetAccount()
	 */
	public function ajajGet( $aAccountLookup=null )
	{ $this->ajajGetAccount($aAccountLookup); }

	/**
	 * Fetches the list of organizations to which an account has membership in.
	 * @param string $aAuthID an account's auth ID (a UUID)
	 * @param string[] $aFieldList a subset of columns to return; if exactly one
	 *  column is given, then the return value is a simple array of the values
	 *  from that column
	 * @param SqlBuilder $aFilter - (OPTIONAL) a filter for orgs to get.
	 * @return object[]|NULL an associative array of organization data, or a simple
	 *  array of values if exactly one column is requested in
	 *  <code>$aFieldList</code>, or null if an error occurs.
	 * @since BitsTheater v4.0.0
	 */
	protected function getOrgsForAccount( $aAuthID=null, $aFieldList=null,
			SqlBuilder $aFilter=null )
	{
		try
		{
			$theOrgs = array();
			$dbOrgs = $this->getMyModel();
			$theRowSet = AuthOrgSet::withContextAndColumns($this, $aFieldList)
				->setDataFromPDO(
						$dbOrgs->getOrgsForAuthCursor( $aAuthID, null, $aFilter )
				);
			$theFieldList = $theRowSet->getExportFieldsList();
			$theSimpleField = ( !empty($theFieldList) && count($theFieldList) == 1 )
				? $theFieldList[0] : null;
			foreach ($theRowSet as $theRow) {
				if ( !empty($theSimpleField) )
				{ // Caller wanted exactly one column; collapse it to a string[].
					$theOrgs[] = $theRow->exportData()->{$theSimpleField};
				}
				else {
					$theOrgs[] = $theRow->exportData();
				}
			};
			return $theOrgs ;
		}
		catch( Exception $x )
		{
			$this->errorLog( __METHOD__
					. ' could not fetch org map for account ['
					. $aAuthID . '] because of an exception: '
					. $x->getMessage()
				);
			return null ;
		}
	}

	/**
	 * Public API function to fetch the list of organizations to which an
	 * account belongs.
	 * @param string $aAuthID an account's auth ID (a UUID)
	 * @since BitsTheater v4.0.0
	 */
	public function ajajGetOrgsFor( $aAuthID=null )
	{
		$this->checkAllowed( 'accounts', 'view' ) ;
		$theAuthID = $this->getRequestData( $aAuthID, 'auth_id' ) ;
		$bLimitedOrgs = $this->getRequestData(true, 'limited_orgs', false);
		$theFieldList = $this->getRequestData(array(
				'org_id',
				'org_name',
				'org_title',
				'org_desc',
				'parent_org_id',
		), 'field_list', false);
		$theFilter = null;
		if ( $bLimitedOrgs || !$this->isAllowed('auth_orgs', 'transcend') ) {
			$dbAuth = $this->getMyModel();
			$theCurrOrgID = $dbAuth->getCurrentOrgID();
			if ( !empty($theCurrOrgID) && $theCurrOrgID != $dbAuth::ORG_ID_4_ROOT ) {
				$theLimitedOrgs = $dbAuth->getOrgAndAllChildrenIDs($theCurrOrgID);
				if ( !empty($theLimitedOrgs) ) {
					//instead of returning all the orgs the account belongs to, restrict it to
					//  only returning the orgs based on current org and its children.
					$theFilter = SqlBuilder::withModel($dbAuth)
						->startFilter(' AND map.')
						->setParamValue('showonlythese_orgs', $theLimitedOrgs)
						->addParamForColumn('showonlythese_orgs', 'org_id')
						;
				}
			}
		}
		$this->setApiResults(
				$this->getOrgsForAccount( $theAuthID, $theFieldList, $theFilter )
		);
	}
	
	/**
	 * Standard output for either getAll.
	 * @param \PDOStatement $aRowSet - the result set to return.
	 * @return AuthAccountSet Returns the wrapper class used.
	 * @since BitsTheater 3.7.0
	 */
	protected function getAuthAccountSet($aRowSet)
	{
		$v = $this->getMyScene();
		//get all fields, even the optional ones
		$theFieldList = AuthAccount::getDefinedFields();
		//construct our iterator object
		$theAccountSet = AuthAccountSet::create( $this )
				->setItemClassArgs($this->getMyModel(), $theFieldList)
				->setDataFromPDO($aRowSet)
				;
		$theAccountSet->filter = $v->filter ;
		$theAccountSet->total_count = $v->getPagerTotalRowCount() ;
		
		//include group details.
		$theGroupFieldList = array('group_id', 'group_name');
		$theAccountSet->mGroupList = AuthGroupList::create( $this )
				->setFieldList($theGroupFieldList)
				->setItemClassArgs($this->getAuthGroupsModel(), $theGroupFieldList)
				;
					
		return $theAccountSet;
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
		$this->checkAllowed( 'accounts', 'view' );
		$theGroupID = $this->getRequestData( $aGroupID, 'group_id', false ) ;
		try {
			if ( !empty($theGroupID) ) {
				$dbAuthGroups = $this->getAuthGroupsModel();
				if ( $dbAuthGroups->groupExists($theGroupID) ) {
					$theFilter = SqlBuilder::withModel($dbAuthGroups)
						->startFilter()
						->mustAddParam('group_id', $theGroupID)
						;
				}
				else
				{ throw BrokenLeg::toss( $this, 'ENTITY_NOT_FOUND', $theGroupID ); }
			}
			$dbAuth = $this->getMyModel();
			$theRowSet = $dbAuth->getAuthAccountsToDisplay($this->scene,
					$theFilter
			);
			$this->setApiResults($this->getAuthAccountSet($theRowSet));
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
		$theAccountID = $this->getRequestData( $aAccountID, 'account_id', true ) ;
		$this->setActiveStatus( $theAccountID, true ) ;
	}
	
	/**
	 * What auth patterns should be removed on Deactivation?
	 * Basic patterns are actual IDs of account and account_name as auth_id.
	 * @param AuthAccount $aAcct - the account info.
	 * @return array Returns 2D array [authID:string, acctID:int].
	 */
	protected function getAuthIDPatternsToRemove( AuthAccount $aAcct )
	{
		return array(
				//check normal IDs
				array('authID' => $aAcct->auth_id, 'acctID' => $aAcct->account_id),
				//also check account name
				array('authID' => $aAcct->account_name, 'acctID' => 0),
		);
	}
	
	/**
	 * What auth tokens should be removed on Deactivation?
	 * @return string[]
	 */
	protected function getAuthTokenPatternsToRemove()
	{
		return array(
				AuthDB::TOKEN_PREFIX_ANTI_CSRF . '%',
				AuthDB::TOKEN_PREFIX_COOKIE . '%',
				AuthDB::TOKEN_PREFIX_LOCKOUT . '%',
		);
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
		$theAccountID = $this->getRequestData($aAccountID, 'account_id', true);
		$this->setActiveStatus($theAccountID, false);
		$dbAuth = $this->getMyModel();
		$theAuthRow = $dbAuth->getAuthByAccountId($theAccountID);
		$theFieldList = AuthAccount::getDefinedFields();
		$theFieldList[] = 'load_hardware_ids';
		$theAcct = AuthAccount::fetchInstanceFromRow($theAuthRow, $dbAuth, $theFieldList);
		$theAuthsToRemove = $this->getAuthIDPatternsToRemove($theAcct);
		$theTokensToRemove = $this->getAuthTokenPatternsToRemove();
		foreach ($theAuthsToRemove as $theAuthPattern) {
			$theAuthID = $theAuthPattern['authID'];
			$theAcctID = $theAuthPattern['acctID'];
			//$dbAuth->logStuff(' REMOVING TOKENS FOR ID: ', $theAuthPattern['authID']);//DEBUG
			foreach ($theTokensToRemove as $theTokenPattern) {
				//$dbAuth->logStuff('   REMOVING [', $theTokenPattern, '] Token Pattern');//DEBUG
				$dbAuth->removeTokensFor($theAuthID, $theAcctID, $theTokenPattern);
			}
		}
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
		$this->checkAllowed( 'accounts', 'activate' );
		$dbAuth = $this->getProp( 'Auth' ) ;
		try {
			$theAuth = $dbAuth->getAuthByAccountId($aAccountID) ;
			if ( !empty($theAuth) ) {
				$dbAuth->setInvitation( $dbAuth->createAccountInfoObj($theAuth), $bActive ) ;
			}
			else {
				throw BrokenLeg::toss( $this, 'ENTITY_NOT_FOUND', $aAccountID ) ;
			}
		}
		catch (\Exception $x)
		{ throw BrokenLeg::tossException( $this, $x ) ; }

		$theResponse = new \stdClass() ;
		$theResponse->account_id = $aAccountID ;
		$theResponse->is_active = $bActive ;
		$this->setApiResults($theResponse);
	}

	/**
	 * (Override) As ABitsAccount::ajajDelete(), but also deletes data from the
	 * auth tables.
	 * @since BitsTheater 3.6
	 */
	public function ajajDelete( $aAccountID=null )
	{
		$theAccountID = $this->getRequestData($aAccountID, 'account_id');
		$this->checkCanDeleteAccount($theAccountID);
		$dbAuth = $this->getMyModel();
		$theAuth = $dbAuth->getAuthByAccountID($theAccountID) ;
		$theAuthID = $theAuth['auth_id'];
		if ( !empty($theAuthID) ) {
			// Each part of the chain happens only if the previous one succeeds.
			$this->deletePermissionData($theAccountID, $theAuthID);
			$dbAuth->deleteAuthAccount($theAuthID);
		}
		$this->scene->results = APIResponse::noContentResponse() ;
	}

	/**
	 * Deletes the permission data associated with an account ID.
	 * Consumed by ajajDelete().
	 * @param integer $aAccountID the account ID
	 * @throws BrokenLeg
	 * @since BitsTheater 4.0.0
	 */
	protected function deletePermissionData( $aAccountID, $aAuthID )
	{
		$this->debugLog( __METHOD__ . ' - Deleting auth group map for account [' . $aAuthID . ']...' ) ;
		$dbAuthGroups = $this->getAuthGroupsModel();
		$theGroups = $dbAuthGroups->getAcctGroups( $aAccountID );
		if ( !empty($theGroups) )
		{
			try {
				foreach ($theGroups as $theGroupID)
					$dbAuthGroups->delMap( $theGroupID, $aAuthID ) ;
			}
			catch( Exception $x )
			{ throw BrokenLeg::tossException( $this, $x ) ; }
		}
		return $this ;
	}
	
	/**
	 * Deletes the assignments of an account to organizations, if any.
	 * @param string $aAccountID the account's auth ID
	 * @return $this Returns $this for chaining.
	 */
	protected function deleteOrgMap( $aAuthID )
	{
		$this->debugLog( __METHOD__ . ' deleting org assignments for account ['
				. $aAuthID . ']...' ) ;
		$dbAuth = $this->getProp( static::CANONICAL_MODEL ) ;
		try
		{ $dbAuth->delOrgsForAuth( $aAuthID ) ; }
		catch( Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
		return $this ;
	}

	/**
	 * Map a mobile device with an account to auto-login once configured.
	 * Returns NO CONTENT.
	 * @return string Returns the redirect URL, if defined.
	 */
	public function ajajMapMobileToAccount()
	{
		$v = $this->getMyScene();
		$this->viewToRender('results_as_json');
		//no error for "missing device_id" since we may be NULLing it out
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
		if ( empty($theAuthRow) )
		{ throw BrokenLeg::toss( $this, 'MISSING_VALUE', "'account_id' or 'auth_id'" ) ; }
		try {
			//once we have all 3 peices, create our one-time mapping token
			$dbAuth->generateAutoLoginForMobileDevice($theAuthRow['auth_id'],
					$theAuthRow['account_id'], trim($v->device_id)
			);
			$this->setApiResultsAsNoContent();
		}
		catch (Exception $x)
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}
	
	/**
	 * Mobile devices might ask the server for what account should be used
	 * for authenticating mobile devices (which may be rooted).<br>
	 * Returns JSON encoded array[account_name, auth_id, user_token, auth_token]
	 * @return string Returns the redirect URL, if defined.
	 */
	public function requestMobileAuthAccount()
	{
		$v = $this->getMyScene();
		$this->viewToRender('results_as_json');
		
		$dbAuth = $this->getMyModel();
		$myAuth = $this->getDirector()->getMyAccountInfo();
		$theMobileRow = $this->getMyMobileRow();
		if ( !empty($myAuth) && !empty($theMobileRow) ) {
			$theAuthToken = $dbAuth->generateAuthTokenForMobile(
					$myAuth->account_id, $myAuth->auth_id, $theMobileRow['mobile_id']
			);
			$v->results = array(
					'account_name' => $myAuth->account_name,
					'auth_id' => $myAuth->auth_id,
					'user_token' => $theMobileRow['account_token'],
					'auth_token' => $theAuthToken,
					'api_version_seq' => $this->getRes('website/api_version_seq'),
			);
		}
	}
	
	/**
	 * Render a page for viewing a table of accounts.
	 * @return string Returns the redirect URL, if defined.
	 */
	public function viewAll()
	{
		if (!$this->isAllowed('accounts','view'))
			return $this->getHomePage();
		//shortcut variable $v also in scope in our view php file.
		$v = $this->getMyScene();
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
	
	/**
	 * Endpoint for creating a new organization, which will entail creating a new dbuser and db.
	 * @return string Returns the redirect URL, if defined.
	 */
	public function ajajCreateOrg()
	{
		$this->checkAllowed('auth_orgs', 'create');
		$theOrgName = $this->getRequestData( null, 'org_name' ) ;
		if( ! AuthOrg::validateOrgShortName( $theOrgName ) )
		{ // Don't accept names that might cause trouble for the framework.
			throw AccountAdminException::toss( $this,
				AccountAdminException::ACT_INVALID_ORG_SHORT_NAME, $theOrgName ) ;
		}
		
		$dbAuth = $this->getProp('Auth');
		try {
			$theResults = $this->setApiResults($dbAuth->addOrganization($this->scene));
			if ( !empty($theResults) && !empty($theResults->data) ) {
				$dbAuthGroups = $this->getProp('AuthGroups');
				$dbAuthGroups->setupDefaultDataForNewOrg($theResults->data);
			}
		}
		catch ( Exception $x )
		{ throw BrokenLeg::tossException($this, $x); }
	}
	
	/**
	 * Endpoint for updating an organization, aside from org_name and dbconn fields.
	 * @return string Returns the redirect URL, if defined.
	 */
	public function ajajUpdateOrg()
	{
		$this->checkAllowed('auth_orgs', 'modify');
		$dbAuth = $this->getProp('Auth');
		try {
			$this->scene->results = APIResponse::resultsWithData(
					$dbAuth->updateOrganization($this->scene)
			);
		}
		catch ( Exception $x )
		{ throw BrokenLeg::tossException($this, $x); }
	}
	
	/**
	 * Used by ajajGetOrgs().
	 * @param $aID - (OPTIONAL) get a particular Org else current & children.
	 * @return AuthOrgSet Returns the set to iterate through and fetch.
	 */
	protected function getOrgs( $aID=null )
	{
		$v = $this->getMyScene();
		$dbAuth = $this->getMyModel();
		$theFilter = SqlBuilder::withModel($dbAuth)
			->startFilter()
			;
		if ( !empty($aID) ) {
			$theFilter->setParamValue('org_id', $aID);
		}
		else {
			$theCurrOrgID = $dbAuth->getCurrentOrgID();
			$theIDList = $dbAuth->getOrgAndAllChildrenIDs($theCurrOrgID);
			$theFilter->setParamValueIfEmpty('org_id', $theIDList);
		}
		$theFilter->addParam('org_id');
		//get default field list
		$theFieldList = array();
		if ( filter_var($v->with_map_info, FILTER_VALIDATE_BOOLEAN) )
		{ $theFieldList[] = 'with_map_info'; }
		if ( filter_var($v->with_map_counts, FILTER_VALIDATE_BOOLEAN) )
		{ $theFieldList[] = 'with_map_counts'; }
		//construct our iterator object
		$theRecordSet = AuthOrgSet::create($this)
			->setupPagerDataFromUserData($this->scene)
			->setItemClassArgs($dbAuth, $theFieldList)
			->getOrganizationsToDisplay($theFilter)
			;
		return $theRecordSet;
	}
	
	/**
	 * Retrieve single org for given id or all (paged) if null. Can optionally
	 * supply a field list by supplying a 'field_list' array in the
	 * request body.
	 * @param string $aID - (optional) The ID of the record to be returned.
	 * @return string Return the URL to redirect to, if any.
	 * @throws BrokenLeg::ENTITY_NOT_FOUND if ID given but not found.
	 */
	public function ajajGetOrgs($aID=null)
	{
		// Assign by reference our current scene to our scope helper variable.
		$v = $this->getMyScene();
		// Ensure view set to response in JSON format for this endpoint.
		$this->viewToRender('results_as_json');
		// Ensure required endpoint permissions are held, throwing BrokenLeg otherwise.
		$this->checkAllowed('auth_orgs', 'view');
		// sort_by is an alias for orderby in this endpoint
		$v->orderby = $this->getRequestData($v->orderby, 'sort_by', false);
		// get all data, or just a single one?
		$theID = $this->getRequestData($aID, 'org_id', false);
		// Retrieve the data from db.
		try
		{
			$theRecordSet = $this->getOrgs($theID);
			if ( !empty($theID) ) {
				$theRow = $theRecordSet->fetch();
				if ( $theRow !== false ) {
					$this->setApiResults($theRow->exportData());
				}
				else if ( empty($aID) ) {
					$this->setApiResults(array());
				} else {
					throw BrokenLeg::toss($this, 'ENTITY_NOT_FOUND', $theID);
				}
			}
			else {
				$this->setApiResults($theRecordSet);
			}
		}
		catch(Exception $x)
		{ throw BrokenLeg::tossException($this, $x); }
	}

	/**
	 * Retrieves specific accounts by the given parameters.
	 * @param string $aFilter - Filter string used to search for accounts
	 * by a certain subset of field/column values.
	 */
	public function ajajSearch( $aSearchText=null )
	{
		// Assign by reference our current scene to our scope helper variable.
		$v = $this->getMyScene();
		// Ensure view set to response in JSON format for this endpoint.
		$this->viewToRender('results_as_json');
		// Ensure required endpoint permissions are held, throwing BrokenLeg otherwise.
		$this->checkAllowed('accounts', 'view');
		// sort_by is an alias for orderby in this endpoint
		$v->orderby = $this->getRequestData($v->orderby, 'sort_by', false);
		// Parse given request parameters.
		$theSearchText = Strings::stripEnclosure(trim(
				$this->getRequestData($aSearchText, 'search', false)
		));
		if ( !empty($theSearchText) )
		{ $theSearchText = '%' . $theSearchText . '%'; }
		$bAndSearchText = filter_var($v->andSearch, FILTER_VALIDATE_BOOLEAN);
		//put the authgroups/authorgs ID lists under their appropriate filter key
		if ( !empty($v->authgroups) )
		{
			if ( empty($v->filter) )
			{ $v->filter = array(); }
			if ( is_array($v->filter) )
			{ $v->filter['group_id'] =  $v->authgroups; }
			else //if it is not an array, it is an object
			{ $v->filter->group_id = $v->authgroups; }
		}
		if ( !empty($v->authorgs) )
		{
			if ( empty($v->filter) )
			{ $v->filter = array(); }
			if ( is_array($v->filter) )
			{ $v->filter['org_id'] =  $v->authorgs; }
			else //if it is not an array, it is an object
			{ $v->filter->org_id = $v->authorgs; }
		}
		//we wish to return auth groups and orgs list details
		$bIncMaps = true;
		//get default field list
		$theFieldList = array();
		if ( $bIncMaps )
		{
			$theFieldList[] = 'with_map_info';
			$theFieldList[] = 'limited_orgs'; //flag to limit orgs to current + children only
		}
		try
		{
			//construct our iterator object
			$theIterator = AuthAccountSet::create($this)
				->setupPagerDataFromUserData($this->scene)
				->setupSqlDataFromUserData($this->scene)
				->setItemClassArgs($this->getMyModel(), $theFieldList)
				;
			$theFilter = $theIterator->getFilterForSearch($v->filter,
					$theSearchText, $bAndSearchText);
			$theRowSet = $theIterator->getAccountsToDisplay($theFilter);
			$this->setApiResults($theRowSet);
		}
		catch ( \Exception $x )
		{ throw BrokenLeg::tossException($this, $x); }
	}
	
	/**
	 * Change the currently viewed org to a different one.
	 * @param string $aOrgID - the org_id (if null, fetch from POST var
	 *   <code>'org_id'</code> instead).
	 * @throws BrokenLeg <ul>
	 *  <li>'MISSING_ARGUMENT' - if the org ID is not specified.</li>
	 *  <li>'ENTITY_NOT_FOUND' - if no org with that ID exists.</li>
	 * </ul>
	 * @since BitsTheater v4.1.0
	 */
	public function ajajChangeOrg( $aOrgID=null )
	{
		//guests cannot change what org they are looking at, ignore them
		if ( $this->isGuest() )
		{ throw BrokenLeg::toss($this, BrokenLeg::ACT_NOT_AUTHENTICATED); }
		//org_id is required request parameter (url/get/post)
		$theOrgID = $this->getRequestData( $aOrgID, 'org_id', false ) ;
		//get my auth
		$myAuthID = $this->getDirector()->getMyAccountInfo()->auth_id;
		try {
			//get our model
			$dbAuth = $this->getMyModel();
			if ( !empty($theOrgID) && $theOrgID != $dbAuth::ORG_ID_4_ROOT ) {
				//get our org data - do not use the AuthOrg costume as we need
				//  the dbconn info which the costume does not provide (security
				//  precaution against accidentally exporting back to a client).
				$theOrg = $dbAuth->getOrganization($theOrgID);
				//if org exists...
				if ( !empty($theOrg) ) {
					if ( $this->isAllowed('auth_orgs', 'transcend') ) {
						$theOrgList = array();
						$theOrgSet = AuthOrgSet::withContextAndColumns($this, array('org_id'))
							->setPagerEnabled(false)
							->getOrganizationsToDisplay()
							;
						foreach ($theOrgSet as $anOrg) { //$theOrg var name already in use
							$theOrgList[] = $anOrg->exportData()->org_id;
						}
					}
					else {
						$theOrgList = $dbAuth->getOrgsForAuthCursor($myAuthID, array('org_id'))
							->fetchAll(\PDO::FETCH_COLUMN)
							;
					}
					//... ensure it is one of logged in users mapped orgs
					if ( !empty($theOrgList) && in_array($theOrgID, $theOrgList, true) )
					{
						$dbAuth->setCurrentOrg($theOrg);
						//no need to return anything but a "yep, it worked" response
						$this->setNoContentResponse();
					}
					else {
						throw BrokenLeg::toss($this, BrokenLeg::ACT_FORBIDDEN);
					}
				}
				else {
					throw BrokenLeg::toss($this, BrokenLeg::ACT_ENTITY_NOT_FOUND, $aOrgID);
				}
			}
			else { //switching back to root, only allow if can transcend
				if ( $this->isAllowed('auth_orgs', 'transcend') ) {
					$dbAuth->setCurrentOrg();
					//no need to return anything but a "yep, it worked" response
					$this->setNoContentResponse();
				}
				else {
					throw BrokenLeg::toss($this, BrokenLeg::ACT_MISSING_ARGUMENT, 'org ID');
				}
			}
		}
		catch ( \Exception $x ) {
			throw BrokenLeg::tossException($this, $x);
		}
	}
	
	/**
	 * Fetches a specific account preference for the given account.
	 *
	 * In addition to the function arguments, we support the Boolean query param
	 * 'simple'; if true, then only the value will be returned, as plaintext.
	 * If false, or not specified, the API sends back an object.
	 *
	 * <pre>
	 * {
	 *     "auth_id": (string),
	 *     "namespace": (string),
	 *     "pref_key": (string),
	 *     "pref_value": (boolean|integer|string)
	 * }
	 * </pre>
	 *
	 * @param string $aAuthID the account ID
	 * @param string $aSpace the preference namespace
	 * @param string $aKey the preference key
	 */
	public function ajajGetPreferenceFor( $aAuthID, $aSpace, $aKey )
	{
		$theAuthID = $this->resolveTargetAuthID($aAuthID) ;
		$theSpace = $this->getRequestData( $aSpace, 'namespace' ) ;
		$theKey = $this->getRequestData( $aKey, 'pref_key' ) ;
		$bSimple = $this->getRequestData( null, 'simple', false ) ;
		if( $bSimple === null ) $bSimple = false ;
		$dbPrefs = $this->getProp( PrefsDB::MODEL_NAME ) ;
		try
		{
			$theValue = $dbPrefs->getPreference($theAuthID,$theSpace,$theKey) ;
			if( $bSimple )
			{ // print only the value, directly to the output stream
				$this->viewToRender( 'results_as_txt' ) ;
				print $theValue ;
				return ;
			} // otherwise, return a full API response
			$this->setApiResults( array(
					'auth_id' => $theAuthID,
					'namespace' => $theSpace,
					'pref_key' => $theKey,
					'pref_value' => $theValue
				));
		}
		catch( \Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}
	
	/**
	 * Fetches a specified preference value for the current account.
	 *
	 * In addition to the function arguments, we support the Boolean query param
	 * 'simple'; if true, then only the value will be returned, as plaintext.
	 * If false, or not specified, the API sends back an object.
	 *
	 * <pre>
	 * {
	 *     "auth_id": (string),
	 *     "namespace": (string),
	 *     "pref_key": (string),
	 *     "pref_value": (boolean|integer|string)
	 * }
	 * </pre>
	 *
	 * @param string $aSpace the preference namespace
	 * @param string $aKey the preference key
	 */
	public function ajajGetPreference( $aSpace, $aKey )
	{
		$theAuthID = $this->getDirector()->account_info->auth_id ;
		$this->ajajGetPreferenceFor( $theAuthID, $aSpace, $aKey ) ;
	}
	
	/**
	 * Sets the value of an account preference.
	 * @param string $aAuthID the account ID
	 * @param string $aSpace the preference namespace
	 * @param string $aKey the preference key
	 * @param string $aValue the value to set
	 */
	public function ajajSetPreferenceFor( $aAuthID, $aSpace, $aKey, $aValue=null )
	{
		$theAuthID = $this->resolveTargetAuthID($aAuthID) ;
		$theSpace = $this->getRequestData( $aSpace, 'namespace' ) ;
		$theKey = $this->getRequestData( $aKey, 'pref_key' ) ;
		// Value isn't strictly required because we can allow null.
		$theValue = $this->getRequestData( $aValue, 'pref_value', false ) ;
		$theSummary = array(
				'auth_id' => $theAuthID,
				'namespace' => $theSpace,
				'pref_key' => $theKey,
				'pref_value' => $theValue
			);
		$dbPrefs = $this->getProp( PrefsDB::MODEL_NAME ) ;
		try
		{
			$theResult = $dbPrefs->setPreference(
					$theAuthID, $theSpace, $theKey, $theValue ) ;
			$theSummary['pref_value'] = $theResult['value'] ;
			if ( $theResult['status'] == 200 || $theResult['status'] == 201 ) {
				$this->setApiResults($theSummary, $theResult['status']);
			}
			else
			{ // Some exception handler override returned some data to us.
				$theError = AccountAdminException::toss( $this,
						AccountAdminException::ACT_PREFERENCE_UPDATE_FAILED ) ;
				$theError->putExtras($theSummary) ;
				$theError->setConditionCode( $theResult['status'] ) ;
				throw $theError ;
			}
		}
		catch( \Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}
	
	/**
	 * Sets the value of an account preference for the current account.
	 * @param string $aSpace the preference namespace
	 * @param string $aKey the preference key
	 * @param string $aValue the value to set
	 */
	public function ajajSetPreference( $aSpace, $aKey, $aValue=null )
	{
		$theAuthID = $this->getDirector()->account_info->auth_id ;
		$this->ajajSetPreferenceFor( $theAuthID, $aSpace, $aKey, $aValue ) ;
	}
	
	/**
	 * Resets an account's preference to its default value.
	 * On success, we just return HTTP 204.
	 * Operation is idempotent; if setting is already gone, we don't care.
	 * @param string $aAuthID the account ID
	 * @param string $aSpace the preference namespace
	 * @param string $aKey the preference key
	 */
	public function ajajResetPreferenceFor( $aAuthID, $aSpace, $aKey )
	{
		$theAuthID = $this->resolveTargetAuthID($aAuthID) ;
		$theSpace = $this->getRequestData( $aSpace, 'namespace' ) ;
		$theKey = $this->getRequestData( $aKey, 'pref_key' ) ;
		$dbPrefs = $this->getProp( PrefsDB::MODEL_NAME ) ;
		try
		{
			$theResult = $dbPrefs->resetPreference(
					$theAuthID, $theSpace, $theKey ) ;
			if( $theResult )
				$this->setApiResultsAsNoContent() ;
			else
			{ // Something failed and didn't throw an exception.
				throw AccountAdminException::toss( $this,
						AccountAdminException::ACT_PREFERENCE_UPDATE_FAILED ) ;
			}
		}
		catch( \Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}
	
	/**
	 * Resets the specified preference for the current account to its default
	 * value.
	 * On success, we just return HTTP 204.
	 * Operation is idempotent; if setting is already gone, we don't care.
	 * @param string $aSpace the preference namespace
	 * @param string $aKey the preference key
	 */
	public function ajajResetPreference( $aSpace, $aKey )
	{
		$theAuthID = $this->getDirector()->account_info->auth_id ;
		$this->ajajResetPreferenceFor( $theAuthID, $aSpace, $aKey ) ;
	}
	
	/**
	 * Gets all the preferences for an account.
	 * @param string $aAuthID (optional) the account ID; if not supplied, then
	 *  the current account's preferences are fetched
	 */
	public function ajajGetPreferencesFor( $aAuthID=null )
	{
		$theAuthID = $this->resolveTargetAuthID($aAuthID) ;
		$dbPrefs = $this->getProp( PrefsDb::MODEL_NAME ) ;
		try
		{ $this->setApiResults( $dbPrefs->getPreferencesFor($theAuthID) ) ; }
		catch( \Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}
	
	/**
	 * Gets preferences for the current account.
	 */
	public function ajajGetPreferences()
	{
		$theAuthID = $this->getDirector()->account_info->auth_id ;
		$this->ajajGetPreferencesFor($theAuthID) ;
	}
	
	/**
	 * Gets the preference <i>profile</i> for an account. This is a hierarchical
	 * map of preferences namespaces and keys to values, where the value is
	 * either an explicit value provisioned for the account, or the default
	 * value specified in the application resources.
	 * @param string $aAuthID (optional) the account ID; if not supplied, then
	 *  the current account's preferences are fetched
	 */
	public function ajajGetPreferenceProfileFor( $aAuthID=null )
	{
		$theAuthID = $this->resolveTargetAuthID($aAuthID) ;
		$dbPrefs = $this->getProp( PrefsDb::MODEL_NAME ) ;
		try
		{
			$this->setApiResults(
					$dbPrefs->getPreferenceProfileFor($theAuthID) ) ;
		}
		catch( \Exception $x )
		{ throw BrokenLeg::tossException( $this, $x ) ; }
	}
	
	/**
	 * Gets the preference <i>profile</i> for this account. This is a
	 * hierarchical map of preferences namespaces and keys to values, where the
	 * value is either an explicit value provisioned for the account, or the
	 * default value specified in the application resources.
	 */
	public function ajajGetPreferenceProfile()
	{
		$theAuthID = $this->getDirector()->account_info->auth_id ;
		$this->ajajGetPreferenceProfileFor($theAuthID) ;
	}
		
	/**
	 * Reusable code to figure out which account we're working with. A requestor
	 * may send a request with no auth ID supplied, in which case we should act
	 * on the requestor's own account. If the requestor does supply an explicit
	 * ID, then we must ensure that it matches
	 * @param string $aAuthID (optional) the account ID; if not supplied, then
	 *  the current account's preferences are fetched
	 * @return string the resolved ID
	 */
	protected function resolveTargetAuthID( $aAuthID=null )
	{
		$theAuthID = $this->getRequestData( $aAuthID, 'auth_id', false ) ;
		$theCurrentAuthID = $this->getDirector()->account_info->auth_id ;
		if( empty($theAuthID) ) // No ID was supplied; use the current account.
			$theAuthID = $theCurrentAuthID ;
		else if( $theAuthID != $theCurrentAuthID ) // Requestor needs rights.
			$this->checkAllowed( 'accounts', 'modify' ) ;
		return $theAuthID ;
	}
	
}//end class

}//end namespace
