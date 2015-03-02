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
use BitsTheater\models\PropCloset\KeyValueModel as BaseModel;
use BitsTheater\models\SetupDb as MetaModel;
use BitsTheater\costumes\IFeatureVersioning;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\ConfigNamespaceInfo;
use BitsTheater\costumes\ConfigSettingInfo;
use BitsTheater\costumes\ConfigResEntry;
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
		$theResults = array();
		//get defined (and allowed) config settings
		$theConfigAreas = $this->getConfigAreas();
		foreach ($theConfigAreas as $theNamespaceInfo) {
			$theNamespaceInfo->settings_list = $this->getConfigSettings($theNamespaceInfo);
			if (!empty($theNamespaceInfo->settings_list)) {
				array_push($theResults, array(
						'ns' => 'namespace',
						'key' => $theNamespaceInfo->namespace,
						'value' => null,
						'default' => null, 
				));
				/* @var $theSettingInfo ConfigSettingInfo */
				foreach ($theNamespaceInfo->settings_list as $theSettingName => $theSettingInfo) {
					$theDefaultValue = $theSettingInfo->mSettingInfo->default_value;
					$theVarName = $theNamespaceInfo->namespace.'_'.$theSettingName;
					if (isset($aScene->$theVarName)) {
						$theDefaultValue = $aScene->$theVarName;
					}
					array_push($theResults, array(
							'ns' => $theNamespaceInfo->namespace,
							'key' => $theSettingName,
							'value' => $theDefaultValue,
							'default' => $theDefaultValue, 
					));
						
				}//end foreach setting
			}
		}//end foreach area
		return $theResults;
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
	
	/**
	 * Overrides the default behavior to act on the added map data key "should_insert".
	 * @param array $aMapData - associative array of map data.
	 * @return boolean Returns TRUE if the data should be inserted into the table.
	 */
	protected function shouldInsertMapData($aMapData) {
		return @(parent::shouldInsertMapData($aMapData) || $aMapData['should_insert']);
	}
	
	/**
	 * Overrides default behavior to grab Resource definition if not found
	 * inside the config table.
	 * @param array|string $aNsKey - array with 'ns' and 'key' keys or an "ns/key" string. 
	 * @throws DbException only if the table already exists.
	 * @see \BitsTheater\models\PropCloset\KeyValueModel::getMapData()
	 */
	public function getMapData($aNsKey) {
		$theMapData = parent::getMapData($aNsKey);
		if (!empty($theMapData)) {
			return $theMapData;
		} else {
			$theConfigResEntry = $this->getConfigDefinition($aNsKey);
			return array(
					'namespace' => $theConfigResEntry->config_namespace(),
					static::MAPKEY_NAME => $theConfigResEntry->config_setting(),
					'value' => $theConfigResEntry->default_value,
					'val_def' => $theConfigResEntry->default_value,
					'should_insert' => $theConfigResEntry->config_is_allowed(),
			);
		}
	}
	
	/**
	 * Set up a ns/key for the first time.
	 * @param array|string $aMapInfo - array with 'ns' and 'key' keys or an "ns/key" string;
	 * typcially, this should be array[ns, key, value, default].
	 * @throws DbException only if the table already exists.
	 * @return boolean Returns TRUE if inserted, FALSE if already exists.
	 */
	public function defineMapValue($aMapInfo) {
		if (parent::defineMapValue($aMapInfo)) {
			$this->debugLog(__FUNCTION__.' inserted config: '.$this->implodeKeyName($aMapInfo));
		} else {
			$this->debugLog(__FUNCTION__.' config already exists: '.$this->implodeKeyName($aMapInfo));
		}
	}
	
	/**
	 * Returns the static definition of a setting.<br>
	 * NOTE: $aConfigSetting param may be left out and the $aNamespace 
	 * param could be the combined form of "ns/setting" instead. Also, since
	 * other function params accept the array['ns' & 'key'] form for the first
	 * param, this function does as well.
	 * @param string|array $aNamespace - the namespace of the setting (e.g. "auth" or "site").
	 * @param string $aConfigSetting - the setting name (e.g. "cookie_shelf_life" or "mode").
	 * @return ConfigResEntry Returns the config resource entry found or NULL if not found.
	 */
	public function getConfigDefinition($aNamespace, $aConfigSetting=null) {
		if (empty($aConfigSetting)) {
			$theNsKey = $this->splitKeyName($aNamespace);
			$aNamespace = $theNsKey['ns'];
			$aConfigSetting = $theNsKey['key'];
		}
		if (!empty($aNamespace) && !empty($aConfigSetting)) {
			$res = $this->director->getRes('config/'.$aNamespace);
			if (array_key_exists($aConfigSetting, $res))
				return $res[$aConfigSetting];
		}
		return null;
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
