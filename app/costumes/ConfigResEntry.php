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
use BitsTheater\costumes\EnumResEntry as BaseCostume;
{//namespace begin

/**
 * Config resources have UI label, descriptive and input elements.
 * Helper class for Resource-based config work.
 */
class ConfigResEntry extends BaseCostume {
	protected $configNamespace = null;
	protected $is_editable = true; //false prevents its display on website config page
	public $input_type = null;
	public $input_enums = null;
	public $default_value = null;
	
	public function __construct($aConfigNamespace=null, $aConfigSetting=null, $aLabel=null, $aDesc=null) {
		parent::__construct($aConfigSetting, $aLabel, $aDesc);
		$this->config_namespace($aConfigNamespace);
	}
	
	public function config_namespace($aConfigNamespace=null) {
		if (!empty($aConfigNamespace))
			$this->configNamespace = $aConfigNamespace;
		return $this->configNamespace;
	}
	
	public function config_setting($aConfigSetting=null) {
		if (!empty($aConfigSetting))
			$this->value = $aConfigSetting;
		return $this->value;
	}
	
	public function config_is_allowed($bIsAllowed=null) {
		if (isset($bIsAllowed))
			$this->is_editable = $bIsAllowed;
		return $this->is_editable;
	}
	
	public function setInputType($aInputType) {
		$this->input_type = $aInputType;
		return $this; //for chaining
	}
	
	public function setInputEnums($aInputEnums) {
		if (!empty($aInputEnums)) {
			$this->input_enums = array();
			foreach ($aInputEnums as $key => $val) {
				if ($val instanceof EnumResEntry) {
					$this->input_enums[$key] = $val;
				} else if (is_string($val)) {
					$this->input_enums[$key] = new EnumResEntry($key, $val);
				} else if (is_array($val)) {
					$this->input_enums[$key] = new EnumResEntry($key, $val['label'], (isset($val['desc'])?$val['desc']:null) );
				}
			}
		}
		return $this; //for chaining
	}
	
	public function setInput($aInput) {
		if (is_string($aInput)) {
			$this->setInputType($aInput);
		} else if (is_array($aInput)) {
			if (isset($aInput['type']))
				$this->setInputType($aInput['type']);
			
			if (isset($aInput['default']))
				$this->default_value = $aInput['default'];
			
			if (isset($aInput['is_editable']))
				$this->config_is_allowed(!empty($aInput['is_editable']));
			
			if (isset($aInput['enums']))
				$this->setInputEnums($aInput['enums']);
			else if (isset($aInput['values']))
				$this->setInputEnums($aInput['values']);
		}
		return $this; //for chaining
	}

}//end class

}//end namespace
