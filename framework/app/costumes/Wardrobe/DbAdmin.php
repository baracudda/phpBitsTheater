<?php
/*
 * Copyright (C) 2018 Blackmoon Info Tech Services
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace BitsTheater\costumes\Wardrobe;
use BitsTheater\costumes\ABitsCostume as BaseCostume;
use BitsTheater\Model as BasicModel;
use BitsTheater\Scene as BasicScene;
use BitsTheater\costumes\DbConnInfo;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\BrokenLeg;
use BitsTheater\models\Config as ConfigDB;
use com\blackmoonit\database\DbUtils;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\exceptions\IllegalArgumentException;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * Database administration class used for privileged admin functions.
 * @since BitsTheater v4.0.0
 */
class DbAdmin extends BaseCostume
{ use WornByIDirectedForValidation;

	/**
	 * Create the database using the provided dbconn inputs.
	 * @param object $aDbConnInput - the dbconn input from the user (CONSIDER IT TOXIC!).
	 * @param DbConnInfo $aDbConn - (optional) newly defined connection.
	 * @return $this Returns $this for chaining.
	 */
	public function createDbFromUserInput( $aDbConnInput, DbConnInfo $aDbConn=null )
	{
		$this->sanitizeDbConnInput($aDbConnInput);
		// enforce certain fields to be non-empty
		$this->checkIsNotEmpty('dbname', $aDbConnInput->dbname)
			->checkIsNotEmpty('dbconn', $aDbConnInput->dbconn)
			;
		switch ($aDbConnInput->dbtype) {
			case DbUtils::DB_TYPE_MYSQL:
				return $this->createDbAndUserForMySql( $aDbConnInput, $aDbConn );
			default:
				//TODO what is the typical process for create user/db?
				return $this;
		}//switch
	}
	
	/**
	 * Sanitize the input so we can rely on simple names for databases and users.
	 * @param object $aDbConnInput - the user input contained in an object.
	 * @param string $aInputFieldName - the property name to check in the object.
	 * @param boolean $bAllowEmpty - if TRUE, this method will not throw an exception if empty.
	 * @throws BrokenLeg If $bAllowEmpty is FALSE (the default) then an exception will be
	 *    thrown if the value is missing before or after sanitization.
	 */
	public function sanitizeDbInfoInput( $aDbConnInput, $aInputFieldName, $bAllowEmpty=false )
	{
		//$this->logStuff(__METHOD__, ' [', $aInputFieldName, ']=', $aDbConnInput->{$aInputFieldName}); //DEBUG
		// if empty is not allowed, toss exception if $aInputFieldName is empty
		if ( !$bAllowEmpty && empty($aDbConnInput->{$aInputFieldName}) )
		{ throw BrokenLeg::toss($this, BrokenLeg::ACT_MISSING_VALUE, $aInputFieldName); }
		// sanitize the user input for org_name, which will become the dbname
		//   lacking a "standard dbname" format, if it can be a filename, its good enough
		$aDbConnInput->{$aInputFieldName} = Strings::sanitizeFilename(
				strtolower(trim($aDbConnInput->{$aInputFieldName})), null
		);
		// if empty is not allowed, toss exception if sanitized $aInputFieldName is empty
		if ( !$bAllowEmpty && empty($aDbConnInput->{$aInputFieldName}) )
		{
			throw BrokenLeg::pratfall('BAD_REQUEST', BrokenLeg::HTTP_BAD_REQUEST,
					$this->getRes('auth', 'msg_sanitized_input_is_empty', $aInputFieldName)
			);
		}
		//$this->logStuff(__METHOD__, ' [', $aInputFieldName, '] sanitizedValue=', $aDbConnInput->{$aInputFieldName}); //DEBUG
	}
	
	/**
	 * Database information requires a lot of scrutiny and sanitization.
	 * @param object $aDbConnInput - the object containing the input data.
	 * @throws BrokenLeg Throws exceptions for missing fields and sanitization blank-outs.
	 */
	protected function sanitizeDbConnInput( $aDbConnInput )
	{
		// ensure an attacker did not define our var for us ;)
		$aDbConnInput->dbconn = null;
		// we always need a dbname, sanitize it
		if ( empty($aDbConnInput->dbname) )
		{ $aDbConnInput->dbname = $aDbConnInput->org_name; }
		$this->sanitizeDbInfoInput($aDbConnInput, 'dbname');
		// get the current APP_DB_CONN_NAME info
		$theAppDbConn = new DbConnInfo(APP_DB_CONN_NAME);
		$theAppDbConn->loadDbConnInfo();
		// if nothing else supplied about db info, assume current connection info
		if ( empty($aDbConnInput->dbuser) && empty($aDbConnInput->dbpass)
				&& empty($aDbConnInput->dbhost) && empty($aDbConnInput->dbport)
				&& empty($aDbConnInput->dbtype) && empty($aDbConnInput->dbcharset)
				&& empty($aDbConnInput->table_prefix) )
		{
			$aDbConnInput->dbtype = $theAppDbConn->dbConnSettings->driver;
			$aDbConnInput->dbcharset = $theAppDbConn->dbConnSettings->charset;
			$aDbConnInput->table_prefix = $theAppDbConn->dbConnOptions->table_prefix;
			$aDbConnInput->dbhost = $theAppDbConn->dbConnSettings->host;
			$aDbConnInput->dbport = $theAppDbConn->dbConnSettings->port;
			$aDbConnInput->dbuser = $theAppDbConn->dbConnSettings->username;
			$aDbConnInput->dbpass = $theAppDbConn->dbConnSettings->password;
			$aDbConnInput->dbconn = '/' . $aDbConnInput->dbname;
		}
		else {
			// if not supplied, default the dbuser to the dbname
			if ( empty($aDbConnInput->dbuser) )
			{ $aDbConnInput->dbuser = $aDbConnInput->dbname; }
			$this->sanitizeDbInfoInput($aDbConnInput, 'dbuser');
			// if the dbuser is used and dbpass is empty, toss exception
			if ( !empty($aDbConnInput->dbuser) )
			{ $this->checkIsNotEmpty('dbpass', $aDbConnInput->dbpass); }
			// if the dbtype is empty, default to our current dbtype
			if ( empty($aDbConnInput->dbtype) )
			{ $aDbConnInput->dbtype = $theAppDbConn->dbConnSettings->driver; }
			$this->sanitizeDbInfoInput($aDbConnInput, 'dbtype');
			// if the dbhost is empty, default to our current dbhost
			if ( empty($aDbConnInput->dbhost) )
			{ $aDbConnInput->dbhost = $theAppDbConn->dbConnSettings->host; }
			$this->sanitizeDbInfoInput($aDbConnInput, 'dbhost');
			// if the dbport is empty, default to our current dbport
			if ( empty($aDbConnInput->dbport) )
			{ $aDbConnInput->dbport = $theAppDbConn->dbConnSettings->port; }
			// check for a numeric port number
			if ( !empty($aDbConnInput->dbport) )
			{ $aDbConnInput->dbport = Strings::toInt($aDbConnInput->dbport); }
			// if the dbcharset is empty, default to our current dbcharset
			if ( empty($aDbConnInput->dbcharset) )
			{ $aDbConnInput->dbcharset = $theAppDbConn->dbConnSettings->charset; }
			$this->sanitizeDbInfoInput($aDbConnInput, 'dbcharset', true);
			// if the table_prefix is empty, default to our current table_prefix
			if ( empty($aDbConnInput->table_prefix) )
			{ $aDbConnInput->table_prefix = $theAppDbConn->dbConnOptions->table_prefix; }
			// do not allow even whitespace in a table_prefix
			$aDbConnInput->table_prefix = preg_replace('/\W+/m', '_', $aDbConnInput->table_prefix);
			$this->sanitizeDbInfoInput($aDbConnInput, 'table_prefix', true);
			// now put it all together as one big happy dbconn string
			$theDbConnUriQueryData = array();
			if ( !empty($aDbConnInput->table_prefix) )
			{ $theDbConnUriQueryData['table_prefix'] = $aDbConnInput->table_prefix; }
			if ( !empty($aDbConnInput->dbcharset) )
			{ $theDbConnUriQueryData['charset'] = $aDbConnInput->dbcharset; }
			$aDbConnInput->dbconn = $aDbConnInput->dbtype . '://';
			$aDbConnInput->dbconn .= $aDbConnInput->dbuser . ':';
			$aDbConnInput->dbconn .= $aDbConnInput->dbpass . '@';
			$aDbConnInput->dbconn .= $aDbConnInput->dbhost;
			if ( !empty($aDbConnInput->dbport) )
			{ $aDbConnInput->dbconn .= ':' . $aDbConnInput->dbport; }
			$aDbConnInput->dbconn .= '/' . $aDbConnInput->dbname;
			if ( !empty($theDbConnUriQueryData) )
			{ $aDbConnInput->dbconn .= '?' . http_build_query($theDbConnUriQueryData); }
		}
		unset($theAppDbConn);
		// database user "hosts its allowed to connect from" restriction
		if ( empty($aDbConnInput->dbuser_fromhost) )
		{
			switch ($aDbConnInput->dbtype) {
				case DbUtils::DB_TYPE_MYSQL:
					$aDbConnInput->dbuser_fromhost = '%';
					break;
			}
		}
	}
	
	/**
	 * Create the retricted database user and the database to use.
	 * Due to the "server admin" nature of this particular feature,
	 * it is recommended that you have the user pass in the admin creds
	 * to use rather than permanently elevate the website user's priveleges.
	 * <span style="font-family:monospace">admin_dbuser<span> and
	 * <span style="font-family:monospace">admin_dbpass<span> are the credential
	 * fields to use for that.
	 * @param object $aDbConnInput - the <i>sanitized</i> dbconn input from the user.
	 * @param DbConnInfo $aDbConn - (optional) newly defined connection.
	 * @return $this Returns $this for chaining.
	 */
	protected function createDbAndUserForMySql( $aDbConnInput, DbConnInfo $aDbConn=null )
	{
		// first, a new Model object with the default connection
		$theModel = new BasicModel();
		$theModel->director = $this->getDirector();
		if ( !empty($aDbConnInput->admin_dbuser) && !empty($aDbConnInput->admin_dbpass) ) {
			if ( !empty($aDbConn) ) {
				$theWebAppDbConn = clone $aDbConn;
			}
			else {
				// since we do not keep around the sensitive information, we need to reload
				//   the default connection information.
				$theWebAppDbConn = new DbConnInfo();
				$theWebAppDbConn->loadDbConnInfo();
			}
			$theWebAppDbConn->cnvToAdminConn(
					$aDbConnInput->admin_dbuser, $aDbConnInput->admin_dbpass
			);
			unset($aDbConnInput->admin_dbuser); //do not keep around any longer than needed
			unset($aDbConnInput->admin_dbpass); //do not keep around any longer than needed
			// then we have our model object connect to the new connection
			$theModel->connectTo($theWebAppDbConn);
			// now we can let SqlBuilder run its queries using this temp model
			//   which has admin privileges, which go poof at end of method.
		}
		$theSql = SqlBuilder::withModel($theModel)->obtainParamsFrom($aDbConnInput);
		$theSql->beginTransaction();
		try {
			if ( !Strings::beginsWith($aDbConnInput->dbconn, '/') ) {
				// we are changing more than just making a new database, we need a new user as well
				$theResult = $theSql->reset()
					->startWith('SELECT COUNT(*) FROM mysql.user')
					->startWhereClause()
					->mustAddParam('user', $aDbConnInput->dbuser)
					->endWhereClause()
					->add('LIMIT 1')
					//->logSqlDebug(__METHOD__) //DEBUG
					->query()
					;
				if ( empty($theResult) || empty($theResult->fetch(\PDO::FETCH_COLUMN)) ) {
					$theSql->reset()
						->startWith('CREATE USER')
						->add(":dbuser@:dbuser_fromhost")
						->setParam('dbuser', $aDbConnInput->dbuser)
						->setParam('dbuser_fromhost', $aDbConnInput->dbuser_fromhost)
						->add("IDENTIFIED BY :dbpass")
						->setParam('dbpass', $aDbConnInput->dbpass)
						//->logSqlDebug(__METHOD__) //DEBUG
						;
					$ps = $theSql->execDML();
					switch ($ps->errorCode()) {
						case '00000': //SUCCESS! \o/
							$this->logStuff(__METHOD__,
									' created DBUSER [', $aDbConnInput->dbuser, ']'
							);
							break;
						case '42000': //ER_NO_PERMISSION_TO_CREATE_USER
							throw new DbException(null,
									$this->getRes('auth', 'msg_no_permission_to_create_user')
							);
						default:
							$theErrInfo = $ps->errorInfo();
							throw $theSql->newDbException(__METHOD__,
									$this->getRes('msg_generic_create_user_error',
											$theErrInfo[0], $theErrInfo[2]
									)
							);
					}//switch
				}
				else
				{
					$this->logStuff(__METHOD__, ' DBUSER [',
							$aDbConnInput->dbuser . '] already exists.'
					);
				}
			}
			
			// now that we have the dbuser, grant it rights to the soon-to-be db
			$theSql->reset();
			$theSql->startWith('GRANT ALL PRIVILEGES ON')
				->add($theSql->getQuoted($aDbConnInput->dbname) . '.*')
				->add('TO')
				->add(":dbuser@:dbuser_fromhost")
				->setParam('dbuser', $aDbConnInput->dbuser)
				->setParam('dbuser_fromhost', $aDbConnInput->dbuser_fromhost)
				;
			//$theSql->logSqlDebug(__METHOD__); //DEBUG
			$theSql->execDML();
			
			// now that we have a dbuser with rights, create the new db
			$theSql->reset();
			$theSql->startWith('CREATE DATABASE IF NOT EXISTS')
				->add($theSql->getQuoted($aDbConnInput->dbname))
				;
			$theSql->execDML();
			$this->logStuff(__METHOD__,
					' created Database [', $aDbConnInput->dbname, '].'
			);
			$theSql->commitTransaction();
			return $this;
		}
		catch (\PDOException $pdox) {
			$theSql->rollbackTransaction();
			throw $theSql->newDbException(__METHOD__, $pdox);
		}
	}
	
	/**
	 * Once the new database has been created, create all of the tables for the
	 * given model connections.
	 * @param BasicScene $aScene - a Scene object for setupModel() routines.
	 * @param DbConnInfo $aDbConnInfo - the dbconn info to use.
	 * @param string $aDbConnName - the filter for which models to setup.
	 */
	public function setupNewDb( BasicScene $aScene, DbConnInfo $aDbConnInfo, $aDbConnName)
	{
		if ( empty($aDbConnName) )
		{ throw new IllegalArgumentException('$aModelDbConn cannot be empty'); }
		/* @var $theModelList \ReflectionClass[] */
		$theModelList = BasicModel::getAllModelClassInfo(); //gets all non-abstract models
		$theDbConn = $aDbConnInfo->connect();
		$theDbConn->beginTransaction();
		try {
			// copy over our Config model/table first
			$theConfigModelName = ConfigDB::MODEL_NAME;
			/* @var $dbCurrConfig ConfigDB */
			$dbCurrConfig = $this->getProp($theConfigModelName);
			/* @var $dbNewConfig ConfigDB */
			$dbNewConfig = new $theConfigModelName( $this->getDirector() );
			$dbNewConfig->connectTo($aDbConnInfo);
			$dbNewConfig->setupModel($aScene);
			$dbNewConfig->setupFeatureVersion($aScene);
			$theConfigSettings = $dbCurrConfig->getDefinedSettings();
			foreach ($theConfigSettings as $theNamespaceInfo)
			{
				// for each namespace, copy over each setting
				foreach ($theNamespaceInfo->settings_list as $theSetting)
				{
					$dbNewConfig->setConfigValue($theSetting->ns,
							$theSetting->key, $theSetting->getCurrentValue()
					);
				}
			}
			
			foreach ($theModelList as $theMirror) {
				//$this->logStuff(__METHOD__, ' setup? [', $theMirror->getShortName(), ']'); //DEBUG
				// avoid our singleton framework method and create our own model object
				$theModelClassName = $theMirror->getName();
				/* @var $theModel \BitsTheater\Model */
				$theModel = new $theModelClassName( $this->getDirector() );
				if ( $theModel->dbConnName==$aDbConnName ) {
					// found a model we need to setup
					$theModel->connectTo($aDbConnInfo);
					// call the setup methods for it
					if ( $theMirror->hasMethod('setupModel') )
					{ $theModel->setupModel($aScene); }
					if ( $theMirror->hasMethod('setupDefaultData') )
					{ $theModel->setupDefaultData($aScene); }
					if ( $theMirror->hasMethod('setupFeatureVersion') )
					{ $theModel->setupFeatureVersion($aScene); }
				}
				unset($theModel); //recycle for immediate reuse
			}
			$theDbConn->commit();
		}
		catch ( \Exception $x )
		{
			$theDbConn->rollBack();
			throw BrokenLeg::tossException($this, $x);
		}
	}
	
}//end class

}//end namespace
