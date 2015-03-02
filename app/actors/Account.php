<?php
namespace BitsTheater\actors;
use BitsTheater\actors\Understudy\AuthBasicAccount as BaseActor;
use BitsTheater\scenes\Account as MyScene;
	/* @var $v MyScene */
use com\blackmoonit\Strings;
{//namespace begin

class Account extends BaseActor {
	
	public function registerViaMobile() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		
		if (!empty($v->fingerprints)) {
			$theNewPrints = array(
					'device_id' => $v->fingerprints[0],
					'device_name' => $v->fingerprints[1],
					'app_version_name' => $v->fingerprints[2],
					'latitude' => (is_numeric($v->fingerprints[3]) ? $v->fingerprints[3] : null),
					'longitude' => (is_numeric($v->fingerprints[4]) ? $v->fingerprints[4] : null),
					'device_memory' => (is_numeric($v->fingerprints[5]) ? $v->fingerprints[5] : null),
					'locale' => $v->fingerprints[6],
					'app_fingerprint' => $v->fingerprints[7],
			);
			$v->fingerprints = $theNewPrints;
		}
		
		return parent::registerViaMobile();
	}
	
	public function requestMobileAuth($aPing=null) {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		parent::requestMobileAuth($aPing);
		if (empty($v->results) && $aPing==='fresnel') {
			$v->results = array(
					'user_token' => 'ping',
					'auth_token' => 'pong',
					'api_version_seq' => $v->getRes('website/api_version_seq'),
			);
		}
	}
	
}//end class

}//end namespace

