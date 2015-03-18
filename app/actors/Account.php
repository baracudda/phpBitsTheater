<?php
namespace BitsTheater\actors;
use BitsTheater\actors\Understudy\AuthBasicAccount as BaseActor;
use BitsTheater\scenes\Account as MyScene;
	/* @var $v MyScene */
use com\blackmoonit\Strings;
{//namespace begin

class Account extends BaseActor {
	
	/**
	 * Example API fingerprints from mobile device. Recomended that
	 * your website mixes their order up, at the very least.
	 * @param string[] $aFingerprints - string array of device info.
	 * @return string[] Return a keyed array of device info.
	 */
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

	/**
	 * Example API circumstances from mobile device. Recommended that
	 * your website mixes their order up, at the very least.
	 * @param string[] $aCircumstances - string array of device meta,
	 * such as current GPS, user device name setting, current timestamp, etc.
	 * @return string[] Return a keyed array of device meta.
	 */
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
	
	/**
	 * Convert the fingerprint and circumstance string arrays into something
	 * meaningful for the Account actor parent.
	 * @see \BitsTheater\actors\Understudy\AuthBasicAccount::registerViaMobile()
	 */
	public function registerViaMobile() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		$v->fingerprints = $this->cnvFingerprints2KeyedArray($v->fingerprints);
		$v->circumstances = $this->cnvCircumstances2KeyedArray($v->circumstances);
		return parent::registerViaMobile();
	}
	
	/**
	 * Convert the fingerprint and circumstance string arrays into something
	 * meaningful for the Account actor parent. Also, react to the ping/pong
	 * request in our own fashion.
	 * @see \BitsTheater\actors\Understudy\AuthBasicAccount::requestMobileAuth()
	 */
	public function requestMobileAuth($aPing=null) {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		$v->fingerprints = $this->cnvFingerprints2KeyedArray($v->fingerprints);
		$v->circumstances = $this->cnvCircumstances2KeyedArray($v->circumstances);
		//$this->debugLog(__METHOD__.' v='.$this->debugStr($v));
		
		parent::requestMobileAuth($aPing);
		
		if (empty($v->results) && $aPing===VIRTUAL_HOST_NAME) {
			$v->results = array(
					'user_token' => 'ping',
					'auth_token' => 'pong',
					'api_version_seq' => $v->getRes('website/api_version_seq'),
			);
		}
	}
	
}//end class

}//end namespace

