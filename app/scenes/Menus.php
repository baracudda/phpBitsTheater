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
use com\blackmoonit\Strings;
{//namespace begin

class Menus extends Scene {
	const IS_SUBTEXT_SUPPORTED = false;

	public function getText($aMenuItem,$aKey) {
		if (empty($aMenuItem[$aKey]))
			return '';
		if ($aMenuItem[$aKey]{0}=='&') {
			$sa = explode('@',$aMenuItem[$aKey],2);
			switch ($sa[0]) {
			case '&config':
				return $this->_director[$sa[1]];
			case '&res':
				return $this->getRes($sa[1]);
			case '&method':
				return $this->$sa[1]();
			case '&view':
				$meth = explode('/',$sa[1]);
				return $this->cueActor($meth[0],$meth[1]);
			}//switch
		} else
			return $aMenuItem[$aKey];
	}
	
	public function getMenuIcon($aMenuItem) {
		$src = $this->getText($aMenuItem,'icon');
		if (!empty($src))
			return sprintf('<img src="%s" border="0" class="menu_icon" />',$src);
		else
			return '';
	}
	
	public function getMenuDisplay($aMenuItem) {
		$theResult = trim($this->getMenuIcon($aMenuItem).' '.$this->getText($aMenuItem,'label'));
		if (self::IS_SUBTEXT_SUPPORTED && !empty($aMenuItem['subtext']))
			$theResult .= '<span class="subtext">'.$this->getText($aMenuItem,'subtext').'</span>';
		return $theResult;
	}

	public function renderMenuItem($aMenuKey, $aMenuItem, $aSubLevel=0) {
		$isLast = (isset($aMenuItem['last']));
		$isSubMenu = ((empty($aMenuItem['link']) || !empty($aMenuItem['hasSubmenu'])) && isset($aMenuItem['submenu']));
		$theLink = $this->getText($aMenuItem,'link');

		$theClasses = '';
		if ($isSubMenu)
			$theClasses .= 'parent ';
		if ($isLast)
			$theClasses .= 'last ';
		if (!empty($theClasses))
			$theClasses = ' class="'.trim($theClasses).'"';
		
		$theItem = sprintf('<a href="%1$s" %3$s><span>%2$s</span></a>',
				$theLink,$this->getMenuDisplay($aMenuItem),$theClasses);
		if ($isSubMenu) {
			$theItem .= "\n".$this->renderMenu($aMenuItem['submenu'],$aSubLevel+1)."\n";
		}
		
		
		$theClasses = '';
		if ($aMenuKey==$this->_director['current_menu_key'])
			$theClasses .= 'current ';
		if (!empty($theClasses))
			$theClasses = ' class="'.trim($theClasses).'"';
		return str_repeat("\t",$aSubLevel).'<li'.$theClasses.'>'.$theItem."</li>\n";
	}
	
	public function renderMenu($aMenu,$aSubLevel=0) {
		if (empty($aMenu))
			return;
		$w = str_repeat("\t",$aSubLevel).'<div><ul'.(($aSubLevel==0)?' class="menu"':'').'>'."\n";
		foreach ($aMenu as $theMenuKey => &$theMenuItem) {
			$w .= $this->renderMenuItem($theMenuKey,$theMenuItem,$aSubLevel);
		}
		$w .= str_repeat("\t",$aSubLevel)."</ul></div>";
		if (!$this->_director->isGuest())
			$w = str_replace('%account_id%',$this->_director->account_info['account_id'],$w);
		return $w;
	}

}//end class

}//end namespace
