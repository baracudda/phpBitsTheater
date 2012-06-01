<?php
namespace app\config;
{
define('_DEBUG_APP',true);

class Settings extends \stdClass {

	const APP_ID = 'BD8A13E4-0CF0-4785-53E7-9DF49F863B3B';

	const PAGE_Landing = '/home';
	
	//the table name prefix prepended to all table names used by this app.
	const TABLE_PREFIX = 'bits_';

}//end class

}//end namespace
