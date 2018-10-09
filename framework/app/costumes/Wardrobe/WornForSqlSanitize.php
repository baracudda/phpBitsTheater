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

namespace BitsTheater\costumes\Wardrobe;
{//begin namespace

trait WornForSqlSanitize
{
	
	/**
	 * @var string[] The list of column names whose data should be returned.
	 */
	protected $mSqlSanitizedFieldList = null;
	
	/**
	 * @return string[] Returns an array of column names as values sorted
	 * in no particular order defining what columns of data to return.
	 */
	public function getFieldListDefinition()
	{ return $this->mSqlSanitizedFieldList; }
	
	/**
	 * Save the string array detailing the colums to return. The array is
	 * a simple collection of column names as values sorted in no particular
	 * order.
	 * @param string[] $aFieldListDef - the array defining the field list.
	 * @return $this Returns $this for chaining.
	 */
	public function setFieldListDefinition( $aFieldListDef )
	{
		$this->mSqlSanitizedFieldList = $aFieldListDef;
		return $this;
	}
	
	/**
	 * @var array <code>array[fieldname => ascending=true]</code>.
	 */
	protected $mSqlSanitizedSortDefinition = null;
	
	/**
	 * @return array Returns an <code>array[fieldname => ascending=true]</code>.
	 */
	public function getOrderByDefinition()
	{ return $this->mSqlSanitizedSortDefinition; }
	
	/**
	 * Save the array detailing the order by definition to use. The array is
	 * a collection of column names as keys already sorted in priority order
	 * with a boolean value where TRUE means Ascending and FALSE means
	 * descending sort order for that particular column.
	 * @param array $aOrderByDef - the array defining the sort order.
	 * @return $this Returns $this for chaining.
	 */
	public function setOrderByDefinition( $aOrderByDef )
	{
		$this->mSqlSanitizedSortDefinition = $aOrderByDef;
		return $this;
	}
	
	/**
	 * Returns TRUE if the fieldname specified is sortable.
	 * @param string $aFieldName - the field name to check.
	 * @return boolean Returns TRUE if sortable, else FALSE.
	 */
	abstract public function isFieldSortable( $aFieldName ) ;
	
	/**
	 * Return the default columns to sort by and whether or not they should be ascending.
	 * @return array Returns <code>array[fieldname => ascending=true]</code>.
	 */
	abstract public function getDefaultSortColumns() ;
	
	/**
	 * Get the defined maximum row count for a page in a pager.
	 * @return int Returns the max number of rows shown for a single pager page;
	 *   guaranteed to be >=1 unless the pager is disabled, which returns 0.
	 */
	abstract public function getPagerPageSize() ;
	
	/**
	 * Get the SQL query offset based on pager page size and page desired.
	 * @return int Returns the offset the query should use.
	 */
	abstract public function getPagerQueryOffset() ;
	
	/**
	 * Set the query total regardless of paging.
	 * @param number $aTotalRowCount - the total.
	 * @return $this Returns $this for chaining.
	 */
	abstract public function setPagerTotalRowCount( $aTotalRowCount ) ;
	
	/**
	 * Figure out the columns to sort by and whether or not they should be ascending.
	 * The default behavior is to call getOrderByDefinition() and return if not empty.
	 * The fallback behavior reads properties called <code>orderby</code> and possibly
	 * <code>orderbyrvs</code> to determine the result.
	 * If <code>orderby</code> is a string, then <code>orderbyrvs</code> is used to
	 * determine ascending or not. Otherwise, <code>orderby</code> is presumed to be
	 * an <code>array[fieldname => ascending=true]</code>.
	 * If neither default, nor fallback are defined, getDefaultSortColumns() is called.
	 * @return array Returns <code>array[fieldname => ascending=true]</code>.
	 */
	public function getSortColumns()
	{
		$theOrderBy = $this->getOrderByDefinition();
		if ( !empty($theOrderBy) ) {
			return $theOrderBy;
		}
		if ( !empty($this->orderby) ) {
			if ( is_string($this->orderby) )
				return array( $this->orderby => ($this->orderbyrvs ? false : true) );
			else if ( is_array($this->orderby) )
				return $this->orderby;
		}
		return $this->getDefaultSortColumns();
	}
	
	/**
	 * Providing click-able headers in tables to easily sort them by a particular field
	 * is a great UI feature. However, in order to prevent SQL injection attacks, we
	 * must double-check that a supplied field name to order the query by is something
	 * we can sort on; this method makes use of the <code>isFieldSortable()</code>
	 * method to determine if the browser supplied field name is one of our possible
	 * headers that can be clicked on for sorting purposes.
	 * @return array Returns <code>array[fieldname => ascending=true]</code>.
	 */
	public function getSanitizedOrderByList()
	{
		$theOrderByList = $this->getSortColumns();
		foreach ($theOrderByList as $theFieldName => $bSortAscending)
		{
			if ( !$this->isFieldSortable($theFieldName) )
				unset($theOrderByList[$theFieldName]);
		}
		return $theOrderByList;
	}
	
	/**
	 * Prune the field list to remove any invalid fields.
	 * @param string[] $aFieldList - the supplied field list.
	 * @return string[] Returns the array of valid fields.
	 */
	static public function getSanitizedFieldList( $aFieldList )
	{ return array_intersect( static::getDefinedFields() , $aFieldList ) ; }
	
	/**
	 * Check for what fields to return by API request, allowing for default if
	 * not supplied.
	 * @param array $aDefaultFieldList - (optional) a default field list.
	 * @return NULL|string[] Returns the array of fields to select.
	 */
	public function getRequestedFieldList( $aDefaultFieldList=null )
	{
		$theFieldList = null;
		if (!empty($this->field_list))
			$theFieldList = static::getSanitizedFieldList( $this->field_list );
		if (empty($theFieldList))
			$theFieldList = static::getSanitizedFieldList( $aDefaultFieldList );
		//if we are still at a loss for fields, just return NULL
		if (empty($theFieldList))
			$theFieldList = null;
		return $theFieldList;
	}
	
	/**
	 * Append this field and its direction to the sort order definition.
	 * @param string $aField - a field name.
	 * @param boolean $bIsAscendingOrder - (OPTIONAL) set to FALSE for
	 *   Descending order or use ASC/DESC strings.
	 * @return $this Returns $this for chianing.
	 */
	public function addOrderByDefinition( $aField, $bIsAscendingOrder=true )
	{
		$theOrderByDef = $this->getOrderByDefinition();
		if ( is_null($theOrderByDef) ) {
			$theOrderByDef = array();
		}
		$theOrderByDef[$aField] = $bIsAscendingOrder;
		$this->setOrderByDefinition($theOrderByDef);
		return $this;
	}
	
	/**
	 * Initialize our data based on vars found in a Scene.
	 * Vars looked for in Scene parameter:<br>
	 *   <code>orderby</code>
	 * , <code>orderbyrvs</code>
	 * , <code>field_list</code>
	 * @param object $aScene - the scene object we are pulling data from.
	 * @return $this Returns $this for chaining.
	 */
	public function setupSqlDataFromUserData( $aScene )
	{
		// see if we have an orderby clause
		if ( !empty($aScene->orderby) ) {
			if ( is_string($aScene->orderby) ) {
				$bSortAscending = !filter_var($aScene->orderbyrvs, FILTER_VALIDATE_BOOLEAN);
				$this->addOrderByDefinition($aScene->orderby, $bSortAscending);
			}
			else if ( is_array($aScene->orderby) ) {
				foreach ( $aScene->orderby as $theKey => $theVal ) {
					// if input is proper string key to boolean-ish|ASC/DESC value
					if ( is_string($theKey) ) {
						$this->addOrderByDefinition($theKey, $theVal);
					}
					// else treat as list of strings, all in ASC order
					else {
						$this->addOrderByDefinition($theVal);
					}
				}
			}
		}
		if ( !empty($aScene->field_list) ) {
			$this->setFieldListDefinition(
					static::getSanitizedFieldList($aScene->field_list)
			);
		}
		return $this;
	}
	
}//end trait

}//end namespace
