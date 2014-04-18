<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
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
use com\blackmoonit\Strings;
use \PDO;
use \PDOException;
use \InvalidArgumentException;
use \RuntimeException;
use \UnexpectedValueException;
use \DateTime;
use \DateTimeZone;
{//begin namespace

class DbUtils {
	const DB_TYPE_CUBRID	= 'cubrid';		//Cubrid
	const DB_TYPE_DBLIB		= 'dblib';		//FreeTDS / Microsoft SQL Server / Sybase
	const DB_TYPE_FIREBIRD	= 'firebird';	//Firebird/Interbase 6
	const DB_TYPE_IBM		= 'ibm';		//IBM DB2
	const DB_TYPE_INFORMIX	= 'informix';	//IBM Informix Dynamic Server
	const DB_TYPE_MYSQL		= 'mysql';		//MySQL 3.x/4.x/5.x
	const DB_TYPE_OCI		= 'oci';		//Oracle Call Interface
	const DB_TYPE_ODBC		= 'odbc';		//ODBC v3 (IBM DB2, unixODBC and win32 ODBC)
	const DB_TYPE_PGSQL		= 'pgsql';		//PostgreSQL
	const DB_TYPE_SQLITE	= 'sqlite';		//SQLite 3 and SQLite 2
	const DB_TYPE_SQLSRV	= 'sqlsrv';		//Microsoft SQL Server / SQL Azure
	const DB_TYPE_4D		= '4d';			//4D
			
	private function __construct() {} //do not instantiate

	/**
	 * [dbconn]
	 * driver = "mysql"
	 * host = "localhost"
	 * ;port = 3306
	 * dbname = "mydatabase"
	 * username = "server_bot"
	 * password = "pw-for-server_bot"
	 * 
	 * @param array $aConfigData
	 */
	static private function cnvDbConnIni2Dns($aConfigData) {
		switch ($aConfigData['dbconn']['driver']) {
			case 'sqlite':
				$theDns = $aConfigData['dbconn']['driver'].':'.$aConfigData['dbconn']['host'];
				break;
			case 'mysql':
			default:
				$theDns = $aConfigData['dbconn']['driver'].':host='.$aConfigData['dbconn']['host'].
						((!empty($aConfigData['dbconn']['port'])) ? (';port='.$aConfigData['dbconn']['port']) : '' ).
						';dbname='.$aConfigData['dbconn']['dbname'];
		}
		return array('dns'=>$theDns,'usr'=>$aConfigData['dbconn']['username'],'pwd'=>$aConfigData['dbconn']['password']);
	}

	/**
	 * Returns the database connection driver being used.
	 * cubrid	PDO_CUBRID	Cubrid
	 * dblib 	PDO_DBLIB 	FreeTDS / Microsoft SQL Server / Sybase
	 * firebird	PDO_FIREBIRD Firebird/Interbase 6
	 * ibm		PDO_IBM 	IBM DB2
	 * informix	PDO_INFORMIX IBM Informix Dynamic Server
	 * mysql	PDO_MYSQL 	MySQL 3.x/4.x/5.x
	 * oci		PDO_OCI 	Oracle Call Interface
	 * odbc		PDO_ODBC 	ODBC v3 (IBM DB2, unixODBC and win32 ODBC)
	 * pgsql	PDO_PGSQL 	PostgreSQL
	 * sqlite	PDO_SQLITE 	SQLite 3 and SQLite 2
	 * sqlsrv	PDO_SQLSRV 	Microsoft SQL Server / SQL Azure
	 * 4d		PDO_4D		4D
	 */
	static public function getDbType(PDO $aDbConn) {
		if (isset($aDbConn)) {
			return $aDbConn->getAttribute(PDO::ATTR_DRIVER_NAME);
		} else {
			return '';
		}
	}

	/**
	 * Given a Db connection and a caught exception, see if it was a Timeout Exception.
	 * @param PDO $aDbConn - a db connection.
	 * @param PDOException $aPDOException - 
	 * @return boolean
	 */
	static public function isDbConnTimeout(PDO $aDbConn, PDOException $aPDOException) {
		switch (self::getDbType($aDbConn)) {
			case self::DB_TYPE_MYSQL:
				return (strpos($aPDOException->getMessage(), '2006 MySQL') !== false);
			default:
				return false;
		}
	}
	
	/**
	 * Load pertinent database connection information from INI config file.
	 * @param string $aConfigPath
	 * @return array The settings are returned as an associative array.
	 * @throws InvalidArgumentException if aConfigPath is empty.
	 * @throws RuntimeException if unable to import the file.
	 */
	static public function readDbConnInfo($aConfigPath) {
		if (empty($aConfigPath)) {
			throw new InvalidArgumentException('Config path is empty.');
		}
		
		$theDefaultConfig = array('dbopts','dbconn');
		$theDefaultConfig['dbopts'] = array_fill_keys(array('table_prefix','dns_scheme','dns_value'),null);
		$theDefaultConfig['dbconn'] = array_fill_keys(array('driver','host','port','dbname','username','password'),null);

		if ($theConfig = parse_ini_file($aConfigPath, TRUE)) {
			$theConfig = array_replace_recursive($theDefaultConfig,$theConfig);
			switch ($theConfig['dbopts']['dns_scheme']) {
				case 'ini':
					$theConfig = array_merge($theConfig,self::cnvDbConnIni2Dns($theConfig));
					break;
				case 'alias':
					$theConfig['dns'] = $theConfig['dbopts']['dns_value'];
					break;
				default:
					$theConfig['dns'] = $theConfig['dbopts']['dns_scheme'].':'.$theConfig['dbopts']['dns_value'];
			}
			return $theConfig;
		} else {
			throw new RuntimeException('Unable to import '.$aConfigPath.'.');
		}
	}
	
	/**
	 * 
	 * @param string $aDnsScheme - 'alias', 'ini', 'uri'
	 * @param string $aDnsValue - string whose parsed value varies by aDnsScheme.
	 */
	static public function getDbConnInfo($aDnsScheme, $aDnsValue) {
		switch ($aDnsScheme) {
			case 'ini':
				return self::getDnsFromIniFile($aDnsValue);
			case 'alias':
				return $aDnsValue;
			default:
				return $aDnsScheme.':'.$aDnsValue;
		}
	}
	
	/**
	 * Returns an array containing ('dns'=>$dnsString, 'usr'=>$username, 'pwd'=>$password) for PDO constructor
	 * 
	 * ;comments begin with semi-colon
	 * [dbconn]
	 * driver = mysql
	 * host = localhost
	 * ;port = 3306
	 * dbname = my_db_name
	 * username = rootbeer
	 * password = "DoubleHelix!"
	 * 
	 * @param string $aConfigFilename - filename containing the INI information needed.
	 * @throws InvalidArgumentException - if the filename is empty.
	 * @throws RuntimeException - if the filename fails to import.
	 */
	static public function getDnsFromIniFile($aConfigFilename) {
		if (empty($aConfigFilename)) {
			throw new InvalidArgumentException('Config filename empty.');
		}
		if (!$theConfig = parse_ini_file($aConfigFilename, TRUE)) {
			throw new RuntimeException('Unable to import '.$aConfigFilename.'.');
		}
		return self::cnvDbConnIni2Dns($theConfig);
	}
	
	/**
	 * Get a PDO database connection.
	 * @param string/array $aDnsInfo - if string, use as dns (see link for acceptable formats); else array(dns,usr,pwd).
	 * @link http://php.net/manual/pdo.construct.php
	 */
	static public function getPDOConnection($aDnsInfo) {
		$theResult = null;
		if (!empty($aDnsInfo['usr']) && !empty($aDnsInfo['pwd']))
			$theResult = new PDO($aDnsInfo['dns'],$aDnsInfo['usr'],$aDnsInfo['pwd']);
		else
			$theResult = new PDO($aDnsInfo['dns']);
		$theResult->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE,PDO::FETCH_ASSOC);
		$theResult->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
		return $theResult;
	}
	
	/*-----------------------------------------------------------------------------
	 * Validation related methods
	 *----------------------------------------------------------------------------*/

	/**
	 * Check to see if required field is set, throw an Exception if it is not set.
	 * @param array $aDataRow - row data.
	 * @param string $aFieldName - fieldname of required column.
	 * @throws UnexpectedValueException - exception thrown if required column is missing data.
	 */
	static public function validateRequiredField(&$aDataRow, $aFieldName) {
		if (!isset($aDataRow[$aFieldName])) {
			throw new UnexpectedValueException("$aFieldName field missing", 417);
		}
	}

	/**
	 * Check all columns listed in $aFieldSet of $aDataRow against the $aValidateMethod.
	 * @param Class $aObj
	 * @param array $aDataRow
	 * @param array $aFieldSet
	 * @param string $aValidateMethod
	 * @param array $aChildSetNames
	 */
	static public function validateRow($aObj, &$aDataRow, &$aFieldSet, $aValidateMethod, &$aChildSetNames=null) {
		if (is_null($aDataRow))
			return;
		foreach ($aFieldSet as $theFieldName) {
			if (method_exists($aObj,$aValidateMethod)) {
				$aObj->$aValidateMethod($aDataRow,$theFieldName);
			} else if (method_exists(__CLASS__,$aValidateMethod)) {
				self::$aValidateMethod($aDataRow,$theFieldName);
			}
		}
		if (is_null($aChildSetNames))
			return;
		foreach ($aChildSetNames as $theChildName) {
			if (isset($aDataRow[$theChildName])) {
				$theChild = $aDataRow[$theChildName];
				self::validateSet($aObj,$theChild,$aFieldSet,$aValidateMethod,$aChildSetNames);
				$aDataRow[$theChildName] = $theChild;
			}
		}
	}

	/**
	 * Validates an array of row-arrays.
	 * @param Class $aObj - class containing validation functions other than those provided in this class.
	 * @param array $aDataSet - array of row-arrays.
	 * @param array $aFieldSet - array of fieldnames to determine which columns will be passed into the $aValidationMethod.
	 * @param string $aValidateMethod - validation method to use.
	 * @param array $aChildSetNames - some row data may contain child row set data, if column matches, recurse into it.
	 */
	static public function validateSet($aObj, &$aDataSet, &$aFieldSet, $aValidateMethod, &$aChildSetNames=null) {
		if (is_null($aDataSet))
			return;
		foreach ($aDataSet as &$theDataRow) {
			self::validateRow($aObj,$theDataRow,$aFieldSet,$aValidateMethod,$aChildSetNames);
		}
	}
	
	/*-----------------------------------------------------------------------------
	 * Various useful conversion routines.
	 *----------------------------------------------------------------------------*/

	/**
	 * In-place conversion from 32char BINARY text field into a well-formed UUID string
	 * @param array $aDataRow
	 * @param string $aFieldName
	 */
	static public function cnvTextId2UUIDField(&$aDataRow, $aFieldName) {
		if (isset($aDataRow[$aFieldName])) {
			$aDataRow[$aFieldName] = Strings::cnvTextId2UUID($aDataRow[$aFieldName]);
		}
	}
	
	/**
	 * In-place conversion from convertion from UUID string into 32char BINARY text field.
	 * @param access $aDataRow
	 * @param string $aFieldName
	 */
	static public function cnvUUID2TextIdField(&$aDataRow, $aFieldName) {
		if (isset($aDataRow[$aFieldName])) {
			$aDataRow[$aFieldName] = Strings::cnvUUID2TextId($aDataRow[$aFieldName]);
		}
	}
	
	/**
	 * In-place conversion from text field into a well-formed UTF8 string
	 * @param access $aDataRow
	 * @param string $aFieldName
	 */
	static public function cnvToUTF8Field(&$aDataRow, $aFieldName) {
		if (isset($aDataRow[$aFieldName])) {
			$aDataRow[$aFieldName] = utf8_encode($aDataRow[$aFieldName]);
		}
	}
	
	/**
	 * In-place conversion from UTF-8 text field into a string
	 * @param access $aDataRow
	 * @param string $aFieldName
	 */
	static public function cnvFromUTF8Field(&$aDataRow, $aFieldName) {
		if (isset($aDataRow[$aFieldName])) {
			$aDataRow[$aFieldName] = utf8_decode($aDataRow[$aFieldName]);
		}
	}

	/**
	 * In-place conversion from Unix timestamp to MySQL timestamp string.
	 * @param access $aDataRow
	 * @param string $aFieldName
	 */
	static public function cnvTimestampUnix2MySql(&$aDataRow, $aFieldName) {
		if (isset($aDataRow[$aFieldName])) {
			$aDataRow[$aFieldName] = Strings::cnvTimestampUnix2MySQL($aDataRow[$aFieldName]);
		}
	}
	
	/**
	 * @return Returns a SQL datetime string representing now() in UTC.
	 */
	static public function utc_now($bUseMicroseconds=false) {
		$theDateTimeUtc = new DateTime('now', new DateTimeZone('UTC') );
		$theFormatStr = ($bUseMicroseconds) ? "Y-m-d\TH:i:s.u\Z" : "Y-m-d\TH:i:s\Z";
		return $theDateTimeUtc->format($theFormatStr);
		//return gmdate("Y-m-d\TH:i:s\Z");
	}

	/**
	 * Convert unfetched rows from a result set and fetch them all into array form.
	 * @param PDOStatement|array $aRowSet - result set or an array
	 * @param string $aFieldNameKey - fieldname to use as key entries.
	 * @param string $aFieldNameValue - fieldname to use as values, if omitted, use entire row array
	 */
	static public function cnvRowsToArray(&$aRowSet, $aFieldNameKey, $aFieldNameValue=null) {
		$theArray = NULL;
		if (!empty($aRowSet)) {
			$theResult = array();
			
			$doMap = function(&$aResults,&$aRow,$aKeyName,$aValueName=null) { 
					if ($aValueName) {
						$aResults[$aRow[$aKeyName]] = $aRow[$aValueName];
					} else {
						$aResults[$aRow[$aKeyName]] = $aRow;
					}
			};			
			
			if (is_array($aRowSet)) {
				foreach($aRowSet as $theRow) {
					$doMap($theResult,$theRow,$aFieldNameKey,$aFieldNameValue);
				}
			} else {
				while(($theRow = $aRowSet->fetch()) !== FALSE) {
					$doMap($theResult,$theRow,$aFieldNameKey,$aFieldNameValue);
				}
			}
		}
		return $theResult;
	}
	
	static public function getColumnFrom2dArray($aKeyName, array &$aArray) {
		return array_map(
				function($val) use ($aKeyName) {
					return $val[$aKeyName];
				}
				,$aArray);
	}

}//class

}//namespace
