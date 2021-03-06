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
use BitsTheater\costumes\CursorCloset\ARecordSetPaged as BaseCostume;
use BitsTheater\costumes\ISqlSanitizer;
use BitsTheater\costumes\WornForSqlSanitize;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\AuthGroup as MyRecord;
use BitsTheater\models\AuthGroups as MyModel;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a set of auth groups.
 *
 * <pre>
 * $theSet = AuthGroupSet::create($this)
 *     ->setDataFromPDO($pdo)
 *     ;
 * </pre>
 */
class AuthGroupSet extends BaseCostume
implements ISqlSanitizer
{ use WornForSqlSanitize;

	/**
	 * The name of the class that will be used by default to contain items of
	 * the set.
	 * @var string
	 */
	const DEFAULT_ITEM_CLASS = MyRecord::ITEM_CLASS;
	/**
	 * Return the Model class or name to use in a getProp() call.
	 * @var string
	 */
	const MY_MODEL_CLASS = MyModel::MODEL_NAME;
	
	/**
	 * Return the property name the JSON export should use for the array of records.
	 * @return string "records" is used unless overridden by a descendant.
	 */
	protected function getJsonPropertyName() {
		return 'authgroups';
	}
	
	/** @return MyModel Returns my model to use. */
	protected function getMyModel( $aOrgID=null )
	{ return $this->getProp(static::MY_MODEL_CLASS, $aOrgID); }
	
	/**
	 * Return the default columns to sort by and whether or not they should be ascending.
	 * @return array Returns <code>array[fieldname => ascending=true]</code>.
	 */
	public function getDefaultSortColumns()
	{ return MyRecord::getDefaultSortColumns(); }
	
	/**
	 * Returns TRUE if the fieldname specified is sortable.
	 * @param string $aFieldName - the field name to check.
	 * @return boolean Returns TRUE if sortable, else FALSE.
	 */
	public function isFieldSortable($aFieldName)
	{ return MyRecord::isFieldSortable($aFieldName); }
	
	/** @return string[] Returns the list of fields we want to return. */
	public function getExportFieldsList()
	{ return ( isset($this->mItemClassArgs[1]) ) ? $this->mItemClassArgs[1] : null; }
	
	/**
	 * @see MyModel::getAuthGroupsToDisplay()
	 * @param SqlBuilder $aFilter - (optional) additional filtering.
	 * @param boolean $bIncludeUnknownGroup - (OPTIONAL) includes the UNKNOWN role if
	 *   set to TRUE (default=FALSE).
	 * @return $this Returns $this for chaining.
	 */
	public function getAuthGroupsToDisplay(SqlBuilder $aFilter=null, $bIncludeUnknownGroup=false)
	{
		$theFieldsToGet = $this->getExportFieldsList();
		$theRowSet = $this->getMyModel()
			->getAuthGroupsToDisplay($this, $aFilter, $theFieldsToGet, $bIncludeUnknownGroup);
		$this->setDataFromPDO($theRowSet);
		//$this->filter = $aFilter->?; //not supported yet
		return $this;
	}
	
}//end class

}//end namespace
