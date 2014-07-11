<?php

namespace BitsTheater\costumes;
use BitsTheater\costumes\ABitsCostume as BaseCostume;
use com\blackmoonit\Strings;
use com\blackmoonit\exceptions\IllegalArgumentException;
use BitsTheater\configs\Settings;
{//begin namespace

/**
 * Joka/Wingu communication data package class
 */
class JokaPackage extends BaseCostume {
	public $payload_id;
	public $payload; //JSON encoded
	public $package_name;
	public $device_id;
	public $transmit_ts;
	public $received_ts;
	
	/**
	 * Given a JokaPackage, construct a new package that will route back to the sender.
	 * @param JokaPackage $aJokaPackage - package to reply to.
	 * @return JokaPackage Returns a new instance of JokaPackage ready to accept a new payload.
	 */
	static public function replyTo(JokaPackage $aJokaPackage) {
		$theClassName = get_called_class();
		if (empty($aJokaPackage))
			throw new IllegalArgumentException($theClassName.'::replyTo requires a valid JokaPackage paramater.', 404);
		$theJokaPackage = new $theClassName($aJokaPackage->getDirector());
		$theJokaPackage->package_name = $aJokaPackage->package_name;
		$theNamespace = array_shift(explode('\\', $theClassName,2));
		$theAppId = Settings::getAppId();
		$theJokaPackage->device_id = $theNamespace.'|'.$theAppId;
		return $theJokaPackage;
	}
	
	static public function getModelHandlerNameOfPackage($aPackageName) {
		//remove the domain (com.example.*) from package name to get Model classname.
		list($dotCom, $dotDomain, $theModelClassName) = explode('.',$aPackageName,3);
		if (empty($theModelClassName)) {
			$theModelClassName = (!empty($dotDomain)) ? $dotDomain : $aPackageName;
		}
		$theModelClassName = str_replace('.', '_', $theModelClassName);
		//convert "the_model_class_name" to "TheModelClassName"
		return Strings::getClassName($theModelClassName);
	}
	
	public function getModelHandlerName() {
		return self::getModelHandlerNameOfPackage($this->package_name);
	}
	
	public function getOrCreatePayloadId() {
		if (empty($this->payload_id)) {
			$this->payload_id = Strings::createUUID();
		}
		return $this->payload_id;
	}
	
}//end class

}//end namespace
