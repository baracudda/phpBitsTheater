<?php
namespace BitsTheater\res\en;
use BitsTheater\res\Config as BaseResources;
use BitsTheater\costumes\ConfigSettingInfo;
{//begin namespace

class CoreConfig extends BaseResources {
	public $menu_admin_label = 'Admin';
	public $menu_admin_subtext = 'nuts &amp; bolts';
	public $menu_settings_label = 'Settings';
	public $menu_settings_subtext = '';
	
	public $msg_save_applied = 'Settings saved!';

	public $label_namespace = array(
			'site' => 'Site Settings',
			'auth' => 'Authorization',
	);
	public $desc_namespace = array(
			'site' => 'Settings that will determine behavior site-wide.',
			'auth' => 'Determines how identity is discovered.',
	);
	
	public $label_site = array(
			'mode' => 'Operating Mode',
	);
	public $desc_site = array(
			'mode' => 'Normal is the standard operation mode; Maintenance will refuse connections; Demo/Kiosk mode will favor local resources.',
	);
	public $input_site = array(
			'mode' => array(
					'type' => ConfigSettingInfo::INPUT_DROPDOWN,
					'values' => array(
							'normal' => 'Normal',
							'maintenance' => 'Maintenance',
							'demo' => 'Demo/Kiosk',
					),
			),
	);
	
	public $label_auth = array(
			'register_url' => 'Registration URL',
			'login_url' => 'Login URL',
			'logout_url' => 'Logout URL',
	);
	public $desc_auth = array(
			'register_url' => 'URL for the registration page.',
			'login_url' => 'URL for the login page.',
			'logout_url' => 'URL for the logout page.',
	);
	/* all strings for now, and that's the default type so no need to define anything yet.
	public $input_auth = array(
		
	);
	*/

}//end class

}//end namespace
