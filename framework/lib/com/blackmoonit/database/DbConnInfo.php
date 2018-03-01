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
use com\blackmoonit\Strings;
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
	 * Used to name the object to differentiate it from other connections.
	 * @var string
	 */
	public $myDbConnName;

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
	 * Standard constructor takes a name and defaults some properties based on the name.
	 * @param string $aDbConnName - the name to use (should be unique if you have more objects).
	 */
	public function __construct($aDbConnName=null, DbConnOptions $aDbConnOptions=null, DbConnSettings $aDbConnSettings=null) {
		$this->myDbConnName = (!empty($aDbConnName)) ? $aDbConnName : 'id-'.Strings::createUUID();
		if (!empty($aDbConnOptions)) {
			$this->dbConnOptions = $aDbConnOptions;
		} else {
			$this->dbConnOptions = new DbConnOptions($aDbConnName);
		}
		if (!empty($aDbConnSettings)) {
			$this->dbConnSettings = $aDbConnSettings;
		} else {
			$this->dbConnSettings = new DbConnSettings();
		}
	}

	/**
	 * Typical db connection information constructor which defaults some data based on the DbConnName.
	 * @param string $aDbConnName - the name to use (should be unique if you have more objects).
	 * @param string $aDbConnDriver - (optional) one of the DbConnSettings::DRIVER_* consts (defaults to MySQL).
	 * @return \com\blackmoonit\database\DbConnInfo - returns the newly constructed object.
	 * @see \com\blackmoonit\database\DbConnSettings
	 */
	static public function asSchemeINI($aDbConnName, $aDbConnDriver=DbConnSettings::DRIVER_MYSQL) {
		$o = new DbConnInfo($aDbConnName, DbConnOptions::asSchemeINI($aDbConnName));
		$o->dbConnSettings->driver = $aDbConnDriver;
		return $o;
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
	 * Given a DbConnection string, parse the information we need to make a db connection.
	 * @param string $aDbConnString - a connection string composed like the following:
	 * <ol type="1">
	 *   <li>driver://dbuser:dbpw@dbhost:dbport/dbname?prefix="webapp_"&charset="utf8mb4"</li>
	 *   <li>driver://env_var@dbhost:dbport/dbname?prefix="webapp_"&charset="utf8mb4"</li>
	 *   <li><span style="font-family:monospace">env_var</span> (which may contain any
	 *      of the above formats)</li>
	 * </ol>
	 * @return $this Returns $this for chaining.
	 * @throws InvalidArgumentException if various data points are missing
	 */
	public function loadDbConnInfoFromString($aDbConnString)
	{
		if ( empty($aDbConnString) )
			throw new InvalidArgumentException('$aDbConnString is empty.');
		if ( strpos($aDbConnString, '://')>0 ) {
			$this->dbConnOptions->dns_scheme = DbConnOptions::DB_CONN_SCHEME_INI;
			$theParsedParts = parse_url($aDbConnString);
			// Potential keys within this array are: scheme - e.g. http,
			//   host, port, user, pass, path, query - after the question mark ?,
			//   fragment - after the hashmark #
			if ( !empty($theParsedParts['scheme']) )
			{ $this->dbConnSettings->driver = $theParsedParts['scheme']; }
			if ( !empty($theParsedParts['host']) )
			{ $this->dbConnSettings->host = $theParsedParts['host']; }
			if ( !empty($theParsedParts['port']) )
			{ $this->dbConnSettings->port = $theParsedParts['port']; }
			if ( !empty($theParsedParts['user']) && empty($theParsedParts['pass']) ) {
				$theUserPw = getenv($theParsedParts['user']);
				$theParsedParts['user'] = strstr($theUserPw, ':', true);
				$theParsedParts['pass'] = Strings::strstr_after($theUserPw, ':');
			}
			if ( !empty($theParsedParts['user']) )
			{ $this->dbConnSettings->username = $theParsedParts['user']; }
			if ( !empty($theParsedParts['pass']) )
			{ $this->dbConnSettings->password = $theParsedParts['pass']; }
			if ( !empty($theParsedParts['path']) )
			{ $this->dbConnSettings->dbname = trim($theParsedParts['path'], '/'); }
			if ( !empty($theParsedParts['query']) ) {
				$theQueryParts = explode('&', $theParsedParts['query']);
				if ( !empty($theQueryParts['prefix']) )
				{ $this->dbConnOptions->table_prefix = $theQueryParts['prefix']; }
				if ( !empty($theQueryParts['table_prefix']) )
				{ $this->dbConnOptions->table_prefix = $theQueryParts['table_prefix']; }
				if ( !empty($theQueryParts['charset']) )
				{ $this->dbConnSettings->charset = $theQueryParts['charset']; }
			}
			$this->calcPDOparams();
			return $this;
		}
		else
		{ return $this->loadDbConnInfoFromString(getenv($aDbConnString)); }
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
		$theFilename = basename($aIniFilePath,'.ini');
		if (Strings::beginsWith($theFilename,'dbconn-')) {
			$this->myDbConnName = Strings::strstr_after($theFilename,'dbconn-');
			$this->dbConnOptions->myDbConnName = $this->myDbConnName;
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
