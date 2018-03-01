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
use BitsTheater\costumes\IFeatureVersioning;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\WornForAuditFields;
use BitsTheater\costumes\WornForFeatureVersioning;
use BitsTheater\outtakes\RightsException ;
use BitsTheater\BrokenLeg ;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Arrays;
use com\blackmoonit\FinallyBlock;
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
	 * </ol>
	 * @var integer
	 */
	const FEATURE_VERSION_SEQ = 1; //always ++ when making db schema changes
	
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
	
	/** @var string The constant, assumed ID of the "unregistered user" group. */
	const UNREG_GROUP_ID = 'UNKNOWN' ;
	
	/** @var string The DB value meaning "allow" is '+'. */
	const VALUE_Allow = '+';
	/** @var string The DB value meaning "forbid" is 'x'. */
	const VALUE_Deny = 'x';
	/**
	 * If the value is missing from TABLE_Permissions, then it also means VALUE_Disallow.
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
	 * Keep a live cache while the current script runs to avoid hitting the db too often.
	 * @var array Stores "namespace/permission" as key to the permission value.
	 */
	public $_permCache = array();

	public function setupAfterDbConnected()
	{
		parent::setupAfterDbConnected();
		$this->tnGroups = $this->tbl_.self::TABLE_Groups;
		$this->tnGroupMap = $this->tbl_.self::TABLE_GroupMap;
		$this->tnGroupRegCodes = $this->tbl_.self::TABLE_GroupRegCodes;
		$this->tnPermissions = $this->tbl_.self::TABLE_Permissions;
	}
	
	/**
	 * @return string Returns the ID of the "titan" superuser group.
	 */
	public function getTitanGroupID()
	{ return $this->getDirector()->app_id; }

	/**
	 * Future db schema updates may need to create a temp table of one
	 * of the table definitions in order to update the contained data,
	 * putting schema here and supplying a way to provide a different name
	 * allows this process.
	 * @param string $aTABLEconst - one of the defined table name consts.
	 * @param string $aTableNameToUse - (optional) alternate name to use.
	 */
	protected function getTableDefSql($aTABLEconst, $aTableNameToUse=null)
	{
		switch($aTABLEconst) {
		case self::TABLE_Groups:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnGroups;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} " .
						'( group_id ' . CommonMySQL::TYPE_UUID . ' NOT NULL' .
						', group_num INT NULL' .
						', group_name VARCHAR(60) NOT NULL' .
						', parent_group_id ' . CommonMySql::TYPE_UUID . ' NULL' .
						', ' . CommonMySQL::getAuditFieldsForTableDefSql() .
						', PRIMARY KEY (group_id)' .
						', KEY (parent_group_id)' .
						', UNIQUE KEY (group_num)' .
						') ' . CommonMySQL::TABLE_SPEC_FOR_UNICODE;
			}//switch dbType
		case self::TABLE_GroupMap:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnGroupMap;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} " .
						'( auth_id ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', group_id ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', ' . CommonMySQL::getAuditFieldsForTableDefSql() .
						', PRIMARY KEY (auth_id, group_id)' .
						', UNIQUE KEY (group_id, auth_id)' .
						')';
			}//switch dbType
		case self::TABLE_GroupRegCodes:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnGroupRegCodes;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} " .
						'( group_id ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', reg_code VARCHAR(64) NOT NULL' .
						', ' . CommonMySQL::getAuditFieldsForTableDefSql() .
						', PRIMARY KEY (group_id, reg_code)' .
						', UNIQUE KEY (reg_code, group_id)' .
						') ' . CommonMySQL::TABLE_SPEC_FOR_UNICODE .
						" COMMENT='Auto-assign group_id if Registration Code matches reg_code'";
			}//switch dbType
		case self::TABLE_Permissions:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnPermissions;
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
		}//switch TABLE const
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
	 * If the groups table is empty, supply some default starter-data.
	 */
	protected function setupDefaultDataForGroups()
	{
		if ($this->isEmpty($this->tnGroups)) {
			// Start building the query until we have the audit field values...
			$theSql = SqlBuilder::withModel($this);
			$theSql->startWith('INSERT INTO')->add($this->tnGroups);
			$this->setAuditFieldsOnInsert($theSql);
			
			// Now use this to construct each of the rows of group data.
			// This works because audit fields are the only params set in the
			// SqlBuilder, from setAuditFieldsOnInsert().
			$theDefaultData =
					$this->buildDefaultGroupDataArray( $theSql->myParams ) ;
			
			// Now continue building the query.
			$theSql->mustAddParam( 'group_id' );
			$theSql->mustAddParam( 'group_name' );
			try
			{ $theSql->execMultiDML( $theDefaultData ); }
			catch (PDOException $pdox)
			{ throw $theSql->newDbException(__METHOD__, $pdox); }
		}
	}
	
	/**
	 * Constructs the group information for insertion into the database.
	 * @param array $aAuditFields A map of audit fields for the row.
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
					break;
				case 1:
					$theGroupID = $this->getTitanGroupID();
					break;
				default:
					$theGroupID = Strings::createUUID();
			}//end switch
			$theDefaultData[] = array_merge( array(
					'group_id' => $theGroupID,
					'group_num' => $theIdx++,
					'group_name' => $theGroupName,
			), $aAuditFields );
		}
		return $theDefaultData ;
	}
	
	/**
	 * If the group registration code table is empty,
	 * supply some default starter-data.
	 */
	protected function setupDefaultDataForGroupRegCodes()
	{
		//no default reg codes
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
			$this->setupDefaultDataForGroups();
			$this->setupDefaultDataForGroupRegCodes();
		}
	}

	/**
	 * Other models may need to query ours to determine our version number
	 * during Site Update. Without checking SetupDb, determine what version
	 * we may be running as.
	 * @param object $aScene - (optional) extra data may be supplied
	 */
	public function determineExistingFeatureVersion( $aScene )
	{
		switch ($this->dbType())
		{
			case self::DB_TYPE_MYSQL:
			default:
				if ( !$this->exists($this->tnGroups) ||
					!$this->exists($this->tnGroupMap) ||
					!$this->exists($this->tnGroupRegCodes) ||
					!$this->exists($this->tnPermissions)
				) return 0;
		}//switch
		return self::FEATURE_VERSION_SEQ ;
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
			{
				//next update goes here
			}
		}//switch
	}
	
	/**
	 * Use of this model might require migrating an existing BitsGroups model data.
	 * Based on AuthBasic models at the time of pre-v4.0.0 framework.
	 */
	protected function migrateFromBitsGroups()
	{
		$theTaskText = 'migrating schema to v4.0.0 AuthGroups model';
		$this->logStuff(__METHOD__, ' ', $theTaskText);
		$this->migrateFromBitsGroupsToAuthGroups();
		$this->migrateFromBitsGroupMapToAuthGroupMap();
		$this->migrateFromBitsGroupRegCodesToAuthGroupRegCodes();
		$this->migrateFromBitsPermissionsToAuthGroupPermissions();
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
						$theItem['parent_group_id'] > $dbOldAuthGroups::TITAN_GROUP_ID )
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
					case $dbOldAuthGroups::TITAN_GROUP_ID:
						$theNewRow['group_id'] = $this->getTitanGroupID();
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
				$theNewRow['group_num'] = $theItem['group_id'];
				$this->add($theNewRow);
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
					case $dbOldAuthGroups::TITAN_GROUP_ID:
						$theNewRow['group_id'] = $this->getTitanGroupID();
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
					case $dbOldAuthGroups::TITAN_GROUP_ID:
						$theNewRow['group_id'] = $this->getTitanGroupID();
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
					case $dbOldAuthGroups::TITAN_GROUP_ID:
						continue; //useless to assign rights to the Titan group, throw it away
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
	
	protected function exists($aTableName=null)
	{ return parent::exists( empty($aTableName) ? $this->tnGroups : $aTableName ); }

	public function isEmpty($aTableName=null)
	{ return parent::isEmpty( empty($aTableName) ? $this->tnGroups : $aTableName ); }
	
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
	 * Insert a group record.
	 * @param array|object $aDataObject - the (usually Scene) object containing
	 *   the data to be used.
	 * @return array Returns the data posted to the database.
	 */
	public function add($aDataObject)
	{
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aDataObject);
		$theSql->startWith('INSERT INTO')->add($this->tnGroups);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->addParam('group_id', Strings::createUUID())
			->mustAddParam('group_name', $theSql->getParam('group_id'))
			->mustAddParam('parent_group_id')
			->addParamIfDefined('group_num', null, \PDO::PARAM_INT)
			;
		//ensure parent_group_id is not "bad data"
		$theParentID = trim($theSql->getParam('parent_group_id'));
		if ( empty($theParentID) ||
				$theParentID == static::UNREG_GROUP_ID ||
				$theParentID == $this->getTitanGroupID()
		) {
			$theSql->setParam('parent_group_id', null);
		}
		//$theSql->logSqlDebug(__METHOD__);
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
	public function del($aGroupID)
	{
		$theSql = SqlBuilder::withModel($this);
		$theGroupID = trim($aGroupID);
		if ( $theGroupID == $this->getTitanGroupID() )
			return;  //trivially ignore attempts to delete the "Titan" group.
		$bWasInTransaction = $this->db->inTransaction();
		if ( !$bWasInTransaction )
			$this->db->beginTransaction();
		try {
			$theSql->startWith('DELETE FROM')->add($this->tnGroupRegCodes);
			$theSql->startWhereClause();
			$theSql->mustAddParam('group_id', $theGroupID);
			$theSql->endWhereClause();
			$theSql->execDML();
			
			$theSql->startWith('DELETE FROM')->add($this->tnGroupMap);
			$theSql->startWhereClause();
			$theSql->mustAddParam('group_id', $theGroupID);
			$theSql->endWhereClause();
			$theSql->execDML();
			
			$theSql->reset()->startWith('DELETE FROM')->add($this->tnGroups);
			$theSql->startWhereClause();
			$theSql->mustAddParam('group_id', $theGroupID);
			$theSql->endWhereClause();
			$theSql->execDML();

			if ( !$bWasInTransaction )
				$this->db->commit();
			return $theSql->myParams;
		}
		catch (PDOException $pdox)
		{
			if ( !$bWasInTransaction )
				$this->db->rollBack();
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
	 * Add a reg code record for a group.
	 * @param string $aGroupID - the group ID.
	 * @param string $aRegCode - the registration code.
	 * @return array Returns the data added.
	 * @throws DbException if an error happens in the query itself
	 */
	public function addRegCode($aGroupID, $aRegCode)
	{
		if ( $aGroupID == $this->getTitanGroupID() )
		{ throw new \InvalidArgumentException('invalid $aGroupID param'); }
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('INSERT INTO')->add($this->tnGroupRegCodes);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('group_id', $aGroupID);
		$theSql->mustAddParam('reg_code', $aRegCode);
		try
		{ return $theSql->execDMLandGetParams(); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}

	/**
	 * Add a reg code record for a group.
	 * @param string $aGroupID - the group ID.
	 * @param string[] $aRegCodes - the registration codes.
	 * @throws DbException if an error happens in the query itself
	 */
	public function addRegCodes($aGroupID, $aRegCodes)
	{
		if ( empty($aRegCodes) ) return; //trivial, nothing to insert
		if ( $aGroupID == $this->getTitanGroupID() )
		{ throw new \InvalidArgumentException('invalid $aGroupID param'); }
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('INSERT INTO')->add($this->tnGroupRegCodes);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('group_id', $aGroupID);
		$theSql->mustAddParam('reg_code', 'id-list');
		$theListToInsert = array();
		foreach ($aRegCodes as $theCode) {
			$theListToInsert[] = array(
					'created_ts' => $theSql->getParam('created_ts'),
					'created_by' => $theSql->getParam('created_by'),
					'updated_ts' => $theSql->getParam('updated_ts'),
					'updated_by' => $theSql->getParam('updated_by'),
					'group_id' => $theSql->getParam('group_id'),
					'reg_code' => $theCode,
			);
		}
		try
		{ $theSql->execMultiDML($theListToInsert); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
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
		if ( $aGroupID == $this->getTitanGroupID() )
		{ throw new \InvalidArgumentException('invalid $aGroupID param'); }
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
		if ( $aGroupID == $this->getTitanGroupID() )
		{ throw new \InvalidArgumentException('invalid $aGroupID param'); }
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
	 * @return string[] Returns the array of group IDs.
	 */
	public function getGroupIDListForAuth( $aAuthID )
	{
		if ( empty($aAuthID) )
		{ throw new \InvalidArgumentException('invalid $aAuthID param'); }
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('SELECT group_id FROM')->add($this->tnGroupMap);
		$theSql->startWhereClause()
			->mustAddParam('auth_id', $aAuthID)
			->endWhereClause()
			;
		try
		{ return Arrays::array_column($theSql->query()->fetchAll(), 'group_id'); }
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
			return $this->getGroupIDListForAuth($theAuthRow['auth_id']);
		}
		else //we may be in a state before migration took place
		{
			//NOTE: UNTIL MIGRATION TAKES PLACE, ONLY A TITAN CAN LOGIN
			//      AS WE HAVE NO DATA YET!
			/* @var $dbOldAuthGroups \BitsTheater\models\PropCloset\BitsGroups */
			$dbOldAuthGroups = $this->getProp(
					'\BitsTheater\models\PropCloset\BitsGroups'
			);
			//when return to caller, ensure we free up the old model object
			$theFinalBlock = new FinallyBlock(function($aContext, $aProp) {
				$aContext->returnProp($aProp);
			}, $this, $dbOldAuthGroups);
			if ( $dbOldAuthGroups->exists() && !$dbOldAuthGroups->isEmpty() )
			{
				$theList = $dbOldAuthGroups->getAcctGroups($aAcctId);
				//$this->logStuff(__METHOD__, ' ', $theList); //DEBUG
				if ( in_array($dbOldAuthGroups::TITAN_GROUP_ID, $theList) )
				{ return array( $this->getTitanGroupID() ); }
			}
		}
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
		if ( $theGroupParentId == static::UNREG_GROUP_ID ||
				$theGroupParentId == $this->getTitanGroupID() )
		{ $theGroupParentId = null; }
		if ( !($aGroupNum >= 0) )
			$aGroupNum = null;
		
		$theNewGroupID = Strings::createUUID();
		$theResults = $this->add(array(
				'group_id' => $theNewGroupID,
				'group_num' => $aGroupNum,
				'group_name' => $aGroupName,
				'parent_group_id' => $theGroupParentId,
		));

		$theNewRegCodeRow = $this->insertGroupRegCode($theNewGroupID, $aGroupRegCode);
		$theResults = array_merge($theResults, $theNewRegCodeRow);

		if ( !empty($aGroupCopyID) )
		{
			$dbPerms = $this->getProp('Permissions') ;
			try
			{
				$theCopyResult = $dbPerms->copyPermissions($aGroupCopyID, $theNewGroupID);
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
	 * Updates an existing group.
	 * @param object $aDataObject - the (usually Scene) object containing
	 *   the data to be used.
	 * @return array|boolean Returns FALSE if not found or array of updated data.
	 * @throws DbException if an error happens in the query itself
	 */
	public function modifyGroup( $aDataObject )
	{
		if ( empty($aDataObject) || empty($aDataObject->group_id) )
			return false; //trivially reject bad calls
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aDataObject);
		$theSql->startWith('UPDATE')->add($this->tnGroups);
		$this->setAuditFieldsOnUpdate($theSql);
		$theSql->addParamIfDefined('group_name');
		$theSql->addParamIfDefined('parent_group_id');
		$theSql->addParamIfDefined('group_num', \PDO::PARAM_INT);
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
	 * Fetches the registration code for a given group ID.
	 * @param string $aGroupID the group ID
	 * @return string Returns the registration code for that group
	 * @throws DbException if an error happens in the query itself
	 */
	protected function getGroupRegCode( $aGroupID )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT reg_code FROM ' . $this->tnGroupRegCodes )
			->startWhereClause()
			->mustAddParam( 'group_id', $aGroupID )
			->endWhereClause()
			;
		try { $theRow = $theSql->getTheRow() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
		return $theRow['reg_code'] ;
	}

	/**
	 * Insert a new registration code for an existing group ID.
	 * @param string $aGroupID - the group ID.
	 * @param string $aRegCode - the new registration code.
	 * @return array|boolean Returns FALSE if bad data or array of inserted data.
	 * @throws DbException if an error happens in the query itself
	 */
	public function insertGroupRegCode( $aGroupID, $aRegCode )
	{
		$theGroupID = trim($aGroupID);
		$theRegCode = trim($aRegCode);
		if ( empty($theGroupID) || $theGroupID == static::UNREG_GROUP_ID ||
				$theGroupID == $this->getTitanGroupID() || empty($theRegCode) )
		{ return false; } //trivially reject bad data
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
				'group_id' => $theGroupID,
				'reg_code' => $theRegCode,
		));
		$theSql->startWith('INSERT INTO')->add($this->tnGroupRegCodes);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('group_id');
		$theSql->mustAddParam('reg_code');
		try { return $theSql->execDMLandGetParams(); }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ); }
	}

	/**
	 * Get the list of reg code for a given group.
	 * @return string[] Return array of reg codes.
	 */
	public function getGroupRegCodes( $aGroupID=null )
	{
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('SELECT * FROM')->add($this->tnGroupRegCodes);
		$theSql->startWhereClause()
			->mustAddParam('group_id', $aGroupID)
			->endWhereClause()
			;
		try {
			$ps = $theSql->query();
			return Arrays::array_column($ps->fetchAll(), 'reg_code');
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
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('SELECT group_id FROM')->add($this->tnGroupRegCodes);
		$theSql->startWhereClause()
			->mustAddParam('reg_code', trim($aRegCode))
			->endWhereClause()
			;
		$theRow = $theSql->getTheRow();
		return (!empty($theRow)) ? $theRow['group_id'] : static::UNREG_GROUP_ID;
	}

	/**
	 * Returns a dictionary of all permission group data.
	 * @param $bIncludeSystemGroups boolean indicates whether to include the
	 *  "unregistered" and "titan" groups that are defined by default when the
	 *  system is installed
	 * @throws DbException if an error happens in the query itself
	 */
	public function getAllGroups( $bIncludeSystemGroups=false )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT G.group_id, G.group_name,' )
			->add( 'G.parent_group_id, GRC.reg_code' )
			->add( 'FROM ' . $this->tnGroups . ' AS G' )
			->add( 'LEFT JOIN ' . $this->tnGroupRegCodes )
			->add(   'AS GRC USING (group_id)' )
			;
		if ( !$bIncludeSystemGroups )
		{
			$theSql->startWhereClause()
				->setParamOperator(SqlBuilder::OPERATOR_NOT_EQUAL)
			 	->addFieldAndParam('group_id', 'unreg_group_id', static::UNREG_GROUP_ID)
			 	->setParamPrefix(' AND ')
				->addFieldAndParam('group_id', 'titan_group_id', $this->getTitanGroupID())
				->endWhereClause()
				;
		}
		$theSql->add( 'ORDER BY G.group_name' ) ;

		try { return $theSql->query()->fetchAll() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
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
	 *   "unregistered" and "titan" groups that are defined by default when the
	 *   system is installed
	 * @param string[]|string $aFieldList - (optional) which fields to return,
	 *   the default is all of them.
	 * @param bool[] $aSortList - (optional) how to sort the data, use array
	 *   format of <code>array[fieldname => ascending=true]</code>.
	 * @throws DbException if an error happens in the query itself
	 */
	public function getListForPicker( $bIncludeSystemGroups=false,
			$aFieldList=null, $aSortList=null )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith('SELECT')->addFieldList($aFieldList)
			->add('FROM')->add($this->tnGroups)
		;
		if ( !$bIncludeSystemGroups ) {
			$theSql->startWhereClause();
			$theSql->setParamOperator(SqlBuilder::OPERATOR_NOT_EQUAL);
			$theSql->addFieldAndParam('group_id', 'unreg_group_id', static::UNREG_GROUP_ID);
			$theSql->setParamPrefix(' AND ');
			$theSql->addFieldAndParam('group_id', 'titan_group_id', $this->getTitanGroupID());
			$theSql->endWhereClause();
		}
		$theSql->applyOrderByList( $aSortList ) ;
		//$theSql->logSqlDebug(__METHOD__);
		try { return $theSql->query() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}
	
	//=========================================================================
	//===============  AuthPermissions           ==============================
	//=========================================================================
	
	/**
	 * Check group permissions to see if current user account (or passed in one) is allowed.
	 * @param string $aNamespace - namespace of permission.
	 * @param string $aPermission - permission to test against.
	 * @param array $acctInfo - account information (optional, defaults current user).
	 * @return boolean Returns TRUE if allowed, else FALSE.
	 */
	public function isPermissionAllowed($aNamespace, $aPermission, $acctInfo=null) {
		if ( empty($acctInfo) ) {
			$acctInfo = $this->getDirector()->getMyAccountInfo();
		}
		if ( empty($acctInfo) )
		{
			$acctInfo = $this->getProp('Auth')->createAccountInfoObj(array(
					'auth_id' => '',
					'groups' => array( static::UNREG_GROUP_ID ),
			));
		}
		//$this->logStuff(__METHOD__, ' acctinfo=', $acctInfo); //DEBUG
		if ( !empty($acctInfo->groups) &&
				(array_search($this->getTitanGroupID(), $acctInfo->groups, true) !== false) )
		{ return true; } //Titan group is always allowed everything
		//cache the current users permissions
		if ( empty($this->_permCache[$acctInfo->auth_id]) )
		{
			$this->_permCache[$acctInfo->auth_id] = array();
			try {
				foreach ($acctInfo->groups as $theGroupId) {
					$this->_permCache[$acctInfo->auth_id][$theGroupId] =
							$this->getAssignedRights($theGroupId);
				}
			} catch (DbException $dbe) {
				//use default empty arrays which will mean all permissions will be not allowed
			}
		}
		//$this->debugLog('perms:'.$this->debugStr($this->_permCache));
        //$this->debugLog(__METHOD__.' '.memory_get_usage(true));

		//if any group allows the permission, then we allow it.
		foreach ($this->_permCache[$acctInfo->auth_id] as $theGroupId => $theAssignedRights)
		{
			$theResult = static::FORM_VALUE_Disallow;
			if ( !empty($theAssignedRights[$aNamespace]) &&
					!empty($theAssignedRights[$aNamespace][$aPermission]) )
			{ $theResult = $theAssignedRights[$aNamespace][$aPermission]; }
			//if any group the user is a member of allows the permission, then return true
			if ( $theResult==static::FORM_VALUE_Allow )
			{ return true; }
		}
		//if neither denied, nor allowed, then it is not allowed
		return false;
	}

	/**
	 * Query the permissions table for group rights.
	 * @param string $aGroupID - the group id to load.
	 * @return \PDOStatement Returns the executed query statement.
	 * $throws \PDOException if query causes an exception
	 */
	protected function getGroupRightsCursor( $aGroupID )
	{
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
				'group_id' => $aGroupID,
		));
		$theSql->startWith('SELECT namespace, permission, value')
			->add('FROM')->add($this->tnPermissions)
			->startWhereClause()
			->mustAddParam('group_id', static::UNREG_GROUP_ID)
			->endWhereClause()
			;
		return $theSql->query();
	}

	/**
	 * Load the group rights and merge it into the passed in array;
	 * loaded "deny" rights will trump array param.
	 * @param string $aGroupID - the group id
	 * @param array $aRightsToMerge - the already defined rights.
	 */
	protected function loadAndMergeRights( $aGroupID, &$aRightsToMerge, $bIsFirstSet )
	{
		$rs = $this->getGroupRightsCursor($aGroupID);
		while ( $rs!=null && ($theRow = $rs->fetch())!==false )
		{
			//what is the current value, if any?
			$theCurrValue =& $aRightsToMerge[$theRow['namespace']][$theRow['permission']];
			switch ( $theCurrValue ) {
				case static::FORM_VALUE_Deny:
				case static::FORM_VALUE_DoNotShow:
				{
					if ( $bIsFirstSet ) {
						//a new parent group may permit a once denied permission, reset to Disallow
						$theCurrValue = static::FORM_VALUE_Disallow;
					}
					else {
						//once denied, futher parent merges will be ignored
						continue;
					}
					break;
				}
				case static::FORM_VALUE_Allow:
				{
					//once a group-with-parents allows a permission, another
					//  group membership will not revoke it.
					if ( $bIsFirstSet )
					{ continue; }
					break;
				}
				default:
					//do nothing, check out the new value
			}//switch
			//what will the new value be, if any?
			switch ( $theRow['value'] ) {
				case static::VALUE_Allow:
					$theCurrValue = static::FORM_VALUE_Allow;
					break;
				case static::VALUE_Deny:
					$theCurrValue = ($bIsFirstSet)
							? static::FORM_VALUE_Deny : static::FORM_VALUE_DoNotShow;
					break;
				case static::VALUE_Disallow:
				default:
					//only set to "disallow" if empty as it will not overwrite other values
					if ( empty($theCurrValue) )
					{ $theCurrValue = static::FORM_VALUE_Disallow; }
					break;
			}//switch
		}//while
	}
	
	/**
	 * Given a list of groups and a groupID, return it and its list of parents.
	 * The resulant list will be the groupID, followed by entries for each parent.
	 * @param string $aGroupID - the ID of the group.
	 * @param array $aGroupList - the group row list with group_id as key.
	 * @return string[] Returns a list of IDs whose first entry is the $aGroupID
	 *   parameter followed by each parent, in turn.
	 */
	protected function getGroupParentsFromList($aGroupID, &$aGroupList)
	{
		if ( is_null($aGroupID) ) return array(); //trivial
		$theResultList = array($aGroupID);
		$theGroupID = $aGroupID;
		while ( !empty($aGroupList[$theGroupID]) &&
				!empty($aGroupList[$theGroupID]['parent_group_id']) )
		{
			$theParentGroupID = $aGroupList[$theGroupID]['parent_group_id'];
			if ( empty($theParentGroupID) ) break; //found them all, done!
			//avoid circular parent references (infinite loop)
			if ( array_search($theParentGroupID, $theResultList) !== false )
			{
				//break the circular link to avoid future issues
				$aGroupList[$theGroupID]['parent_group_id'] = null;
				$this->modifyGroup($aGroupList[$theGroupID]);
				break;
			}
			//ok, parent is found, add to list
			$theResultList[] = $theParentGroupID;
			//now let us find its parent next
			$theGroupID = $theParentGroupID;
		}
		return $theResultList;
	}
	
	/**
	 * Load up all rights assigned to this group as well as parent groups.
	 * @param string|array $aGroupIDorList - a group ID or an array of IDs.
	 * @return array Returns the assigned rights for a given group.
	 */
	public function getAssignedRights( $aGroupIDorList )
	{
		if ( is_string($aGroupIDorList) )
		{ $theGroups = array($aGroupIDorList); }
		else
		{ $theGroups = $aGroupIDorList; }
		//$this->logStuff(__METHOD__, ' groupsToCheck=', $theGroups); //DEBUG
		
		//is Titan one of the groups?
		if ( array_search($this->getTitanGroupID(), $theGroups) !== false )
		{ return $this->getTitanRights(); } //trivial shortcut
		
		//get all defined groups in an ID list (Titan is never a defined group).
		$theGroupList = Arrays::array_column_as_key(
				SqlBuilder::withModel($this)
					->startWith('SELECT * FROM')->add($this->tnGroups)
					->query()->fetchAll(),
				'group_id'
		);
		//$this->logStuff(__METHOD__, ' grouplist=', $theGroupList); //DEBUG
		
		//merge list: [group_id=>string, bIsParentOfPreviousEntry=>boolean]
		$theMergeList = array();
		foreach ( $theGroups as $theGroupID )
		{
			$theGroupAndParents = $this->getGroupParentsFromList($theGroupID, $theGroupList);
			$i = -1; //start off negative; naturally works out as <0 is orig, >=0 is parent
			foreach( $theGroupAndParents as $theGroupIDToMerge)
			{
				//check rights for group, and then all its parents
				$theMergeList[] = array(
						'group_id' => $theGroupID,
						'bIsParentOfPreviousEntry' => ($i++ < 0),
				);
				
			}
		}
		//$this->logStuff(__METHOD__, ' merge=', $theMergeList); //DEBUG
		
		$theAssignedRights = array();
		//auth model is IF ONE GROUP ALLOWS IT, THE RIGHT IS ALLOWED
		foreach ($theMergeList as $theMergeEntry)
		{
			$this->loadAndMergeRights($theMergeEntry['group_id'],
					$theAssignedRights, $theMergeEntry['bIsParentOfPreviousEntry']
			);
		}
		return $theAssignedRights;
	}

	/**
	 * Like getAssignedRights(), but returns only the rights that are allowed
	 * for the group, as a Boolean "true"; any right that is in 'disallow' or
	 * 'deny' state is omitted from the result set.
	 * @param string|array $aGroupID - a group ID or an array of IDs.
	 * @return object Return an object with just TRUE permissions.
	 */
	public function getGrantedRights( $aGroupID=null )
	{
		$theAssignedRights = $this->getAssignedRights($aGroupID);
		//$this->logStuff(__METHOD__, ' getAR=', $theAssignedRights); //DEBUG
		// Now go back and remove everything that's not allowed and set allowed to TRUE.
		foreach( $theAssignedRights as $theSpace => &$theSpacePerms )
		{
			foreach( $theSpacePerms as $thePerm => &$theVal )
			{
				switch ( $theVal ) {
					case static::FORM_VALUE_Allow:
						$theVal = true;
						break;
					default:
						unset( $theSpacePerms[$thePerm] ) ;
						break;
				}//switch
			}
			if ( count($theSpacePerms) == 0  )
				unset( $theAssignedRights[$theSpace] ) ;
		}
		return ((object)($theAssignedRights)) ;
	}

	/**
	 * Build up a giant tree of all the permissions, all marked as true.
	 */
	protected function getTitanRights()
	{
		$theTitanPerms = array() ;
		$theSpaces = $this->getRes( 'permissions/namespace' ) ;
		foreach( $theSpaces as $theSpace => $theNSInfo )
		{
			$theTitanPerms[$theSpace] = array() ;
			$thePerms = $this->getRes( 'permissions/' . $theSpace ) ;
			foreach( $thePerms as $thePerm => $thePermInfo )
				$theTitanPerms[$theSpace][$thePerm] = true ;
		}
		//$this->logStuff(__METHOD__, $theTitanPerms); //DEBUG
		return $theTitanPerms ;
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
		
		//remove existing permissions
		//NOTE: introducing VALUE_Disallow removed the need to delete permissions.
		//$this->removeGroupPermissions($aDataObject->group_id);

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
					case static::FORM_VALUE_Deny:
						$theValue = static::VALUE_Deny;
					default:
						$theValue = static::VALUE_Disallow;
				}//switch
				$theRightsList[] = array(
						'ns' => $ns,
						'perm' => $theRight,
						'group_id' => $aDataObject->group_id,
						'value' => $theValue,
				);
			}//end foreach
		}//end foreach
		if ( !empty($theRightsList) )
		{
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aDataObject);
			$theSql->startWith('INSERT INTO')->add($this->tnPermissions);
			$this->setAuditFieldsOnInsert($theSql);
			$theSql->mustAddFieldAndParam('namespace', 'ns')
				->mustAddFieldAndParam('permission', 'perm')
				->mustAddParam('group_id')
				->mustAddParam('value')
				->execMultiDML($theRightsList)
				;
		}
	}

	/**
	 * Returns a raw SELECT * from the permission/group mapping table.
	 * @param $bIncludeSystemGroups boolean indicates whether to include the
	 *  "unregistered" and "titan" groups that are defined by default when the
	 *  system is installed
	 * @throws DbException if a problem occurs during DB query execution
	 * @return array a table of rows from the DB
	 */
	public function getPermissionMap( $bIncludeSystemGroups=false )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith('SELECT')
			->add('group_id, namespace AS ns, permission, value')
			->add('FROM')->add($this->tnPermissions)
			;
		if( ! $bIncludeSystemGroups )
		{
			$theSql->startWhereClause()
				->setParamOperator( SqlBuilder::OPERATOR_NOT_EQUAL )
			 	->addFieldAndParam( 'group_id', 'unreg_group_id', static::UNREG_GROUP_ID )
			 	->setParamPrefix( ' AND ' )
				->addFieldAndParam( 'group_id', 'titan_group_id', $this->getTitanGroupID() )
				->endWhereClause()
				;
		}
		$theSql->add( ' ORDER BY group_id, namespace, permission' ) ;
		try { return $theSql->query()->fetchAll() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox) ; }
	}

	/**
	 * Copies permissions for one group to another group.
	 * Consumed by the createGroup() function in the AuthGroups model.
	 * @param string $aSourceGroupID the source of the permissions
	 * @param string $aTargetGroupID the target for the permissions
	 * @return array indication of the result
	 * @throws DbException if a problem occurs during DB query execution
	 * @throws RightsException if either source or target is not specified, or
	 *  not found, or is the "titan" group
	 */
	public function copyPermissions( $aSourceGroupID, $aTargetGroupID )
	{
		//source group ID
		if( ! isset( $aSourceGroupID ) )
		{ throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', '$aSourceGroupID' ) ; }
		if( $aSourceGroupID == $this->getTitanGroupID() )
		{ throw RightsException::toss( $this, 'CANNOT_COPY_FROM_TITAN' ) ; }
		$theSourceGroupRow = $this->getGroup($aSourceGroupID, 'group_name');
		if ( empty($theSourceGroupRow) )
		{ throw RightsException::toss( $this, 'GROUP_NOT_FOUND', $aSourceGroupID ) ; }
		//target group ID
		if( ! isset( $aTargetGroupID ) )
		{ throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', '$aTargetGroupID' ) ; }
		if( $aTargetGroupID == $this->getTitanGroupID() )
		{ throw RightsException::toss( $this, 'CANNOT_COPY_TO_TITAN' ) ; }
		$theTargetGroupRow = $this->getGroup($aTargetGroupID, 'group_name');
		if ( empty($theTargetGroupRow) )
		{ throw RightsException::toss( $this, 'GROUP_NOT_FOUND', $aTargetGroupID ) ; }
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
					. ' failed to insert [' . $theCount
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
	 * triple. The values are '+' for "always allow", null for "inherit", and
	 * '-' for "always deny".
	 * @param string $aGroupID the ID of the group whose permissions will be
	 *  modified
	 * @param string $aNamespace the namespace of the permission
	 * @param string $aPerm the name of the permission
	 * @param string $aValue the value: one of '+', '-', or null
	 * @return array a dictionary of namespace, permission, group ID, and value
	 *  for the updated permission, in the order that those columns appear in
	 *  the database
	 * @throws DbException if something goes wrong in the DB
	 * @throws BrokenLeg if one of the parameters is missing
	 * @throws RightsException if the group ID is that of the "titan" group
	 */
	public function setPermission( $aGroupID, $aNamespace, $aPerm, $aValue=null )
	{
		if( empty( $aGroupID ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', 'group_id' ) ;
		if( empty( $aNamespace ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', 'namespace' ) ;
		if( empty( $aPerm ) )
			throw BrokenLeg::toss( $this, 'MISSING_ARGUMENT', 'permission' ) ;
		if( $aGroupID == $this->getTitanGroupID() )
			throw RightsException::toss( $this, 'CANNOT_MODIFY_TITAN' ) ;
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
		//  SqlBuilder uses the "datakey" as the parameter name. See use of "ns2".
		$theInsertParams = array(
			'namespace' => $aNamespace,
			'permission' => $aPerm,
			'group_id' => $aGroupID,
			'value' => $theDBValue,
		);
		$theUpdateParams = array(
			'ns2' => $aNamespace,
			'perm2' => $aPerm,
			'gid2' => $aGroupID,
			'val2' => $theDBValue,
		);
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array_merge(
				$theInsertParams, $theUpdateParams
		));
		switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: //MySQL uses "INSERT ... ON DUPLICATE KEY UPDATE"
				$theSql->startWith('INSERT INTO')->add($this->tnPermissions);
				$this->setAuditFieldsOnInsert($theSql)
					->mustAddParam('namespace')
					->mustAddParam('permission')
					->mustAddParam('group_id')
					->mustAddParam('value')
					;
				$theSql->add('ON DUPLICATE KEY UPDATE');
				$this->addAuditFieldsForUpdate($theSql->setParamPrefix(' '))
					->mustAddFieldAndParam('namespace', 'ns2')
					->mustAddFieldAndParam('permission', 'perm2')
					->mustAddFieldAndParam('group_id', 'gid2')
					->mustAddFieldAndParam('value', 'val2')
					;
				break;
			default:
				$theSql->startWith('MERGE')->add($this->tnPermissions);
				$theSql->add('WHEN NOT MATCHED BY TARGET')->add('INSERT');
				$this->setAuditFieldsOnInsert($theSql)
					->mustAddParam('namespace')
					->mustAddParam('permission')
					->mustAddParam('group_id')
					->mustAddParam('value')
					;
				$theSql->add('WHEN MATCHED THEN')->add('UPDATE');
				$this->addAuditFieldsForUpdate($theSql->setParamPrefix(' '))
					->mustAddFieldAndParam('namespace', 'ns2')
					->mustAddFieldAndParam('permission', 'perm2')
					->mustAddFieldAndParam('group_id', 'gid2')
					->mustAddFieldAndParam('value', 'val2')
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
		$theSql->mustAddFieldAndParam('namespace', 'namespace_new');
		$theSql->startWhereClause();
		$theSql->mustAddFieldAndParam('namespace', 'namespace_old');
		$theSql->endWhereClause();
		try
		{ $theSql->execDML(); }
		catch(PDOException $pdox) {
			throw new DbException($pdox, __METHOD__ . ' failed to convert permissions from ' .
					$aOldNamespace . ' to ' . $aNewNamespace
			);
		}
		//after changing permissions, affect the cache too
		$this->_permCache = array();
		$this->isPermissionAllowed($aNewNamespace, '');
	}
	
}//end class

}//end namespace
