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
use BitsTheater\Scene;
{//namespace begin

/**
 * Menu resources have UI label, descriptive and permission elements.
 * Helper class for Resource-based menu work.
 */
class MenuItemResEntry extends BaseCostume {
	//definition
	protected $_title = 'menuitem';
	protected $_hasSubmenu = false;
	protected $_label = null;
	protected $_subtext = null;
	protected $_url = null;
	protected $_filter = null;
	protected $_gone = false;
	protected $_icon = null;

	//used when rendering the menu
	protected $_isLast = false;
	protected $_submenu = null;
	/**
	 * @var Scene
	 */
	protected $_scene = null;
	
	static public function makeEntry(Director $aDirector, $aTitle=null) {
		$o = new MenuItemResEntry($aDirector);
		$o->title($aTitle);
		return $o;
	}
	
	public function title($aTitle=null) {
		if (isset($aTitle)) {
			$this->_title = $aTitle;
			return $this; //for chaining
		} else {
			return $this->_title;
		}
	}

	/**
	 * If param is passed in, sets the value; otherwise
	 * returns the current value.
	 * @param boolean $b - if set, will store it and return $this.
	 * @return MenuItemResEntry/boolean Returns the value of hasSubmenu if nothing is passed in,
	 * otherwise $this is returned if a param isset().
	 */
	public function hasSubmenu($b=null) {
		if (isset($b)) {
			$this->_hasSubmenu = $b;
			return $this; //for chaining
		} else {
			return $this->_hasSubmenu;
		}
	}
	
	public function text($aText=null) {
		if (isset($aText)) {
			$this->_label = $aText;
			return $this; //for chaining
		} else {
			return $this->_label;
		}
	}

	public function label($aText=null) {
		return $this->text($aText);
	}

	public function url($aUrl=null) {
		if (isset($aUrl)) {
			$this->_url = $aUrl;
			return $this; //for chaining
		} else {
			return $this->_url;
		}
	}
	
	public function link($aUrl=null) {
		return $this->url($aUrl);
	}
	
	public function subtext($aSubtext=null) {
		if (isset($aSubtext)) {
			$this->_subtext = $aSubtext;
			return $this; //for chaining
		} else {
			return $this->_subtext;
		}
	}
	
	/**
	 * Menu filter can either be an array, set of string parameters, or a
	 * semi-colon (;) separated string of several filters that must all
	 * be TRUE to enable the menu item.
	 * @param string|array $aFilter
	 * @return \BitsTheater\costumes\MenuItemResEntry|string|array
	 */
	public function filter($aFilter=null, $_=null) {
		if (isset($aFilter)) {
			if (func_num_args()<2)
				$this->_filter = $aFilter;
			else
				$this->_filter = func_get_args();
			return $this; //for chaining
		} else {
			return $this->_filter;
		}
	}
	
	public function gone($bGone=null) {
		if (isset($bGone)) {
			$this->_gone = $bGone;
			return $this; //for chaining
		} else {
			return $this->_gone;
		}
	}
	
	public function icon($aIcon=null) {
		if (isset($aIcon)) {
			$this->_icon = $aIcon;
			return $this; //for chaining
		} else {
			return $this->_icon;
		}
	}
	
	public function last($bLast=null) {
		if (isset($bLast)) {
			$this->_isLast = $bLast;
			return $this; //for chaining
		} else {
			return $this->_isLast;
		}
	}
	
	public function submenu($aSubMenu=null) {
		if (isset($aSubMenu)) {
			$this->_submenu = $aSubMenu;
			return $this; //for chaining
		} else {
			return $this->_submenu;
		}
	}

	public function scene($aScene=null) {
		if (isset($aScene)) {
			$this->_scene = $aScene;
			return $this; //for chaining
		} else {
			return $this->_scene;
		}
	}

	public function cnvToText($aValue) {
		if (empty($aValue))
			return '';
		if ($aValue{0}=='&') {
			$sa = explode('@',$aValue,2);
			switch ($sa[0]) {
				case '&res':
					return $this->_scene->getRes($sa[1]);
				case '&method':
					$args = explode('/',$sa[1]);
					$theMethodName = array_shift($args);
					return call_user_func_array(array($this->_scene,$theMethodName), $args);
				case '&view':
					$meth = explode('/',$sa[1]);
					return $this->_scene->cueActor($meth[0],$meth[1]);
				case '&config':
					return $this->_scene->_config[$sa[1]];
			}//switch
		} else
			return $aValue;
	}
	
	public function getText() {
		return $this->cnvToText($this->text());
	}
	
	public function getLabel() {
		return $this->cnvToText($this->label());
	}
	
	public function getSubtext() {
		return $this->cnvToText($this->subtext());
	}
	
	public function getUrl() {
		return $this->cnvToText($this->url());
	}

	public function getLink() {
		return $this->getUrl();
	}

	public function getIconSrc() {
		return $this->cnvToText($this->icon());
	}

	public function isSubMenu() {
		return ( empty($this->_url) || $this->hasSubmenu() ) && !empty($this->_submenu);
	}
	
	protected function checkPermission($aPermissionString) {
		$sa = explode('/',$aPermissionString,2);
		if (count($sa)>=2)
			return call_user_func_array(array($this->_director,'isAllowed'),$sa);
		else
			return false;
	}
	
	protected function isPassFilterSegment($aFilterSegment) {
		if (empty($aFilterSegment))
			return true;
		if ($aFilterSegment{0}=='&') {
			$sa = explode('@',$aFilterSegment,2);
			switch ($sa[0]) {
				case '&right':
					return $this->checkPermission($sa[1]);
				case '&method':
					list($s, $result) = explode('=', $sa[1]);
					$theResultCompare = (!empty($result)) ? (strtolower($result)!=='false') : true;
					$args = explode('/',$s);
					$theMethodName = array_shift($args);
					//$this->debugLog(__METHOD__.' m='.$theMethodName.' a='.$this->debugStr($args).' r='.($theResultCompare ? 'true' : 'false'));
					return call_user_func_array(array($this->_scene,$theMethodName),$args)==$theResultCompare;
				case '&false': //always disable
					return false;
			}//switch
		} else {
			return $this->checkPermission($aFilterSegment);
		}
	}
	
	public function isMenuAllowed(Scene $aScene) {
		$this->scene($aScene);
		$theFilter = $this->filter();
		if (!empty($theFilter) && !$this->gone()) {
			if (is_string($theFilter))
				$theFilterSegments = explode(';', $theFilter);
			else if (is_array($theFilter))
				$theFilterSegments = $theFilter;
			else
				$theFilterSegments = array();
			foreach ($theFilterSegments as $theFilterSegment) {
				if (!$this->isPassFilterSegment($theFilterSegment)) {
					return false;
				}
			}
		}
		return !$this->gone();
	}
	
}//end class

}//end namespace
