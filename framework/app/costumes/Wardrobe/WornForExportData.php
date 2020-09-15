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
use com\blackmoonit\Arrays;
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
				if ( isset($aExportData->{$theField}) ) {
					$o->{$theField} = $aExportData->{$theField};
				}
				else {
					$o->{$theField} = null;
				}
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
	{
		return (object) Arrays::getPublicPropertiesOfObject($this);
	}
	
	/**
	 * Return this payload data as a simple class,
	 * minus any metadata this class might have.
	 * @return object Returns the data to be exported.
	 */
	public function exportData()
	{ return $this->exportFilter( $this->constructExportObject() ); }
	
	/**
	 * Sometimes you just want to pass in a flag for all extra record
	 * information as a single field rather than list each one individually.
	 * Similar to passing in NULL, but adds in all other mapping data as well.
	 * This specifically checks for "with_map_info" as an entry in $aFieldList
	 * and removes the entry and replaces it with all the entries in
	 * $aMapFieldList.
	 * @return string[] Returns the munged $aFieldList with "with_map_info"
	 *   entry replaced by appending the $aMapFieldList entries.
	 */
	protected function appendFieldListWithMapInfo( $aFieldList, $aMapFieldList,
			$bForceAppend=false )
	{
		if ( $aFieldList==null ) $aFieldList = array();
		if ( $aMapFieldList==null ) $aMapFieldList = array();
		$theIndex = array_search('with_map_info', $aFieldList);
		$bIncMapInfo = ( $theIndex !== false || $bForceAppend );
		if ( $theIndex !== false ) {
			array_splice($aFieldList, $theIndex, 1);
		}
		if ( empty($aFieldList) ) {
			$aFieldList = array_diff($this::getDefinedFields(), $aMapFieldList);
		}
		if ( $bIncMapInfo ) {
			$aFieldList = array_merge($aFieldList, $aMapFieldList);
		}
		return $aFieldList;
	}
	
	/**
	 * Whenever a field list is undefined, we default to all publicly
	 * defined fields in the class as valid fields to fetch. Sometimes
	 * we want to have "expensive" fields that are only calculated/loaded
	 * when specifically asked for and not just when null is passed in.
	 * This method will remove these "expensive" fields if the field
	 * list passed in is NULL or otherwise empty().
	 * @param string[] $aFieldList
	 * @param string[] $aRemovalList
	 * @return string[] Returns the result
	 */
	static protected function restrictPublicFieldList( $aFieldList, $aRemovalList )
	{
		return ( !empty($aFieldList) ) ? $aFieldList : array_diff(
				static::getDefinedFields(), $aRemovalList
		);
	}
		
	/**
	 * Whenever a field list is undefined, we default to all publicly
	 * defined fields in the class as valid fields to fetch. Sometimes
	 * we want to have "expensive" fields that are only calculated/loaded
	 * when specifically asked for and not just when null is passed in.
	 * This method will remove these "expensive" fields as long as the
	 * class defines them with RESTRICTED_EXPORT_FIELD_LIST.
	 * @return string[] Returns the list of default export fields.
	 */
	static public function getDefaultExportFieldList()
	{
		$theFieldList = static::getDefinedFields();
		if ( !empty(static::$RESTRICTED_EXPORT_FIELD_LIST) ) {
			$theFieldList = array_diff($theFieldList, static::$RESTRICTED_EXPORT_FIELD_LIST);
		}
		return $theFieldList;
	}
	
}//end trait

}//end namespace
