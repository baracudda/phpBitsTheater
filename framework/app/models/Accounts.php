<?php

namespace BitsTheater\models;
use BitsTheater\models\PropCloset\AuthOrgs as BaseModel;
{//begin namespace

class Accounts extends BaseModel
{
	/**
	 * The name of the model which can be used in IDirected::getProp().
	 * @var string
	 * @since BitsTheater 3.6.1
	 */
	const MODEL_NAME = __CLASS__ ;
	
	protected function exists($aTableName=null) {
		return parent::exists( empty($aTableName) ? $this->tnAccounts : $aTableName );
	}
	
	public function isEmpty($aTableName=null) {
		return parent::isEmpty( empty($aTableName) ? $this->tnAccounts : $aTableName );
	}
	
}//end class

}//end namespace
