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

namespace BitsTheater\scenes; 
use BitsTheater\Scene;
use com\blackmoonit\Widgets;
{//namespace begin

class Rights extends Scene {

	protected function setupDefaults() {
		parent::setupDefaults();
		$this->redirect = BITS_URL.'/rights';
		$this->groups = array();
		$this->rights = null;
		$this->right_groups = null;
		$theText = $this->getRes('generic/save_button_text');
		$this->save_button = '<br/>'.Widgets::createSubmitButton('submit_save',$theText)."\n";
	}
	
	public function getRightValues($rights) {
		$res = $rights->getPermissionRes('right_values');
		$theResult = array();
		foreach ($res as $key => $keyInfo) { //allow, disallow, deny
			$theResult[$key] = $keyInfo['label'];
		}
		return $theResult;
	}
	
	public function getShortRightValues() {
		return array('allow'=>'+','disallow'=>'-','deny'=>'x');
	}
	
	public function getRightValue($assigned_rights,$ns,$rightName) {
		$theResult = 'disallow';
		if (!empty($assigned_rights[$ns]) && !empty($assigned_rights[$ns][$rightName]))
			$theResult = $assigned_rights[$ns][$rightName];
		return $theResult;
	}
	
}//end class

}//end namespace

