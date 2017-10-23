<?php
/*
 * Copyright (C) 2014 Blackmoon Info Tech Services
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
use com\blackmoonit\AdamEve as BaseCostume;
use BitsTheater\costumes\IDirected;
use BitsTheater\Director;
use BitsTheater\Model;
use stdClass as StandardClass;
use com\blackmoonit\exceptions\IllegalArgumentException;
{//namespace begin

abstract class ABitsCostume extends BaseCostume
implements IDirected
{
	const _SetupArgCount = 1; //number of args required to call the setup() method.
	/**
	 * @var Director
	 */
	protected $_director = null;
	
	/**
	 * Magic PHP method to limit what var_dump() shows.
	 */
	public function __debugInfo() {
		$vars = parent::__debugInfo();
		unset($vars['_director']);
		return $vars;
	}
	
	/**
	 * Costume classes know about the Director.
	 * @param IDirected $aContext - context used to get the Director object
	 */
	public function setup(IDirected $aContext) {
		$this->setDirector($aContext->getDirector());
		$this->bHasBeenSetup = true;
	}
	
	public function cleanup() {
		unset($this->_director);
		parent::cleanup();
	}
	
	/**
	 * Set the director variable.
	 * @param Director $aDirector - site director object
	 */
	public function setDirector(Director $aDirector)
	{
		$this->_director = $aDirector ;
		return $this ;
	}
	
	/**
	 * Return the director object.
	 * @return Director Returns the site director object.
	 */
	public function getDirector() {
		return $this->_director;
	}

	/**
	 * {@inheritDoc}
	 * @see \BitsTheater\costumes\IDirected::isAllowed()
	 */
	public function isAllowed($aNamespace, $aPermission, $acctInfo=null) {
		return $this->getDirector()->isAllowed($aNamespace,$aPermission,$acctInfo);
	}

	/**
	 * {@inheritDoc}
	 * @see \BitsTheater\costumes\IDirected::isGuest()
	 */
	public function isGuest() {
		return $this->getDirector()->isGuest();
	}
	
	/**
	 * {@inheritDoc}
	 * @see \BitsTheater\costumes\IDirected::checkAllowed()
	 */
	public function checkAllowed($aNamespace, $aPermission, $aAcctInfo=null) {
		return $this->getDirector()->checkAllowed($aNamespace, $aPermission, $aAcctInfo);
	}
	
	/**
	 * {@inheritDoc}
	 * @return $this
	 * @see \BitsTheater\costumes\IDirected::checkPermission()
	 */
	public function checkPermission($aNamespace, $aPermission, $aAcctInfo=null)
	{
		$this->getDirector()->checkPermission($aNamespace, $aPermission, $aAcctInfo);
		return $this;
	}
	
	/**
	 * Return a Model object, creating it if necessary.
	 * @param string $aName - name of the model object.
	 * @return Model Returns the model object.
	 */
	public function getProp($aName) {
		return $this->getDirector()->getProp($aName);
	}
	
	/**
	 * Let the system know you do not need a Model anymore so it
	 * can close the database connection as soon as possible.
	 * @param Model $aProp - the Model object to be returned to the prop closet.
	 */
	public function returnProp($aProp) {
		$this->getDirector()->returnProp($aProp);
	}

	/**
	 * Get a resource based on its combined 'namespace/resource_name'.
	 * @param string $aName - The 'namespace/resource[/extras]' name to retrieve.
	 */
	public function getRes($aName) {
		return $this->getDirector()->getRes($aName);
	}
	
	/**
	 * Returns the URL for this site appended with relative path info.
	 * @param mixed $aRelativeUrl - array of path segments OR a bunch of string parameters
	 * equating to path segments.
	 * @return string - returns the site domain + relative path URL.
	 */
	public function getSiteUrl($aRelativeURL='', $_=null) {
		return call_user_func_array(array($this->getDirector(), 'getSiteUrl'), func_get_args());
	}
	
	/**
	 * Get the setting from the configuration model.
	 * @param string $aSetting - setting in form of "namespace/setting"
	 * @throws \Exception
	 */
	public function getConfigSetting($aSetting) {
		return $this->getDirector()->getConfigSetting($aSetting);
	}
	
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
	 * @param IDirected $aContext - used to get Director object.
	 * @param array $anArray - associative array of data
	 * @return ABitsCostume Returns the new instance.
	 */
	static public function fromArray(IDirected $aContext, $anArray) {
		$theClassName = get_called_class();
		$o = new $theClassName($aContext);
		return $o->setDataFrom($anArray);
	}
	
	/**
	 * Create a new instance of whatever class this method
	 * is called from (MyClass::fromObj() makes a MyClass
	 * instance) and sets its properties to the values of the
	 * object param.
	 * @param IDirected $aContext - used to get the Director object.
	 * @param object $anObj - object to copy data from.
	 * @return ABitsCostume Returns the new instance.
	 */
	static public function fromObj(IDirected $aContext, $anObj) {
		$theClassName = get_called_class();
		$o = new $theClassName($aContext);
		return $o->setDataFrom($anObj);
	}
	
	/**
	 * Create a new instance of whatever class this method
	 * is called from (MyClass::fromJson() makes a MyClass
	 * instance) and sets its properties to the values of the
	 * JSON param.
	 * @param IDirected $aContext - used to get the Director object.
	 * @param string $asJson - JSON encoded string
	 * @return ABitsCostume Returns the new instance.
	 */
	static public function fromJson(IDirected $aContext, &$asJson) {
		$o = self::fromArray($aContext, json_decode($asJson,true));
		return $o;
	}
	
	/**
	 * Return this payload data as a simple class, minus any metadata this class might have.
	 * @return string Return self encoded as a standard class.
	 */
	public function exportData() {
		//default export is "all public fields"
		return (object) call_user_func('get_object_vars', $this);
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
	 * @return BitsTheater\costumes\mixed Returns the converted class.
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
	 * @param IDirected $aContext - used to get the Director object.
	 * @param StandardClass $aStdClass - the StdClass instance to convert from.
	 * @return \BitsTheater\costumes\mixed Returns the converted class.
	 */
	static public function fromStdClass(IDirected $aContext, StandardClass $aStdClass) {
		//$aContext->debugLog('costume stdcls: '.$aContext->debugStr($aStdClass));
		$o = self::cnvStdClassToXClass($aStdClass, get_called_class());
		//$aContext->debugLog('costume cls: '.$aContext->debugStr($o));
		$o->director = $aContext->getDirector();
		return $o;
	}

	/**
	 * Construct a standard object with all data fields worth exporting defined.
	 * @return object Returns a standard object with the properties to export defined.
	 */
	protected function constructExportObject()
	{
		$o = (object) call_user_func('get_object_vars', $this);
		unset($o->myClassName);
		unset($o->mySimpleClassName);
		unset($o->myNamespaceName);
		return $o;
	}
	
}//end class
	
}//end namespace
