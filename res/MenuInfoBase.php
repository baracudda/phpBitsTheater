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
	
	public function setup($aDirector) {
		parent::setup($aDirector);
		$this->bHasBeenSetup = false; //set back to true at end of this method
		//strings that require concatination need to be defined during setup()		
		
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

		$this->bHasBeenSetup = true;
	}
		
}//end class

}//end namespace
