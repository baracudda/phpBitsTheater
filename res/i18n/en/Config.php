<?php
namespace BitsTheater\res\en;
use BitsTheater\res\Resources as BaseResources;
{//begin namespace

class Config extends BaseResources {
	public $menu_admin_label = 'Admin';
	public $menu_admin_subtext = 'nuts &amp; bolts';
	public $menu_settings_label = 'Settings';
	public $menu_settings_subtext = '';
	
	public $msg_save_applied = 'Settings saved!';

	public $namespace = array(
			'site' => array('label'=>'Site Settings', 'desc'=>'Settings that will determine behavior site-wide.',),
			'auth' => array('label'=>'Authorization', 'desc'=>'Determines how identity is discovered.', 'group_id'=>1),
	);
			
	public $site = array(
			//0 = nomral, 1 = Maintenance, 2 = demo
			'mode' => array('label' => 'Operating Mode',
							'desc' => 'Normal is the standard operation mode; Maintenance will refuse connections; Demo/Kiosk mode will favor local resources.',
							'input' => 'dropdown',
							'dropdown_values' => array(
									'normal' => array('label' => 'Normal', ),
									'maintenance' => array('label' => 'Maintenance', ),
									'demo' => array('label' => 'Demo/Kiosk', ),
							),
			),
	);
	
	public $auth = array(
			'register_url' => array('label'=>'Registration URL', 'desc'=>'URL for the registration page.', ),
			'login_url' => array('label'=>'Login URL', 'desc'=>'URL for the login page.', ),
			'logout_url' => array('label'=>'Logout URL', 'desc'=>'URL for the logout page.', ),
	);
	

}//end class

}//end namespace
