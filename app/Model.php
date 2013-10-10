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

namespace com\blackmoonit\bits_theater\app;
use com\blackmoonit\database\GenericDb as BaseModel;
use com\blackmoonit\database\DbUtils;
use com\blackmoonit\Strings;
use \ReflectionClass;
use \PDOException;
use com\blackmoonit\exceptions\DbException;
{//begin namespace

/**
 * Base class for Models.
 */
class Model extends BaseModel {
	const _SetupArgCount = 2; //number of args required to call the setup() method.
	public $tbl_;
	public $director;
	
	/**
	 * Setup Model for use.
	 * @param Director $aDirector - site director object
	 * @param array $aDbConn - use this connection. If null, create a new one.
	 */
	public function setup(Director $aDirector, $aDbConn) {
		$this->director = $aDirector;
		$this->tbl_ = $this->director->table_prefix;
		if (is_null($aDbConn))
			$this->connect($this->getDbConnInfo());
		else {
			$this->db = $aDbConn;
		}
		$this->bHasBeenSetup = true;
	}
	
	public function cleanup() {
		unset($this->db);
		unset($this->tbl_);
		unset($this->director);
		parent::cleanup();
	}
	
	static public function newModel($aModelClassName, Director $aDirector) {
		return new $aModelClassName($aDirector, $aDirector->dbConn);
	}
	
	public function isConnected() {
		return (isset($this->db));
	}
	
	public function getDbConnInfo() {
		return DbUtils::readDbConnInfo(BITS_DB_INFO);
	}
	
	/**
	 * Execute DML (data manipulation language - INSERT, UPDATE, DELETE) statements
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aSql - SQL statement (may be parameterized).
	 * @param array $aParamValues - if the SQL statement is parameterized, pass in the values for them, too. 
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @throws DbException if there is an error.
	 * @return number of rows affected; using params returns TRUE instead.
	 */
	public function execDML($aSql, $aParamValues=null, $aParamTypes=null) {
		try {
			if (is_null($aParamValues)) {
				return $this->db->exec($aSql);
			} else {
				$theStatement = $this->db->prepare($aSql);
				$this->bindValues($theStatement,$aParamValues,$aParamTypes);
				return $theStatement->execute();
			}
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe);
		}
	}

	/**
	 * Execute Select query, returns PDOStatement.
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aSql - SQL statement (may be parameterized).
	 * @param array $aParamValues - if the SQL statement is parameterized, pass in the values for them, too. 
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @throws DbException if there is an error.
	 * @return PDOStatement on success.
	 */
	public function query($aSql, $aParamValues=null, $aParamTypes=null) {
		try {
			if (is_null($aParamValues)) {
				return $this->db->query($aSql);
			} else {
				$theStatement = $this->db->prepare($aSql);
				$this->bindValues($theStatement,$aParamValues,$aParamTypes);
				$theStatement->execute();
				return $theStatement;
			}
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe);
		}
	}
	
	/**
	 * combination query & fetch a single row, returns null if errored
	 */
	public function getTheRow($aSql, $aParamValues=null, $aParamTypes=null) {
		$theResult = null;
		$r = $this->query($aSql,$aParamValues,$aParamTypes);
		if ($r)
			$theResult = $r->fetch();
		$r->closeCursor();
		return $theResult;
	}
	
	/**
	 * Return TRUE if specified table exists.
	 * @param string $aTableName
	 */
	protected function exists($aTableName) {
		try {
			$this->query("SELECT 1 FROM $aTableName WHERE 1=0");
			return true;
		} catch (DbException $dbe) {
			return false;
		}
	}
	
	/**
	 * return TRUE iff table exists and is empty.
	 */	
	public function isEmpty($aTableName) {
		$r = $this->query("SELECT 1 FROM $aTableName WHERE EXISTS(SELECT * FROM $aTableName LIMIT 1)");
		return ($r->fetch()==null);
	}
	
	//===== Parameterized queries =====	

	/**
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aParamSql - the parameterized SQL string.
	 * @throws DbException if there is an error.
	 * @return PDOStatement is returned, ready for binding to params.
	 */
	public function prepareSQL($aParamSql) {
		try {
			return $this->db->prepare($aParamSql);
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe, 'Preparing: '.$aParamSql);
		}
	}

	/**
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aParamSql - the parameterized SQL string.
	 * @param array $aListOfParamValues - array with the array of values for the parameters in the SQL statement.
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @throws DbException if there is an error.
	 */
	public function execMultiDML($aParamSql, $aListOfParamValues, $aParamTypes=null) {
		try {
			$theStatement = $this->db->prepare($aParamSql);
			foreach ($aListOfParamValues as $theSqlParams) {
				$this->bindValues($theStatement,$theSqlParams,$aParamTypes);
				$theStatement->execute();
				$theStatement->closeCursor();
			}
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe);
		}
	}
	
	/**
	 * Perform an INSERT query and return the new Auto-Inc ID field value for it.
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aSql - SQL statement (may be parameterized).
	 * @param array $aParamValues - if the SQL statement is parameterized, pass in the values for them, too. 
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @throws DbException if there is an error.
	 * @return Returns the lastInsertId().
	 */
	public function addAndGetId($aSql, $aParamValues=null, $aParamTypes=null) {
		$theResult = null;
		if (!empty($aSql)) {
			$this->db->beginTransaction();
			if ($this->execDML($aSql,$aParamValues,$aParamTypes)==1) {
				$theResult = $this->db->lastInsertId();
				$this->db->commit();
			} else {
				$this->db->rollBack();
			}
		}
		return $theResult;
	}
	
	
	//===== static helper functions =====
	
	static public function getModelClassPattern() {
		return BITS_APP_PATH.'model'.¦.'*.php';
	}
	
	static public function getAllModelClassInfo() {
		$theModels = array();
		foreach (glob(self::getModelClassPattern()) as $theModelFile) {
			$theModelClass = str_replace('.php','',basename($theModelFile));
			$classInfo = new ReflectionClass(__NAMESPACE__.'\\model\\'.$theModelClass);
			if (!$classInfo->isAbstract()) {
			    $theModels[] = $classInfo;
			}
			unset($classInfo);
		}
		return $theModels;
	}
	
	static public function isExistant($aModelName) {
		$theModelFile = __NAMESPACE__.'\\model\\'.$aModelName.'.php';
		return file_exists($theModelFile);
	}
	
	public function getRes($aName) {
		return $this->director->getRes($aName);
	}
	
	public function getProp($aName) {
		return $this->director->getProp($aName);
	}
	
	public function returnProp($aModel) {
		$this->director->returnProp($aModel);
	}

	static public function cnvRowsToArray($aRowSet, $aFieldNameKey, $aFieldNameValue=null) {
		return DbUtils::cnvRowsToArray($aRowSet,$aFieldNameKey,$aFieldNameValue);
	}
	
}//end class

}//end namespace
