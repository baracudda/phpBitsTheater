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
use com\blackmoonit\exceptions\DbException;
use BitsTheater\costumes\CursorCloset\AuthAccount as BaseCostume;
{//namespace begin

/**
 * AuthOrg accounts can use this costume to wrap account info.
 * PDO statements can fetch data directly into this class.
 */
class AuthAcct4Orgs extends BaseCostume
{
	/** @var string My fully qualified classname. */
	const ITEM_CLASS = __CLASS__;
	
	// basic info
	public $last_seen_ts;
	
	//extended info (optional - must be explicitly asked for via mExportTheseFields)
	//  alias: "with_map_info" will include all of these fields
	//public $groups will be included if you specify the alias ^
	public $org_ids;
	
	/** @var string Used to limit what groups are returned. */
	protected $mCurrOrgID = null;
	
	/**
	 * Set the list of fields to restrict export to use.
	 * @param array $aFieldList - the field list.
	 * @return $this Returns $this for chaining.
	 */
	public function setExportFieldList($aFieldList)
	{
		$theIndex = array_search('with_map_info', $aFieldList);
		$bIncMapInfo = ( $theIndex!==false );
		if ( $theIndex!==false )
		{ unset($aFieldList[$theIndex]); }
		if ( empty($aFieldList) ) {
			$aFieldList = array_diff(static::getDefinedFields(), array(
					'groups',
					'org_ids',
			));
		}
		if ( $bIncMapInfo ) {
			$aFieldList[] = 'groups';
			$aFieldList[] = 'org_ids';
		}
		parent::setExportFieldList($aFieldList);
		if ( in_array('groups', $this->getExportFieldList()) ) {
			$this->mCurrOrgID = $this->getAuthProp()->getCurrentOrgID();
		}
		return $this;
	}
	
	/** @return \BitsTheater\models\Auth */
	protected function getAuthProp()
	{ return $this->getModel()->getProp( 'Auth' ); }
	
	/**
	 * groups ID list was requested, this method fills in that property.
	 */
	protected function getGroupsList()
	{
		$dbAuthGroups = $this->getAuthGroupsProp();
		if ( !empty($this->auth_id) ) try {
			$this->groups = $dbAuthGroups->getAcctGroupsForOrg(
					$this->auth_id, $this->mCurrOrgID
			);
		}
		catch ( DbException $dbx )
		{
			if ( $dbx->getCode()==$dbAuthGroups::ERR_CODE_EMPTY_AUTHGROUP_TABLE ) {
				parent::getGroupsList();
			}
			else {
				$this->getModel()->logErrors(__METHOD__, $dbx->getMessage());
			}
		}
		catch ( \Exception $x )
		{ $this->getModel()->logErrors(__METHOD__, $x->getMessage()); }
	}
	
	/**
	 * groups ID list was requested, this method fills in that property.
	 */
	protected function getOrgsList()
	{
		if ( !empty($this->auth_id) ) try {
			if ( in_array('org_ids', $this->getExportFieldList()) ) {
				$this->org_ids = $this->getAuthProp()
					->getOrgsForAuthCursor($this->auth_id, 'org_id')
					->fetchAll(\PDO::FETCH_COLUMN)
					;
			}
		}
		catch (\Exception $x)
		{ $this->getModel()->logErrors(__METHOD__, $x->getMessage()); }
	}
	
	/**
	 * Event called after fetching data from db and setting all our properties.
	 */
	public function onFetch()
	{
		parent::onFetch();
		$this->getOrgsList();
	}
	
}//end class

}//end namespace
