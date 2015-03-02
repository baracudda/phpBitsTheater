<?php
namespace BitsTheater\actors;
use BitsTheater\actors\Understudy\BitsInstall as BaseActor;
use BitsTheater\scenes\Install as MyScene;
	/* @var $v MyScene */
use com\blackmoonit\database\DbConnInfo;
use com\blackmoonit\Strings;
{//namespace begin

class Install extends BaseActor {
	
	public function getDbConns() {
		$db_conns = array();
	
		$theDbConnInfo = DbConnInfo::asSchemeINI('webapp');
		$theDbConnInfo->dbConnSettings->dbname = 'Fresnel';
		$theDbConnInfo->dbConnSettings->host = '';
		$theDbConnInfo->dbConnSettings->username = '';
		$db_conns[] = $theDbConnInfo;
	
		$theDbConnInfo = DbConnInfo::asSchemeINI('fresnel');
		$theDbConnInfo->dbConnOptions->table_prefix = '';
		$theDbConnInfo->dbConnSettings->dbname = 'Fresnel';
		$theDbConnInfo->dbConnSettings->host = '';
		$theDbConnInfo->dbConnSettings->username = '';
		$db_conns[] = $theDbConnInfo;
	
		return $db_conns;
	}
	
}//end class

}//end namespace

