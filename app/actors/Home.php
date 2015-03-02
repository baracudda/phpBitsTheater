<?php
namespace BitsTheater\actors;
use BitsTheater\actors\Understudy\Home as BaseActor;
use BitsTheater\Scene as MyScene;
	/* @var $v MyScene */
use com\blackmoonit\Strings;
{//namespace begin

class Home extends BaseActor {
	
	public function getapp($aStepNum=0) {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		
		$this->renderThisView = 'results_as_apk';
		
		$theStepNum = (isset($aStepNum)) ? $aStepNum+0 : 0;
		switch ($theStepNum) {
			case 0:
				return $this->getSiteURL();
			case 1:
				$v->results = BITS_RES_PATH.'files/'.'Fresnel.apk';
				break;
			default:
				die('No more steps, all done!');
		}//switch
	}
		
}//end class

}//end namespace

