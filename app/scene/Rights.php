<?php
namespace com\blackmoonit\bits_theater\app\scene; 
use com\blackmoonit\bits_theater\app\Scene;
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

