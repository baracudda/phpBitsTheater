<?php
namespace app\actor; 
use app\Actor;
use app\config\Settings;
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
			return BITS_URL.Settings::PAGE_Landing;
		$this->scene->config = $this->config;
		$this->scene->config_areas = $this->getConfigAreas();
		$theNamespaces = $this->getRes('config/namespace');
		foreach ($theNamespaces as $ns=>$nsInfo) {
			if (empty($nsInfo['group_id']) || in_array($nsInfo['group_id'],$this->director->account_info['groups'])) {
				$this->scene->config_areas[$ns] = $nsInfo;
			}
		}
		$this->scene->redirect = BITS_URL.Settings::PAGE_Landing;
		$this->scene->next_action = BITS_URL.'/config/modify';
		$theText = $this->scene->getRes('generic/save_button_text');
		$this->scene->save_button = '<br/>'.Widgets::createSubmitButton('submit_save',$theText)."\n";
	}
	
	public function modify() {
		if (!$this->director->isAllowed('config','modify'))
			return BITS_URL.Settings::PAGE_Landing;
	
		$v =& $this->scene;
		$v->config = $this->config;
		$v->config_areas = $this->getConfigAreas();
		foreach ($v->config_areas as $ns => $nsInfo) {
			foreach ($v->getRes('config/'.$ns) as $theSetting => $theSettingInfo) {
				$theWidgetName = $ns.'__'.$theSetting;
				$theNewValue = $v->$theWidgetName;
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

