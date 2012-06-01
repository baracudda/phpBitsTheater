<?php
namespace res\en;
use res\Resources;
{

class Config extends Resources {

	public $namespace = array(
				'auth' => array('label'=>'Authorization', 'desc'=>'Determines how identity is discovered.', ),
				'game_server' => array('label'=>'Server/Realm Information', 'desc'=>'Server/Realm details.', ),
			);
			
	public $auth = array(
				'register_url' => array('label'=>'Registration URL', 'desc'=>'URL for the registration page.', ),
				'login_url' => array('label'=>'Login URL', 'desc'=>'URL for the login page.', ),
				'logout_url' => array('label'=>'Logout URL', 'desc'=>'URL for the logout page.', ),
				'smf_path' => array('label'=>'SMF Path', 'desc'=>'Path to your installed SMF forums', ),
			);
	
	public $game_server = array(
				'name' => array('label' => 'Server Name', 'desc'=>'All events take place on this server.'),
				'timezone' => array('label'=>'Timezone', 'desc'=>'All scheduled events will be located in this timezone. 
						<a target="_blank" href="http://www.php.net/manual/en/timezones.php">List of Supported Timezones.</a>'),
			);

}//end class

}//end namespace
