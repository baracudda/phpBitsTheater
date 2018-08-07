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
use BitsTheater\costumes\Wardrobe\TicketViaRequest as BaseCostume;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\costumes\HttpAuthHeader;
use BitsTheater\Scene;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * Class used to help manage logging in via HTTP "Authorization" Header.
 * @since BitsTheater [NEXT]
 */
class TicketViaHttpHeader extends BaseCostume
{
	/** @var array The mobile record, if we used Broadway auth. */
	protected $mMobileRow;
	
	/**
	 * Descendants may wish to further scrutinize header information before allowing access.
	 * @param HttpAuthHeader $aAuthHeader - the header info.
	 * @param array $aMobileRow - the mobile row data.
	 * @param AccountInfoCache $aUserAccount - the user account data.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 */
	protected function checkMobileCircumstances(
			HttpAuthHeader $aAuthHeader, AccountInfoCache $aAuthAccount)
	{
		//barring checking circumstances like if the GPS is outside
		//  pre-determined bounds, we are authenticated!
	}

	/**
	 * The HTTP "Authorization" Header may contain authorization information.
	 * @param Scene $aScene - var container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	public function checkForTicket(Scene $aScene)
	{
		if ( empty($aScene->HTTP_AUTHORIZATION) )
		{ return null; } //trivial, nothing to check
		$theResult = null;
		$dbAuth = $this->getMyModel();
		//check for HttpAuth header
		$theAuthHeader = HttpAuthHeader::fromHttpAuthHeader(
				$this->getDirector(), $aScene->HTTP_AUTHORIZATION
		);
		switch ($theAuthHeader->auth_scheme) {
			case $theAuthHeader::AUTH_TYPE_BASIC:
				$aScene->{$dbAuth::KEY_userinfo} = $theAuthHeader->getHttpAuthBasicAccountName();
				$aScene->{$dbAuth::KEY_pwinput} = $theAuthHeader->getHttpAuthBasicAccountPw();
				//keeping lightly protected pw in memory can be bad, clear out usage asap.
				unset($theAuthHeader);
				unset($aScene->HTTP_AUTHORIZATION);
				unset($_SERVER['HTTP_AUTHORIZATION']);
				return parent::checkForTicket($aScene);
			case $theAuthHeader::AUTH_TYPE_BROADWAY:
				//$this->logStuff(__METHOD__, ' chkhdr=', $theAuthHeader);
				if ( !empty($theAuthHeader->auth_id) && !empty($theAuthHeader->auth_token) ) {
					$theResult = $dbAuth->createAccountInfoObj($dbAuth->getAuthByAuthId(
							$theAuthHeader->auth_id
					));
					$dbAuth->removeStaleMobileAuthTokens();
					if ( !empty($theResult) && $theResult->is_active ) {
						$theAuthTokenRow = $dbAuth->getAuthTokenRow(
								$theAuthHeader->auth_id, $theAuthHeader->auth_token
						);
						//$this->logStuff(__METHOD__, ' arow=', $theAuthTokenRow);
					}
					if ( !empty($theAuthTokenRow) ) {
						$theAuthMobileRows = $dbAuth->getAuthMobilesByAuthId(
								$theAuthHeader->auth_id
						);
						//$this->logStuff(__METHOD__, ' fp=', $theAuthHeader->fingerprints);
						foreach ($theAuthMobileRows as $theMobileRow) {
							//$this->debugLog(__METHOD__.' chk against mobile_id='.$theMobileRow['mobile_id']);
							if ( Strings::hasher($theAuthHeader->fingerprints,
									$theMobileRow['fingerprint_hash'])
							   )
							{
								//$this->debugLog(__METHOD__.' fmatch?=true');
								// update our mobile circumstances
								$this->mMobileRow = $dbAuth->updateMobileCircumstances(
										$theAuthHeader, $theMobileRow
								);
								// determine if we need to do any further auth checks based
								//   on updated moblie data
								$this->checkMobileCircumstances($theAuthHeader, $theResult);
								break;
							}
							//else $this->debugLog(__METHOD__.' no match against '.$theMobileRow['fingerprint_hash']); //DEBUG
						}
						if ( empty($this->mMobileRow) ) {
							//the device does not match any of our records
							$theResult->is_active = false;
						}
					}//if auth token row !empty
				}
				return $theResult;
			default:
				return $theResult;
		}//end switch
	}
	
}//end class

}//end namespace
