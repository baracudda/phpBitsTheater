<?php
namespace com\blackmoonit\bits_theater\res\en;
use com\blackmoonit\bits_theater\res\Resources;
{

class Config extends Resources {

	public $namespace = array(
				'auth' => array('label'=>'Authorization', 'desc'=>'Determines how identity is discovered.', 'group_id'=>1),
			);
			
	public $auth = array(
				'register_url' => array('label'=>'Registration URL', 'desc'=>'URL for the registration page.', ),
				'login_url' => array('label'=>'Login URL', 'desc'=>'URL for the login page.', ),
				'logout_url' => array('label'=>'Logout URL', 'desc'=>'URL for the logout page.', ),
			);
	

}//end class

}//end namespace
