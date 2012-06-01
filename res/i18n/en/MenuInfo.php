<?php
namespace res\en;
use res\MenuInfo as ResMenuInfo;
{//begin namespace

class MenuInfo extends ResMenuInfo {

	public $menu_main_info = array(
				'home' => array('label'=>'Schedule', 'icon'=>'&res@menu_info/icon_sched', ),
				'forum' => array('label'=>'Forum', 'icon'=>'&res@menu_info/icon_home', 'subtext'=>'<i>discuss!</i>', ),
				'lists' => array('label'=>'Lists', 'icon'=>'&res@menu_info/icon_lists', ),
				'admin' => array('label'=>'Admin', 'icon'=>'&res@menu_info/icon_admin', 'subtext'=>'nuts &amp; bolts',),
				'create' => array('label'=>'Create', 'icon'=>'&res@menu_info/icon_create', ),
				'account' => array('label'=>'My Account', 'icon'=>'&res@menu_info/icon_account', 'subtext'=>'me, myself, &amp; I', ),
				//'login' => array('label'=>'Login', ),
				//'logout' => array('label'=>'Logout', ),
			);

	public $menu_lists_info = array(
				'sched' => array('label'=>'Upcoming Events', ),
				'history' => array('label'=>'Past Events', ),
				'units' => array('label'=>'Roster', ),
			);
			
	public $menu_account_info = array(
				'account' => array('label'=>'Details', ),
				'sched' => array('label'=>'Upcoming Events', ),
				'history' => array('label'=>'Past Events', ),
			);
	
	public $menu_create_info = array(
				'event' => array('label'=>'Event', ),
			);
			
	public $menu_admin_info = array(
				'config' => array('label'=>'Settings', ),
				'game_data' => array('label'=>'Game Data', ),
				'rights' => array('label'=>'Permissions', ),
				'templates' => array('label'=>'Templates', ),
			);

	public function setup() {
		parent::setup();
		$this->res_array_merge($this->menu_main,$this->menu_main_info);
		unset($this->menu_main_info);
		$this->res_array_merge($this->menu_lists,$this->menu_lists_info);
		unset($this->menu_lists_info);
		$this->res_array_merge($this->menu_account,$this->menu_account_info);
		unset($this->menu_account_info);
		$this->res_array_merge($this->menu_create,$this->menu_create_info);
		unset($this->menu_create_info);
		$this->res_array_merge($this->menu_admin,$this->menu_admin_info);
		unset($this->menu_admin_info);
	}
		
}//end class

}//end namespace
