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

namespace BitsTheater\costumes\CursorCloset;
use BitsTheater\costumes\CursorCloset\ARecord as BaseCostume;
{//namespace begin

/**
 * Auth accounts can use this costume to wrap auth org info.
 * PDO statements can fetch data directly into this class.
 */
class AuthOrg extends BaseCostume
{
	/** @var string My fully qualified classname. */
	const ITEM_CLASS = __CLASS__;
	
	//included by default
	public $org_id;
	public $org_name;
	public $org_title;
	public $org_desc;
	public $parent_org_id;
	
	//specifically excluded until we find a reason to include
	//public $dbconn;
	
	//not included by default
	public $created_by;
	public $updated_by;
	public $created_ts;
	public $updated_ts;
	
	/**
	 * Set the list of fields to restrict export to use.
	 * @param array $aFieldList - the field list.
	 * @return $this Returns $this for chaining.
	 */
	public function setExportFieldList($aFieldList)
	{
		if ( empty($aFieldList) ) {
			$aFieldList = array_diff(static::getDefinedFields(), array(
					'created_by',
					'created_ts',
					'updated_by',
					'updated_ts',
			));
		}
		return parent::setExportFieldList($aFieldList);
	}
	
	/**
	 * Return the default columns to sort by and whether or not they should be ascending.
	 * @return array Returns <code>array[fieldname => ascending=true]</code>.
	 */
	static public function getDefaultSortColumns()
	{ return array('org_title' => true); }
	
	/**
	 * Returns TRUE if the fieldname specified is sortable.
	 * @param string $aFieldName - the field name to check.
	 * @return boolean Returns TRUE if sortable, else FALSE.
	 */
	static public function isFieldSortable($aFieldName)
	{
		$theAllowedSorts = array_diff(static::getDefinedFields(), array(
				'dbconn',
		));
		return ( array_search($aFieldName, $theAllowedSorts)!==false );
	}
	
}//end class
	
}//end namespace
