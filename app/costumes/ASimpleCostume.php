<?php
/*
 * Copyright (C) 2015 Blackmoon Info Tech Services
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace BitsTheater\costumes;
use \stdClass as BaseCostume;
use \stdClass as StandardClass;
use com\blackmoonit\exceptions\IllegalArgumentException;
{//namespace begin

abstract class ASimpleCostume extends BaseCostume {
	
	/**
	 * Copies values into matching property names
	 * based on the array keys or object property names.
	 * @param array|object $aThing - array or object to copy from.
	 */
	protected function copyFrom(&$aThing) {
		foreach ($aThing as $theName => $theValue) {
			if (property_exists($this, $theName)) {
				$this->{$theName} = $theValue;
			}
		}
	}
	
	/**
	 * Given an array or object, set the data members to its contents.
	 * @param array|object $aThing - associative array or object
	 * @return Returns $this for chaining purposes.
	 */
	public function setDataFrom($aThing) {
		if (!empty($aThing))
			$this->copyFrom($aThing);
		return $this;
	}
	
	/**
	 * Create a new instance of whatever class this method
	 * is called from (MyClass::fromArray() makes a MyClass
	 * instance) and sets its properties to the values of the
	 * array param.
	 * @param array $anArray - associative array of data
	 * @return ASimpleCostume Returns the new instance.
	 */
	static public function fromArray($anArray) {
		$theClassName = get_called_class();
		$o = new $theClassName();
		return $o->setDataFrom($anArray);
	}
	
	/**
	 * Create a new instance of whatever class this method
	 * is called from (MyClass::fromObj() makes a MyClass
	 * instance) and sets its properties to the values of the
	 * object param.
	 * @param object $anObj - object to copy data from.
	 * @return ASimpleCostume Returns the new instance.
	 */
	static public function fromObj($anObj) {
		$theClassName = get_called_class();
		$o = new $theClassName();
		return $o->setDataFrom($anObj);
	}
	
	/**
	 * Create a new instance of whatever class this method
	 * is called from (MyClass::fromJson() makes a MyClass
	 * instance) and sets its properties to the values of the
	 * JSON param.
	 * @param string $asJson - JSON encoded string
	 * @return ASimpleCostume Returns the new instance.
	 */
	static public function fromJson($asJson) {
		return static::fromArray(json_decode($asJson,true));
	}
	
	/**
	 * Create a new instance of whatever class this method
	 * is called from (MyClass::fromThing() makes a MyClass
	 * instance) and sets its properties to the values of the
	 * object or array param.
	 * @param array|object $aThing - thing to copy data from.
	 * @return ASimpleCostume Returns the new instance.
	 */
	static public function fromThing($aThing) {
		$theClassName = get_called_class();
		$o = new $theClassName();
		return $o->setDataFrom($aThing);
	}
	
	/**
	 * Return this payload data as a simple class, minus any metadata this class might have.
	 * @return string Return self encoded as a standard class.
	 */
	public function exportData() {
		return $this;
	}
	
	/**
	 * JSON string for this payload data, minus any metadata the class might have.
	 * @return string Return self encoded to JSON.
	 */
	public function toJson($aJsonEncodeOptions=null) {
		return json_encode($this->exportData(), $aJsonEncodeOptions);
	}
	
	/**
	 * Utility function to convert a standard class to a specified class.
	 * @param StandardClass $aStdClass - the standard class to convert from.
	 * @param string $aClassName - the class to convert to.
	 * @return mixed Returns the converted class.
	 * @throws IllegalArgumentException
	 */
	static public function cnvStdClassToXClass(StandardClass $aStdClass, $aClassName) {
		$x = serialize($aStdClass);
		//reach in and change the class of the serialized object
		$y = preg_replace('@^O:8:"stdClass":@','O:'.strlen($aClassName).':"'.$aClassName.'":',$x);
		//unserialize into new class
		$o = unserialize($y);
		if ($o!==false)
			return $o;
		else
			throw new IllegalArgumentException('Failed to convert from stdClass to '.$aClassName.'.');
	}
	
	/**
	 * Convert an instance of StdClass to this class.
	 * @param StandardClass $aStdClass - the StdClass instance to convert from.
	 * @return mixed Returns the converted class.
	 */
	static public function fromStdClass(StandardClass $aStdClass) {
		//Strings::debugLog('costume stdcls: '.Strings::debugStr($aStdClass));
		$o = self::cnvStdClassToXClass($aStdClass, get_called_class());
		//Strings::debugLog('costume cls: '.Strings::debugStr($o));
		return $o;
	}
	
}//end class
	
}//end namespace
