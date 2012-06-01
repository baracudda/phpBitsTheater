<?php
namespace app\config;
{
define('_DEBUG_APP',true);

class Settings extends \stdClass {

	const APP_ID = '%app_id%';

	const PAGE_Landing = '/home';
	
	//the table name prefix prepended to all table names used by this app.
	const TABLE_PREFIX = '%table_prefix%';

}//end class

}//end namespace
