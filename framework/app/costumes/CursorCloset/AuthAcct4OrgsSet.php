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
use BitsTheater\costumes\CursorCloset\AuthAccountSet as BaseCostume;
use BitsTheater\costumes\AuthOrgList;
use BitsTheater\costumes\SqlBuilder;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a set of AuthAcct4Orgs.
 *
 * <pre>
 * $theSet = AuthAcct4OrgsSet::create($this)
 *     ->setDataFromPDO($pdo)
 *     ;
 * </pre>
 */
class AuthAcct4OrgsSet extends BaseCostume
{
	
	/**
	 * Org data to be returned.
	 * @var AuthOrgList
	 */
	public $mOrgList = null;
	
	/**
	 * Sets the construction arguments for our Item Class.
	 * @param mixed $args - arguments to pass to the class's constructor.
	 * @return $this Returns $this for chaining.
	 */
	public function setItemClassArgs( ...$args ): self
	{
		// check field list argument of MyRecord for extended info we need to retrieve.
		if ( !empty($args[1]) ) {
			//the field list arg
			$theFieldList =& $args[1];
			$theIndex = array_search('with_map_info', $theFieldList);
			$bAddAllMaps = ( $theIndex!==false );
			if ( $bAddAllMaps || array_search('org_ids', $theFieldList)!==false ) {
				$this->mOrgList = AuthOrgList::create($this->getMyModel());
			}
		}
		return parent::setItemClassArgs(...$args);
	}
	
	/**
	 * Event called after fetching from $this->mDataSet.
	 * @param \BitsTheater\costumes\AuthAccount $aRow - the fetched data.
	 * @return object|false Returns the row data fetched.
	 */
	protected function onFetch($aRow): object|false
	{
		if ( !empty($aRow) && !empty($this->mOrgList) ) {
			$this->mOrgList->addListOfIds($aRow->org_ids);
		}
		return parent::onFetch($aRow);
	}
	
	/**
	 * print() out extra properties besides the set of records here, if any.
	 * @param int $aEncodeOptions options for `json_encode()`
	 */
	protected function printExtraJsonProperties( int $aEncodeOptions=0 )
	{
		if (!empty($this->mOrgList)) {
			print( ',"authorgs":');
			$this->mOrgList->printAsJson( $aEncodeOptions );
		}
		parent::printExtraJsonProperties($aEncodeOptions);
	}
	
	/**
	 * {@inheritDoc}
	 * @see \BitsTheater\costumes\CursorCloset\AuthAccountSet::getItemFieldListForFilters()
	 */
	public function getItemFieldListForFilters()
	{
		$theList = parent::getItemFieldListForFilters();
		if ( !empty($this->mOrgList) )
		{
			$theList = array_merge($theList, array(
					'org_id',
					'org_name',
					'org_title',
					'org_desc',
					'parent_org_id',
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
			case 'group_id':
			case 'group_name':
				$theSavedPrefix = $aFilter->myParamPrefix;
				$dbAuthGroups = $this->getAuthGroupsProp();
				$aFilter->add($aFilter->myParamPrefix . 'auth_id IN (');
				$aFilter->endWhereClause()
					->add('SELECT auth_id FROM')->add($dbAuthGroups->tnGroupMap)
					;
				if ( $aFieldname!=='group_id') {
					$aFilter->add('INNER JOIN')->add($dbAuthGroups->tnGroups)
						->add('USING(group_id)')
						;
				}
				$aFilter	->startWhereClause()->addParam($aFieldname);
				$aFilter->add(')');
				$aFilter->setParamPrefix($theSavedPrefix);
				break;
			case 'org_id':
			case 'org_name':
			case 'org_title':
			case 'org_desc':
			case 'parent_org_id':
				$theSavedPrefix = $aFilter->myParamPrefix;
				$dbAuth = $this->getMyModel();
				$aFilter->add($aFilter->myParamPrefix . 'auth_id IN (');
				$aFilter->endWhereClause()
					->add('SELECT auth_id FROM')->add($dbAuth->tnAuthOrgMap)
					;
				if ( $aFieldname!=='org_id') {
					$aFilter->add('INNER JOIN')->add($dbAuth->tnAuthOrgs)
						->add('USING(org_id)')
						;
				}
				$aFilter->startWhereClause()->addParam($aFieldname);
				$aFilter->add(')');
				$aFilter->setParamPrefix($theSavedPrefix);
				break;
			default:
				parent::handleFilterField($aFilter, $aFieldname);
		}//switch
	}
	
	/**
	 * {@inheritDoc}
	 * @see \BitsTheater\costumes\CursorCloset\AuthAccountSet::getItemFieldListForSearch()
	 */
	public function getItemFieldListForSearch()
	{
		$theList = parent::getItemFieldListForSearch();
		if ( !empty($this->mOrgList) )
		{ $theList[] = 'org_title'; }
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
			case 'group_name':
				$theSavedPrefix = $aFilter->myParamPrefix;
				$dbAuthGroups = $this->getAuthGroupsProp();
				$aFilter->add($aFilter->myParamPrefix . 'auth_id IN (');
				$aFilter->endWhereClause()
					->add('SELECT auth_id FROM')->add($dbAuthGroups->tnGroupMap)
					->add('INNER JOIN')->add($dbAuthGroups->tnGroups)->add('USING(group_id)')
					;
				$aFilter->startWhereClause()->setParamOperator(' LIKE ')
					->setParamValueIfEmpty($aFieldname, $aSearchText)
					->addParam($aFieldname)
					->setParamOperator('=')
					;
				$aFilter->add(')');
				$aFilter->setParamPrefix($theSavedPrefix);
				break;
			case 'org_title':
				$theSavedPrefix = $aFilter->myParamPrefix;
				$dbAuth = $this->getMyModel();
				$aFilter->add($aFilter->myParamPrefix . 'auth_id IN (');
				$aFilter->endWhereClause()
					->add('SELECT auth_id FROM')->add($dbAuth->tnAuthOrgMap)
					->add('INNER JOIN')->add($dbAuth->tnAuthOrgs)->add('USING(org_id)')
					;
				$aFilter->startWhereClause()->setParamOperator(' LIKE ')
					->setParamValueIfEmpty($aFieldname, $aSearchText)
					->addParam($aFieldname)
					->setParamOperator('=')
					;
				$aFilter->add(')');
				$aFilter->setParamPrefix($theSavedPrefix);
				break;
			default:
				parent::handleSearchField($aFilter, $aFieldname, $aSearchText);
		}//switch
	}
	
}//end class

}//end namespace
