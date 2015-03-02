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
use BitsTheater\Director;
use com\blackmoonit\Strings;
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
	
	//useful for displaying settings based on namespace
	public $settings_list;
	
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
		$o->group_id = ($aNamespace=='auth') ? 1 : null;
		$o->setDataFrom($aNsData);
		return $o;
	}
	
	public function isAllowed() {
		//TODO better auth mechanism needed
		return ( !isset($this->group_id) || !$this->_director->isInstalled() ||
				(!empty($this->_director->account_info) && in_array($this->group_id,$this->_director->account_info->groups)) );
	}
	
}//end class
	
}//end namespace
	
	