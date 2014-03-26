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
use BitsTheater\res\ResException;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * menus are arrays['link','filter','label','icon','subtext']
 * if link is empty (or hasSubmenu is true), it will be a submenu, 
 * if submenus result in no items, main item will be removed too.
 */
class Menus extends Actor {
	const ALLOW_URL_ACTIONS = false;
	
	protected function getLink($aLink) {
		if (empty($aLink))
			return '';
		if ($aLink{0}=='&') {
			$sa = explode('@',$aLink,2);
			switch ($sa[0]) {
			case '&config':
				return $this->director[$sa[1]];
			case '&method':
				return $this->director->$sa[1]();
			}//switch
		} else
			return $aLink;	
	}
	
	protected function isMenuAllowed($aFilter) {
		if (empty($aFilter))
			return true;
		if ($aFilter{0}=='&') {
			$sa = explode('@',$aFilter,2);
			switch ($sa[0]) {
			case '&right':
				return call_user_func_array(array($this->director,'isAllowed'),explode('/',$sa[1]));
			case '&method':
				$meth = explode('/',$sa[1]);
				$b = (strtolower($meth[1])==='true');
				return call_user_func_array(array($this->director,$meth[0]),array())==$b;
			}//switch
		} else
			return call_user_func_array(array($this->director,'isAllowed'),explode('/',$aFilter));
	}
	
	protected function isMenuItemRemoved($aMenuName,&$aMenuItem) {
		if (!empty($aMenuItem['filter'])) {
			$theList = explode(',',$aMenuItem['filter']);
			foreach ($theList as $theFilter) {
				if (!$this->isMenuAllowed($theFilter)) {
					return true;
				}
			}
		}
		//recursive call in case of submenu items so that if menu ends up empty, return true
		if (empty($aMenuItem['link']) || !empty($aMenuItem['hasSubmenu'])) {
			//get submenu and filter it
			$resName = 'menu_info/menu_'.$aMenuName;
			try {
				$submenu = $this->scene->getRes($resName);
			} catch (ResException $re) {
				$re->debugPrint();
				$submenu = null;
			}
			if (isset($submenu)) {
				foreach ($submenu as $theMenuName => $theMenuItem) {
					if ($this->isMenuItemRemoved($theMenuName,$theMenuItem)) {
						unset($submenu[$theMenuName]);
					}
				}
			}
			if (empty($submenu)) {
				return empty($aMenuItem['link']);
			} else {
				$aMenuItem['submenu'] = $submenu;
			}
		} else {
			$theLink = $this->getLink($aMenuItem['link']);
			if (empty($theLink))
				return true;
			if (!$this->director->isGuest())
				$aMenuItem['link'] = str_replace('%account_id%',$this->director->account_info['account_id'],$theLink);
			else
				$aMenuItem['link'] = $theLink;
		}
		return false;
	}
	
	protected function buildMenuFromRes($aRes) {
		$theMenu = $this->scene->getRes($aRes);
		//print('<br/><pre>');var_dump($theMenu);print("</pre><br/><br/>\n");
		//Strings::debugLog($aRes.'='.Strings::debugStr($theMenu));

		//process the menu array/tree first, anything left will be rendered
		foreach ($theMenu as $theMenuName => &$theMenuItem) {
			if ($this->isMenuItemRemoved($theMenuName,$theMenuItem)) {
				unset($theMenu[$theMenuName]);
			}
		}
		if (empty($theMenu)) 
			return;
		end($theMenu);
		$theMenu[key($theMenu)]['last'] = true;
		//print('<br/><pre>');var_dump($theMenu);print("</pre><br/><br/>\n");
		return $theMenu;
	}

	protected function buildAppMenu() {
		$this->scene->app_menu = $this->buildMenuFromRes('menu_info/menu_main');
	}

}//end class

}//end namespace
