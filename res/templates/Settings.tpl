<?php
namespace com\blackmoonit\bits_theater\app\config;
{//begin namespace
define('_DEBUG_APP',true);

class Settings extends \stdClass {

	const APP_ID = '%app_id%';

	const PAGE_Landing = '/home';
	
	static function getAppId() {
		return self::APP_ID;
	}
	
	static function getLandingPage() {
		return self::PAGE_Landing;
	}
	
}//end class

}//end namespace
