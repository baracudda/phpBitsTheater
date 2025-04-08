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
use BitsTheater\actors\Understudy\AuthOrgsBase as BaseActor;
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\AuthAccount;
use BitsTheater\costumes\LogMessage as Logger;
use BitsTheater\costumes\SqlBuilder;
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
			
			$theOrgID = $this->getDirector()->getPropsMaster()->getDefaultOrgID();
			if ( $theOrgID == AuthDB::ORG_ID_4_ROOT ) $theOrgID = null;
			$dbAuthGroups = $this->getAuthGroupsModel();
			//currOrgRoles gets only those roles for current org
			$theAuthRow['currOrgRoles'] = SqlBuilder::withModel($dbAuthGroups)
					->startWith('SELECT')
					->add("IFNULL(GROUP_CONCAT(__CurrOrgRoleMapAlias.group_id SEPARATOR ','), '') AS currOrgRoles")
					->add("FROM")->add($dbAuthGroups->tnGroupMap)->add("AS __CurrOrgRoleMapAlias")
					->add("JOIN")->add($dbAuthGroups->tnGroups)->add("AS __CurrOrgRolesAlias USING (group_id)")
					->startWhereClause('__CurrOrgRoleMapAlias.')
					->mustAddParam('auth_id', $theAuthRow['auth_id'])
					->setParamPrefix(' AND __CurrOrgRolesAlias.')
					->mustAddParam('org_id', $theOrgID)
					->endWhereClause()
					//->logSqlDebug(__METHOD__)
					->query()->fetchColumn();
			;
			
			//typical AuthAccount obj does not get all defined orgs (just ones with roles)
			$theAuthRow['org_ids'] = SqlBuilder::withModel($dbAuth)
					->startWith('SELECT')
					->add("IFNULL(GROUP_CONCAT(__AuthOrgAlias.org_id SEPARATOR ','), '') AS org_ids")
					->add("FROM")->add($dbAuth->tnAuthOrgMap)->add("AS __AuthOrgAlias")
					->startWhereClause('__AuthOrgAlias.')
					->mustAddParam('auth_id', $theAuthRow['auth_id'])
					->endWhereClause()
					//->logSqlDebug(__METHOD__)
					->query()->fetchColumn();
			;
			
			$theFieldList = AuthAccount::getExportFieldListUsingShorthand(array('with_map_info'));
			$theOptions = AuthAccount::getOptionsListUsingShorthand($this, $theFieldList, array('limited_orgs'=>true));
			//$this->logStuff(__METHOD__, ' opts=', $theOptions);//DEBUG
			$theResult = AuthAccount::withRow($theAuthRow, $dbAuth, $theFieldList, $theOptions);
			//$this->logStuff(__METHOD__, ' auth=', $theResult);//DEBUG
			return $theResult;
		}
		catch( \Exception $x )
		{ throw BrokenLeg::tossException($this, $x); }
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
		$theFieldList[] = 'hardware_ids';
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
					$theAuthRow['account_id'], Strings::trim($v->device_id)
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
	
}//end class

}//end namespace
