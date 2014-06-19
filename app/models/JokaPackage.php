<?php

namespace BitsTheater\models;
use com\blackmoonit\Strings;
use com\blackmoonit\exceptions\IllegalArgumentException;
use BitsTheater\configs\Settings;
{//begin namespace

/**
 * Joka/Wingu communication data package class
 */
class JokaPackage {
	public $payload_id;
	public $payload; //JSON encoded
	public $package_name;
	public $device_id;
	public $transmit_ts;
	public $received_ts;
	
	public function __construct($anArray=null) {
		$this->setDataFromArray($anArray);
	}
	
	static public function fromArray(&$anArray) {
		$theClassName = get_called_class();
		$theJokaPackage = new $theClassName();
		$theJokaPackage->setDataFromArray($anArray);
		return $theJokaPackage;
	}
	
	static public function fromJson(&$asJson) {
		//Strings::debugLog('joka json: '.Strings::debugStr($asJson));
		$o = self::fromArray(json_decode($asJson,true));
		//Strings::debugLog('joka cls: '.Strings::debugStr($o));
		return $o;
	}
	
	static public function cnvStdClassToXClass($aStdClass, $aClassName) {
		$x = serialize($aStdClass);
		//reach in and change the class of the serialized object
		$y = preg_replace('@^O:8:"stdClass":@','O:'.strlen($aClassName).':"'.$aClassName.'":',$x);
		//unserialize into new class
		return unserialize($y);
	}
	
	static public function fromStdClass($aStdClass) {
		//Strings::debugLog('joka stdcls: '.Strings::debugStr($aStdClass));
		$o = self::cnvStdClassToXClass($aStdClass, get_called_class());
		//Strings::debugLog('joka cls: '.Strings::debugStr($o));
		return $o;
	}
	
	/**
	 * Given a JokaPackage, construct a new package that will route back to the sender.
	 * @param JokaPackage $aJokaPackage - package to reply to.
	 */
	static public function replyTo(JokaPackage $aJokaPackage) {
		$theClassName = get_called_class();
		if (empty($aJokaPackage))
			throw new IllegalArgumentException($theClassName.'::replyTo requires a valid JokaPackage paramater.', 404);
		$theJokaPackage = new $theClassName();
		$theJokaPackage->package_name = $aJokaPackage->package_name;
		$theNamespace = array_shift(explode('\\', $theClassName,2));
		$theAppId = Settings::getAppId();
		$theJokaPackage->device_id = $theNamespace.'|'.$theAppId;
		return $theJokaPackage;
	}
	
	public function decodePayload($aPayloadClassName=null) {
		//JSON to stdClass
		$theStdClass = is_string($this->payload) ? json_decode($this->payload) : $this->payload;
		if (!empty($aPayloadClassName)) {
			return self::cnvStdClassToXClass($theStdClass, $aPayloadClassName);
		}
		return $theStdClass;
	}
	
	public function encodePayload($aPayload) {
		$this->payload = json_encode($aPayload);
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
	
	public function setDataFromArray($anArray) {
		if (!empty($anArray)) {
			$this->payload_id = $anArray['payload_id'];
			$this->payload = $anArray['payload'];
			$this->package_name = $anArray['package_name'];
			$this->device_id = $anArray['device_id'];
			$this->transmit_ts = $anArray['transmit_ts'];
			$this->received_ts = $anArray['received_ts'];
		}
	}
	
	public function getOrCreatePayloadId() {
		if (empty($this->payload_id)) {
			$this->payload_id = Strings::createUUID();
		}
		return $this->payload_id;
	}
	
}//end class

}//end namespace
