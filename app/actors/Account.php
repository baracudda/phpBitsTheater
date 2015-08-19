<?php
namespace BitsTheater\actors;
use BitsTheater\actors\Understudy\AuthBasicAccount as BaseActor;
use BitsTheater\scenes\Account as MyScene;
	/* @var $v MyScene */
use com\blackmoonit\Strings;
{//namespace begin

class Account extends BaseActor {
	
	/**
	 * Convert the fingerprint and circumstance string arrays into something
	 * meaningful for the Account actor parent.
	 * @see \BitsTheater\actors\Understudy\AuthBasicAccount::registerViaMobile()
	 */
	public function registerViaMobile() {
		//shortcut variable $v also in scope in our view php file.
		$v =& $this->scene;
		$v->fingerprints = $v->cnvFingerprints2KeyedArray($v->fingerprints);
		$v->circumstances = $v->cnvCircumstances2KeyedArray($v->circumstances);
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
		$v->fingerprints = $v->cnvFingerprints2KeyedArray($v->fingerprints);
		$v->circumstances = $v->cnvCircumstances2KeyedArray($v->circumstances);
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
