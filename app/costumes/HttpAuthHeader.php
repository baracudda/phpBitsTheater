<?php
/*
 * Copyright (C) 2014 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes;
use BitsTheater\costumes\ASimpleCostume as BaseCostume;
use com\blackmoonit\Arrays;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * HTTP Authorization headers have different members based on the scheme
 * being utilized, but any common characterizitics/methods would go here.
 */
class HttpAuthHeader extends BaseCostume {
	public $auth_header = null;
	public $auth_scheme = null;
	
	/**
	 * Basic http auth username.
	 * @var string
	 */
	public $username = null;
	/**
	 * Basic http auth pw_input.
	 * @var string
	 */
	public $pw_input = null;
	
	/**
	 * Broadway http auth user auth_id.
	 * @var string
	 */
	public $auth_id = null;
	/**
	 * Broadway http auth device's fingerprints which are 
	 * non-volatile between API calls.
	 * @var string
	 */
	public $fingerprints = null;
	/**
	 * Broadway http auth device's circumstances which may be 
	 * volatile between API calls. Contains items like GPS 
	 * location and current timestamp.
	 * @var string[]
	 */
	public $circumstances = null;
	/**
	 * Broadway http auth token.
	 * @var string
	 */
	public $auth_token = null;
	
	
	public function __construct($aHttpAuthHeader) {
		if (!empty($aHttpAuthHeader)) {
			$this->auth_header = $aHttpAuthHeader;
		} else if (!empty($_SERVER['HTTP_AUTHORIZATION'])) {
			$this->auth_header = $_SERVER['HTTP_AUTHORIZATION'];
			unset($_SERVER['HTTP_AUTHORIZATION']);
		}
		$this->auth_scheme = strstr($this->auth_header, ' ', true);
		$this->parseAuthData();
	}
	
	protected function parseCsvString($aCsvString) {
		$theResult = array();
		$firstPass = Arrays::parse_csv_to_array($aCsvString);
		if (!empty($firstPass[0])) {
			$theResult = Arrays::cnvKeyValuePairsToAssociativeArray($firstPass[0]);
		}
		return $theResult;
	}
	
	protected function parseAuthData() {
		$theAuthData = base64_decode(substr($this->auth_header, strlen($this->auth_scheme)+1));
		switch ($this->auth_scheme) {
			case 'Basic':
				list($this->account_name, $this->pw_input) = explode(':', $theAuthData);
				break;
			case 'Broadway':
				$this->setDataFrom($this->parseCsvString($theAuthData));
				
				//fingerprints is itself an array of string
				//  however, we do not wish to parse it, just use "as is"
				
				//circumstances is itself an array of strings
				$this->circumstances = explode(', ', Strings::stripEnclosure($this->circumstances,'[',']'));
				//Strings::debugLog(__METHOD__.' self='.Strings::debugStr($this));
				break;
		}
	}
	
	public function getDeviceName() {
		if (!empty($this->circumstances) && !empty($this->circumstances[3]))
			return $this->circumstances[3];
	}
	
	public function getLatLong() {
		if (!empty($this->circumstances))
			return array($this->circumstances[1], $this->circumstances[2]);
	}
	
	public function getTimestamp() {
		if (!empty($this->circumstances) && !empty($this->circumstances[0]))
			return $this->circumstances[0];
	}
	
}//end class

}//end namespace
