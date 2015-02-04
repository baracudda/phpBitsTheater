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
 * Class built using MySQL, but hope to expand it so it tolerates others, too.
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
	 * @param string $aDefaultValue - (optional) default value if data is null.
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
				if (isset($theDataSet->$aDataKey)) {
					$theData = $theDataSet->$aDataKey;
				}
			} else if (is_string($theDataSet)) {
				$theData = $theDataSet;
			}
		}
		//see if there is a data processing function and call it
		if (isset($this->myParamFuncs[$aDataKey])) {
			$theData = $this->myParamFuncs[$aDataKey]($this, $aDataKey, $theData);
		}
		return $theData;
	}
	
	/**
	 * Mainly used internally by addParamIfDefined to determine if data param exists.
	 * @param string $aDataKey - array key or property name used to retrieve
	 * data set by the setDataSet() method.
	 * @return boolean Returns TRUE if data key is defined (or param function exists).
	 * @see \BitsTheater\costumes\SqlBuilder::setDataSet()
	 */
	public function isDataKeyDefined($aDataKey) {
		//see if there is a data processing function
		$bResult = (isset($this->myParamFuncs[$aDataKey]));
		if (!$bResult && isset($this->myDataSet)) {
			$theDataSet = $this->myDataSet;
			if (is_array($theDataSet)) {
				$bResult = array_key_exists($aDataKey, $theDataSet);
			} else if (is_object($theDataSet)) {
				$bResult = property_exists($theDataSet, $aDataKey);
			} else if (is_string($theDataSet)) {
				$bResult = true;
			}
		}
		return $bResult;
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
	 * @param string $aParamKey - the field/param name.
	 * @param string $aParamValue - the param value to use.
	 * @param number $aParamType - (optional) PDO::PARAM_* integer constant (STR is default).
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
	 * Adds to the SQL string as a set of values; e.g. "(datakey_1,datakey_2, .. datakey_N)"
	 * along with param values set for each of the keys.
	 * Honors the ParamPrefix and ParamOperator properties.
	 * @param string $aFieldName - the field name.
	 * @param string $aDataKey - array key or property name used to retrieve
	 * data set by the setDataSet() method; NOTE: $aDataKeys will have _$i from 1..count() appended.
	 * @param array $aDataValuesList - the value list to use as param values.
	 * @param number $aParamType - (optional) PDO::PARAM_* integer constant (STR is default).
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function mustAddParamAsList($aFieldName, $aDataKey, $aDataValuesList, $aParamType=PDO::PARAM_STR) {
		if (is_array($aDataValuesList) && !empty($aDataValuesList)) {
			$this->mySql .= $this->myParamPrefix.$this->field_quotes.$aFieldName.$this->field_quotes.$this->myParamOperator.'(';
			$theList = $aDataValuesList;
			for ($i=0; $i < count($theList); $i++) {
				$theDataKey = $aDataKey.'_'.$i;
				$this->mySql .= ':'.$theDataKey.(($i < count($theList)-1) ? ',' : ')');
				$this->setParam($theDataKey,$theList[$i],$aParamType);
			}
			return $this;
		} else {
			return $this->mustAddParam($aDataKey, null, $aParamType);
		}
	}
	
	/**
	 * Internal method to affect SQL statment with a param and its value.
	 * @param string $aFieldName - the field name.
	 * @param string $aParamKey - the param name.
	 * @param string $aParamValue - the param value to use.
	 * @param number $aParamType - PDO::PARAM_* integer constant.
	 */
	protected function addingParam($aFieldName, $aParamKey, $aParamValue, $aParamType) {
		if (!is_array($aParamValue) || empty($aParamValue)) {
			if (!is_null($aParamValue)) {
				$this->mySql .= $this->myParamPrefix.$this->field_quotes.$aFieldName.$this->field_quotes.$this->myParamOperator.':'.$aParamKey;
				$this->setParam($aParamKey,$aParamValue,$aParamType);
			} else {
				switch (trim($this->myParamOperator)) {
					case '=':
						$this->mySql .= $this->myParamPrefix.$this->field_quotes.$aFieldName.$this->field_quotes.' IS NULL';
						break;
					case '<>':
						$this->mySql .= $this->myParamPrefix.$this->field_quotes.$aFieldName.$this->field_quotes.' IS NOT NULL';
						break;
				}//switch
			}
		} else {
			$saveParamOp = $this->myParamOperator;
			switch (trim($this->myParamOperator)) {
				case '=':
					$this->myParamOperator = ' IN ';
					break;
				case '<>':
					$this->myParamOperator = ' NOT IN ';
					break;
			}//switch
			$this->mustAddParamAsList($aFieldName, $aParamKey, $aParamValue, $aParamType);
			$this->myParamOperator = $saveParamOp;
		}
	}
	
	/**
	 * Parameter must go into the SQL string regardless of NULL status of data.
	 * @param string $aFieldName - field name to use.
	 * @param string $aDataKey - array key or property name used to retrieve
	 * data set by the setDataSet() method.
	 * @param string $aDefaultValue - (optional) default value if data is null.
	 * @param number $aParamType - (optional) PDO::PARAM_* integer constant (STR is default).
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function mustAddFieldAndParam($aFieldName, $aDataKey, $aDefaultValue=null, $aParamType=PDO::PARAM_STR) {
		$theData = $this->getDataValue($aDataKey, $aDefaultValue);
		$this->addingParam($aFieldName, $aDataKey, $theData, $aParamType);
		return $this;
	}
	
	/**
	 * Parameter must go into the SQL string regardless of NULL status of data.
	 * @param string $aDataKey - array key or property name used to retrieve
	 * data set by the setDataSet() method; this doubles as the field name.
	 * @param string $aDefaultValue - (optional) default value if data is null.
	 * @param number $aParamType - (optional) PDO::PARAM_* integer constant (STR is default).
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function mustAddParam($aDataKey, $aDefaultValue=null, $aParamType=PDO::PARAM_STR) {
		$theData = $this->getDataValue($aDataKey, $aDefaultValue);
		$this->addingParam($aDataKey, $aDataKey, $theData, $aParamType);
		return $this;
	}
	
	/**
	 * Parameter only gets added to the SQL string if data is not NULL. This differs from
	 * addParam() in that you specify the field name and param name to use, in case they
	 * need to be different for some reason, like if you need to update an ID field. <br>
	 * e.g. <code>UPDATE myIDfield=:new_myIDfield_data WHERE myIDfield=:myIDfield</code>
	 * @param string $aFieldName - field name to use.
	 * @param string $aDataKey - array key or property name used to retrieve
	 * data set by the setDataSet() method.
	 * @param string $aDefaultValue - (optional) default value if data is null.
	 * @param number $aParamType - (optional) PDO::PARAM_* integer constant (STR is default).
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function addFieldAndParam($aFieldName, $aDataKey, $aDefaultValue=null, $aParamType=PDO::PARAM_STR) {
		$theData = $this->getDataValue($aDataKey, $aDefaultValue);
		if (isset($theData)) {
			$this->addingParam($aFieldName, $aDataKey, $theData, $aParamType);
		}
		return $this;
	}
	
	/**
	 * Parameter only gets added to the SQL string if data is not NULL.
	 * @param string $aDataKey - array key or property name used to retrieve
	 * data set by the setDataSet() method.
	 * @param string $aDefaultValue - (optional) default value if data is null.
	 * @param number $aParamType - (optional) PDO::PARAM_* integer constant (STR is default).
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function addParam($aDataKey, $aDefaultValue=null, $aParamType=PDO::PARAM_STR) {
		$theData = $this->getDataValue($aDataKey, $aDefaultValue);
		if (isset($theData)) {
			$this->addingParam($aDataKey, $aDataKey, $theData, $aParamType);
		}
		return $this;
	}

	/**
	 * Parameter gets added to the SQL string if data key exists in data set,
	 * regardless of empty(value).
	 * @param string $aDataKey - array key or property name used to retrieve
	 * data set by the setDataSet() method.
	 * @param string $aValueIfEmpty - (optional) value to use if empty()==true.
	 * @param number $aParamType - (optional) PDO::PARAM_* integer constant (STR is default).
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function addParamIfDefined($aDataKey, $aValueIfEmpty=null, $aParamType=PDO::PARAM_STR) {
		if ($this->isDataKeyDefined($aDataKey)) {
			$theData = $this->getDataValue($aDataKey);
			if (empty($theData))
				$theData = $aValueIfEmpty;
			$this->addingParam($aDataKey, $aDataKey, $theData, $aParamType);
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
	 * Adds a string to the SQL prefixed with a space (just in case).
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
	 * func($thisSqlBuilder, $paramKey, $currentParamValue) and returns
	 * the processed value.
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function setParamDataHandler($aParamKey, $aParamFunc) {
		if (!empty($aParamKey)) {
			$this->myParamFuncs[$aParamKey] = $aParamFunc;
		}
		return $this;
	}

	/**
	 * Apply an externally defined set of WHERE field clauses and param values
	 * to our SQL (excludes the "WHERE" keyword).
	 * @param SqlBuilder $aFilter - External SqlBuilder object (can be NULL).
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function applyFilter(SqlBuilder $aFilter=null) {
		if (!empty($aFilter)) {
			if (!empty($aFilter->mySql)) {
				$this->mySql .= $this->myParamPrefix.$aFilter->mySql;
			}
			if (!empty($aFilter->myParams)) {
				foreach ($aFilter->myParams as $theFilterParamKey => $theFilterParamValue) {
					$this->setParam($theFilterParamKey, $theFilterParamValue, $aFilter->myParamTypes[$theFilterParamKey]);
				}
			}
		}
		return $this;
	}
	
	/**
	 * If sort list is defined and its contents are also contained
	 * in the non-empty $aFieldList, then apply the sort order as neccessary.
	 * @see \BitsTheater\costumes\SqlBuilder::applyOrderByList() which this method is an alias of.
	 * @param array $aSortList - keys are the fields => values are 'ASC' or 'DESC' with null='ASC'.
	 * @param array/string $aFieldList - the list or comma string of fieldnames.
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function applySortList($aSortList, $aFieldList=null) {
		return $this->applyOrderByList($aSortList, $aFieldList);
	}
	
	/**
	 * If order by list is defined and its contents are also contained
	 * in the non-empty $aFieldList, then apply the sort order as neccessary.
	 * @param array $aOrderByList - keys are the fields => values are 'ASC' or 'DESC' with null='ASC'.
	 * @return \BitsTheater\costumes\SqlBuilder Returns $this for chaining.
	 */
	public function applyOrderByList($aOrderByList) {
		if (!empty($aOrderByList)) {
			$theSortKeyword = 'ORDER BY';
			//other db types may use a diff reserved keyword, set that here
			//...
			$this->add($theSortKeyword);
			
			$theOrderByList = $aOrderByList;
			if (!isset($theOrderByList[0])) {
				$theOrderByList = array();
				foreach ($aOrderByList as $theField => $theSortOrder) {
					$theOrderByList[] = $theField.((!empty($theSortOrder)) ? ' '.$theSortOrder : '');
				}
			}
			$this->add(implode(',', $theOrderByList));
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
	 * SQL Params should be ordered array with ? params OR associative array with :label params.
	 * @param array $aListOfParamValues - array of arrays of values for the parameters in the SQL statement.
	 * @throws DbException if there is an error.
	 * @see \BitsTheater\Model::execMultiDML();
	 */
	public function execMultiDML($aListOfParamValues) {
		return $this->myModel->execMultiDML($this->mySql, $aListOfParamValues, $this->myParamTypes);
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
	
	