<?php
namespace com\blackmoonit\bits_theater\app\model;
use com\blackmoonit\bits_theater\app\Model;
{//namespace begin

class SetupDb extends Model {

	protected function callModelMethod($aModelList, $aMethodName, $args) {
		if (!is_array($args))
			$args = array($args);
		foreach ($aModelList as $modelInfo) {
			if ($modelInfo->hasMethod($aMethodName)) {
				$theModel = $this->director->getProp($modelInfo); //let director clean up our open models after all done
				call_user_func_array(array($theModel,$aMethodName),$args);
				//$theModel->$aMethodName($aScene);
			}
		}
	}

	public function setupModels($aScene) {
		$models = self::getAllModelClassInfo();

		$this->callModelMethod($models,'setupModel',$aScene);
		$this->callModelMethod($models,'setupDefaultData',$aScene);

		array_walk($models, function(&$n) { unset($n); } );
		unset($models);
	}
	

}//end class

}//end namespace
