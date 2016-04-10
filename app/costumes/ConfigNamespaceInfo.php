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
use BitsTheater\costumes\ABitsCostume as BaseCostume;
use BitsTheater\costumes\ConfigSettingInfo;
use BitsTheater\Director;
use com\blackmoonit\Strings;
use stdClass as StandardClass;
{//namespace begin

/**
 * Configuration Namespace information in class form
 * instead of associative array form.
 */
class ConfigNamespaceInfo extends BaseCostume {
	public $namespace;
	public $label;
	public $desc;
	
	//restricted to only this group id
	public $group_id;
	
	/**
	 * @var array[ConfigSettingInfo->key => ConfigSettingInfo]
	 */
	public $settings_list;
	
	/**
	 * Copies values into matching property names
	 * based on the array keys or object property names.
	 * @param array|object $aThing - array or object to copy from.
	 */
	protected function copyFrom(&$aThing) {
		parent::copyFrom($aThing);
		//TODO see @isNamespaceAllowed(), better way includes override by descendants
		//$this->group_id = ($this->namespace=='auth') ? 1 : null;
		if (!empty($this->settings_list)) {
			$theSettings = $this->settings_list;
			$this->settings_list = array();
			foreach ($theSettings as $theKey => $theSettingInfo) {
				if ($theSettingInfo instanceof ConfigSettingInfo)
					$this->settings_list[$theKey] = $theSettingInfo;
				else {
					$this->settings_list[$theKey] = ConfigSettingInfo::fromArray(
							$this->getDirector(), $theSettingInfo
					);
				}
			}
		}
	}
	
	/**
	 * Given a namespace and namespace data, convert to this class.
	 * @param Director $aDirector - the framework Director object.
	 * @param string $aNamespace - the config namespace.
	 * @param array $aNsData - config namespace as associative array.
	 * @return ConfigNamespaceInfo Returns the created object.
	 */
	static public function fromConfigArea(Director $aDirector, $aNamespace, $aNsData) {
		$o = new ConfigNamespaceInfo($aDirector);
		$o->namespace = $aNamespace;
		//TODO see @isNamespaceAllowed(), better way includes override by descendants
		//$o->group_id = ($aNamespace=='auth') ? 1 : null;
		$o->setDataFrom($aNsData);
		return $o;
	}
	
	public function isNamespaceAllowed() {
		//TODO better auth mechanism needed
		$theDirector = $this->getDirector();
		return ( !isset($this->group_id) || !$theDirector->isInstalled() ||
				(!empty($theDirector->account_info) && in_array($this->group_id, $theDirector->account_info->groups)) );
	}
	
	/**
	 * Return this payload data as a simple class, minus any metadata this class might have.
	 * @return string Return self encoded as a standard class.
	 */
	public function exportData() {
		$o = new StandardClass();
		$o->namespace = $this->namespace;
		$o->label = $this->label;
		$o->desc = $this->desc;
		//NOTE: group_id is manufactured from namespace at this time, no export needed.
		$o->settings_list = array();
		foreach ($this->settings_list as $key => $theSettingInfo) {
			$o->settings_list[] = $theSettingInfo->exportData();
		}
		return $o;
	}
	
}//end class
	
}//end namespace
	
	