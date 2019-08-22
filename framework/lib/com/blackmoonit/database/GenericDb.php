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
use com\blackmoonit\AdamEve as BaseDbClass;
use com\blackmoonit\exceptions\DbException;
use PDO;
use PDOStatement;
use PDOException;
{//begin namespace

class GenericDb extends BaseDbClass {
	/** Cubrid */
	const DB_TYPE_CUBRID	= 'cubrid';
	/** FreeTDS / Microsoft SQL Server / Sybase */
	const DB_TYPE_DBLIB		= 'dblib';
	/** Firebird/Interbase 6 */
	const DB_TYPE_FIREBIRD	= 'firebird';
	/** IBM DB2 */
	const DB_TYPE_IBM		= 'ibm';
	/** IBM Informix Dynamic Server */
	const DB_TYPE_INFORMIX	= 'informix';
	/** MySQL 3+ */
	const DB_TYPE_MYSQL		= 'mysql';
	/** Oracle Call Interface */
	const DB_TYPE_OCI		= 'oci';
	/** ODBC v3 (IBM DB2, unixODBC and win32 ODBC) */
	const DB_TYPE_ODBC		= 'odbc';
	/** PostgreSQL */
	const DB_TYPE_PGSQL		= 'pgsql';
	/** SQLite 3 and SQLite 2 */
	const DB_TYPE_SQLITE	= 'sqlite';
	/** Microsoft SQL Server / SQL Azure */
	const DB_TYPE_SQLSRV	= 'sqlsrv';
	/** 4D */
	const DB_TYPE_4D		= '4d';
	
	/** Number of connection attempts per query before giving up as exception. */
	const MAX_RETRY_COUNT = 3;
	
	/**
	 * @var PDO
	 */
	public $db = null;

	/**
	 * PDO database connection and helper functions.
	 * @link http://php.net/manual/pdo.construct.php
	 */
	public function connect() {
		$theDnsInfo = $this->getDbConnInfo();
		if (!empty($theDnsInfo)) {
			$this->db = $theDnsInfo->getPDOConnection();
		}
	}

	/**
	 * Used in base connect() implementation.
	 * @return \com\blackmoonit\database\DbConnInfo the database connection info.
	 */
	public function getDbConnInfo() {
		//let descendants handle
	}
	
	/**
	 * Get the db driver in use. Possible return values include: <table>
	 * <tr><th>RETURNS</th><th>self::DB_TYPE_*</th><th>Description</th></tr>
	 * <tr><td>cubrid</td>	<td>DB_TYPE_CUBRID</td>		<td>Cubrid</td></tr>
	 * <tr><td>dblib</td>	<td>DB_TYPE_DBLIB</td>		<td>FreeTDS / Microsoft SQL Server / Sybase</td></tr>
	 * <tr><td>firebird</td><td>DB_TYPE_FIREBIRD</td>	<td>Firebird/Interbase 6</td></tr>
	 * <tr><td>ibm</td>		<td>DB_TYPE_IBM</td>		<td>IBM DB2</td></tr>
	 * <tr><td>informix</td><td>DB_TYPE_INFORMIX</td>	<td>IBM Informix Dynamic Server</td></tr>
	 * <tr><td>mysql</td>	<td>DB_TYPE_MYSQL</td>		<td>MySQL 3.x/4.x/5.x</td></tr>
	 * <tr><td>oci</td>		<td>DB_TYPE_OCI</td>		<td>Oracle Call Interface</td></tr>
	 * <tr><td>odbc</td>	<td>DB_TYPE_ODBC</td>		<td>ODBC v3 (IBM DB2, unixODBC and win32 ODBC)</td></tr>
	 * <tr><td>pgsql</td>	<td>DB_TYPE_PGSQL</td>		<td>PostgreSQL</td></tr>
	 * <tr><td>sqlite</td>	<td>DB_TYPE_SQLITE</td>		<td>SQLite 3 and SQLite 2</td></tr>
	 * <tr><td>sqlsrv</td>	<td>DB_TYPE_SQLSRV</td>		<td>Microsoft SQL Server / SQL Azure</td></tr>
	 * <tr><td>4d</td>		<td>DB_TYPE_4D</td>			<td>4D</td></tr>
	 * </table>
	 * @param PDO $aDbConn - the PDO connection.
	 * @return string Returns the database connection driver being used.<br>
	 */
	public function dbType() {
		return DbUtils::getDbType($this->db);
	}
	
	/**
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aParamSql - the parameterized SQL string.
	 * @return PDOStatement is returned, ready for binding to params.
	 * @throws DbException if there is an error.
	 */
	public function prepareSQL($aParamSql) {
		$theRetries = 0;
		do {
			try {
				return $this->db->prepare($aParamSql);
			} catch (PDOException $pdoe) {
				if (DbUtils::isDbConnTimeout($this->db, $pdoe)) {
					//connection timed out, reconnect and try again
					$this->connect();
				} else {
					throw $pdoe;
				}
			}
		} while ($theRetries++ < static::MAX_RETRY_COUNT);
	}

	/**
	 * Bind each named param with its value.
	 * @param PDOStatement or string $aStatement - the prepared SQL statement, or SQL string to be prepared.
	 * @param array $aSqlParams - the values to bind to the prepared statement.
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @return PDOStatement returns the passed-in/generated PDOStatement.
	 */
	public function bindValues($aStatement, array $aSqlParams, $aParamTypes = NULL) {
		if (is_string($aStatement))
			$theStatement = $this->prepareSQL($aStatement);
		else
			$theStatement = $aStatement;
		foreach ($aSqlParams as $theKey=>$theValue) {
			if (is_array($theValue)) {
				continue;
			} elseif (is_null($theValue)) {
				$theParamType = PDO::PARAM_NULL;
			} elseif (!is_null($aParamTypes) && array_key_exists($theKey,$aParamTypes)) {
				$theParamType = $aParamTypes[$theKey];
			} elseif (is_string($theValue)) {
				$theParamType = PDO::PARAM_STR;
			} elseif (is_int($theValue)) {
				$theParamType = PDO::PARAM_INT;
			} elseif (is_bool($theValue)) {
				$theParamType = PDO::PARAM_BOOL;
			} else { //default type is STR
				$theParamType = PDO::PARAM_STR;
			}
			if ($theParamType==PDO::PARAM_STR && is_object($theValue)) {
				throw new \PDOException('Trying to bind an Object in a SQL query');
			}
			$theStatement->bindValue($theKey,$theValue,$theParamType);
		}
		return $theStatement;
	}
	
	/**
	 * Return an array with missing fields set to NULL so we do not get "key does not exist" notifications.
	 * @param array $aKeys - keys that should exist.
	 * @param array $aValues - values to be used for the existing array keys.
	 */
	public function forceKeysExist(array $aKeys, array $aValues) {
		return array_replace(array_fill_keys($aKeys,NULL),$aValues);
	}
	
	/**
	 * Return SQL format field listing; ie: <code>"rec_id, name".</code>
	 * @param array $aFieldList - fields we want formatted for SQL.
	 * @param array $aTextIdFieldList - (optional) TextId fields require special attention for SELECT statements.
	 */
	public function getSqlFields($aTableName, array $aFieldList, array $aTextIdFieldList = array()) {
		$theResult = '';
		foreach ($aFieldList as $theFieldname) {
			if (!in_array($theFieldname, $aTextIdFieldList, true)) {
				$theResult .= $aTableName.'.'.$theFieldname.', ';
			} else {
				$theResult .= 'HEX('.$aTableName.'.'.$theFieldname.') as '.$theFieldname.', ';
			}
		}
		if (!empty($theResult))
			return substr($theResult,0,-2);
		else
			return '*';
	}
	
	/**
	 * Return SQL format for INSERT value field list.
	 * @param array $aFieldList - fields we want returned from SQL SELECT statement.
	 * @param array $aTextIdFieldList - (optional) TextId fields require special attention.
	 * @throws \InvalidArgumentException - if the fieldlist is empty.
	 */
	public function getSqlValueFields(array $aFieldList, array $aTextIdFieldList = NULL) {
		$theResult = '';
		foreach ($aFieldList as $theFieldname) {
			if (!in_array($theFieldname, $aTextIdFieldList, true)) {
				$theResult .= ':'.$theFieldname.', ';
			} else {
				$theResult .= 'UNHEX(:'.$theFieldname.'), ';
			}
		}
		if (!empty($theResult))
			return substr($theResult,0,-2);
		else
			throw new \InvalidArgumentException('invalid field listing');
	}
	
	/**
	 * Return SQL format for UPDATE field list.
	 * @param array $aUpdateParams - index keys are fields we want UPDATEd (values contain their new data).
	 * @param array $aTextIdFieldList - (optional) TextId fields require special attention.
	 * @throws \InvalidArgumentException - if the fieldlist is empty.
	 */
	public function getSqlUpdateFields(array $aUpdateParams, array $aTextIdFieldList = NULL) {
		$theResult = '';
		foreach ($aUpdateParams as $theFieldname=>$theNewValue) {
			if (!in_array($theFieldname, $aTextIdFieldList, true)) {
				$theResult .= $theFieldname.'=:'.$theFieldname.', ';
			} else {
				$theResult .= $theFieldName.'=UNHEX(:'.$theFieldname.'), ';
			}
		}
		if (!empty($theResult))
			return substr('SET '.$theResult,0,-2);
		else
			throw new \InvalidArgumentException('invalid field listing');
	}
	
	/**
	 * @param boolean $bUseMicroseconds - include micro-seconds or not.
	 * @return string Returns a SQL datetime string representing now() in UTC.
	 */
	public function utc_now($bUseMicroseconds=false) {
		switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL:
				//MySQL <=5.5 works+warning with "Z" timezone, 5.6+ gives fatal error
				return ($bUseMicroseconds) ? DbUtils::utc_now(true) : gmdate(DbUtils::DATETIME_FORMAT_DEF_STD) ;
			default:
				return DbUtils::utc_now($bUseMicroseconds);
		}
	}
	
	/**
	 * @return string Returns a SQL datetime string representing now() in UTC.
	 */
	public function getDateTimeAsDbTimestampFormat( \DateTime $aDateTime )
	{
		switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL:
				return $aDateTime->format(DbUtils::DATETIME_FORMAT_DEF_STD);
			default:
				return $aDateTime->format(DbUtils::DATETIME_FORMAT_ISO8601);
		}
	}
	
}//class

}//namespace
