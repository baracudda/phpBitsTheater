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
use BitsTheater\costumes\CursorCloset\AuthAccount as BaseCostume;
use BitsTheater\costumes\SqlBuilder;
use com\blackmoonit\exceptions\DbException;
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
	/** @var boolean Used to limit what org_ids are returned. */
	protected $bLimitOrgsToCurrentPlusChildren = false;
	protected $mLimitedOrgs = null;
	
	/**
	 * Set the list of fields to restrict export to use.
	 * @param array $aFieldList - the field list.
	 * @return $this Returns $this for chaining.
	 */
	public function setExportFieldList( $aFieldList )
	{
		//check for "limited_orgs" and treat as flag for setOrgsLimited() as
		//  some static factory methods do not allow a direct call to it.
		if ( !empty($aFieldList) ) {
			$theIndex = array_search('limited_orgs', $aFieldList);
			if ( $theIndex !== false ) {
				array_splice($aFieldList, $theIndex, 1); //its a flag, just remove it
				$this->setOrgsLimited(true);
			}
		}
		$aFieldList = $this->appendFieldListWithMapInfo($aFieldList, array(
				'groups',
				'org_ids',
		));
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
	 * Set if we limit orgs or not, default is no limits.
	 * @param boolean $aBool - the value to set.
	 * @return $this Returns $this for chaining.
	 */
	public function setOrgsLimited( $aBool )
	{
		$this->bLimitOrgsToCurrentPlusChildren = $aBool;
		$this->mLimitedOrgs = null;
		if ( $aBool ) {
			$dbAuth = $this->getAuthProp();
			$theCurrOrgID = $dbAuth->getCurrentOrgID();
			if ( !empty($theCurrOrgID) && $theCurrOrgID != $dbAuth::ORG_ID_4_ROOT ) {
				$this->mLimitedOrgs = $dbAuth->getOrgAndAllChildrenIDs($theCurrOrgID);
			}
		}
		return $this;
	}
	
	/**
	 * Get the flag value for if orgs are limited or not.
	 * @return boolean Returns TRUE if we are limiting orgs result.
	 */
	public function isOrgsLimited()
	{ return $this->bLimitOrgsToCurrentPlusChildren; }
	
	/**
	 * groups ID list was requested, this method fills in that property.
	 */
	protected function getGroupsList()
	{
		if ( !empty($this->auth_id) ) try {
			$dbAuthGroups = $this->getAuthGroupsProp();
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
				$dbAuth = $this->getAuthProp();
				$theFilter = null;
				if ( $this->isOrgsLimited() && !empty($this->mLimitedOrgs) ) {
					//instead of returning all the orgs the account belongs to, restrict it to
					//  only returning the orgs based on current org and its children.
					$theFilter = SqlBuilder::withModel($dbAuth)
						->startFilter(' AND map.')
						->setParamValue('showonlythese_orgs', $this->mLimitedOrgs)
						->addParamForColumn('showonlythese_orgs', 'org_id')
						;
				}
				$this->org_ids = $dbAuth
					->getOrgsForAuthCursor($this->auth_id, 'org_id', $theFilter)
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
