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
use BitsTheater\costumes\CursorCloset\ARecordSet as BaseCostume;
use BitsTheater\costumes\ISqlSanitizer;
use BitsTheater\costumes\WornForSqlSanitize;
use BitsTheater\costumes\WornForPagerManagement;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\models\Auth as MyModel;
use BitsTheater\costumes\AuthOrg as MyRecord;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a set of auth organizations.
 *
 * <pre>
 * $theSet = AuthOrgSet::create($this)
 *     ->setDataFromPDO($pdo)
 *     ;
 * </pre>
 */
class AuthOrgSet extends BaseCostume
implements ISqlSanitizer
{ use WornForSqlSanitize, WornForPagerManagement;

	/**
	 * The name of the class that will be used by default to contain items of
	 * the set.
	 * @var string
	 */
	const DEFAULT_ITEM_CLASS = MyRecord::ITEM_CLASS;
	
	/**
	 * Return the Model class or name to use in a getProp() call.
	 * @return string
	 */
	protected function getModelClassToUse()
	{ return MyModel::MODEL_NAME; }
	
	/** @return MyModel Returns my model to use. */
	protected function getMyModel()
	{ return $this->getProp($this->getModelClassToUse()); }
	
	/**
	 * Return the property name the JSON export should use for the array of records.
	 * @return string "records" is used unless overridden by a descendant.
	 */
	protected function getJsonPropertyName()
	{ return 'authorgs'; }
	
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
	
	/**
	 * @return number Returns the total count for query
	 *   regardless of paging.
	 */
	public function getPagerTotalRowCount()
	{ return $this->total_count; }
	
	/**
	 * Set the query total regardless of paging.
	 * @param number $aTotalRowCount - the total.
	 * @return $this Returns $this for chaining.
	 */
	public function setPagerTotalRowCount( $aTotalRowCount )
	{
		// protect against negative numbers/overflow
		$this->total_count = max($aTotalRowCount, 0);
		return $this;
	}
	
	/** @return string[] Returns the list of fields we want to return. */
	public function getExportFieldsList()
	{ return ( isset($this->mItemClassArgs[1]) ) ? $this->mItemClassArgs[1] : null; }
	
	/**
	 * @see MyModel::getOrganizationsToDisplay()
	 * @param SqlBuilder $theFilter - (optional) additional filtering.
	 * @return $this Returns $this for chaining.
	 */
	public function getOrganizationsToDisplay($theFilter=null)
	{
		$theRowSet = $this->getMyModel()
			->getOrganizationsToDisplay($this,
					$this->getExportFieldsList(), $theFilter
			);
		$this->setDataFromPDO($theRowSet);
		//$this->filter = $theFilter?; //not supported yet
		return $this;
	}
	
	
}//end class

}//end namespace
