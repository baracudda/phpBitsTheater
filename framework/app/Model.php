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

namespace BitsTheater;
use com\blackmoonit\database\GenericDb as BaseModel;
use BitsTheater\costumes\DbConnInfo;
use BitsTheater\costumes\IDirected;
use BitsTheater\costumes\PropsMaster;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\WornForCLI;
use com\blackmoonit\database\DbUtils;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\OutputToCSV;
use com\blackmoonit\Strings;
use ReflectionClass;
use PDOException;
use PDOStatement;
{//begin namespace

/**
 * Base class for Models.
 */
class Model extends BaseModel
implements IDirected
{
	use WornForCLI;
	
	/**
	 * The number of args required to call the setup() method.
	 * @var number
	 */
	const _SetupArgCount = 1;
	
	/** @var string The db connection name we should be using. */
	const DB_CONN_NAME = PropsMaster::DB_CONN_NAME_FOR_AUTH;
	/**
	 * Add our database name before the defined table prefix so we can work
	 * with multiple databases at once.
	 * @var boolean The default ancestor values is FALSE.
	 */
	const TABLE_PREFIX_INCLUDES_DB_NAME = false;
	/**
	 * The prefix all database tables will use. Defined in DbConnInfo.
	 * Usually descendents will also prefix the database name as well.
	 * @var string
	 */
	public $tbl_ = '';
	/**
	 * @var Director
	 */
	public $director = null;
	/**
	 * @var DbConnInfo
	 */
	public $myDbConnInfo = null;
	
	/**
	 * Magic PHP method to limit what var_dump() shows.
	 */
	public function __debugInfo() {
		$vars = parent::__debugInfo();
		unset($vars['director']);
		return $vars;
	}
	
	/**
	 * Set our director instance given a Context to retrieve it.
	 * @param IDirected $aContext - the Context object.
	 * @return $this Returns $this for chaining.
	 */
	public function setDirector( IDirected $aContext )
	{ $this->director = $aContext->getDirector(); return $this; }
	
	/**
	 * Setup Model for use; connect to db if not done yet.
	 * @param IDirected $aContext - the Context object.
	 */
	public function setup( IDirected $aContext )
	{
		$this->setDirector($aContext);
		if ( !$this->isConnected() ) {
			$this->connect();
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
	
	/**
	 * Ancient factory method kept around for backwards compatibility.
	 * @param string $aModelClassName - the descendant class to create.
	 * @param IDirected $aContext - the context to use.
	 * @return Model Returns a model descendant.
	 * @deprecated
	 */
	static public function newModel( $aModelClassName, IDirected $aContext )
	{ return new $aModelClassName($aContext); }
	
	/**
	 * Factory method used to create, connect and call setup().
	 * Useful in certain situations where we need two of the same model
	 * pointing to different db connections at the same time.
	 * @param IDirected $aContext - the context to use.
	 * @param DbConnInfo $aDbConnInfo - (optional) the db to connect to.
	 * @return Model Returns the model descentant.
	 */
	static public function newInstance( IDirected $aContext, DbConnInfo $aDbConnInfo=null )
	{
		$theClass = get_called_class();
		$theModel = new $theClass();
		$theModel->setDirector($aContext);
		if ( !empty($aDbConnInfo) ) {
			$theModel->connectTo($aDbConnInfo);
		}
		$theModel->setup($aContext);
		return $theModel;
	}
	
	/**
	 * Used in base connect() implementation.
	 * @return DbConnInfo the database connection info.
	 */
	public function getDbConnInfo() {
		return $this->myDbConnInfo;
	}
	
	/**
	 * Connect to the database. May also be called to reconnect after timeout.
	 * @param string $aDbConnName - connection info name to load
	 * @throws DbException - if failed to connect, this exception is thrown.
	 * @deprecated connectTo() is the preferred method now.
	 */
	public function connect($aDbConnName=null) {
		if (empty($aDbConnName)) {
			$aDbConnName = static::DB_CONN_NAME;
		}
		$this->connectTo($this->director->getDbConnInfo($aDbConnName));
	}
	
	/**
	 * Connect to the database. May also be called to reconnect after timeout.
	 * @param DbConnInfo $aDbConnInfo - the connection information.
	 * @throws DbException - if failed to connect, this exception is thrown.
	 * @return $this Returns $this for chaining.
	 */
	public function connectTo( DbConnInfo $aDbConnInfo )
	{
		$this->myDbConnInfo = $aDbConnInfo;
		try {
			if ( !empty($this->db) )
			{ unset($this->db); }
			$this->db = $this->myDbConnInfo->connect();
		} catch (PDOException $pdox) {
			throw BrokenLeg::tossException($this, $pdox)
				->putExtra('dbconn', $this->myDbConnInfo->dbConnName)
				;
		}
		if ( !empty($this->db) ) {
			$this->setupAfterDbConnected();
		}
		return $this;
	}
	
	/**
	 * Descendants may wish to override this method to handle more stuff after
	 * a successful db connection is made. Should probably include on first line:
	 * parent::setupAfterDbConnected().
	 */
	protected function setupAfterDbConnected()
	{
		$this->tbl_ = $this->getDefinedTablePrefix();
	}
	
	/**
	 * Ensure our table prefix matches the connection information.
	 * @return string Returns the defined table prefix.
	 * @see Model::TABLE_PREFIX_INCLUDES_DB_NAME
	 */
	protected function getDefinedTablePrefix()
	{
		$theResult = '';
		if ( static::TABLE_PREFIX_INCLUDES_DB_NAME )
		{
			$theResult = SqlBuilder::withModel($this)->getQuoted(
					$this->myDbConnInfo->dbName
			) . '.';
		}
		$theResult .= $this->myDbConnInfo->table_prefix;
		return $theResult;
	}
	
	/**
	 * Detect if we have a connection or not.
	 * @return boolean Returns TRUE if a database connection exists.
	 */
	public function isConnected() {
		return (isset($this->db));
	}
	
	public function prepareSQL($aParamSql) {
		try {
			return parent::prepareSQL($aParamSql);
		} catch (PDOException $pdoe) {
			throw new DbException($pdoe);
		}
	}

	/**
	 * Binds
	 * @param string/PDOStatement $aSql - SQL statement (may be parameterized).
	 * @param array $aParamValues - if the SQL statement is parameterized, pass in the values for them, too.
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @throws PDOException if there is an error.
	 */
	public function bindParamValues($aSql, $aParamValues, $aParamTypes=null) {
		if (is_array($aParamValues))
			return $this->bindValues($aSql,$aParamValues,$aParamTypes);
		elseif (is_string($aParamTypes))
			return $this->bindValues($aSql,array(1=>$aParamValues),array(1=>$aParamTypes));
		else
			return $this->bindValues($aSql,array(1=>$aParamValues));
	}
	
	/**
	 * Execute DML (data manipulation language - INSERT, UPDATE, DELETE) statements
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string/PDOStatement $aSql - SQL statement (may be parameterized).
	 * @param array $aParamValues - if the SQL statement is parameterized, pass in the values for them, too.
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @throws DbException if there is an error.
	 * @return number|PDOStatement Returns the number of rows affected OR if using params, the PDOStatement.
	 */
	public function execDML($aSql, $aParamValues=null, $aParamTypes=null) {
		$theRetries = 0;
		do {
			try {
				if (is_null($aParamValues)) {
					return $this->db->exec($aSql);
				} else {
					$thePdoStatement = $this->bindParamValues($aSql,$aParamValues,$aParamTypes);
					$thePdoStatement->execute();
					return $thePdoStatement;
				}
			} catch (PDOException $pdoe) {
				if ($theRetries<static::MAX_RETRY_COUNT && DbUtils::isDbConnTimeout($this->db, $pdoe)) {
					//connection timed out, reconnect and try again
					$this->connect();
				} else {
					throw new DbException($pdoe);
				}
			}
		} while ($theRetries++ < static::MAX_RETRY_COUNT);
	}

	/**
	 * Execute Select query, returns PDOStatement.
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aSql - SQL statement (may be parameterized).
	 * @param array $aParamValues - (optional) if the SQL statement is parameterized, pass in the values for them, too.
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @throws DbException if there is an error.
	 * @return number|PDOStatement Returns the number of rows affected OR if using params, the PDOStatement.
	 */
	public function query($aSql, $aParamValues=null, $aParamTypes=null) {
		$theRetries = 0;
		do {
			try {
				if (is_null($aParamValues)) {
					return $this->db->query($aSql);
				} else {
					$thePdoStatement = $this->bindParamValues($aSql,$aParamValues,$aParamTypes);
					if ( ! $thePdoStatement instanceof PDOStatement ) {
						$this->errorLog(__METHOD__ . ' Bad query: SQL=' . $aSql
								. ' params=' . $this->debugStr($aParamValues)
								);
						$this->errorLog((new \Exception)->getTraceAsString());
					}
					$thePdoStatement->execute();
					return $thePdoStatement;
				}
			} catch (PDOException $pdoe) {
				if ($theRetries<static::MAX_RETRY_COUNT && DbUtils::isDbConnTimeout($this->db, $pdoe)) {
					//connection timed out, try to reconnect
					$this->connect();
				} else {
					throw new DbException($pdoe);
				}
			}
		} while ($theRetries++ < static::MAX_RETRY_COUNT);
	}
	
	/**
	 * A combination query & fetch a single row; returns null if error.
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aSql - SQL statement (may be parameterized).
	 * @param array $aParamValues - (optional) if the SQL statement is parameterized, pass in the values for them, too.
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @throws DbException if there is an error.
	 * @return mixed Returns the fetched data as PDOStatement is set to return it as.
	 * @see Model::query()
	 */
	public function getTheRow($aSql, $aParamValues=null, $aParamTypes=null) {
		$theResult = null;
		$r = $this->query($aSql,$aParamValues,$aParamTypes);
		if (!empty($r)) {
			$theResult = $r->fetch();
			//if nothing was found, "false" is returned, but we want to return "null"
			if ($theResult===false)
				$theResult = null;
			$r->closeCursor();
		}
		return $theResult;
	}
	
	/**
	 * Return TRUE if field exists in table.
	 * @param string $aFieldNames - the comma separated field names to check.
	 * @param string $aTableName - the table to check.
	 * @return boolean Return TRUE if the field exists in table.
	 */
	public function isFieldExists($aFieldNames, $aTableName) {
		try {
			$this->query("SELECT {$aFieldNames} FROM {$aTableName} LIMIT 1");
			return true;
		} catch (PDOException $e) {
			return false;
		}
	}
	
	/**
	 * Return TRUE if specified table exists.
	 * @param string $aTableName
	 */
	protected function exists($aTableName) {
		try {
			$this->query("SELECT 1 FROM {$aTableName} WHERE 1=0");
			return true;
		} catch (PDOException $e) {
			return false;
		}
	}
	
	/**
	 * return TRUE iff table exists and is empty.
	 */
	public function isEmpty($aTableName) {
		if ($this->exists($aTableName)) {
			$ps = $this->query("SELECT 1 FROM $aTableName WHERE EXISTS(SELECT * FROM $aTableName LIMIT 1)");
			$theResult = $ps->fetch();
			return (empty($theResult));
		} else {
			return false;
		}
	}

	/**
	 * return TRUE if table exists.
	 */
	public function isExists($aTableName) {
		return ($this->exists($aTableName));
	}
	
	/**
	 * Return TRUE if SQL statement returns NO results.
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aSql - SQL statement (may be parameterized).
	 * @param array $aParamValues - (optional) if the SQL statement is parameterized, pass in the values for them, too.
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @throws DbException if there is an error.
	 * @return boolean Returns TRUE if no data was returned.
	 */
	public function isNoResults($aSql, $aParamValues=null, $aParamTypes=null) {
		$theResult = true;
		$ps = $this->query($aSql,$aParamValues,$aParamTypes);
		if ($ps) {
			$theResult = ($ps->fetch()===false);
			$ps->closeCursor();
		}
		return $theResult;
	}
	
	//===== Parameterized queries =====

	/**
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aParamSql - the parameterized SQL string.
	 * @param array $aListOfParamValues - array with the array of values for the parameters in the SQL statement.
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @throws DbException if there is an error.
	 */
	public function execMultiDML($aParamSql, $aListOfParamValues, $aParamTypes=null) {
		$theRetries = 0;
		do {
			try {
				$theStatement = $this->prepareSQL($aParamSql);
				foreach ($aListOfParamValues as $theSqlParams) {
					$theStatement = $this->bindParamValues($theStatement,$theSqlParams,$aParamTypes);
					$theStatement->execute();
					$theStatement->closeCursor();
				}
				return; //break out of retry loop if all went well
			} catch (PDOException $pdoe) {
				if ($theRetries<static::MAX_RETRY_COUNT && DbUtils::isDbConnTimeout($this->db, $pdoe)) {
					//connection timed out, try to reconnect
					$this->connect();
				} else {
					throw new DbException($pdoe);
				}
			}
		} while ($theRetries++ < static::MAX_RETRY_COUNT);
	}
	
	/**
	 * Perform an INSERT query and return the new Auto-Inc ID field value for it.
	 * Params should be ordered array with ? params OR associative array with :label params.
	 * @param string $aSql - SQL statement (may be parameterized).
	 * @param array $aParamValues - if the SQL statement is parameterized, pass in the values for them, too.
	 * @param array $aParamTypes - (optional) the types of each param (PDO::PARAM_? constants).
	 * @throws DbException if there is an error.
	 * @return int Returns the lastInsertId().
	 */
	public function addAndGetId($aSql, $aParamValues=null, $aParamTypes=null) {
		$theResult = null;
		if (!empty($aSql)) {
			$theRetries = 0;
			do {
				try {
					$bWasInTrans = $this->db->inTransaction() ;
					if( ! $bWasInTrans )
					{$this->db->beginTransaction();}
					if ($this->execDML($aSql,$aParamValues,$aParamTypes)) {
						$theResult = $this->db->lastInsertId();
						if( ! $bWasInTrans )
							$this->db->commit();
					} else {
						if( ! $bWasInTrans )
							$this->db->rollBack();
					}
					return $theResult; //break out of retry loop if all went well
				} catch (PDOException $pdoe) {
					if ($theRetries<static::MAX_RETRY_COUNT && DbUtils::isDbConnTimeout($this->db, $pdoe)) {
						//connection timed out, try to reconnect
						$this->connect();
					} else {
						if ($this->db->inTransaction())
							$this->db->rollBack ();
						throw new DbException($pdoe);
					}
				}
			} while ($theRetries++ < static::MAX_RETRY_COUNT);
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
		return BITS_APP_PATH.'models'.¦.$aModelClassPattern.'.php';
	}

	/**
	 * Get list of all non-abstract model ReflectionClass objects.
	 * @return \ReflectionClass[] Returns list of all models as a ReflectionClass.
	 */
	static public function getAllModelClassInfo() {
		return self::getModelClassInfos();
	}
	
	/**
	 * During website development, some models may get orphaned. Prevent them
	 * from being used by overriding and disallowing the old model names.
	 * @param string $aModelName - the simple name of the model class.
	 * @return boolean Returns TRUE if the model is to be used.
	 */
	static protected function isModelClassAllowed($aModelName) {
		return true;
	}
	
	/**
	 * Get a list of models based on the parameter.
	 * @param string $aModelClassPattern - NULL for all non-abstract models,
	 *   else a result from getModelClassPattern.
	 * @param boolean $bIncludeAbstracts - restricts the list to only
	 *   instantiable classes if FALSE, default is FALSE.
	 * @return \ReflectionClass[] Returns list of all models that match the
	 *   pattern as a ReflectionClass.
	 * @see Model::getModelClassPattern()
	 */
	static public function getModelClassInfos($aModelClassPattern=null, $bIncludeAbstracts=false) {
		$theModelClassPattern = (empty($aModelClassPattern)) ? self::getModelClassPattern() : $aModelClassPattern;
		$theModels = array();
		foreach (glob($theModelClassPattern) as $theModelFile) {
			$theModelName = str_replace('.php','',basename($theModelFile));
			if (!static::isModelClassAllowed($theModelName))
				continue;
			//Strings::debugLog(__METHOD__.' name to reflect: '.$theModelName);
			$theModelClass = Director::getModelClass($theModelName);
			//Strings::debugLog(__METHOD__.' reflecting: '.$theModelClass);
			$classInfo = new ReflectionClass($theModelClass);
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
			//$aDirector->logStuff(__METHOD__, ' calling: ', $modelInfo->getShortName(), '->', $aMethodName);
			if ( $modelInfo->hasMethod($aMethodName) ) try {
				$theModel = $aDirector->getProp($modelInfo);
				$theResult[$modelInfo->getShortName()] = call_user_func_array(array($theModel,$aMethodName),$args);
			}
			catch ( \Exception $x ) {
				$aDirector->logErrors($modelInfo->getShortName(), '->', $aMethodName, ' exception: ', $x);
				throw $x;
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
	
	static public function cnvRowsToArray($aRowSet, $aFieldNameKey, $aFieldNameValue=null) {
		return DbUtils::cnvRowsToArray($aRowSet,$aFieldNameKey,$aFieldNameValue);
	}
	
	/**
	 * Given a query result set and a file path, generate the CSV file.
	 * @param PDOStatement $aQueryResults - the query result PDOStatement.
	 * @param string $aFilePath - filename to create, intermediate folders should already exist.
	 */
	public function queryResultsToCsvFile(PDOStatement $aQueryResults, $aFilePath) {
		$theCSV = OutputToCSV::newInstance()->useInputForHeaderRow();
		//TODO set all the various CSV options
		$theCSV->setInput($aQueryResults);
		return $theCSV->generateOutputToFile($aFilePath);
	}
	
	/**
	 * Return the director object.
	 * @return Director Returns the site director object.
	 */
	public function getDirector() {
		return $this->director;
	}
	
	/**
	 * {@inheritDoc}
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 * @see \BitsTheater\costumes\IDirected::isAllowed()
	 */
	public function isAllowed($aNamespace, $aPermission, $aAcctInfo=null) {
		return $this->getDirector()->isAllowed($aNamespace, $aPermission, $aAcctInfo);
	}
	
	/**
	 * {@inheritDoc}
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 * @see \BitsTheater\costumes\IDirected::isGuest()
	 */
	public function isGuest() {
		return $this->getDirector()->isGuest();
	}
	
	/**
	 * {@inheritDoc}
	 * @return boolean Returns TRUE if allowed, FALSE if not.
	 * @see \BitsTheater\costumes\IDirected::checkAllowed()
	 */
	public function checkAllowed($aNamespace, $aPermission, $aAcctInfo=null) {
		return $this->getDirector()->checkAllowed($aNamespace, $aPermission, $aAcctInfo);
	}
	
	/**
	 * {@inheritDoc}
	 * @return $this Returns $this for chaining.
	 * @see \BitsTheater\costumes\IDirected::checkPermission()
	 */
	public function checkPermission($aNamespace, $aPermission, $aAcctInfo=null)
	{
		$this->getDirector()->checkPermission($aNamespace, $aPermission, $aAcctInfo);
		return $this;
	}
	
	/**
	 * Return a Model object for a given org, creating it if necessary.
	 * @param string $aName - name of the model object.
	 * @param string $aOrgID - (optional) the org ID whose data we want.
	 * @return Model Returns the model object.
	 */
	public function getProp( $aName, $aOrgID=null )
	{ return $this->getDirector()->getProp($aName, $aOrgID); }
	
	/**
	 * Let the system know you do not need a Model anymore so it
	 * can close the database connection as soon as possible.
	 * @param Model $aProp - the Model object to be returned to the prop closet.
	 */
	public function returnProp($aProp) {
		$this->getDirector()->returnProp($aProp);
	}

	/**
	 * Get a resource based on its combined 'namespace/resource_name'.
	 * Alternatively, you can pass each segment in as its own parameter.
	 * @param string $aName - The 'namespace/resource[/extras]' name to retrieve.
	 */
	public function getRes($aName) {
		return call_user_func_array(array($this->getDirector(), 'getRes'), func_get_args());
	}
	
	/**
	 * Returns the URL for this site appended with relative path info.
	 * @param string[]|string $aRelativeURL - array of path segments
	 *   OR a bunch of string parameters equating to path segments.
	 * @return string Returns the site relative path URL.
	 * @see Director::getSiteUrl()
	 */
	public function getSiteUrl($aRelativeURL='') {
		return call_user_func_array(array($this->getDirector(), 'getSiteUrl'), func_get_args());
	}
	
	/**
	 * Get the setting from the configuration model.
	 * @param string $aSetting - setting in form of "namespace/setting"
	 * @param string $aOrgID - (optional) the org ID whose data we want.
	 * @throws \Exception
	 */
	public function getConfigSetting( $aSetting, $aOrgID=null )
	{
		//if the model is specific to a particular org, default our
		//  config query to using that org ID rather than the system
		//  default org.
		$theOrgID = ( !empty($aOrgID) ) ? $aOrgID : $this->myDbConnInfo->mOrgID;
		return $this->getDirector()->getConfigSetting($aSetting, $theOrgID);
	}
	
	/**
	 * (Override) Returns the SQL code to create a table.
	 * @param string $aTableConst the name of a table
	 * @param string $aTableNameOverride a custom name for the table
	 * @return string the SQL code to create the table, or null if no definition
	 *  exists for the specified name
	 */
	protected function getTableDefSql( $aTableConst, $aTableNameOverride=null )
	{
		//switch( $aTableConst )
		//{
		//    Descendants define what to do.
		//}
	}

	/**
	 * Sets up one table in the model.
	 * Consumed by setupModel().
	 * @param string $aTableConst the table name constant
	 * @param string $aTableName the table name value
	 */
	protected function setupTable( $aTableConst, $aTableName )
	{
		$theSql = '' ;
		try
		{
			$theSql = $this->getTableDefSql( $aTableConst ) ;
			$this->execDML( $theSql ) ;
			$this->debugLog( $this->getRes(
					'install/msg_create_table_x_success/' . $aTableName
			) ) ;
		}
		catch( PDOException $pdox )
		{
			$this->errorLog(__METHOD__.' failed: '.$pdox->getMessage().' sql='.$theSql);
			throw new DbException( $pdox, $theSql ) ;
		}
	}

	/**
	 * Send string out to the debug log (or std log as [dbg]).
	 * @param $s - parameter to print out (non-strings converted with debugStr()).
	 * @see Strings::debugLog()
	 * @see \com\blackmoonit\AdamEve::debugStr()
	 */
	public function debugLog($s) {
		parent::debugLog($s);
		if ($this->isRunningUnderCLI()) {
			print( (is_string($s)) ? $s : $this->debugStr($s) );
			print(PHP_EOL);
		}
	}
	
	/**
	 * Send string out to the error log prepended with the defined error prefix.
	 * @param $s - parameter to print out (non-strings converted with debugStr()).
	 * @see Strings::errorLog($s)
	 * @see \com\blackmoonit\AdamEve::debugStr()
	 */
	public function errorLog($s) {
		parent::errorLog($s);
		if ($this->isRunningUnderCLI()) {
			print( (is_string($s)) ? $s : $this->debugStr($s) );
			print(PHP_EOL);
		}
	}
	
	/**
	 * Certain fields need to store JSON-serialized data in the database. These
	 * can sometimes be inadvertently processed as objects when they arrive in
	 * one of the costumes. This method can be used to ensure that the value
	 * stored in the database is, indeed, a string, rather than an array or
	 * object prematurely decoded from JSON inputs.
	 * @param mixed $aValue any value
	 * @return string If an object/array is passed in, this will return the JSON
	 *   serialization of that entity; otherwise, it is returned as-is.
	 * @since BitsTheater 4.3.1
	 */
	public function stringify( $aValue )
	{
		if ( is_array($aValue) || is_object($aValue) )
			return json_encode($aValue);
		else
			return $aValue;
	}

}//end class

}//end namespace
