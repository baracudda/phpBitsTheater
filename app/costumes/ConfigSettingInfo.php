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
use BitsTheater\costumes\ConfigNamespaceInfo;
use BitsTheater\Director;
use com\blackmoonit\Strings;
use com\blackmoonit\Widgets;
use com\blackmoonit\Arrays;
use BitsTheater\models\Config;
{//namespace begin

/**
 * Configuration setting information in class form
 * instead of associative array form.
 */
class ConfigSettingInfo extends BaseCostume {
	const INPUT_STRING = 'string';
	const INPUT_BOOLEAN = 'boolean';
	const INPUT_DROPDOWN = 'dropdown';
	
	public $config_namespace_info;
	public $config_key;
	
	//from config table
	public $ns;
	public $key;
	public $value;
	public $default_value;
	
	//from config res arrays
	public $label;
	public $desc;
	public $input;
	public $dropdown_values;
	
	/**
	 * Magic PHP method to limit what var_dump() shows.
	 */
	public function __debugInfo() {
		$vars = parent::__debugInfo();
		unset($vars['config_namespace_info']);
		unset($vars['ns']);
		unset($vars['key']);
		return $vars;
	}
	
	/**
	 * Given namespace data and setting data, convert to this class.
	 * @param ConfigNamespaceInfo $aNamespaceInfo - instance of namespace info.
	 * @param string $aSettingName - the setting name (machine name, not human label).
	 * @param array $aSettingInfo - human UI / widget info loaded from the getRes() method.
	 * @return ConfigSettingInfo Returns the created object.
	 */
	static public function fromConfigRes(ConfigNamespaceInfo $aNamespaceInfo, $aSettingName, $aSettingInfo) {
		if ($aNamespaceInfo!=null) {
			if (is_array($aSettingInfo))
				$o = static::fromArray($aNamespaceInfo->getDirector(),$aSettingInfo);
			else {
				$o = new ConfigSettingInfo($aNamespaceInfo->getDirector());
				$o->label = $aSettingInfo->label;
				$o->desc = $aSettingInfo->desc;
				$o->input = $aSettingInfo->input_type;
				$o->dropdown_values = $aSettingInfo->input_enums;
				$o->default_value = $aSettingInfo->default_value;
			}
			$o->config_namespace_info = $aNamespaceInfo;
			$o->ns = $aNamespaceInfo->namespace;
			$o->key = $aSettingName;
			$o->config_key = $o->ns.'/'.$o->key;
			/* @var $dbConfig Config */
			$dbConfig = $o->getDirector()->getProp('config');
			$o->value = $dbConfig->getMapValue($o->config_key);
			if (!isset($o->default_value)) {
				$o->default_value = $dbConfig->getMapDefault($o->config_key);
			}
			return $o;
		}
	}

	/**
	 * See if this setting is restricted somehow (future use, no restrictions yet).
	 * @return boolean
	 */
	public function isAllowed() {
		return true;
	}
	
	/**
	 * Get this setting's widget name such that it would be unique.
	 * @return string Returns this setting's widget name.
	 */
	public function getWidgetName() {
		return $this->ns.'__'.$this->key;
	}
	
	/**
	 * Get the HTML string to use as a widget for this setting.
	 * @return string Returns the HTML to use as a setting widget.
	 */
	public function getInputWidget() {
		$theWidgetName = $this->getWidgetName();
		$theValue = (isset($this->value)) ? $this->value : $this->default_value;
		switch ($this->input) {
			case self::INPUT_STRING:
				return Widgets::createTextBox($theWidgetName,$theValue);
			case self::INPUT_BOOLEAN:
				return Widgets::createCheckBox($theWidgetName,$theValue,!empty($theValue));
			case self::INPUT_DROPDOWN:
				$theItemList = array();
				foreach((array)$this->dropdown_values as $key => $valueRow) {
					if (is_array($valueRow))
						$theItemList[$key] = $valueRow['label'];
					else
						$theItemList[$key] = $valueRow->label;
				}
				return Widgets::createDropDown($theWidgetName, $theItemList, $theValue);
			default:
				return Widgets::createTextBox($theWidgetName, $theValue);
		}
	}
	
	/**
	 * Given the Scene variable, get the widget values converted back to what needs
	 * to be saved as a config value.
	 * @param Scene $aScene - the scene containing the values.
	 * @return mixed Returns the value to be saved.
	 */
	public function getInputValue($aScene) {
		$theWidgetName = $this->getWidgetName();
		switch ($this->input) {
			case self::INPUT_STRING:
				return $aScene->$theWidgetName;
			case self::INPUT_BOOLEAN:
				return (!empty($aScene->$theWidgetName)) ? 1 : 0;
			case self::INPUT_DROPDOWN:
				$theValueList = array_keys($this->dropdown_values);
				//$aScene->addUserMsg($this->debugStr($theValueList));
				if (in_array($aScene->$theWidgetName,$theValueList)) {
					return $aScene->$theWidgetName;
				} else {
					return $this->default_value;
				}
			default:
				return $aScene->$theWidgetName;
		}
	}
	
}//end class
	
}//end namespace
