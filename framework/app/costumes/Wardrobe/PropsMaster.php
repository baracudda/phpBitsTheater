<?php
/*
 * Copyright (C) 2019 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes\Wardrobe;
use BitsTheater\costumes\ABitsCostume as BaseCostume;
use BitsTheater\costumes\DbConnInfo as DbConnInfoInUse;
use BitsTheater\costumes\IDirected;
use BitsTheater\models\Auth as AuthModel;
use BitsTheater\Model;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * Model Manager class used to keep track of connections to various orgs.
 * @since BitsTheater 4.4.0
 */
class PropsMaster extends BaseCostume
{
	const DB_CONN_NAME_FOR_AUTH = 'webapp';
	const DB_CONN_NAME_FOR_DATA = APP_DB_CONN_NAME;
	
	/** @var DbConnInfoInUse The Auth data db connection info. */
	public $mDbConnInfoForAuth;

	/** @var DbConnInfoInUse The Org data db connection info. */
	public $mDbConnInfoForOrg = array(AuthModel::ORG_ID_4_ROOT => null);
	
	/** @var array $[orgID][$theModelClass]['model'|'ref_count']; model=DbConnInfoInUse */
	public $mPropRooms = [];
	
	/** @var string The default Org ID to use when not specified. */
	protected $mDefaultOrgID = AuthModel::ORG_ID_4_ROOT;

	/** @return AuthModel */
	protected function getAuthModel()
	{ return $this->getPropFromRoom(AuthModel::MODEL_NAME); }
	
	/**
	 * Construct and return our new object.
	 * @return $this Returns $this for chaining.
	 */
	static public function withContext( IDirected $aContext )
	{
		$theCalledClass = get_called_class();
		return new $theCalledClass($aContext);
	}
	
	/** @return string Returns the current default org ID. */
	public function getDefaultOrgID()
	{ return $this->mDefaultOrgID; }
	
	/**
	 * Set the default org ID, NULL means Root.
	 * @param string $aOrgID - the ID of the new default org.
	 * @return $this Returns $this for chaining.
	 */
	public function setDefaultOrgID( $aOrgID )
	{
		$this->mDefaultOrgID = ( !empty($aOrgID) ) ? $aOrgID : AuthModel::ORG_ID_4_ROOT;
		return $this;
	}
	
	/**
	 * Retrieve the connection information for auth data.
	 * @return DbConnInfoInUse
	 */
	public function getDbConnInfoForAuthData()
	{
		if ( empty($this->mDbConnInfoForAuth) ) {
			$this->mDbConnInfoForAuth = new DbConnInfoInUse(static::DB_CONN_NAME_FOR_AUTH);
		}
		return $this->mDbConnInfoForAuth;
	}
	
	/**
	 * Retrieve the connection information for a specific org connection.
	 * @param string $aOrgID - the org ID which will define what connection we use.
	 * @return DbConnInfoInUse Returns the connection to use.
	 * @throws DbException if db connection fails.
	 */
	public function getDbConnInfoForOrgData( $aOrgID )
	{
		$theOrgID = ( !empty($aOrgID) ) ? $aOrgID : $this->mDefaultOrgID;
		if ( empty($this->mDbConnInfoForOrg[$theOrgID]) ) {
			$theNewDbConnInfo = new DbConnInfoInUse(APP_DB_CONN_NAME);
			$theNewDbConnInfo->mOrgID = $theOrgID;
			if ( $theOrgID != AuthModel::ORG_ID_4_ROOT ) try {
				$dbAuth = $this->getAuthModel();
				$theOrg = $dbAuth->getOrganization($theOrgID);
				$this->returnProp($dbAuth);
				if ( !empty($theOrg) && !empty($theOrg['dbconn']) ) {
					$theNewDbConnInfo->loadDbConnInfoFromString($theOrg['dbconn']);
				}
				else {
					$this->logStuff('WARNING: org ID [', $theOrgID, '] does not define "dbconn".');
				}
			}
			catch ( \InvalidArgumentException $iax ) {
				$theOrgName = isset($theOrg['org_name']) ? $theOrg['org_name'] : 'Root';
				$theErrMsg = 'db connect failed. org_name [' . $theOrgName . ']';
				$this->logErrors(__METHOD__, ' ', $theErrMsg);
				throw new DbException($iax, $theErrMsg);
			}
			$this->mDbConnInfoForOrg[$theOrgID] = $theNewDbConnInfo;
		}
		if ( !empty($this->mDbConnInfoForOrg[$theOrgID]) ) {
			return $this->mDbConnInfoForOrg[$theOrgID];
		}
		else {
			return $this->mDbConnInfoForAuth;
		}
	}
	
	/**
	 * Returns the correct namespace associated with the model name/ReflectionClass.
	 * @param string|\ReflectionClass $aModelName - model name as string or the
	 *   ReflectionClass of model in question.
	 * @return string Returns the model class name with correct namespace.
	 */
	static public function getModelClass( $aModelName )
	{
		if ( is_string($aModelName) ) {
			$theModelSegPos = strpos($aModelName, 'models\\');
			if ( class_exists($aModelName) && !empty($theModelSegPos) ) {
				return $aModelName;
			}
			$theModelName = Strings::getClassName($aModelName);
			$theModelClass = BITS_NAMESPACE_MODELS . $theModelName;
			if ( !class_exists($theModelClass) ) {
				$theModelClass = WEBAPP_NAMESPACE . 'models\\' . $theModelName;
			}
		} else if ( $aModelName instanceof \ReflectionClass ) {
			$theModelClass = $aModelName->getName();
		}
		return $theModelClass;
	}
	
	static public function getModelDbConnName( $aModelClass )
	{
		$theDbConnName = ( !empty($aModelClass::DB_CONN_NAME) ) ? $aModelClass::DB_CONN_NAME : null;
		if ( empty($theDbConnName) && property_exists($aModelClass, 'dbConnName') ) {
			//check proir non-static property for dbConnName
			try {
				//do not pass in director so we just get bare minimum object with no db connection attempted
				$o = new $aModelClass();
				if ( !empty($o->dbConnName) ) {
					$theDbConnName = $o->dbConnName;
				}
			}
			catch( \Exception $x ) {
				//eat exception, if any, as we do not care if model fails to instantiate.
			}
		}
		return $theDbConnName;
	}
	
	/**
	 * Retrieve the singleton Model object for a given model class and org.
	 * @param string $aModelClass - the model class to retrieve.
	 * @param string $aOrgID - (optional) the org ID of the data desired.
	 * @throws \Exception when the model fails to connect or is not found.
	 * @return Model Returns the model class requested connected appropriately.
	 */
	public function getPropFromRoom( $aModelClass, $aOrgID=null )
	{
		$theModelClass = static::getModelClass($aModelClass);
		if ( !class_exists($theModelClass) ) {
			$theErrMsg = 'Model class: [' . $aModelClass . '] not found.';
			$this->logErrors(__METHOD__, ' ', $theErrMsg);
			throw new \Exception($theErrMsg);
		}
		$theDbConnName = static::getModelDbConnName($theModelClass);
		switch ( $theDbConnName ) {
			case static::DB_CONN_NAME_FOR_DATA: {
				$theOrgID = ( !empty($aOrgID) ) ? $aOrgID : $this->mDefaultOrgID;
				break;
			}
			default:
			case static::DB_CONN_NAME_FOR_AUTH: {
				$theOrgID = AuthModel::ORG_ID_4_ROOT;
				break;
			}
		}
		//do we have the org connection defined?
		if ( empty($this->mPropRooms[$theOrgID]) ) {
			$this->mPropRooms[$theOrgID] = array();
		}
		if ( !empty($this->mPropRooms[$theOrgID][$theModelClass]) ) {
			$this->mPropRooms[$theOrgID][$theModelClass]['ref_count'] += 1;
			$theResult = $this->mPropRooms[$theOrgID][$theModelClass]['model'];
			if ( !empty($theResult) ) { //witnessed empty(), but never duplicated
				return $theResult;
			}
		}
		//ensure we have a non-empty reference in case of a dbconn exception
		//  so that nested infinite loops can be avoided
		$this->mPropRooms[$theOrgID][$theModelClass] = array(
				'ref_count' => 0,
				'model' => null,
		);
		try {
			//create a new model instance
			/* @var $theNewModel Model */
			$theNewModel = new $theModelClass();
			$theNewModel->setDirector($this);
			//connect the new model instance if it has a dbConnName
			if ( !empty($theDbConnName) ) {
				//connect to the unique auth database for auth models
				if ( $theDbConnName == static::DB_CONN_NAME_FOR_AUTH ) {
					$theNewModel->connectTo($this->getDbConnInfoForAuthData());
				}
				//else connect to the appropriate org database
				else {
					$theNewModel->connectTo($this->getDbConnInfoForOrgData($theOrgID));
				}
			}
			//now run through the model setup routine
			$theNewModel->setup($this);
			//model is now ready for action, place it within our rooms for use.
			$this->mPropRooms[$theOrgID][$theModelClass]['ref_count'] += 1;
			$this->mPropRooms[$theOrgID][$theModelClass]['model'] = $theNewModel;
			return $theNewModel;
		}
		catch ( \Exception $x ) {
			$this->mPropRooms[$theOrgID][$theModelClass] = null;
			$this->logErrors(__METHOD__, ' ', $x->getMessage());
			throw $x;
		}
	}
	
	/**
	 * Given a model instance, decrement its usage counter, only freeing if 0.
	 * @param Model $aModel - a model instance.
	 */
	public function returnPropToRoom( Model $aModel=null )
	{
		if ( !empty($aModel) && !empty($aModel->myDbConnInfo) ) {
			$theModelClass = get_class($aModel);
			$theOrgID = $aModel->myDbConnInfo->mOrgID;
			if ( !empty($this->mPropRooms[$theOrgID][$theModelClass]) ) {
				//if this was the last model reference, destroy the model.
				$this->mPropRooms[$theOrgID][$theModelClass]['ref_count'] -= 1;
				if ( $this->mPropRooms[$theOrgID][$theModelClass]['ref_count']<1 ) {
					$this->mPropRooms[$theOrgID][$theModelClass]['model'] = null;
					unset($this->mPropRooms[$theOrgID][$theModelClass]);
				}
			}
			$aModel = null;
		}
	}
	
	/**
	 * Close all models associated with a specific org connection.
	 * @param string $aOrgID - the org ID of the connection to close.
	 * $return $this Returns $this for chaining.
	 */
	public function closeConnection( $aOrgID )
	{
		$theOrgID = ( !empty($aOrgID) ) ? $aOrgID : $this->mDefaultOrgID;
		if ( !empty($this->mDbConnInfoForOrg[$theOrgID]) ) {
			if ( !empty($this->mPropRooms[$theOrgID]) ) {
				array_walk($this->mPropRooms[$theOrgID], function(&$n) {
					if ( !empty($n) ) {
						$n = array();
					}
				});
				$this->mDbConnInfoForOrg[$theOrgID]->disconnect();
				unset($this->mDbConnInfoForOrg[$theOrgID]);
			}
		}
		
	}
	
	/**
	 * Close out all our cached models and connections.
	 * $return $this Returns $this for chaining.
	 */
	public function closeAllConnections()
	{
		foreach( $this->mDbConnInfoForOrg as $theOrgID => $theDbConnInfo) {
			if ( !empty($theDbConnInfo) ) {
				$this->closeConnection($theOrgID);
			}
		}
		if ( !empty($this->mDbConnInfoForAuth) ) {
			$this->mDbConnInfoForAuth->disconnect();
			$this->mDbConnInfoForAuth = null;
		}
	}
	
}//end class

}//end namespace
