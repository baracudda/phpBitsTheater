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
		unset($this->director->account_info);
		$this->clearCsrfTokenCookie();
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
	 * @return boolean Returns FALSE if output exists prior to calling this function.
	 *   Returns TRUE if successfull, but does not mean the user accepted the cookie.
	 */
	public function setMySiteCookie($aName, $aValue=null, $aExpireTS=0) {
		//RFC 6265: the only proper way to create a "host-only" cookie is to NOT
		//  set the domain attribute. Otherwise, what you end up with is ".domain"
		//  which allows subdomains access.
		$theDomain = null; //$_SERVER['SERVER_NAME'];
		return setcookie($aName, $aValue, $aExpireTS,
				BITS_URL, $theDomain, null, true
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
		$theCsrfCookieName = $this->getConfigSetting('site/csrfCookieName');
		$theCsrfHeaderName = $this->getConfigSetting('site/csrfHeaderName');
		if (!empty($theCsrfCookieName) && !empty($theCsrfHeaderName)) {
			$theCsrfToken = (!empty($aToken)) ? $aToken : Strings::createUUID();
			$theCachedToken = $this->getDirector()[$theCsrfHeaderName];
			if (empty($theCachedToken)) {
				if ($this->setMySiteCookie($theCsrfCookieName, $theCsrfToken))
					$this->getDirector()[$theCsrfHeaderName] = $theCsrfToken;
			}
			return true;
		}
	}
	
	/**
	 * Checks the Cross-Site Request Forgery protection token against the
	 * csrfHeader token sent to us.
	 * @return boolean Return FALSE only if the csrf settings are defined
	 *   and the header token sent to us does not match the one we generated
	 *   for the cookie.
	 */
	public function checkCsrfTokenHeader() {
		$theCsrfCookieName = $this->getConfigSetting('site/csrfCookieName');
		$theCsrfHeaderName = $this->getConfigSetting('site/csrfHeaderName');
		if (!empty($theCsrfCookieName) && !empty($theCsrfHeaderName)) {
			$theCachedToken = $this->getDirector()[$theCsrfHeaderName];
			if (!empty($theCachedToken)) {
				$theVarIndex = 'HTTP_'.strtoupper($theCsrfHeaderName);
				$theHeaderToken = isset($_SERVER[$theVarIndex])
					? $_SERVER[$theVarIndex] : null;
				//$this->debugLog(__METHOD__.' ct='.$theCachedToken.' ht='.$theHeaderToken.
				//		' ?='.(($theCachedToken === $theHeaderToken)?'true':'false'));
				//$this->debugLog($_SERVER);
				return ($theCachedToken === $theHeaderToken);
			}
		}
		return true;
	}
	
	/**
	 * Clear out the CSRF protection token cookie and its cached value.
	 * Useful for when a user logs out.
	 */
	public function clearCsrfTokenCookie() {
		$theCsrfCookieName = $this->getConfigSetting('site/csrfCookieName');
		$theCsrfHeaderName = $this->getConfigSetting('site/csrfHeaderName');
		if (!empty($theCsrfCookieName) && !empty($theCsrfHeaderName)) {
			$this->getDirector()[$theCsrfHeaderName] = null;
			$this->setMySiteCookie($theCsrfCookieName);
		}
	}

}//end class

}//end namespace
