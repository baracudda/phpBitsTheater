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

namespace BitsTheater\models\PropCloset;
use BitsTheater\Model as BaseModel;
use BitsTheater\costumes\colspecs\CommonMySql;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\costumes\AuthGroup;
use BitsTheater\costumes\IDirected;
use BitsTheater\costumes\IFeatureVersioning;
use BitsTheater\costumes\ISqlSanitizer;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\WornForAuditFields;
use BitsTheater\costumes\WornForFeatureVersioning;
use BitsTheater\models\Auth as AuthDB;
use BitsTheater\outtakes\RightsException ;
use BitsTheater\BrokenLeg ;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Arrays;
use com\blackmoonit\Strings;
use PDOException;
use Exception;
{//begin namespace

/**
 * AuthGroups are the things that get permissions to which auth accounts
 * belong. AuthGroups may be defined heirarchically where decendants may
 * permit some actions a parent does not grant; but may NOT permit any
 * action a parent specifically denies. This is a revamp of BitsGroups
 * model so that IDs are UUID rather than INT.
 * @since v4.0.0
 */
class AuthGroups extends BaseModel implements IFeatureVersioning
{
	use WornForFeatureVersioning, WornForAuditFields;

	/**
	 * Used by meta data mechanism to keep the database up-to-date with the code.
	 * A non-NULL string value here means alter-db-schema needs to be managed.
	 * @var string
	 */
	const FEATURE_ID = 'BitsTheater/AuthGroups';
	/**
	 * The schema version for this model. Always increment this when making
	 * changes to the schema.
	 * <ol type="1">
	 *  <li value="1">
	 *   Initial schema design.
	 *  </li>
	 *  <li value="2">
	 *   Add org_id to tnGroups, replace Titan group with "org-parent", and
	 *   change group_num to be auto-inc just like account_id is defined.
	 *   Also, tnGroupRegCodes needs new indexes to make reg_code unique.
	 *  </li>
	 *  <li value="3">
	 *   Change group_num to be auto-inc if it is not already.
	 *   Not sure how this did not happen during v2.
	 *  </li>
	 * </ol>
	 * @var integer
	 */
	const FEATURE_VERSION_SEQ = 3; //always ++ when making db schema changes
	
	/**
	 * Add our database name before the defined table prefix so we can work
	 * with multiple databases at once.
	 * @var boolean This value is TRUE as the intention here is to work with multiple dbs.
	 */
	const TABLE_PREFIX_INCLUDES_DB_NAME = true;
	
	public $tnGroups;			const TABLE_Groups = 'authgroups';
	public $tnGroupMap;			const TABLE_GroupMap = 'authgroup_map';
	public $tnGroupRegCodes;	const TABLE_GroupRegCodes = 'authgroup_reg_codes';
	public $tnPermissions;		const TABLE_Permissions = 'authgroup_permissions';
	
	/** @var string The error code to notify callers to maybe check for migration. */
	const ERR_CODE_EMPTY_AUTHGROUP_TABLE = '6x9=42';
	
	/** @var string The ID of the "unregistered user" group. */
	const UNREG_GROUP_ID = 'UNKNOWN' ;
	
	/** @var string The DB value meaning "allow" is '+'. */
	const VALUE_Allow = '+';
	/** @var string The DB value meaning "forbid" is 'x'. */
	const VALUE_Deny = 'x';
	/**
	 * If the value is missing from TABLE_Permissions, then it also means
	 * VALUE_Disallow.
	 * @var string The DB value meaning "disallow" is '-'.
	 */
	const VALUE_Disallow = '-';
	
	/**
	 * The UI form value for "allow"
	 * @var string Defined as 'allow'
	 */
	const FORM_VALUE_Allow = 'allow';
	/**
	 * The UI form value for "forbid" which prevents child groups from allowing.
	 * @var string Defined as 'deny'
	 * @see AuthGroups::FORM_VALUE_DoNotShow
	 */
	const FORM_VALUE_Deny = 'deny';
	/**
	 * The UI form value for "not allowed"
	 * @var string Defined as 'disallow'
	 */
	const FORM_VALUE_Disallow = 'disallow';
	/**
	 * The UI form value for "parent forbids, do not display"
	 * @var string Defined as 'deny-disable'
	 */
	const FORM_VALUE_DoNotShow = 'deny-disable';
	/**
	 * The UI form value for "parent allows"
	 * @var string Defined as 'parent-allow'
	 */
	const FORM_VALUE_ParentAllowed = 'parent-allow';
	
	/** @var boolean Is schema latest version? */
	protected $bIsOrgColumnExists = true;
	
	/**
	 * Overridden method to handle additional logic after a successful
	 * database connection is made.
	 */
	public function setupAfterDbConnected()
	{
		parent::setupAfterDbConnected();
		$this->tnGroups = $this->tbl_ . self::TABLE_Groups;
		$this->tnGroupMap = $this->tbl_ . self::TABLE_GroupMap;
		$this->tnGroupRegCodes = $this->tbl_ . self::TABLE_GroupRegCodes;
		$this->tnPermissions = $this->tbl_ . self::TABLE_Permissions;
		$this->bIsOrgColumnExists = $this->isFieldExists('org_id', $this->tnGroups);
	}
	
	/**
	 * Overridden method that returns the SQL code needed to create the table as
	 * specified.
	 * @param string $aTableConst - the const name of a table to be defined.
	 * @param string $aTableNameOverride - (OPTIONAL) a custom name for the
	 *   table in case schema upgrades require cloning a table or two.
	 * @return string|NULL Return the SQL code to create the specified table.
	*/
	protected function getTableDefSql($aTableConst, $aTableNameOverride=null)
	{
		switch($aTableConst)
		{
			case self::TABLE_Groups:
				return $this->getTableDefSqlForGroups($aTableNameOverride);
			case self::TABLE_GroupMap:
				return $this->getTableDefSqlForGroupMap($aTableNameOverride);
			case self::TABLE_GroupRegCodes:
				return $this->getTableDefSqlForGroupRegCodes($aTableNameOverride);
			case self::TABLE_Permissions:
				return $this->getTableDefSqlForPermissions($aTableNameOverride);
		}
	}

	/**
	 * Returns the SQL code to create the "groups" table.
	 * Called by getTableDefSql().
	 * @param string $aTableNameOverride - (OPTIONAL) a custom name for the
	 *   table in case schema upgrades require cloning a table or two.
	 * @return string Returns the SQL code to create the table.
	 */
	protected function getTableDefSqlForGroups($aTableNameOverride=null)
	{
			$theTableName = $this->tnGroups;
			if ( !empty($aTableNameOverride) )
			{ $theTableName = $aTableNameOverride; }
			switch ( $this->dbType() ) {
				case self::DB_TYPE_MYSQL:
				default:
					return "CREATE TABLE IF NOT EXISTS {$theTableName} " .
							'( group_id ' . CommonMySQL::TYPE_UUID . ' NOT NULL' .
							', group_num INT NOT NULL AUTO_INCREMENT' . " COMMENT 'user-friendly ID'" .
							', group_name VARCHAR(60) NOT NULL' .
							', parent_group_id ' . CommonMySql::TYPE_UUID . ' NULL' .
							', org_id ' . CommonMySql::TYPE_UUID . ' NULL' .
							', ' . CommonMySQL::getAuditFieldsForTableDefSql() .
							', PRIMARY KEY (group_id)' .
							', KEY (parent_group_id)' .
							', KEY (org_id)' .
							', UNIQUE KEY (group_num)' .
							') ' . CommonMySQL::TABLE_SPEC_FOR_UNICODE;
			}//switch dbType
	}
	
	/**
	 * Returns the SQL code to create the "group_map" table.
	 * Called by getTableDefSql().
	 * @param string $aTableNameOverride - (OPTIONAL) a custom name for the
	 *   table in case schema upgrades require cloning a table or two.
	 * @return string Returns the SQL code to create the table.
	 */
	protected function getTableDefSqlForGroupMap($aTableNameOverride=null)
	{
		$theTableName = $this->tnGroupMap;
		if ( !empty($aTableNameOverride) )
		{ $theTableName = $aTableNameOverride; }
		switch ( $this->dbType() ) {
			case self::DB_TYPE_MYSQL:
			default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} " .
						'( auth_id ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', group_id ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', ' . CommonMySQL::getAuditFieldsForTableDefSql() .
						', PRIMARY KEY (auth_id, group_id)' .
						', UNIQUE KEY (group_id, auth_id)' .
						')';
		}//switch dbType
	}

	/**
	 * Returns the SQL code to create the "group_reg_codes" table.
	 * Called by getTableDefSql().
	 * @param string $aTableNameOverride - (OPTIONAL) a custom name for the
	 *   table in case schema upgrades require cloning a table or two.
	 * @return string Returns the SQL code to create the table.
	 */
	protected function getTableDefSqlForGroupRegCodes($aTableNameOverride=null)
	{
		$theTableName = $this->tnGroupRegCodes;
		if ( !empty($aTableNameOverride) )
		{ $theTableName = $aTableNameOverride; }
		switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} " .
						'( group_id ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', reg_code VARCHAR(64) NOT NULL' .
						', ' . CommonMySQL::getAuditFieldsForTableDefSql() .
						', PRIMARY KEY (reg_code)' .
						', KEY (group_id, created_ts)' .
						') ' . CommonMySQL::TABLE_SPEC_FOR_UNICODE .
						" COMMENT='Auto-assign group_id if Registration Code matches reg_code'";
		}//switch dbType
	}
	
	/**
	 * Returns the SQL code to create the "permissions" table.
	 * Called by getTableDefSql().
	 * @param string $aTableNameOverride - (OPTIONAL) a custom name for the
	 *   table in case schema upgrades require cloning a table or two.
	 * @return string Returns the SQL code to create the table.
	 */
	protected function getTableDefSqlForPermissions($aTableNameOverride=null)
	{
		$theTableName = $this->tnPermissions;
		if ( !empty($aTableNameOverride) )
		{ $theTableName = $aTableNameOverride; }
		switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						'( namespace VARCHAR(40) NOT NULL' .
						', permission VARCHAR(40) NOT NULL' .
						', group_id ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', value '. CommonMySql::TYPE_ASCII_CHAR(1) . ' NOT NULL' .
						', ' . CommonMySQL::getAuditFieldsForTableDefSql() .
						', PRIMARY KEY (namespace, permission, group_id)' .
						', UNIQUE KEY IdxGroupPermissions (group_id, namespace, permission)' .
						') ' . CommonMySQL::TABLE_SPEC_FOR_UNICODE;
		}//switch dbType
	}

	/**
	 * Called during website installation and db re-setupDb feature.
	 * Never assume the database is empty.
	 */
	public function setupModel()
	{
		$this->setupTable( self::TABLE_Groups, $this->tnGroups ) ;
		$this->setupTable( self::TABLE_GroupMap, $this->tnGroupMap ) ;
		$this->setupTable( self::TABLE_GroupRegCodes, $this->tnGroupRegCodes ) ;
		$this->setupTable( self::TABLE_Permissions, $this->tnPermissions ) ;
	}

	/**
	 * Constructs the group information for insertion into the database.
	 * @param array $aAuditFields - A map of audit fields for the row.
	 * @see AuthGroups::setupDefaultDataForGroups()
	 */
	protected function buildDefaultGroupDataArray( $aAuditFields )
	{
		$theGroupNames = $this->getRes('AuthGroups/group_names');
		$theDefaultData = array() ;
		$theIdx = 0 ;
		foreach( $theGroupNames as $theGroupName )
		{ // Construct value set for each row with group data and audit fields.
			switch ( $theIdx ) {
				case 0:
					$theGroupID = static::UNREG_GROUP_ID;
					$theGroupNum = count($theGroupNames);
					break;
				default:
					$theGroupID = Strings::createUUID();
					$theGroupNum = $theIdx;
			}//end switch
			$theDefaultData[$theIdx++] = array_merge( $aAuditFields, array(
					'group_id' => $theGroupID,
					'group_num' => $theGroupNum,
					'group_name' => $theGroupName,
			) );
		}
		return $theDefaultData ;
	}
	
	/**
	 * If the groups table is empty, supply some default starter-data.
	 * @param object $aScene - (optional) extra data may be supplied
	 * @return array Return the default data added, indexed by group_num.
	 */
	protected function setupDefaultDataForAuthGroups( $aScene=null )
	{
		if ( $this->isEmpty($this->tnGroups) ) {
			// Start building the query until we have the audit field values...
			$theSql = SqlBuilder::withModel($this);
			$theSql->startWith('INSERT INTO')->add($this->tnGroups);
			$this->setAuditFieldsOnInsert($theSql);
			$theSql->mustAddParam( 'group_id', '__PLACEHOLDER__' );
			$theSql->mustAddParam( 'group_num', '__PLACEHOLDER__' );
			$theSql->mustAddParam( 'group_name', '__PLACEHOLDER__' );
			
			// Now use this to construct each of the rows of group data.
			// This works because audit fields are the only params set in the
			// SqlBuilder, from setAuditFieldsOnInsert().
			$theDefaultData =
					$this->buildDefaultGroupDataArray( $theSql->myParams ) ;

			try
			{
				$theSql->execMultiDML( $theDefaultData );
				$this->fixGroupNum0AfterInsert($theDefaultData[0]['group_id']);
			}
			catch (PDOException $pdox)
			{ throw $theSql->newDbException(__METHOD__, $pdox); }
			return $theDefaultData;
		}
	}
	
	/**
	 * Since group_num is an auto-inc field, 0 might get auto-changed to be
	 * the next auto-inc value instead of 0, which is what we actually want.
	 * UPDATE should fix that for us.
	 * @param string $aGroupID - the group ID to fix.
	 * @return $this Returns $this for chaining.
	 * @throws PDOException if a problem occurs.
	 */
	protected function fixGroupNum0AfterInsert( $aGroupID )
	{
		switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL:
			default:
				SqlBuilder::withModel($this)
					->startWith('UPDATE')->add($this->tnGroups)
					->add('SET')
					->mustAddParam('group_num', 0, \PDO::PARAM_INT)
					->startWhereClause()
					->mustAddParam('group_id', $aGroupID)
					->endWhereClause()
					->execDML()
				;
		}//switch
		return $this;
	}
	
	/**
	 * If the group registration code table is empty, add the site ID to admin.
	 * @param array $aAuthGroups - the auth groups added.
	 */
	protected function setupDefaultDataForAuthGroupRegCodes( $aAuthGroups )
	{
		if ( $this->isEmpty($this->tnGroupRegCodes) && !empty($aAuthGroups) ) {
			// Start building the query until we have the audit field values...
			$theSql = SqlBuilder::withModel($this);
			$theSql->startWith('INSERT INTO')->add($this->tnGroupRegCodes);
			$this->setAuditFieldsOnInsert($theSql);
			//admin group defaults to the site ID on initial install
			$theSql->mustAddParam('group_id', $aAuthGroups[2]['group_id']);
			$theSql->mustAddParam('reg_code', $this->getDirector()->app_id);
			try
			{ $theSql->execDML(); }
			catch (PDOException $pdox)
			{ throw $theSql->newDbException(__METHOD__, $pdox); }
		}
		
	}
	
	/**
	 * Return an array that would grant all rights for a given namespace to a
	 * particular auth group.
	 * @param string $aNamespace - the namespace to use.
	 * @param string $aGroupID - the ID to assign rights to.
	 * @param string $aRightValue - (OPTIONAL) default to Allow, but can
	 *   supply Disallow or Deny, if desired.
	 * @param string[] $aSkipPermissionList - (OPTIONAL) a list of permissions
	 *   to skip assignment.
	 * @return array Returns 2D array of permission rows to insert.
	 */
	protected function assignAllRightsInANamespace($aNamespace, $aGroupID,
			$aRightValue=null, $aSkipPermissionList=null)
	{
		$theResults = array();
		if ( empty($aRightValue) )
		{ $aRightValue = static::VALUE_Allow; }
		$thePerms = $this->getRes( 'permissions', $aNamespace );
		foreach ( $thePerms as $thePerm => $thePermInfo ) {
			if ( empty($aSkipPermissionList) ||
					!in_array($thePerm, $aSkipPermissionList) )
			{
				$theResults[] = array(
						'namespace' => $aNamespace,
						'permission' => $thePerm,
						'group_id' => $aGroupID,
						'value' => $aRightValue,
				);
			}
		}
		return $theResults;
	}
	
	/**
	 * Default rights for a guest account.
	 * @param string $aGroupID - the group ID to use.
	 * @return array Returns 2D array of permission rows to insert.
	 */
	protected function getDefaultRightsForGuest( $aGroupID )
	{
		$theResults = array();
		return $theResults;
	}
	
	/**
	 * Default rights for a sub-org account.
	 * @param string $aGroupID - the group ID to use.
	 * @return array Returns 2D array of permission rows to insert.
	 */
	protected function getDefaultRightsForOrgParent( $aGroupID )
	{
		$theResults = array();
		$theNamespaceList = $this->getRes('permissions', 'namespace');
		foreach ( $theNamespaceList as $theNS => $theNSInfo ) {
			switch ( $theNS ) {
				case 'auth_orgs':
				case 'config':
					$theResults = array_merge( $theResults,
							$this->assignAllRightsInANamespace($theNS,
									$aGroupID, static::VALUE_Deny)
					);
					break;
				default:
			}//switch
		}//foreach
		return $theResults;
	}
	
	/**
	 * Default rights for an admin account.
	 * @param string $aGroupID - the group ID to use.
	 * @return array Returns 2D array of permission rows to insert.
	 */
	protected function getDefaultRightsForAdmin( $aGroupID )
	{
		$theResults = array();
		$theNamespaceList = $this->getRes('permissions', 'namespace');
		foreach ( $theNamespaceList as $theNS => $theNSInfo ) {
			$theResults = array_merge( $theResults,
					$this->assignAllRightsInANamespace($theNS, $aGroupID)
			);
		}
		return $theResults;
	}
	
	/**
	 * Default rights for a privileged account.
	 * @param string $aGroupID - the group ID to use.
	 * @return array Returns 2D array of permission rows to insert.
	 */
	protected function getDefaultRightsForPrivileged( $aGroupID )
	{
		$theResults = array();
		return $theResults;
	}
	
	/**
	 * Default rights for a restricted account.
	 * @param string $aGroupID - the group ID to use.
	 * @return array Returns 2D array of permission rows to insert.
	 */
	protected function getDefaultRightsForRestricted( $aGroupID )
	{
		$theResults = array();
		return $theResults;
	}
	
	/**
	 * When tables are created, default data may be needed in them. Check
	 * the table(s) for isEmpty() before filling it with default data.
	 * @param array $aAuthGroups - the auth groups added.
	 */
	protected function setupDefaultDataForAuthPermissions( $aAuthGroups )
	{
		//only want default data if the table is empty
		if ( $this->isEmpty($this->tnPermissions) ) {
			$this->insertDataForAuthPermissionGroup(
					$this->getDefaultRightsForGuest($aAuthGroups[0]['group_id'])
			);
			$this->insertDataForAuthPermissionGroup(
					$this->getDefaultRightsForOrgParent($aAuthGroups[1]['group_id'])
			);
			$this->insertDataForAuthPermissionGroup(
					$this->getDefaultRightsForAdmin($aAuthGroups[2]['group_id'])
			);
			$this->insertDataForAuthPermissionGroup(
					$this->getDefaultRightsForPrivileged($aAuthGroups[3]['group_id'])
			);
			$this->insertDataForAuthPermissionGroup(
					$this->getDefaultRightsForRestricted($aAuthGroups[4]['group_id'])
			);
			$this->logStuff(__METHOD__, ' Default permissions assigned.');
		}
	}
	
	/**
	 * When tables are created, default data may be needed in them. Check
	 * the table(s) for isEmpty() before filling it with default data.
	 * @param object $aScene - (optional) extra data may be supplied
	 */
	public function setupDefaultData( $aScene=null )
	{
		//only want default data if the groups table is empty
		if ( $this->isEmpty($this->tnGroups) )
		{
			$theAuthGroups = $this->setupDefaultDataForAuthGroups($aScene);
			$this->setupDefaultDataForAuthGroupRegCodes($theAuthGroups);
			$this->setupDefaultDataForAuthPermissions($theAuthGroups);
		}
	}
	
	/**
	 * Setup the default auth group data for a new org.
	 * @param string $aOrgID - the new org ID.
	 */
	public function setupDefaultDataForNewOrg( $aOrgData )
	{
		$theGroupNames = $this->getRes('AuthGroups/group_names');
		$theSql = SqlBuilder::withModel($this)
			->startWith('INSERT INTO')->add($this->tnGroups);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('group_id')
			->mustAddParam('group_name')
			->mustAddParam('parent_group_id')
			->mustAddParam('org_id')
			//->logSqlDebug(__METHOD__, 'DEBUG')
		;
		$theDefaultData = array() ;
		$theIdx = 0;
		foreach ($theGroupNames as $theGroupName)
		{
			//avoid "guest" and "org parent" default roles
			if ( $theIdx++ < 2 ) continue;
			$theDefaultData[$theIdx-1] = array_merge($theSql->myParams, array(
					'group_id' => Strings::createUUID(),
					'group_name' => $theGroupName,
					'parent_group_id' => $aOrgData['parent_authgroup_id'],
					'org_id' => $aOrgData['org_id'],
			));
		}
		try
		{ $theSql->execMultiDML($theDefaultData); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
		//now that we have the default groups, define their permissions.
		$this->insertDataForAuthPermissionGroup(
				$this->getDefaultRightsForAdmin($theDefaultData[2]['group_id'])
		);
		$this->insertDataForAuthPermissionGroup(
				$this->getDefaultRightsForPrivileged($theDefaultData[3]['group_id'])
		);
		$this->insertDataForAuthPermissionGroup(
				$this->getDefaultRightsForRestricted($theDefaultData[4]['group_id'])
		);
	}

	/**
	 * Overridden method that returns the existing feature version for this
	 * model. Other models may need to query ours to determine our version
	 * number during Site Update. Without checking SetupDb, this method
	 * determines what version we may be running as.
	 * @param \BitsTheater\Scene $aScene - (optional) extra context may be supplied.
	 * @return integer - the current version number.
	 */
	public function determineExistingFeatureVersion($aScene)
	{
		if ( !$this->exists($this->tnGroups) )
		{ return 0; }
		if ( !$this->exists($this->tnGroupMap) )
		{ return 0; }
		if ( !$this->exists($this->tnGroupRegCodes) )
		{ return 0; }
		if ( !$this->exists($this->tnPermissions) )
		{ return 0; }
		switch ($this->dbType())
		{
			case self::DB_TYPE_MYSQL:
			default:
				if ( !$this->isFieldExists('org_id', $this->tnGroups) )
				{ return 1; }
				$bIsV2 = filter_var(
						$this->describeColumn(
								$this->tnGroups, 'group_num'
						)->IS_NULLABLE, FILTER_VALIDATE_BOOLEAN
				);
				if ( $bIsV2 )
				{ return 2; }
				
		}
		return self::FEATURE_VERSION_SEQ;
	}

	
	/**
	 * Check current feature version and compare it to the
	 * current version, upgrading the db schema as needed.
	 * @param array $aFeatureMetaData - the models current feature metadata.
	 * @param object $aScene - (optional) extra data may be supplied
	 */
	public function upgradeFeatureVersion($aFeatureMetaData, $aScene)
	{
		$theSeq = $aFeatureMetaData['version_seq'];
		$this->logStuff('Running ', __METHOD__, ' v'.$theSeq.'->v'.self::FEATURE_VERSION_SEQ);

		// This switch block's cases are tests against the current version
		// sequence number. The cases must be implemented sequentially (low to
		// high) and not use break, so that all changes between versions will
		// fall through cumulatively to the end.
		switch (true) {
			case ( $theSeq < 1 ):
			{
				// In case we drop the table and then run actionWebsiteUpgrade.php
				// We detect the missing table by setting $theSeq to 0 in
				//   determineExistingFeatureVersion. Here we just re-create the feature.
				$this->setupModel();
				// Existing website might be using older BitsGroups model, migrate the data.
				$this->migrateFromBitsGroups();
				// Since setupModel() will create the latest version of the feature,
				//   there is no need to run through the rest of the version updates.
				break;
				// For every other $theSeq case, it needs to fall through to the next.
			}
			case ( $theSeq < 2 ):
				$this->upgradeFeatureVersionTo2();
			case ( $theSeq < 3 ):
			{
				//for some reason or other, the v2 upgrade code may not have
				//  actually changed the group_num column to be NON NULL and
				//  auto-inc.  So v3 will just focus on fixing that.
				$bIsV2 = filter_var(
						$this->describeColumn(
								$this->tnGroups, 'group_num'
						)->IS_NULLABLE, FILTER_VALIDATE_BOOLEAN
				);
				//$this->logStuff(__METHOD__, $bIsV2?'v2':'v3+');//DEBUG
				$theSql = SqlBuilder::withModel($this);
				if ( $bIsV2 ) try {
					$theAutoInc = $theSql->reset()
						->startWith('SELECT MAX(group_num)')
						->add('FROM')->add($this->tnGroups)
						->query()->fetch(\PDO::FETCH_COLUMN)
						;
					$theAutoInc += 1; //we want to start with the next val
					//existing NULLs must be hand-updated else DUPLICATE key errors
					$theSql->reset()
						->startWith('UPDATE')->add($this->tnGroups)
						->add('SET group_num=')
						->add('(SELECT @row := @row + 1 as group_num')
						->add(" FROM (SELECT @row := {$theAutoInc}) AS c")
						->add(')')
						->startWhereClause()
						->mustAddParam('group_num', null)
						->endWhereClause()
						->applyOrderByList(array(
								'updated_ts' => SqlBuilder::ORDER_BY_ASCENDING
						))
						//->logSqlDebug(__METHOD__) //DEBUG
						->execDML()
						;
					$this->logStuff('v3: ensure group_num not null and unique.');
					$theSql->reset()
						->startWith('ALTER TABLE')->add($this->tnGroups)
						->add('MODIFY')->add('`group_num` INT NOT NULL AUTO_INCREMENT')
						->add(" COMMENT 'user-friendly ID'")
						//->logSqlDebug(__METHOD__) //DEBUG
						->execDML()
						;
					$this->logStuff('v3: group_num NOT NULL and auto-inc');
					//since group_num is an auto-inc field, 0 might get
					//  auto-changed to be the next auto-inc value instead
					//  of 0, which is what we actually want. UPDATE should
					//  fix that for us.
					$theDefaultData =
							$this->buildDefaultGroupDataArray(array());
					$theSql->reset()
						->startWith('UPDATE')->add($this->tnGroups)
						->add('SET')
						->mustAddParam('group_num', 0, \PDO::PARAM_INT)
						->startWhereClause()
						->mustAddParam('group_id', $theDefaultData[0]['group_id'])
						->endWhereClause()
						->execDML()
					;
					$this->logStuff('v3: reset group_num 0 back to 0.');
					$theAutoInc = $theSql->reset()
						->startWith('SELECT MAX(group_num)')
						->add('FROM')->add($this->tnGroups)
						->query()->fetch(\PDO::FETCH_COLUMN)
						;
					$theAutoInc += 1; //we want to start with the next val
					$theSql->reset()
						->startWith('ALTER TABLE')->add($this->tnGroups)
						->add('AUTO_INCREMENT=' . $theAutoInc)
						//->logSqlDebug(__METHOD__) //DEBUG
						->execDML()
						;
					$this->logStuff('v3: group_num auto-inc should now start at ['
							. $theAutoInc . ']'
					);
				}
				catch ( \PDOException $pdox ) {
					throw $theSql->newDbException('v3 failed to update', $pdox);
				}
				$this->logStuff('v3: finished updating.');
			}
			case ( $theSeq < 4 ):
			{
				// Next version's changes go here.
			}
		}//switch
	}
	
	/**
	 * Use of this model might require migrating an existing BitsGroups model data.
	 * Based on AuthBasic models at the time of pre-v4.0.0 framework.
	 */
	protected function migrateFromBitsGroups()
	{
		$theTaskText = 'migrating schema to v4.x AuthGroups model';
		$this->logStuff(__METHOD__, ' ', $theTaskText);
		$this->migrateFromBitsGroupsToAuthGroups();
		$this->migrateFromBitsGroupMapToAuthGroupMap();
		$this->migrateFromBitsGroupRegCodesToAuthGroupRegCodes();
		$this->migrateFromBitsPermissionsToAuthGroupPermissions();
		$this->migrateTitanRoleToAdmin();
		$this->migrateToAuthGroupsComplete();
		$this->logStuff(__METHOD__, ' FINISHED ', $theTaskText);
	}
	
	/**
	 * Use of this model might require migrating an existing BitsGroups model data.
	 * This method should be executed first in such a migration to generate UUIDs
	 * and map back to the old INT values.
	 */
	protected function migrateFromBitsGroupsToAuthGroups()
	{
		/* @var $dbOldAuthGroups \BitsTheater\models\PropCloset\BitsGroups */
		$dbOldAuthGroups = $this->getProp(
				'\BitsTheater\models\PropCloset\BitsGroups'
		);
		if ( $this->isEmpty($this->tnGroups) &&
				$dbOldAuthGroups->exists($dbOldAuthGroups->tnGroups) &&
				!$dbOldAuthGroups->isEmpty($dbOldAuthGroups->tnGroups)
		) try {
			$this->logStuff(' migrating from ', $dbOldAuthGroups->tnGroups,
					' to ', $this->tnGroups);
			$theSql = SqlBuilder::withModel($dbOldAuthGroups);
			$theSql->startWith('SELECT * FROM')->add($dbOldAuthGroups->tnGroups);
			$ps = $theSql->query();
			$theIDList = array();
			for( $theItem = $ps->fetch() ; $theItem !== false ; $theItem = $ps->fetch() )
			{
				//group_name
				$theNewRow = array(
						'group_name' => $theItem['group_name'],
						'created_by' => $theItem['created_by'],
						'created_ts' => $theItem['created_ts'],
						'updated_by' => $theItem['updated_by'],
						'updated_ts' => $theItem['updated_ts'],
				);
				//parent_group_id
				if ( isset($theItem['parent_group_id']) &&
						$theItem['parent_group_id'] > 0 )
				{
					if ( !array_key_exists($theItem['parent_group_id'], $theIDList) )
					{
						$theIDList[$theItem['parent_group_id']] = array(
								'old_id' => $theItem['parent_group_id'],
								'new_id' => Strings::createUUID(),
						);
					}
					$theNewRow['parent_group_id'] =
							$theIDList[$theItem['parent_group_id']]['new_id'];
				}
				//group_id
				switch ( $theItem['group_id'] )
				{
					case $dbOldAuthGroups::UNREG_GROUP_ID:
						$theNewRow['group_id'] = static::UNREG_GROUP_ID;
						break;
					default:
						if ( !array_key_exists($theItem['group_id'], $theIDList) )
						{
							$theIDList[$theItem['group_id']] = array(
								'old_id' => $theItem['group_id'],
								'new_id' => Strings::createUUID(),
							);
						}
						$theNewRow['group_id'] = $theIDList[$theItem['group_id']]['new_id'];
				}//end switch
				//group_num
				if ( $theItem['group_id'] == $dbOldAuthGroups::UNREG_GROUP_ID ) {
					$theNewRow['group_num'] = -1; //0 will cause auto-inc field to put "next" val
				}
				else {
					$theNewRow['group_num'] = $theItem['group_id'];
				}
				
				$this->add($theNewRow);
				//if we were forced to use -1 instead of 0, fix it.
				if ( $theNewRow['group_num'] == -1 ) {
					$this->fixGroupNum0AfterInsert($theNewRow['group_id']);
				}
			}
			$this->logStuff(' migrated to ', $this->tnGroups);
		}
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Use of this model might require migrating an existing BitsGroups model data.
	 */
	protected function migrateFromBitsGroupMapToAuthGroupMap()
	{
		/* @var $dbOldAuthGroups \BitsTheater\models\PropCloset\BitsGroups */
		$dbOldAuthGroups = $this->getProp(
				'\BitsTheater\models\PropCloset\BitsGroups'
		);
		if ( $this->isEmpty($this->tnGroupMap) &&
				$dbOldAuthGroups->exists($dbOldAuthGroups->tnGroupMap) &&
				!$dbOldAuthGroups->isEmpty($dbOldAuthGroups->tnGroupMap)
		) try {
			$this->logStuff(' migrating from ', $dbOldAuthGroups->tnGroupMap,
					' to ', $this->tnGroupMap);
			//the "old->new ID" re-usable query
			$theIdSql = SqlBuilder::withModel($this)
					->startWith('SELECT group_id FROM')->add($this->tnGroups)
					->startWhereClause()
					->mustAddParam('group_num', 'oldID')
					->endWhereClause()
					;
			/* @var $dbAuth \BitsTheater\models\Auth */
			$dbAuth = $this->getProp('Auth');
			//now to go through the map table
			$theSql = SqlBuilder::withModel($dbOldAuthGroups);
			$theSql->startWith('SELECT * FROM')->add($dbOldAuthGroups->tnGroupMap);
			$ps = $theSql->query();
			for( $theItem = $ps->fetch() ; $theItem !== false ; $theItem = $ps->fetch() )
			{
				$theNewRow = array(
						'created_by' => $theItem['created_by'],
						'created_ts' => $theItem['created_ts'],
						'updated_by' => $theItem['updated_by'],
						'updated_ts' => $theItem['updated_ts'],
				);
				//auth_id
				$theNewRow['auth_id'] = $dbAuth->getAuthByAccountId(
						$theItem['account_id']
				)['auth_id'];
				//group_id
				switch ( $theItem['group_id'] )
				{
					case $dbOldAuthGroups::UNREG_GROUP_ID:
						$theNewRow['group_id'] = static::UNREG_GROUP_ID;
						break;
					default:
						$theMigrateIdRow = $theIdSql->setParam('group_num', $theItem['group_id'])
							->getTheRow();
						$theNewRow['group_id'] = $theMigrateIdRow['group_id'];
				}//end switch
				//$this->logStuff(__METHOD__, ' ', $theNewRow); //DEBUG
				if ( !empty($theNewRow['auth_id']) )
					$this->addMap($theNewRow['group_id'], $theNewRow['auth_id']);
				else
					$this->logStuff(__METHOD__, ' unknown account_id, cannot migrate ',
							$theItem
					);
			}
			$this->logStuff(' migrated to ', $this->tnGroupMap);
		}
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Use of this model might require migrating an existing BitsGroups model data.
	 */
	protected function migrateFromBitsGroupRegCodesToAuthGroupRegCodes()
	{
		/* @var $dbOldAuthGroups \BitsTheater\models\PropCloset\BitsGroups */
		$dbOldAuthGroups = $this->getProp(
				'\BitsTheater\models\PropCloset\BitsGroups'
		);
		if ( $this->isEmpty($this->tnGroupRegCodes) &&
				$dbOldAuthGroups->exists($dbOldAuthGroups->tnGroupRegCodes) &&
				!$dbOldAuthGroups->isEmpty($dbOldAuthGroups->tnGroupRegCodes)
		) try {
			$this->logStuff(' migrating from ', $dbOldAuthGroups->tnGroupRegCodes,
					' to ', $this->tnGroupRegCodes);
			$theIdSql = SqlBuilder::withModel($this)
					->startWith('SELECT group_id FROM')->add($this->tnGroups)
					->startWhereClause()
					->mustAddParam('group_num', 'oldID')
					->endWhereClause()
					;
			$theSql = SqlBuilder::withModel($dbOldAuthGroups);
			$theSql->startWith('SELECT * FROM')->add($dbOldAuthGroups->tnGroupRegCodes);
			$ps = $theSql->query();
			for( $theItem = $ps->fetch() ; $theItem !== false ; $theItem = $ps->fetch() )
			{
				$theNewRow = array(
						'reg_code' => $theItem['reg_code'],
						'created_by' => $theItem['created_by'],
						'created_ts' => $theItem['created_ts'],
						'updated_by' => $theItem['updated_by'],
						'updated_ts' => $theItem['updated_ts'],
				);
				//group_id
				switch ( $theItem['group_id'] )
				{
					case $dbOldAuthGroups::UNREG_GROUP_ID:
						$theNewRow['group_id'] = static::UNREG_GROUP_ID;
						break;
					default:
						$theMigrateIdRow = $theIdSql->setParam('group_num', $theItem['group_id'])
							->getTheRow();
						$theNewRow['group_id'] = $theMigrateIdRow['group_id'];
				}//end switch
				$this->insertGroupRegCode($theNewRow['group_id'], $theNewRow['reg_code']);
			}
			$this->logStuff(' migrated to ', $this->tnGroupRegCodes);
		}
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Use of this model might require migrating an existing BitsGroups model data.
	 */
	protected function migrateFromBitsPermissionsToAuthGroupPermissions()
	{
		/* @var $dbOldAuthGroups \BitsTheater\models\PropCloset\BitsGroups */
		$dbOldAuthGroups = $this->getProp(
				'\BitsTheater\models\PropCloset\BitsGroups'
		);
		/* @var $dbOldPermissions \BitsTheater\models\PropCloset\AuthPermissions */
		$dbOldPermissions = $this->getProp(
				'\BitsTheater\models\PropCloset\AuthPermissions'
		);
		if ( $this->isEmpty($this->tnPermissions) &&
				$dbOldPermissions->exists($dbOldPermissions->tnPermissions) &&
				!$dbOldPermissions->isEmpty($dbOldPermissions->tnPermissions)
		) try {
			$this->logStuff(' migrating from ', $dbOldPermissions->tnPermissions,
					' to ', $this->tnPermissions);
			$theIdSql = SqlBuilder::withModel($this)
				->startWith('SELECT group_id FROM')->add($this->tnGroups)
				->startWhereClause()
				->mustAddParam('group_num', 'oldID')
				->endWhereClause()
				;
			$theAddSql = SqlBuilder::withModel($this)
				->startWith('INSERT INTO')->add($this->tnPermissions);
			$this->setAuditFieldsOnInsert($theAddSql)
				->mustAddParam('namespace', '__placeolder__')
				->mustAddParam('permission', '__placeolder__')
				->mustAddParam('group_id', '__placeolder__')
				->mustAddParam('value', '__placeolder__')
				;
			$theSql = SqlBuilder::withModel($dbOldPermissions);
			$theSql->startWith('SELECT * FROM')->add($dbOldPermissions->tnPermissions);
			$ps = $theSql->query();
			for( $theItem = $ps->fetch() ; $theItem !== false ; $theItem = $ps->fetch() )
			{
				$theAddSql->setParam('namespace', $theItem['namespace'])
					->setParam('permission', $theItem['permission'])
					->setParam('value', $theItem['value'])
					;
				//group_id
				switch ( $theItem['group_id'] )
				{
					case $dbOldAuthGroups::UNREG_GROUP_ID:
						$theAddSql->setParam('group_id', static::UNREG_GROUP_ID);
						break;
					default:
						$theMigrateIdRow = $theIdSql->setParam('group_num', $theItem['group_id'])
							->getTheRow();
						$theAddSql->setParam('group_id', $theMigrateIdRow['group_id']);
				}//end switch
				$theAddSql->execDML();
			}
			$this->logStuff(' migrated to ', $this->tnGroupRegCodes);
		}
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Use of this model might require migrating an existing BitsGroups model data.
	 */
	protected function migrateToAuthGroupsComplete()
	{
		/* @var $dbOldAuthGroups \BitsTheater\models\PropCloset\BitsGroups */
		$dbOldAuthGroups = $this->getProp(
				'\BitsTheater\models\PropCloset\BitsGroups'
		);
		/* @var $dbOldPermissions \BitsTheater\models\PropCloset\AuthPermissions */
		$dbOldPermissions = $this->getProp(
				'\BitsTheater\models\PropCloset\AuthPermissions'
		);
		//remove old feature record
		/* @var $dbMeta SetupDb */
		$dbMeta = $this->getProp('SetupDb');
		$dbMeta->removeFeature($dbOldAuthGroups::FEATURE_ID);
		$this->logStuff(' removed ', $dbOldAuthGroups::FEATURE_ID,
				' from ', $dbMeta->tnSiteVersions);
		//$dbOldPermissions did not have a FEATURE_ID to remove.
		$this->returnProp($dbMeta);
		//remove old tables
		$theSql = SqlBuilder::withModel($dbOldPermissions)
			->startWith('DROP TABLE')->add($dbOldPermissions->tnPermissions)
			->execDML()
			;
		$this->logStuff(' dropped old table ', $dbOldPermissions->tnPermissions);
		$theSql = SqlBuilder::withModel($dbOldAuthGroups)
			->startWith('DROP TABLE')->add($dbOldAuthGroups->tnGroupRegCodes)
			->execDML()
			;
		$this->logStuff(' dropped old table ', $dbOldAuthGroups->tnGroupRegCodes);
		$theSql = SqlBuilder::withModel($dbOldAuthGroups)
			->startWith('DROP TABLE')->add($dbOldAuthGroups->tnGroupMap)
			->execDML()
			;
		$this->logStuff(' dropped old table ', $dbOldAuthGroups->tnGroupMap);
		$theSql = SqlBuilder::withModel($dbOldAuthGroups)
			->startWith('DROP TABLE')->add($dbOldAuthGroups->tnGroups)
			->execDML()
			;
		$this->logStuff(' dropped old table ', $dbOldAuthGroups->tnGroups);
	}
	
	protected function upgradeFeatureVersionTo2ForRegCodes()
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith(CommonMySql::getIndexDefinitionSql($this->tnGroupRegCodes))
			->startWhereClause()
			->mustAddParam('Seq_in_index', 1, \PDO::PARAM_INT)
			->endWhereClause()
		;
		$theIndexRows = $theSql->query()->fetchAll();
		$theIndexNames = Arrays::array_column($theIndexRows, 'Key_name');
		//add a unique key to see if it works first
		$theSql = SqlBuilder::withModel($this)
			->startWith('ALTER TABLE')->add($this->tnGroupRegCodes);
		$theSql->add('  ADD UNIQUE KEY `test-4-unique` (reg_code)');
		$theSql->execDML();
		$this->logStuff('v2: all reg_code values are unique!');
		//once we know the unique index can be defined, drop all indexes
		//  and re-add the ones we really want defined.
		$theSql = SqlBuilder::withModel($this)
			->startWith('ALTER TABLE')->add($this->tnGroupRegCodes);
		foreach ($theIndexNames as $theIndexName) {
			if ( $theIndexName=='PRIMARY' )
			{ $theSql->add('DROP PRIMARY KEY,'); }
			else
			{
				$theSql->add('DROP KEY')->add(
					$theSql->getQuoted($theIndexName)
				)->add(',');
			}
		}
		$theSql->add('DROP KEY `test-4-unique`');
		$theSql->execDML();
		$this->logStuff('v2: ', $this->tnGroupRegCodes,
				' had its indexes dropped.');
		$theSql = SqlBuilder::withModel($this)
			->startWith('ALTER TABLE')->add($this->tnGroupRegCodes);
		$theSql->add('  ADD PRIMARY KEY (reg_code)');
		$theSql->add(', ADD KEY (group_id)');
		$theSql->execDML();
		$this->logStuff('v2: ', $this->tnGroupRegCodes,
				' had its indexes recreated correctly.');
	}
	
	/**
	 * In version 2, we migrated the Titan role to be just a plain Admin
	 * role and did away with any "special cases" for Titan. Since migrations
	 * from 3.x will also need to run this particular migration separately,
	 * it needs to be its own method to be called from both places.
	 * @return $this Returns $this for chaining.
	 * @throws PDOException if a problem occurs.
	 */
	protected function migrateTitanRoleToAdmin()
	{
		//deliberately remove the Titan UUID so it can never be used
		//  to potentially login again as a faux-admin.
		//get data for the former Titan group
		$theGroup1 = AuthGroup::fromThing($this->getGroupByNum(1));
		//replace former titan group with current group 2 ID in map
		//  so that current titan accounts will be admins (best guess)
		$theGroup2 = AuthGroup::fromThing($this->getGroupByNum(2));
		$theSql = SqlBuilder::withModel($this)
			->startWith('UPDATE')->add($this->tnGroupMap);
		$this->setAuditFieldsOnUpdate($theSql)
			->mustAddParam('group_id', $theGroup2->group_id)
			->startWhereClause()
			->setParamValueIfEmpty('oldgroup_id', $theGroup1->group_id)
			->addParamForColumn('oldgroup_id', 'group_id')
			->endWhereClause()
			->execDML()
		;
		//remove group 1 (former Titan group) AFTER we remapped members
		$this->del($theGroup1->group_id);
		//re-add default group 1 (org-parent)
		$theGroupNames = $this->getRes('AuthGroups', 'group_names');
		$theGroup1->group_id = Strings::createUUID();
		$theGroup1->group_num = 1;
		$theGroup1->group_name = $theGroupNames[1];
		$theSql = SqlBuilder::withModel($this)
			->obtainParamsFrom($theGroup1)
			->startWith('INSERT INTO')->add($this->tnGroups)
		;
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('group_id')
			->mustAddParam('group_num')
			->mustAddParam('group_name')
			->execDML()
		;
		//set default permissions for group 1.
		$this->insertDataForAuthPermissionGroup(
				$this->getDefaultRightsForOrgParent($theGroup1->group_id)
		);
		$this->logStuff('authgroup 1 migrated to be "org-parent".');
		return $this;
	}
	
	protected function upgradeFeatureVersionTo2()
	{
		$theTransactionSql = SqlBuilder::withModel($this);
		$theTransactionSql->beginTransaction();
		try {
			$this->migrateTitanRoleToAdmin();
			//add org_id field to the groups table.
			$this->addFieldToTable(2, 'org_id', $this->tnGroups,
					'org_id ' . CommonMySql::TYPE_UUID . ' NULL',
					'parent_group_id'
			);
			//alter table to add index for new org_id field and change group_num def.
			$theSql = SqlBuilder::withModel($this)->startWith('ALTER TABLE')->add($this->tnGroups);
			$theSql->add('  ADD KEY')->add('(org_id)');
			$theSql->add(', MODIFY')->add('`group_num` INT NOT NULL AUTO_INCREMENT')
				->add(" COMMENT 'user-friendly ID'");
			$theSql->execDML();
			$this->logStuff('v2: added index for org_id and auto-inc group_num');
			
			try {
				$this->upgradeFeatureVersionTo2ForRegCodes();
			}
			catch ( \PDOException $pdox ) {
				$this->logErrors('v2: updating indexes on reg codes failed: ', $pdox);
				//PITA, but gotta force unique-ness
				//remove any orphans
				$theSql = SqlBuilder::withModel($this)
					->startWith('DELETE r.* FROM')->add($this->tnGroupRegCodes)->add('AS r')
					->add('LEFT JOIN')->add($this->tnGroups)->add('AS g USING (group_id)')
					->add('WHERE g.group_id IS NULL')
					//->logSqlDebug(__METHOD__) //DEBUG
					->execDML();
				$this->logStuff('v2: removing any reg_code orphans first.');
				//now force remainder to be unique via their group_num
				$theSql = SqlBuilder::withModel($this)
					->startWith('UPDATE')->add($this->tnGroupRegCodes)->add('AS r')
					->add('INNER JOIN')->add($this->tnGroups)->add('AS g USING (group_id)')
					->setParamPrefix('SET r.')
					->mustAddParam('updated_ts', $this->utc_now())
					->setParamPrefix(', r.')
					->mustAddParam('updated_by', $this->getDirector()->getMyUsername())
					->add(", r.reg_code=CONCAT(r.reg_code, '-', g.group_num)")
					//->logSqlDebug(__METHOD__) //DEBUG
					->execDML();
				$this->logStuff('v2: forcing all reg_code values to be unique.');
				$this->upgradeFeatureVersionTo2ForRegCodes();
			}
			$theTransactionSql->commitTransaction();
			$this->logStuff('v2: ', self::FEATURE_ID, ' finished its schema update.');
		}
		catch ( \Exception $x ) {
			$theTransactionSql->rollbackTransaction();
			throw $x; //caller handles exceptions
		}
	}
	
	protected function exists($aTableName=null)
	{ return parent::exists( empty($aTableName) ? $this->tnGroups : $aTableName ); }

	public function isEmpty($aTableName=null)
	{ return parent::isEmpty( empty($aTableName) ? $this->tnGroups : $aTableName ); }
	
	/**
	 * Insert the data into the permissions table.
	 * @param array $aRightsData - the rights data; ensure each entry has the
	 *   following keys: 'namespace', 'permission', 'group_id', and 'value'.
	 */
	public function insertDataForAuthPermissionGroup( $aRightsData )
	{
		if ( empty($aRightsData) ) return; //trivial
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('INSERT INTO')->add($this->tnPermissions);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('namespace', '__PLACEHOLDER__')
			->mustAddParam('permission', '__PLACEHOLDER__')
			->mustAddParam('group_id', '__PLACEHOLDER__')
			->mustAddParam('value', static::VALUE_Disallow)
		;
		//merge our audit field data with passed in rights data
		$theParamData = array();
		foreach( $aRightsData as $theRowData ) {
			$theParamData[] = array_merge($theSql->myParams, $theRowData);
		}
		try { $theSql->execMultiDML($theParamData); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Insert/update the data into the permissions table.
	 * @param array $aRightsData - the rights data; ensure each entry has the
	 *   following keys: <ul>
	 *   <li>namespace</li>
	 *   <li>permission</li>
	 *   <li>group_id</li>
	 *   <li>value4insert</li>
	 *   <li>value4update</li>
	 *   </ul>
	 * @since 4.2.2
	 */
	public function mergeDataForAuthPermissionGroup( $aRightsData )
	{
		if ( empty($aRightsData) ) return; //trivial
		$theSql = SqlBuilder::withModel($this);
		switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: //MySQL uses "INSERT ... ON DUPLICATE KEY UPDATE"
				$theSql->startWith('INSERT INTO')->add($this->tnPermissions);
				$this->setAuditFieldsOnInsert($theSql)
					->mustAddParam('namespace', '__PLACEHOLDER__')
					->mustAddParam('permission', '__PLACEHOLDER__')
					->mustAddParam('group_id', '__PLACEHOLDER__')
					->mustAddParamForColumn('value4insert', 'value', static::VALUE_Disallow)
					;
				$theSql->add('ON DUPLICATE KEY UPDATE');
				$this->addAuditFieldsForUpdate($theSql->setParamPrefix(' '))
					->mustAddParamForColumn('value4update', 'value', static::VALUE_Disallow)
					;
				break;
			default:
				$theSql->startWith('MERGE')->add($this->tnPermissions);
				$theSql->add('WHEN NOT MATCHED BY TARGET')->add('INSERT');
				$this->setAuditFieldsOnInsert($theSql)
					->mustAddParam('namespace', '__PLACEHOLDER__')
					->mustAddParam('permission', '__PLACEHOLDER__')
					->mustAddParam('group_id', '__PLACEHOLDER__')
					->mustAddParamForColumn('value4insert', 'value', static::VALUE_Disallow)
					;
				$theSql->add('WHEN MATCHED THEN')->add('UPDATE');
				$this->addAuditFieldsForUpdate($theSql->setParamPrefix(' '))
					->mustAddParamForColumn('value4update', 'value', static::VALUE_Disallow)
					;
				break;
		}//end switch
		//merge in the audit field params
		foreach ($aRightsData as &$thePermRow) {
			//keep the audit data, but override __PLACEHOLDER__ data with the actual params.
			$thePermRow = array_merge($theSql->myParams, $thePermRow);
		}
		try {
			//$this->logStuff(__METHOD__, ' rightsdata=', $aRightsData); //DEBUG
			//$theSql->logSqlDebug(__METHOD__); //DEBUG
			$theSql->execMultiDML($aRightsData);
		}
		catch( PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Retrieve a single group row by its group_num rather than group_id.
	 * Mainly used to migrate from old groups model to this one as of 4.0.0.
	 * @param number $aGroupNum - the group_num to get.
	 * @param string $aFieldList - which fields to return, default is all of them.
	 * @return array Returns the row data.
	 */
	public function getGroupByNum( $aGroupNum, $aFieldList=null )
	{
		if ( !isset($aGroupNum) )
		{ throw new \InvalidArgumentException('invalid $aGroupNum param'); }
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('SELECT')->addFieldList($aFieldList);
		$theSql->add('FROM')->add($this->tnGroups);
		$theSql->startWhereClause();
		$theSql->mustAddParam('group_num', $aGroupNum);
		$theSql->endWhereClause();
		try { return $theSql->getTheRow(); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Retrieve a single group row.
	 * @param number $aGroupID - the group_id to get.
	 * @param string $aFieldList - which fields to return, default is all of them.
	 * @return array Returns the row data.
	 */
	public function getGroup($aGroupID, $aFieldList=null)
	{
		if ( empty($aGroupID) )
		{ throw new \InvalidArgumentException('invalid $aGroupID param'); }
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('SELECT')->addFieldList($aFieldList);
		$theSql->add('FROM')->add($this->tnGroups);
		$theSql->startWhereClause();
		$theSql->mustAddParam('group_id', $aGroupID);
		$theSql->endWhereClause();
		try { return $theSql->getTheRow(); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Ensure the given parent_group_id is valid for the org
	 * @param SqlBuilder $aSqlBuilder - the SqlBuilder instance.
	 * @param \BitsTheater\costumes\AuthOrg $aAuthOrg - (OPTIONAL) validate
	 *   against this org, else use the current org.
	 */
	protected function validateAuthGroupDataForOrg( SqlBuilder $aSqlBuilder,
			$aAuthOrg=null )
	{
		$theSql = $aSqlBuilder;
		$theAuthOrg = ( empty($aAuthOrg) ) ? AuthDB::getCurrentOrg($this) : $aAuthOrg;
		if ( !empty($theAuthOrg) ) {
			$theSql->setParamValue('org_id', $theAuthOrg->org_id);
		}
		else {
			$theSql->setParamValue('org_id', null);
		}
		$theParentID = $theSql->getParamValue('parent_group_id');
		//ensure parent_group_id is not the unregistered authgroup ID.
		if ( $theParentID == static::UNREG_GROUP_ID ) $theParentID = null;
		//ensure parent_group_id is one of the our defined authgroups.
		if ( !empty($theParentID) ) {
			$theParentGroup = $this->getGroup($theParentID);
			//if parent not found, clear it out
			if ( empty($theParentGroup) )
			{ $theParentID = null; }
		}
		//if still not empty, then parent is legit; otherwise check config
		if ( empty($theParentID) && !empty($theAuthOrg) &&
				!empty($theAuthOrg->parent_authgroup_id) )
		{
			// No parent was found, or the ID didn't match anything.
			// Instead, ensure that the current org's parent authgroup is
			// carried down to this group, to preserve whatever restrictions are
			// mandated by that hierarchy.
			$theParentID = $theAuthOrg->parent_authgroup_id ;
		}
		$theSql->setParamValue( 'parent_group_id', $theParentID ) ;
	}
	
	/**
	 * Insert a group record.
	 * @param array|object $aDataObject - the (usually Scene) object containing
	 *   the data to be used.
	 * @return array Returns the data posted to the database.
	 */
	public function add($aDataObject)
	{
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aDataObject);
		$this->validateAuthGroupDataForOrg($theSql);
		//now we can insert our new record.
		$theSql->startWith('INSERT INTO')->add($this->tnGroups);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('group_id', Strings::createUUID())
			->mustAddParam('group_name', $theSql->getParam('group_id'))
			->addParam('parent_group_id')
			->addParam('org_id')
			->addParamOfType('group_num', \PDO::PARAM_INT)
			//->logSqlDebug(__METHOD__, 'DEBUG')
			;
		try
		{ return $theSql->execDMLandGetParams(); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}

	/**
	 * Remove a group and its child data.
	 * @param string $aGroupID - the group ID.
	 * @return array Returns an array('group_id'=>$aGroupID).
	 */
	public function del( $aGroupID )
	{
		$theGroupID = trim($aGroupID);
		$theSql = SqlBuilder::withModel($this);
		//prevent delete if any child group found
		$theParentCheck = $theSql->startWith('SELECT group_id')
			->add('FROM')->add($this->tnGroups)
			->startWhereClause()
			->mustAddParam('parent_group_id', $theGroupID)
			->endWhereClause()
			->add('LIMIT 1')
			->getTheRow()
		;
		if ( !empty($theParentCheck) ) {
			throw BrokenLeg::pratfallRes($this,
					'FORBIDDEN', BrokenLeg::ERR_FORBIDDEN,
					'AuthGroups/errmsg_group_is_parent'
			);
		}
		$theSql->beginTransaction();
		try {
			$theSql->reset();
			$theSql->startWith('DELETE FROM')->add($this->tnPermissions);
			$theSql->startWhereClause();
			$theSql->mustAddParam('group_id', $theGroupID);
			$theSql->endWhereClause();
			$theSql->execDML();
			
			$theSql->reset();
			$theSql->startWith('DELETE FROM')->add($this->tnGroupRegCodes);
			$theSql->startWhereClause();
			$theSql->mustAddParam('group_id', $theGroupID);
			$theSql->endWhereClause();
			$theSql->execDML();
			
			$theSql->reset();
			$theSql->startWith('DELETE FROM')->add($this->tnGroupMap);
			$theSql->startWhereClause();
			$theSql->mustAddParam('group_id', $theGroupID);
			$theSql->endWhereClause();
			$theSql->execDML();
			
			$theSql->reset();
			$theSql->startWith('DELETE FROM')->add($this->tnGroups);
			$theSql->startWhereClause();
			$theSql->mustAddParam('group_id', $theGroupID);
			$theSql->endWhereClause();
			$theSql->execDML();
			
			$theSql->commitTransaction();
			return $theSql->myParams;
		}
		catch (PDOException $pdox)
		{
			$theSql->rollbackTransaction();
			throw $theSql->newDbException(__METHOD__, $pdox);
		}
	}

	/**
	 * Add a set of records to the auth_id/group_id map table.
	 * @param string $aAuthID - the auth account ID.
	 * @param string[] $aGroupIDs - the group IDs.
	 * @throws DbException if an error happens in the query itself
	 */
	public function addGroupsToAuth( $aAuthID, $aGroupIDs )
	{
		if (empty($aGroupIDs) || !is_array($aGroupIDs))
		{ throw new \InvalidArgumentException('invalid $aGroupIDs param'); }
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('INSERT INTO')->add($this->tnGroupMap);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('auth_id', $aAuthID);
		$theSql->mustAddParam('group_id', '__placeholder__');
		//use the params added so far to help create our multi-DML array
		//  every entry needs to match the number of SQL parameters used
		$theParamList = array();
		foreach ($aGroupIDs as $anID) {
			$theParamList[] = array(
					'created_ts' => $theSql->getParam('created_ts'),
					'updated_ts' => $theSql->getParam('updated_ts'),
					'created_by' => $theSql->getParam('created_by'),
					'updated_by' => $theSql->getParam('updated_by'),
					'auth_id' => $theSql->getParam('auth_id'),
					'group_id' => $anID,
			);
		}
		try
		{ $theSql->execMultiDML($theParamList); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Add a record to the auth_id/group_id map table.
	 * @param string $aGroupID - the group ID.
	 * @param string $aAuthID - the auth account ID.
	 * @return array Returns the data added.
	 */
	public function addMap($aGroupID, $aAuthID)
	{
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('INSERT INTO')->add($this->tnGroupMap);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('auth_id', $aAuthID);
		$theSql->mustAddParam('group_id', $aGroupID);
		try
		{ return $theSql->execDMLandGetParams(); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}

	/**
	 * Remove a record from the auth_id/group_id map table.
	 * @param string $aGroupID - the group ID.
	 * @param string $aAuthID - the auth account ID.
	 * @return array Returns the data removed.
	 */
	public function delMap($aGroupID, $aAuthID)
	{
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('DELETE FROM')->add($this->tnGroupMap);
		$theSql->startWhereClause()
			->mustAddParam('group_id', $aGroupID)
			->setParamPrefix(' AND ')
			->mustAddParam('auth_id', $aAuthID)
			->endWhereClause()
			;
		try
		{ return $theSql->execDMLandGetParams(); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Replace any UUID tokens with a generated UUID.
	 * @param string $aRegCode - the registration code to process.
	 * @return string Returns the string after replacements were made.
	 */
	protected function processRegCodeUUID( $aRegCode )
	{
		return str_replace('~~uuid~~', Strings::createUUID(), $aRegCode);
	}

	/**
	 * Replace any UUID tokens with a generated UUID.
	 * @param string $aRegCode - the registration code to process.
	 * @return string Returns the string after replacements were made.
	 */
	protected function processRegCodeWiggler( $aRegCode )
	{
		$theFoundGroup = static::UNREG_GROUP_ID;
		do
		{
//			$this->debugLog( __METHOD__ . ' [TRACE] theFoundGroup [' . $theFoundGroup . ']' ) ;
			$aNewRegCode = preg_replace_callback('/~{3,}|$/',
				function($matches) use ($theFoundGroup)
				{
					$len = strlen($matches[0]);
					if ( !empty($len) )
					{ return Strings::urlSafeRandomChars($len); }
					else if ( $theFoundGroup != static::UNREG_GROUP_ID )
					//first time through will not trigger random end chars,
					//  only if we find a conflict and no wigglers will
					//  we tack on some random stuff.
					{ return '.' . Strings::urlSafeRandomChars(); }
				},
				$aRegCode
			);
			$theFoundGroup = $this->findGroupIdByRegCode($aNewRegCode);
		} while ( $theFoundGroup != static::UNREG_GROUP_ID );
		return $aNewRegCode;
	}
	
	/**
	 * Add a reg code record for a group.
	 * @param string $aGroupID - the group ID.
	 * @param string $aRegCode - the registration code.
	 * @return array Returns the data added.
	 * @throws DbException if an error happens in the query itself
	 */
	public function addRegCode($aGroupID, $aRegCode)
	{
		$theGroupID = trim($aGroupID);
		$theRegCode = trim($aRegCode);
		if ( empty($theGroupID) ||
				$theGroupID == static::UNREG_GROUP_ID ||
				empty($theRegCode)
			)
		{ return false; } //trivially reject bad data
		$theRegCode = $this->processRegCodeWiggler(
				$this->processRegCodeUUID($theRegCode)
		);
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('INSERT INTO')->add($this->tnGroupRegCodes);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('group_id', $theGroupID);
		$theSql->mustAddParam('reg_code', $theRegCode);
		try
		{ return $theSql->execDMLandGetParams(); }
		catch ( \PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}

	/**
	 * Add a set of reg codes for a group.
	 * @param string $aGroupID - the group ID.
	 * @param string[] $aRegCodes - the registration codes.
	 */
	public function addRegCodes($aGroupID, $aRegCodes)
	{
		if ( empty($aRegCodes) ) return; //trivial, nothing to insert
		foreach ($aRegCodes as $theCode) {
			$this->addRegCode($aGroupID, $theCode);
		}
	}

	/**
	 * Remove a reg code record for a group.
	 * @param string $aGroupID - the group ID.
	 * @param string $aRegCode - the registration code.
	 * @return array Returns the data removed.
	 * @throws DbException if an error happens in the query itself
	 */
	public function delRegCode($aGroupID, $aRegCode)
	{
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('DELETE FROM')->add($this->tnGroupRegCodes);
		$theSql->startWhereClause()
			->mustAddParam('group_id', $aGroupID)
			->setParamPrefix(' AND ')
			->mustAddParam('reg_code', $aRegCode)
			->endWhereClause()
			;
		try
		{ return $theSql->execDMLandGetParams(); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}

	/**
	 * Remove all reg code records for a group.
	 * @param string $aGroupID - the group ID.
	 * @throws DbException if an error happens in the query itself
	 */
	public function clearRegCodes($aGroupID)
	{
		if ( empty($aGroupID) ) return; //trivial protection
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('DELETE FROM')->add($this->tnGroupRegCodes);
		$theSql->startWhereClause()
			->mustAddParam('group_id', $aGroupID)
			->endWhereClause()
			;
		try
		{ return $theSql->execDMLandGetParams(); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}

	/**
	 * Get the groups a particular auth_id belongs to.
	 * @param string $aAuthID - the auth account ID.
	 * @param string $aOrgID - (OPTIONAL) the org ID to limit results for.
	 * @return string[] Returns the array of group IDs.
	 */
	public function getGroupIDListForAuthAndOrg( $aAuthID, $aOrgID )
	{
		if ( empty($aAuthID) )
		{ throw new \InvalidArgumentException('invalid $aAuthID param'); }
		if ( $aOrgID == AuthDB::ORG_ID_4_ROOT ) {
			$aOrgID = null;
		}
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('SELECT group_id FROM')->add($this->tnGroupMap)
			->add('INNER JOIN')->add($this->tnGroups)->add('AS g USING (group_id)')
			->startWhereClause()
			->mustAddParam('auth_id', $aAuthID)
			->setParamPrefix(' AND (g.')
			->mustAddParam('org_id', $aOrgID)
			->setParamPrefix(' OR ')
			;
		// OR a group that has the transcend permission
		$theSubQuery = SqlBuilder::withModel($this)
			->startWith('SELECT group_id FROM')->add($this->tnPermissions)
			->add('INNER JOIN')->add($this->tnGroups)->add('AS g2 USING (group_id)')
			->startWhereClause('g2.')
			->mustAddParamForColumn('subquery.org_id', 'org_id', null)
			->setParamPrefix(' AND ')
			->mustAddParam('namespace', 'auth_orgs')
			->setParamPrefix(' AND ')
			->mustAddParam('permission', 'transcend')
			->setParamPrefix(' AND ')
			->mustAddParam('value', static::VALUE_Allow)
			->endWhereClause()
			;
		$theSql->addSubQueryForColumn($theSubQuery, 'group_id');
		$theSql->add(')');
		//$theSql->logSqlDebug(__METHOD__); //DEBUG
		try
		{
			$theIDList = $theSql->query()->fetchAll(\PDO::FETCH_COLUMN);
			//former Titan account would not be able to log in to run
			//  schema update if we didn't map the former UUID to
			//  something else already known. Once schema is updated, the
			//  former Titan UUID is removed from the auth groups table
			//  thus rendering it impossible to login as a Titan (where
			//  UUID = app_id) once upgrade is complete (unless some funny
			//  business occurs via manual db manipulation itself, in which
			//  case no protection is sufficient).
			$isFormerTitan = false;
			if ( !empty($theIDList) ) {
				$isFormerTitan = in_array($this->getDirector()->app_id,
					$theIDList, true
				);
			}
			if ( $isFormerTitan ) {
				//replace former titan group with current group 2 ID in map
				//  so that current titan accounts will be admins (best guess)
				$theGroup2 = AuthGroup::fromThing($this->getGroupByNum(2));
				$theIDList[$isFormerTitan] = $theGroup2->group_id;
			}
			return $theIDList;
		}
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}

	/**
	 * Get the groups a particular account belongs to.
	 * @param integer $aAcctId - the account ID.
	 * @return array Returns the array of group IDs.
	 */
	public function getAcctGroups($aAcctId)
	{
		if ( $this->exists($this->tnGroups) && !$this->isEmpty($this->tnGroups) )
		{
			/* @var $dbAuth \BitsTheater\models\Auth */
			$dbAuth = $this->getProp('Auth');
			$theAuthRow = $dbAuth->getAuthByAccountId($aAcctId);
			if ( !empty($theAuthRow) ) {
				return $this->getGroupIDListForAuthAndOrg(
						$theAuthRow['auth_id'], $dbAuth->getCurrentOrgID()
				);
			}
			else
			{ return array( static::UNREG_GROUP_ID ); }
		}
		else //we may be in a state before migration took place
		{
			/* @var $dbOldAuthGroups \BitsTheater\models\PropCloset\BitsGroups */
			$dbOldAuthGroups = $this->getProp(
					'\BitsTheater\models\PropCloset\BitsGroups'
			);
			//when return to caller, ensure we free up the old model object
			try {
				if ( $dbOldAuthGroups->exists() && !$dbOldAuthGroups->isEmpty() )
				{
					$theList = $dbOldAuthGroups->getAcctGroups($aAcctId);
					//$this->logStuff(__METHOD__, ' ', $theList); //DEBUG
					if ( in_array($dbOldAuthGroups::TITAN_GROUP_ID, $theList) )
					{
						//group 2 is default admin group, best we can do
						$theGroup2Row = $this->getGroupByNum(2);
						return array( $theGroup2Row['group_id'] );
					}
				}
			}
			finally {
				$this->returnProp($dbOldAuthGroups);
			}
		}
	}

	/**
	 * Get the groups a particular account belongs to filtered by org.
	 * The current org is used if none is supplied.
	 * @param string $aAuthID - the auth ID for the account.
	 * @return string[] Returns the array of group IDs.
	 */
	public function getAcctGroupsForOrg($aAuthID, $aOrgID=null)
	{
		if ( !$this->exists($this->tnGroups) || $this->isEmpty($this->tnGroups) )
		{
			$err = new DbException(null, 'parent table is empty');
			$err->setCode(static::ERR_CODE_EMPTY_AUTHGROUP_TABLE);
			throw $err;
		}
		if ( !empty($aAuthID) )
		{ return $this->getGroupIDListForAuthAndOrg($aAuthID, $aOrgID); }
		else
		{ return array( static::UNREG_GROUP_ID ); }
	}

	/**
	 * Insert an auth group with the given data.
	 * @param string $aGroupID - the new group's ID.
	 * @param string $aGroupName - the new group's name.
	 * @param string $aGroupParentID - the ID of the group from which permission
	 *  settings should be inherited (default null).
	 * @throws DbException if an error happens in the query itself
	 */
	public function insertGroup( $aDataObject )
	{ return $this->add($aDataObject); }
	
	/**
	 * Creates a new auth group.
	 * @param string $aGroupName - the new group's name
	 * @param string $aGroupParentID - the ID of the group from which permission
	 *  settings should be inherited (default null)
	 * @param integer $aGroupNum - (optional) a unique number representing an alt ID.
	 * @param string $aGroupRegCode - (optional) the group's registration code
	 * @param string $aGroupCopyID - (optional) the ID of a group from which permissions
	 *  should be *copied* into the new group.
	 * @throws DbException if an error happens in the query itself
	 */
	public function createGroup( $aGroupName, $aGroupParentID=null, $aGroupNum=null,
			$aGroupRegCode=null, $aGroupCopyID=null )
	{
		$theGroupParentId = trim($aGroupParentID);
		if ( $theGroupParentId == static::UNREG_GROUP_ID )
		{ $theGroupParentId = null; }
		$theGroupNum = filter_var($aGroupNum, FILTER_VALIDATE_INT, FILTER_NULL_ON_FAILURE);
		if ( $theGroupNum < 0 )
		{ $theGroupNum = null; }
		
		$theNewGroupID = Strings::createUUID();
		$theResults = $this->add(array(
				'group_id' => $theNewGroupID,
				'group_num' => $aGroupNum,
				'group_name' => $aGroupName,
				'parent_group_id' => $theGroupParentId,
		));

		$theNewRegCodeRow = $this->insertGroupRegCode($theNewGroupID, $aGroupRegCode);
		if ( is_array($theNewRegCodeRow) ) {
			$theResults = array_merge($theResults, $theNewRegCodeRow);
		}

		if ( !empty($aGroupCopyID) )
		{
			try
			{
				$theCopyResult = $this->copyPermissions($aGroupCopyID, $theNewGroupID);
				$theResults['copied_group'] = $aGroupCopyID ;
				$theResults['copied_perms'] = $theCopyResult['count'] ;
			}
			catch( Exception $x )
			{
				$theExClass = (new \ReflectionClass($x))->getShortName();
				$this->errorLog( __METHOD__
						. ' failed to copy permissions for group ['
						. $aGroupCopyID . '] because of a '
						. $theExClass . ': '
						. $x->getMessage()
						);
				$theResults['copied_group'] = static::UNREG_GROUP_ID;
				$theResults['group_copy_error'] = $x->getMessage();
			}
		}
		return $theResults ;
	}

	/**
	 * Updates an existing group. Notable exceptions: cannot update the
	 * parent_group_id, nor the org_id.
	 * @param object $aDataObject - the (usually Scene) object containing
	 *   the data to be used.
	 * @return array|boolean Returns FALSE if not found, else array of updated data.
	 * @throws DbException if an error happens in the query itself
	 */
	public function modifyGroup( $aDataObject )
	{
		if ( empty($aDataObject) || empty($aDataObject->group_id) )
			return false; //trivially reject bad calls
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aDataObject);
		$theSql->startWith('UPDATE')->add($this->tnGroups);
		$this->setAuditFieldsOnUpdate($theSql);
		$theSql->addParam('group_name');
		$theSql->addParamOfType('group_num', \PDO::PARAM_INT);
		$theSql->startWhereClause()
			->mustAddParam('group_id')
			->endWhereClause()
			;
		//$theSql->logSqlDebug(__METHOD__);
		try { return $theSql->execDMLandGetParams(); }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox) ; }
	}

	/**
	 * Insert a new registration code for an existing group ID.
	 * @param string $aGroupID - the group ID.
	 * @param string $aRegCode - the new registration code.
	 * @return array|boolean Returns FALSE if bad data or array of inserted data.
	 * @throws DbException if an error happens in the query itself
	 */
	public function insertGroupRegCode( $aGroupID, $aRegCode )
	{ return $this->addRegCode($aGroupID, $aRegCode); }

	/**
	 * Get the list of reg code for a given group.
	 * @param string $aGroupID - (OPTIONAL) restrict output to group_id.
	 *   NOTE: if omitted, 2D array of records indexed by group_id is returned
	 *   as legacy behavior.
	 * @return string[] Return array of reg codes.
	 */
	public function getGroupRegCodes( $aGroupID=null )
	{
		$theFieldList = ( !empty($aGroupID) ) ? 'reg_code' : null;
		$theSql = SqlBuilder::withModel($this)
			->startWith('SELECT')->addFieldList($theFieldList)
			->add('FROM')->add($this->tnGroupRegCodes)
			->startWhereClause()
			->setParamValueIfEmpty('group_id', $aGroupID)->addParam('group_id')
			->endWhereClause()
			->applyOrderByList(array(
					'created_ts' => SqlBuilder::ORDER_BY_ASCENDING
			))
		;
		try
		{
			if ( !empty($aGroupID) ) {
				return $theSql->query()->fetchAll(\PDO::FETCH_COLUMN);
			}
			else {
				return Arrays::array_column_as_key(
						$theSql->query()->fetchAll(), 'group_id'
				);
			}
		}
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ); }
	}

	/**
	 * See if an entered registration code matches a group_id.
	 * @param string $aRegCode - the entered registration code.
	 * @return string Returns the group_id which matches or static::UNREG_GROUP_ID if none.
	 */
	public function findGroupIdByRegCode( $aRegCode )
	{
		$theRegCode = trim($aRegCode);
		if ( empty($theRegCode) ) return static::UNREG_GROUP_ID; //trivial
		$theSql = SqlBuilder::withModel($this)
			->startWith('SELECT group_id FROM')->add($this->tnGroupRegCodes)
			->startWhereClause()
			->mustAddParam('reg_code', $theRegCode)
			->endWhereClause()
			->add('LIMIT 1')
		;
		try
		{
			$theRow = $theSql->query()->fetchAll(\PDO::FETCH_COLUMN);
			return ( !empty($theRow) ) ? $theRow[0] : static::UNREG_GROUP_ID;
		}
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ); }
	}

	/**
	 * Get a list of AuthIDs mapped to AuthGroupID.
	 * @param string $aAuthGroupID - the group ID.
	 * @return string[] Returns the list of auth_ids.
	 * @throws DbException if an error happens in the query itself
	 */
	public function getAuthIDListForGroup( $aAuthGroupID )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith('SELECT auth_id FROM')->add($this->tnGroupMap)
			->startWhereClause()
			->mustAddParam( 'group_id', $aAuthGroupID )
			->endWhereClause()
			;
		try { return Arrays::array_column($theSql->query()->fetchAll(), 'auth_id'); }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ); }
	}

	/**
	 * Returns a dictionary of all permission groups IDs and names.
	 * @param $bIncludeSystemGroups boolean indicates whether to include the
	 *   "unregistered" group.
	 * @param string[]|string $aFieldList - (optional) which fields to return,
	 *   the default is all of them.
	 * @param bool[] $aSortList - (optional) how to sort the data, use array
	 *   format of <code>array[fieldname => ascending=true]</code>.
	 * @throws DbException if an error happens in the query itself
	 */
	public function getListForPicker( $bIncludeSystemGroups=false,
			$aFieldList=null, $aSortList=null )
	{
		//get our current org ID
		$theOrgID = $this->getProp('Auth')->getCurrentOrgID();
		//now get our list of groups restricted by org
		$theSql = SqlBuilder::withModel($this)
			->startWith('SELECT')->addFieldList($aFieldList)
			->add('FROM')->add($this->tnGroups)
			->startWhereClause()
			->mustAddParam('org_id', $theOrgID)
		;
		if ( !$bIncludeSystemGroups ) {
			$theSql->setParamPrefix(' AND ');
			$theSql->setParamOperator(SqlBuilder::OPERATOR_NOT_EQUAL);
			$theSql->mustAddParam('group_id', static::UNREG_GROUP_ID);
		}
		$theSql->endWhereClause();
		$theSql->applyOrderByList( $aSortList ) ;
		//$theSql->logSqlDebug(__METHOD__);
		try { return $theSql->query() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}
	
	/**
	 * Check group permissions for user account.
	 * @param string $aNamespace - namespace of permission.
	 * @param string $aPermission - permission to test against.
	 * @param AccountInfoCache $aAcctInfo - (optional) check this account
	 *   instead of current user.
	 * @param array $aListOfRights - (OPTIONAL) a subset of rights to load.
	 * @return boolean Returns TRUE if allowed, else FALSE.
	 */
	public function isPermissionAllowed($aNamespace, $aPermission,
			AccountInfoCache $aAcctInfo=null, $aListOfRights=null)
	{
		if ( empty($aAcctInfo) ) return false; //trivial
		//$this->logStuff(__METHOD__, ' acctinfo=', $aAcctInfo); //DEBUG
		//NULL means we have not even tried to check permissions.
		if ( is_null($aAcctInfo->groups) )
		{
			$theOrgID = null;
			if ( !empty($aAcctInfo->mSeatingSection) ) {
				$theOrgID = $aAcctInfo->mSeatingSection->org_id;
			}
			$aAcctInfo->groups = $this->getGroupIDListForAuthAndOrg(
					$aAcctInfo->auth_id, $theOrgID
			);
			if ( empty($aAcctInfo->groups) )
			{ $aAcctInfo->groups = array(static::UNREG_GROUP_ID); }
		}
		//NULL means we have not even tried to check permissions.
		if ( is_null($aAcctInfo->rights) ) try
		{
			$aAcctInfo->rights = (object)$this->getGrantedRights(
					$aAcctInfo->groups, $aListOfRights
			);
			//cast to an object because session restore will restore as object
			foreach ($aAcctInfo->rights as $theNS => $thePerms) {
				if ( !is_object($thePerms) ) {
					$aAcctInfo->rights->{$theNS} = (object)$thePerms;
				}
			}
		} catch (DbException $dbx) {
			$aAcctInfo->rights = array();
		}
		//$this->logStuff(__METHOD__, ' acctInfo=', $aAcctInfo); //DEBUG
		return ( !empty($aAcctInfo->rights->{$aNamespace}) &&
				!empty($aAcctInfo->rights->{$aNamespace}->{$aPermission})
		);
	}

	//=========================================================================
	//===============  AuthPermissions           ==============================
	//=========================================================================
	
	/**
	 * Build up a giant tree of all the permissions, all marked as the param.
	 * @param IDirected $aContext - the context to use.
	 * @param boolean|string $aRightGranted - (OPTIONAL) the default value is
	 *   FALSE, but can supply it with FORM_* values as well as boolean.
	 * @return array Returns a 2D bool|string leaf of namespace[permission[]].
	 */
	static public function getDefinedRights( IDirected $aContext,
			$aRightGranted=false )
	{
		$theResults = array() ;
		$theNSList = $aContext->getRes( 'permissions/namespace' ) ;
		foreach( $theNSList as $theNS => $theNSInfo )
		{
			$theResults[$theNS] = array() ;
			$thePerms = $aContext->getRes( 'permissions/' . $theNS ) ;
			foreach( $thePerms as $thePerm => $thePermInfo )
				$theResults[$theNS][$thePerm] = $aRightGranted ;
		}
		//$aContext->getDirector()->logStuff(__METHOD__, $theResults); //DEBUG
		return $theResults ;
	}

	/**
	 * Build up a giant tree of all the permissions, all marked as true.
	 * @return array Returns a 2D boolean leaf of namespace[permission[]].
	 */
	public function getAllAccessPass()
	{ return $this::getDefinedRights($this, true); }

	/**
	 * Given a list of groups, load them up into an array.
	 * @param string|string[] $aGroupIDorList - (optional) a group ID or an
	 *  array of IDs; null value returns all groups
	 * @param string[] $aFieldList - the list of fields to retrieve.
	 * @return AuthGroup[] Returns the loaded groups keyed by group_id.
	 */
	public function getAuthGroupList( $aGroupIDorList=null, $aFieldList=null )
	{
		$theResults = array();
		$theSql = SqlBuilder::withModel($this);
		try
		{
			$theRowSet = $theSql
				->startWith('SELECT')->addFieldList($aFieldList)
				->add('FROM')->add($this->tnGroups)
				;
			if( !empty($aGroupIDorList) )
			{
				$theSql->startWhereClause()
					->mustAddParam('group_id', $aGroupIDorList)
					->endWhereClause()
					;
			}
			$theRowSet = $theSql->query() ;
			$theRowSet->setFetchMode(
					\PDO::FETCH_CLASS|\PDO::FETCH_PROPS_LATE,
					AuthGroup::class, array($this)
			);
			while ( ($theAuthGroup = $theRowSet->fetch()) !== false ) {
				/* @var $theAuthGroup AuthGroup */
				$theResults[$theAuthGroup->group_id] = $theAuthGroup;
			}
		}
		catch ( \PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox); }
		//$this->logStuff(__METHOD__, ' groups=', $theResults); //DEBUG
		return ( !empty($theResults) ) ? $theResults : array();
	}
	
	/**
	 * Show the auth groups for display (pager and such).
	 * @param ISqlSanitizer $aSqlSanitizer - the SQL sanitizer obj being used.
	 * @param SqlBuilder $aFilter - (optional) Specifies restrictions on
	 *   data to return; effectively populating a WHERE filter for the query.
	 * @param string[]|NULL $aFieldList - (optional) String list representing
	 *   which columns to return. Leaving this argument blank defaults to
	 *   returning all table column fields.
	 * @throws DBException
	 * @return \PDOStatement Returns the query result.
	 */
	public function getRolesToDisplay(ISqlSanitizer $aSqlSanitizer=null,
			 SqlBuilder $aFilter=null, $aFieldList=null)
	{
		//restrict results to current org
		$theOrg = AuthDB::getCurrentOrg($this);
		$theOrgID = ( !empty($theOrg) ) ? $theOrg->org_id : null;
		$theSql = SqlBuilder::withModel($this)->setSanitizer($aSqlSanitizer);
		//query field list NOTE: since we may have a nested query in
		//  the field list, must add HINT for getQueryTotals()
		$theSql->startWith('SELECT')
			->add(SqlBuilder::FIELD_LIST_HINT_START)
			->addFieldList($aFieldList)
			->add(SqlBuilder::FIELD_LIST_HINT_END)
			;
		$theSql->add('FROM')->add($this->tnGroups)
			->startWhereClause()
			->mustAddParam('org_id', $theOrgID)
			->setParamPrefix(' AND ')
			->applyFilter($aFilter)
			->endWhereClause()
			;
		$theSql->retrieveQueryTotalsForSanitizer()
			->applyOrderByListFromSanitizer()
			->applyQueryLimitFromSanitizer()
			;
		//$theSql->logSqlDebug(__METHOD__); //DEBUG
		try { return $theSql->query() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Given a list of groups, load them, and their parents up into an array.
	 * @param string|string[] $aGroupIDorList - a group ID or an array of IDs.
	 * @param string[] $aFieldList - the list of fields to retrieve.
	 * @return AuthGroup[] Returns the loaded groups keyed by group_id.
	 */
	public function getAuthGroupsAndParents( $aGroupIDorList,
			$aFieldList=null  )
	{
		$theGroupIDsToSearch = $aGroupIDorList ;
		$theGroupsToReturn = array() ;
		//NOTE: getAuthGroupList() got changed from a required parameter to
		//  an optional param of IDs, so now we must check to ensure we do
		//  not send in a blank list which would return all of them whereas
		//  before the change it would return none of them and we depended on
		//  that fact. No big deal, just means we reverse our do/while loop.
		while ( !empty($theGroupIDsToSearch) )
		{ // Search one level of groups, add them, then search their parents.
			$theGroupsSearched = $this->getAuthGroupList( $theGroupIDsToSearch, $aFieldList ) ;
			$theGroupsToReturn = array_merge( $theGroupsToReturn, $theGroupsSearched ) ;
			$theGroupIDsToSearch = array() ;
			foreach( $theGroupsSearched as $theGroup )
			{
				if( !empty( $theGroup->parent_group_id )
						&& !in_array( $theGroup->parent_group_id, $theGroupsToReturn ) )
				{ // We haven't traced this path up the hierarchy yet.
					$theGroupIDsToSearch[] = $theGroup->parent_group_id ;
				}
			}
//			$this->debugLog( __METHOD__ . ' [TRACE] Groups to search on next iteration: ' . json_encode($theGroupIDsToSearch) ) ;
		}
//		$this->debugLog( __METHOD__ . ' [TRACE] Final group set: ' . json_encode($theGroupsToReturn) ) ;
		return $theGroupsToReturn ;
	}
	
	/**
	 * Load up all rights assigned to this group as well as parent groups.
	 * @param string|string[] $aGroupIDorList - a group ID or an array of IDs.
	 * @param array $aListOfRights - (OPTIONAL) a subset of rights to load.
	 * @param string $aOrgID - (OPTIONAL) a specific org to use, current org otherwise.
	 * @return array Returns 2D boolean leaf array of [namespace[permission]].
	 */
	public function getAssignedRights( $aGroupIDorList, $aListOfRights=null, $aOrgID=null )
	{
		//get list of defined rights
		if ( empty($aListOfRights) ) {
			$theResults = $this::getDefinedRights($this, false);
		}
		else {
			$theResults = $aListOfRights;
		}
		//get list of auth groups and their parents
		$theGroups = $this->getAuthGroupsAndParents($aGroupIDorList, array(
				'group_id', 'group_num', 'group_name', 'parent_group_id',
		));
		$theCompleteList = array_keys($theGroups);
		//now that we have all pertinent groups in memory, lets process!
		$theMemberList = ( !empty($aGroupIDorList) )
			? ( is_string($aGroupIDorList) ? array($aGroupIDorList) : $aGroupIDorList )
			: array()
			;
		//array_values because array_diff keeps the keys intact and we do not wish that here.
		$theParentList = array_values(array_diff($theCompleteList, $theMemberList));
		
		//auth model is IF ONE PARENT DENIES IT, THE RIGHT IS NOT ALLOWED
		if ( !empty($theParentList) ) {
			$theSql = SqlBuilder::withModel($this)
				->startWith('SELECT DISTINCT namespace, permission')
				->add('FROM')->add($this->tnPermissions)
				->startWhereClause()
				->mustAddParam('group_id', $theParentList)
				->setParamPrefix(' AND ')
				->mustAddParam('value', static::VALUE_Deny)
				;
			//limit our rights query to just those wanted, if defined
			if ( !empty($aListOfRights) ) {
				$theSql->add('AND ( 0');
				foreach ($aListOfRights as $theNamespace => $theRightsList) {
					$theNamespaceKey = $theSql->getUniqueDataKey('namespace');
					$thePermissionKey = $theSql->getUniqueDataKey('permission');
					$theSql->setParamPrefix(' OR (')
						->mustAddParamForColumn($theNamespaceKey, 'namespace', $theNamespace)
						->setParamPrefix(' AND ')
						->mustAddParamForColumn($thePermissionKey, 'permission', array_keys($theRightsList))
						->add(')')
						;
				}
				$theSql->add(')');
			}
			$theSql->endWhereClause()
				//->logSqlDebug(__METHOD__) //DEBUG
				;
			$theForbiddenRights = $theSql->query()->fetchAll();
		}
		else $theForbiddenRights = array();
		
		//auth model is IF ONE GROUP ALLOWS IT, THE RIGHT IS ALLOWED
		if ( !empty($theCompleteList) )
		{
			if ( empty($aOrgID) ) {
				$theOrgID = $this->getProp(AuthDB::MODEL_NAME)->getCurrentOrgID();
			}
			else if ( $aOrgID==AuthDB::ORG_ID_4_ROOT ) {
				$theOrgID = null;
			}
			else {
				$theOrgID = $aOrgID;
			}
			$theSql = SqlBuilder::withModel($this)
				->startWith('SELECT DISTINCT namespace, permission')
				->add('FROM')->add($this->tnPermissions)->add('AS p')
				->add('LEFT JOIN')->add($this->tnGroups)->add('AS g USING (group_id)')
				->startWhereClause(' p.')
				->mustAddParam('group_id', $theCompleteList)
				->setParamPrefix(' AND p.')
				->mustAddParam('value', static::VALUE_Allow)
				;
			//limit our rights query to just those wanted, if defined
			if ( !empty($aListOfRights) ) {
				$theSql->add('AND ( 0');
				foreach ($aListOfRights as $theNamespace => $theRightsList) {
					$theNamespaceKey = $theSql->getUniqueDataKey('namespace');
					$thePermissionKey = $theSql->getUniqueDataKey('permission');
					$theSql->setParamPrefix(' OR (p.')
						->mustAddParamForColumn($theNamespaceKey, 'namespace', $theNamespace)
						->setParamPrefix(' AND p.')
						->mustAddParamForColumn($thePermissionKey, 'permission', array_keys($theRightsList))
						->add(')')
						;
				}
				$theSql->add(')');
			}
			if ( $this->bIsOrgColumnExists )
			{ // Don't look for an org ID if we haven't yet updated the table.
				$theSql->setParamPrefix(' AND (g.')
					->mustAddParam('org_id', $theOrgID)
					->setParamPrefix(' OR g.')
					->mustAddParamForColumn('null_org_id', 'org_id', null)
					->add(')')
					;
			}
			$theSql->endWhereClause()
				//->logSqlDebug(__METHOD__) //DEBUG
				;
			$theAssignedRights = $theSql->query()->fetchAll() ;
		}
		else
		{ $theAssignedRights = array(); }

		//$this->logStuff(__METHOD__, ' loaded rights=', $theAssignedRights); //DEBUG
		foreach ((array)$theAssignedRights as $theGrantedRight) {
			$theResults[$theGrantedRight['namespace']][$theGrantedRight['permission']] = true;
		}
		//$this->logStuff(__METHOD__, ' loaded denies=', $theForbiddenRights); //DEBUG
		foreach ((array)$theForbiddenRights as $theRevokedRight) {
			unset($theResults[$theRevokedRight['namespace']][$theRevokedRight['permission']]);
		}
		//$this->logStuff(__METHOD__, ' rights=', $theResults); //DEBUG
		return $theResults;
	}

	/**
	 * Like getAssignedRights(), but returns only the rights that are allowed
	 * for the group, as a Boolean "true"; any right that is in 'disallow' or
	 * 'deny' state is omitted from the result set.
	 * @param string|string[] $aGroupIDorList - a group ID or an array of IDs.
	 * @param array $aListOfRights - (OPTIONAL) a subset of rights to load.
	 * @return array Returns 2D boolean leaf array of [namespace[permission]].
	 */
	public function getGrantedRights( $aGroupIDorList=null, $aListOfRights=null )
	{
		$theResults = $this->getAssignedRights($aGroupIDorList, $aListOfRights);
		// Now go back and remove everything that's not allowed.
		foreach ($theResults as $theNS => $thePerms ) {
			foreach($thePerms as $thePerm => $theVal ) {
				if ( !$theVal ) {
					unset($theResults[$theNS][$thePerm]);
				}
			}
			if ( count($theResults[$theNS]) == 0 ) {
				unset($theResults[$theNS]);
			}
		}
		//$this->logStuff(__METHOD__, ' rights=', $theResults); //DEBUG
		return $theResults;
	}

	/**
	 * Returns a dictionary of all permission group data.
	 * @param boolean $bIncludeSystemGroups - (OPTIONAL) indicates whether to
	 *   include the "unregistered" group, defaults to FALSE.
	 * @param string|null $aOrgID - the org to use besides the current one.
	 * @return \PDOStatement Returns the query results unfetched.
	 * @throws DbException if an error happens in the query itself
	 */
	public function getAuthGroupsForOrg( $bIncludeSystemGroups=false,
			$aOrgID=null )
	{
		$theOrgID = ( empty($aOrgID) )
				? $this->getProp('Auth')->getCurrentOrgID() : $aOrgID;
		//now get our list of groups restricted by org
		$theSql = SqlBuilder::withModel($this)
			->startWith('SELECT')
			->add('group_id, group_num, group_name, parent_group_id')
			->add('FROM')->add($this->tnGroups)
			->startWhereClause()
			->mustAddParam('org_id', $theOrgID)
		;
		if ( !$bIncludeSystemGroups ) {
			$theSql->setParamPrefix(' AND ');
			$theSql->setParamOperator(SqlBuilder::OPERATOR_NOT_EQUAL);
			$theSql->mustAddParam('group_id', static::UNREG_GROUP_ID);
		}
		$theSql->endWhereClause();
		$theSql->add( 'ORDER BY group_num' ) ;
		try { return $theSql->query(); }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}

		
	/**
	 * Remove existing permissions for a particular group.
	 * @param string $aGroupId - the group ID.
	 */
	public function removeGroupPermissions( $aGroupID )
	{
		if ( empty($aGroupID) ) return; //trivial
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('DELETE FROM')->add($this->tnPermissions);
		$theSql->startWhereClause()->mustAddParam('group_id', $aGroupID)->endWhereClause();
		$theSql->execDML();
	}

	/**
	 * Modify the saved permissions for a particular group.
	 * @param object $aDataObject - the (usually Scene) object containing
	 *   the data to be used.
	 */
	public function modifyGroupRights( $aDataObject )
	{
		if ( empty($aDataObject) || empty($aDataObject->group_id) )
			return; //trivial
		
		//add new permissions
		$theRightsList = array();
		$theRightGroups = $this->getRes('permissions/namespace');
		foreach ($theRightGroups as $ns => $nsInfo)
		{
			foreach ($this->getRes('permissions/' . $ns) as $theRight => $theRightInfo)
			{
				$varName = $ns.'__'.$theRight;
				$theAssignment = $aDataObject->$varName;
				//$this->debugLog($varName.'='.$theAssignment);
				switch ( $theAssignment ) {
					case static::FORM_VALUE_Allow:
						$theValue = static::VALUE_Allow;
						break;
					case static::FORM_VALUE_Deny:
						$theValue = static::VALUE_Deny;
						break;
					default:
						$theValue = static::VALUE_Disallow;
				}//switch
				$theRightsList[] = array(
						'namespace' => $ns,
						'permission' => $theRight,
						'group_id' => $aDataObject->group_id,
						'value4insert' => $theValue,
						'value4update' => $theValue,
				);
			}//end foreach
		}//end foreach
		$this->mergeDataForAuthPermissionGroup($theRightsList);
	}

	/**
	 * Returns a set of permission rows from the permission/group mapping
	 * table that are distinct values for each namespace/permission.
	 * @param string|string[] $aGroupIDorList - a group ID or an array of IDs.
	 * @throws DbException if a problem occurs during DB query execution
	 * @return \PDOStatement Returns the query.
	 */
	public function getAssignedPermissionMap( $aGroupIDorList )
	{
		if ( empty($aGroupIDorList) ) return; //trivial
		$theSql = SqlBuilder::withModel($this)
			->startWith('SELECT DISTINCT namespace, permission, value')
			->add('FROM')->add($this->tnPermissions)
			->startWhereClause()
			->mustAddParam('group_id', $aGroupIDorList)
			->setParamPrefix(' AND ')
			->setParamOperator(SqlBuilder::OPERATOR_NOT_EQUAL)
			->mustAddParam('value', static::VALUE_Disallow)
			->endWhereClause()
		;
		try
		{ return $theSql->query(); }
		catch ( \PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox); }
	}

	/**
	 * Copies permissions for one group to another group.
	 * Consumed by the createGroup() function in the AuthGroups model.
	 * @param string $aSourceGroupID the source of the permissions
	 * @param string $aTargetGroupID the target for the permissions
	 * @return array indication of the result
	 * @throws DbException if a problem occurs during DB query execution
	 * @throws RightsException if either source or target is not specified, or
	 *   not found
	 */
	public function copyPermissions( $aSourceGroupID, $aTargetGroupID )
	{
		//source group ID
		if( ! isset( $aSourceGroupID ) )
		{ throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', '$aSourceGroupID' ) ; }
		$theSourceGroupRow = $this->getGroup($aSourceGroupID, 'group_name');
		if ( empty($theSourceGroupRow) )
		{ throw RightsException::toss( $this, 'GROUP_NOT_FOUND', $aSourceGroupID ) ; }
		//target group ID
		if( ! isset( $aTargetGroupID ) )
		{ throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', '$aTargetGroupID' ) ; }
		$theTargetGroupRow = $this->getGroup($aTargetGroupID, 'group_name');
		if ( empty($theTargetGroupRow) )
		{ throw RightsException::toss( $this, 'GROUP_NOT_FOUND', $aTargetGroupID ) ; }
		//ensure source and target share the same org_id
		if ( $theSourceGroupRow['org_id'] != $theTargetGroupRow['org_id'] )
		{ throw BrokenLeg::toss($this, BrokenLeg::ACT_FORBIDDEN); }
		//remove any target permissions
		try { $this->removeGroupPermissions($aTargetGroupID); }
		catch( PDOException $pdox )
		{
			throw new DbException( $pdox, __METHOD__
					. ' failed to delete old permissions for target group ['
					. $aTargetGroupID . '].'
					);
		}
		//get source permission cursor (PDOStatement)
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT namespace, permission, group_id, value')
			->add('FROM')->add($this->tnPermissions)
			->startWhereClause()
			->mustAddParam( 'group_id', $aSourceGroupID )
			->endWhereClause()
			;
		try { $ps = $theSql->query() ; }
		catch( PDOException $pdox )
		{
			throw new DbException( $pdox, __METHOD__
					. ' failed to fetch permissions for source group ['
					. $aSourceGroupID . '].'
					);
		}
		//define our multi-query
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('INSERT INTO')->add($this->tnPermissions);
		$this->setAuditFieldsOnInsert($theSql)
			->mustAddParam('namespace', '__placeholder__')
			->mustAddParam('permission', '__placeholder__')
			->mustAddParam('group_id', $aTargetGroupID)
			->mustAddParam('value', '__placeholder__')
			;
		//read in source to use as multi-query
		$theParamList = array();
		for( $theItem = $ps->fetch() ; $theItem !== false ; $theItem = $ps->fetch() )
		{
			$theParamList[] = array(
					'namespace' => $theItem['namespace'],
					'permission' => $theItem['permission'],
					'group_id' => $theSql->getParam('group_id'),
					'value' => $theItem['value'],
					'created_ts' => $theSql->getParam('created_ts'),
					'created_by' => $theSql->getParam('created_by'),
					'updated_ts' => $theSql->getParam('updated_ts'),
					'updated_by' => $theSql->getParam('updated_by'),
			);
		}
		//use the param list we made to execute our multi-query
		try
		{ $theSql->execMultiDML($theParamList); }
		catch (PDOException $pdox)
		{
			throw new DbException( $pdox, __METHOD__
					. ' failed to insert [' . count($theParamList)
					. '] permissions into target group ['
					. $aTargetGroupID . '].'
					);
		}
		return array(
				'source_group_id' => $aSourceGroupID,
				'target_group_id' => $aTargetGroupID,
				'count' => count($theParamList),
		);
	}

	/**
	 * Sets one of the ternary flags for a given group/namespace/permission
	 * triple. The values are VALUE_Allow const for "always allow",
	 * VALUE_Deny const for "always deny", and VALUE_Disallow or null
	 * for "inherit" (defaulting to not allowed).
	 * @param string $aGroupID - the ID of the group whose permissions will be
	 *   modified
	 * @param string $aNamespace - the namespace of the permission
	 * @param string $aPerm - the name of the permission
	 * @param string $aValue - the value: one of '+', 'x', '-', or null.
	 * @return array a dictionary of namespace, permission, group ID, and value
	 *   for the updated permission, in the order that those columns appear in
	 *   the database.
	 * @throws DbException if something goes wrong in the DB
	 * @throws BrokenLeg if one of the parameters is missing
	 */
	public function setPermission( $aGroupID, $aNamespace, $aPerm, $aValue=null )
	{
		if( empty( $aGroupID ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', 'group_id' ) ;
		if( empty( $aNamespace ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', 'namespace' ) ;
		if( empty( $aPerm ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', 'permission' ) ;
		//what db value should be stored?
		switch( $aValue ) {
			case static::VALUE_Allow:
			case static::FORM_VALUE_Allow:
				$theDBValue = static::VALUE_Allow ;
				break ;
			case static::VALUE_Deny:
			case static::FORM_VALUE_Deny:
				$theDBValue = static::VALUE_Deny ;
				break ;
			default: ;
				$theDBValue = static::VALUE_Disallow ;
				break ;
		}//switch
		//NOTE: PDO requires the parameter name be unique in parameterized queries; since
		//  SqlBuilder uses the "datakey" as the parameter name. See use of "name2".
		$theInsertParams = array(
			'namespace' => $aNamespace,
			'permission' => $aPerm,
			'group_id' => $aGroupID,
			'value' => $theDBValue,
		);
		$theUpdateParams = array(
			'name2' => $aNamespace,
			'perm2' => $aPerm,
			'gpid2' => $aGroupID,
			'valu2' => $theDBValue,
		);
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array_merge(
				$theInsertParams, $theUpdateParams
		));
		//SQL statement can assume no value is NULL.
		switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: //MySQL uses "INSERT ... ON DUPLICATE KEY UPDATE"
				$theSql->startWith('INSERT INTO')->add($this->tnPermissions);
				$this->setAuditFieldsOnInsert($theSql)
					->addParam('namespace')
					->addParam('permission')
					->addParam('group_id')
					->addParam('value')
					;
				$theSql->add('ON DUPLICATE KEY UPDATE');
				$this->addAuditFieldsForUpdate($theSql->setParamPrefix(' '))
					->addParamForColumn('name2', 'namespace')
					->addParamForColumn('perm2', 'permission')
					->addParamForColumn('gpid2', 'group_id')
					->addParamForColumn('valu2', 'value')
					;
				break;
			default:
				$theSql->startWith('MERGE')->add($this->tnPermissions);
				$theSql->add('WHEN NOT MATCHED BY TARGET')->add('INSERT');
				$this->setAuditFieldsOnInsert($theSql)
					->addParam('namespace')
					->addParam('permission')
					->addParam('group_id')
					->addParam('value')
					;
				$theSql->add('WHEN MATCHED THEN')->add('UPDATE');
				$this->addAuditFieldsForUpdate($theSql->setParamPrefix(' '))
					->addParamForColumn('name2', 'namespace')
					->addParamForColumn('perm2', 'permission')
					->addParamForColumn('gpid2', 'group_id')
					->addParamForColumn('valu2', 'value')
					;
				break;
		}//end switch
		try { $theSql->execDML() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
		return $theInsertParams;
	}

	/**
	 * Convert an old namespace to a new one.
	 */
	public function migrateNamespace($aOldNamespace, $aNewNamespace)
	{
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
				'namespace_old' => $aOldNamespace,
				'namespace_new' => $aNewNamespace,
		));
		$theSql->startWith('UPDATE')->add($this->tnPermissions);
		$this->setAuditFieldsOnUpdate($theSql);
		$theSql->addParamForColumn('namespace_new', 'namespace');
		$theSql->startWhereClause();
		$theSql->addParamForColumn('namespace_old', 'namespace');
		$theSql->endWhereClause();
		try
		{ $theSql->execDML(); }
		catch(PDOException $pdox) {
			throw new DbException($pdox, __METHOD__ . ' failed to convert permissions from ' .
					$aOldNamespace . ' to ' . $aNewNamespace
			);
		}
		//after changing permissions, affect the cache too
		if ( !empty($this->getDirector()->account_info) ) {
			$this->getDirector()->account_info->rights = null;
		}
	}
	
}//end class

}//end namespace
