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

class DbConnInfo extends BaseDbConnInfo {
	public $dbConnName = 'webapp';
	public $dbName = null;
	public $table_prefix = '';	//prefix for every table used by this connection
	/**
	 * @var PDO
	 */
	public $dbConn = null; 		//the actual, open connection

	/**
	 * Setup this class for use.
	 * @param array $aDbConn - use this connection. If null, create a new one.
	 */
	public function __construct($aDbConnName) {
		parent::__construct();
		if (!empty($aDbConnName)) {
			$this->dbConnName = $aDbConnName;
		}
	}
	
	public function __destruct() {
		$this->disconnect();
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
		return file_exists($this->getConfigFilePath());
	}
	
	/**
	 * Connects to the database and returns the connection.
	 * @return PDO Returns the connection when successful, FALSE if the attempt failed.
	 */
	public function connect() {
		if (empty($this->dbConn) && $this->canAttemptConnectDb()) {
			$theCfgFile = $this->getConfigFilePath();
			$this->loadDbConnInfoFromIniFile($theCfgFile);
			$this->table_prefix = $this->dbConnOptions->table_prefix;
			if (!empty($this->dbConnSettings) && (!empty($this->dbConnSettings->dbname))) {
				$this->dbName = $this->dbConnSettings->dbname;
			}
			$this->dbConn = $this->getPDOConnection();
			unset($this->dbConnSettings);
			unset($this->username);
			unset($this->password);
		} elseif (empty($this->dbConn)) {
			throw new DbException(null,'Failed to connect: '.str_replace(BITS_CFG_PATH,'"[%config]'.¦,$this->getConfigFilePath()).'" not found.');
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
