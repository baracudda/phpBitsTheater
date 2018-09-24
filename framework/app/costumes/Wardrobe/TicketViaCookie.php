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
use BitsTheater\costumes\Wardrobe\ATicketForVenue as BaseCostume;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\Scene;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * Class used to help manage logging in via a browser Cookie.
 * @since BitsTheater [NEXT]
 */
class TicketViaCookie extends BaseCostume
{
	/** @var string The cookie's token to match against the auth token. */
	public $cookie_token;
	
	/**
	 * Tasks to complete before we even check to see if this ticket is for
	 * this particular venue.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return $this Returns $this for chaining.
	 */
	protected function onBeforeCheckTicket( Scene $aScene )
	{
		$dbAuth = $this->getMyModel();
		if ( !empty($_COOKIE[$dbAuth::KEY_userinfo]) ) {
			$this->ticket_name = Strings::strstr_after(
					$_COOKIE[$dbAuth::KEY_userinfo],
					$this->getDirector()->app_id . '-'
			);
		}
		if ( !empty($_COOKIE[$dbAuth::KEY_token]) ) {
			$this->cookie_token = $_COOKIE[$dbAuth::KEY_token];
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
		return !$aScene->bExplicitAuthRequired &&
				!empty($this->ticket_name) && !empty($this->cookie_token) ;
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
		//our cookie mechanism consumes cookie on use and creates a new one
		//  by having rotating cookie tokens, stolen cookies have a limited
		//  window in which to crack them before a new one is generated.
		try {
			$dbAuth = $this->getMyModel();
			$theResult = $dbAuth->getAndEatCookie(
					$this->ticket_name, $this->cookie_token
			);
			if ( !empty($theResult) ) {
				$theAuthRow = $dbAuth->getAuthByAuthId($this->ticket_name);
				if ( !empty($theAuthRow) ) {
					return $dbAuth->createAccountInfoObj($theAuthRow);
				}
			}
		} catch ( \Exception $x) {
			//do not care if the cookie crumbles,
			//  log it so admin knows about it, though
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
		//save ticket short term cache
		$dbAuth->saveAccountToSessionCache($aAcctInfo);
		//login success, bake our CSRF token cookie!
		$bCsrfTokenWasBaked = $dbAuth->setCsrfTokenCookie();
		//so updateCookie() has audit info
		$this->getDirector()->account_info = $aAcctInfo;
		//bake (re-create) a new cookie for next time
		$dbAuth->updateCookie($aAcctInfo->auth_id, $aAcctInfo->account_id);
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
		//remove any browser cookies
		$dbAuth = $this->getMyModel();
		$dbAuth->setMySiteCookie($dbAuth::KEY_userinfo);
		$dbAuth->setMySiteCookie($dbAuth::KEY_token);
		try {
			//remove their cookie tokens
			$dbAuth->removeTokensFor($aAcctInfo->auth_id,
					$aAcctInfo->account_id, $dbAuth::TOKEN_PREFIX_COOKIE . '%'
			);
		}
		catch (\Exception $x) {
			//do not care if removing tokens fail, log it though
			$this->logErrors(__METHOD__,
					' removing cookie tokens during logout: ',
					$x->getMessage()
			);
		}
		return parent::ripTicket($aAcctInfo);
	}
	
}//end class

}//end namespace
