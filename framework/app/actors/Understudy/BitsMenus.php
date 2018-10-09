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

namespace BitsTheater\actors\Understudy;
use BitsTheater\Actor as BaseActor;
use BitsTheater\costumes\MenuItemResEntry;
use BitsTheater\res\ResException;
{//namespace begin

/**
 * Menus items are MenuItemResEntry's.
 * if link is empty (or hasSubmenu is true), it will be a submenu,
 * if submenus result in no items, main item will be removed too.
 */
class BitsMenus extends BaseActor {
	const ALLOW_URL_ACTIONS = false;
	
	protected function isMenuItemRemoved($aMenuKey, MenuItemResEntry &$aMenuItem) {
		//if filter is defined, check if allowed
		if (!$aMenuItem->isMenuAllowed($this->scene))
			return true;
		
		$theLink = $aMenuItem->getLink();
		
		//recursive call in case of submenu items so that if menu ends up empty, return true
		if ( $aMenuItem->hasSubmenu() || empty($theLink) ) {
			//get submenu and filter it
			$resName = 'menu_info/menu_'.$aMenuKey;
			try {
				$submenu = $this->scene->getRes($resName);
			} catch (ResException $re) {
				$re->debugPrint();
				$submenu = null;
			}
			if (isset($submenu)) {
				foreach ($submenu as $theMenuKey => &$theMenuItem) {
					if ($this->isMenuItemRemoved($theMenuKey,$theMenuItem)) {
						unset($submenu[$theMenuKey]);
					}
				}
			}
			if (empty($submenu)) {
				return empty($theLink);
			} else {
				$aMenuItem->submenu($submenu);
			}
		}
		
		//if we made it here, show the menuitem
		return false;
	}
	
	protected function buildMenuFromRes($aRes) {
		$theMenu = $this->scene->getRes($aRes);
		//print('<br/><pre>');var_dump($theMenu);print("</pre><br/><br/>\n");
		//Strings::debugLog($aRes.'='.Strings::debugStr($theMenu));
		
		//process the menu filters first, anything left will be rendered
		foreach ($theMenu as $theMenuKey => &$theMenuItem) {
			if ($this->isMenuItemRemoved($theMenuKey,$theMenuItem)) {
				unset($theMenu[$theMenuKey]);
			}
		}
		if (empty($theMenu))
			return;
		end($theMenu);
		$theMenu[key($theMenu)]->last(true);
		//print('<br/><pre>');var_dump($theMenu);print("</pre><br/><br/>\n");
		return $theMenu;
	}

	protected function buildAppMenu() {
		$this->scene->app_menu = $this->buildMenuFromRes('menu_info/menu_main');
	}

}//end class

}//end namespace
