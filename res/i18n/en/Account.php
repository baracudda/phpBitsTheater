<?php
namespace BitsTheater\res\en;
use BitsTheater\res\Resources;
{//begin namespace

class Account extends Resources {
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
