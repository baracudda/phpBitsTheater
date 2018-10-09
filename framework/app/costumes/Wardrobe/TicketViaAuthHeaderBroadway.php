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
use com\blackmoonit\Arrays;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * Class used to help manage logging in via HTTP "Broadway" Authorization Header.
 * @since BitsTheater v4.1.0
 */
class TicketViaAuthHeaderBroadway extends BaseCostume
{
	/** @var string The HTTP Authorization type. */
	const AUTH_SCHEME = 'Broadway';
	/** @var string The raw HTTP Auth header. */
	protected $auth_header = null;
	/** @var string The scheme name for the HTTP Auth header. */
	protected $auth_scheme = null;
	/** @var string Broadway http auth user auth_id. */
	public $auth_id = null;
	/** @var string Broadway HTTP Auth token. */
	public $auth_token = null;
	/**
	 * Broadway http auth device's fingerprints which are
	 * non-volatile between API calls.
	 * @var string
	 */
	public $fingerprints = null;
	/**
	 * Fingerprints may be defined with a unique separator.
	 * Defaults to ", ".
	 * @var string
	 */
	public $fsep = ', ';
	/**
	 * This mobile_id is NOT RELATED to the framework's `mobile_id`
	 * primary key for the webapp_auth_mobile table. It is, however,
	 * what the framework considers the "device_id" and will be stored as that.
	 * @var string Fingerprint param for a mobile/OS instance ID.
	 */
	public $mobile_id;
	/**
	 * This device_id is NOT RELATED to the framework's `device_id`, but it
	 * is related to the framework's "hardward_id".
	 * @var string Fingerprint param for the hardware ID.
	 */
	public $device_id;
	/**
	 * Broadway HTTP Auth device's circumstances which may be
	 * volatile between API calls. Contains items like GPS
	 * location and current timestamp.
	 * @var string[]
	 */
	public $circumstances = null;
	/**
	 * Circumstances may be defined with a unique separator.
	 * Defaults to ", ".
	 * @var string
	 */
	public $csep = ', ';
	/**
	 * Circumstance param for timestamp of circumstance data.
	 * @var string
	 */
	public $circumstance_ts;
	/**
	 * Circumstance param for the GPS latitude.
	 * Stored here as a decimal string rather than a double.
	 * @var string
	 */
	public $device_latitude;
	/**
	 * Circumstance param for the GPS longitude.
	 * Stored here as a decimal string rather than a double.
	 * @var string
	 */
	public $device_longitude;
	/** @return string[] Returns [lat,long]. */
	public function getLatLong()
	{ return array($this->device_latitude, $this->device_longitude); }
	/** @var string Circumstance param for the device name. */
	public $device_name;
	/** @var boolean Is the GPS service disabled? */
	public $device_gps_disabled = false;
	
	/**
	 * Get the auth header data we need to process. Sometimes it is not in
	 * the actual HTTP headers.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return string Returns the header data to parse.
	 */
	protected function getAuthHeaderData( Scene $aScene )
	{ return Strings::getHttpHeaderValue('Authorization'); }
	
	/**
	 * Once we have the auth header data, parse it.
	 * @param string $aAuthHeaderData - the auth header data to parse.
	 */
	protected function parseAuthHeader( $aAuthHeaderData )
	{
		if ( !empty($aAuthHeaderData) ) {
			$theParamsList = Arrays::parseCsvParamsStringToArray($aAuthHeaderData);
			if ( !empty($theParamsList) ) {
				foreach ($theParamsList as $theParams) {
					$this->setDataFrom($theParams);
				}
			}
		}
		if ( !empty($this->fingerprints) ) {
			$theDataStr = Strings::stripEnclosure($this->fingerprints,'[',']');
			$this->setDataFrom($this->parseAuthBroadwayFingerprints(
					explode($this->fsep, $theDataStr)
			));
		}
		if (!empty($this->circumstances) ) {
			$theDataStr = Strings::stripEnclosure($this->circumstances,'[',']');
			$this->setDataFrom($this->parseAuthBroadwayCircumstances(
					explode($this->csep, $theDataStr)
			));
		}
		//make the reported mobile ID the ticket name so lockout tokens are based on device
		$this->ticket_name = $this->mobile_id;
		//NOTE: this mobile_id is NOT RELATED to the framework's `mobile_id`
		//  primary key for the webapp_auth_mobile table. It is, however,
		//  what the framework considers the "device_id" and will be stored as
		//  that. This object's device_id will become a "hardware_id".
		//  Apologies for naming convention mess, legacy code is fun sometimes.
	}
	
	/**
	 * Tasks to complete before we even check to see if this ticket is for
	 * this particular venue.
	 * @param Scene $aScene - var container object which may have auth info.
	 * @return $this Returns $this for chaining.
	 */
	protected function onBeforeCheckTicket( Scene $aScene )
	{
		$this->auth_header = $this->getAuthHeaderData($aScene);
		if ( !empty($this->auth_header) ) {
			$this->auth_scheme = strstr($this->auth_header, ' ', true);
			if ( $this->auth_scheme == static::AUTH_SCHEME ) {
				//decode the header data
				$this->parseAuthHeader(base64_decode(
					substr($this->auth_header, strlen($this->auth_scheme)+1)
				));
			}
		}
		return $this;
	}
	
	/**
	 * API fingerprints from mobile device. Recommended that
	 * your website mixes their order up, at the very least.
	 * @param string[] $aFingerprints - string array of device info.
	 * @return string[] Return a keyed array of device info.
	 * @since BitsTheater 3.6.1
	 */
	protected function parseAuthBroadwayFingerprints($aFingerprints) {
		if ( !empty($aFingerprints) ) {
			return array(
					'app_signature' => $aFingerprints[0],
					'mobile_id' => $aFingerprints[1],
					'device_id' => $aFingerprints[2],
					'device_locale' => $aFingerprints[3],
					'device_memory' => (is_numeric($aFingerprints[4]) ? $aFingerprints[4] : null),
			);
		} else return array();
	}
	
	/**
	 * API circumstances from mobile device. Recommended that
	 * your website mixes their order up, at the very least.
	 * @param string[] $aCircumstances - string array of device meta,
	 * such as current GPS, user device name setting, current timestamp, etc.
	 * @return string[] Return a keyed array of device meta.
	 * @since BitsTheater 3.6.1
	 */
	protected function parseAuthBroadwayCircumstances($aCircumstances) {
		if ( !empty($aCircumstances) ) {
			return array(
					'circumstance_ts' => $aCircumstances[0],
					'device_name' => $aCircumstances[1],
					'device_latitude' => (is_float($aCircumstances[2]) ? $aCircumstances[2] : null),
					'device_longitude' => (is_float($aCircumstances[3]) ? $aCircumstances[3] : null),
			);
		} else return array();
	}
	
	/** @var array The mobile record, if we used Broadway auth. */
	public $mMobileRow;
	
	/**
	 * Descendants may wish to further scrutinize header information before allowing access.
	 * @param array $aMobileRow - the mobile row data.
	 * @param AccountInfoCache $aUserAccount - the user account data.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 */
	protected function checkMobileCircumstances(AccountInfoCache $aAuthAccount)
	{
		//barring checking circumstances like if the GPS is outside
		//  pre-determined bounds, we are authenticated!
	}
	
	/**
	 * Data that should get stored in the database is exported here.
	 * @param array $aMobileRow - the mobile row data.
	 * @return array The array of data as the db model expects it.
	 */
	protected function getCircumstanceDataToStore( $aMobileRow )
	{
		if ( empty($aMobileRow) ) return; //trivial
		$theResult = array();
		if ( is_numeric($this->device_latitude) && is_numeric($this->device_longitude) ) {
			$theResult['latitude'] = $this->device_latitude;
			$theResult['longitude'] = $this->device_longitude;
		}
		if ( !empty($this->device_name) &&
				( empty($aMobileRow['device_name']) ||
						strcmp($aMobileRow['device_name'], $this->device_name) != 0)
			)
		{
			$theResult['device_name'] = $this->device_name;
		}
		if ( !empty($theResult) ) {
			$theResult['mobile_id'] = $aMobileRow['mobile_id'];
		}
		return $theResult;
	}

	/**
	 * See if we can map the account to a mobile record.
	 * @param AccountInfoCache $aAcctInfo - the account being mapped.
	 * @return $this Returns $this for chaining.
	 */
	protected function determineMobileRow( AccountInfoCache $aAcctInfo )
	{
		$dbAuth = $this->getMyModel();
		//do we have a cache of which mobile device it is?
		if ( !empty($this->getDirector()[$dbAuth::KEY_MobileInfo]) ) {
			$this->mMobileRow = $this->getDirector()[$dbAuth::KEY_MobileInfo];
			unset($this->getDirector()[$dbAuth::KEY_MobileInfo]);
		}
		else {
			$theAuthMobileRows = $dbAuth->getAuthMobilesByAuthId($aAcctInfo->auth_id);
			//$this->logStuff(__METHOD__, ' fp=', $this->fingerprints); //DEBUG
			//$this->logStuff(__METHOD__, ' list=', $theAuthMobileRows); //DEBUG
			foreach ($theAuthMobileRows as $theMobileRow) {
				//$this->debugLog(__METHOD__.' chk against mobile_id='.$theMobileRow['mobile_id']); //DEBUG
				if ( Strings::hasher($this->fingerprints, $theMobileRow['fingerprint_hash']) ) {
					//$this->debugLog(__METHOD__.' fmatch?=true'); //DEBUG
					$this->mMobileRow = $theMobileRow;
					break;
				}
				//else $this->debugLog(__METHOD__.' no match against '.$theMobileRow['fingerprint_hash']); //DEBUG
			}
		}
		if ( !empty($this->mMobileRow) ) {
			// determine if we need to do any further auth checks based
			//   on updated moblie data
			$this->checkMobileCircumstances($aAcctInfo);
		} else {
			//the device does not match any of our records
			$aAcctInfo->is_active = false;
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
		return !empty($this->ticket_name) &&
				!empty($this->auth_id) && !empty($this->auth_token) ;
	}

	/**
	 * The HTTP "Authorization" Header may contain authorization information.
	 * @param Scene $aScene - var container object for auth info.
	 * @return AccountInfoCache|NULL Returns the account info to use.
	 *   If the account info has <code>is_active=false</code>, auth failed.
	 *   NULL means no ticket information was found to even attempt auth.
	 */
	protected function processTicket(Scene $aScene)
	{
		$theResult = null;
		$dbAuth = $this->getMyModel();
		$theAuthRow = $dbAuth->getAuthByAuthId($this->auth_id);
		if ( !empty($theAuthRow) ) {
			$theResult = $dbAuth->createAccountInfoObj($theAuthRow);
		}
		if ( !empty($theResult) && $theResult->is_active ) {
			//remove any stale mobile tokens
			$dbAuth->removeStaleMobileAuthTokens();
			//now check to see if we still have a mobile auth token
			$theAuthTokenRow = $dbAuth->getAuthTokenRow(
					$this->auth_id, $this->auth_token
			);
			if ( !empty($theAuthTokenRow) ) {
				$this->determineMobileRow($theResult);
			}
			else {
				//invalid auth token, disable account for current script run
				$theResult->is_active = false;
			}
		}
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
		//$this->logStuff(__METHOD__, ' DEBUG ', $this);
		$dbAuth = $this->getMyModel();
		//save mobile info in short term cache
		$this->getDirector()[$dbAuth::KEY_MobileInfo] = $this->mMobileRow;
		//update our mobile circumstances
		if ( !empty($this->mMobileRow) ) {
			$dbAuth->updateMobileCircumstances(
					$this->getCircumstanceDataToStore($this->mMobileRow)
			);
		}
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
		$dbAuth = $this->getMyModel();
		if ( !empty($this->getDirector()[$dbAuth::KEY_MobileInfo]) ) {
			$theMobileRow = $this->getDirector()[$dbAuth::KEY_MobileInfo];
			//remove mobile info from short term cache
			unset($this->getDirector()[$dbAuth::KEY_MobileInfo]);
			if ( !empty($theMobileRow) ) {
				//remove the mobile auth token
				$theTokenPattern = $dbAuth::TOKEN_PREFIX_MOBILE .
						$theMobileRow['mobile_id'] . ':%';
				$dbAuth->removeTokensFor($aAcctInfo->auth_id,
						$aAcctInfo->account_id, $theTokenPattern
				);
			}
		}
		return parent::ripTicket($aAcctInfo);
	}
	
	/**
	 * Get my mobile data, if known.
	 * @return array Returns the mobile data as an array.
	 */
	public function getMyMobileRow()
	{
		$dbAuth = $this->getMyModel();
		if ( !empty($this->getDirector()[$dbAuth::KEY_MobileInfo]) ) {
			return $this->getDirector()[$dbAuth::KEY_MobileInfo];
		}
	}
	
}//end class

}//end namespace
