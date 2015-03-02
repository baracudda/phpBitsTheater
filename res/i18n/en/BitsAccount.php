<?php
/*
 * Copyright (C) 2015 Blackmoon Info Tech Services
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

namespace BitsTheater\res\en;
use BitsTheater\res\Resources as BaseResources;
{//begin namespace

class BitsAccount extends BaseResources {
	public $menu_account_label = 'My Account';
	public $menu_account_subtext = 'me, myself, &amp; I';
	
	public $label_login = 'Login';
	public $label_logout = 'Logout';
	public $label_name = 'Username';
	public $label_email = 'Email';
	public $label_pwinput = 'Password';
	public $label_pwconfirm = 'Confirm Password';
	public $label_regcode = 'Registration Code';
	public $label_submit = 'Register';
	public $label_save_cookie = 'Remember me';
	public $label_pwinput_old = 'Current Password';
	public $label_pwinput_new = 'New Password';
	
	public $msg_pw_nomatch = 'passwords do not match';
	public $msg_acctexists = '%1$s already exists. Please use a different one.';
	public $msg_update_success = 'Successfully updated the account.';
	
	public function setup($aDirector) {
		parent::setup($aDirector);
		$this->label_modify = $this->getRes('generic/save_button_text');
	}
	
}//end class

}//end namespace
