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
use BitsTheater\costumes\colspecs\IteratedSet as BaseCostume;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\WornByModel;
use BitsTheater\Director;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a short list of records.
 *
 * <pre>
 * $theSet = RecordList::create($this->getDirector())
 *     ->setDataFromPDO($pdo)
 *     ;
 * </pre>
 *
 * @since BitsTheater 3.8.0
 */
abstract class ARecordList extends BaseCostume
{
	use WornByModel;
	
	/**
	 * The total count of the list.
	 * @var integer
	 */
	public $total_count = 0;
	/**
	 * Name of the ID field to use.
	 * @return string
	 */
	abstract protected function getIdFieldName();
	/**
	 * Name of the table where to get the record from.
	 * @return string
	 */
	abstract protected function getIdTableName();
	/**
	 * If the ID field is numeric, we need to know in order to PRESERVE KEYS in array_slice().
	 */
	const ID_IS_NUMERIC = false;
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
	 * Return the Model class or name to use in a getProp() call.
	 * @return class|string
	 * @see Director::getProp()
	 */
	abstract protected function getModelClassToUse();
	
	/**
	 * Costume classes know about the Director.
	 * @param Director $aDirector - site director object
	 */
	public function setup(Director $aDirector)
	{
		$this->setModel( $aDirector->getProp( $this->getModelClassToUse() ) );
		$this->mItemClassArgs = array( $this->getModel() );
		parent::setup( $aDirector );
	}
	
	/**
	 * Set the list of IDs to load.
	 * NOTE: the IDs must be the KEYs, value is throwaway.
	 * @param array $aList - the list to use.
	 * @return ARecordList Returns $this for chaining.
	 */
	public function setListOfIds($aList)
	{
		$this->mIdList = $aList;
		return $this;
	}
	
	/**
	 * Append to the list of IDs to load.
	 * NOTE: the IDs must be the KEYs, value is throwaway.
	 * @param array $aList - the list of IDs to add.
	 * @return ARecordList Returns $this for chaining.
	 */
	public function addListOfKeysAsIds($aList)
	{
		$this->mIdList = array_merge($this->mIdList, $aList);
		return $this;
	}
	
	/**
	 * Reduce data retrieval overhead by specifying which
	 * fields to return.
	 * @param array $aFieldList - the field list.
	 * @return ARecordList Returns $this for chaining.
	 */
	public function setFieldList($aFieldList)
	{
		$this->mFieldList = $aFieldList;
		return $this;
	}
	
	/**
	 * Append to the list of IDs to load.
	 * NOTE: the IDs must be the VALUEs.
	 * @param array $aList - the list of IDs to add.
	 * @return ARecordList Returns $this for chaining.
	 */
	public function addListOfIds($aList)
	{
		foreach ($aList as $theId) {
			if (empty($this->mIdList[$theId]))
				$this->mIdList[$theId] = true;
		}
		return $this;
	}
	
	/**
	 * Create the SqlBuilder to use for the query.
	 * @param array $aListOfIds - array of IDs to get records for.
	 * @return \BitsTheater\costumes\SqlBuilder
	 */
	protected function createSqlQuery( $aListOfIds )
	{
		$theSql = SqlBuilder::withModel( $this->getModel() );
		$theSql->obtainParamsFrom(array(
				$this->getIdFieldName() => $aListOfIds,
		));
		$theSql->startWith('SELECT')->addFieldList( $this->mFieldList );
		$theSql->add('FROM')->add( $this->getIdTableName() );
		$theSql->startWhereClause()->mustAddParam( $this->getIdFieldName() )->endWhereClause();
		return $theSql;
	}
	
	/**
	 * Event called after fetching from $this->mDataSet.
	 * Sets $this->mCurrent and updates $this->mFetchedCount.
	 * @param object $aRow - the fetched data.
	 * @return object Returns the row data fetched.
	 */
	protected function onFetch($aRow)
	{
		//List functions by taking a chunk off the list, fetching until there's nothing left
		//  then taking another chunk off the list, repeat until nothing is left of the mIdList.
		if (empty($aRow) && !empty($this->mIdList)) {
			//chunk size is either CHUNK_SIZE or list count, whichever is less.
			$theChunkSize = min(array(count($this->mIdList), static::CHUNK_SIZE_TO_PROCESS));
			//the chunk to process this go-around
			$theBiteSizeChunkOfIds = array_slice(
					$this->mIdList, 0, $theChunkSize, static::ID_IS_NUMERIC
			);
			//remove the chunck we're processing from the list
			$this->mIdList = array_slice($this->mIdList, $theChunkSize, static::ID_IS_NUMERIC);
			//create our new mDataSet to iterate through
			$theSql = $this->createSqlQuery( array_keys($theBiteSizeChunkOfIds) );
			$this->setDataFromPDO( $theSql->query() );
			return parent::onFetch( $this->mDataSet->fetch() );
		} else {
			return parent::onFetch( $aRow );
		}
	}

	/**
	 * Prints the entire data set to the output stream, item by item.
	 * @param string $aEncodeOptions options for `json_encode()`
	 * @return ARecordList $this
	 */
	public function printAsJson( $aEncodeOptions=null )
	{
		$this->total_count = count( $this->mIdList );
		return $this->printAsJsonObjectWithIdKey( $this->getIdFieldName(), $aEncodeOptions );
	}

}//end class

}//end namespace
