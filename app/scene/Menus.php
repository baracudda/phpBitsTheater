<?php
namespace com\blackmoonit\bits_theater\app\scene; 
use com\blackmoonit\bits_theater\app\Scene;
use com\blackmoonit\Strings;
{//namespace begin

class Menus extends Scene {

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
		if (!empty($aMenuItem['subtext']))
			$theResult .= '<span class="subtext">'.$this->getText($aMenuItem,'subtext').'</span>';
		return $theResult;
	}

	public function renderMenuItem($aMenuItem,$aSubLevel=0) {
		$isLast = (isset($aMenuItem['last']));
		$isSubMenu = ((empty($aMenuItem['link']) || !empty($aMenuItem['hasSubmenu'])) && isset($aMenuItem['submenu']));
		$theLink = $this->getText($aMenuItem,'link');
		$theItem = sprintf('<a href="%1$s" %3$s><span>%2$s</span></a>',
				$theLink,$this->getMenuDisplay($aMenuItem),($isSubMenu)?'class="parent"':(($isLast)?'class="last"':''));
		if ($isSubMenu) {
			$theItem .= "\n".$this->renderMenu($aMenuItem['submenu'],$aSubLevel+1)."\n";
		}
		return str_repeat("\t",$aSubLevel).'<li>'.$theItem."</li>\n";
	}
	
	public function renderMenu($aMenu,$aSubLevel=0) {
		if (empty($aMenu))
			return;
		$w = str_repeat("\t",$aSubLevel).'<ul '.(($aSubLevel==0)?'class="menu"':'').'>'."\n";
		foreach ($aMenu as &$theMenuItem) {
			$w .= $this->renderMenuItem($theMenuItem,$aSubLevel);
		}
		$w .= str_repeat("\t",$aSubLevel)."</ul>";
		if (!$this->_director->isGuest())
			$w = str_replace('%account_id%',$this->_director->account_info['account_id'],$w);
		return $w;
	}

}//end class

}//end namespace
