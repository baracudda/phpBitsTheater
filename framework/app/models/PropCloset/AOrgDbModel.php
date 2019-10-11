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

namespace BitsTheater\models\PropCloset;
use BitsTheater\Model as BaseModel;
use BitsTheater\costumes\IDirected;
use BitsTheater\models\Auth as AuthModel;
{//begin namespace

class AOrgDbModel extends BaseModel
{
	/**
	 * The database connection name this model uses.
	 * @var string
	 */
	const DB_CONN_NAME = APP_DB_CONN_NAME;
	
	/**
	 * Add our database name before the defined table prefix so we can work
	 * with multiple databases at once.
	 * @var boolean This value is TRUE as the intention here is to work with multiple dbs.
	 */
	const TABLE_PREFIX_INCLUDES_DB_NAME = true;
	
	/** @return AuthModel */
	protected function getAuthModel() { return $this->getProp(AuthModel::MODEL_NAME); }
	
	/**
	 * Connect to a different org, null org data will connect to Root org.
	 * @param string|array|object $aOrg - org ID (string), or org row data (array)
	 *   or org object data with the org_id info available.
	 * @return $this Returns $this for chaining.
	 */
	public function connectToOrg( $aOrg )
	{
		$theOrgID = null;
		if ( !empty($aOrg) ) {
			if ( is_string($aOrg) ) {
				$theOrgID = $aOrg;
			}
			else if ( is_array($aOrg) ) {
				$theOrgID = ( !empty($aOrg['org_id']) ) ? $aOrg['org_id'] : null;
			}
			else if ( is_object($aOrg) ) {
				$theOrgID = ( !empty($aOrg->org_id) ) ? $aOrg->org_id : null;
			}
		}
		$theNewDbConnInfo = $this->getDirector()->getPropsMaster()->getDbConnInfoForOrgData($theOrgID);
		return $this->connectTo($theNewDbConnInfo);
	}
	
	/**
	 * Connect to a different org by its ID. NULL=AuthModel::ORG_ID_4_ROOT.
	 * @param string $aOrgID - org_id with the dbconn info to connect to.
	 * @return string[] Returns the org data loaded, if any.
	 */
	public function connectToOrgID( $aOrgID )
	{
		$theOrg = null;
		if ( !empty($aOrgID) && $aOrgID != AuthModel::ORG_ID_4_ROOT ) {
			$dbAuth = $this->getAuthModel();
			$theOrg = $dbAuth->getOrganization($aOrgID);
			$this->returnProp($dbAuth);
		}
		$this->connectToOrg($theOrg);
		return $theOrg;
	}
	
	/**
	 * Factory method used to create, connect and call setup().
	 * Useful in certain situations where we need two of the same model
	 * pointing to different org connections at the same time.
	 * @param IDirected $aContext - the context to use.
	 * @param string|array|object $aOrg - org ID (string), or org row data (array)
	 *   or org object data with the org_id info available.
	 * @return AOrgDbModel Returns the model descentant.
	 */
	static public function newInstanceToOrg( IDirected $aContext, $aOrg )
	{
		$theClass = get_called_class();
		$theModel = new $theClass();
		$theModel->setDirector($aContext)->connectToOrg($aOrg)->setup($aContext);
		return $theModel;
	}
	
}//end class

}//end namespace
