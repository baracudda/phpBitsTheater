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

namespace BitsTheater\models\PropCloset; 
use BitsTheater\Model as BaseModel;
use com\blackmoonit\Strings;
{//namespace begin

abstract class AuthBase extends BaseModel {
	const TYPE = 'abstract'; //decendants must override this
	const ALLOW_REGISTRATION = false; //only 1 type allows it, so far
	const KEY_userinfo = 'ticketholder'; //var name in checkTicket($aScene) for username
	const KEY_pwinput = 'pwinput'; //var name in checkTicket($aScene) for pw
	protected $dbPermissions = null;
	
	public function cleanup() {
		if (isset($this->director))
			$this->director->returnProp($this->dbPermissions);
		parent::cleanup();
	}
	
	public function getType() {
		return static::TYPE;
	}
	
	public function isRegistrationAllowed() {
		return static::ALLOW_REGISTRATION;
	}
	
	public function checkTicket($aScene) {
		if ($this->director->isInstalled()) {
			if ($this->director->app_id != \BitsTheater\configs\Settings::getAppId()) {
				$this->ripTicket();
			}
		}
	}
	
	public function ripTicket() {
		$this->clearCsrfTokenCookie();
		unset($this->director->account_info);
		$this->director->resetSession();
	}
	
	public function canRegister($aAcctName, $aEmailAddy) {
		return static::ALLOW_REGISTRATION;
	}
		
	public function registerAccount($aUserData) {
		//overwrite this
	}
	
	public function renderInstallOptions($anActor) {
		return $anActor->renderFragment('auth_'.static::TYPE.'_options');
	}
	
	/**
	 * Check your authority mechanism to determine if a permission is allowed.
	 * @param string $aNamespace - namespace of the permission.
	 * @param string $aPermission - name of the permission.
	 * @param string $acctInfo - (optional) check this account instead of current user.
	 * @return boolean Return TRUE if the permission is granted, FALSE otherwise.
	 */
	abstract public function isPermissionAllowed($aNamespace, $aPermission, $acctInfo=null);
	
	/**
	 * Return the defined permission groups.
	 */
	abstract public function getGroupList();
	
	/**
	 * Checks the given account information for membership.
	 * @param AccountInfoCache $aAccountInfo - the account info to check.
	 * @return boolean Returns FALSE if the account info matches a member account.
	 */
	abstract public function isGuestAccount($aAccountInfo);
	
	/**
	 * Send an HTTPOnly cookie preset with out site information.
	 * @param string $aName - The name of the cookie.
	 * @param string $aValue - (optional) The value of the cookie.
	 *   This value is stored on the client's device; do not store sensitive information.
	 *   Assuming $aName is 'cookiename', this value is retrieved as $_COOKIE['cookiename'].
	 * @param int $aExpireTS - (optional) The Unix timestamp for when the cookie expires.
	 *   e.g.: time()+60*60*24*30 will set the cookie to expire in 30 days.
	 *   If set to 0, or omitted, the cookie will expire at the end of the session
	 *   (when the browser closes).
	 * @param boolean $bDoNotLetJsReadIt - (optional) HTTPOnly flag, defaults TRUE.
	 * @return boolean Returns FALSE if output exists prior to calling this function.
	 *   Returns TRUE if successfull, but does not mean the user accepted the cookie.
	 */
	public function setMySiteCookie($aName, $aValue=null, $aExpireTS=0, $bDoNotLetJsReadIt=true) {
		//RFC 6265: the only proper way to create a "host-only" cookie is to NOT
		//  set the domain attribute. Otherwise, what you end up with is ".domain"
		//  which allows subdomains access.
		$theDomain = null; //$_SERVER['SERVER_NAME'];
		return setcookie($aName, $aValue, $aExpireTS,
				BITS_URL, $theDomain, null, $bDoNotLetJsReadIt
		);
	}

	/**
	 * Retrieve the current CSRF token.
	 * @param string $aCsrfTokenName - the name of the token, in case it's useful.
	 * @return string Returns the token.
	 */
	protected function getMyCsrfToken($aCsrfTokenName) {
		return $this->getDirector()[$aCsrfTokenName];
	}
	
	/**
	 * Set the CSRF token to use.
	 * @param string $aCsrfTokenName - the name of the token, in case it's useful.
	 * @param string $aCsrfToken - (optional) the token to use,
	 *   one will be generated if necessary.
	 * @return string Returns the token to use.
	 */
	protected function setMyCsrfToken($aCsrfTokenName, $aCsrfToken=null) {
		$theCsrfToken = (!empty($aCsrfToken)) ? $aCsrfToken : Strings::createUUID();
		$this->getDirector()[$aCsrfTokenName] = $theCsrfToken;
		return $theCsrfToken;
	}
	
	/**
	 * Removes the current CSRF token in use.
	 * @param string $aCsrfTokenName - the name of the token, in case it's useful.
	 */
	protected function clearMyCsrfToken($aCsrfTokenName) {
		unset($this->getDirector()[$aCsrfTokenName]);
	}
	
	/**
	 * Get the cookie and header names from setup, if possible.
	 * @return array Returns array( $cookieName, $headerName ), if defined and
	 *   array( null, null ) if not.
	 */
	public function getCsrfCookieHeaderNames() {
		if (!$this->getDirector()->isInstalled())
			return array(null, null);
		return array(
				$this->getConfigSetting('site/csrfCookieName'),
				$this->getConfigSetting('site/csrfHeaderName'),
		);
	}

	/**
	 * Set the Cross-Site Request Forgery protection cookie.
	 * @param string $aToken - (optional) set the cookie with this token. If not
	 *   supplied, a random UUID will be created and used.
	 * @return boolean Returns FALSE if output exists prior to calling this function.
	 *   Returns TRUE if successfull, but does not mean the user accepted the cookie.
	 *   Also returns FALSE if there is no cookie name set in Settings.
	 */
	public function setCsrfTokenCookie($aToken=null) {
		list( $theCsrfCookieName, $theCsrfHeaderName) = $this->getCsrfCookieHeaderNames();
		if (!empty($theCsrfCookieName) && !empty($theCsrfHeaderName)) {
			$theCachedToken = $this->getMyCsrfToken($theCsrfHeaderName);
			if (empty($theCachedToken)) {
				$theNewToken = $this->setMyCsrfToken($theCsrfHeaderName, $aToken);
				return $this->setMySiteCookie($theCsrfCookieName, $theNewToken, 0, false);
			}
			return true;
		} else
			return false;
	}
	
	/**
	 * Checks the Cross-Site Request Forgery protection token against the
	 * csrfHeader token sent to us.
	 * @return boolean Return FALSE only if the csrf settings are defined
	 *   and the header token sent to us does not match the one we generated
	 *   for the cookie.
	 */
	public function checkCsrfTokenHeader() {
		list( $theCsrfCookieName, $theCsrfHeaderName) = $this->getCsrfCookieHeaderNames();
		if (!empty($theCsrfCookieName) && !empty($theCsrfHeaderName)) {
			$theCachedToken = $this->getMyCsrfToken($theCsrfHeaderName);
			if (!empty($theCachedToken)) {
				$theVarIndex = Strings::httpHeaderNameToServerKey($theCsrfHeaderName);
				$theHeaderToken = isset($_SERVER[$theVarIndex])
					? $_SERVER[$theVarIndex] : null;
				//$this->debugLog(__METHOD__.' ct='.$theCachedToken.' sk='.$theVarIndex.' ht='.$theHeaderToken.
				//		' ?='.(($theCachedToken === $theHeaderToken)?'true':'false'));
				//$this->debugLog($_SERVER);
				return ($theCachedToken === $theHeaderToken);
			} else
				return false;
		} else
			return true;
	}
	
	/**
	 * Clear out the CSRF protection token cookie and its cached value.
	 * Useful for when a user logs out.
	 */
	public function clearCsrfTokenCookie() {
		list( $theCsrfCookieName, $theCsrfHeaderName) = $this->getCsrfCookieHeaderNames();
		if (!empty($theCsrfCookieName) && !empty($theCsrfHeaderName)) {
			$this->clearMyCsrfToken($theCsrfHeaderName);
			$this->setMySiteCookie($theCsrfCookieName);
		}
	}

	/**
	 * Checks to see if the Cross-Site Request Forgery protection token
	 * has been sent to us via the header.
	 * @return boolean Return FALSE only if the csrf settings are defined
	 *   and the header token was not sent.
	 */
	public function isCsrfTokenHeaderPresent() {
		list( $theCsrfCookieName, $theCsrfHeaderName) = $this->getCsrfCookieHeaderNames();
		if (!empty($theCsrfCookieName) && !empty($theCsrfHeaderName)) {
			$theVarIndex = Strings::httpHeaderNameToServerKey($theCsrfHeaderName);
			return isset($_SERVER[$theVarIndex]);
		} else
			return true;
	}
	
}//end class

}//end namespace
