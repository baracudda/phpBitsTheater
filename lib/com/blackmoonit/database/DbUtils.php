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
use \UnexpectedValueException;
use \DateTime;
use \DateTimeZone;
use \DateInterval;
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
	
	const DATETIME_FORMAT_DEF_STD = 'Y-m-d H:i:s' ;        // standard date-time
	const DATETIME_FORMAT_DEF_USEC = 'Y-m-d H:i:s.u' ;      // with microseconds
	
	private function __construct() {} //do not instantiate

	/**
	 * Returns the database connection driver being used.
	 * cubrid	DB_TYPE_CUBRID		Cubrid
	 * dblib 	DB_TYPE_DBLIB 		FreeTDS / Microsoft SQL Server / Sybase
	 * firebird	DB_TYPE_FIREBIRD 	Firebird/Interbase 6
	 * ibm		DB_TYPE_IBM 		IBM DB2
	 * informix	DB_TYPE_INFORMIX 	IBM Informix Dynamic Server
	 * mysql	DB_TYPE_MYSQL 		MySQL 3.x/4.x/5.x
	 * oci		DB_TYPE_OCI 		Oracle Call Interface
	 * odbc		DB_TYPE_ODBC 		ODBC v3 (IBM DB2, unixODBC and win32 ODBC)
	 * pgsql	DB_TYPE_PGSQL 		PostgreSQL
	 * sqlite	DB_TYPE_SQLITE 		SQLite 3 and SQLite 2
	 * sqlsrv	DB_TYPE_SQLSRV 		Microsoft SQL Server / SQL Azure
	 * 4d		DB_TYPE_4D			4D
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
	 * @return string Returns a SQL datetime string representing now() in UTC.
	 */
	static public function utc_now($bUseMicroseconds=false) {
		/* PHP is bugged, the "u" format does not work!
		$theDateTimeUtc = new DateTime('@'.microtime(true), new DateTimeZone('UTC') );
		$theFormatStr = ($bUseMicroseconds) ? "Y-m-d\TH:i:s.u\Z" : "Y-m-d\TH:i:s\Z";
		return $theDateTimeUtc->format($theFormatStr);
		*/
		if ($bUseMicroseconds) {
			$nowTs =microtime(true);
			$nowTs_int = (int) floor($nowTs);
			$nowTs_ms = (int) round(($nowTs - floor($nowTs)) * 1000000.0, 0);
			return date("Y-m-d\TH:i:s.").sprintf('%06d',$nowTs_ms).'Z';
		} else {
			return gmdate("Y-m-d\TH:i:s\Z");
		}
	}

	/**
	 * Convert unfetched rows from a result set and fetch them all into array form.
	 * @param PDOStatement|array $aRowSet - result set or an array
	 * @param string $aFieldNameKey - fieldname to use as key entries.
	 * @param string $aFieldNameValue - fieldname to use as values, if omitted, use entire row array
	 * @return array Returns the dataset as an array with keys based on $aFieldNameKey.
	 */
	static public function cnvRowsToArray(&$aRowSet, $aFieldNameKey, $aFieldNameValue=null) {
		$theResult = array();
		if (!empty($aRowSet)) {
			$doMap = null;
			if (!empty($aFieldNameValue)) {
				$doMap = function(&$aResults,&$aRow,$aKeyName,$aValueName) {
						$aResults[$aRow[$aKeyName]] = $aRow[$aValueName];
				};
			} else {
				$doMap = function(&$aResults,&$aRow,$aKeyName,$aValueName) {
						$aResults[$aRow[$aKeyName]] = $aRow;
				};
			}
			foreach($aRowSet as $theRow) {
				$doMap($theResult,$theRow,$aFieldNameKey,$aFieldNameValue);
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
	
	/**
	 * Get the DateTime object from "days ago" number. 0 = today.
	 * @param number $aDaysAgo - how far back should date go
	 * @return DateTime Returns the DateTime object.
	 */
	static public function cnvDaysToDateTime($aDaysAgo) {
		$theDate = new DateTime();
		//today at midnight
		$theDate->setTime(0,0,0);
		if (!empty($aDaysAgo)) {
			$theDate->sub(new DateInterval('P'.$aDaysAgo.'D'));
		} // else will get UTC of last midnight
		return $theDate;
	}
	
	/**
	 * Calculate the datetime string from a "days ago" number. 0 = today.
	 * @param number $aDaysAgo - how far back should date go
	 * @param string $aDateFormat - datetime format to return. Defaults to 'Y-m-d H:i:s'.
	 * @return DateTime Returns the DateTime object.
	 */
	static public function cnvDaysToDateTimeStr($aDaysAgo, $aDateFormat='Y-m-d H:i:s') {
		return self::cnvDaysToDateTime($aDaysAgo)->format($aDateFormat);
	}

	
}//class

}//namespace
