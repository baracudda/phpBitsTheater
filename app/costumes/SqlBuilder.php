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

namespace BitsTheater\costumes;
use BitsTheater\costumes\ABitsCostume as BaseCostume;
use BitsTheater\Director;
use BitsTheater\Model;
use com\blackmoonit\Strings;
use \PDO;
{//namespace begin

/**
 * Models can use this class to help build their SQL queries / PDO statements.
 */
class SqlBuilder extends BaseCostume {
	/**
	 * The model class, so we can pass-thru some method calls like query.
	 * Also useful for auto-determining database quirks like the char used
	 * around field names.
	 * @var \BitsTheater\Model
	 */
	public $myModel = null;
	/**
	 * The char used around field names in case of spaces and keyword clashes.
	 * Determined by the database type being used (MySQL vs Oracle, etc.).
	 * @var string
	 */
	public $field_quotes = '`';
	
	public $myDataSet = null;
	public $myParamPrefix = ' ';
	public $myParamOperator = '=';
	public $mySql = '';
	public $myParams = array();
	public $myParamTypes = array();
	public $myParamFuncs = array();
	
	/**
	 * Models can use this class to help build their SQL queries / PDO statements.
	 * @param \BitsTheater\Model $aModel - the model being used.
	 * @return \BitsTheater\costumes\SqlBuilder Returns the created object.
	 */
	static public function withModel(Model $aModel) {
		$theClassName = get_called_class();
		$o = new $theClassName($aModel->director);
		return $o->setModel($aModel);
	}
	
	/**
	 * Sets the model being used along with initializing database specific quirks.
	 * @param \BitsTheater\Model $aModel
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function setModel(Model $aModel) {
		$this->myModel = $aModel;
		switch ($this->myModel->dbType()) {
			case Model::DB_TYPE_MYSQL:
			default:
				$this->field_quotes = '`';
		}//switch
		return $this;
	}
	
	/**
	 * Sets the dataset to be used by addParam methods.
	 * @param array or StdClass $aDataSet - array or class used to get param data.
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function setDataSet($aDataSet) {
		$this->myDataSet = $aDataSet;
		return $this;
	}
	
	/**
	 * Mainly used internally to get param data.
	 * @param string $aDataKey - array key or property name used to retrieve
	 * data set by the setDataSet() method.
	 * @param string $aDefaultValue - default value if data is null.
	 * @return mixed Returns the data value.
	 * @see \BitsTheater\costumes\SqlBuilder::setDataSet()
	 */
	public function getDataValue($aDataKey, $aDefaultValue=null) {
		$theData = $aDefaultValue;
		if (isset($this->myDataSet)) {
			$theDataSet = $this->myDataSet;
			if (is_array($theDataSet)) {
				if (isset($theDataSet[$aDataKey])) {
					$theData = $theDataSet[$aDataKey];
				}
			} else if (is_object($theDataSet)) {
				$theData = $theDataSet->$aDataKey;
				if (!isset($theData) && isset($aDefaultValue)) {
					$theData = $aDefaultValue;
				}
			} else if (is_string($theDataSet)) {
				$theData = $theDataSet;
				if (!isset($theData) && isset($aDefaultValue)) {
					$theData = $aDefaultValue;
				}
			}
		}
		//see if there is a data processing function and call it
		if (isset($this->myParamFuncs[$aDataKey])) {
			$theData = $this->myParamFuncs[$aDataKey]($this, $aDataKey, $theData);
		}
		return $theData;
	}
	
	/**
	 * Resets the object so it can be resused with same dataset.
	 * @param boolean $bClearDataFunctionsToo - (optional) clear data processing
	 * functions as well (default is FALSE).
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function reset($bClearDataFunctionsToo=false) {
		$this->myParamPrefix = '';
		$this->myParamOperator = '=';
		$this->mySql = '';
		$this->myParams = array();
		$this->myParamTypes = array();
		if ($bClearDataFunctionsToo)
			$this->myParamFuncs = array();
		return $this;
	}
	
	/**
	 * Sets the param value and param type, but does not affect the SQL string.
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function setParam($aParamKey, $aParamValue, $aParamType=null) {
		if (isset($aParamValue)) {
			$this->myParams[$aParamKey] = $aParamValue;
			if (isset($aParamType)) {
				$this->myParamTypes[$aParamKey] = $aParamType;
			} else if (empty($this->myParamTypes[$aParamKey])) {
				$this->myParamTypes[$aParamKey] = PDO::PARAM_STR;
			}
		} else {
			$this->myParams[$aParamKey] = null;
			$this->myParamTypes[$aParamKey] = PDO::PARAM_NULL;
		}
		return $this;
	}
	
	/**
	 * Gets the current value of a param that has been added.
	 * @param string $aParamKey - the param key to retrieve.
	 * @return multitype Returns the data.
	 */
	public function getParam($aParamKey) {
		if (isset($this->myParams[$aParamKey]))
			return $this->myParams[$aParamKey];
	}
	
	/**
	 * Sets the SQL string to this value.
	 * @param string $aSql - the first part of a SQL string to build upon.
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function startWith($aSql) {
		$this->mySql = $aSql;
		return $this;
	}
	
	/**
	 * Adds the fieldlist to the SQL string.
	 * @param array/string $aFieldList - the list or comma string of fieldnames.
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function addFieldList($aFieldList) {
		$theFieldList = '*';
		if (!empty($aFieldList)) {
			if (is_array($aFieldList)) {
				$theFieldList = implode(', ', $aFieldList);
			} else if (is_string($aFieldList)) {
				$theFieldList = $aFieldList;
			}
		}
		return $this->add($theFieldList);
	}
	
	/**
	 * Parameter must go into the SQL string regardless of NULL status of data.
	 * @param string $aDataKey - array key or property name used to retrieve
	 * data set by the setDataSet() method.
	 * @param string $aDefaultValue - (optional) default value if data is null.
	 * @param string $aParamType - (optional) PDO::PARAM_* integer constant
	 * of param type (STR is default).
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function mustAddParam($aDataKey, $aDefaultValue=null, $aParamType=PDO::PARAM_STR) {
		$theData = $this->getDataValue($aDataKey, $aDefaultValue);
		$this->mySql .= $this->myParamPrefix.$this->field_quotes.$aDataKey.$this->field_quotes.$this->myParamOperator.':'.$aDataKey;
		return $this->setParam($aDataKey,$theData,$aParamType);
	}
	
	/**
	 * Parameter only gets added to the SQL string if data is not NULL.
	 * @param string $aDataKey - array key or property name used to retrieve
	 * data set by the setDataSet() method.
	 * @param string $aDefaultValue - (optional) default value if data is null.
	 * @param string $aParamType - (optional) PDO::PARAM_* integer constant
	 * of param type (STR is default).
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function addParam($aDataKey, $aDefaultValue=null, $aParamType=PDO::PARAM_STR) {
		$theData = $this->getDataValue($aDataKey, $aDefaultValue);
		if (isset($theData)) {
			$this->mySql .= $this->myParamPrefix.$this->field_quotes.$aDataKey.$this->field_quotes.$this->myParamOperator.':'.$aDataKey;
			$this->setParam($aDataKey,$theData,$aParamType);
		}
		return $this;
	}

	/**
	 * Sets the "glue" string that gets prepended to all subsequent calls
	 * to addParam kinds of methods. Spacing is important here, so add what
	 * is needed!
	 * @param string $aStr - glue to prepend.
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function setParamPrefix($aStr=', ') {
		$this->myParamPrefix = $aStr;
		return $this;
	}

	/**
	 * Operator string to use in all subsequent calls to addParam methods.
	 * @param string $aStr - operator string to use ('=' is default,
	 * ' LIKE ' is a popular operator as well).
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function setParamOperator($aStr='=') {
		$this->myParamOperator = $aStr;
		return $this;
	}
	
	/**
	 * Adds a string to the SQL prepended with a space (just in case).
	 * @param string $aStr - patial SQL sting to add.
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function add($aStr) {
		$this->mySql .= ' '.$aStr;
		return $this;
	}
	
	/**
	 * Sometimes parameter data needs processing before being used.
	 * @param string $aParamKey - the parameter key.
	 * @param Function $aParamFunc - a function used to process the
	 * data (even a default value) of the form:<br>
	 * func($thisSqlBuilder, $paramKey $currentParamValue) and returns
	 * the processed value.
	 * @return \BitsTheater\costumes\SqlBuilder
	 */
	public function setParamDataHandler($aParamKey, $aParamFunc) {
		if (!empty($aParamKey)) {
			$this->myParamFuncs[$aParamKey] = $aParamFunc;
		}
		return $this;
	}
	
	//=================================================================
	// MAPPED FUNCTIONS TO MODEL
	//=================================================================

	/**
	 * Execute DML (data manipulation language - INSERT, UPDATE, DELETE) statements
	 * @throws DbException if there is an error.
	 * @return Returns TRUE.
	 * @see \BitsTheater\Model::execDML();
	 */
	public function execDML() {
		return $this->myModel->execDML($this->mySql, $this->myParams, $this->myParamTypes);
	}
	
	/**
	 * Execute Select query, returns PDOStatement.
	 * @throws DbException if there is an error.
	 * @return PDOStatement on success.
	 * @see \BitsTheater\Model::query();
	 */
	public function query() {
		return $this->myModel->query($this->mySql, $this->myParams, $this->myParamTypes);
	}
	
	/**
	 * A combination query & fetch a single row, returns null if errored.
	 * @see \BitsTheater\Model::getTheRow();
	 */
	public function getTheRow() {
		return $this->myModel->getTheRow($this->mySql, $this->myParams, $this->myParamTypes);
	}
	
	/**
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @throws DbException if there is an error.
	 * @see \BitsTheater\Model::execMultiDML();
	 */
	public function execMultiDML() {
		return $this->myModel->execMultiDML($this->mySql, $this->myParams, $this->myParamTypes);
	}
	
	/**
	 * Perform an INSERT query and return the new Auto-Inc ID field value for it.
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @throws DbException if there is an error.
	 * @return Returns the lastInsertId().
	 * @see \BitsTheater\Model::addAndGetId();
	 */
	public function addAndGetId() {
		return $this->myModel->addAndGetId($this->mySql, $this->myParams, $this->myParamTypes);
	}
	
}//end class
	
}//end namespace
	
	