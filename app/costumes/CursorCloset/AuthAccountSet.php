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
use BitsTheater\Director;
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
	 * Return the Model class or name to use in a getProp() call.
	 * @return class|string
	 * @see Director::getProp()
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
	 * Costume classes know about the Director.
	 * @param Director $aDirector - site director object
	 */
	public function setup(Director $aDirector) {
		parent::setup($aDirector);
		$this->dbAuthGroups = $aDirector->getProp( 'AuthGroups' );
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
				/* instead of forcing strings, use JSON_FORCE_OBJECT in json_encode options
				if (!empty($aRow->groups))
					foreach ($aRow->groups as &$theGroupId)
						$theGroupId = strval($theGroupId);
				*/
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
		} catch (\Exception $e) {
			$this->errorLog(__METHOD__ . $e->getMessage());
		}
		return parent::onFetch($aRow);
	}
	
	/**
	 * print() out extra properties besides the set of records here, if any.
	 * @param string $aEncodeOptions options for `json_encode()`
	 */
	protected function printExtraJsonProperties( $aEncodeOptions ) {
		if (!empty($this->mGroupList)) {
			print( ',"authgroups":');
			$this->mGroupList->printAsJson( $aEncodeOptions );
		}
	}
	
	/**
	 * Prints the entire data set to the output stream, item by item.
	 * @param string $aEncodeOptions options for `json_encode()`
	 * @return IteratedSet $this
	 */
	public function printAsJson( $aEncodeOptions=null )
	{
		return parent::printAsJson( $aEncodeOptions | JSON_FORCE_OBJECT );
	}

}//end class

}//end namespace
