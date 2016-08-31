<?php

namespace BitsTheater\models;
use BitsTheater\models\PropCloset\SetupDb as BaseModel;
use BitsTheater\costumes\SqlBuilder;
use com\blackmoonit\exceptions\DbException;
use PDOException;
{//begin namespace

class SetupDb extends BaseModel {

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
