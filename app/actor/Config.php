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

namespace com\blackmoonit\bits_theater\app\actor; 
use com\blackmoonit\bits_theater\app\Actor;
use com\blackmoonit\Widgets;
{//namespace begin

class Config extends Actor {
	const DEFAULT_ACTION = 'edit';

	private function getConfigAreas() {
		$theAreas = array();
		$theNamespaces = $this->getRes('config/namespace');
		foreach ($theNamespaces as $ns=>$nsInfo) {
			if (empty($nsInfo['group_id']) || in_array($nsInfo['group_id'],$this->director->account_info['groups'])) {
				$theAreas[$ns] = $nsInfo;
			}
		}
		return $theAreas;
	}
	
	public function edit() {
		if (!$this->director->isAllowed('config','modify'))
			return $this->getHomePage();
		$this->scene->config = $this->config;
		$this->scene->config_areas = $this->getConfigAreas();
		$theNamespaces = $this->getRes('config/namespace');
		foreach ($theNamespaces as $ns=>$nsInfo) {
			if (empty($nsInfo['group_id']) || in_array($nsInfo['group_id'],$this->director->account_info['groups'])) {
				$this->scene->config_areas[$ns] = $nsInfo;
			}
		}
		$this->scene->redirect = $this->getHomePage();
		$this->scene->next_action = $this->getMyUrl('/config/modify');
		$theText = $this->scene->getRes('generic/save_button_text');
		$this->scene->save_button = '<br/>'.Widgets::createSubmitButton('submit_save',$theText)."\n";
	}
	
	public function modify() {
		if (!$this->director->isAllowed('config','modify'))
			return $this->getHomePage();
		$v =& $this->scene;
		$v->config = $this->config;
		$v->config_areas = $this->getConfigAreas();
		foreach ($v->config_areas as $ns => $nsInfo) {
			foreach ($v->getRes('config/'.$ns) as $theSetting => $theSettingInfo) {
				$theWidgetName = $ns.'__'.$theSetting;
				switch ($theSettingInfo['input']) {
					case 'boolean':
						$theNewValue = (!empty($v->$theWidgetName)) ? 1 : 0;
						break;
					case 'string':
					default:
						$theNewValue = $v->$theWidgetName;
				}
				$theOldValue = $v->config->getConfigValue($ns,$theSetting);
				if ($theNewValue != $theOldValue) {
					$v->config[$ns.'/'.$theSetting] = $theNewValue;
				}
			}
		}
		return $this->scene->redirect;
	}
	
}//end class

}//end namespace

