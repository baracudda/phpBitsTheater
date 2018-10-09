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

namespace BitsTheater\costumes;
{//begin namespace

/**
 * UI defined values like sort order, pager info, and requested fields desired
 * can be an attack vector (SQL Injection) if not properly sanitized.
 * If your class can protect against such attack vectors, define this interface
 * so that a SqlBuilder instance can query your object for sanitization methods.
 */
interface ISqlSanitizer
{
	/**
	 * @return string[] Returns the array of defined fields available.
	 */
	static function getDefinedFields() ;
	
	/**
	 * Returns TRUE if the fieldname specified is sortable.
	 * @param string $aFieldName - the field name to check.
	 * @return boolean Returns TRUE if sortable, else FALSE.
	 */
	function isFieldSortable( $aFieldName ) ;
	
	/**
	 * Return the default columns to sort by and whether or not they should be ascending.
	 * @return array Returns <code>array[fieldname => ascending=true]</code>.
	 */
	function getDefaultSortColumns() ;
	
	/**
	 * Figure out the columns to sort by and whether or not they should be ascending.
	 * The default behavior reads properties called <code>orderby</code> and possibly
	 * <code>orderbyrvs</code> to determine the result.
	 * If <code>orderby</code> is a string, then <code>orderbyrvs</code> is used to
	 * determine ascending or not. Otherwise, <code>orderby</code> is presumed to be
	 * an <code>array[fieldname => ascending=true]</code>.
	 * @return array Returns <code>array[fieldname => ascending=true]</code>.
	 */
	function getSortColumns() ;
	
	/**
	 * Providing click-able headers in tables to easily sort them by a particular field
	 * is a great UI feature. However, in order to prevent SQL injection attacks, we
	 * must double-check that a supplied field name to order the query by is something
	 * we can sort on; this method makes use of the <code>isFieldSortable()</code>
	 * method to determine if the browser supplied field name is one of our possible
	 * headers that can be clicked on for sorting purposes.
	 * @return array Returns <code>array[fieldname => ascending=true]</code>.
	 */
	function getSanitizedOrderByList() ;
	
	/**
	 * Prune the field list to remove any invalid fields.
	 * @param string[] $aFieldList - the supplied field list.
	 * @return string[] Returns the array of valid fields.
	 */
	static function getSanitizedFieldList( $aFieldList ) ;
	
	/**
	 * Get the defined maximum row count for a page in a pager.
	 * @return int Returns the max number of rows shown for a single pager page;
	 *   guaranteed to be >=1 unless the pager is disabled, which returns 0.
	 */
	function getPagerPageSize() ;
	
	/**
	 * Get the SQL query offset based on pager page size and page desired.
	 * @return int Returns the offset the query should use.
	 */
	function getPagerQueryOffset() ;
	
	/**
	 * Set the query total regardless of paging.
	 * @param number $aTotalRowCount - the total.
	 * @return $this Returns $this for chaining.
	 */
	function setPagerTotalRowCount( $aTotalRowCount ) ;
	
}//end interface

}//end namespace
