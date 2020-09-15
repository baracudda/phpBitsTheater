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
use BitsTheater\costumes\CursorCloset\ARecord as BaseCostume;
use BitsTheater\models\Auth as AuthModel;
use BitsTheater\models\AuthGroups as AuthGroupsModel;
{//namespace begin

/**
 * AuthBasic accounts can use this costume to wrap account info.
 * PDO statements can fetch data directly into this class.
 */
class AuthAccount extends BaseCostume
{
	/** @var string My fully qualified classname. */
	const ITEM_CLASS = __CLASS__;
	/** @var string The separator used between hardware IDs list as string. */
	const HARDWARE_IDS_SEPARATOR = ', ';
	
	public $account_id;
	public $account_name;
	//public $external_id;
	public $auth_id;
	public $email;
	//public $pwhash; do not export!
	public $verified_ts;
	public $is_active;
	/** @var string */
	public $comments;
	public $hardware_ids;
	public $created_by;
	public $updated_by;
	public $created_ts;
	public $updated_ts;
	
	//extended info (optional - must be explicitly asked for via mExportTheseFields)
	
	/** @var string[] Array of group_id values the account belongs to. */
	public $groups;
	/**
	 * Used to know if onFetch() should load authgroup information or not.
	 * @var boolean
	 */
	protected $bLoadAuthGroupInfo = false;
	/**
	 * Model for retrieving authgroup mapping information.
	 * @var AuthGroupsModel
	 */
	protected $dbAuthGroups = null;
	
	/** @var int The number of lockout tokens this account has accrued. */
	public $lockout_count;
	/** @var boolean if lockout count reaches or exceeds max limit. */
	public $is_locked;
	/**
	 * Used to know if onFetch() should load lockout information or not.
	 * @var boolean
	 */
	protected $bLoadLockoutInfo = false;
	/**
	 * Used to know if onFetch() should load hardware_ids or not.
	 * @var boolean
	 */
	protected $bLoadHardwareIDs = false;
	
	/**
	 * Set the list of fields to restrict export to use.
	 * @param array $aFieldList - the field list.
	 * @return $this Returns $this for chaining.
	 */
	public function setExportFieldList($aFieldList)
	{
		$aFieldList = $this->appendFieldListWithMapInfo($aFieldList, array(
				'groups',
				'lockout_count',
		));
		if ( in_array('load_hardware_ids', $aFieldList) ) {
			$aFieldList = array_diff($aFieldList, array('load_hardware_ids'));
			$this->bLoadHardwareIDs = true;
		}
		parent::setExportFieldList($aFieldList);
		if ( in_array('groups', $aFieldList) ) {
			$this->bLoadAuthGroupInfo = true;
			$this->dbAuthGroups = $this->getAuthGroupsProp();
		}
		if ( in_array('lockout_count', $aFieldList) ) {
			$this->bLoadLockoutInfo = true;
		}
		return $this;
	}
	
	/**
	 * Construct the standard object with all data fields worth exporting defined.
	 * @return object Returns a standard object with the properties to export defined.
	 */
	protected function constructExportObject()
	{
		$o = parent::constructExportObject();
		unset($o->pwhash); //never export this value
		$o->is_active = filter_var($o->is_active, FILTER_VALIDATE_BOOLEAN);
		if ( isset($o->lockout_count) ) {
			$theMaxAttempts = intval(
					$this->getMyModel()->getConfigSetting('auth/login_fail_attempts'),
					10
			);
			$o->is_locked = ( $o->lockout_count >= $theMaxAttempts );
		}
		else {
			unset($o->lockout_count);
			unset($o->is_locked);
		}
		return $o;
	}

	/** @return AuthModel */
	protected function getMyModel()
	{ return $this->getModel(); }
	
	/** @return AuthGroupsModel */
	protected function getAuthGroupsProp()
	{ return $this->getModel()->getProp('AuthGroups'); }
	
	/**
	 * groups ID list was requested, this method fills in that property.
	 */
	protected function getGroupsList()
	{
		if ( !empty($this->account_id) ) try {
			$this->groups = $this->getAuthGroupsProp()->getAcctGroups($this->account_id);
			if ( !empty($this->groups) ) {
				foreach ($this->groups as &$theGroupID) {
					if ( is_numeric($theGroupID) ) {
						$theGroupID = strval($theGroupID);
					}
				}
			}
		}
		catch (\Exception $x)
		{ $this->getModel()->logErrors(__METHOD__, $x->getMessage()); }
	}
	
	/**
	 * Sometimes we need to reload the groups list after loading the account
	 * record from the database.
	 * @return $this Returns $this for chaining.
	 */
	public function loadGroupsList()
	{
		$this->getGroupsList();
		return $this;
	}
	
	/**
	 * Parse any info retrieved via onFetch() for hardware_ids property.
	 */
	protected function parseHardwareIDs()
	{
		if ( !empty($this->hardware_ids) )
		{
			//convert string field to a proper list of items
			$this->hardware_ids = explode($this::HARDWARE_IDS_SEPARATOR, $this->hardware_ids);
			foreach ($this->hardware_ids as &$theToken) {
				list($thePrefix, $theHardwareId, $theUUID) = explode(':', $theToken);
				if ( !empty($thePrefix) && !empty($theHardwareId) && !empty($theUUID) ) {
					$theToken = $theHardwareId;
				}
			}
			//if there is only 1 item, ensure it is just a string, not an array
			if (count($this->hardware_ids)==1)
			{ $this->hardware_ids = $this->hardware_ids[0]; }
		}
	}
	
	/**
	 * Lockout Count was requested, this method fills in that property.
	 */
	protected function getLockoutCount()
	{
		if ( !empty($this->auth_id) ) try {
			$dbAuth = $this->getMyModel();
			$theTokens = $dbAuth->getAuthTokens($this->auth_id, $this->account_id,
					$dbAuth::TOKEN_PREFIX_LOCKOUT . "%", true
			);
			$this->lockout_count = ( !empty($theTokens) ) ? count($theTokens) : 0;
		}
		catch (\Exception $x)
		{ $this->getModel()->logErrors(__METHOD__, $x->getMessage()); }
	}
	
	/**
	 * Hardware IDs were requested, this method fills in that property.
	 */
	protected function getHardwareIDs()
	{
		if ( !empty($this->auth_id) && empty($this->hardware_ids) ) try {
			$dbAuth = $this->getMyModel();
			$theTokens = $dbAuth->getAuthTokens($this->auth_id, $this->account_id,
					$dbAuth::TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT . ":%", true
			);
			if ( !empty($theTokens) ) {
				$this->hardware_ids = implode(static::HARDWARE_IDS_SEPARATOR,
						array_column($theTokens, 'token')
				);
				$this->parseHardwareIDs();
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
		if ( $this->bLoadAuthGroupInfo ) {
			$this->getGroupsList();
		}
		$this->parseHardwareIDs();
		if ( $this->bLoadLockoutInfo ) {
			$this->getLockoutCount();
		}
		if ( $this->bLoadHardwareIDs ) {
			$this->getHardwareIDs();
		}
	}
	
	/**
	 * Return the default columns to sort by and whether or not they should be ascending.
	 * @return array Returns <code>array[fieldname => ascending=true]</code>.
	 */
	static public function getDefaultSortColumns()
	{ return array('account_name' => true); }
	
	/**
	 * Returns TRUE if the fieldname specified is sortable.
	 * @param string $aFieldName - the field name to check.
	 * @return boolean Returns TRUE if sortable, else FALSE.
	 */
	static public function isFieldSortable($aFieldName)
	{
		$theAllowedSorts = array_diff(static::getDefinedFields(), array(
		));
		return ( array_search($aFieldName, $theAllowedSorts)!==false );
	}
	
	/**
	 * What fields are individually filterable?
	 * @return string[] Returns the list of filterable fields.
	 */
	static public function getFilterFieldList()
	{ return static::getDefinedFields(); }
	
	/**
	 * What fields are text searchable?
	 * @return string[] Returns the list of searchable fields.
	 */
	static public function getSearchFieldList()
	{
		return array(
				'account_name',
				'email',
				'created_by',
				'updated_by',
				//NOTE: not sure how to "text search" date fields, yet
				//'created_ts',
				//'updated_ts',
				//'verified_ts',
		);
	}
	
	/**
	 * Ensure we get the "hardware_ids" field in such a way as we can decode it later.
	 * @param AuthModel $dbAuth - the auth model.
	 * @param string $aAuthIDAlias - the auth_id value to match against.
	 * @return string Returns the SQL used for defining the "hardware_ids" field.
	 */
	static public function sqlForHardwareIDs( $dbAuth, $aAuthIDAlias )
	{
		if ( empty($dbAuth) ) {
			throw new \InvalidArgumentException('$dbAuth cannot be empty');
		}
		if ( empty($aAuthIDAlias) ) {
			throw new \InvalidArgumentException('$aAuthIDAlias cannot be empty');
		}
		$theSeparator = "'" . static::HARDWARE_IDS_SEPARATOR . "'";
		switch ( $dbAuth->dbType() ) {
			case $dbAuth::DB_TYPE_MYSQL:
			default:
				$theSqlStr = '(SELECT'
					. " GROUP_CONCAT(__AuthTknsAlias.token SEPARATOR {$theSeparator})"
					. " FROM {$dbAuth->tnAuthTokens} AS __AuthTknsAlias"
					. " WHERE {$aAuthIDAlias}=__AuthTknsAlias.auth_id"
					. "   AND __AuthTknsAlias.token LIKE '" . $dbAuth::TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT . ":%'"
					. ") AS hardware_ids"
					;
		}//switch
		return $theSqlStr;
	}
	
}//end class

}//end namespace
