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
 * @since BitsTheater [NEXT]
 */
class TicketViaRequest extends BaseCostume
{
	/** @var boolean Update the cookie, if requested to do so. */
	protected $bUpdateCookie = false;
	
	/**
	 * Cookies might remember our user if the session forgot and they have
	 * not tried to login.
	 * @param array $aCookieMonster - the cookie keys and data.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	protected function getAuthFromCookie($aCookieMonster) {
		$dbAuth = $this->getMyModel();
		if ( !empty($aCookieMonster[$dbAuth::KEY_userinfo]) ) {
			$theAuthID = Strings::strstr_after(
					$aCookieMonster[$dbAuth::KEY_userinfo],
					$this->getDirector()->app_id . '-'
			);
		}
		if ( !empty($aCookieMonster[$dbAuth::KEY_token]) ) {
			$theAuthToken = $aCookieMonster[$dbAuth::KEY_token];
		}
		if ( !empty($theAuthID) && !empty($theAuthID) ) {
			//our cookie mechanism consumes cookie on use and creates a new one
			//  by having rotating cookie tokens, stolen cookies have a limited
			//  window in which to crack them before a new one is generated.
			$theResult = $dbAuth->getAndEatCookie($theAuthID, $theAuthToken);
			if ( !empty($theResult) ) {
				$this->bUpdateCookie = true; //replace the used cookie
			}
		}
	}
	
	/**
	 * Check PHP session storage for auth info.
	 * @param Scene $aScene - var container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	protected function getAuthFromSession($aScene)
	{
		$dbAuth = $this->getMyModel();
		if ( !empty($this->getDirector()[$dbAuth::KEY_userinfo]) ) {
			$theAuthID = $this->getDirector()[$dbAuth::KEY_userinfo];
			//session info used up, unset in case fail occurs later
			unset($this->getDirector()[$dbAuth::KEY_userinfo]);
			return $dbAuth->getAuthByAuthId($theAuthID);
		}
	}

	/**
	 * The URL may contain authorization information.
	 * @param Scene $aScene - var container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	public function checkForTicket(Scene $aScene)
	{
		$theResult = null;
		$dbAuth = $this->getMyModel();
		if ( !empty($aScene->{$dbAuth::KEY_userinfo}) )
		{ $theUserInput = $aScene->{$dbAuth::KEY_userinfo}; }
		if ( !empty($aScene->{$dbAuth::KEY_pwinput}) )
		{ $theAuthInput = $aScene->{$dbAuth::KEY_pwinput}; }
		if ( !empty($theUserInput) && !empty($theAuthInput) ) {
			$theAuthRow = $dbAuth->getAuthByName($theUserInput);
			if ( empty($theAuthRow) ) {
				$theAuthRow = $dbAuth->getAuthByEmail($theUserInput);
			}
			if ( !empty($aScene->{$dbAuth::KEY_cookie}) ) {
				$this->bUpdateCookie = filter_var(FILTER_VALIDATE_BOOLEAN,
						$aScene->{$dbAuth::KEY_cookie}
				);
			}
		}
		else if ( empty($theUserInput) && empty($theAuthInput) &&
				!$aScene->bExplicitAuthRequired )
		{
			//check to see if we have the auth_id cached in short term
			$theAuthRow = $this->getAuthFromSession($aScene);
			if ( empty($theAuthRow) ) {
				//check to see if we have the auth_id in long term cache
				try {
					$theAuthRow = $this->getAuthFromCookie($_COOKIE);
				} catch ( \Exception $x) {
					//do not care if the cookie crumbles,
					//  log it so admin knows about it, though
					$this->logStuff(__METHOD__, ' ', $x->getMessage());
				}
			}
		}
		//clean up all possible places where the pw input might reside
		unset($aScene->{$dbAuth::KEY_pwinput});
		unset($_GET[$dbAuth::KEY_pwinput]);
		unset($_POST[$dbAuth::KEY_pwinput]);
		unset($_REQUEST[$dbAuth::KEY_pwinput]);
		
		//see if we can successfully log in now that we know what auth record
		//$this->logStuff(__METHOD__, ' arow=', $theAuthRow);//DEBUG
		if ( !empty($theAuthRow) ) {
			//account was found!
			$pwhash = $theAuthRow['pwhash'];
			$theResult = $dbAuth->createAccountInfoObj($theAuthRow);
			unset($theAuthRow);
			//check pwinput against 1-way encrypted one
			if ( Strings::hasher($theAuthInput, $pwhash) ) {
				//authorized, load extended account data
				$dbAuthGroups = $dbAuth->getProp('AuthGroups');
				$theResult->groups = $dbAuthGroups->getGroupIDListForAuth(
						$theResult->auth_id
				);
				$this->returnProp($dbAuthGroups);
			} else {
				//auth fail!
				$theResult->is_active = false;
			}
			unset($pwhash);
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
	public function onTicketAccepted(Scene $aScene, AccountInfoCache $aAcctInfo)
	{
		//$this->logStuff(__METHOD__, ' DEBUG ', $this);
		$dbAuth = $this->getMyModel();
		//save ticket short term cache
		$this->getDirector()[$dbAuth::KEY_userinfo] = $aAcctInfo->auth_id;
		//if we have been asked to remember the ticket long term, save a cookie
		if ( $this->bUpdateCookie ) {
			//bake (create) a new cookie for next time
			$dbAuth->updateCookie($aAcctInfo->auth_id, $aAcctInfo->account_id);
		}
		//$this->debugLog(__METHOD__ . ' setCsrfTokenCookie call.'); //DEBUG
		$bCsrfTokenWasBaked = $dbAuth->setCsrfTokenCookie();
		return $this;
	}
	
}//end class

}//end namespace
