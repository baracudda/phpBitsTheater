<?php
namespace res\en;
use res\MenuInfo as ResMenuInfo;
{//begin namespace

class MenuInfo extends ResMenuInfo {

	public $menu_main_info = array(
				'home' => array('label'=>'Home', 'icon'=>'&res@menu_info/icon_home', ),
				'admin' => array('label'=>'Admin', 'icon'=>'&res@menu_info/icon_admin', 'subtext'=>'nuts &amp; bolts',),
				'account' => array('label'=>'My Account', 'icon'=>'&res@menu_info/icon_account', 'subtext'=>'me, myself, &amp; I', ),
			);

	public $menu_account_info = array(
				'account' => array('label'=>'Details', ),
			);
	
	public $menu_admin_info = array(
				'config' => array('label'=>'Settings', ),
				'rights' => array('label'=>'Permissions', ),
			);

	public function setup() {
		parent::setup();
		$this->res_array_merge($this->menu_main,$this->menu_main_info);
		unset($this->menu_main_info);
		$this->res_array_merge($this->menu_account,$this->menu_account_info);
		unset($this->menu_account_info);
		$this->res_array_merge($this->menu_admin,$this->menu_admin_info);
		unset($this->menu_admin_info);
	}
		
}//end class

}//end namespace
