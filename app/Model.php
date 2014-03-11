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
use com\blackmoonit\bits_theater\app\Director;
use com\blackmoonit\database\GenericDb as BaseModel;
use com\blackmoonit\database\DbUtils;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Strings;
use \ReflectionClass;
use \PDOException;
{//begin namespace

/**
 * Base class for Models.
 */
class Model extends BaseModel {
	const _SetupArgCount = 1; //number of args required to call the setup() method.
	/**
	 * Use the named connection found in director->dbConnInfo[].
	 * @var string
	 */
	const dbConnName = 'webapp';
	public $tbl_ = '';
	public $director = null;
	public $myDbConnInfo = null;
	
	/**
	 * Setup Model for use; connect to db if not done yet.
	 * @param Director $aDirector - site director object
	 */
	public function setup(Director $aDirector) {
		$this->director = $aDirector;
		$this->myDbConnInfo = $this->director->getDbConnInfo(static::dbConnName);
		try {
			$this->db = $this->myDbConnInfo->connect();
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe,'Failed to connect'.(!empty($this->myDbConnInfo->dbName) ? ' to '.$this->myDbConnInfo->dbName : ''));
		}
		if (!empty($this->db)) {
			$this->setupAfterDbConnected();
		}
		$this->bHasBeenSetup = true;
	}
	
	public function cleanup() {
		unset($this->db);
		unset($this->myDbConnInfo);
		unset($this->tbl_);
		unset($this->director);
		parent::cleanup();
	}
	
	static public function newModel($aModelClassName, Director $aDirector) {
		return new $aModelClassName($aDirector);
	}
	
	/**
	 * Descendants may wish to override this method to handle more stuff after 
	 * a successful db connection is made. Should probably include on first line:
	 * parent::setupAfterDbConnected().
	 */
	protected function setupAfterDbConnected() {
		$this->tbl_ = $this->myDbConnInfo->table_prefix;
	}
	
	public function isConnected() {
		return (isset($this->db));
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
		if ($this->exists($aTableName)) {
			$r = $this->query("SELECT 1 FROM $aTableName WHERE EXISTS(SELECT * FROM $aTableName LIMIT 1)");
			return ($r->fetch()==null);
		} else {
			return false;
		}
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
	
	/**
	 * Returns the model class pattern used by getModelClassInfos.
	 * @param string $aModelClassPattern - filename pattern to use, default is '*'. Do not include the '.php' as that is assumed.
	 * @return string Returns the "glob" pattern matching string.
	 * @see Model::getModelClassInfos()
	 */
	static public function getModelClassPattern($aModelClassPattern='*') {
		return BITS_APP_PATH.'model'.¦.$aModelClassPattern.'.php';
	}

	/**
	 * Get list of all non-abstract model ReflectionClass objects.
	 * @return array Returns list of all models as a ReflectionClass.
	 */
	static public function getAllModelClassInfo() {
		return self::getModelClassInfos();
	}
	
	/**
	 * Get a list of models based on the parameter.
	 * @param string $aModelClassPattern - NULL for all non-abstract models, else a result from getModelClassPattern.
	 * @param boolean $bIncludeAbstracts - restricts the list to only instantiable classes if FALSE, default is FALSE.
	 * @return array Returns list of all models that match the pattern as a ReflectionClass.
	 * @see Model::getModelClassPattern()
	 */
	static public function getModelClassInfos($aModelClassPattern=null, $bIncludeAbstracts=false) {
		$theModelClassPattern = (empty($aModelClassPattern)) ? self::getModelClassPattern() : $aModelClassPattern;
		$theModels = array();
		foreach (glob($theModelClassPattern) as $theModelFile) {
			$theModelClass = str_replace('.php','',basename($theModelFile));
			$classInfo = new ReflectionClass(BITS_NAMESPACE_MODEL.$theModelClass);
			if ($bIncludeAbstracts || !$classInfo->isAbstract()) {
			    $theModels[] = $classInfo;
			}
			unset($classInfo);
		}
		return $theModels;
	}
	
	/**
	 * Given a model list (output of getModelClassInfos), call their method with $args, if applicable.
	 * @param Director $aDirector - site director object
	 * @param array[ReflectionClass] $aModelList - list of model ReflectionClass.
	 * @param string $aMethodName - method to call.
	 * @param mixed $args - arguments to pass to the method to call.
	 * @return array Returns an array of key(model class name) => value(function result);
	 * @see Model::getModelClassInfos()
	 */
	static public function callModelMethod(Director $aDirector, array $aModelList, $aMethodName, $args=null) {
		$theResult = array();
		if (!is_array($args))
			$args = array($args);
		foreach ($aModelList as $modelInfo) {
			if ($modelInfo->hasMethod($aMethodName)) {
				$theModel = $aDirector->getProp($modelInfo);
				$theResult[$modelInfo->getShortName()] = call_user_func_array(array($theModel,$aMethodName),$args);
			}
		}
		return $theResult;
	}
	
	/**
	 * Calls methodName for every model class that matches the class patern and returns an array of results.
	 * @param Director $aDirector - site director object
	 * @param string $aModelClassPattern - NULL for all non-abstract models, else a result from getModelClassPattern.
	 * @param string $aMethodName - method to call.
	 * @param mixed $args - arguments to pass to the method to call.\
	 * @return array Returns an array of key(model class name) => value(function result);
	 * @see Model::getModelClassInfos()
	 * @see Model::getModelClassPattern()
	 * @see Model::callModelMethod()
	 */
	static public function foreachModel(Director $aDirector, $aModelClassPattern, $aMethodName, $args=null) {
		$theModelClassPattern = self::getModelClassPattern($aModelClassPattern);
		$theModelList = self::getModelClassInfos($theModelClassPattern);
		return self::callModelMethod($aDirector, $theModelList, $aMethodName, $args);
	}

	static public function isExistant($aModelName) {
		return file_exists(self::getModelClassPattern($aModelName));
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
	
	/**
	 * Returns the URL for this site appended with relative path info.
	 * @param mixed $aRelativeURL - array of path segments OR a bunch of string parameters
	 * equating to path segments.
	 * @return string - returns the site domain + relative path URL.
	 */
	public function getSiteURL($aRelativeURL='', $_=null) {
		return call_user_func_array(array($this->director, 'getSiteURL'), func_get_args());
	}
	
}//end class

}//end namespace
