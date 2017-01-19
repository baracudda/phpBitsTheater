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
use BitsTheater\costumes\CursorCloset\AuthGroupSet as BaseCostume;
use BitsTheater\costumes\SqlBuilder;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a list of auth groups.
 *
 * <pre>
 * $theSet = AuthGroupList::create($this->getDirector())
 *     ->setListOfIds($theListToUse)
 *     ;
 * </pre>
 */
class AuthGroupList extends BaseCostume
{
	/**
	 * Process the list of IDs in chunks of this size.
	 * @var number
	 */
	const CHUNK_SIZE_TO_PROCESS = 25;
	
	/**
	 * The list of IDs to use to retrieve data.
	 * NOTE: the IDs must be the KEYs, value is throwaway.
	 * @var bool[]
	 */
	protected $mIdList = array();
	
	/**
	 * The list of fields to retrieve.
	 * @var array
	 */
	protected $mFieldList = null;
	
	/**
	 * Set the list of IDs to load.
	 * NOTE: the IDs must be the KEYs, value is throwaway.
	 * @param array $aList - the list to use.
	 * @return AuthGroupList Returns $this for chaining.
	 */
	public function setListOfIds($aList) {
		$this->mIdList = $aList;
		return $this;
	}
	
	/**
	 * Append to the list of IDs to load.
	 * NOTE: the IDs must be the KEYs, value is throwaway.
	 * @param array $aList - the list of IDs to add.
	 * @return AuthGroupList Returns $this for chaining.
	 */
	public function addListOfKeysAsIds($aList) {
		$this->mIdList = $this->mIdList + $aList;
		return $this;
	}
	
	/**
	 * Reduce data retrieval overhead by specifying which
	 * fields to return.
	 * @param array $aFieldList - the field list.
	 * @return AuthGroupList Returns $this for chaining.
	 */
	public function setFieldList($aFieldList) {
		$this->mFieldList = $aFieldList;
		return $this;
	}
	
	/**
	 * Append to the list of IDs to load.
	 * NOTE: the IDs must be the VALUEs.
	 * @param array $aList - the list of IDs to add.
	 * @return AuthGroupList Returns $this for chaining.
	 */
	public function addListOfIds($aList) {
		foreach ($aList as $theId) {
			if (empty($this->mIdList[$theId]))
				$this->mIdList[$theId] = true;
		}
		return $this;
	}
	
	/**
	 * Event called after fetching from $this->mDataSet.
	 * Sets $this->mCurrent and updates $this->mFetchedCount.
	 * @param object $aRow - the fetched data.
	 * @return object Returns the row data fetched.
	 */
	protected function onFetch($aRow) {
		if (empty($aRow) && !empty($this->mIdList)) {
			$theChunkSize = min(array(count($this->mIdList), self::CHUNK_SIZE_TO_PROCESS));
			//the chunk to process this go-around
			//  NOTE: since group_id is an INTEGER, we need to PRESERVE KEYS
			$theBiteSizeChunkOfIds = array_slice($this->mIdList, 0, $theChunkSize, true);
			//remove the chunck we're processing from the list
			$this->mIdList = array_slice($this->mIdList, $theChunkSize);
			//create our new mDataSet to iterate through
			$theSql = SqlBuilder::withModel($this->dbModel);
			$theSql->obtainParamsFrom(array(
					'group_id' => array_keys($theBiteSizeChunkOfIds),
			));
			$theSql->startWith('SELECT')->addFieldList($this->mFieldList);
			$theSql->add('FROM')->add($this->dbModel->tnGroups);
			$theSql->startWhereClause()->mustAddParam('group_id')->endWhereClause();
			$this->setDataFromPDO($theSql->query());
			return parent::onFetch($this->mDataSet->fetch());
		} else {
			return parent::onFetch($aRow);
		}
	}

	/**
	 * Prints the entire data set to the output stream, item by item.
	 * @param string $aEncodeOptions options for `json_encode()`
	 * @return IteratedSet $this
	 */
	public function printAsJson( $aEncodeOptions=null )
	{
		$this->total_count = count($this->mIdList);
		return $this->printAsJsonObjectWithIdKey('group_id', $aEncodeOptions);
	}

}//end class

}//end namespace
