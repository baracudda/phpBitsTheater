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

class BitsAccount extends BaseResources
{
	public $menu_account_label = 'My Account';
	public $menu_account_subtext = 'me, myself, &amp; I';
	
	public $title_request_pwd_reset = 'Request Password Reset' ;
	
	public $label_login = 'Login';
	public $label_logout = 'Logout';
	public $label_name = 'Username';
	public $label_email = 'Email';
	public $label_pwinput = 'Password';
	public $label_pwconfirm = 'Confirm Password';
	public $label_regcode = 'Registration Code';
	public $label_submit = 'Submit';
	public $label_save_cookie = 'Remember me';
	public $label_pwinput_old = 'Current Password';
	public $label_pwinput_new = 'New Password';
	public $label_register = 'Register' ;
	public $label_requestpwreset = 'Request Password Reset' ;
	
	public $msg_pw_nomatch = 'Passwords do not match.' ;
	public $msg_acctexists =
		'%1$s already exists. Please use a different one.' ;
	public $msg_update_success = 'Successfully updated the account.';
	public $msg_reg_too_fast = 'In an effort to thwart spam bots, please wait a few seconds before submitting.';
	public $msg_pw_request_denied =
		'You may not request a password reset for that email address at this time.' ;
	public $msg_pw_reset_requested = 'Password Reset Requested' ;
	public $msg_pw_reset_email_sent =
		'An email with instructions has been sent to [%1$s].' ;

	public $err_fatal = 'I am slain, Horatio...' ;
	public $err_not_connected = 'Connection to the database failed.' ;
	public $err_pw_request_failed =
		'The password reset request failed. Contact the system administrator.' ;
	public $err_email_dispatch_failed =
		'Failed to dispatch an email to [%1$s].' ;
	
	// "Help" messages should be initialized in the initHelpText() function that
	// is invoked by the constructor.
	public $help_request_pwd_reset = '' ;
	
	// Similarly, initialize email body text separately.
	public $email_body_pwd_reset_instr = '' ;
	
	public function setup($aDirector)
	{
		parent::setup($aDirector) ;
		$this->label_modify = $this->getRes('generic/save_button_text') ;
		$this->initHelpText() ;
		$this->initEmailBodyText() ;
	}
	
	protected function initHelpText()
	{
		$this->help_request_pwd_reset =
			  'Please enter your email address and click the <b>'
			. $this->label_submit // <-- must match requestPasswordReset.php
			. '</b> button. If a matching account is found, then a message will'
			. ' be sent to that address, with a link to a URL on this server.'
			. ' Clicking the link will take you to a page which will log you'
			. ' into the system and allow you to change your password.'
			;
	}
	
	protected function initEmailBodyText()
	{
		$this->email_body_pwd_reset_instr =
			  '<p>Someone requested a password reset for the account '
			. 'associated with this email address (%1$s). Please click the '
			. 'link below to access the web site and reset your password.</p>'
			. PHP_EOL . PHP_EOL
			. '<p>Your temporary password is: <b>%2$s</b></p>'
			;
	}
	
}//end class

}//end namespace
