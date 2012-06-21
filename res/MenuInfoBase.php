<?php
namespace res;
{//begin namespace

class MenuInfoBase extends Resources {
	
	public function setup() {//strings that require concatination need to be defined during setup()
		parent::setup();
		
		$this->icon_home = BITS_RES.'/images/menu/home.png';
		$this->icon_account = BITS_RES.'/images/menu/account.png';
		$this->icon_admin = BITS_RES.'/images/menu/admin.png';
	
		$this->menu_main = array( //no link defined means submenu is defined as $menu_%name%
				'home' => array(
						'link' => BITS_URL, 
						'filter' => '',
					),
				'admin' => array(
						'filter' => '&right@auth/config', //example only.  submenus with all filtered off, should remove themselves
					), 
				'account' => array(
						'link' => BITS_URL.'/account/view/%account_id%', 
						'filter' => '&method@isGuest/false',
					), 
				/*
				'login' => array(
						'link' => '&view@account/buildAuthLogin', 
						'filter' => '&method@isGuest/true',
					), 
				'logout' => array(
						'link' => '&view@account/buildAuthLogout',
						'filter' => '&method@isGuest/false',
					), 
				*/
			);

		$this->menu_admin = array(
				'config' => array(
						'link' => BITS_URL.'/config/edit', 
						'filter' => 'config/modify',
					), 
				'rights' => array(
						'link' => BITS_URL.'/rights/', 
						'filter' => 'rights/modify',
					), 
			);
	
		$this->menu_account = array(
				'account' => array(
						'link' => BITS_URL.'/account/view/%account_id%', 
						'filter' => 'account/view',
					), 
			);
	}
		
}//end class

}//end namespace
