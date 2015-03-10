<?php
namespace BitsTheater\actors;
use BitsTheater\actors\Understudy\AuthBasicAccount as BaseActor;
use BitsTheater\scenes\Account as MyScene;
	/* @var $v MyScene */
use com\blackmoonit\Strings;
{//namespace begin

class Account extends BaseActor {
	
	protected function cnvFingerprints2KeyedArray($aFingerprints) {
		if (!empty($aFingerprints)) {
			return array(
					'device_id' => $aFingerprints[0],
					'app_version' => $aFingerprints[1],
					'device_memory' => (is_numeric($aFingerprints[2]) ? $aFingerprints[2] : null),
					'locale' => $aFingerprints[3],
					'app_signature' => $aFingerprints[4],
			);
		} else return array();
	}

	protected function cnvCircumstances2KeyedArray($aCircumstances) {
		if (!empty($aCircumstances)) {
			return array(
					'now_ts' => $aCircumstances[0],
					'latitude' => (is_numeric($aCircumstances[1]) ? $aCircumstances[1] : null),
					'longitude' => (is_numeric($aCircumstances[2]) ? $aCircumstances[2] : null),
					'device_name' => $aCircumstances[3],
			);
		} else return array();
	}
	
	public function registerViaMobile() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		$v->fingerprints = $this->cnvFingerprints2KeyedArray($v->fingerprints);
		$v->circumstances = $this->cnvCircumstances2KeyedArray($v->circumstances);
		return parent::registerViaMobile();
	}
	
	public function requestMobileAuth($aPing=null) {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		$v->fingerprints = $this->cnvFingerprints2KeyedArray($v->fingerprints);
		$v->circumstances = $this->cnvCircumstances2KeyedArray($v->circumstances);
		//$this->debugLog(__METHOD__.' v='.$this->debugStr($v));
		
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

