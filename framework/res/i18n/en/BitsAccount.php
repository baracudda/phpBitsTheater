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

	public $menu_acctlist_label = 'Accounts';
	public $menu_acctlist_subtext = '';
	
	public $title_acctlist = 'Account List';
	
	public $colheader_account_id = '#';
	public $colheader_account_name = 'Name';
	public $colheader_account_extid = 'ExtID'; //$external_id;
	public $colheader_auth_id = 'ID';
	public $colheader_email = 'Email';
	public $colheader_verified_ts = 'Verified';
	public $colheader_account_is_active = 'Active';
	//public $colheader_hardware_ids;
	
	public $label_is_active_true = 'Yes';
	public $label_is_active_false = 'No';
	public $label_button_add_account = '<span class="glyphicon glyphicon-plus"></span> Add Account';
	public $label_dialog_title_account_add = 'Add Account';
	public $label_dialog_title_account_edit = 'Edit Account';
	public $label_dialog_auth_groups = 'Roles';
	
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
	
	public $placeholder_name = '   your name or alias';
	public $placeholder_email = '   you@example.com';
	public $placeholder_pwinput = '   your own personal secret phrase';
	public $placeholder_pwconfirm = '   re-type your secret phrase';
	public $placeholder_regcode = '   open-sesame!';
	public $placeholder_autharea_username = '  user';
	public $placeholder_autharea_password = '  pw';
	
	public $msg_pw_nomatch = 'Passwords do not match.' ;
	public $msg_reg_code_mismatch = 'Failed to match a valid Registration Code.';
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
	public $err_cannot_delete_active_account =
		'Cannot delete account [%1$s]; the account is linked to data.' ;
	public $err_cannot_delete_yourself = 'You cannot delete your own account!' ;
	public $err_unique_field_already_exists =
		'Unique field [%1$s] already exists in system.' ;
	/**
	 * Thrown when a client attempts to create an organization but the "short
	 * name" for the org (used to create its database) fails validation.
	 * The message string contains one parameter, allowing it to echo the
	 * invalid input back to the user.
	 * @var string
	 * @since BitsTheater v4.1.0
	 * @see \BitsTheater\actors\Understudy\AuthOrgAccount::ajajCreateOrg()
	 */
	public $errmsg_invalid_org_short_name =
		'String [%1$s] is not a valid codename for an organization.' ;
	public $errmsg_no_preference_specified =
		'Not enough information to resolve a preference location.' ;
	public $errmsg_no_such_preference =
		'Could not resolve a preference at [%1$s/%2$s].' ;
	/**
	 * Thrown when a user's input for a password change is shorter than the
	 * minimum length set in the instance's configs.
	 * This is the programmatic error token.
	 * @var string
	 * @since BitsTheater v4.1.0
	 * @see \BitsTheater\outtakes\AccountAdminException::MSG_PASSWORD_MINIMUM_LENGTH
	 */
	public $errmsg_password_minimum_length =
		'Passwords must be at least %1$s characters in length.' ;
	public $errmsg_preference_update_failed =
		'Failed to update the account preference.' ;
	
	// "Help" messages should be initialized in the initHelpText() function that
	// is invoked by the constructor.
	public $help_request_pwd_reset = '' ;
	
	// Similarly, initialize email body text separately.
	public $email_body_pwd_reset_instr = '' ;
	
	public $err_pw_failed_account_locked =
		'The login attempt failed too often. Contact the system administrator.' ;
	
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
