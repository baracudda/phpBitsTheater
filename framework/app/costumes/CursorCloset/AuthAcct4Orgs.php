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
use BitsTheater\costumes\IDirected;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\models\Auth as AuthModel;
use BitsTheater\models\AuthGroups as AuthGroupsModel;
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
	
	/** @var string[] The list of fields "with_map_info" will expand to become. */
	static protected $mapInfoFields = array(
			'groups',
			'currOrgRoles',
			'lockout_count',
			'hardware_ids',
			'org_ids',
	);
	
	/** @var string[] Lists only roles for current org as opposed to all in groups. */
	public $currOrgRoles;
	
	//extended info (optional - must be explicitly asked for via mExportTheseFields)
	//  alias: "with_map_info" will include all of these fields
	//public $groups will be included if you specify the alias ^
	public $org_ids;
	
	/** @var string Used to limit what groups are returned. */
	protected $mCurrOrgID = null;
	/** @var boolean Used to limit what org_ids are returned. */
	protected $mLimitedOrgs = null;
	/** @var AuthModel */
	protected $dbAuth;
	
	/**
	 * Parse options array into various properties for our object.
	 * @param string[] $aOptions - options like ['bLoadHardwareIDs'=>true].
	 */
	protected function parseOptions( $aOptions )
	{
		parent::parseOptions($aOptions);
		if ( is_array($aOptions) ) {
			if ( !empty($aOptions['mCurrOrgID']) ) {
				$this->mCurrOrgID = $aOptions['mCurrOrgID'];
			}
			if ( !empty($aOptions['mLimitedOrgs']) ) {
				$this->mLimitedOrgs = $aOptions['mLimitedOrgs'];
			}
		}
	}
	
	/**
	 * Return the list of options to use given a list of key=>values.
	 * @param IDirected $aContext - the context to use (can be handy to get settings).
	 * @param string[] $aExportFieldList - the fields that will be exported.
	 * @param string[] $aMetaOptions - the key=>value list.
	 * @return string[] Returns the options list to use.
	 */
	static public function getOptionsListUsingShorthand( IDirected $aContext,
			$aExportFieldList, $aMetaOptions )
	{
		$theOptions = parent::getOptionsListUsingShorthand($aContext, $aExportFieldList, $aMetaOptions);
		$theOptions['mCurrOrgID'] = $aContext->getDirector()->getPropsMaster()->getDefaultOrgID();
		if ( !empty($aMetaOptions['limited_orgs']) ) {
			if ( !empty($theOptions['mCurrOrgID']) && $theOptions['mCurrOrgID'] != AuthModel::ORG_ID_4_ROOT ) {
				$theOptions['mLimitedOrgs'] = $aContext->getProp(AuthModel::MODEL_NAME)
					->getOrgAndAllChildrenIDs($theOptions['mCurrOrgID']);
			}
		}
		return $theOptions;
	}
		
	/** @return AuthModel */
	protected function getAuthProp()
	{
		if ( empty($this->dbAuth ) ) {
			$this->dbAuth = $this->getModel()->getProp( 'Auth' );
		}
		return $this->dbAuth;
	}
	
	/**
	 * groups ID list was requested, this method fills in that property.
	 */
	protected function getGroupsList()
	{
		if ( !empty($this->auth_id) ) try {
			//check to see if the initial SQL result has the data we need already
			if ( is_string($this->groups) ) {
				$this->groups = ( !empty($this->groups) ) ? explode(',', $this->groups) : null;
			}
			else {
				$dbAuthGroups = $this->getAuthGroupsProp();
				$this->groups = $dbAuthGroups->getAcctGroupsForOrg(
						$this->auth_id, $this->mCurrOrgID
				);
			}
			if ( is_string($this->currOrgRoles) ) {
				$this->currOrgRoles = ( !empty($this->currOrgRoles) ) ? explode(',', $this->currOrgRoles) : null;
			}
		}
		catch ( DbException $dbx ) {
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
				if ( !empty($this->mLimitedOrgs) ) {
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
		if ( is_string($this->org_ids) ) {
			$theList = ( !empty($this->org_ids) ) ? explode(',', $this->org_ids) : null;
			if ( !empty($theList) && !empty($this->mLimitedOrgs) ) {
				$this->org_ids = array_values(array_intersect($this->mLimitedOrgs, $theList));
			}
			else {
				$this->org_ids = $theList;
			}
		}
		else {
			$this->getOrgsList();
		}
	}
	
	/**
	 * What fields are text searchable?
	 * @return string[] Returns the list of searchable fields.
	 */
	static public function getSearchFieldList()
	{
		return array_merge(parent::getSearchFieldList(), array(
				'comments',
		));
	}
	
	/**
	 * Ensure we get the org list for an account.
	 * @param AuthGroupsModel $dbAuthGroups - the auth groups (roles) model.
	 * @param string $aAuthIDAlias - the auth_id value to match against.
	 * @return string Returns the SQL used for defining the "groups" field.
	 */
	static public function sqlForGroupList( $dbAuthGroups, $aAuthIDAlias )
	{
		$theSqlStr = parent::sqlForGroupList($dbAuthGroups, $aAuthIDAlias);
		switch ( $dbAuthGroups->dbType() ) {
			case $dbAuthGroups::DB_TYPE_MYSQL:
			default:
				$theOrgIDWhere = ' IS NULL';
				$theOrgID = $dbAuthGroups->getDirector()->getPropsMaster()->getDefaultOrgID();
				if ( !empty($theOrgID) && $theOrgID != AuthModel::ORG_ID_4_ROOT ) {
					$theOrgIDWhere = "='{$theOrgID}'";
				}
				$theSqlStr .= ', (SELECT' .
					" IFNULL(GROUP_CONCAT(__CurrOrgRoleMapAlias.group_id SEPARATOR ','), '')" .
					" FROM {$dbAuthGroups->tnGroupMap} AS __CurrOrgRoleMapAlias" .
					" JOIN {$dbAuthGroups->tnGroups} AS __CurrOrgRolesAlias USING (group_id)" .
					" WHERE {$aAuthIDAlias}=__CurrOrgRoleMapAlias.auth_id" .
					"   AND __CurrOrgRolesAlias.org_id" . $theOrgIDWhere .
					") AS currOrgRoles" ;
		}//switch
		return $theSqlStr;
	}
	
	/**
	 * Ensure we get the org list for an account.
	 * @param AuthModel $dbAuth - the auth model.
	 * @param string $aAuthIDAlias - the auth_id value to match against.
	 * @return string Returns the SQL used for defining the "org_ids" field.
	 */
	static public function sqlForOrgList( $dbAuth, $aAuthIDAlias )
	{
		if ( empty($dbAuth) ) {
			throw new \InvalidArgumentException('$dbAuth cannot be empty');
		}
		if ( empty($aAuthIDAlias) ) {
			throw new \InvalidArgumentException('$aAuthIDAlias cannot be empty');
		}
		switch ( $dbAuth->dbType() ) {
			case $dbAuth::DB_TYPE_MYSQL:
			default:
				$theSqlStr = '(SELECT' .
					" IFNULL(GROUP_CONCAT(__AuthOrgAlias.org_id SEPARATOR ','), '')" .
					" FROM {$dbAuth->tnAuthOrgMap} AS __AuthOrgAlias" .
					" WHERE {$aAuthIDAlias}=__AuthOrgAlias.auth_id" .
					") AS org_ids" ;
		}//switch
		return $theSqlStr;
	}
	
}//end class

}//end namespace
