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

namespace com\blackmoonit\bits_theater\app\model; 
use com\blackmoonit\bits_theater\app\model\KeyValueModel;
{//namespace begin

class Config extends KeyValueModel {
	const TABLE_NAME = 'config';
	
	public function setupModel() {
		return parent::setupModel();
	}
	
	protected function getDefaultData($aScene) {
		if (empty($aScene->auth_registration_url))
			$aScene->auth_register_url = BITS_URL.'/account/register';
		if (empty($aScene->auth_login_url))
			$aScene->auth_login_url = BITS_URL.'/account/login';
		if (empty($aScene->auth_logout_url))
			$aScene->auth_logout_url = BITS_URL.'/account/logout';
		$r = array(
			//AUTH
			array('ns' => 'namespace', 'key'=>'auth', 'value'=>null, 'default'=>null, ),
			array('ns' => 'auth', 'key'=>'register_url', 'value'=>$aScene->auth_register_url, 'default'=>BITS_URL.'/account/register', ),
			array('ns' => 'auth', 'key'=>'login_url', 'value'=>$aScene->auth_login_url, 'default'=>BITS_URL.'/account/login', ),
			array('ns' => 'auth', 'key'=>'logout_url', 'value'=>$aScene->auth_logout_url, 'default'=>BITS_URL.'/account/logout', ),

		);
		return $r;
	}

	public function getConfigLabel($aNamespace, $aKey) {
		$res =& $this->director->getRes('Config/'.$aNamespace);
		return $res[$aKey]['label'];
	}

	public function getConfigDesc($aNamespace, $aKey) {
		$res =& $this->director->getRes('Config/'.$aNamespace);
		return $res[$aKey]['desc'];
	}
	
	public function getConfigValue($aNamespace, $aKey) {
		return $this[$aNamespace.'/'.$aKey];
	}
	
	public function setConfigValue($aNamespace, $aKey, $aValue) {
		$this[$aNamespace.'/'.$aKey] = $aValue;
	}
	
}//end class

}//end namespace
