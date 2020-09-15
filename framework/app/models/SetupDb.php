<?php

namespace BitsTheater\models;
use BitsTheater\models\PropCloset\SetupDb as BaseModel;
use BitsTheater\costumes\DbConnInfo;
{//begin namespace

class SetupDb extends BaseModel
{
	/**
	 * The name of the model which can be used in IDirected::getProp().
	 * @var string
	 * @since BitsTheater 3.6.1
	 */
	const MODEL_NAME = __CLASS__ ;
	
	/**
	 * Connect to a different org by its ID.
	 * @param string $aOrgID - org_id with the dbconn info to connect to.
	 * @return string[] Returns the org data loaded, if any.
	 */
	public function connectToOrgID( $aOrgID )
	{
		$theOrg = null;
		$theNewDbConnInfo = new DbConnInfo(APP_DB_CONN_NAME);
		if ( !empty($aOrgID) && $aOrgID != AuthModel::ORG_ID_4_ROOT ) {
			$dbAuth = $this->getAuthProp();
			$theOrg = $dbAuth->getOrganization($aOrgID);
			$this->returnProp($dbAuth);
			if ( empty($theOrg) || empty($theOrg['dbconn']) ) return null; //trivial
			$theNewDbConnInfo->loadDbConnInfoFromString($theOrg['dbconn']);
		}
		$this->connectTo($theNewDbConnInfo);
		return $theOrg;
	}
	
	/**
	 * During website development, some models may get orphaned. Prevent them
	 * from being used by overriding and disallowing the old model names.
	 * @param string $aModelName - the simple name of the model class.
	 * @return boolean Returns TRUE if the model is to be used.
	 */
	static protected function isModelClassAllowed($aModelName) {
		$theOrphans = array(
		);
		return (array_search($aModelName, $theOrphans)===false);
	}
	
}//end class

}//end namespace
