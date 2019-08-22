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
use BitsTheater\costumes\DbConnInfo;
use BitsTheater\models\Auth as AuthModel;
{//begin namespace

class AOrgDbModel extends BaseModel
{

	public $dbConnName = APP_DB_CONN_NAME;

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
	 * @param array $aOrgRow - org data with the dbconn info to connect to.
	 * @return $this Returns $this for chaining.
	 */
	public function connectToOrg( $aOrgRow )
	{
		$theNewDbConnInfo = new DbConnInfo(APP_DB_CONN_NAME);
		if ( !empty($aOrgRow) && !empty($aOrgRow['dbconn']) && $aOrgRow['org_id'] != AuthModel::ORG_ID_4_ROOT ) {
			$theNewDbConnInfo->loadDbConnInfoFromString($aOrgRow['dbconn']);
		}
		$this->connectTo($theNewDbConnInfo);
		return $this;
	}
	
	/**
	 * Connect to a different org by its ID. NULL=AuthModel::ORG_ID_4_ROOT.
	 * @param string $aOrgID - org_id with the dbconn info to connect to.
	 * @return string[] Returns the org data loaded, if any.
	 */
	public function connectToOrgID( $aOrgID )
	{
		$theOrg = null;
		$theNewDbConnInfo = new DbConnInfo(APP_DB_CONN_NAME);
		if ( !empty($aOrgID) && $aOrgID != AuthModel::ORG_ID_4_ROOT ) {
			$dbAuth = $this->getAuthModel();
			$theOrg = $dbAuth->getOrganization($aOrgID);
			$this->returnProp($dbAuth);
			if ( empty($theOrg) || empty($theOrg['dbconn']) ) return null; //trivial
			$theNewDbConnInfo->loadDbConnInfoFromString($theOrg['dbconn']);
		}
		$this->connectTo($theNewDbConnInfo);
		return $theOrg;
	}
	
}//end class

}//end namespace
