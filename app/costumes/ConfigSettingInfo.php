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
use BitsTheater\costumes\ConfigResEntry;
use BitsTheater\Director;
use com\blackmoonit\Strings;
use com\blackmoonit\Widgets;
use com\blackmoonit\widgetbuilder\TextAreaWidget ;
use com\blackmoonit\Arrays;
use BitsTheater\models\Config;
use stdClass as StandardClass;
{//namespace begin

/**
 * Configuration setting information in class form
 * instead of associative array form.
 */
class ConfigSettingInfo extends BaseCostume
{
	const INPUT_STRING = 'string' ;
	const INPUT_TEXT = 'text' ;
	const INPUT_BOOLEAN = 'boolean' ;
	const INPUT_DROPDOWN = 'dropdown' ;
	const INPUT_PASSWORD = 'string-pw' ;
	const INPUT_INTEGER = 'integer' ;

	public $config_namespace_info;
	public $config_key;

	//from config table
	public $ns;
	public $key;
	public $value;

	/**
	 * The Config resource entry.
	 * @var ConfigResEntry
	 */
	public $mSettingInfo = null;

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
	 * Copies values into matching property names
	 * based on the array keys or object property names.
	 * @param array|object $aThing - array or object to copy from.
	 */
	protected function copyFrom(&$aThing) {
		parent::copyFrom($aThing);
		$this->config_key = $this->ns.'/'.$this->key;
		if ( !empty($this->mSettingInfo) && (!$this->mSettingInfo instanceof ConfigResEntry) ) {
			$theSettingInfo = $this->mSettingInfo;
			$this->mSettingInfo = new ConfigResEntry($this->ns, $this->key);
			$this->mSettingInfo->setDataFrom($theSettingInfo);
		}
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
			$o = new ConfigSettingInfo($aNamespaceInfo->getDirector());
			if ($aSettingInfo instanceof ConfigResEntry) {
				$o->mSettingInfo = $aSettingInfo;
			} else {
				$o->mSettingInfo = new ConfigResEntry($aNamespaceInfo->namespace, $aSettingName);
				$o->mSettingInfo->setDataFrom($aSettingInfo);
			}

			$o->config_namespace_info = $aNamespaceInfo;
			$o->ns = $aNamespaceInfo->namespace;
			$o->key = $aSettingName;
			$o->config_key = $o->ns.'/'.$o->key;
			return $o;
		}
	}

	/**
	 * Return this payload data as a simple class, minus any metadata this class might have.
	 * @return string Return self encoded as a standard class.
	 */
	public function exportData() {
		$o = new StandardClass();
		$o->ns = $this->ns;
		$o->key = $this->key;
		switch( $this->mSettingInfo->input_type )
		{
			case self::INPUT_BOOLEAN:
			case self::INPUT_INTEGER:
				$o->value = intval($this->getCurrentValue());
				break;
			case self::INPUT_DROPDOWN:
			case self::INPUT_TEXT:
			case self::INPUT_STRING:
			case self::INPUT_PASSWORD:
			default:
				$o->value = $this->getCurrentValue();
		}//switch
		$o->mSettingInfo = $this->mSettingInfo->exportData();
		return $o;
	}
	
	public function getLabel() {
		return $this->mSettingInfo->label;
	}

	public function getDescription() {
		return $this->mSettingInfo->desc;
	}

	public function getCurrentValue() {
		if ($this->getDirector()->isInstalled()) {
			/* @var $dbConfig Config */
			$dbConfig = $this->getDirector()->getProp('Config');
			$this->value = $dbConfig->getMapValue($this->config_key);
			if (!isset($this->mSettingInfo->default_value)) {
				//preserve whatever default is now in the config table (admin set it, so honor it)
				$this->mSettingInfo->default_value = $dbConfig->getMapDefault($this->config_key);
			}
			$this->getDirector()->returnProp($dbConfig);
		}
		return (isset($this->value)) ? $this->value : $this->mSettingInfo->default_value;
	}

	/**
	 * See if this setting is restricted.
	 * @return boolean
	 */
	public function isConfigAllowed() {
		return $this->mSettingInfo->config_is_allowed();
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
	public function getInputWidget()
	{
		$theWidgetName = $this->getWidgetName() ;
		$theValue = $this->getCurrentValue() ;
		switch( $this->mSettingInfo->input_type )
		{
			case self::INPUT_STRING:
				return Widgets::createTextBox($theWidgetName,$theValue) ;
			case self::INPUT_TEXT:
				return (new TextAreaWidget($theWidgetName))
					->append( $theValue )
					->setRows(4)->setCols(60)
					->setAttr( 'maxlength', 250 )
					->renderInline()
					;
			case self::INPUT_BOOLEAN:
				return Widgets::createCheckBox($theWidgetName,!empty($theValue));
			case self::INPUT_DROPDOWN:
				$theItemList = array();
				foreach( $this->mSettingInfo->input_enums as $key => $valueRow )
				{
					if (is_array($valueRow))
						$theItemList[$key] = isset($valueRow['label']) ? $valueRow['label'] : null;
					else
						$theItemList[$key] = $valueRow->label;
				}
				return Widgets::createDropDown($theWidgetName, $theItemList, $theValue);
			case self::INPUT_PASSWORD:
				return Widgets::createPassBox($theWidgetName, $theValue);
			case self::INPUT_INTEGER:
				return Widgets::createNumericBox($theWidgetName,$theValue);
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
	public function getInputValue($aScene)
	{
		$theWidgetName = $this->getWidgetName();
		switch( $this->mSettingInfo->input_type )
		{
			case self::INPUT_TEXT:
			case self::INPUT_STRING:
			case self::INPUT_PASSWORD:
				return $aScene->$theWidgetName;
			case self::INPUT_BOOLEAN:
				return (!empty($aScene->$theWidgetName)) ? '1' : '0';
			case self::INPUT_DROPDOWN:
				$theValueList = array_keys($this->mSettingInfo->input_enums);
				//$aScene->addUserMsg($this->debugStr($theValueList));
				if (in_array($aScene->$theWidgetName,$theValueList)) {
					return $aScene->$theWidgetName;
				} else {
					return $this->getCurrentValue();
				}
			case self::INPUT_INTEGER:
			default:
				return $aScene->$theWidgetName;
		}
	}

}//end class

}//end namespace
