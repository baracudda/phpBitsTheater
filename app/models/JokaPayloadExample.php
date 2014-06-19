<?php

namespace BitsTheater\models;
use com\blackmoonit\Strings;
{//begin namespace

/**
 * Example (or ancestor) class used in JokaPackage->payload.
 */
class JokaPayloadExample {
	public $action;
	
	static public function fromArray($anArray) {
		$theClassName = get_called_class();
		$theJokaPayload = new $theClassName();
		return $theJokaPayload->setDataFromArray($anArray);
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
	
	protected function copyFromArray(&$anArray) {
		foreach ($anArray as $theName => $theValue) {
			if (property_exists($this, $theName)) {
				$this->{$theName} = $theValue;
			}
		}
	}
	
	/**
	 * Given an array, set the data members to its contents.
	 * @param array $anArray - array of data
	 * @return Returns $this for chaining purposes.
	 */
	public function setDataFromArray($anArray) {
		if (!empty($anArray))
			$this->copyFromArray($anArray);
		return $this;
	}
	
}//end class

}//end namespace
