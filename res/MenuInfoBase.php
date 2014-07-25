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

namespace BitsTheater\res;
{//begin namespace

class MenuInfoBase extends Resources {
	//menu items
	public $menu_item_home;
	public $menu_item_account;
	public $menu_item_admin;
	public $menu_item_config;
	public $menu_item_rights;
	public $menu_item_login;
	public $menu_item_logout;
	
	//menus containing menu items
	public $menu_main;
	public $menu_admin;
	
	public function setup($aDirector) {
		parent::setup($aDirector);
		$this->bHasBeenSetup = false; //set back to true at end of this method
		//strings that require concatination need to be defined during setup()

		//==================================
		// Individual Menu Item Definitions
		//==================================
		$this->menu_item_home = array(
				'link' => BITS_URL,
				'filter' => '',
				'label' => '&res@generic/menu_home_label',
				'subtext' => '&res@generic/menu_home_subtext',
				//'icon'=>'&res@menu_info/icon_home',
		);
		$this->menu_item_account = array(
				'link' => BITS_URL.'/account/view/%account_id%',
				'filter' => '&method@isGuest/false',
				'label' => '&res@account/menu_account_label',
				'subtext' => '&res@account/menu_account_subtext',
				//either of the below icon definitions work, here as an example
				//'icon' => '&res@account/imgsrc/menu/account', //res_class/function_name/arg1/arg2
				'icon' => '&res@account/imgsrc/icon_menu_account',
		);
		$this->menu_item_admin = array(
				//'filter' => '&right@auth/config', //example only.  submenus with all filtered off, should remove themselves
				'label' => '&res@config/menu_admin_label',
				'subtext' => '&res@config/menu_admin_sublabel',
				'icon' => '&res@config/imgsrc/icon_menu_admin',
				'hasSubmenu' => true,
		);
		$this->menu_item_config = array(
				'link' => BITS_URL.'/config/edit',
				'filter' => '&right@config/modify',
				'label' => '&res@config/menu_settings_label',
				'subtext' => '&res@config/menu_settings_subtext',
		);
		$this->menu_item_rights = array(
				'link' => BITS_URL.'/rights/',
				'filter' => '&right@auth/modify',
				'label' => '&res@permissions/menu_rights_label',
				'subtext' => '&res@permissions/menu_rights_subtext',
		);
		$this->menu_item_login = array(
				'link' => '&view@account/buildAuthLogin',
				'filter' => '&method@isGuest/true',
		);
		$this->menu_item_logout = array(
				'link' => '&view@account/buildAuthLogout',
				'filter' => '&method@isGuest/false',
		);
		
		//==================================
		// Menu Group Definitions
		//==================================
		$this->menu_main = array( //no link defined, or hasSubmenu=true, means submenu is defined as $menu_%name%
				'home' => $this->menu_item_home,
				'account' => $this->menu_item_account,
				'admin' => $this->menu_item_admin,
		);

		$this->menu_admin = array(
				'config' => $this->menu_item_config,
				'rights' => $this->menu_item_rights,
		);
	
		$this->bHasBeenSetup = true;
	}
		
}//end class

}//end namespace
