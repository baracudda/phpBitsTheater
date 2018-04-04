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
use BitsTheater\costumes\CursorCloset\ARecordSet as BaseCostume;
use BitsTheater\costumes\AuthGroupList;
use BitsTheater\models\AuthGroups as AuthGroupsProp;
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
{
	/**
	 * Group data to be returned.
	 * @var AuthGroupList
	 */
	public $mGroupList = null;
	
	/**
	 * The name of the class that will be used by default to contain items of
	 * the set.
	 * @var string
	 */
	const DEFAULT_ITEM_CLASS = 'AuthAccount';

	/**
	 * Return the Model class or name to use in a getProp() call.
	 * @return string
	 */
	protected function getModelClassToUse() {
		return 'Accounts';
	}
	
	/**
	 * Return the property name the JSON export should use for the array of records.
	 * @return string "records" is used unless overridden by a descendant.
	 */
	protected function getJsonPropertyName() {
		return 'accounts';
	}
	
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
			if ( array_search('groups', $theFieldList)!==false ) {
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
	protected function printExtraJsonProperties( $aEncodeOptions ) {
		if ( !empty($this->mGroupList) ) {
			print(',"titan_group_id":"'
					. $this->getAuthGroupsProp()->getTitanGroupID() . '"'
			);
			print(',"authgroups":');
			$this->mGroupList->printAsJson( $aEncodeOptions );
		}
		parent::printExtraJsonProperties($aEncodeOptions);
	}
	
}//end class

}//end namespace
