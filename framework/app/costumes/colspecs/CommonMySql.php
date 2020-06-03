<?php
/*
 * Copyright (C) 2016 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes\colspecs;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * Common column specifications using MySQL syntax.
 *
 *
 * !!! We cannot construct a const from other consts, so cannot use concatenation here !!!
 *
 */
class CommonMySql
{
	/**
	 * Defines the generic Unicode character set and collation in use.
	 * @var string
	 * @since v4.0.0
	 */
	const DEFAULT_UNICODE_SPEC = 'CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci';
	
	/**
	 * Use this to specify primary and foreign key columns
	 * @var string
	 */
	const TYPE_UUID = 'char(36) CHARACTER SET ascii COLLATE ascii_bin' ;
	/**
	 * Boolean style field defaulting to FALSE.
	 * @var string
	 */
	const TYPE_BOOLEAN_0 = 'TINYINT(1) NOT NULL DEFAULT 0' ;
	/**
	 * Boolean style field defaulting to TRUE.
	 * @var string
	 */
	const TYPE_BOOLEAN_1 = 'TINYINT(1) NOT NULL DEFAULT 1' ;

	/**
	 * SQL text for programmatically limited/enumerated string values.
	 * @param integer $aCharLength - the string length of the column.
	 * @return string Returns the SQL used for Char(x) with ASCII binary sorting.
	 */
	static public function TYPE_ASCII_CHAR( $aCharLength )
	{ return 'CHAR(' . $aCharLength . ') CHARACTER SET ascii COLLATE ascii_bin'; }
	
	/**
	 * SQL text for generic string values (including emojis).
	 * @param integer $aCharLength - the string length of the column.
	 * @return string Returns the SQL used for Char(x) with UTF8 generic sorting.
	 */
	static public function TYPE_UNICODE_CHAR( $aCharLength )
	{ return 'VARCHAR(' . $aCharLength . ') ' . self::DEFAULT_UNICODE_SPEC; }
	
	/**
	 * Dummy time to use for non-null timestamp columns.
	 * @var string
	 */
	const DUMMY_TIME = '1970-01-01 00:00:01' ;
	
	/**
	 * Fully-defines a column for tracking a row's creation time.
	 * We cannot construct a const from other consts, so cannot use DUMMY_TIME here.
	 * We supply a default value to avoid INSERT errors.
	 * @var string
	 */
	const CREATED_TS_SPEC =
			"created_ts timestamp NOT NULL DEFAULT '" . self::DUMMY_TIME . "'" ;
	/**
	 * Fully-defines a column for tracking a row's update time.
	 * In MySQL, only one column may use the CURRENT_TIMESTAMP default.
	 * @var string
	 */
	const UPDATED_TS_SPEC =
			'updated_ts timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP' ;
	/**
	 * Typical field definition for storing account names.
	 */
	const ACCOUNT_NAME_SPEC = 'VARCHAR(60) ' . self::DEFAULT_UNICODE_SPEC . ' NULL';
	/**
	 * User that created a record may be recorded.
	 * @var string
	 */
	const CREATED_BY_SPEC = '`created_by` ' . self::ACCOUNT_NAME_SPEC ;
	/**
	 * User that updated a record may be recorded.
	 * @var string
	 */
	const UPDATED_BY_SPEC = '`updated_by` ' . self::ACCOUNT_NAME_SPEC ;
	/**
	 * Version of this current record
	 * @var string
	 */
	const VERSION_NUM_SPEC = '`version_num` INT(11) NULL' ;
	/**
	 * UUID of the record that replaced this one
	 * @var string
	 */
	const VERSION_REPLACED_BY_SPEC =
			"`replaced_by` char(36) CHARACTER SET ascii COLLATE ascii_bin NULL" ;
	
	/**
	 * Default Unicode table specification.
	 * "utf8mb4" requires MySQL 5.5+, else use "utf8".
	 * @var string
	 */
	const TABLE_SPEC_FOR_UNICODE = self::DEFAULT_UNICODE_SPEC ;
	
	/**
	 * Default ASCII table specification.
	 * @var string
	 */
	const TABLE_SPEC_FOR_ASCII = 'CHARACTER SET ascii COLLATE ascii_bin';
	
	/**
	 * A regular expression which will recognize SQL date strings when used
	 * as the pattern in <code>preg_match()</code>. This is used when
	 * discovering SQL datetime fields to be converted to ISO 8601 format.
	 * @var string
	 * @see \BitsTheater\costumes\colspecs\CommonMySql::deepConvertSQLTimestampsToISOFormat()
	 * @link https://stackoverflow.com/a/11510391
	 * @since BitsTheater 3.8.1
	 */
	const SQL_DATETIME_REGEX =
		"/^\d\d\d\d-(\d)?\d-(\d)?\d \d\d:\d\d:\d\d$/" ;

	/**
	 * A regular expression which will recognize ISO8601 date strings when used
	 * as the pattern in <code>preg_match()</code>. This is used when
	 * discovering ISO8601 datetime fields to be converted to MySQL format.
	 * @var string
	 * @see \BitsTheater\costumes\colspecs\CommonMySql::deepConvertISO8601DateTimeToMySQLFormat()
	 * @since BitsTheater 4.3.1
	 */
	const ISO8601_DATETIME_REGEX = "/^\d\d\d\d-\d\d-\d\dT\d\d:\d\d:\d\dZ$/";
	
	/**
	 * Used to avoid infinite recursion while performing the deepConvert*() methods.
	 * @var string
	 */
	const DEEP_CONVERT_FLAG = '__DEEP_CONVERT_FLAG';
	
	/**
	 * Insert standard audit fields into a table definition.
	 * @return string
	 */
	static public function getAuditFieldsForTableDefSql() {
		return
				self::CREATED_BY_SPEC.', '.
				self::UPDATED_BY_SPEC.', '.
				self::CREATED_TS_SPEC.', '.
				self::UPDATED_TS_SPEC
		;
	}
	/**
	 * Insert standard versioning fileds into a table definition.
	 * @return string
	 */
	static public function getVersioningFieldsForTableDefSql()
	{
		return
				self::VERSION_NUM_SPEC.', '.
				self::VERSION_REPLACED_BY_SPEC;
	}
	
	/**
	 * The SQL, when executed, results in 'name' and 'size' columns.
	 * @param string $aDb - the database name.
	 * @param string $aTableName - the table name.
	 * @param string $aFieldName - the field name.
	 * @return string Returns the SQL necessary to get the currently defined field size.
	 */
	static public function getFieldSizeSql($aDb, $aTableName, $aFieldName)
	{
		return 'SELECT COLUMN_NAME AS name, (IfNull(CHARACTER_MAXIMUM_LENGTH,0)+IfNull(NUMERIC_PRECISION,0)) AS size'.
				' FROM information_schema.COLUMNS'.
				" WHERE TABLE_SCHEMA = '{$aDb}'".
				" AND TABLE_NAME = '{$aTableName}'".
				" AND COLUMN_NAME = '{$aFieldName}'";
	}

	/**
	 * Helper method to parse through deep PHP array object, finding and updating all timestamp fields
	 * to an ISO 8601 format.
	 * @param array $objectArray PHP array object with keys and values. Can be a "deep array".
	 * @return array The modified $objectArray object. In case passed in $objectArray is not an array at all,
	 * simply returns back $objectArray.
	 * @deprecated (3.8.1) replaced by deepConvertSQLTimestampsToISOFormat()
	 * @see \BitsTheater\costumes\colspecs\CommonMySql::deepConvertSQLTimestampsToISOFormat()
	 */
	static public function convertTimestampsToISO($objectArray) {
		// Check to ensure passed-in array object is indeed an array.
		if ( (array) $objectArray !== $objectArray ) {
			// If not, simply return the object as is.
			return $objectArray;
		}

		// Iterate through array object.
		foreach($objectArray as $thisKey => $thisValue)
		{
			// If $thisValue is not an array.
			if ( (array) $thisValue !== $thisValue ) {

				// Detect if this key is a timestamp.
				if (preg_match('/_ts$/', $thisKey) == 1) {

					// If so, convert timestamp value to ISO 8601 format.
					$thisValue = CommonMySql::convertSQLTimestampToISOFormat($thisValue);
					$objectArray[$thisKey] = $thisValue;
				}
			} else {
				// If $thisValue is an array itself, also check this array, in a "deep" fashion.
				$thisValue = CommonMySql::convertTimestampsToISO($thisValue);
				$objectArray[$thisKey] = $thisValue;
			}
		}
		return $objectArray;
	}
	
	/**
	 * Searches an object for fields that look like timestamps, and updates
	 * them to ISO 8601 format.
	 * @param array|object|string $aData the data to be converted; may be
	 *  an array, object, or scalar
	 * @return array|object|string the same object, with all SQL datetime
	 *  values replaced by ISO 8601 datetime values.
	 * @see \BitsTheater\costumes\colspecs\CommonMySql::SQL_DATETIME_REGEX
	 * @since BitsTheater 3.8.1
	 */
	static public function deepConvertSQLTimestampsToISOFormat( $aData )
	{
		return static::deepConvert($aData, static::SQL_DATETIME_REGEX, 'convertSQLTimestampToISOFormat');
	}
	
	/**
	 * Takes a MySQL timestamp and converts it to a ISO 8601 compliant format.
	 * @param string $sqlTimestamp The timestamp from MySQL.
	 * @return string the timestamp in ISO 8601 compliant format.
	 * @see https://en.wikipedia.org/wiki/ISO_8601
	 *
	 * Example in:  "2015-09-09 17:55:51"
	 * Example out: "2015-11-12T20:57:10+00:00"
	 * Example out: "2015-11-12T20:57:10Z"
	 *
	 */
	static public function convertSQLTimestampToISOFormat($sqlTimestamp) {
		//Let's use PHP 5 'c' format for ISO 8601 conversion.
		//  More information: http://php.net/manual/en/function.date.php
		//return date('c', strtotime( $sqlTimestamp ) );
		//NOTE: the above uses the server's timezone to perform date conversion
		//  we need to always convert assuming UTC and not be reliant upon
		//  server timezone.
		if (strlen($sqlTimestamp)>10)
		{
			$sqlTimestamp{10} = 'T';
			//If the time is in UTC, add a Z directly after the time without a space.
			//  Z is the zone designator for the zero UTC offset.
			//  UTC time is also known as Zulu time, since Zulu is the NATO
			//  phonetic alphabet word for Z.
			if (!(strpos($sqlTimestamp, '+', 11)<0) && //if timezone info not there
					!Strings::endsWith($sqlTimestamp, 'Z')) //and Zulu designation missing
				$sqlTimestamp .= 'Z'; //assume UTC
		}
		return $sqlTimestamp;
	}
	
	/**
	 * Searches an object for fields named like timestamps, and updates
	 * them to MySQL format. Mirror method for deepConvertSQLTimestampsToISOFormat().
	 * @param array|object|string $aData the data to be converted; may be
	 *  an array, object, or scalar
	 * @return array|object|string the same object, with all ISO 8601 datetime
	 *  values replaced by MySQL datetime values.
	 * @see \BitsTheater\costumes\colspecs\CommonMySql::SQL_DATETIME_REGEX
	 * @since BitsTheater 4.3.1
	 */
	static public function deepConvertISO8601DateTimeToMySQLFormat( $aData )
	{
		return static::deepConvert($aData, static::ISO8601_DATETIME_REGEX, 'convertISO8601DateTimeToMySQLFormat');
	}
	
	/**
	 * Searches an object for fields named like timestamps, and updates
	 * them to a different format based on the convert function name passed in.
	 * @param array|object|string $aData the data to be converted; may be
	 *   an array, object, or scalar
	 * @param string $aValueFormatRegex - a value test to see if $aConvertFuncName
	 *   should be called.
	 * @param string $aConvertFuncName - a method name to static::call when converting.
	 * @param number $depth - how deep into the var we've delved (25 is a hard limit).
	 * @return array|object|string the same object, with all datetime
	 *   values replaced accordingly.
	 */
	static protected function deepConvert( &$aData, $aValueFormatRegex, $aConvertFuncName, $depth=0 )
	{
		static $arrList;
		static $objList;
		if ( $depth===0 ) {
			$arrList = array();
			$objList = array();
		}
		else if ( $depth > 25 ) {
			return $aData;
		}
		//inline functions return their type as type 'object', however,
		//  "Closure objects cannot have properties", so check for Closures.
		if ( $aData instanceof \Closure ) return $aData;
		//do not loop through PDOStatements either
		if ( $aData instanceof \PDOStatement ) return $aData;
		//do not loop through Models
		if ( $aData instanceof \BitsTheater\Model ) return $aData;
		//do not loop through Director
		if ( $aData instanceof \BitsTheater\Director ) return $aData;
		try {
			$bIsObject = is_object($aData);
			$bIsArray = is_array($aData);
			if ( $bIsObject && isset($aData->{self::DEEP_CONVERT_FLAG}) ) {
				return $aData;
			}
			if ( $bIsArray && isset($aData[self::DEEP_CONVERT_FLAG]) ) {
				return $aData;
			}
			if ( $bIsObject || $bIsArray ) {
				//update debug flag to preven recusion
				if ( $bIsObject ) {
					$objList[] = $aData;
					$aData->{self::DEEP_CONVERT_FLAG} = count($objList);
				}
				else if ( $bIsArray ) {
					$arrList[] = &$aData;
					$aData[self::DEEP_CONVERT_FLAG] = count($arrList);
				}
				//Strings::debugLog('deepConvert PUSH data=', $aData);//DEBUG
				//convert
				foreach( $aData as $theKey => &$theValue ) {
					if ( $theKey !== self::DEEP_CONVERT_FLAG && !empty($theValue) ) {
						if ( is_string($theValue) && (
								( preg_match('/_ts$/', $theKey) == 1 ) ||
								( preg_match($aValueFormatRegex, $theValue) == 1 )
							)
						)
						{ // Key has timestamp suffix, or value matches SQL datetime
							$theValue = static::$aConvertFuncName($theValue);
						}
						else if ( is_array($theValue) || is_object($theValue) ) {
							$theValue = static::deepConvert($theValue,
									$aValueFormatRegex, $aConvertFuncName, $depth+1
							);
						}
					}
				}
			}
			else if ( is_string($aData) ) {
				$aData = static::$aConvertFuncName($aData);
			}
		}
		catch ( \Exception $x ) {
			Strings::errorLog(__METHOD__, ' ', $x);
		}
		finally {
			if ( $depth===0 ) {
				foreach ($arrList as &$var) {
					unset($var[self::DEEP_CONVERT_FLAG]);
				}
				$arrList = null;
				foreach ($objList as &$var) {
					unset($var->{self::DEEP_CONVERT_FLAG});
				}
				$objList = null;
			}
		}
		return $aData;
	}
	
	/**
	 * Mirror method for convertSQLTimestampToISOFormat().
	 * @param string $aDateTimeISO8601 - a datetime string using ISO8601 format.
	 * @return string Returns the string MySQL needs for it's SQL dialect.
	 * @since BitsTheater 4.3.1
	 */
	static public function convertISO8601DateTimeToMySQLFormat( $aDateTimeISO8601 )
	{
		if (strlen($aDateTimeISO8601)>10)
		{
			$aDateTimeISO8601{10} = ' ';
			$aDateTimeISO8601 = rtrim($aDateTimeISO8601, 'Z');
		}
		return $aDateTimeISO8601;
	}

	/**
	 * The SQL, when executed, results in rows with useful columns like
	 *   'Non_unique' (0/1), 'Key_name' (index name),
	 *   'Seq_in_index' (col order), 'Column_name', and 'Null' ('YES'/'NO')
	 * @param string $aTableName - the table name (database prefix is optional).
	 * @param string $aFieldName - the field (column) name.
	 * @return string Returns the SQL necessary to get the index
	 *   definition record(s).
	 */
	static public function getFieldIndexesSql($aTableName, $aFieldName)
	{
		/* Another possibility, but SHOW KEYS is easier to work with.
		SELECT * FROM information_schema.statistics
		WHERE table_schema = [DATABASE NAME]
		AND table_name = [TABLE NAME] AND column_name = [COLUMN NAME]
		*/
		$theSql = 'SHOW KEYS FROM ' . $aTableName;
		$theSql += " WHERE Column_name='" . $aFieldName. "'";
		return $theSql;
	}
	
	/**
	 * The SQL, when executed, results in rows with useful columns like
	 *   'Non_unique' (0/1), 'Key_name' (index name),
	 *   'Seq_in_index' (col order), 'Column_name', and 'Null' ('YES'/'NO')
	 * @param string $aTableName - the table name (database prefix is optional).
	 * @param string $aIndexName - (optional) the index name,
	 *   NULL for all of them (default)
	 * @return string Returns the SQL necessary to get the index
	 *   definition record(s).
	 */
	static public function getIndexDefinitionSql($aTableName, $aIndexName=null)
	{
		$theSql = 'SHOW KEYS FROM ' . $aTableName;
		if ( !empty($aIndexName) )
			$theSql .= " WHERE Key_name='" . $aIndexName. "'";
		return $theSql;
	}
	
	/**
	 * The SQL used to copy the definition and indexes of an old table to a new table.
	 * @param string $aNewTableName - the new table name.
	 * @param string $aOldTableName - the old table name.
	 * @return string Returns the SQL used to copy a table structure.
	 */
	static public function getCreateNewTableAsOldTableSql($aNewTableName, $aOldTableName)
	{
		//Oracle, and others use AS instead of LIKE
		return "CREATE TABLE {$aNewTableName} LIKE {$aOldTableName}";
	}
	
	/**
	 * The SQL used to determine if a column value is non-ASCII.
	 * @param string $aFieldName - the referenced column/field name.
	 * @return string Returns the SQL to be used in WHERE clause.
	 */
	static public function getIsUnicodeValueSQLWhereClause( $aFieldName )
	{
		//a value converted to ASCII and compared to itself will differ if Unicode.
		return $aFieldName . ' <> CONVERT(' . $aFieldName . ' USING ASCII)';
	}
	
	
}//end class

}//end namespace
