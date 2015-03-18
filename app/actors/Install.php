<?php
namespace BitsTheater\actors;
use BitsTheater\actors\Understudy\BitsInstall as BaseActor;
use BitsTheater\scenes\Install as MyScene;
	/* @var $v MyScene */
use com\blackmoonit\database\DbConnInfo;
use com\blackmoonit\Strings;
{//namespace begin

class Install extends BaseActor {
	/* exmple connection override	
	public function getDbConns() {
		$db_conns = array();

		$theDbConnInfo = DbConnInfo::asSchemeINI('webapp');
		$theDbConnInfo->dbConnSettings->dbname = 'MyDatabase';
		$theDbConnInfo->dbConnSettings->host = '';
		$theDbConnInfo->dbConnSettings->username = '';
		$db_conns[] = $theDbConnInfo;

		//other connections?
		$theDbConnInfo = DbConnInfo::asSchemeINI('maindb');
		$theDbConnInfo->dbConnOptions->table_prefix = '';
		$theDbConnInfo->dbConnSettings->dbname = 'MainDatabase';
		$theDbConnInfo->dbConnSettings->host = '';
		$theDbConnInfo->dbConnSettings->username = '';
		$db_conns[] = $theDbConnInfo;
	
		return $db_conns;
	}
	*/	
}//end class

}//end namespace

