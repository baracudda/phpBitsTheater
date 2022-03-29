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
use BitsTheater\Model;
use com\blackmoonit\exceptions\DbException;
{//namespace begin

/**
 * Models can use this class to help build their SQL queries / PDO statements.
 * Class built using MySQL, but hope to expand it so it tolerates others, too.
 */
class SqlBuilder extends BaseCostume {
	/**
	 * 5 digit code meaning "successful completion/no error".
	 * @var string
	 */
	const SQLSTATE_SUCCESS = \PDO::ERR_NONE;
	/**
	 * 5 digit code meaning "no data"; e.g. UPDATE/DELETE failed due
	 * to record(s) defined by WHERE clause returned no rows at all.
	 * @var string
	 */
	const SQLSTATE_NO_DATA = '02000';
	/**
	 * 5 digit ANSI SQL code meaning a table referenced in the SQL
	 * does not exist.
	 * @var unknown
	 */
	const SQLSTATE_TABLE_DOES_NOT_EXIST = '42S02';
	/**
	 * The SQL element meaning ascending order when sorting.
	 * @var string
	 */
	const ORDER_BY_ASCENDING = 'ASC';
	/**
	 * The SQL element meaning descending order when sorting.
	 * @var string
	 */
	const ORDER_BY_DESCENDING = 'DESC';
	/**
	 * Sometimes we have a nested query in field list. So in order for
	 * {@link SqlBuilder::getQueryTotals()} to work automatically, we need to
	 * supply a comment hint to start the field list.
	 * @var string
	 */
	const FIELD_LIST_HINT_START = '/* FIELDLIST */';
	/**
	 * Sometimes we have a nested query in field list. So in order for
	 * {@link SqlBuilder::getQueryTotals()} to work automatically, we need to
	 * supply a comment hint to end the field list.
	 * @var string
	 */
	const FIELD_LIST_HINT_END = '/* /FIELDLIST */';
	/**
	 * Standard SQL specifies '<>' as NOT EQUAL.
	 * @var string
	 */
	const OPERATOR_NOT_EQUAL = '<>';
	
	/**
	 * The model class, so we can pass-thru some method calls like query.
	 * Also useful for auto-determining database quirks like the char used
	 * around field names.
	 * @var \BitsTheater\Model
	 */
	public $myModel = null;
	/**
	 * The object used to sanitize field/orderby lists to help prevent
	 * SQL injection attacks.
	 * @var ISqlSanitizer
	 */
	public $mySqlSanitizer = null;
	
	public $mySql = '';
	public $myParams = array();
	public $myDataSet = array();
	
	public $myParamTypes = array();
	public $myParamFuncs = array();
	
	/**
	 * The char used around field names in case of spaces and keyword clashes.
	 * Determined by the database type being used (MySQL vs Oracle, etc.).
	 * @var string
	 */
	public $field_quotes = '`';
	/**
	 * Using the "=" when NULL is involved is ambiguous unless you know
	 * if it is part of a SET clause or WHERE clause.  Explicitly set
	 * this flag to let the SqlBuilder know it is in a WHERE clause.
	 * @var boolean
	 */
	public $bUseIsNull = false;
	
	/**
	 * Temporary parameter prefix used while constructing the SQL string.
	 * @var string
	 */
	public $myParamPrefix = ' ';
	/**
	 * Temporary operator used while constructing the SQL string.
	 * @var string
	 */
	public $myParamOperator = '=';
	/**
	 * Temporary parameter type used while constructing the SQL string.
	 * @var string
	 */
	public $myParamType = \PDO::PARAM_STR;
	/**
	 * Used to determine if we started a transaction or not.
	 * @var int Flag is incremented every time a transaction is requested
	 *   and decremented when commited; only begins/commits when transitioning
	 *   from 0 to 1 and back to 0. This allows us to "nest" transactions.
	 */
	public $myTransactionFlag = 0;
	
	/**
	 * Magic PHP method to limit what var_dump() shows.
	 */
	public function __debugInfo()
	{
		$vars = parent::__debugInfo();
		unset($vars['myModel']);
		unset($vars['mySqlSanitizer']);
		return $vars;
	}
	
	/**
	 * Models can use this class to help build their SQL queries / PDO statements.
	 * @param \BitsTheater\Model $aModel - the model being used.
	 * @return $this Returns the created object.
	 */
	static public function withModel( Model $aModel )
	{
		$theClassName = get_called_class();
		$o = new $theClassName($aModel->director);
		return $o->setModel($aModel);
	}
	
	/**
	 * Sets the model being used along with initializing database specific quirks.
	 * @param \BitsTheater\Model $aModel
	 * @return $this Returns $this for chaining.
	 */
	public function setModel( Model $aModel )
	{
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
	 * @return $this Returns $this for chaining.
	 * @deprecated Please use {@link SqlBuilder::obtainParamsFrom()}
	 */
	public function setDataSet( $aDataSet )
	{
		$this->myDataSet = $aDataSet;
		return $this;
	}
	
	/**
	 * Sets the dataset to be used by addParam methods. Alias for setDataSet().
	 * @param array or StdClass $aDataSet - array or class used to get param data.
	 * @return $this Returns $this for chaining.
	 */
	public function obtainParamsFrom( $aDataSet )
	{
		$this->myDataSet = $aDataSet;
		return $this;
	}
	
	/**
	 * Set the object used for sanitizing SQL to help prevent SQL Injection attacks.
	 * @param ISqlSanitizer $aSqlSanitizer - the object used to sanitize field/orderby lists.
	 * @return $this Returns $this for chaining.
	 */
	public function setSanitizer( ISqlSanitizer $aSanitizer=null )
	{
		$this->mySqlSanitizer = $aSanitizer;
		return $this;
	}
	
	/**
	 * Retrieve the data that will be used for a particular param.
	 * @param string $aDataKey - array key or property name used to retrieve
	 *   data set by the obtainParamsFrom() method.
	 * @param string $aDefaultValue - (OPTIONAL) default value if data is null.
	 * @return mixed Returns the data value.
	 * @see \BitsTheater\costumes\SqlBuilder::getParamValue()
	 */
	public function getDataValue( $aDataKey, $aDefaultValue=null )
	{
		$theValue = $this->getParamValue($aDataKey);
		if ( is_null($theValue) )
		{ $theValue = $aDefaultValue; }
		return $theValue;
	}
	
	/**
	 * Retrieve the data that will be used for a particular param.
	 * May call the defined param function, unlike the getParam() method.
	 * @param string $aParamKey - array key or property name used to retrieve
	 *   data set by the obtainParamsFrom() method.
	 * @return mixed Returns the data value.
	 * @see \BitsTheater\costumes\SqlBuilder::obtainParamsFrom()
	 */
	public function getParamValue( $aParamKey )
	{
		$theData = null;
		if ( isset($this->myDataSet) ) {
			if ( is_array($this->myDataSet) ) {
				if ( isset($this->myDataSet[$aParamKey]) ) {
					$theData = $this->myDataSet[$aParamKey];
				}
			} else if ( is_object($this->myDataSet) ) {
				if ( isset($this->myDataSet->{$aParamKey}) ) {
					$theData = $this->myDataSet->{$aParamKey};
				}
			} else if ( is_string($this->myDataSet) ) {
				$theData = $this->myDataSet;
			}
		}
		//see if there is a data processing function and call it
		if ( isset($this->myParamFuncs[$aParamKey]) ) {
			$theData = $this->myParamFuncs[$aParamKey]($this, $aParamKey, $theData);
		}
		return $theData;
	}
	
	/**
	 * Mainly used in cases where param data container may be an unknown type,
	 * yet we want to tweak it or set a default value.
	 * @param string $aParamKey - array key or property name used to retrieve
	 *   data set by the obtainParamsFrom() method.
	 * @param string $aNewValue - new value to use.
	 * @return $this Returns $this for chaining.
	 * @see \BitsTheater\costumes\SqlBuilder::obtainParamsFrom()
	 */
	public function setParamValue( $aParamKey, $aNewValue )
	{
		if ( isset($this->myDataSet) ) {
			if ( is_array($this->myDataSet) ) {
				$this->myDataSet[$aParamKey] = $aNewValue;
			}
			else if ( is_object($this->myDataSet) ) {
				$this->myDataSet->{$aParamKey} = $aNewValue;
			}
			else if ( is_string($this->myDataSet) ) {
				$this->myDataSet = $aNewValue;
			}
		}
		return $this;
	}
	
	/**
	 * Set a value for a param when its data value is NULL.
	 * @param string $aParamKey - array key or property name used to retrieve
	 *   data set by the obtainParamsFrom() method.
	 * @param string $aNewValue - new value to use.
	 * @return $this Returns $this for chaining.
	 * @see \BitsTheater\costumes\SqlBuilder::obtainParamsFrom()
	 */
	public function setParamValueIfNull( $aParamKey, $aNewValue )
	{
		$theDataValue = $this->getParamValue($aParamKey);
		if ( is_null($theDataValue) ) {
			$this->setParamValue($aParamKey, $aNewValue);
		}
		return $this;
	}
	
	/**
	 * Set a value for a param when its data value is empty(). e.g. null|""|0
	 * @param string $aParamKey - array key or property name used to retrieve
	 *   data set by the obtainParamsFrom() method.
	 * @param string $aNewValue - new value to use.
	 * @return $this Returns $this for chaining.
	 * @see \BitsTheater\costumes\SqlBuilder::obtainParamsFrom()
	 */
	public function setParamValueIfEmpty( $aParamKey, $aNewValue )
	{
		$theDataValue = $this->getParamValue($aParamKey);
		if ( empty($theDataValue) ) {
			//check for empty on new value and ensure NULL if so;
			//  why set an empty value only if current one is empty? to ensure
			//  addParam() will either include the param in the query or not.
			if ( !is_null($aNewValue) && empty($aNewValue) )
			{ $aNewValue = null; }
			$this->setParamValue($aParamKey, $aNewValue);
		}
		return $this;
	}
	
	/**
	 * Mainly used internally by addParamIfDefined to determine if data param exists.
	 * @param string $aDataKey - array key or property name used to retrieve
	 *   data set by the obtainParamsFrom() method.
	 * @return boolean Returns TRUE if data key is defined (or param function exists).
	 * @see \BitsTheater\costumes\SqlBuilder::obtainParamsFrom()
	 */
	public function isDataKeyDefined( $aDataKey )
	{
		//see if there is a data processing function
		$bResult = ( isset($this->myParamFuncs[$aDataKey]) );
		if ( !$bResult && isset($this->myDataSet) ) {
			if ( is_array($this->myDataSet) ) {
				$bResult = array_key_exists($aDataKey, $this->myDataSet);
			} else if ( is_object($this->myDataSet) ) {
				$bResult = property_exists($this->myDataSet, $aDataKey);
			} else if ( is_string($this->myDataSet) ) {
				$bResult = true;
			}
		}
		return $bResult;
	}
	
	/**
	 * Resets the object so it can be resused with same dataset.
	 * @param boolean $bClearDataFunctionsToo - (optional) clear data processing
	 *   functions as well (default is FALSE).
	 * @return $this Returns $this for chaining.
	 */
	public function reset( $bClearDataFunctionsToo=false )
	{
		$this->myParamPrefix = ' ';
		$this->myParamOperator = '=';
		$this->myParamType = \PDO::PARAM_STR;
		$this->mySql = '';
		$this->myParams = array();
		$this->myParamTypes = array();
		if ( $bClearDataFunctionsToo )
		{ $this->myParamFuncs = array(); }
		return $this;
	}
	
	/**
	 * Sets the param value and param type, but does not affect the SQL string.
	 * @param string $aParamKey - the field/param name.
	 * @param mixed $aParamValue - the param value to use.
	 * @param number $aParamType - (OPTIONAL) \PDO::PARAM_* integer constant.
	 * @return $this Returns $this for chaining.
	 */
	public function setParam( $aParamKey, $aParamValue, $aParamType=null )
	{
		if ( isset($aParamValue) ) {
			$this->myParams[$aParamKey] = $aParamValue;
			if ( isset($aParamType) ) {
				$this->myParamTypes[$aParamKey] = $aParamType;
			} else if ( !isset($this->myParamTypes[$aParamKey]) ) {
				$this->myParamTypes[$aParamKey] = $this->myParamType;
			}
		} else {
			$this->myParams[$aParamKey] = null;
			$this->myParamTypes[$aParamKey] = \PDO::PARAM_NULL;
		}
		return $this;
	}
	
	/**
	 * Gets the current value of a param that has been added.
	 * @param string $aParamKey - the param key to retrieve.
	 * @return mixed Returns the data.
	 */
	public function getParam( $aParamKey )
	{
		if ( isset($this->myParams[$aParamKey]) )
		{ return $this->myParams[$aParamKey]; }
	}
	
	/**
	 * Sets the SQL string to this value.
	 * @param string $aSql - the first part of a SQL string to build upon.
	 * @return $this Returns $this for chaining.
	 */
	public function startWith( $aSql )
	{
		$this->mySql = $aSql;
		return $this;
	}
	
	/**
	 * Adds the fieldlist to the SQL string.
	 * @param array|string $aFieldList - the list or comma string of fieldnames.
	 * @return $this Returns $this for chaining.
	 */
	public function addFieldList( $aFieldList )
	{
		$theFieldList = '*' ;
		if( !empty($aFieldList) )
		{
			if( is_array($aFieldList) )
			{
				if( !empty($this->myParamPrefix) )
				{ // ensure prefixes are applied
					$thePrefixedFieldList = array() ;
					foreach( $aFieldList as $aField )
					{
						if( strpos( $aField, $this->myParamPrefix ) !== 0 )
							$thePrefixedFieldList[] =
								$this->myParamPrefix . $aField ;
						else
							$thePrefixedFieldList[] = $aField ;
					}
					$theFieldList = implode( ', ', $thePrefixedFieldList ) ;
				}
				else
					$theFieldList = implode( ', ', $aFieldList ) ;
			}
			else if( is_string($aFieldList) )
			{ // add it as-is; let the caller beware
				$theFieldList = $aFieldList ;
			}
		}
		else if( !empty( $this->myParamPrefix ) )
		{
			$theFieldList = $this->myParamPrefix . '*' ;
		}
		return $this->add($theFieldList) ;
	}
	
	/**
	 * Adds to the SQL string as a set of values;
	 * e.g. "(datakey_1,datakey_2, .. datakey_N)"
	 * along with param values set for each of the keys.
	 * Honors the ParamPrefix and ParamOperator properties.
	 * @param string $aColumnName - the table column (aka field) name.
	 * @param string $aParamKey - array key or property name used to retrieve
	 *   data set by the obtainParamsFrom() method;
	 *   NOTE: $aDataKeys will have _$i from 1..count() appended.
	 * @param array $aDataValuesList - the value list to use as param values.
	 * @param number $aParamType - (OPTIONAL) \PDO::PARAM_* integer constant
	 *   to use instead of relying on the current param type setting.
	 * @return $this Returns $this for chaining.
	 */
	public function addParamAsListForColumn($aColumnName, $aParamKey,
			$aDataValuesList, $aParamType=null)
	{
		if ( is_array($aDataValuesList) && !empty($aDataValuesList) ) {
			$this->mySql .= $this->myParamPrefix;
			$this->mySql .= $this->field_quotes . $aColumnName . $this->field_quotes;
			$this->mySql .= $this->myParamOperator.'(';
			$i = 1;
			foreach ( $aDataValuesList as $theDataValue ) {
				$theDataKey = $aParamKey . '_' . $i++;
				$this->mySql .= ':' . $theDataKey . ',';
				$this->setParam($theDataKey, $theDataValue, $aParamType);
			}
			$this->mySql = rtrim($this->mySql, ',') . ')';
		}
		return $this;
	}
	
	/**
	 * Internal method to affect SQL statment with a param and its value.
	 * @param string $aFieldName - the field name.
	 * @param string $aParamKey - the param name.
	 * @param mixed $aParamValue - the param value to use.
	 * @param number $aParamType - (OPTIONAL) \PDO::PARAM_* integer constant
	 *   to use instead of relying on the current param type setting.
	 */
	protected function addingParam( $aFieldName, $aParamKey, $aParamValue,
			$aParamType=null )
	{
		if ( is_array($aParamValue) && !empty($aParamValue) ) {
			$saveParamOp = $this->myParamOperator;
			switch ( trim($this->myParamOperator) ) {
				case '=':
					$this->myParamOperator = ' IN ';
					break;
				case self::OPERATOR_NOT_EQUAL:
					$this->myParamOperator = ' NOT IN ';
					break;
			}//switch
			$this->addParamAsListForColumn($aFieldName, $aParamKey, $aParamValue, $aParamType);
			$this->myParamOperator = $saveParamOp;
		}
		else {
			if ( is_array($aParamValue) && empty($aParamValue) ) {
				$aParamValue = null;
			}
			if ( !is_null($aParamValue) || !$this->bUseIsNull ) {
				$this->mySql .= $this->myParamPrefix .
						$this->field_quotes . $aFieldName . $this->field_quotes .
						$this->myParamOperator . ':' . $aParamKey ;
				$this->setParam($aParamKey, $aParamValue, $aParamType);
			} else {
				switch ( trim($this->myParamOperator) ) {
					case '=':
						$this->mySql .= $this->myParamPrefix .
								$this->field_quotes . $aFieldName . $this->field_quotes .
								' IS NULL' ;
						break;
					case self::OPERATOR_NOT_EQUAL:
						$this->mySql .= $this->myParamPrefix .
								$this->field_quotes . $aFieldName . $this->field_quotes .
								' IS NOT NULL' ;
						break;
				}//switch
			}
		}
	}
	
	/**
	 * Parameter must go into the SQL string regardless of NULL status of data.
	 * @param string $aFieldName - field name to use.
	 * @param string $aDataKey - array key or property name used to retrieve
	 *   data set by the obtainParamsFrom() method.
	 * @param mixed $aDefaultValue - (optional) default value if data is null.
	 * @param number $aParamType - (optional) PDO::PARAM_* integer constant (STR is default).
	 * @return $this Returns $this for chaining.
	 * @deprecated use mustAddParamForColumn() instead.
	 */
	public function mustAddFieldAndParam($aFieldName, $aDataKey,
			$aDefaultValue=null, $aParamType=\PDO::PARAM_STR)
	{
		$theData = $this->getDataValue($aDataKey, $aDefaultValue);
		$this->addingParam($aFieldName, $aDataKey, $theData, $aParamType);
		return $this;
	}
	
	/**
	 * Parameter must go into the SQL string regardless of NULL status of data.
	 * This is a "shortcut" method designed to combine calls to setParamType(),
	 * setParamValueIfNull(), and addParam().
	 * @param string $aParamKey - the param key used to get its data.
	 * @param mixed $aDefaultValue - (OPTIONAL) default value if data is null.
	 * @param number $aParamType - (OPTIONAL) PDO::PARAM_* integer constant (STR is default).
	 * @return $this Returns $this for chaining.
	 */
	public function mustAddParam( $aParamKey, $aDefaultValue=null, $aParamType=null )
	{
		$theData = $this->getDataValue($aParamKey, $aDefaultValue);
		$this->addingParam($aParamKey, $aParamKey, $theData, $aParamType);
		return $this;
	}
	
	/**
	 * Parameter must go into the SQL string regardless of NULL status of data.
	 * This is a "shortcut" method designed to combine calls to setParamType,
	 * setParamValue, and addParam.
	 * @param string $aParamKey - the param key used to get its data.
	 * @param string $aColumnName - the table column (aka field) name.
	 * @param mixed $aDefaultValue - (OPTIONAL) default value if data is null.
	 * @param number $aParamType - (OPTIONAL) \PDO::PARAM_* integer constant
	 *   to use instead of relying on the current param type setting.
	 * @return $this Returns $this for chaining.
	 */
	public function mustAddParamForColumn( $aParamKey, $aColumnName,
			$aDefaultValue=null, $aParamType=null )
	{
		$theData = $this->getDataValue($aParamKey, $aDefaultValue);
		$this->addingParam($aColumnName, $aParamKey, $theData, $aParamType);
		return $this;
	}
	
	/**
	 * Parameter only gets added to the SQL string if data IS NOT NULL.
	 * @param string $aParamKey - the param key used to get its data.
	 * @return $this Returns $this for chaining.

	 */
	public function addParam( $aParamKey )
	{
		$theData = $this->getParamValue($aParamKey);
		if ( isset($theData) ) {
			$this->addingParam($aParamKey, $aParamKey, $theData);
		}
		return $this;
	}
	
	/**
	 * Parameter only gets added to the SQL string if data IS NOT NULL.
	 * @param string $aParamKey - the param key used to get its data.
	 * @param string $aColumnName - the table column (aka field) name.
	 * @return $this Returns $this for chaining.

	 */
	public function addParamForColumn( $aParamKey, $aColumnName )
	{
		$theData = $this->getParamValue($aParamKey);
		if ( isset($theData) ) {
			$this->addingParam($aColumnName, $aParamKey, $theData);
		}
		return $this;
	}
	
	/**
	 * Rather than rely on the current param type settings, apply the given
	 * param type for this particular param being added.
	 * @param string $aParamKey - array key or property name used to retrieve
	 *   data set by the obtainParamsFrom() method.
	 * @param number $aParamType - \PDO::PARAM_* integer constant to use
	 *   instead of relying on the current param type setting.
	 */
	public function addParamOfType($aParamKey, $aParamType)
	{
		$theData = $this->getParamValue($aParamKey);
		if ( isset($theData) ) {
			$this->addingParam($aParamKey, $aParamKey, $theData, $aParamType);
		}
		return $this;
	}

	/**
	 * Parameter gets added to the SQL string if data key exists in data set,
	 * EVEN IF THE DATA IS NULL.
	 * @param string $aParamKey - array key or property name used to retrieve
	 *   data set by the obtainParamsFrom() method.
	 * @param number $aParamType - (OPTIONAL) \PDO::PARAM_* integer constant
	 *   to use instead of relying on the current param type setting.
	 * @param $aParamTypeDeprecated - (IGNORE) defined for backward
	 *   compatibility only!
	 * @return $this Returns $this for chaining.
	 */
	public function addParamIfDefined($aParamKey, $aParamType=null,
			$aParamTypeDeprecated=null)
	{
		if ( $this->isDataKeyDefined($aParamKey) ) {
			$theData = $this->getParamValue($aParamKey);
			//2nd param was always ignored anyway, if older code specified a 3rd param,
			//  just make it our 2nd param instead.
			if ( !is_null($aParamTypeDeprecated) )
				$aParamType = $aParamTypeDeprecated;
			$this->addingParam($aParamKey, $aParamKey, $theData, $aParamType);
		}
		return $this;
	}

	/**
	 * Parameter gets added to the SQL string if data key exists in data set.
	 * @param string $aParamKey - the param key used to get its data.
	 * @param string $aColumnName - the table column (aka field) name.
	 * @return $this Returns $this for chaining.
	 */
	public function addParamIfDefinedForColumn($aParamKey, $aColumnName)
	{
		if ( $this->isDataKeyDefined($aParamKey) ) {
			$theData = $this->getParamValue($aParamKey);
			$this->addingParam($aColumnName, $aParamKey, $theData);
		}
		return $this;
	}

	/**
	 * Sets the "glue" string that gets prepended to all subsequent calls
	 * to addParam kinds of methods. Spacing is important here, so add what
	 * is needed!
	 * @param string $aStr - glue to prepend.
	 * @return $this Returns $this for chaining.
	 */
	public function setParamPrefix( $aStr=', ' )
	{
		$this->myParamPrefix = $aStr;
		return $this;
	}

	/**
	 * Operator string to use in all subsequent calls to addParam methods.
	 * @param string $aStr - operator string to use ('=' is default,
	 *   ' LIKE ' is a popular operator as well).
	 * @return $this Returns $this for chaining.
	 */
	public function setParamOperator( $aStr='=' )
	{
		if ( strpos($aStr, '!=') !== false )
		{ $aStr = str_replace('!=', self::OPERATOR_NOT_EQUAL, $aStr); }
		$this->myParamOperator = $aStr;
		return $this;
	}
	
	/**
	 * Parameter type to use in all subsequent calls to addParam methods.
	 * @param number $aParamType - \PDO::PARAM_* integer constant.
	 * @return $this Returns $this for chaining.
	 */
	public function setParamType( $aParamType )
	{
		$this->myParamType = $aParamType;
		return $this;
	}
	
	/**
	 * Adds a string to the SQL prefixed with a space (just in case).
	 *
	 * <b><i><u>DO NOT</u></i></b> use this method to write values gathered from
	 * user input directly into a query. <b><i><u>ALWAYS</u></i></b> use the
	 * <code>addParam()</code> or similar methods, or pre-sanitize the data
	 * value before writing it into the query.
	 *
	 * @param string $aStr - patial SQL sting to add.
	 * @return $this Returns $this for chaining.
	 */
	public function add( $aStr )
	{
		$this->mySql .= ' ' . $aStr;
		return $this;
	}
	
	/**
	 * Adds a string to the SQL, prefixed with a space, and wrapped in
	 * single-quotes, representing a literal string in an SQL statement.
	 *
	 * <b><i><u>DO NOT</u></i></b> use this method to write values gathered from
	 * user input directly into a query. <b><i><u>ALWAYS</u></i></b> use the
	 * <code>addParam()</code> or similar methods, or pre-sanitize the data
	 * value before writing it into the query.
	 *
	 * @param string $aStr the string to be wrapped and appended
	 * @param boolean $bAndAComma (optional:false) also append a comma and an
	 *  additional space. Useful for composing lists.
	 * @return $this Returns $this for chaining.
	 * @since BitsTheater v4.1.0
	 */
	public function addQuoted( $aStr, $bAndAComma=false )
	{
		$this->mySql .= ' \'' . $aStr . ( $bAndAComma ? '\', ' : '\'' ) ;
		return $this ;
	}
	
	/**
	 * Sometimes parameter data needs processing before being used.
	 * @param string $aParamKey - the parameter key.
	 * @param callable $aParamFunc - a function used to process the
	 *   data (even a default value) of the form:<br>
	 *   func($thisSqlBuilder, $paramKey, $currentParamValue) and returns
	 *   the processed value.
	 * @return $this Returns $this for chaining.
	 */
	public function setParamDataHandler( $aParamKey, $aParamFunc )
	{
		if ( !empty($aParamKey) ) {
			$this->myParamFuncs[$aParamKey] = $aParamFunc;
		}
		return $this;
	}
	
	/**
	 * Sub-query gets added to the SQL string.
	 * @param SqlBuilder $aSubQuery - the sub-query object to copy the SQL
	 *   string from and insert as "("+SubQuery->SQL+")".
	 * @param string $aColumnName - the table column (aka field) name.
	 * @param boolean $bExpectMultipleResults - (OPTIONAL) whether to expect
	 *   an array of results or just a single one. Default is TRUE.
	 * @return $this Returns $this for chaining.
	 */
	public function addSubQueryForColumn( SqlBuilder $aSubQuery, $aColumnName,
			$bExpectMultipleResults=true )
	{
		$saveParamOp = $this->myParamOperator;
		if ( $bExpectMultipleResults ) {
			switch ( trim($this->myParamOperator) ) {
				case '=':
					$this->myParamOperator = ' IN ';
					break;
				case self::OPERATOR_NOT_EQUAL:
					$this->myParamOperator = ' NOT IN ';
					break;
			}//switch
		}
		$this->mySql .= $this->myParamPrefix .
				$this->field_quotes . $aColumnName . $this->field_quotes .
				$this->myParamOperator . '(' . $aSubQuery->mySql . ')' ;
		$this->myParamOperator = $saveParamOp;
		//also merge in any params and param types from the sub-query
		$this->myParams = array_merge($this->myParams, $aSubQuery->myParams);
		$this->myParamTypes = array_merge($this->myParamTypes, $aSubQuery->myParamTypes);
		return $this;
	}
	
	/**
	 * Apply an externally defined set of WHERE field clauses and param values
	 * to our SQL (excludes the "WHERE" keyword).
	 * @param SqlBuilder $aFilter - External SqlBuilder object (can be NULL).
	 * @return $this Returns $this for chaining.
	 */
	public function applyFilter( SqlBuilder $aFilter=null )
	{
		if ( !empty($aFilter) ) {
			if ( !empty($aFilter->mySql) ) {
				$this->mySql .= $this->myParamPrefix.$aFilter->mySql;
			}
			if ( !empty($aFilter->myParams) ) {
				foreach ($aFilter->myParams as $theFilterParamKey => $theFilterParamValue) {
					$this->setParam( $theFilterParamKey, $theFilterParamValue,
							$aFilter->myParamTypes[$theFilterParamKey]
					);
				}
			}
		}
		return $this;
	}
	
	/**
	 * If sort list is defined and its contents are also contained
	 * in the non-empty $aFieldList, then apply the sort order as neccessary.
	 * @see \BitsTheater\costumes\SqlBuilder::applyOrderByList() which this method is an alias of.
	 * @param array $aSortList - keys are the fields => values are
	 *   'ASC'|true or 'DESC'|false with null='ASC'.
	 * @param string|string[] $aFieldList - the list or comma string of fieldnames.
	 * @return $this Returns $this for chaining.
	 */
	public function applySortList( $aSortList, $aFieldList=null )
	{ return $this->applyOrderByList($aSortList, $aFieldList); }
	
	/**
	 * If order by list is defined, then apply the sort order as neccessary.
	 * @param array $aOrderByList - keys are the fields => values are
	 *   'ASC'|true or 'DESC'|false with null='ASC'.
	 * @return $this Returns $this for chaining.
	 */
	public function applyOrderByList( $aOrderByList )
	{
		if ( !empty($aOrderByList) ) {
			$theSortKeyword = 'ORDER BY';
			//other db types may use a diff reserved keyword, set that here
			//TODO ...
			$this->add($theSortKeyword);
			
			$theOrderByList = $aOrderByList;
			//if plain string[] implying all ASC order, skip processing step
			if ( !isset($theOrderByList[0]) ) {
				$theOrderByList = array();
				foreach ($aOrderByList as $theField => $theSortOrder) {
					$theEntry = $theField . ' ';
					if ( is_bool($theSortOrder) )
						$theEntry .= ($theSortOrder) ? self::ORDER_BY_ASCENDING : self::ORDER_BY_DESCENDING;
					else if ( strtoupper(trim($theSortOrder))==self::ORDER_BY_DESCENDING )
						$theEntry .= self::ORDER_BY_DESCENDING;
					else
						$theEntry .= self::ORDER_BY_ASCENDING;
					$theOrderByList[] = $theEntry;
				}
			}
			$this->add(implode(',', $theOrderByList));
		}
		return $this;
	}
	
	/**
	 * Retrieve the order by list from the sanitizer which might be from the UI or a default.
	 * @return $this Returns $this for chaining.
	 */
	public function applyOrderByListFromSanitizer()
	{
		if ( !empty($this->mySqlSanitizer) )
			return $this->applyOrderByList( $this->mySqlSanitizer->getSanitizedOrderByList() ) ;
		else
			return $this;
	}
	
	/**
	 * Some operators require alternate handling during WHERE clauses
	 * (e.g. "=" with NULLs). This will setParamPrefix(" WHERE ") which will
	 * apply to the next addParam.
	 * @param string $aAdditionalParamPrefix - string to append to " WHERE "
	 *   as the next param prefix.
	 * @return $this Returns $this for chaining.
	 */
	public function startWhereClause( $aAdditionalParamPrefix='' )
	{
		$this->bUseIsNull = true;
		return $this->setParamPrefix(' WHERE '.$aAdditionalParamPrefix);
	}
	
	/**
	 * Some operators require alternate handling during WHERE clauses (e.g. "=" with NULLs).
	 * @return $this Returns $this for chaining.
	 */
	public function endWhereClause()
	{
		$this->bUseIsNull = false;
		return $this;
	}
	
	/**
	 * Some operators require alternate handling during WHERE clauses
	 * (e.g. "=" with NULLs). Similar to startWhereClause(), this method is
	 * specific to building a filter object that consists entirely of a
	 * partial WHERE clause which will get appended to the main SqlBuilder
	 * object using applyFilter().
	 * @param string $aAdditionalParamPrefix - (OPTIONAL) string to set as the
	 *   inital value for setParamPrefix(). Defaults to " AND ".
	 * @return $this Returns $this for chaining.
	 */
	public function startFilter( $aSetParamPrefix=' AND ' )
	{
		$this->bUseIsNull = true;
		$this->startWith('1');
		return $this->setParamPrefix($aSetParamPrefix);
	}
	
	//=================================================================
	// MAPPED FUNCTIONS TO MODEL
	//=================================================================

	/**
	 * Execute DML (data manipulation language - INSERT, UPDATE, DELETE) statements.
	 * @throws DbException if there is an error.
	 * @return number|\PDOStatement Returns the number of rows affected OR if using params,
	 *   the PDOStatement.
	 * @see \BitsTheater\Model::execDML();
	 */
	public function execDML() {
		return $this->myModel->execDML($this->mySql, $this->myParams, $this->myParamTypes);
	}
	
	/**
	 * Executes DML statement and then checks the returned SQLSTATE.
	 * @param string|array $aSqlState5digitCodes - standard 5 digit codes to check,
	 *   defaults to '02000', meaning "no data"; e.g. UPDATE/DELETE failed due
	 *   to record defined by WHERE clause returned no data. May be a comma separated
	 *   list of codes or an array of codes to check against.
	 * @return boolean Returns the result of the SQLSTATE check.
	 * @link https://ib-aid.com/download/docs/firebird-language-reference-2.5/fblangref25-appx02-sqlstates.html
	 */
	public function execDMLandCheck($aSqlState5digitCodes=array(self::SQLSTATE_NO_DATA)) {
		$theExecResult = $this->execDML();
		if (!empty($aSqlState5digitCodes)) {
			$theStatesToCheck = null;
			if (is_string($aSqlState5digitCodes)) {
				$theStatesToCheck = explode(',', $aSqlState5digitCodes);
			} else if (is_array($aSqlState5digitCodes)) {
				$theStatesToCheck = $aSqlState5digitCodes;
			}
			if (!empty($theStatesToCheck)) {
				$theSqlState = $theExecResult->errorCode();
				return (array_search($theSqlState, $theStatesToCheck, true)!==false);
			}
		}
		return !empty($theExecResult);
	}
	
	/**
	 * Executes DML statement and then checks the returned SQLSTATE.
	 * @param string $aSqlState5digitCode - a single standard 5 digit code to check,
	 *   defaults to '02000', meaning "no data"; e.g. UPDATE/DELETE failed due
	 *   to record defined by WHERE clause returned no data.
	 * @return boolean Returns the result of the SQLSTATE check.
	 * @see SqlBuilder::execDMLandCheck()
	 * @link https://dev.mysql.com/doc/refman/5.6/en/error-messages-server.html
	 * @link https://ib-aid.com/download/docs/firebird-language-reference-2.5/fblangref25-appx02-sqlstates.html
	 */
	public function execDMLandCheckCode($aSqlState5digitCode=self::SQLSTATE_NO_DATA) {
		return ($this->execDML()->errorCode()==$aSqlState5digitCode);
	}
	
	/**
	 * Execute Select query, returns PDOStatement.
	 * @throws DbException if there is an error.
	 * @return \PDOStatement on success.
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
	 * @return int Returns the lastInsertId().
	 * @see \BitsTheater\Model::addAndGetId();
	 */
	public function addAndGetId() {
		return $this->myModel->addAndGetId($this->mySql, $this->myParams, $this->myParamTypes);
	}
	
	/**
	 * Execute DML (data manipulation language - INSERT, UPDATE, DELETE) statements
	 * and return the params used in the query. Convenience method when using
	 * parameterized queries since PDOStatement::execDML() always only returns TRUE.
	 * @throws DbException if there is an error.
	 * @return string[] Returns the param data.
	 * @see \PDOStatement::execute();
	 */
	public function execDMLandGetParams()
	{
		$this->myModel->execDML($this->mySql, $this->myParams, $this->myParamTypes);
		return $this->myParams;
	}
	
	/**
	 * Sometimes we want to aggregate the query somehow rather than return data from it.
	 * @param array $aSqlAggragates - (optional) the aggregation list, defaults to array('count(*)'=>'total_rows').
	 * @return array Returns the results of the aggregates.
	 */
	public function getQueryTotals( $aSqlAggragates=array('count(*)'=>'total_rows') )
	{
		$theSqlFields = array();
		foreach ($aSqlAggragates as $theField => $theName)
			array_push($theSqlFields, $theField . ' AS ' . $theName);
		$theSelectFields = implode(', ', $theSqlFields);
		$sqlTotals = $this->cloneFrom($this);
		try {
			return $sqlTotals->replaceSelectFieldsWith($theSelectFields)
					->getAggregateResults(array_values($aSqlAggragates))
			;
		} catch (\PDOException $pdoe)
		{ throw $sqlTotals->newDbException(__METHOD__, $pdoe); }
	}
	
	/**
	 * Create a clone of the object param and return it.
	 * @param SqlBuilder $aSqlBuilder - the builder to clone.
	 * @return SqlBuilder Returns the cloned builder.
	 */
	public function cloneFrom(SqlBuilder $aSqlBuilder)
	{
		return clone $aSqlBuilder;
	}
	
	/**
	 * Replace the currently formed SELECT fields with the param.  If you have nested queries,
	 * you will need to use the
	 * <pre>SELECT /&#42 FIELDLIST &#42/ field1, field2, (SELECT blah) AS field3 /&#42 /FIELDLIST &#42/ FROM</pre>
	 * hints in the SQL.
	 * @param string|array $aSelectFields - (optional) the fields to use instead, defaults to "*".
	 * @return SqlBuilder Returns $this for chaining.
	 */
	public function replaceSelectFieldsWith($aSelectFields=null)
	{
		$theSelectFields = null;
		if (is_string($aSelectFields))
			$theSelectFields = $aSelectFields;
		if (is_array($aSelectFields))
			$theSelectFields = implode(',', $aSelectFields);
		if (empty($theSelectFields))
			$theSelectFields = '*';
		$theSelectFields = 'SELECT '.$theSelectFields.' FROM';
		//nested queries can mess us up, so check for hints first
		if (strpos($this->mySql, self::FIELD_LIST_HINT_START)>0 &&
				strpos($this->mySql, self::FIELD_LIST_HINT_END)>0)
		{
			$this->mySql = preg_replace('|SELECT /\* FIELDLIST \*/ .+? /\* /FIELDLIST \*/ FROM|i',
					$theSelectFields, $this->mySql, 1
			);
		}
		else
		{
			//we want a "non-greedy" match so that it stops at the first "FROM" it finds: ".+?"
			$this->mySql = preg_replace('|SELECT .+? FROM|i', $theSelectFields, $this->mySql, 1);
		}
		//$this->debugLog(__METHOD__.' sql='.$theSql->mySql.' params='.$this->debugStr($theSql->myParams));
		return $this;
	}

	/**
	 * Execute the currently built SELECT query and retrieve all the aggregates as numbers.
	 * @param string[] $aSqlAggragateNames - the aggregate names to retrieve.
	 * @return number[] Returns the array of aggregate values.
	 */
	public function getAggregateResults( $aSqlAggragateNames=array('total_rows') )
	{
		$theResults = array();
		//$this->debugLog(__METHOD__.' sql='.$theSql->mySql.' params='.$this->debugStr($theSql->myParams));
		$theRow = $this->getTheRow();
		if (!empty($theRow)) {
			foreach ($aSqlAggragateNames as $theName)
			{
				$theResults[$theName] = $theRow[$theName]+0;
			}
		}
		return $theResults;
	}
	
	/**
	 * Standardized SQL failure log(s) meant to easily record DbExceptions on the
	 * server for postmortem debugging.
	 * @param string $aWhatFailed - the thing that failed, typically __METHOD__.
	 * @param string|object $aMsgOrException - error message or Exception object.
	 * @return SqlBuilder Returns $this for chaining.
	 */
	public function logSqlFailure($aWhatFailed, $aMsgOrException) {
		$theMsg = (is_object($aMsgOrException) && method_exists($aMsgOrException, 'getMessage'))
				? $aMsgOrException->getMessage()
				: $aMsgOrException
		;
		$this->errorLog($aWhatFailed . ' [1/3] failed: ' . $theMsg);
		$this->errorLog($aWhatFailed . ' [2/3] sql=' . $this->mySql);
		$this->errorLog($aWhatFailed . ' [3/3] params=' . $this->debugStr($this->myParams));
		if ( $this->getDirector()->isDebugging() &&
				is_callable(array($aMsgOrException, 'getTraceAsString')) )
		{ $this->errorLog( $aMsgOrException->getTraceAsString() ); }
		return $this;
	}
	
	/**
	 * Create the DbException object and log the SQL failure.
	 * @param string $aWhatFailed - the thing that failed, typically __METHOD__.
	 * @param \PDOException $aPdoException - the PDOException that occurred.
	 * @return DbException Return the new exception object.
	 */
	public function newDbException($aWhatFailed, $aPdoException) {
		$this->logSqlFailure($aWhatFailed, $aPdoException);
		return new DbException($aPdoException, $aWhatFailed . ' failed.');
	}
	
	/**
	 * Standardized SQL failure log(s) meant to easily record DbExceptions on the
	 * server for postmortem debugging.
	 * @param string $aWhatMethod - the thing that you are debugging, typically __METHOD__.
	 * @param string $aMsg - (optional) debug message.
	 * @return SqlBuilder Returns $this for chaining.
	 */
	public function logSqlDebug($aWhatMethod, $aMsg = '') {
		$this->debugLog($aWhatMethod . ' [1/2] ' . $aMsg . ' sql=' . $this->mySql);
		$this->debugLog($aWhatMethod . ' [2/2] params=' . $this->debugStr($this->myParams));
		return $this;
	}
	
	/**
	 * PDO requires all query parameters be unique. This poses an issue when multiple datakeys
	 * with the same name are needed in the query (especially true for MERGE queries). This
	 * method will check for any existing parameters named $aDataKey and will return a new
	 * name with a number for a suffix to ensure its uniqueness.
	 * @param string $aDataKey - the parameter/datakey name.
	 * @return string Return the $aDataKey if already unique, else a modified version to
	 *   ensure it is unique in the query-so-far.
	 */
	public function getUniqueDataKey($aDataKey) {
		$i = 1;
		$theDataKey = $aDataKey;
		while (array_key_exists($theDataKey, $this->myParams))
			$theDataKey = $aDataKey . strval(++$i);
		return $theDataKey;
	}
	
	/**
	 * Quoted identifiers are DB vendor specific so providing a helper method to just
	 * return a properly quoted string for MySQL vs MSSQL vs Oracle, etc. is handy.
	 * @param string $aIdentifier - the string to quote.
	 * @return string Returns the string properly quoted for the database connection in use.
	 */
	public function getQuoted( $aIdentifier )
	{
		return $this->field_quotes
				. str_replace($this->field_quotes,
						str_repeat($this->field_quotes, 2), $aIdentifier)
				. $this->field_quotes
		;
	}
	
	/**
	 * Providing click-able headers in tables to easily sort them by a particular field
	 * is a great UI feature. However, in order to prevent SQL injection attacks, we
	 * must double-check that a supplied field name to order the query by is something
	 * we can sort on; this method makes use of the <code>Scene::isFieldSortable()</code>
	 * method to determine if the browser supplied field name is one of our possible
	 * headers that can be clicked on for sorting purposes. The Scene's properties called
	 * <code>orderby</code> and <code>orderbyrvs</code> are used to determine the result.
	 * @param object $aScene - the object, typically a Scene decendant, which is used
	 *   to call <code>isFieldSortable()</code> and access the properties
	 *   <code>orderby</code> and <code>orderbyrvs</code>.
	 * @param array $aDefaultOrderByList - (optional) default to use if no proper
	 *   <code>orderby</code> field was defined.
	 * @return array Returns the sanitized OrderBy list.
	 * @deprecated Please use SqlBuilder::applyOrderByListFromSanitizer()
	 */
	public function sanitizeOrderByList($aScene, $aDefaultOrderByList=null)
	{
		$theOrderByList = $aDefaultOrderByList;
		if (!empty($aScene) && !empty($aScene->orderby))
		{
			//does the object passed in even define our validation method?
			$theHeaderLabel = (method_exists($aScene, 'isFieldSortable'))
					? $aScene->isFieldSortable($aScene->orderby)
					: null
					;
			//only valid columns we are able to sort on will define a header label
			if (!empty($theHeaderLabel))
			{
				$theSortDirection = null;
				if (isset($aScene->orderbyrvs))
				{
					$theSortDirection = ($aScene->orderbyrvs)
							? self::ORDER_BY_DESCENDING
							: self::ORDER_BY_ASCENDING
							;
				}
				$theOrderByList = array( $aScene->orderby => $theSortDirection );
			}
		}
		return $theOrderByList;
	}
	
	/**
	 * If we are not already in a transaction, start one.
	 * @return $this Returns $this for chaining.
	 */
	public function beginTransaction()
	{
		if ( $this->myTransactionFlag<1 ) {
			if ( !$this->myModel->db->inTransaction() ) {
				$this->myModel->db->beginTransaction();
				$this->myTransactionFlag += 1;
			}
		}
		else {
			$this->myTransactionFlag += 1;
		}
		return $this;
	}
	
	/**
	 * If we started a transaction earlier, commit it.
	 * @return $this Returns $this for chaining.
	 */
	public function commitTransaction()
	{
		if ( $this->myTransactionFlag>0 ) {
			if ( --$this->myTransactionFlag == 0 ) {
				$this->myModel->db->commit();
			}
		}
		return $this;
	}
	
	/**
	 * If we started a transaction earlier, roll it back.
	 * @return $this Returns $this for chaining.
	 */
	public function rollbackTransaction()
	{
		if ( $this->myTransactionFlag>0 ) {
			if ( --$this->myTransactionFlag == 0 ) {
				$this->myModel->db->rollBack();
			}
		}
		return $this;
	}
	
	/**
	 * If the Sanitizer is using a pager and limiting the query, try to
	 * retrieve the overall query total so we can display "page 1 of 20"
	 * or equivalent text/widget.<br>
	 * NOTE: this method must be called after the SELECT query is defined,
	 * but before the OrderBy/Sort and LIMIT clauses are applied to the SQL
	 * string.
	 * @return $this Returns $this for chaining.
	 */
	public function retrieveQueryTotalsForSanitizer()
	{
		if ( !empty($this->mySqlSanitizer) && $this->mySqlSanitizer->isTotalRowsDesired() )
		{
			$theCount = $this->getQueryTotals();
			if ( !empty($theCount) ) {
				$this->mySqlSanitizer->setPagerTotalRowCount(
						$theCount['total_rows']
				);
			}
		}
		return $this;
	}
	
	/**
	 * If we have a SqlSanitizer defined, retrieve the query limit information
	 * from it and add the SQL limit clause to our SQL string.
	 * @return $this Returns $this for chaining.
	 */
	public function applyQueryLimitFromSanitizer()
	{
		if ( !empty($this->mySqlSanitizer) )
			return $this->addQueryLimit(
					$this->mySqlSanitizer->getPagerPageSize(),
					$this->mySqlSanitizer->getPagerQueryOffset()
			) ;
		else
			return $this;
	}
	
	/**
	 * Return the SQL "LIMIT" expression for our model's database type.
	 * @param int $aLimit - the limit we are wishing to impose.
	 * @param int $aOffset - (optional) the offset with which to start our limit.
	 * @return $this Returns $this for chaining.
	 */
	public function addQueryLimit($aLimit, $aOffset=null)
	{
		if ( $aLimit+0 > 0 ) {
			$theModel = $this->myModel;
			switch ( $theModel->dbType() ) {
				case $theModel::DB_TYPE_MYSQL:
				case $theModel::DB_TYPE_PGSQL:
				default:
					$this->add('LIMIT')->add($aLimit);
					if ( $aOffset+0 > 0 )
					{ $this->add('OFFSET')->add($aOffset); }
			}//switch
		}
		return $this;
	}
	
}//end class
	
}//end namespace
