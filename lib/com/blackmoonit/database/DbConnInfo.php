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

namespace com\blackmoonit\database;
use com\blackmoonit\database\DbConnOptions;
use com\blackmoonit\database\DbConnSettings;
use \InvalidArgumentException;
use \RuntimeException;
use \PDO;
{//begin namespace

/**
 * PDO connection information class, useful instead of an associative array.
 */
class DbConnInfo {
	const INI_SECTION_DB_OPTIONS = 'dbopts';
	const INI_SECTION_DB_CONN_INFO = 'dbconn';
	
	/**
	 * @var DbConnOptions
	 */
	public $dbConnOptions;
	
	/**
	 * @var DbConnSettings
	 */
	public $dbConnSettings;
	
	public $dns;
	public $username;
	public $password;
	
	/**
	 * @return DbConnInfo Returns the newly constructed object.
	 */
	public function __construct() {
		$this->dbConnOptions = new DbConnOptions();
		$this->dbConnSettings = new DbConnSettings();
	}
	
	/**
	 * Once the $dbConnOptions and $dbConnSettings info is set, calculate our PDO connection params.
	 * @throws InvalidArgumentException
	 */
	protected function calcPDOparams() {
		if (!empty($this->dbConnOptions)) {
			switch ($this->dbConnOptions->dns_scheme) {
				case DbConnOptions::DB_CONN_SCHEME_INI:
					if (!empty($this->dbConnSettings)) {
						$this->dns = $this->dbConnSettings->getDnsParam();
						$this->username = $this->dbConnSettings->username;
						$this->password = $this->dbConnSettings->password;
					} else {
						throw new InvalidArgumentException('"dbconn" information is missing');
					}
					break;
				case DbConnOptions::DB_CONN_SCHEME_ALIAS:
					$this->dns = $this->dbConnOptions->dns_value;
					break;
				case DbConnOptions::DB_CONN_SCHEME_URI:
					$this->dns = 'uri:'.$this->dbConnOptions->dns_value;
					break;
				default:
					$this->dns = $this->dbConnOptions->dns_scheme.':'.$this->dbConnOptions->dns_value;
					break;
			}//switch
		} else {
			throw new InvalidArgumentException('"dbopts" information is missing');
		}
	}
	
	/**
	 * Load up an INI file containing the information we need to make a db connection.
	 * 
	 * ;comments begin with semi-colon
	 * [dbopts]
	 * table_prefix = "webapp_"
	 * dns_scheme = "ini"
	 * ;if dns_scheme is not "ini", custom PDO dns string supplied in dns_value
	 * dns_value = ""
	 * 
	 * ;if using "ini" scheme, following section is mantatory, otherwise its unnessessary.
	 * [dbconn]
	 * driver = mysql
	 * host = localhost
	 * ;port = 3306
	 * dbname = my_db_name
	 * username = rootbeer
	 * password = "DoubleHelix!"
	 * 
	 * @param string $aIniFilePath - filename containing the INI information needed.
	 * @throws InvalidArgumentException if various data points are missing
	 * @throws RuntimeException if unable to import the file.
	 */
	public function loadDbConnInfoFromIniFile($aIniFilePath) {
		if (empty($aIniFilePath)) {
			throw new InvalidArgumentException('Db INI filename is empty.');
		}
		if ($theConfig = parse_ini_file($aIniFilePath, TRUE)) {
			$this->dbConnOptions->copyFromArray($theConfig[self::INI_SECTION_DB_OPTIONS]);
			if (!empty($theConfig[self::INI_SECTION_DB_CONN_INFO]))
				$this->dbConnSettings->copyFromArray($theConfig[self::INI_SECTION_DB_CONN_INFO]);
			try {
				$this->calcPDOparams();
			} catch (InvalidArgumentException $e) {
				throw new InvalidArgumentException($e->getMessage().' from INI file '.basename($aIniFilePath));
			}
		} else {
			throw new RuntimeException('Unable to import '.basename($aIniFilePath).'.');
		}
	}
	
	/**
	 * Load pertinent database connection information from INI config file.
	 * @param string $aIniFilePath - the path and filename info.
	 * @return DbConnInfo Returns the database settings as this object.
	 * @throws InvalidArgumentException if various data points are missing
	 * @throws RuntimeException if unable to import the file.
	 */
	static public function readDbConnInfo($aIniFilePath) {
		$o = new DbConnInfo();
		if (empty($aIniFilePath))
			$aIniFilePath = $this->dbConnOptions->ini_filename;
		$o->loadDbConnInfoFromIniFile($aIniFilePath);
		return $o;
	}
	
	/**
	 * Get a PDO database connection.
	 * @return PDO Returns the PDO connection.
	 */
	public function getPDOConnection() {
		$theResult = null;
		if (!empty($this->username) && !empty($this->password))
			$theResult = new PDO($this->dns,$this->username,base64_decode($this->password));
		else
			$theResult = new PDO($this->dns);
		$theResult->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
		$theResult->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		return $theResult;
	}
	
}//class

}//namespace
