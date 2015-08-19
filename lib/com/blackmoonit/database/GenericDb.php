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
use com\blackmoonit\database\DbConnInfo;
use com\blackmoonit\database\DbUtils;
use \PDO;
use \PDOStatement;
use \PDOException;
{//begin namespace

class GenericDb extends BaseDbClass {
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
	 * @return DbConnInfo the database connection info.
	 */
	public function getDbConnInfo() {
		//let descendants handle
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
	 * @throws InvalidArgumentException - if the fieldlist is empty.
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
			throw new InvalidArgumentException('invalid field listing');
	}
	
	/**
	 * Return SQL format for UPDATE field list.
	 * @param array $aUpdateParams - index keys are fields we want UPDATEd (values contain their new data).
	 * @param array $aTextIdFieldList - (optional) TextId fields require special attention.
	 * @throws InvalidArgumentException - if the fieldlist is empty.
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
			throw new InvalidArgumentException('invalid field listing');
	}
	
	/**
	 * @return Returns a SQL datetime string representing now() in UTC.
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
	
}//class

}//namespace
