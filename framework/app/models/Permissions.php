<?php

namespace BitsTheater\models;
use BitsTheater\models\AuthGroups as BaseModel;
{//namespace begin

/**
 * Subsumed by AuthGroups model, exists now for backward compatibility.
 * @deprecated New code should use the AuthGroups model only.
 */
class Permissions extends BaseModel
{

	/**
	 * The name of the model which can be used in IDirected::getProp().
	 * @var string
	 * @since BitsTheater 3.6.1
	 */
	const MODEL_NAME = __CLASS__ ;
	
	protected function exists($aTableName=null) {
		return parent::exists( empty($aTableName) ? $this->tnPermissions : $aTableName );
	}

	public function isEmpty($aTableName=null) {
		return parent::isEmpty( empty($aTableName) ? $this->tnPermissions : $aTableName );
	}

}//end class

}//end namespace
