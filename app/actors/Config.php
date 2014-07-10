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

namespace BitsTheater\actors; 
use BitsTheater\Actor;
use BitsTheater\Scene as MyScene;
	/* @var $v MyScene */
use BitsTheater\models\Config as ConfigModel;
	/* @var $dbConfig ConfigModel */
use BitsTheater\costumes\ConfigNamespaceInfo;
	/* @var $theNamespaceInfo ConfigNamespaceInfo */
use BitsTheater\costumes\ConfigSettingInfo;
	/* @var $theSettingInfo ConfigSettingInfo */
use com\blackmoonit\Widgets;
{//namespace begin

class Config extends Actor {
	const DEFAULT_ACTION = 'edit';

	public function edit() {
		if (!$this->isAllowed('config','modify'))
			return $this->getHomePage();
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		
		$dbConfig = $this->getProp('config');
		$v->config_areas = $dbConfig->getConfigAreas();
		foreach ($v->config_areas as $theNamespaceInfo) {
			$theNamespaceInfo->settings_list = $dbConfig->getConfigSettings($theNamespaceInfo);
		}
		
		$v->redirect = $this->getMyUrl('edit');
		$v->next_action = $this->getMyUrl('modify');
		$theText = $this->getRes('generic/save_button_text');
		$v->save_button = '<br/>'.Widgets::createSubmitButton('submit_save',$theText)."\n";
		//indicate what top menu we are currently in
		$this->setCurrentMenuKey('admin');
	}
	
	public function modify() {
		if (!$this->isAllowed('config','modify'))
			return $this->getHomePage();
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		
		$bSaved = false;
		$dbConfig = $this->getProp('config');
		$v->config_areas = $dbConfig->getConfigAreas();
		foreach ($v->config_areas as $theNamespaceInfo) {
			$theNamespaceInfo->settings_list = $dbConfig->getConfigSettings($theNamespaceInfo);
			foreach ($theNamespaceInfo->settings_list as $theSettingName => $theSettingInfo) {
				$theNewValue = $theSettingInfo->getInputValue($v);
				$theOldValue = $theSettingInfo->value;
				//$v->addUserMsg(' ov='.$theOldValue.' nv='.$this->debugStr($theNewValue));
				if ($theNewValue != $theOldValue) {
					$dbConfig->setConfigValue($theSettingInfo->ns, $theSettingInfo->key, $theNewValue);
					$bSaved = true;
				}
			}
		}
		if ($bSaved)
			$v->addUserMsg($this->getRes('config/msg_save_applied'), $v::USER_MSG_NOTICE);
		
		return $v->redirect;
	}
	
}//end class

}//end namespace

