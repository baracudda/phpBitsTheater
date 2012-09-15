<?php
namespace com\blackmoonit\bits_theater\res\en;
use com\blackmoonit\bits_theater\res\MenuInfo as ResMenuInfo;
{//begin namespace

class MenuInfo extends ResMenuInfo {

	public function setup($aDirector) {
		parent::setup($aDirector);
	
		$menu_main_info = array(
				'home' => array('label'=>'Home', 'icon'=>'&res@menu_info/icon_home', ),
				'casnodes' => array('label'=>'Nodes', 'subtext'=>'Definition & Status',),
				'account' => array('label'=>'My Account', 'icon'=>'&res@menu_info/icon_account', 'subtext'=>'me, myself, &amp; I', ),
				'admin' => array('label'=>'Admin', 'icon'=>'&res@menu_info/icon_admin', 'subtext'=>'nuts &amp; bolts',),
		);
		$this->res_array_merge($this->menu_main,$menu_main_info);
		
		$menu_account_info = array(
				'account' => array('label'=>'Details', ),
		);
		$this->res_array_merge($this->menu_account,$menu_account_info);
		
		$menu_admin_info = array(
				'config' => array('label'=>'Settings', ),
				'rights' => array('label'=>'Permissions', ),
		);
		$this->res_array_merge($this->menu_admin,$menu_admin_info);
		
		$menu_casnodes_info = array(
				'addnode' => array('label'=>'Add Node', ),
		);
		$this->res_array_merge($this->menu_casnodes,$menu_casnodes_info);
	}
		
}//end class

}//end namespace
