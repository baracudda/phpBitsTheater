<?php
/*
 * Copyright (C) 2016 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes ;
use com\blackmoonit\Arrays;
use com\blackmoonit\Strings;
{ // begin namespace

/**
 * Useful traits for decoding the Broadway scheme of a HTTP Auth header.
 * @since BitsTheater 3.6.1
 */
trait WornForHttpAuthBroadway
{
	/**
	 * Broadway http auth user auth_id.
	 * @var string
	 */
	public $auth_id = null;
	/**
	 * Broadway HTTP Auth token.
	 * @var string
	 */
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
	 * Fingerprint param for a mobile/OS instance ID.
	 * @var string
	 */
	public $mobile_id;
	/**
	 * Fingerprint param for the hardware ID.
	 * @var string
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
	/**
	 * Circumstance param for the device name.
	 * @var string
	 */
	public $device_name;
	/**
	 * Is the GPS service disabled?
	 * @var boolean
	 */
	public $device_gps_disabled = false;

	/**
	 * Parse the Auth Data out into various properties.
	 * @param string $aAuthData
	 */
	protected function parseAuthHeaderAsAuthBroadway($aAuthData) {
		$theParamsList = Arrays::parseCsvParamsStringToArray($aAuthData);
		if (!empty($theParamsList)) {
			foreach ($theParamsList as $theParams)
				$this->setDataFrom($theParams);
		}
		$dbAuth = $this->getProp('Auth');
		$this->setDataFrom($dbAuth->parseAuthBroadwayFingerprints(
				explode($this->fsep, Strings::stripEnclosure($this->fingerprints,'[',']'))
		));
		$this->setDataFrom($dbAuth->parseAuthBroadwayCircumstances(
				explode($this->csep, Strings::stripEnclosure($this->circumstances,'[',']'))
		));
	}
	
	public function getDeviceName() {
		return $this->device_name;
	}
	
	public function getLatLong() {
		return array($this->device_latitude, $this->device_longitude);
	}
	
	public function getLatitude() {
		return $this->device_latitude;
	}
	
	public function getLongitude() {
		return $this->device_longitude;
	}
	
	public function getTimestamp() {
		return $this->circumstance_ts;
	}
	
} // end trait

} // end namespace
