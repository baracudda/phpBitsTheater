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
use BitsTheater\models\Accounts as MyModel;
use BitsTheater\models\AuthGroups;
use BitsTheater\Director;
use com\blackmoonit\FinallyBlock;
use Exception;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a set of AuthAccounts.
 *
 * <pre>
 * $theSet = AuthAccountSet::create($this->getDirector())
 *     ->setDataFromPDO($pdo)
 *     ;
 * </pre>
 */
class AuthAccountSet extends BaseCostume
{
	public $filter = null;
	public $total_count = 0;
	
	/**
	 * The model I need to access to.
	 * @var MyModel
	 */
	protected $dbModel = null;
	/**
	 * The auth model I might need.
	 * @var AuthGroups
	 */
	protected $dbAuthGroups = null;
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
	 * Creates an iterated set based on an already-obtained PDOStatement.
	 * @param Director $aContext the context in which to create the object
	 * @return ContactSet
	 */
	static public function create(Director $aContext)
	{
		$o = parent::create($aContext);
		$o->dbModel = $aContext->getProp(MyModel::MODEL_NAME);
		$o->dbAuthGroups = $aContext->getProp(AuthGroups::MODEL_NAME);
		$o->mItemClassArgs = array($o->dbModel);
		return $o;
	}
	
	/**
	 * Event called after fetching from $this->mDataSet.
	 * @param object $aRow - the fetched data.
	 * @return object Returns the row data fetched.
	 */
	protected function onFetch($aRow)
	{
		try {
			if (!empty($this->mGroupList) && !empty($aRow)) {
				$aRow->groups = $this->dbAuthGroups->getAcctGroups($aRow->account_id);
				if (!empty($aRow->groups))
					foreach ($aRow->groups as &$theGroupId)
						$theGroupId = strval($theGroupId);
				$this->mGroupList->addListOfIds($aRow->groups);
			}
			if ($aRow !== false && !empty($aRow->hardware_ids))
			{
				//convert string field to a proper list of items
				$aRow->hardware_ids = explode('|', $aRow->hardware_ids);
				foreach ($aRow->hardware_ids as &$theToken) {
					list($thePrefix, $theHardwareId, $theUUID) = explode(':', $theToken);
					$theToken = $theHardwareId;
				}
				//if there is only 1 item, ensure it is just a string, not an array
				if (count($aRow->hardware_ids)==1)
					$aRow->hardware_ids = $aRow->hardware_ids[0];
			}
		} catch (Exception $e) {
			$this->debugLog(__METHOD__ . $e->getMessage());
		}
		return parent::onFetch($aRow);
	}
	
	/**
	 * Prints the entire data set to the output stream, item by item.
	 * @param string $aEncodeOptions options for `json_encode()`
	 * @return IteratedSet $this
	 */
	public function printAsJson( $aEncodeOptions=null )
	{
		print( '{' ) ;
		$theFinalEnclosure = new FinallyBlock(function($me) {
			print( ',"count":' . $me->mFetchedCount );
			print( '}' ) ;
		}, $this);
		try
		{
			print( '"filter":"' . $this->filter . '"');
			print( ',"total_count":' . $this->total_count );
			print( ',"accounts":');
			parent::printAsJson( $aEncodeOptions );
			if (!empty($this->mGroupList)) {
				print( ',"authgroups":');
				$this->mGroupList->printAsJson( $aEncodeOptions );
			}
		}
		catch( Exception $x )
		{
			$this->debugLog( __METHOD__ . ' failed: ' . $x->getMessage() );
			throw $x ;
		}
		return $this ;
	}

}//end class

}//end namespace
