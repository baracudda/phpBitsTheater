<?php
/*
 * Copyright (C) 2019 Blackmoon Info Tech Services
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
use BitsTheater\costumes\Wardrobe\ATicketForVenue as BaseCostume;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\costumes\AuthPasswordReset;
use BitsTheater\outtakes\PasswordResetException;
use BitsTheater\Scene;
{//namespace begin

/**
 * Class used to help manage logging in via a password reset link.
 * @since BitsTheater v4.3.0
 */
class TicketViaResetLink extends BaseCostume
{
	/** @var string The request token to match against the auth token. */
	public $auth_token;
	/** @var AuthPasswordReset The object handling pw reset. */
	public $mAuthReset;
	
	/**
	 * Tasks to complete before we even check to see if this ticket is for
	 * this particular venue.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return $this Returns $this for chaining.
	 */
	protected function onBeforeCheckTicket( Scene $aScene )
	{
		if ( !empty($aScene->auth_id) ) {
			$this->ticket_name = $aScene->auth_id;
		}
		if ( !empty($aScene->auth_token) ) {
			$this->auth_token = $aScene->auth_token;
		}
		return $this;
	}
	
	/**
	 * Check to see if this venue should process the ticket.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return boolean Returns TRUE if this venue should process the ticket.
	 */
	protected function isTicketForThisVenue( Scene $aScene )
	{
		//$this->logStuff(__METHOD__, ' this=', $this); //DEBUG
		return ( !$aScene->bExplicitAuthRequired &&
				!empty($this->ticket_name) && !empty($this->auth_token) );
	}

	/**
	 * The method used to perform authentication for this particular venue.
	 * @param Scene $aScene - var container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	protected function processTicket( Scene $aScene )
	{
		try {
			$dbAuth = $this->getMyModel();
			$this->mAuthReset = AuthPasswordReset::withModel($dbAuth);
			//$this->logStuff(__METHOD__, ' [TRACE] venue=', $this); //DEBUG
			if ( $this->mAuthReset->authenticateForReentry($this->ticket_name, $this->auth_token) ) {
				$theAuthRow = $dbAuth->getAuthByAuthId($this->ticket_name);
				//$this->logStuff(__METHOD__, ' [TRACE] auth=', $theAuthRow); //DEBUG
				if ( !empty($theAuthRow) ) {
					return $dbAuth->createAccountInfoObj($theAuthRow);
				}
			}
		}
		catch( PasswordResetException $prx ) {
			$this->logErrors($prx->toJson());
			throw $prx;
		}
		catch ( \Exception $x) {
			$this->logStuff(__METHOD__, ' ', $x->getMessage());
		}
	}
	
	/**
	 * If we successfully authorize, do some additional things.
	 * @param Scene $aScene - var container object for auth info.
	 * @param AccountInfoCache $aAcctInfo - the account info to use.
	 * @return $this Returns $this for chaining.
	 */
	public function onTicketAccepted( Scene $aScene, AccountInfoCache $aAcctInfo )
	{
		parent::onTicketAccepted($aScene, $aAcctInfo);
		$dbAuth = $this->getMyModel();
		//once we've successfully used the email link, set the pw
		$this->mAuthReset->clobberPassword() ;
		//also remove all pw reset tokens for this account BEFORE we create more tokens
		$this->mAuthReset->deleteAllTokens() ;
		//determine what org to use
		$dbAuth->checkForDefaultOrg($aScene, $aAcctInfo);
		//save ticket short term cache
		$dbAuth->saveAccountToSessionCache($aAcctInfo);
		//login success, bake our CSRF token cookie!
		//$bCsrfTokenWasBaked =
				$dbAuth->setCsrfTokenCookie();
		return $this;
	}
	
}//end class

}//end namespace
