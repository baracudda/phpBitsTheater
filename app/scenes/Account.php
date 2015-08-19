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

namespace BitsTheater\scenes; 
use BitsTheater\Scene as MyScene;
use ReflectionClass;
{//namespace begin

class Account extends MyScene {
	protected $KEY_userinfo = '';
	protected $KEY_pwinput = '';
	protected $KEY_cookie = '';

	protected function setupDefaults() {
		parent::setupDefaults();
		$dbAuth = $this->getProp('Auth');
		$theMetaAuth = new ReflectionClass($dbAuth);
		if ($theMetaAuth->hasConstant('KEY_userinfo'))
			$this->KEY_userinfo = $theMetaAuth->getConstant('KEY_userinfo');
		if ($theMetaAuth->hasConstant('KEY_pwinput'))
			$this->KEY_pwinput = $theMetaAuth->getConstant('KEY_pwinput');
		if ($theMetaAuth->hasConstant('KEY_cookie'))
			$this->KEY_cookie = $theMetaAuth->getConstant('KEY_cookie');
		$theMetaAuth = null;
		$this->returnProp($dbAuth);
	}
	
	public function getUsernameKey() {
		return $this->KEY_userinfo;
	}
	
	public function getUsername() {
		$theKey = $this->getUsernameKey();
		return $this->$theKey;
	}
	
	public function getPwInputKey() {
		return $this->KEY_pwinput;
	}
	
	public function getPwInput() {
		$theKey = $this->getPwInputKey();
		return $this->$theKey;
	}
	
	public function getUseCookieKey() {
		return $this->KEY_cookie;
	}
	
	public function getUseCookie() {
		$theKey = $this->getUseCookieKey();
		return $this->$theKey;
	}
	
	/**
	 * API fingerprints from mobile device. Recomended that
	 * your website mixes their order up, at the very least.
	 * @param string[] $aFingerprints - string array of device info.
	 * @return string[] Return a keyed array of device info.
	 */
	public function cnvFingerprints2KeyedArray($aFingerprints) {
		if (!empty($aFingerprints)) {
			return array(
					'device_id' => $aFingerprints[0],
					'app_version' => $aFingerprints[1],
					'device_memory' => (is_numeric($aFingerprints[2]) ? $aFingerprints[2] : null),
					'locale' => $aFingerprints[3],
					'app_signature' => $aFingerprints[4],
			);
		} else return array();
	}

	/**
	 * API circumstances from mobile device. Recommended that
	 * your website mixes their order up, at the very least.
	 * @param string[] $aCircumstances - string array of device meta,
	 * such as current GPS, user device name setting, current timestamp, etc.
	 * @return string[] Return a keyed array of device meta.
	 */
	public function cnvCircumstances2KeyedArray($aCircumstances) {
		if (!empty($aCircumstances)) {
			return array(
					'now_ts' => $aCircumstances[0],
					'latitude' => (is_numeric($aCircumstances[1]) ? $aCircumstances[1] : null),
					'longitude' => (is_numeric($aCircumstances[2]) ? $aCircumstances[2] : null),
					'device_name' => $aCircumstances[3],
			);
		} else return array();
	}

}//end class

}//end namespace
