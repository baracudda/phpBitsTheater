<?php
/*
 * Copyright (C) 2018 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes\Wardrobe;
use BitsTheater\costumes\SimpleCostume;
{//begin namespace

trait WornForExportData
{
	/**
	 * Export these fields. All public fields, if NULL.
	 * @var string[]
	 */
	protected $mExportTheseFields = null;

	/**
	 * Accessor for the export field list.
	 * @return string[]
	 * @since BitsTheater v4.0.0
	 */
	public function getExportFieldList()
	{ return $this->mExportTheseFields ; }
	
	/**
	 * Set the list of fields to restrict export to use.
	 * @param array $aFieldList - the field list.
	 * @return $this Returns $this for chaining.
	 * @see static::exportData()
	 */
	public function setExportFieldList($aFieldList)
	{ $this->mExportTheseFields = $aFieldList; return $this; }

	/**
	 * Return the fields that should be exported.
	 * @param object $aExportData - the data to export.
	 * @return object Returns the data to be exported.
	 * @see static::constructExportObject()
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
	 * @return object Returns a standard object with the properties to export
	 *   defined and filled in with this objects current property values.
	 */
	protected function constructExportObject()
	{ return (object) call_user_func('get_object_vars', $this); }
	
	/**
	 * Return this payload data as a simple class,
	 * minus any metadata this class might have.
	 * @return object Returns the data to be exported.
	 */
	public function exportData()
	{ return $this->exportFilter( $this->constructExportObject() ); }
		
}//end trait

}//end namespace
