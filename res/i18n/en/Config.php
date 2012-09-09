<?php
namespace com\blackmoonit\bits_theater\res\en;
use com\blackmoonit\bits_theater\res\Resources;
{

class Config extends Resources {

	public $namespace = array(
				'auth' => array('label'=>'Authorization', 'desc'=>'Determines how identity is discovered.', 'group_id'=>1),
				'remote_token' => array('label'=>'Remote Token', 'desc'=>'Challenge/Response to aquire an auth token for a remote device.', ),
			);
			
	public $auth = array(
				'register_url' => array('label'=>'Registration URL', 'desc'=>'URL for the registration page.', ),
				'login_url' => array('label'=>'Login URL', 'desc'=>'URL for the login page.', ),
				'logout_url' => array('label'=>'Logout URL', 'desc'=>'URL for the logout page.', ),
			);
	
	public $remote_token = array(
				'auth_token' => array('label' => 'Auth Token', 'desc'=>'The string required for all REST transactions.'),
				'challenge' => array('label'=>'Challenge', 'desc'=>'The string initiating the retrieval of an Auth Token. Response will be sent.'),
				'response' => array('label'=>'Response', 'desc'=>'String sent in response to a Challenge.'),
			);

}//end class

}//end namespace
