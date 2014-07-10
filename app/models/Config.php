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

namespace BitsTheater\models; 
use BitsTheater\models\KeyValueModel as BaseModel;
use BitsTheater\costumes\ConfigNamespaceInfo;
use BitsTheater\costumes\ConfigSettingInfo;
use \PDOException;
{//namespace begin

class Config extends BaseModel {
	const TABLE_NAME = 'config';
	const MAPKEY_NAME = 'setting';
	public $tnConfig;
	
	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->tnConfig = $this->getTableName();
	}
	
	public function setupModel() {
		parent::setupModel();

		//realized that Config model should have a different mapkey name from what KeyValueModel defined.
		//  existing sites may need to alter their definition so the updated code will work.
		$theSql = 'SELECT '.static::MAPKEY_NAME.' FROM '.$this->tnConfig.' LIMIT 1';
		try {
			$this->query($theSql);
		} catch(PDOException $e) {
			//we need to alter the table
			$theSql = 'ALTER TABLE '.$this->tnConfig.' CHANGE '.KeyValueModel::MAPKEY_NAME.' '.static::MAPKEY_NAME.' CHAR(40)';
			$this->execDML($theSql);
		}
	}
	
	protected function getDefaultData($aScene) {
		if (empty($aScene->auth_registration_url))
			$aScene->auth_register_url = 'account/register';
		if (empty($aScene->auth_login_url))
			$aScene->auth_login_url = 'account/login';
		if (empty($aScene->auth_logout_url))
			$aScene->auth_logout_url = 'account/logout';
		$r = array(
			array('ns' => 'namespace', 'key'=>'site', 'value'=>null, 'default'=>null, ),
			array('ns' => 'site', 'key'=>'mode', 'value'=>$aScene->site_mode, 'default'=>null, ),
			//AUTH
			array('ns' => 'namespace', 'key'=>'auth', 'value'=>null, 'default'=>null, ),
			array('ns' => 'auth', 'key'=>'register_url', 'value'=>$aScene->auth_register_url, 'default'=>'account/register', ),
			array('ns' => 'auth', 'key'=>'login_url', 'value'=>$aScene->auth_login_url, 'default'=>'account/login', ),
			array('ns' => 'auth', 'key'=>'logout_url', 'value'=>$aScene->auth_logout_url, 'default'=>'account/logout', ),
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
	
	/**
	 * Override setting the mapped value so that we can convert "?" to default value.
	 * "/?" would store "?" instead of the default value.
	 */
	public function setMapValue($aKey, $aNewValue) {
		if ($aNewValue=='\?') {
			$aNewValue = '?';
		} else if ($aNewValue=='?' && isset($this->_mapdefault[$aKey])) {
			$aNewValue = $this->_mapdefault[$aKey];
		}
		parent::setMapValue($aKey, $aNewValue);
	}
	
	/**
	 * Get all defined namespaces visible for current login and return them 
	 * as ConfigNamespaceInfo objects.
	 * @return array Returns an array of ConfigNamespaceInfo objects.
	 */
	public function getConfigAreas() {
		$theAreas = array();
		$theNamespaces = $this->getRes('config/namespace');
		foreach ($theNamespaces as $ns=>$nsInfo) {
			$theNsObj = ConfigNamespaceInfo::fromConfigArea($this->director, $ns, $nsInfo);
			if ($theNsObj->isAllowed()) {
				$theAreas[$ns] = $theNsObj;
			}
		}
		return $theAreas;
	}
	
	/**
	 * Get all defined settings for a particular namespace visible for the
	 * current login and return them as ConfigSettingInfo objects. 
	 * @param ConfigNamespaceInfo $aNamespaceInfo
	 * @return array[ConfigSettingInfo] Returns an array of objects.
	 */
	public function getConfigSettings(ConfigNamespaceInfo $aNamespaceInfo) {
		$theSettings = array();
		if ($aNamespaceInfo!=null) {
			$theSettingList = $this->getRes('config/'.$aNamespaceInfo->namespace);
			foreach ($theSettingList as $theSettingName => $theSettingInfo) {
				$o = ConfigSettingInfo::fromConfigRes($aNamespaceInfo, $theSettingName, $theSettingInfo);
				if ($o->isAllowed()) {
					$theSettings[$theSettingName] = $o;
				}
			}
		}
		return $theSettings;
	}
	
	
}//end class

}//end namespace
