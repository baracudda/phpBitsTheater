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
 * Class used to help manage authentication via request parameters, whether
 * they are found in GET or POST.
 * @since BitsTheater v4.1.0
 */
class TicketViaRequest extends BaseCostume
{
	/** @var string password for the account. */
	public $ticket_secret;
	/** @var boolean Update the cookie, if requested to do so. */
	protected $bUpdateCookie = false;
	
	/**
	 * Tasks to complete before we even check to see if this ticket is for
	 * this particular venue.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return $this Returns $this for chaining.
	 */
	protected function onBeforeCheckTicket( Scene $aScene )
	{
		$this->ticket_name = $this->getUserInput($aScene);
		$this->ticket_secret = $this->getAuthInput($aScene);
		$dbAuth = $this->getMyModel();
		//UI might use "current time" rather than actual boolean-like value.
		$this->bUpdateCookie = !empty($aScene->{$dbAuth::KEY_cookie});
		return $this;
	}
	
	/**
	 * Check to see if this venue should process the ticket.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return boolean Returns TRUE if this venue should process the ticket.
	 */
	protected function isTicketForThisVenue( Scene $aScene )
	{
		return !empty($this->ticket_name) && !empty($this->ticket_secret);
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
		$theResult = null;
		$dbAuth = $this->getMyModel();
		$theAuthRow = $dbAuth->getAuthByName($this->ticket_name);
		if ( empty($theAuthRow) ) {
			$theAuthRow = $dbAuth->getAuthByEmail($this->ticket_name);
		}
		//see if we can successfully log in now that we know what auth record
		if ( !empty($theAuthRow) ) {
			$theResult = $dbAuth->createAccountInfoObj($theAuthRow);
			//check pwinput against 1-way encrypted one
			if ( !Strings::hasher($this->ticket_secret, $theAuthRow['pwhash']) ) {
				//auth fail!
				$theResult->is_active = false;
			}
		}
		//return our results
		return $theResult;
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
		//clean up all possible places where the valid pw input might reside
		unset($this->ticket_secret);
		unset($aScene->{$dbAuth::KEY_pwinput});
		unset($_GET[$dbAuth::KEY_pwinput]);
		unset($_POST[$dbAuth::KEY_pwinput]);
		unset($_REQUEST[$dbAuth::KEY_pwinput]);
		//save ticket short term cache
		$dbAuth->saveAccountToSessionCache($aAcctInfo);
		//login success, bake our CSRF token cookie!
		//$bCsrfTokenWasBaked =
				$dbAuth->setCsrfTokenCookie();
		//if we have been asked to remember the ticket long term, save a cookie
		if ( $this->bUpdateCookie ) {
			//bake (create) a new cookie for next time
			$dbAuth->updateCookie($aAcctInfo);
		}
		//determine what org to use
		$dbAuth->checkForDefaultOrg($aScene, $aAcctInfo);
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
		try {
			$dbAuth = $this->getMyModel();
			//remove their anti-CSRF tokens
			$dbAuth->removeTokensFor($aAcctInfo->auth_id,
					$aAcctInfo->account_id,
					$dbAuth::TOKEN_PREFIX_ANTI_CSRF . '%'
			);
		}
		catch (\Exception $x) {
			//do not care if removing tokens fail, log it though
			$this->logErrors(__METHOD__,
					' removing anti-CSRF tokens during logout: ',
					$x->getMessage()
			);
		}
		return parent::ripTicket($aAcctInfo);
	}
	
}//end class

}//end namespace
