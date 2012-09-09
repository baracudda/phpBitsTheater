<?php
namespace com\blackmoonit\bits_theater\res\en;
use com\blackmoonit\bits_theater\res\MenuInfo as ResMenuInfo;
{//begin namespace

class MenuInfo extends ResMenuInfo {

	public $menu_main_info = array(
				'home' => array('label'=>'Home', 'icon'=>'&res@menu_info/icon_home', ),
				'casnodes' => array('label'=>'Nodes', 'subtext'=>'Definition & Status',),
				'account' => array('label'=>'My Account', 'icon'=>'&res@menu_info/icon_account', 'subtext'=>'me, myself, &amp; I', ),
				'admin' => array('label'=>'Admin', 'icon'=>'&res@menu_info/icon_admin', 'subtext'=>'nuts &amp; bolts',),
			);

	public $menu_account_info = array(
				'account' => array('label'=>'Details', ),
			);
	
	public $menu_admin_info = array(
				'config' => array('label'=>'Settings', ),
				'rights' => array('label'=>'Permissions', ),
			);
			
	public $menu_casnodes_info = array(
				'addnode' => array('label'=>'Add Node', ),
			);

	public function setup() {
		parent::setup();
		$this->res_array_merge($this->menu_main,$this->menu_main_info);
		unset($this->menu_main_info);
		$this->res_array_merge($this->menu_account,$this->menu_account_info);
		unset($this->menu_account_info);
		$this->res_array_merge($this->menu_admin,$this->menu_admin_info);
		unset($this->menu_admin_info);
		$this->res_array_merge($this->menu_casnodes,$this->menu_casnodes_info);
		unset($this->menu_casnodes_info);
	}
		
}//end class

}//end namespace
