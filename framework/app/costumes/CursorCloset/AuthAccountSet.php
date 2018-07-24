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
use BitsTheater\models\Auth as MyModel;
use BitsTheater\costumes\AuthAccount as MyRecord;
use BitsTheater\costumes\AuthGroupList;
use BitsTheater\models\AuthGroups as AuthGroupsProp;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a set of AuthAccounts.
 *
 * <pre>
 * $theSet = AuthAccountSet::create($this)
 *     ->setDataFromPDO($pdo)
 *     ;
 * </pre>
 */
class AuthAccountSet extends BaseCostume
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
	{ return 'accounts'; }
	
	/**
	 * Group data to be returned.
	 * @var AuthGroupList
	 */
	public $mGroupList = null;
	
	/**
	 * @return AuthGroupsProp Returns the database model reference.
	 */
	protected function getAuthGroupsProp()
	{ return $this->getProp(AuthGroupsProp::MODEL_NAME); }

	/**
	 * Sets the construction arguments for our Item Class.
	 * @param mixed $_ - arguments to pass to the class's constructor.
	 * @return $this Returns $this for chaining.
	 */
	public function setItemClassArgs( ...$args )
	{
		// check field list argument of MyRecord for extended info we need to retrieve.
		if ( !empty($args[1]) ) {
			//the field list arg
			$theFieldList =& $args[1];
			$theIndex = array_search('with_map_info', $theFieldList);
			$bAddAllMaps = ( $theIndex!==false );
			if ( $bAddAllMaps || array_search('groups', $theFieldList)!==false ) {
				$this->mGroupList = AuthGroupList::create($this->getModel());
			}
		}
		return parent::setItemClassArgs(...$args);
	}
	
	/**
	 * Event called after fetching from $this->mDataSet.
	 * @param \BitsTheater\costumes\AuthAccount $aRow - the fetched data.
	 * @return object Returns the row data fetched.
	 */
	protected function onFetch($aRow)
	{
		if ( !empty($aRow) && !empty($this->mGroupList) ) {
			$this->mGroupList->addListOfIds($aRow->groups);
		}
		return parent::onFetch($aRow);
	}
	
	/**
	 * print() out extra properties besides the set of records here, if any.
	 * @param string $aEncodeOptions options for `json_encode()`
	 */
	protected function printExtraJsonProperties( $aEncodeOptions )
	{
		if ( !empty($this->mGroupList) ) {
			print(',"titan_group_id":"'
					. $this->getAuthGroupsProp()->getTitanGroupID() . '"'
			);
			print(',"authgroups":');
			$this->mGroupList->printAsJson( $aEncodeOptions );
		}
		parent::printExtraJsonProperties($aEncodeOptions);
	}
	
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
	 * @see MyModel::getOrganizationsToDisplay()
	 * @param SqlBuilder $aFilter - (optional) additional filtering.
	 * @return $this Returns $this for chaining.
	 */
	public function getAccountsToDisplay(SqlBuilder $aFilter=null)
	{
		$theRowSet = $this->getMyModel()
			->getAuthAccountsToDisplay($this, $aFilter);
		$this->setDataFromPDO($theRowSet);
		//$this->filter = $aFilter->?; //not supported yet
		return $this;
	}
	
	/**
	 * @return string[] Return what fields our item has available for filters.
	 */
	public function getItemFieldListForFilters()
	{
		$theItemClass = $this->mItemClass;
		if ( is_callable("{$theItemClass}::getFilterFieldList") )
		{ $theList = $theItemClass::getFilterFieldList(); }
		else
		{ $theList = array(); }
		if ( !empty($this->mGroupList) )
		{
			$theList = array_merge($theList, array(
					'group_id',
					'group_name',
			));
		}
		return $theList;
	}
	
	/**
	 * Given the filter and fieldname, apply the proper query filter.
	 * @param SqlBuilder $aFilter - the filter object.
	 * @param string $aFieldname - the fieldname.
	 */
	protected function handleFilterField( SqlBuilder $aFilter, $aFieldname )
	{
		switch ( $aFieldname ) {
			case 'is_active':
				$aFilter->addParam($aFieldname, null, \PDO::PARAM_INT);
				break;
			case 'hardware_ids':
			case 'mapped_imei':
				$theSavedPrefix = $aFilter->myParamPrefix;
				$dbAuth = $this->getMyModel();
				$aFilter->add($aFilter->myParamPrefix . 'auth_id IN (');
				$aFilter->endWhereClause()
					->add('SELECT auth_id FROM')->add($dbAuth->tnAuthTokens)
					;
				$aFilter->startWhereClause()->setParamOperator(' LIKE ')
					->addFieldAndParam('token', 'mapped_imei')
					->setParamOperator('=')
					;
				$aFilter->add(')');
				$aFilter->setParamPrefix($theSavedPrefix);
				break;
			case 'group_id':
			case 'group_name':
				$theSavedPrefix = $aFilter->myParamPrefix;
				$dbAuthGroups = $this->getAuthGroupsProp();
				$aFilter->add($aFilter->myParamPrefix . 'account_id IN (');
				$aFilter->endWhereClause()
					->add('SELECT account_id FROM')->add($dbAuthGroups->tnGroupMap)
					;
				if ( $aFieldname!=='group_id') {
					$aFilter->add('INNER JOIN')->add($dbAuthGroups->tnGroups)
						->add('USING(group_id)')
						;
				}
				$aFilter->startWhereClause()->addParam($aFieldname);
				$aFilter->add(')');
				$aFilter->setParamPrefix($theSavedPrefix);
				break;
			default:
				$aFilter->addParam($aFieldname);
		}//switch
	}
	
	/**
	 * @return string[] Return what fields should be used for generic search.
	 */
	public function getItemFieldListForSearch()
	{
		$theItemClass = $this->mItemClass;
		if ( is_callable("{$theItemClass}::getSearchFieldList") )
		{ $theList = $theItemClass::getSearchFieldList(); }
		else
		{ $theList = array(); }
		if ( !empty($this->mGroupList) )
		{ $theList[] = 'group_name'; }
		return $theList;
	}
	
	/**
	 * Given the filter and fieldname, apply the proper query filter.
	 * @param SqlBuilder $aFilter - the filter object.
	 * @param string $aFieldname - the fieldname.
	 * @param string $aSearchText - the text to search for.
	 */
	protected function handleSearchField( SqlBuilder $aFilter, $aFieldname, $aSearchText )
	{
		switch ( $aFieldname ) {
			case 'mapped_imei':
				$theSavedPrefix = $aFilter->myParamPrefix;
				$dbAuth = $this->getMyModel();
				$aFilter->add($aFilter->myParamPrefix . 'auth_id IN (');
				$aFilter->endWhereClause()
					->add('SELECT auth_id FROM')->add($dbAuth->tnAuthTokens)
					;
				$aFilter->startWhereClause()->setParamOperator(' LIKE ')
					->addFieldAndParam('token', 'mapped_imei', $aSearchText)
					->setParamOperator('=')
					;
				$aFilter->add(')');
				$aFilter->setParamPrefix($theSavedPrefix);
				break;
			case 'group_name':
				$theSavedPrefix = $aFilter->myParamPrefix;
				$dbAuthGroups = $this->getAuthGroupsProp();
				$aFilter->add($aFilter->myParamPrefix . 'account_id IN (');
				$aFilter->endWhereClause()
					->add('SELECT account_id FROM')->add($dbAuthGroups->tnGroupMap)
					->add('INNER JOIN')->add($dbAuthGroups->tnGroups)->add('USING(group_id)')
					;
				$aFilter->startWhereClause()->setParamOperator(' LIKE ')
					->addParam($aFieldname, $aSearchText)
					->setParamOperator('=')
					;
				$aFilter->add(')');
				$aFilter->setParamPrefix($theSavedPrefix);
				break;
			default:
				$aFilter->setParamOperator(' LIKE ')
					->addParam($aFieldname, $aSearchText)
					->setParamOperator('=')
					;
		}//switch
	}
	
	/**
	 * Given an object/array of fields to filter on and/or some text to search
	 * multiple fields for, return the SqlBuilder-based filter for use with
	 * the model's "*ForDisplay()" method.
	 * @param object|array $aFieldFilter - the simple filter based on field names.
	 * @param string $aSearchText - text to search over multiple fields.
	 * @param boolean $bAndSearchText - if TRUE, 'AND' the filter/search terms,
	 *   otherwise 'OR' them.
	 * @return SqlBuilder Returns the constructed filter object.
	 */
	public function getFilterForSearch($aFieldFilter=null, $aSearchText=null,
			$bAndSearchText=true)
	{
		if ( empty($aFieldFilter) && empty($aSearchText) ) return null; //trivial
		//create the base filter object
		$theFilter = SqlBuilder::withModel( $this->getMyModel() )
			//filter should always be in "where clause mode"
			->startWhereClause()->setParamPrefix(' AND ')
			;
		//if searching for generic text, search over these fields
		if ( !empty($aSearchText) )
		{
			$theSearchFieldList = $this->getItemFieldListForSearch();
			//$this->logStuff(__METHOD__, ' search=', $theSearchFieldList);//DEBUG
			$theFilter->add('(0')
				->setParamOperator(' LIKE ')->setParamPrefix(' OR ')
				//mapped_imei field becomes a simple matter with a data handler
				->setParamDataHandler('mapped_imei',
						function($thisSqlBuilder, $paramKey, $currentParamValue) {
							if ( empty($currentParamValue) ) return null;
							$theModel = $thisSqlBuilder->myModel;
							//remove the outer wildcards, then build up the proper filter.
							$currentParamValue = Strings::stripEnclosure(
									$currentParamValue, '%'
							);
							//if we want to enforce real IMEI's, pad to 15 chars
							/*
							if ( $theModel->dbType()!=$theModel::DB_TYPE_SQLSRV )
							{ $theValue = str_pad($currentParamValue, 15, '_'); }
							else
							{ $theValue = str_pad($currentParamValue, 15, '?'); }
							*/
							$theValue = '%' . $currentParamValue . '%';
							return $theModel::TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT
									. ':' . $theValue . ':%';
						}
					)
				;
			foreach ($theSearchFieldList as $theField) {
				$this->handleSearchField($theFilter, $theField, $aSearchText);
			}//foreach
			$theFilter->setParamOperator('=')->setParamPrefix(' AND ');
			$theFilter->add(')');
		}
		else {
			$bAndSearchText = true;
		}
		//if filtering on specific fields, add those values into WHERE clause
		if ( !empty($aFieldFilter) ) {
			//$this->logStuff(__METHOD__, ' filter=', $aFieldFilter);//DEBUG
			//if we started with no search criteria, place dummy first term
			if ( empty($aSearchText) )
			{ $theFilter->add('1'); }
			//see if we OR the search term or AND it; use add() since we will always end ')'.
			if ( !$bAndSearchText )
			{ $theFilter->add(' OR (1'); }
			else
			{ $theFilter->add(' AND (1'); }
			$theFilter->obtainParamsFrom($aFieldFilter)->setParamPrefix(' AND ')
				//mapped_imei field becomes a simple matter with a data handler
				->setParamDataHandler('mapped_imei',
						function($thisSqlBuilder, $paramKey, $currentParamValue) {
							if ( empty($currentParamValue) ) return null;
							$theModel = $thisSqlBuilder->myModel;
							//if we want to enforce real IMEI's, pad to 15 chars
							/*
							if ( $theModel->dbType()!=$theModel::DB_TYPE_SQLSRV )
							{ $theValue = str_pad($currentParamValue, 15, '_'); }
							else
							{ $theValue = str_pad($currentParamValue, 15, '?'); }
							*/
							$theValue = '%' . $currentParamValue . '%';
							return $theModel::TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT
									. ':' . $theValue . ':%';
						}
					)
				;
			$theFilterFieldList = $this->getItemFieldListForFilters();
			foreach ($theFilterFieldList as $theField) {
				//first, check to see if we _have any_ filtering (avoids subqueries)
				$theFilterValue = $theFilter->getDataValue($theField);
				if ( !isset($theFilterValue) ) continue;
				//we have a filter with data, handle it
				$this->handleFilterField($theFilter, $theField);
			}//foreach
			$theFilter->add(')');
		}
		return $theFilter;
	}
	
}//end class

}//end namespace
