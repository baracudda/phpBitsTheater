<?php
/*
 * Copyright (C) 2014 Blackmoon Info Tech Services
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

namespace BitsTheater;
use com\blackmoonit\database\DbConnInfo as BaseDbConnInfo;
use com\blackmoonit\Strings;
use com\blackmoonit\exceptions\DbException;
use PDO;
{//begin namespace

class DbConnInfo extends BaseDbConnInfo
{
	/** @var string The connection name. */
	public $dbConnName = 'webapp';
	/** @var string The database name used by the connection. */
	public $dbName = null;
	/** @var string Prefix for every table used by this connection */
	public $table_prefix = '';
	/** @var PDO The actual, open connection. */
	public $dbConn = null;
	/** @var boolean Have we successfully loaded our dbconn info yet? */
	public $bDbConnInfoLoaded = false;
	
	/**
	 * Create the object and set the dbConnName, if not empty.
	 * @param string $aDbConnName - (optional) the dbconn name, "webapp" if empty.
	 */
	public function __construct($aDbConnName=null) {
		parent::__construct();
		if (!empty($aDbConnName)) {
			$this->dbConnName = $aDbConnName;
		}
	}
	
	public function __destruct() {
		$this->disconnect();
	}
	
	/**
	 * Factory method for a URI-based new object.
	 * @param string $aURI - the db connection info as URI string.
	 * @param string $aDbConnName - (optional) the connection name for this object.
	 * @return DbConnInfo Returns the newly created object with URI already parsed.
	 */
	static public function fromURI( $aURI, $aDbConnName=null )
	{
		$o = new DbConnInfo($aDbConnName);
		$o->loadDbConnInfoFromString($aURI);
		return $o;
	}
	
	/**
	 * Get the config file including full path.
	 * @return string Returns the file, with path, to the db config.
	 */
	protected function getConfigFilePath() {
		return Strings::format(BITS_CFG_PATH.'dbconn-%s.ini', $this->dbConnName);
	}
	
	/**
	 * Checks for config file existance.
	 * @return boolean Returns TRUE if there is a db config file for our dbConnName.
	 */
	public function canAttemptConnectDb() {
		return file_exists($this->getConfigFilePath())
				|| (getenv('dbconn-' . $this->dbConnName)!=false);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \com\blackmoonit\database\DbConnInfo::calcPDOparams()
	 */
	protected function calcPDOparams()
	{
		parent::calcPDOparams();
		if ( !empty($this->dbConnOptions) && !empty($this->dbConnOptions->table_prefix) )
		{ $this->table_prefix = $this->dbConnOptions->table_prefix; }
		if ( !empty($this->dbConnSettings) && !empty($this->dbConnSettings->dbname) )
		{ $this->dbName = $this->dbConnSettings->dbname; }
	}
	
	/**
	 * {@inheritDoc}
	 * @see \com\blackmoonit\database\DbConnInfo::loadDbConnInfoFromString()
	 */
	public function loadDbConnInfoFromString($aDbConnString)
	{
		//avoid infinite loop, match param with a certain env_var
		$theEnvVar = getenv('dbconn-' . $this->dbConnName);
		if ( Strings::startsWith($aDbConnString, '/') && $aDbConnString != $theEnvVar ) {
			if ( $this->loadDbConnInfo() ) {
				// "/newdbname" dbconn string means "use same connection, just new db name"
				$this->dbConnSettings->dbname = Strings::strstr_after($aDbConnString, '/');
				// re-calc the various PDO params based on the new dbname
				$this->calcPDOparams();
			}
			return $this;
		} else
		{ return parent::loadDbConnInfoFromString($aDbConnString); }
	}
	
	/**
	 * Load db connection information from its configuration file.
	 */
	public function loadDbConnInfo()
	{
		if ( !$this->bDbConnInfoLoaded && $this->canAttemptConnectDb() )
		{
			$theCfgFile = $this->getConfigFilePath();
			if ( file_exists($theCfgFile) )
				$this->loadDbConnInfoFromIniFile($theCfgFile);
			else
				$this->loadDbConnInfoFromString(getenv('dbconn-' . $this->dbConnName));
			$this->bDbConnInfoLoaded = true;
		}
		return ( $this->bDbConnInfoLoaded );
	}
	
	/**
	 * Connects to the database and returns the connection.
	 * @return PDO Returns the connection when successful, FALSE if the attempt failed.
	 */
	public function connect()
	{
		//if PDO connection is empty, attempt to create one
		if ( empty($this->dbConn) ) {
			if ( $this->loadDbConnInfo() )
			{
				$this->dbConn = $this->getPDOConnection();
				//only keep the connection info loaded long enough to
				//  establish the connection object, then remove the
				//  sensitive info so it is not residing in memory
				//  which might get leaked.
				$this->bDbConnInfoLoaded = false;
				unset($this->dbConnSettings);
				unset($this->username);
				unset($this->password);
			}
		}
		//if still empty, we encountered a problem connecting
		if ( empty($this->dbConn) ) {
			if ( !$this->canAttemptConnectDb() )
			{
				$theErrMsg = 'Failed to connect: ' .
						str_replace(BITS_CFG_PATH, '"[%config]' . DIRECTORY_SEPARATOR,
								$this->getConfigFilePath()
						) . '" not found.' ;
			}
			else
			{ $theErrMsg = 'Connection invalid for [' . $this->dbConnName . ']'; }
			$x = new DbException(null, $theErrMsg);
			Strings::errorLog(__METHOD__, ' dbconn=', $this->dbConnName,
					' loaded?=' . ($this->bDbConnInfoLoaded ? 'true' : 'false'),
					' $this=', $this,
					' stk=', $x->getTraceAsString() );
			throw $x;
		}
		return $this->dbConn;
	}
	
	/**
	 * Disconnect from the database. STUB: non-functional!
	 * NOTE: PDO does not have a disconnect at this time.
	 */
	public function disconnect() {
		/* PDO does not have a disconnect at this time
		if (!empty($this->dbConn)) {
			$this->dbConn->disconnect();
		}
		*/
		unset($this->dbConn);
	}
	
}//end class

}//end namespace
