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
use BitsTheater\costumes\IDirected;
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

	/** @var string[] The list of fields "with_map_info" will expand to become. */
	static protected $mapInfoFields = array(
			'groups',
			'lockout_count',
			'hardware_ids',
	);
	
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
	 * Constructor for an AuthAccount entails a Model reference, a fieldset
	 * to return, and a set of options to load extra info.
	 * @param AuthModel $aDbModel - the db model to use.
	 * @param string[] $aFieldList - the field list to return.
	 * @param string[] $aOptions - (OPTIONAL) options like bLoadHardwareIDs=true.
	 */
	public function __construct($aDbModel, $aFieldList, $aOptions=null )
	{
		parent::__construct($aDbModel, $aFieldList);
		$this->parseOptions($aOptions);
	}
	
	/**
	 * Static helper function to create an instance of the record-wrapper
	 * class based on row data already retrieved and possibly causing
	 * additional data to be retrieved based on the field list passed in
	 * (such as loading extra mapping information or additional properties
	 * from additional tables).
	 * @param array|object $aRow - row data already fetched.
	 * @param AuthModel $aModel - the model instance.
	 * @param string[]|NULL $aFieldList - the list of fields to be exported.
	 * @param string[]|NULL $aOptions - the options.
	 * @return $this Returns the newly created instance.
	 */
	static public function withRow( $aRow, $aModel, $aFieldList, $aOptions )
	{
		$theClassName = get_called_class();
		$o = new $theClassName($aModel, $aFieldList, $aOptions);
		$o->setDataFrom($aRow);
		$o->onFetch();
		return $o;
	}
	
	/**
	 * Parse options array into various properties for our object.
	 * @param string[] $aOptions - options like ['bLoadHardwareIDs'=>true].
	 */
	protected function parseOptions( $aOptions )
	{
		if ( is_array($aOptions) ) {
			$this->bLoadAuthGroupInfo = !empty($aOptions['bLoadAuthGroupInfo']);
			$this->bLoadLockoutInfo = !empty($aOptions['bLoadLockoutInfo']);
			$this->bLoadHardwareIDs = !empty($aOptions['bLoadHardwareIDs']);
		}
	}
	
	/**
	 * Return the list of fields to restrict export to use given a list
	 * of fields and shorthand meta names or flags.
	 * @param string[] $aMetaFieldList - the field/meta name list.
	 * @return string[] Returns the export field name list to use.
	 */
	static public function getExportFieldListUsingShorthand( $aMetaFieldList )
	{
		$theFieldList = static::appendFieldListWithMapInfo($aMetaFieldList, static::$mapInfoFields);
		$theIndex = array_search('load_hardware_ids', $theFieldList);
		if ( $theIndex !== false ) {
			$theFieldList[$theIndex] = 'hardware_ids';
		}
		return $theFieldList;
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
		$theOptions = array();
		if ( in_array('groups', $aExportFieldList) ) {
			$theOptions['bLoadAuthGroupInfo'] = true;
		}
		if ( in_array('lockout_count', $aExportFieldList) ) {
			$theOptions['bLoadLockoutInfo'] = true;
		}
		if ( in_array('hardware_ids', $aExportFieldList) ) {
			$theOptions['bLoadHardwareIDs'] = true;
		}
		if ( !empty($aMetaOptions['load_hardware_ids']) ) {
			$theOptions['bLoadHardwareIDs'] = true;
		}
		return $theOptions;
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
			$o->is_locked = ( $o->lockout_count >= $this->getMyModel()->getMaxLoginAttempts() );
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
	{
		if ( empty($this->dbAuthGroups) ) {
			$this->dbAuthGroups = $this->getModel()->getProp('AuthGroups');
		}
		return $this->dbAuthGroups;
	}
	
	/**
	 * groups ID list was requested, this method fills in that property.
	 */
	protected function getGroupsList()
	{
		if ( !empty($this->account_id) ) try {
			//check to see if the initial SQL result has the data we need already
			if ( is_string($this->groups) ) {
				$this->groups = ( !empty($this->groups) ) ? explode(',', $this->groups) : null;
			}
			else {
				$this->groups = $this->getAuthGroupsProp()->getAcctGroups($this->account_id);
			}
			//if one of the groups has a numeric ID, convert them all to be int types
			if ( !empty($this->groups) && is_numeric($this->groups[0]) ) {
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
		if ( !empty($this->hardware_ids) ) {
			//convert string field to a proper list of items
			$theList = explode($this::HARDWARE_IDS_SEPARATOR, $this->hardware_ids);
			foreach ($theList as &$theToken) {
				list($thePrefix, $theHardwareId, $theUUID) = explode(':', $theToken);
				if ( !empty($thePrefix) && !empty($theHardwareId) && !empty($theUUID) ) {
					$theToken = $theHardwareId;
				}
			}
			//ensure it is just a string, not an array
			$this->hardware_ids = implode(',', $theList);
		}
		else {
			$this->hardware_ids = null;
		}
	}
	
	/**
	 * Lockout Count was requested, this method fills in that property.
	 */
	protected function getLockoutCount()
	{
		if ( is_numeric($this->lockout_count) ) {
			$this->lockout_count = intval($this->lockout_count);
		}
		else if ( !empty($this->auth_id) && !is_numeric($this->lockout_count) ) {
			$this->lockout_count = $this->getMyModel()->getAuthLockoutCount($this->auth_id);
		}
	}
	
	/**
	 * Hardware IDs were requested, this method fills in that property.
	 */
	protected function getHardwareIDs()
	{
		if ( !empty($this->auth_id) ) try {
			//check to see if the initial SQL result has the data we need already
			if ( is_string($this->hardware_ids) ) {
				$this->hardware_ids = ( !empty($this->hardware_ids) ) ? $this->hardware_ids : null;
				$this->parseHardwareIDs();
			}
			else {
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
					. " IFNULL(GROUP_CONCAT(__AuthTknsAlias.token SEPARATOR {$theSeparator}), '')"
					. " FROM {$dbAuth->tnAuthTokens} AS __AuthTknsAlias"
					. " WHERE {$aAuthIDAlias}=__AuthTknsAlias.auth_id"
					. "   AND __AuthTknsAlias.token LIKE '" . $dbAuth::TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT . ":%'"
					. ") AS hardware_ids"
					;
		}//switch
		return $theSqlStr;
	}
	
	/**
	 * Ensure we get the lockout count for an account.
	 * @param AuthModel $dbAuth - the auth model.
	 * @param string $aAuthIDAlias - the auth_id value to match against.
	 * @return string Returns the SQL used for defining the "lockout_count" field.
	 */
	static public function sqlForLockoutCount( $dbAuth, $aAuthIDAlias )
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
				$theSqlStr = "(SELECT count(*) FROM {$dbAuth->tnAuthTokens} AS __LockoutAlias"
					. " WHERE {$aAuthIDAlias}=__LockoutAlias.auth_id"
					. "   AND __LockoutAlias.token LIKE '" . $dbAuth::TOKEN_PREFIX_LOCKOUT . ":%'"
					. ") AS lockout_count"
					;
		}//switch
		return $theSqlStr;
	}
	
	/**
	 * Ensure we get the org list for an account.
	 * @param AuthGroupsModel $dbAuthGroups - the auth groups (roles) model.
	 * @param string $aAuthIDAlias - the auth_id value to match against.
	 * @return string Returns the SQL used for defining the "groups" field.
	 */
	static public function sqlForGroupList( $dbAuthGroups, $aAuthIDAlias )
	{
		if ( empty($dbAuthGroups) ) {
			throw new \InvalidArgumentException('$dbAuthGroups cannot be empty');
		}
		if ( empty($aAuthIDAlias) ) {
			throw new \InvalidArgumentException('$aAuthIDAlias cannot be empty');
		}
		switch ( $dbAuthGroups->dbType() ) {
			case $dbAuthGroups::DB_TYPE_MYSQL:
			default:
				$theSqlStr = '(SELECT' .
					" IFNULL(GROUP_CONCAT(__RoleMapAlias.group_id SEPARATOR ','), '')" .
					" FROM {$dbAuthGroups->tnGroupMap} AS __RoleMapAlias" .
					" WHERE {$aAuthIDAlias}=__RoleMapAlias.auth_id" .
					") AS groups" ;
		}//switch
		return $theSqlStr;
	}
	
}//end class

}//end namespace
