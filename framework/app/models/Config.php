<?php

namespace BitsTheater\models;
use BitsTheater\models\PropCloset\BitsConfig as BaseModel;
use BitsTheater\costumes\colspecs\CommonMySql;
use BitsTheater\costumes\SqlBuilder;
use com\blackmoonit\Strings;
{//begin namespace

class Config extends BaseModel
{
	/**
	 * The name of the model which can be used in IDirected::getProp().
	 * @var string
	 * @since BitsTheater 3.6.1
	 */
	const MODEL_NAME = __CLASS__ ;
	
	/**
	 * Add our database name before the defined table prefix so we can work
	 * with multiple databases at once.
	 * @var boolean This value is TRUE as the intention here is to work with multiple dbs.
	 */
	const TABLE_PREFIX_INCLUDES_DB_NAME = true;
	
	/** @var string */
	public $dbConnName = APP_DB_CONN_NAME;
	
	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->migrateToNewConnName();
	}
	
	/**
	 * Move config table to APP_DB_CONN_NAME db connection, if exists as old name.
	 */
	public function migrateToNewConnName()
	{
		if ( $this->getDirector()->isInstalled() && !$this->exists($this->tnConfig) )
		{
			/* @var $dbOldConfig parent::class */
			$dbOldConfig = $this->getProp( parent::class );
			//$this->logStuff(__METHOD__, ' parent=', $dbOldConfig);
			if ( !$dbOldConfig->exists($dbOldConfig->tnConfig) )
			{ return; } //trivial, if nothing to copy from, no need to copy.
			
			$this->logStuff(__METHOD__, ' migrating Config table to new dbconn [',
					$this->dbConnName, ']');
			$theSql = SqlBuilder::withModel($this);
			//the parent model did not include dbname in its table name, manually mix it in
			$theOldDbName = $dbOldConfig->getDbConnInfo()->dbName;
			$theOldTableName = $theSql->getQuoted($theOldDbName) . '.' .
					$dbOldConfig->getDbConnInfo()->table_prefix .
					parent::TABLE_NAME ;
			$theSql->startWith('INSERT INTO')->add($this->tnConfig)
				->add('SELECT * FROM')->add($theOldTableName)
				;
			//$theSql->logSqlDebug(__METHOD__); //DEBUG
			try
			{
				$this->execDML(CommonMySql::getCreateNewTableAsOldTableSql(
					$this->tnConfig, $theOldTableName));
				$this->logStuff(__METHOD__, ' copied structure to new table [', $this->tnConfig, ']');
				$theSql->execDML();
				$this->logStuff(__METHOD__, ' copied data to new table [', $this->tnConfig, ']');
				if ( !$this->isEmpty($this->tnConfig) )
				{
					//if successfully copied, delete the old table
					$theSql = SqlBuilder::withModel($dbOldConfig);
					$theSql->startWith('DROP TABLE')->add($theOldTableName)
						->execDML()
						;
					$this->logStuff(__METHOD__, ' removed old table [', $theOldTableName, ']');
				}
				$this->logStuff(__METHOD__, ' FINISHED migrating Config table to new dbconn [',
						$this->dbConnName, ']');
			}
			catch ( \PDOException $pdox )
			{ /* eat the exception */ }
		}
	}
	
}//end class

}//end namespace
