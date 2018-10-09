<?php
/*
 * Copyright (C) 2018 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes\Wardrobe;
use BitsTheater\costumes\ABitsCostume as BaseCostume;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\costumes\venue\IWillCall;
use BitsTheater\costumes\WornByModel;
use BitsTheater\models\Auth as AuthDB;
use BitsTheater\Scene;
{//namespace begin

/**
 * Class used to help manage authentication via some mechanism.
 * @since BitsTheater v4.1.0
 */
abstract class ATicketForVenue extends BaseCostume
implements IWillCall
{
	use WornByModel;
	
	/** @var string Name used to determine the lockout token, if necessary. */
	public $ticket_name;
	
	/** @var string Key to use for black list property in session storage. */
	const KEY_SESSION_BLACK_LIST = 'ticket-ripped';
	
	/**
	 * Magic PHP method to limit what var_dump() shows.
	 */
	public function __debugInfo() {
		$vars = parent::__debugInfo();
		unset($vars['model']);
		return $vars;
	}
	
	/**
	 * Construct and return our new object.
	 * @param AuthDB $aAuthDB - the auth model.
	 * @return $this Returns $this for chaining.
	 */
	static public function withAuthDB( $aAuthDB )
	{
		$theCalledClass = get_called_class();
		return $theCalledClass::withModel($aAuthDB);
	}
	
	/** @return AuthDB Returns the Auth model in use. */
	protected function getMyModel()
	{ return $this->getModel(); }
	
	/** @return string Returns the Username input. */
	protected function getUserInput( Scene $aScene )
	{
		if ( !empty($aScene->{AuthDB::KEY_userinfo}) )
		{ return trim($aScene->{AuthDB::KEY_userinfo}); }
	}
	
	/** @return string Returns the Password input. */
	protected function getAuthInput( Scene $aScene )
	{
		if ( !empty($aScene->{AuthDB::KEY_pwinput}) )
		{ return trim($aScene->{AuthDB::KEY_pwinput}); }
	}
	
	/**
	 * Check to see if ticket failed so often it is locked out.
	 * @param Scene $aScene - the scene object.
	 * @param string $aTicketName - the ticket input to check.
	 * @return boolean Returns TRUE if too many failures locked the account.
	 */
	public function checkTicketForLockout( Scene $aScene )
	{
		if ( !empty($this->ticket_name) ) {
			//are you already on our soft-ban list?
			//unset($this->getDirector()[static::KEY_SESSION_BLACK_LIST]); //DEBUG
			$theBanList = $this->getDirector()[static::KEY_SESSION_BLACK_LIST];
			if ( !empty($theBanList) ) {
				if ( in_array($this->ticket_name, $theBanList, true) ) {
					//$this->debugLog("[{$this->ticket_name}] is blacklisted, start a new session."); //DEBUG
					return true;
				}
			}
			//not banned, so let us count the number of lockouts they have
			$theMaxAttempts = 0;
			if ( $this->getDirector()->isInstalled() ) {
				$theMaxAttempts = intval(
						$this->getConfigSetting('auth/login_fail_attempts'),
						10
				);
			}
			if ( $theMaxAttempts > 0 ) {
				//  once the number of lockout attempts >= lockout tokens,
				//  account is locked; account will unlock after tokens expire
				//  (currently 1 hour). NOTE: tokens expire individually.
				$dbAuth = $this->getMyModel();
				$theLockoutTokens = $dbAuth->getAuthTokens($this->ticket_name,
						0, $dbAuth::TOKEN_PREFIX_LOCKOUT. '%', true
				);
				//$this->logStuff(__METHOD__, ' locks=', $theLockoutTokens); //DEBUG
				if ( count($theLockoutTokens) >= $theMaxAttempts ) {
					$this->onTicketLocked($aScene, $this->ticket_name);
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Tasks to complete before we even check to see if this ticket is for
	 * this particular venue.
	 * @param Scene $aScene - var container object for auth info.
	 * @return $this Returns $this for chaining.
	 */
	abstract protected function onBeforeCheckTicket( Scene $aScene );

	/**
	 * Check to see if this venue should process the ticket.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return boolean Returns TRUE if this venue should process the ticket.
	 */
	abstract protected function isTicketForThisVenue( Scene $aScene );

	/**
	 * The method used to perform authentication for this particular venue.
	 * @param Scene $aScene - variable container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	abstract protected function processTicket( Scene $aScene );

	/**
	 * The method called to actually perform authentication.
	 * @param Scene $aScene - variable container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	public function checkForTicket( Scene $aScene )
	{
		$this->onBeforeCheckTicket($aScene);
		if ( $this->isTicketForThisVenue($aScene) ) {
			//first, check to see if locked out
			if ( $this->checkTicketForLockout($aScene) )
			{ return; }
			return $this->processTicket($aScene);
		}
	}
	
	/**
	 * If we successfully authorize, do some additional things like load up
	 * their permission groups.
	 * @param Scene $aScene - var container object for auth info.
	 * @param AccountInfoCache $aAcctInfo - the account info to use.
	 * @return $this Returns $this for chaining.
	 */
	public function onTicketAccepted( Scene $aScene, AccountInfoCache $aAcctInfo )
	{
		//$this->logStuff(__METHOD__, ' DEBUG ', $this);
		$dbAuth = $this->getMyModel();
		//authorized, load extended account data
		if ( is_null($aAcctInfo->groups) ) {
			$dbAuthGroups = $dbAuth->getProp('AuthGroups');
			$aAcctInfo->groups = $dbAuthGroups->getGroupIDListForAuthAndOrg(
					$aAcctInfo->auth_id, $dbAuth->getCurrentOrgID()
			);
			$this->returnProp($dbAuthGroups);
		}
		return $this;
	}
	
	/**
	 * If we try to authorize and are rejected, do some additional things.
	 * @param Scene $aScene - var container object for auth info.
	 * @param AccountInfoCache $aAcctInfo - the account which rejected auth.
	 * @return $this Returns $this for chaining.
	 */
	public function onTicketRejected( Scene $aScene, AccountInfoCache $aAcctInfo )
	{
		$dbAuth = $this->getMyModel();
		if ( !empty($aAcctInfo) ) {
			$dbAuth->generateAuthToken($aAcctInfo->auth_id, $aAcctInfo->account_id,
					AuthDB::TOKEN_PREFIX_LOCKOUT
			);
		}
		//generate a token for the login name as well as the ID
		if ( !empty($this->ticket_name) ) {
			$dbAuth->generateAuthToken($this->ticket_name, 0, AuthDB::TOKEN_PREFIX_LOCKOUT);
		}
		return $this;
	}
	
	/**
	 * If ticket fails too often, and a lockout status was determined,
	 * this method gets executed.
	 * @param Scene $aScene - the scene object.
	 * @return $this Returns $this for chaining.
	 */
	public function onTicketLocked( Scene $aScene )
	{
		$aScene->addUserMsg(
				$this->getRes('account', 'err_pw_failed_account_locked'),
				$aScene::USER_MSG_ERROR
		);
		$this->logStuff("[{$this->ticket_name}] is being session banned.");
		//once you get on the soft-ban list, your session needs to expire
		//  before you can try again, even if your tokens expire.
		//  since sessions expire every ~20min of inactivity, this is no
		//  big deal since lockouts are for 1 hour and this will help
		//  prevent DDoS of the site forcing the DB to query for tokens for
		//  every login attempt. Even if the attacker is forced to restart
		//  a new session, that buys cooldown time.
		$theBanList = $this->getDirector()[static::KEY_SESSION_BLACK_LIST];
		$theBanList[] = $this->ticket_name; //cannot do this directly on Director
		$this->getDirector()[static::KEY_SESSION_BLACK_LIST] = $theBanList;
		return $this;
	}
	
	/**
	 * Log the current user out and wipe the slate clean.
	 * Each venue may cache specific items which this should clear out.
	 * @param AccountInfoCache $aAcctInfo - the account info to use.
	 * @return $this Returns $this for chaining.
	 */
	public function ripTicket( AccountInfoCache $aAcctInfo )
	{
		return $this;
	}
	
}//end class

}//end namespace
