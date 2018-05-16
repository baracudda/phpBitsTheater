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
	 * The name of the class that will be used by default to contain items of
	 * the set.
	 * @var string
	 */
	const DEFAULT_ITEM_CLASS = 'AuthAcct4Orgs';

	/**
	 * Return the Model class or name to use in a getProp() call.
	 * @return string
	 */
	protected function getModelClassToUse() {
		return 'Auth';
	}
	
	/**
	 * Event called after fetching from $this->mDataSet.
	 * @param \BitsTheater\costumes\AuthAccount $aRow - the fetched data.
	 * @return object Returns the row data fetched.
	 */
	protected function onFetch($aRow)
	{
		if ( !empty($aRow) && !empty($this->mOrgList) ) {
			$this->mOrgList->addListOfIds($aRow->org_ids);
		}
		return parent::onFetch($aRow);
	}
	
	/**
	 * print() out extra properties besides the set of records here, if any.
	 * @param string $aEncodeOptions options for `json_encode()`
	 */
	protected function printExtraJsonProperties( $aEncodeOptions )
	{
		if (!empty($this->mOrgList)) {
			print( ',"authorgs":');
			$this->mOrgList->printAsJson( $aEncodeOptions );
		}
		parent::printExtraJsonProperties($aEncodeOptions);
	}
	
}//end class

}//end namespace
