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

}//end class

}//end namespace
