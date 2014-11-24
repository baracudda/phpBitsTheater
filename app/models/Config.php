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
use BitsTheater\costumes\IFeatureVersioning;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\models\SetupDb as MetaModel;
use BitsTheater\costumes\ConfigNamespaceInfo;
use BitsTheater\costumes\ConfigSettingInfo;
use com\blackmoonit\Arrays;
use com\blackmoonit\Strings;
use \PDO;
use \PDOException;
{//namespace begin

class Config extends BaseModel implements IFeatureVersioning {
	/**
	 * Used by meta data mechanism to keep the database up-to-date with the code.
	 * A non-NULL string value here means alter-db-schema needs to be managed.
	 * @var string
	 */
	const FEATURE_ID = 'BitsTheater/config';
	const FEATURE_VERSION_SEQ = 2; //always ++ when making db schema changes

	const TABLE_NAME = 'config';
	const MAPKEY_NAME = 'setting';
	public $tnConfig;
	
	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->tnConfig = $this->getTableName();
	}
	
	/**
	 * When tables are created, default data may be needed in them.
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
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

	/**
	 * Returns the current feature metadata for the given feature ID.
	 * @param string $aFeatureId - the feature ID needing its current metadata.
	 * @return array Current feature metadata.
	 */
	public function getCurrentFeatureVersion($aFeatureId=null) {
		return array(
				'feature_id' => self::FEATURE_ID,
				'model_class' => $this->mySimpleClassName,
				'version_seq' => self::FEATURE_VERSION_SEQ,
		);
	}
	
	/**
	 * Meta data may be necessary to make upgrades-in-place easier. Check for
	 * existing meta data and define if not present.
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
	public function setupFeatureVersion($aScene) {
		/* @var $dbMeta MetaModel */
		$dbMeta = $this->getProp('SetupDb');
		$theFeatureData = $dbMeta->getFeature(self::FEATURE_ID);
		if (empty($theFeatureData)) {
			$theFeatureData = $this->getCurrentFeatureVersion();
			//realized that Config model should have a different mapkey name from what KeyValueModel defined.
			//  existing sites may need to alter their definition so the updated code will work.
			try {
				$theSql = 'SELECT '.static::MAPKEY_NAME.' FROM '.$this->tnConfig.' LIMIT 1';
				$this->query($theSql);
			} catch(PDOException $e) {
				//orig version
				$theFeatureData['version_seq'] = 1;
			}
			$dbMeta->insertFeature($theFeatureData);
		}
	}
	
	/**
	 * Check current feature version and compare it to the
	 * current version, upgrading the db schema as needed.
	 * @param array $aFeatureMetaData - the models current feature metadata.
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
	public function upgradeFeatureVersion($aFeatureMetaData, $aScene) {
		$theSeq = $aFeatureMetaData['version_seq'];
		switch (true) {
			//cases should always be lo->hi, never use break; so all changes are done in order.
			case ($theSeq<=1):
				//NOTE: this change needed to happen manually since you cannot login to even get to the update page as the
				//  login mechanism relies strongly on reading several config settings.
				//realized that Config model should have a different mapkey name from what KeyValueModel defined.
				$theSql = 'ALTER TABLE '.$this->tnConfig.' CHANGE '.KeyValueModel::MAPKEY_NAME.' '.static::MAPKEY_NAME.' CHAR(40)';
				$this->execDML($theSql);
		}
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
