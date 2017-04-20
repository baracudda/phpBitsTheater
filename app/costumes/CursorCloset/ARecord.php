<?php
/*
 * Copyright (C) 2017 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes\CursorCloset;
use BitsTheater\costumes\ASimpleCostume as BaseCostume;
use BitsTheater\costumes\SimpleCostume;
use BitsTheater\Model as MyModel;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * PDO statements can fetch data directly into this class.
 */
class ARecord extends BaseCostume
{
	/**
	 * The model I need to access to.
	 * @var MyModel
	 */
	protected $dbModel = null;
	
	/**
	 * Export only these fields. All fields, if NULL.
	 * @var string[]
	 */
	protected $mExportTheseFields = null;

	public function __construct($aDbModel=null, $aFieldList=null)
	{
		$this->dbModel = $aDbModel;
		$this->mExportTheseFields = $aFieldList;
	}
	
	/**
	 * Return the fields that should be exported.
	 * @param object $aExportData - the data to export.
	 * @return object Returns the data to be exported.
	 */
	protected function exportFilter($aExportData)
	{
		if (!empty($aExportData) && !empty($this->mExportTheseFields)) {
			$o = new SimpleCostume();
			foreach ($this->mExportTheseFields as $theField) {
				$o->{$theField} = $aExportData->{$theField};
			}
			return $o;
		} else
			return $aExportData;
	}

	/**
	 * Construct the standard object with all data fields worth exporting defined.
	 * @return object Returns a standard object with the properties to export defined.
	 */
	protected function constructExportObject()
	{
		//default export is "all public fields"
		return (object) call_user_func('get_object_vars', $this);
	}
	
	/**
	 * Return this payload data as a simple class, minus any metadata this class might have.
	 * @return string Return self encoded as a standard class.
	 */
	public function exportData()
	{
		return $this->exportFilter( $this->constructExportObject() );
	}
	
	
}//end class

}//end namespace
