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
use BitsTheater\models\PropCloset\AuthBase as BaseModel;
use BitsTheater\costumes\colspecs\CommonMySql;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\costumes\DbAdmin;
use BitsTheater\costumes\DbConnInfo;
use BitsTheater\costumes\HttpAuthHeader;
use BitsTheater\costumes\IFeatureVersioning;
use BitsTheater\costumes\ISqlSanitizer;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\WornByIDirectedForValidation;
use BitsTheater\costumes\WornForAuditFields;
use BitsTheater\costumes\WornForFeatureVersioning;
use BitsTheater\costumes\AuthPasswordReset;
use BitsTheater\models\AuthGroups as AuthGroupsDB;
use BitsTheater\outtakes\AccountAdminException;
use BitsTheater\outtakes\PasswordResetException;
use BitsTheater\Scene;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Strings;
use Exception;
use PDO;
use PDOException;

{//begin namespace

/**
 * AuthOrgs is a special beast where it combines Accounts & Auth tables,
 * and then extends the logic so that each account will auto
 * change what some db connection information.
 * @since BitsTheater v4.0.0
 */
class AuthOrgs extends BaseModel implements IFeatureVersioning
{
	use WornForFeatureVersioning, WornForAuditFields, WornByIDirectedForValidation;

	/**
	 * Used by meta data mechanism to keep the database up-to-date with the code.
	 * A non-NULL string value here means alter-db-schema needs to be managed.
	 * @var string
	 */
	const FEATURE_ID = 'BitsTheater/AuthOrgs';
	/**
	 * The schema version for this model. Always increment this when making
	 * changes to the schema.
	 * <ol type="1">
	 *  <li value="1">
	 *   Initial schema design.
	 *  </li>
	 *  <li value="2">
	 *   Add Org <span style="font-family:monospace">`dbconn`</span> string field.
	 *  </li>
	 * </ol>
	 * @var integer
	 */
	const FEATURE_VERSION_SEQ = 2; //always ++ when making db schema changes

	const TYPE = 'multitenant';
	const ALLOW_REGISTRATION = true;
	const REGISTRATION_SUCCESS = 0;
	const REGISTRATION_NAME_TAKEN = 1;
	const REGISTRATION_EMAIL_TAKEN = 2;
	const REGISTRATION_REG_CODE_FAIL = 3;
	const REGISTRATION_UNKNOWN_ERROR = 4;
	const REGISTRATION_CAP_EXCEEDED = 5;

	const REGISTRATION_ASK_EMAIL = true;
	const REGISTRATION_ASK_PW = true;

	const KEY_cookie = 'seasontickets';
	const KEY_token = 'ticketmaster';
	const KEY_MobileInfo = 'ticketenvelope';

	/**
	 * Add our database name before the defined table prefix so we can work
	 * with multiple databases at once.
	 * @var boolean This value is TRUE as the intention here is to work with multiple dbs.
	 */
	const TABLE_PREFIX_INCLUDES_DB_NAME = true;

	public $tnAuthAccounts;		const TABLE_AuthAccounts = 'auth_accounts';
	public $tnAuthTokens;		const TABLE_AuthTokens = 'auth_tokens';
	public $tnAuthMobile;		const TABLE_AuthMobile = 'auth_mobile';
	public $tnAuthOrgs;			const TABLE_AuthOrgs = 'auth_orgs';
	public $tnAuthOrgMap;		const TABLE_AuthOrgMap = 'auth_org_map';
	/** @deprecated (backwards compatible alias for tnAuthAccounts */
	public $tnAccounts;
	/** @deprecated (backwards compatible alias for tnAuthAccounts */
	public $tnAuth;
	/** @deprecated (backwards compatible alias for TABLE_AuthAccounts */
	const TABLE_Accounts = self::TABLE_AuthAccounts;
	/** @deprecated (backwards compatible alias for TABLE_AuthAccounts */
	const TABLE_Auth = self::TABLE_AuthAccounts;

	/**
	 * A Cookie's token prefix.
	 * @var string
	 */
	const TOKEN_PREFIX_COOKIE = 'cA';
	/**
	 * A Mobile Auth's token prefix.
	 * @var string
	 */
	const TOKEN_PREFIX_MOBILE = 'mA';
	/**
	 * A Login Lockout's token prefix.
	 * @var string
	 */
	const TOKEN_PREFIX_LOCKOUT = 'lO';
	/**
	 * A Registration Cap's token prefix.
	 * @var string
	 */
	const TOKEN_PREFIX_REGCAP = 'rC';
	/**
	 * A CSRF protection token prefix.
	 * @var string
	 */
	const TOKEN_PREFIX_ANTI_CSRF = 'aC';
	/**
	 * A mobile hardware ID to account mapping token prefix.
	 * @var string
	 * @since BitsTheater 3.6.1
	 */
	const TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT = 'hwid2acct';

	public function setupAfterDbConnected()
	{
		parent::setupAfterDbConnected();
		$this->tnAuthAccounts = $this->tbl_.self::TABLE_AuthAccounts;
		$this->tnAuthTokens = $this->tbl_.self::TABLE_AuthTokens;
		$this->tnAuthMobile = $this->tbl_.self::TABLE_AuthMobile;
		$this->tnAuthOrgs = $this->tbl_.self::TABLE_AuthOrgs;
		$this->tnAuthOrgMap = $this->tbl_.self::TABLE_AuthOrgMap;
		//backwards compatible aliases
		$this->tnAccounts = $this->tbl_.self::TABLE_Accounts;
		$this->tnAuth = $this->tbl_.self::TABLE_Auth;
	}

	/**
	 * @return AuthGroupsDB Returns the database model reference.
	 */
	protected function getAuthGroupsProp()
	{ return $this->getProp(AuthGroupsDB::MODEL_NAME); }

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
		case self::TABLE_AuthAccounts:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthAccounts;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} " .
						'( auth_id ' . CommonMySQL::TYPE_UUID . " NOT NULL COMMENT 'cross-db ID'" .
						", account_id INT NOT NULL AUTO_INCREMENT COMMENT 'user-friendly ID'" .
						', account_name VARCHAR(60) NOT NULL' .
						', email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL' .
						', pwhash ' . CommonMySQL::TYPE_ASCII_CHAR(85) . ' NOT NULL' . //blowfish hash of pw & its salt
						', external_id ' . CommonMySql::TYPE_UUID . ' NULL' .
						', verified_ts TIMESTAMP NULL' . //useless until email verification implemented
						', last_seen_ts TIMESTAMP NULL' .
						', is_active ' . CommonMySQL::TYPE_BOOLEAN_1 .
						', ' . CommonMySQL::getAuditFieldsForTableDefSql() .
						', PRIMARY KEY (auth_id)' .
						', UNIQUE KEY (account_id)' .
						', UNIQUE KEY (account_name)' .
						', UNIQUE KEY (email)' .
						', KEY (external_id)' .
						') ' . CommonMySQL::TABLE_SPEC_FOR_UNICODE;
			}//switch dbType
		case self::TABLE_AuthTokens:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthTokens;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						'( `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY' . //strictly for phpMyAdmin ease of use
						', `auth_id` ' . CommonMySQL::TYPE_UUID . ' NOT NULL' .
						', `account_id` INT NULL' .
						', `token` ' . CommonMySQL::TYPE_ASCII_CHAR(128) . ' NOT NULL' .
						', ' . CommonMySQL::getAuditFieldsForTableDefSql() .
						', INDEX IdxAuthIdToken (`auth_id`, `token`)' .
						', INDEX IdxAcctIdToken (`account_id`, `token`)' .
						', INDEX IdxAuthToken (`token`, `updated_ts`)' .
						')';
			}//switch dbType
		case self::TABLE_AuthMobile: //added in v3
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthMobile;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						'( `mobile_id` ' . CommonMySQL::TYPE_UUID . ' NOT NULL' .
						', `auth_id` ' . CommonMySQL::TYPE_UUID . ' NOT NULL' .
						', `account_id` INT NULL' .
						', `auth_type` ' . CommonMySQL::TYPE_ASCII_CHAR(16) . " NOT NULL DEFAULT 'FULL_ACCESS'" .
						', `account_token` ' . CommonMySQL::TYPE_ASCII_CHAR(64) . " NOT NULL DEFAULT 'STRANGE_TOKEN'" .
						', `device_name` VARCHAR(64) NULL' .
						', `latitude` DECIMAL(11,8) NULL' .
						', `longitude` DECIMAL(11,8) NULL' .
						', `fingerprint_hash` ' . CommonMySQL::TYPE_ASCII_CHAR(85) . ' NULL' .
						', ' . CommonMySQL::getAuditFieldsForTableDefSql() .
						', PRIMARY KEY (`mobile_id`)' .
						', KEY (`auth_id`)' .
						', KEY (`account_id`)' .
						')';
			}//switch dbType
		case self::TABLE_AuthOrgs:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthOrgs;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} " .
						'( org_id ' . CommonMySQL::TYPE_UUID . ' NOT NULL' .
						', org_name VARCHAR(60) NOT NULL' . " COMMENT 'e.g. acmelabs'" .
						', org_title VARCHAR(200) NULL' . " COMMENT 'e.g. Acme Labs, LLC'" .
						', org_desc VARCHAR(2048) NULL' .
						', parent_org_id ' . CommonMySql::TYPE_UUID . ' NULL' .
						', dbconn VARCHAR(1020) NULL' .
						', ' . CommonMySQL::getAuditFieldsForTableDefSql() .
						', PRIMARY KEY (org_id)' .
						', KEY (parent_org_id)' .
						', UNIQUE KEY (org_name)' .
						') ' . CommonMySQL::TABLE_SPEC_FOR_UNICODE;
			}//switch dbType
		case self::TABLE_AuthOrgMap:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthOrgMap;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} " .
						'( auth_id ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', org_id ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', ' . CommonMySQL::getAuditFieldsForTableDefSql() .
						', PRIMARY KEY (auth_id, org_id)' .
						', UNIQUE KEY (org_id, auth_id)' .
						')';
			}//switch dbType
		}//switch TABLE const
	}

	/**
	 * Called during website installation and db re-setupDb feature.
	 * Never assume the database is empty.
	 */
	public function setupModel()
	{
		$this->setupTable( self::TABLE_AuthAccounts, $this->tnAuthAccounts ) ;
		$this->setupTable( self::TABLE_AuthTokens, $this->tnAuthTokens ) ;
		$this->setupTable( self::TABLE_AuthMobile, $this->tnAuthMobile ) ;
		$this->setupTable( self::TABLE_AuthOrgs, $this->tnAuthOrgs ) ;
		$this->setupTable( self::TABLE_AuthOrgMap, $this->tnAuthOrgMap ) ;
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
				if ( !$this->exists($this->tnAuthAccounts) ||
					!$this->exists($this->tnAuthTokens) ||
					!$this->exists($this->tnAuthMobile) ||
					!$this->exists($this->tnAuthOrgs) ||
					!$this->exists($this->tnAuthOrgMap)
				) return 0;
				else if ( !$this->isFieldExists('dbconn', $this->tnAuthOrgs) )
					return 1;
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
				// determineExistingFeatureVersion. Here we just re-create the feature.
				$this->setupModel();
				// Existing website might be using older BitsGroups model, migrate the data.
				$this->migrateFromBitsBasic();
				// Since setupModel() will create the latest version of the feature,
				// there is no need to run through the rest of the version updates.
				break;
				// For every other $theSeq case, it needs to fall through to the next.
			}
			case ( $theSeq < 2 ):
			{
				$this->addFieldToTable(2, 'dbconn', $this->tnAuthOrgs,
						'dbconn VARCHAR(1020) NULL',
						'parent_org_id'
				);
			}
			case ( $theSeq < 3 ):
			{
				// Next update goes here.
			}
		}//switch
	}

	/**
	 * Use of this model might require migrating an existing BitsGroups model data.
	 * Based on AuthBasic models at the time of pre-v4.0.0 framework.
	 */
	protected function migrateFromBitsBasic()
	{
		$theTaskText = 'migrating schema to v4.0.0 AuthOrgs model';
		$this->logStuff(__METHOD__, ' ', $theTaskText);
		$this->migrateFromBitsAccountsToAuthAccounts();
		$this->migrateToAuthAccountsComplete();
		$this->logStuff(__METHOD__, ' FINISHED ', $theTaskText);
	}

	protected function migrateFromBitsAccountsToAuthAccounts()
	{
		/* @var $dbOldAccounts \BitsTheater\models\PropCloset\BitsAccounts */
		$dbOldAccounts = $this->getProp(
				'\BitsTheater\models\PropCloset\BitsAccounts'
		);
		/* @var $dbOldAuthBasic \BitsTheater\models\PropCloset\AuthBasic */
		$dbOldAuthBasic = $this->getProp(
				'\BitsTheater\models\PropCloset\AuthBasic'
		);
		if ( $this->isEmpty($this->tnAuthAccounts)
				&& $dbOldAccounts->exists($dbOldAccounts->tnAccounts)
				&& !$dbOldAccounts->isEmpty($dbOldAccounts->tnAccounts)
				&& $dbOldAuthBasic->exists($dbOldAuthBasic->tnAuth)
				&& !$dbOldAuthBasic->isEmpty($dbOldAuthBasic->tnAuth)
		) try {
			$this->logStuff(' migrating from ', $dbOldAccounts->tnAccounts,
					' to ', $this->tnAuthAccounts);
			$theSql = SqlBuilder::withModel($dbOldAuthBasic);
			$theSql->startWith('SELECT * FROM')->add($dbOldAuthBasic->tnAuth);
			$ps = $theSql->query();
			for( $theItem = $ps->fetch() ; $theItem !== false ; $theItem = $ps->fetch() )
			{
				$theNewRow = array(
						'auth_id' => $theItem['auth_id'],
						'email' => $theItem['email'],
						'account_id' => $theItem['account_id'],
						'pwhash' => $theItem['pwhash'],
						'verified_ts' => $theItem['verified_ts'],
						'is_active' => $theItem['is_active'],
						'created_by' => $theItem['created_by'],
						'created_ts' => $theItem['created_ts'],
						'updated_by' => $theItem['updated_by'],
						'updated_ts' => $theItem['updated_ts'],
				);
				//account_name and external_id
				$theOldAccountRow = $dbOldAccounts->getAccount($theItem['account_id']);
				$theNewRow['account_name'] = $theOldAccountRow['account_name'];
				$theNewRow['external_id'] = $theOldAccountRow['external_id'];
				//now add in our new row
				$this->addAuthAccount($theNewRow);
			}
			$this->logStuff(' migrated to ', $this->tnAuthAccounts);
		}
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}

	protected function migrateToAuthAccountsComplete()
	{
		/* @var $dbOldAccounts \BitsTheater\models\PropCloset\BitsAccounts */
		$dbOldAccounts = $this->getProp(
				'\BitsTheater\models\PropCloset\BitsAccounts'
		);
		/* @var $dbOldAuthBasic \BitsTheater\models\PropCloset\AuthBasic */
		$dbOldAuthBasic = $this->getProp(
				'\BitsTheater\models\PropCloset\AuthBasic'
		);
		//remove old feature record
		/* @var $dbMeta SetupDb */
		$dbMeta = $this->getProp('SetupDb');
		$dbMeta->removeFeature($dbOldAuthBasic::FEATURE_ID);
		$this->logStuff(' removed ', $dbOldAuthBasic::FEATURE_ID,
				' from ', $dbMeta->tnSiteVersions);
		$this->returnProp($dbMeta);
		//remove old tables
		$theSql = SqlBuilder::withModel($dbOldAccounts)
			->startWith('DROP TABLE')->add($dbOldAccounts->tnAccounts)
			->execDML()
			;
		$this->logStuff(' dropped old table ', $dbOldAccounts->tnAccounts);
		$theSql = SqlBuilder::withModel($dbOldAuthBasic)
			->startWith('DROP TABLE')->add($dbOldAuthBasic->tnAuth)
			->execDML()
			;
		$this->logStuff(' dropped old table ', $dbOldAuthBasic->tnAuth);
	}

	protected function exists($aTableName=null) {
		return parent::exists( empty($aTableName) ? $this->tnAuthAccounts : $aTableName );
	}

	public function isEmpty($aTableName=null) {
		return parent::isEmpty( empty($aTableName) ? $this->tnAuthAccounts : $aTableName );
	}

	/**
	 * Create an object representing auth account information.
	 * @param array|object $aInitialData - (optional) include this data in the object.
	 * @return AccountInfoCache Returns the object for the Auth model in use.
	 */
	public function createAccountInfoObj( $aInitialData=null )
	{ return AccountInfoCache::fromThing($this, $aInitialData); }

	/**
	 * Insert an auth account record.
	 * @param array|object $aDataObject - the (usually Scene) object containing
	 *   the data to be used.
	 * @return integer Returns the newly created account_id.
	 * @deprecated use addAuthAccount() instead.
	 */
	public function add($aDataObject)
	{ return $this->addAuthAccount($aDataObject)['account_id']; }

	/**
	 * Insert an auth account record.
	 * @param array|object $aDataObject - the (usually Scene) object containing
	 *   the data to be used.
	 * @return array Returns the data posted to the database.
	 */
	public function addAuthAccount($aDataObject)
	{
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aDataObject);
		$theSql->startWith('INSERT INTO')->add($this->tnAuthAccounts);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('auth_id', Strings::createUUID())
			->addParam('account_id', null, \PDO::PARAM_INT)
			->mustAddParam('account_name')
			->mustAddParam('email')
			->mustAddParam('pwhash')
			->addParamIfDefined('external_id', null, \PDO::PARAM_INT)
			->addParam('verified_ts')
			//->addParamIfDefined('last_seen_ts') we're just now creating it!
			->addParamIfDefined('is_active', 1, \PDO::PARAM_INT)
			;
		//cheap "bad data" cleaning
		$theSql->setParam('account_name', trim($theSql->getParam('account_name')));
		//ensure we have non-empty data for some parameters
		$this->checkIsNotEmpty('account_name', $theSql->getParam('account_name'))
			->checkIsNotEmpty('email', $theSql->getParam('email'))
			->checkIsNotEmpty('pwhash', $theSql->getParam('pwhash'))
			;
		if ( strtolower(trim($theSql->getParam('verified_ts')))=='now' )
			$theSql->setParam('verified_ts', $theSql->getParam('created_ts'));
		//$theSql->logSqlDebug(__METHOD__);
		try
		{
			$theSql->execDML();
			return $this->getAuthByAuthId($theSql->getParam('auth_id'));
		}
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}

	/**
	 * Register an account with our website.
	 * @param array $aUserData - email, account_id, pwinput, verified_timestamp.
	 * @param number|array $aAuthGroups - (optional) auth group membership(s).
	 * @return array Returns account info with ID if succeeds, NULL otherwise.
	 * @throws DbException
	 */
	public function registerAccount($aUserData, $aAuthGroups=null) {
		// ensure pwhash is not empty
		$this->checkIsNotEmpty(static::KEY_pwinput, $aUserData[static::KEY_pwinput]);
		$theUserData = array(
				'account_name' => trim($aUserData[static::KEY_userinfo]),
				'email' => trim($aUserData['email']),
				'pwhash' => Strings::hasher($aUserData[static::KEY_pwinput]),
		);
		if ( !empty($aUserData['verified_timestamp']) )
			$theUserData['verified_ts'] = $aUserData['verified_timestamp'];
		if ( !empty($aUserData['verified_ts']) )
			$theUserData['verified_ts'] = $aUserData['verified_ts'];
		// ensure account_id is not already taken
		if ( !empty($aUserData['account_id']) ) {
			$thePossibleAcct = $this->getAccount($aUserData['account_id']);
			if ( !empty($thePossibleAcct) )
				throw AccountAdminException::toss($this,
						AccountAdminException::ACT_UNIQUE_FIELD_ALREADY_EXISTS,
						'account_id'
				);
			else
				$theUserData['account_id'] = Strings::toInt(
						$aUserData['account_id']
				);
		}
		// ensure account_is_active is respected
		if ( isset($aUserData['account_is_active']) ) {
			$bIsActive = filter_var($aUserData['account_is_active'],
					FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE
			);
			if ( isset($bIsActive) )
				$theUserData['is_active'] = ($bIsActive) ? 1 : 0;
		}
		// define a sane created_by, if possible, by using the cleansed data
		if ( $this->isGuest() && !empty($theUserData['account_name']) )
			$theUserData['created_by'] = $theUserData['account_name'];
	
		$this->db->beginTransaction() ;
		try {
			$dbAuthGroups = $this->getAuthGroupsProp();
			// if this is our first account, it will become a titan
			if ( $this->isEmpty($this->tnAuthAccounts) )
			{ $aAuthGroups = $dbAuthGroups->getTitanGroupID(); }
			// now add our new auth account
			$theResult = $this->addAuthAccount($theUserData);
			// perform any group mapping
			if (is_array($aAuthGroups)) {
				$dbAuthGroups->addGroupsToAuth(
						$theResult['auth_id'], $aAuthGroups
				);
				$theResult['authgroup_ids'] = $aAuthGroups;
			} else if ( !empty($aAuthGroups) ) {
				$dbAuthGroups->addMap(
						$aAuthGroups, $theResult['auth_id']
				);
				$theResult['authgroup_ids'] = array($aAuthGroups);
			}
			$this->returnProp($dbAuthGroups);
			//org mapping
			if( array_key_exists( 'org_ids', $aUserData ) && is_array($aUserData['org_ids']) )
			{
				$this->addOrgsToAuth(
						$theResult['auth_id'], $aUserData['org_ids']
				);
				$theResult['org_ids'] = $aUserData['org_ids'];
			}
			//inc reg cap
			$this->updateRegistrationCap();
			//commit it all
			$this->db->commit();
			//success!
			return $theResult;
		}
		catch (PDOException $pdox) {
			$this->errorLog( __METHOD__ . ' failed: ' . $pdox->getMessage());
			$this->db->rollBack();
			throw new DbException( $pdox, __METHOD__ . ' failed.' );
		}
	}

	/**
	 * Update an existing record; auth_id required and cannot be modified with this method.
	 * @param object $aDataObject - object containing data to be used on UPDATE.
	 * @throws DbException
	 * @return  array(id, & updated data) on success, else NULL.
	 */
	public function updateAuthAccount($aDataObject)
	{
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aDataObject);
		//check our required parameters and toss an exception for invalid ones
		if (empty($aDataObject->auth_id))
			throw new \InvalidArgumentException('invalid $aDataObject->auth_id param');
		$theSql->startWith('UPDATE')->add($this->tnAuthAccounts);
		$this->setAuditFieldsOnUpdate($theSql) ;
		$theSql->addParam('account_id'); //cannot be null, so avoid "..IfDefined()"
		$theSql->addParam('account_name'); //cannot be null, so avoid "..IfDefined()"
		$theSql->addParam('email'); //cannot be null, so avoid "..IfDefined()"
		$theSql->addParam('pwhash'); //cannot be null, so avoid "..IfDefined()"
		$theSql->addParamIfDefined('external_id');
		$theSql->addParamIfDefined('verified_ts');
		$theSql->addParamIfDefined('last_seen_ts');
		$theSql->addParamIfDefined('is_active', PDO::PARAM_INT);
		$theSql->startWhereClause()->mustAddParam( 'auth_id' )->endWhereClause();
		try { return $theSql->execDMLandGetParams(); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}

	/**
	 * Event to be called immediately upon determining when a account is "logged in".
	 * @param object $aScene - the Scene object in use that holds client input.
	 * @param AccountInfoCache $aAuthAccount - the logged in auth account.
	 */
	public function onDetermineAuthAccount( $aScene, AccountInfoCache $aAuthAccount=null )
	{
		if ( !empty($aAuthAccount) && //ensure we have a valid auth & account_id
				!empty($aAuthAccount->auth_id) && $aAuthAccount->account_id > 0 )
		{
			//update last login info
			$aAuthAccount->last_seen_dt = new \DateTime('now', new \DateTimeZone('UTC'));
			$aAuthAccount->last_seen_ts = $this->getDateTimeAsDbTimestampFormat(
					$aAuthAccount->last_seen_dt
			);
			$this->updateAuthAccount((object)array(
					'auth_id' => $aAuthAccount->auth_id,
					'last_seen_ts' => $aAuthAccount->last_seen_ts,
			));
			//determine what org to use (if did not specify, will use 1st org found)
			$theFilter = null;
			if ( !empty($aScene->org_id) ) {
				$theFilter = SqlBuilder::withModel($this)->obtainParamsFrom($aScene)
					->mustAddParam('org_id')
					;
			}
			$theOrgCursor = $this->getOrgsForAuthCursor($aAuthAccount->auth_id, null, $theFilter);
			$theOrgRow = $theOrgCursor->fetch(\PDO::FETCH_OBJ);
			if ( !empty($theOrgRow) && isset( $theOrgRow->dbconn ) )
			{
				//$this->logStuff(__METHOD__, ' switch2org=', $theOrgRow); //DEBUG
				$myOrgSessionKey = 'org4-'.$aAuthAccount->auth_id;
				$this->getDirector()[$myOrgSessionKey] = $theOrgRow->org_id;
				try
				{ $this->swapAppDataDbConnInfo($theOrgRow->dbconn); }
				catch ( \InvalidArgumentException $iax )
				{
					$this->logErrors(__METHOD__, ' org=', $theOrgRow);
					$theOrgName = ( isset( $theOrgRow->org_name ) ?
							$theOrgRow->org_name : '' ) ;
					throw new DbException( $iax,
							'fail2swap2org=[' . $theOrgName . ']' ) ;
				}
			}
			else if ( !empty($theFilter) && !$this->isEmpty($this->tnAuthOrgs) &&
					!$this->getDirector()->isAllowed('auth', 'istitan') )
			{
				// person is trying to log into an Org they do not belong in,
				//   consider them a "guest"
				$this->getDirector()->account_info->groups = array(0);
				$this->updateFailureLockout($this, $aScene);
			}
		}
	}
	
	/**
	 * Swap our APP_DB_CONN_NAME db connection with a new connection string.
	 * @param string $aNewDbConnString - the dbconn string to utilize.
	 * @throws \InvalidArgumentException if the dbconn string fails somehow.
	 */
	protected function swapAppDataDbConnInfo($aNewDbConnString)
	{
		$theNewDbConnInfo = new DbConnInfo(APP_DB_CONN_NAME);
		$theNewDbConnInfo->loadDbConnInfoFromString($aNewDbConnString);
		$this->getDirector()->setDbConnInfo($theNewDbConnInfo);
	}
	
	//=========================================================================
	//===============         Accounts           ==============================
	//=========================================================================

	public function getAccount( $aAccountID )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT * FROM' )->add( $this->tnAuthAccounts )
			->startWhereClause()
			->mustAddParam( 'account_id', $aAccountID )
			->endWhereClause()
			;
		try { return $theSql->getTheRow() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}

	public function getByName( $aName )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT * FROM' )->add( $this->tnAuthAccounts )
			->startWhereClause()
			->mustAddParam( 'account_name', $aName )
			->endWhereClause()
			;
		try { return $theSql->getTheRow() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}

	public function getByExternalId($aExternalId)
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT * FROM' )->add( $this->tnAuthAccounts )
			->startWhereClause()
			->mustAddParam( 'external_id', $aExternalId, \PDO::PARAM_INT )
			->endWhereClause()
			;
		try { return $theSql->getTheRow() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}

	/**
	 * Delete an account.
	 * @param number $aAccountId - the account ID.
	 * @throws \InvalidArgumentException
	 * @throws DbException
	 * @return array Returns the account ID passed in.
	 */
	public function del($aAccountId) {
		$theAcctId = Strings::toInt($aAccountId);
		if (empty($theAcctId))
			throw new \InvalidArgumentException('invalid $aAccountId param');
		$theSql = SqlBuilder::withModel($this);
		try {
			$theSql->startWith('DELETE FROM')->add($this->tnAuthAccounts);
			$theSql->startWhereClause();
			$theSql->mustAddParam('account_id', $theAcctId, PDO::PARAM_INT);
			$theSql->endWhereClause();
			return $theSql->execDMLandGetParams();
		} catch (PDOException $pdoe) {
			throw $theSql->newDbException(__METHOD__, $pdoe);
		}
	}

	/**
	 * Gets the set of all account records.
	 * TODO - Add paging someday.
	 * @return \PDOStatement - the iterable result of the SELECT query
	 * @throws DbException if something goes wrong
	 * @since BitsTheater 3.6
	 */
	public function getAll()
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT * FROM ' . $this->tnAccounts )
			;
		try { return $theSql->query() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}
	
	/**
	 * Given the AccountID, update the name associated with it.
	 * @param number $aAcctID - the account_id of the account.
	 * @param string $aName - the name to use.
	 */
	public function updateName( $aAcctID, $aName )
	{
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
				'account_id' => $aAcctID,
				'account_name' => $aName,
		));
		$theSql->startWith('UPDATE')->add($this->tnAuthAccounts);
		$theSql->add('SET')->mustAddParam('account_name');
		$theSql->startWhereClause()->mustAddParam('account_id')->endWhereClause();
		try { $theSql->execDML() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}
	
	//=========================================================================
	//===============      Organizations         ==============================
	//=========================================================================

	/**
	 * For a given organization ID, returns the record data from the db row.
	 * @param string $aOrgId - org_id of the record.
	 * @param string|string[] $aFieldList - (optional) which fields to return, default is all of them.
	 * @return array Returns the row data.
	 */
	public function getOrganization( $aOrgID, $aFieldList=null )
	{
		$theSql = SqlBuilder::withModel( $this );
		$theSql->startWith('SELECT')->addFieldList($aFieldList)
			->add('FROM')->add($this->tnAuthOrgs)
			->startWhereClause()->mustAddParam( 'org_id', $aOrgID )
			->endWhereClause()
			;
		try
		{ return $theSql->getTheRow(); }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}

	/**
	 * Show the organizations for display (pager and such).
	 * @param ISqlSanitizer $aSqlSanitizer - the SQL sanitizer obj being used.
	 * @param SqlBuilder $aFilter - (optional) Specifies restrictions on
	 *   data to return; effectively populating a WHERE filter for the query.
	 * @param string[]|NULL $aFieldList - (optional) String list representing
	 *   which columns to return. Leaving this argument blank defaults to
	 *   returning all table column fields.
	 * @throws DBException
	 * @return \PDOStatement Returns the query result.
	 */
	public function getOrganizationsToDisplay(ISqlSanitizer $aSqlSanitizer,
			$aFieldList=null, SqlBuilder $aFilter=null)
	{
		$theSql = SqlBuilder::withModel($this)->setSanitizer($aSqlSanitizer)
			->startWith('SELECT')->addFieldList($aFieldList)
			->add('FROM')->add($this->tnAuthOrgs)
			->startWhereClause()->applyFilter($aFilter)->endWhereClause()
			->retrieveQueryTotalsForSanitizer()
			->applyOrderByListFromSanitizer()
			->applyQueryLimitFromSanitizer()
			;
//		$theSql->logSqlDebug(__METHOD__); //DEBUG
		try { return $theSql->query() ; }
		catch( PDOException $pdox )
		{ $this->relayPDOException( __METHOD__, $pdox, $theSql ) ; }
	}
	
	/**
	 * For a given organization id, returns all affiliated accounts.
	 * @param string|string[] $aOrgId - a single ID or an array of several IDs.
	 * @param string|string[] $aFieldList - (optional) which fields to return, default is all of them.
	 * @param SqlBuilder $aFilter - (optional) specifies restrictions on data to return
	 * @param array $aSortList - (optional) sort the results: keys are the fields => values are
	 *    'ASC'|true or 'DESC'|false with null='ASC'.
	 * @return array Returns all rows as an array.
	 */
	public function getAuthAccountsForOrgCursor( $aOrgID, $aFieldList=null,
			$aFilter=null, $aSortList=null )
	{
		$theResultSet = null;
		$theSql = SqlBuilder::withModel($this)
			->startWith('SELECT')->addFieldList($aFieldList)
			->add('FROM')->add($this->tnAuthAccounts)
			->add('JOIN')->add($this->tnAuthOrgMap)->add('AS map USING (auth_id)')
			->startWhereClause(' map.')->mustAddParam('org_id', $aOrgID)
				->setParamPrefix(' AND ')->applyFilter($aFilter)
			->endWhereClause()
			;
		$theSortList = ( !empty($aSortList) ) ? $aSortList : array('account_name', 'account_id');
		$theSql->applySortList($theSortList);
		try
		{ return $theSql->query(); }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}

	/**
	 * For a given auth id, return all organizations that are mapped to it.
	 * @param array/string $aAuthId - UUID string for a single auth id or array of several ids.
	 * @param array/string $aFieldList - (optional) which fields to return, default is all of them.
	 * @param SqlBuilder $aFilter - (optional) specifies restrictions on data to return
	 * @param array $aSortList - (optional) sort results: fields as key => ('ASC' or 'DESC') as value.
	 * @return \PDOStatement Returns the query result object.
	 */
	public function getOrgsForAuthCursor( $aAuthId, $aFieldList=null,
			$aFilter=null, $aSortList=null )
	{
		$theResultSet = null;
		if( empty ($aFieldList ) )
			$aFieldList = array( 'org.*' );
		$theSql = SqlBuilder::withModel( $this )
			->startWith('SELECT')->addFieldList( $aFieldList )
			->add('FROM')->add($this->tnAuthOrgs)->add('AS org')
			->add('JOIN')->add($this->tnAuthOrgMap)->add('AS map USING (org_id)')
			->startWhereClause(' map.')->mustAddParam('auth_id', $aAuthId)
			->setParamPrefix(' AND ')->applyFilter($aFilter)
			->endWhereClause()
			;
		$theSortList = ( !empty($aSortList) ) ? $aSortList : array('org.org_name', 'org.org_id');
		$theSql->applySortList( $theSortList );
		try
		{ return $theSql->query(); }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * For a given data object containing relevant org details, add new
	 * organization to relevant database table.
	 * @param array|object $aDataObject - the (usually Scene) object containing
	 *    the data to be used.
	 * @return array Returns the data posted to the database.
	 */
	public function addOrganization( $aDbConnInput )
	{
		$theDbAdmin = new DbAdmin($this);
		// ensure we have an org_name, default to dbname if we have to.
		if ( empty($aDbConnInput->org_name) )
		{ $aDbConnInput->org_name = $aDbConnInput->dbname; }
		$theDbAdmin->sanitizeDbInfoInput($aDbConnInput, 'org_name');
		$this->checkIsNotEmpty('org_name', $aDbConnInput->org_name)
			->checkIsNotEmpty('org_title', $aDbConnInput->org_title)
			;
		// now that we have an org_name, we have to ensure it has not already been used
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aDbConnInput);
		if ( $theSql->startWith('SELECT 1 FROM')->add($this->tnAuthOrgs)
			->startWhereClause()->mustAddParam('org_name')->endWhereClause()
			->add('LIMIT 1')
			->getTheRow() )
		{
			throw AccountAdminException::toss($this,
					AccountAdminException::ACT_UNIQUE_FIELD_ALREADY_EXISTS, 'org_name'
			);
		}
		$theDbAdmin->createDbFromUserInput($aDbConnInput);
		// add in the proper model tables
		$theDbNewConn = DbConnInfo::fromURI($aDbConnInput->dbconn, APP_DB_CONN_NAME);
		$theDbAdmin->setupNewDb($aDbConnInput, $theDbNewConn, APP_DB_CONN_NAME);
		unset($theDbNewConn);
		// now lets actually insert the new org into our database
		$theSql->reset();
		$theSql->startWith('INSERT INTO')->add($this->tnAuthOrgs);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('org_id', Strings::createUUID())
			->mustAddParam('org_name')
			->mustAddParam('org_title')
			->addParam('org_desc')
			->mustAddParam('dbconn')
			->addParam('parent_org_id')
			;
		try
		{
			$theParams = $theSql->execDMLandGetParams();
			//return everything EXCEPT the constructed dbconn param (security measure)
			unset($theParams['dbconn']);
			return $theParams;
		}
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * For a given data object containing relevant org details, update an existing
	 * organization.
	 * @param array|object $aDataObject - the (usually Scene) object containing
	 *    the data to be used.
	 * @return array Returns the data posted to the database.
	 */
	public function updateOrganization( $aDataInput )
	{
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom( $aDataInput );
		$theSql->startWith('UPDATE')->add($this->tnAuthOrgs);
		$this->setAuditFieldsOnUpdate($theSql);
		$theSql->mustAddParam('org_id', Strings::createUUID())
			//->mustAddParam('org_name') immutable since tied to database name
			->addParamIfDefined('org_title')
			->addParamIfDefined('org_desc')
			//->mustAddParam('dbconn') immutable since we do not change the dbconn
			->addParamIfDefined('parent_org_id')
			->startWhereClause()->mustAddParam('org_id')->endWhereClause()
			;
		$this->checkIsNotEmpty('org_id', $theSql->getParam('org_id'));
		try
		{ return $theSql->execDMLandGetParams(); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}

	/**
	 * Add a set of records to the auth_id/org_id map table.
	 * @param string $aAuthID - the auth account ID.
	 * @param string[] $aOrgIDs - the org IDs.
	 * @throws DbException if an error happens in the query itself
	 */
	public function addOrgsToAuth( $aAuthID, $aOrgIDs )
	{
		if ( !is_array($aOrgIDs) )
		{
			throw new \InvalidArgumentException(__METHOD__ . ' invalid $aOrgIDs param='
					. $this->debugStr($aOrgIDs)
			);
		}
		if ( empty($aOrgIDs) ) return; //trivial
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('INSERT INTO')->add($this->tnAuthOrgMap);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('auth_id', $aAuthID);
		$theSql->mustAddParam('org_id', '__placeholder__');
		//use the params added so far to help create our multi-DML array
		//  every entry needs to match the number of SQL parameters used
		$theParamList = array();
		foreach ($aOrgIDs as $anID) {
			$theParamList[] = array(
					'created_ts' => $theSql->getParam('created_ts'),
					'updated_ts' => $theSql->getParam('updated_ts'),
					'created_by' => $theSql->getParam('created_by'),
					'updated_by' => $theSql->getParam('updated_by'),
					'auth_id' => $theSql->getParam('auth_id'),
					'org_id' => $anID,
			);
		}
		try
		{ $theSql->execMultiDML($theParamList); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Delete a set of records in the auth_id/org_id map table.
	 * @param string $aAuthID - the auth account ID.
	 * @param string[] $aOrgIDs - the org IDs.
	 * @throws DbException if an error happens in the query itself
	 */
	public function delOrgsForAuth( $aAuthID, $aOrgIDs=null )
	{
		if ( isset($aOrgIDs) && !is_array($aOrgIDs) )
		{
			throw new \InvalidArgumentException(__METHOD__ . ' invalid $aOrgIDs param='
					. $this->debugStr($aOrgIDs)
			);
		}
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith( 'DELETE FROM' )->add( $this->tnAuthOrgMap )
			->startWhereClause()
			->mustAddParam('auth_id', $aAuthID)
			;
		if( isset($aOrgIDs) && !empty($aOrgIDs) )
		{ // Optionally restrict delete to list of org IDs
			$theSql->setParamPrefix(' AND ')
				->addParam( 'org_id', $aOrgIDs )
				;
		}
		$theSql->endWhereClause() ;
//		$theSql->logSqlDebug( __METHOD__, ' [DEBUG] ' ) ;
		try { $theSql->execDML(); }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}
	
	//=========================================================================
	//===============        AuthBasic           ==============================
	//=========================================================================

	public function getAuthByEmail($aEmail) {
		$theSql = "SELECT * FROM {$this->tnAuth} WHERE email = :email";
		return $this->getTheRow($theSql,array('email'=>$aEmail));
	}

	public function getAuthByAccountId($aAccountId)
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT * FROM ' . $this->tnAuthAccounts )
			->startWhereClause()
			->mustAddParam( 'account_id', $aAccountId )
			->endWhereClause()
//			->logSqlDebug( __METHOD__, ' [DEBUG] ' )
			;
		return $theSql->getTheRow() ;
	}

	public function getAuthByAuthId($aAuthId) {
		$theSql = "SELECT * FROM {$this->tnAuth} WHERE auth_id=:id";
		return $this->getTheRow($theSql, array('id'=>$aAuthId));
	}

	public function getAuthTokenRow($aAuthId, $aAuthToken) {
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
				'auth_id' => $aAuthId,
				'token' => $aAuthToken,
		));
		$theSql->startWith('SELECT * FROM')->add($this->tnAuthTokens);
		$theSql->startWhereClause()->mustAddParam('auth_id');
		$theSql->setParamPrefix(' AND ')->mustAddParam('token');
		$theSql->endWhereClause();
		return $theSql->getTheRow();
	}

	public function getAuthMobilesByAccountId($aAccountId) {
		$theSql = "SELECT * FROM {$this->tnAuthMobile} WHERE account_id=:id";
		$ps = $this->query($theSql, array('id' => $aAccountId), array('id' => PDO::PARAM_INT));
		if (!empty($ps)) {
			return $ps->fetchAll();
		}
	}

	public function getAuthMobilesByAuthId($aAuthId) {
		$theSql = "SELECT * FROM {$this->tnAuthMobile} WHERE auth_id=:id";
		$ps = $this->query($theSql, array('id' => $aAuthId));
		if (!empty($ps)) {
			return $ps->fetchAll();
		}
	}

	/**
	 * Gets the set of all account records.
	 * @param Scene $aScene - (optional) Scene object in case we need user-defined query limits.
	 * @param number $aGroupId - (optional) the group id to filter on
	 * @return \PDOStatement - the iterable result of the SELECT query
	 * @throws DbException if something goes wrong
	 */
	public function getAccountsToDisplay($aScene=null, $aGroupId=null) {
		$theQueryLimit = (!empty($aScene)) ? $aScene->getQueryLimit($this->dbType()) : null;
		$theSql = SqlBuilder::withModel($this)->setSanitizer($aScene)->obtainParamsFrom(array(
				'group_id' => $aGroupId,
				'token' => self::TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT . ':%',
		));
		try {
			//query field list
			//NOTE: since we have a nested query in field list, must add HINT for getQueryTotals()
			$theSql->startWith('SELECT')->add(SqlBuilder::FIELD_LIST_HINT_START);
			$theSql->add('auth.*');
			//find mapped hardware ids, if any (AuthAccount costume will convert this field into appropriate string)
			$theSql->add(', (')
					->add("SELECT GROUP_CONCAT(`token` SEPARATOR ', ') FROM")->add($this->tnAuthTokens)
					->add('WHERE auth.auth_id=auth_id')->setParamPrefix(' AND ')
					->setParamOperator(' LIKE ')->mustAddParam('token')->setParamOperator('=')
					->add(') AS hardware_ids')
					;
			//done with fields
			$theSql->add(SqlBuilder::FIELD_LIST_HINT_END);
			//now for rest of query
			$theSql->add('FROM')->add($this->tnAuthAccounts)->add('AS auth');
			if (!is_null($aGroupId)) {
				$dbAuthGroups = $this->getAuthGroupsProp();
				$theSql->add('JOIN')->add($dbAuthGroups->tnGroupMap)->add('AS gm USING (auth_id)');
				$theSql->startWhereClause()->mustAddParam('group_id')->endWhereClause();
			}
			//if we have a query limit, we may be using a pager, get total count for pager display
			if (!empty($aScene) && !empty($theQueryLimit)) {
				$theCount = $theSql->getQueryTotals(array(
						'count(*)' => 'total_rows',
						':token' => 'ntmtl', //need to match token list
				));
				if (!empty($theCount)) {
					$aScene->setPagerTotalRowCount($theCount['total_rows']);
				}
			}
			//if we have not caused an exception yet, apply OrderBy and set QueryLimit
			$theSql->applyOrderByListFromSanitizer();
			if (!empty($theQueryLimit)) {
				$theSql->add($theQueryLimit);
			}
			//return the executed query result
			return $theSql->query();
		} catch (PDOException $pdoe) {
			throw $theSql->newDbException(__METHOD__, $pdoe);
		}
	}

	/**
	 * Retrieve the auth mobile data of a particular mobile_id.
	 * @param string $aMobileId - the ID of the row to return.
	 * @return array Returns an array of data for the mobile row.
	 */
	public function getAuthMobileRow($aMobileId) {
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
				'mobile_id' => $aMobileId,
		));
		$theSql->startWith('SELECT * FROM')->add($this->tnAuthMobile);
		$theSql->startWhereClause()->mustAddParam('mobile_id')->endWhereClause();
		return $theSql->getTheRow();
	}

	/**
	 * Selects from the table of authentication tokens. The function allows the
	 * caller to use any of the columns (except change date) as a query filter;
	 * any parameter that is empty will be excluded from the WHERE clause of the
	 * query.
	 *
	 * Get all tokens for a given auth ID:
	 *     getAuthTokens( $aAuthID ) ;
	 * Get a particular token:
	 *     getAuthTokens( null, null, $aTokenValue ) ;
	 * Get tokens for a particular account with a prefix pattern:
	 *     getAuthTokens( null, $aAccountID, ( $aPrefix . '%' ), true ) ;
	 * Get tokens for a particular auth ID with a suffix pattern:
	 *     getAuthTokens( $aAuthID, null, ( '%' . $aSuffix ), true ) ;
	 * etc.
	 *
	 * Note this tactic means that you cannot use the function to search for a
	 * row in which a given value might actually be null/empty, but that should
	 * be irrelevant, since no such row should ever exist in the database.
	 *
	 * @param string $aAuthID an authentication ID to include as a selection
	 *  criterion, if any
	 * @param string $aAccountID an account ID to include as a selection
	 *  criterion, if any
	 * @param string $aToken a specific token value, or a search filter pattern
	 *  to limit the format of the tokens that are returned (use SQL "LIKE"
	 *  syntax for the latter)
	 * @param boolean $isTokenAFilter indicates whether $aToken is a literal
	 *  token value, or a filter pattern
	 * @return array the set of tokens, if any are found
	 */
	public function getAuthTokens( $aAuthID=null, $aAccountID=null,
			$aToken=null, $isTokenAFilter=false )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT * FROM' )->add( $this->tnAuthTokens )
			->startWhereClause()
			;
		if( ! empty($aAuthID) )
			$theSql->addParam( 'auth_id', $aAuthID )->setParamPrefix(' AND ') ;
		if( ! empty($aAccountID) )
			$theSql->addParam('account_id',$aAccountID)->setParamPrefix(' AND ') ;
		if( ! empty($aToken) )
		{ // also search based on a token value
			if( $isTokenAFilter )
			{ // token is a search pattern
				$theSql->setParamOperator(' LIKE ')
					->addParam( 'token', $aToken )
					->setParamOperator('=')
					;
			}
			else // token is a literal value
				$theSql->addParam( 'token', $aToken ) ;

			$theSql->setParamPrefix(' AND ') ;
		}
		// future columns can be added here

		$theSql->endWhereClause();
		$theSql->applyOrderByList(array('updated_ts' => SqlBuilder::ORDER_BY_DESCENDING));
		//$this->debugLog( __METHOD__.' getAuthTokens='.$this->debugStr($theSql) ) ;
		try
		{
			$theSet = $theSql->query() ;
			if (!empty($theSet))
				return $theSet->fetchAll() ;
		}
		catch( PDOException $pdoe )
		{
			$theSql->logSqlFailure(__METHOD__, $pdoe);
		}
		return null ;
	}

	/**
	 * Insert the token into the table.
	 * @param string $aAuthId - token mapped to auth record by this id.
	 * @param number $aAcctId - the account which will map to this token.
	 * @param string $aToken - the token.
	 * @return array Returns the data inserted.
	 * @since BitsTheater 3.6.1
	 */
	public function insertAuthToken($aAuthId, $aAcctId, $aToken) {
		if (empty($aAuthId) && empty($aAcctId))
			throw new \InvalidArgumentException('$aAuthId & $aAcctId cannot both be empty');
		if (empty($aToken))
			throw new \InvalidArgumentException('invalid $aToken param');
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('INSERT INTO')->add($this->tnAuthTokens);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('auth_id', $aAuthId);
		$theSql->mustAddParam('account_id', $aAcctId, PDO::PARAM_INT);
		$theSql->mustAddParam('token', $aToken);
		//$theSql->logSqlDebug(__METHOD__);
		try { return $theSql->execDMLandGetParams(); }
		catch (PDOException $pdoe)
		{ throw $theSql->newDbException(__METHOD__, $pdoe); }
	}

	/**
	 * Create and store an auth token mapped to an account (by account_id).
	 * The token is guaranteed to be universally unique.
	 * @param string $aAuthId - token mapped to auth record by this id.
	 * @param number $aAcctId - the account which will map to this token.
	 * @param string $aTweak - (optional) token generation tweak.
	 * @return string Return the token generated.
	 */
	public function generateAuthToken($aAuthId, $aAcctId, $aTweak=null) {
		//64chars of unique gibberish
		$theAuthToken = self::generatePrefixedAuthToken( $aTweak ) ;
		$this->insertAuthToken($aAuthId, $aAcctId, $theAuthToken);
		return $theAuthToken;
	}

	/**
	 * Prefix/Suffix Token generation may wish to pad small tokens to 64
	 * and larger tokens with a fixed 10 random chars.
	 * @param string $aStr - the prefix/suffix used to calc padding.
	 * @return string Returns the padding to be used.
	 * @since BitsTheater 3.6.1
	 */
	static protected function generateAuthTokenPadding($aStr)
	{
		return Strings::urlSafeRandomChars(
				max(array(64-36-2-strlen($aStr), 10))
		) . ':';
	}

	/**
	 * Creates an authorization token of the form PREFIX:RANDOMCHARS:UUID.
	 * @param string $aPrefix a prefix to be used, if any
	 * @return string an authorization token string
	 */
	static public function generatePrefixedAuthToken( $aPrefix=null )
	{
		return $aPrefix
			. ( !empty($aPrefix) ? ':' : '' )
			. self::generateAuthTokenPadding($aPrefix)
			. Strings::createUUID()
			;
	}

	/**
	 * Creates an authorization token of the form RANDOMCHARS:UUID:SUFFIX.
	 * @param string $aSuffix a suffix to be used, if any
	 * @return string an authorization token string
	 */
	static public function generateSuffixedAuthToken( $aSuffix=null )
	{
		return self::generateAuthTokenPadding($aSuffix)
			. Strings::createUUID()
			. ( !empty($aSuffix) ? ':' : '' )
			. $aSuffix
			;
	}

	/**
	 * Return the $delta to add to time() to generate the expiration date.
	 * @param string $aDuration - (optional) one of the config settings, NULL for what is
	 * stored in configuration.
	 * @return void|number Returns the $delta needed to add to time() to get the
	 * cookie expiration date; NULL = no end date, 0 means do not use cookies.
	 */
	public function getCookieDurationInDays($aDuration=null) {
		//check cookie duration
		$delta = 1; //multiplication factor, which is why it is not 0.
		try {
			$theDuration = (!empty($aDuration)) ? $aDuration : $this->getConfigSetting('auth/cookie_freshness_duration');
		} catch (Exception $e) {
			$theDuration = 'duration_1_day';
		}
		switch ($theDuration) {
			case 'duration_3_months': // => '3 Months',
				$delta = $delta*3;
			default:
			case 'duration_1_month': // => '1 Month',
				$delta = $delta*4;
			case 'duration_1_week': // => '1 Week',
				$delta = $delta*7;
			case 'duration_1_day': // => '1 Day',
				break;
			case 'duration_forever': // => 'Never go stale (not recommended)',
				$delta = null;
				break;
			case 'duration_0': // => 'Do not use cookies!',
				$delta = 0;
				return;
		}//switch
		return $delta;
	}

	/**
	 * Return the cookie expriation time based on Config settings.
	 * @return number|null Returns the cookie expiration timestamp parameter.
	 */
	public function getCookieStaleTimestamp() {
		$delta = $this->getCookieDurationInDays();
		return (!empty($delta)) ? time()+($delta*(60*60*24)) : null;
	}

	/**
	 * Create the set of cookies which will be used the next session to re-auth.
	 * @param string $aAuthId - the auth_id used by the account
	 * @param integer $aAcctId
	 */
	public function updateCookie($aAuthId, $aAcctId) {
		try {
			$theUserToken = $this->director->app_id.'-'.$aAuthId;
			$theAuthToken = $this->generateAuthToken($aAuthId, $aAcctId, self::TOKEN_PREFIX_COOKIE);
			$theStaleTime = $this->getCookieStaleTimestamp();
			$this->setMySiteCookie(self::KEY_userinfo, $theUserToken, $theStaleTime);
			$this->setMySiteCookie(self::KEY_token, $theAuthToken, $theStaleTime);
		} catch (Exception $e) {
			//do not care if setting cookies fails, log it so admin knows about it, though
			$this->debugLog(__METHOD__.' '.$e->getErrorMsg());
		}
	}

	/**
	 * Remove stale tokens.
	 */
	public function removeStaleTokens($aTokenPattern, $aExpireInterval) {
		$theSql = SqlBuilder::withModel($this);
		if (!empty($aExpireInterval) && $this->isConnected()) try {
			$theSql->obtainParamsFrom(array(
					'token' => $aTokenPattern,
			));
			$theSql->startWith('DELETE FROM')->add($this->tnAuthTokens);
			$theSql->startWhereClause();
			$theSql->setParamOperator(' LIKE ')->mustAddParam('token');
			$theDeltaField = 'updated_ts';
			$theSql->add("AND {$theDeltaField}<(NOW() - INTERVAL {$aExpireInterval})");
			$theSql->endWhereClause();
			//$this->debugLog(__METHOD__ . $this->debugStr($theSql));
			$theSql->execDML();
		} catch (Exception $e) {
			//do not care if removing stale tokens fails, log it so admin knows about it, though
			$theSql->logSqlFailure(__METHOD__, $e);
		}
	}

	/**
	 * Delete stale cookie tokens.
	 */
	protected function removeStaleCookies() {
		$delta = $this->getCookieDurationInDays();
		if (!empty($delta)) {
			$this->removeStaleTokens(self::TOKEN_PREFIX_COOKIE.'%', $delta.' DAY');
		}
	}

	/**
	 * Delete stale mobile auth tokens.
	 */
	protected function removeStaleMobileAuthTokens() {
		$this->removeStaleTokens(self::TOKEN_PREFIX_MOBILE.'%', '1 DAY');
	}

	/**
	 * Delete stale auth lockout tokens.
	 */
	protected function removeStaleAuthLockoutTokens() {
		if ($this->director->isInstalled()) {
			$this->removeStaleTokens(self::TOKEN_PREFIX_LOCKOUT.'%', '1 HOUR');
		}
	}

	/**
	 * Delete stale registration cap tokens.
	 */
	protected function removeStaleRegistrationCapTokens() {
		$this->removeStaleTokens(self::TOKEN_PREFIX_REGCAP.'%', '1 HOUR');
	}

	/**
	 * Delete stale Anti CSRF tokens.
	 */
	protected function removeStaleAntiCsrfTokens() {
		$delta = $this->getCookieDurationInDays();
		if (!empty($delta)) {
			$this->removeStaleTokens(self::TOKEN_PREFIX_ANTI_CSRF.'%', $delta.' DAY');
		}
	}

	/**
	 * Delete a specific set of tokens for a user.
	 * @param string $aAuthId - the user's auth_id.
	 * @param number $aAcctId - the user's account_id.
	 * @param string $aTokenPattern - the tokens to match via LIKE.
	 */
	public function removeTokensFor($aAuthId, $aAcctId, $aTokenPattern)
	{
		$theSql = SqlBuilder::withModel($this);
		if ($this->isConnected()) try {
			$theSql->obtainParamsFrom(array(
					'auth_id' => $aAuthId,
					'account_id' => $aAcctId,
					'token' => $aTokenPattern,
			));
			$theSql->startWith('DELETE FROM')->add($this->tnAuthTokens);
			$theSql->startWhereClause()->mustAddParam('auth_id');
			$theSql->setParamPrefix(' AND ')->mustAddParam('account_id');
			$theSql->setParamOperator(' LIKE ')->mustAddParam('token');
			$theSql->endWhereClause();
			$theSql->execDML();
		} catch (Exception $e) {
			//do not care if removing token fails, log it so admin knows about it, though
			$theSql->logSqlFailure(__METHOD__, $e);
		}
	}

	/**
	 * Delete a specific set of anti-CSRF tokens for a user.
	 * @param string $aAuthId - the user's auth_id.
	 * @param number $aAcctId - the user's account_id.
	 */
	protected function removeAntiCsrfToken($aAuthId, $aAcctId) {
		$this->removeTokensFor($aAuthId, $aAcctId, self::TOKEN_PREFIX_ANTI_CSRF.'%');
	}

	/**
	 * Returns the token row if it existed and removes it.
	 * @param string $aAuthId
	 * @param string $aAuthToken
	 */
	public function getAndEatCookie($aAuthId, $aAuthToken) {
		//toss out stale cookies first
		$this->removeStaleCookies();
		//toss out stale anti-crsf tokens as well since they are linked
		$this->removeStaleAntiCsrfTokens();
		//now see if our cookie token still exists
		$theAuthTokenRow = $this->getAuthTokenRow($aAuthId, $aAuthToken);
		$theSql = SqlBuilder::withModel($this);
		if (!empty($theAuthTokenRow)) try {
			//consume this particular cookie
			$theSql->obtainParamsFrom(array(
					'auth_id' => $aAuthId,
					'token' => $aAuthToken,
			));
			$theSql->startWith('DELETE FROM')->add($this->tnAuthTokens);
			$theSql->startWhereClause()->mustAddParam('auth_id');
			$theSql->setParamPrefix(' AND ')->mustAddParam('token');
			$theSql->endWhereClause();
			$theSql->execDML();
		} catch (Exception $e) {
			//do not care if removing cookie fails, log it so admin knows about it, though
			$theSql->logSqlFailure(__METHOD__, $e);
		}
		return $theAuthTokenRow;
	}

	/**
	 * Loads all the appropriate data about an account for login caching purposes.
	 * If the Account is INACTIVE, return NULL.
	 * @param mixed $aDUMMY - unused parameter, exists only for backward compatibility with AuthBasic.
	 * @param integer $aAccountId - the account id.
	 * @return AccountInfoCache|NULL Returns the data if found and active, else NULL.
	 */
	public function getAccountInfoCache($aDUMMY, $aAccountId) {
		$theResult = AccountInfoCache::fromThing($this,
				$this->getAuthByAccountId($aAccountId)
		);
		if ( empty($theResult) || !$theResult->is_active )
			return null;
		$theResult->groups = $this->belongsToGroups($aAccountId) ;
		return $theResult;
	}

	/**
	 * Loads all the appropriate data about an account for login caching purposes.
	 * If the Account is INACTIVE, return NULL.
	 * @param string $aEmail - the account email address.
	 * @return AccountInfoCache|NULL Returns the data if found and active, else NULL.
	 */
	public function getAccountInfoCacheByEmail($aEmail)
	{
		$theResult = AccountInfoCache::fromThing($this,
				$this->getAuthByEmail($aEmail)
		);
		if ( empty($theResult) || !$theResult->is_active )
			return null;
		$theResult->groups = $this->belongsToGroups($theResult->account_id) ;
		return $theResult;
	}

	/**
	 * Authenticates using only the information that a password reset object
	 * would know upon reentry.
	 * @param AuthOrgs $dbAccounts an Accounts prop
	 * @param AuthPasswordReset $aResetUtils an AuthPasswordReset costume
	 * @return AccountInfoCache|false Returns the account info set or FALSE if not found.
	 */
	public function setPasswordResetCreds( $dbAccounts, AuthPasswordReset $aResetUtils )
	{
		if( !isset($dbAccounts) || !isset($aResetUtils) )
		{
			$this->debugLog( __METHOD__ . ' caught a password reset reentry '
					. 'that didn\'t have the right credentials.' ) ;
			throw PasswordResetException::toss( $this,
					'REENTRY_AUTH_FAILED' ) ;
		}
		$theAccountID = $aResetUtils->getAccountID() ;
		$theAccountInfo = $this->getAccountInfoCache($dbAccounts,$theAccountID);
		return ( $this->getDirector()->setMyAccountInfo($theAccountInfo) != null);
	}

	/**
	 * Retrieve the current CSRF token.
	 * @param string $aCsrfTokenName - the name of the token, in case it's useful.
	 * @return string Returns the token.
	 */
	protected function getMyCsrfToken($aCsrfTokenName)
	{
		$theAuthInfo = $this->getDirector()->getMyAccountInfo();
		if ( !empty($theAuthInfo) )
		{
			$theTokens = $this->getAuthTokens($theAuthInfo->auth_id,
					$theAuthInfo->account_id,
					self::TOKEN_PREFIX_ANTI_CSRF.'%', true
			);
			if (!empty($theTokens))
			{
				return $theTokens[0]['token'];
			}
		}
		//if all else fails, call parent
		return parent::getMyCsrfToken($aCsrfTokenName);
	}

	/**
	 * Set the CSRF token to use.
	 * @param string $aCsrfTokenName - the name of the token, in case it's useful.
	 * @param string $aCsrfToken - (optional) the token to use,
	 *   one will be generated if necessary.
	 * @return string Returns the token to use.
	 */
	protected function setMyCsrfToken($aCsrfTokenName, $aCsrfToken=null)
	{
		$delta = $this->getCookieDurationInDays();
		$theAuthInfo = $this->getDirector()->getMyAccountInfo();
		if (!empty($theAuthInfo) && !empty($delta))
			return $this->generateAuthToken($theAuthInfo->auth_id,
					$theAuthInfo->account_id,
					self::TOKEN_PREFIX_ANTI_CSRF
			);
		else
			return parent::setMyCsrfToken($aCsrfTokenName, $aCsrfToken);
	}

	/**
	 * Removes the current CSRF token in use.
	 * @param string $aCsrfTokenName - the name of the token, in case it's useful.
	 */
	protected function clearMyCsrfToken($aCsrfTokenName)
	{
		$theAuthInfo = $this->getDirector()->getMyAccountInfo();
		if (!empty($theAuthInfo))
			$this->removeAntiCsrfToken($theAuthInfo->auth_id,
					$theAuthInfo->account_id
			);
		else
			parent::clearMyCsrfToken($aCsrfTokenName);
	}

	/**
	 * Check PHP session data for account information.
	 * @param AuthOrgs $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info;
	 * if account name is non-empty, skip session data check.
	 * @return boolean Returns TRUE if account was found and successfully loaded.
	 */
	protected function checkSessionForTicket($dbAccounts, $aScene)
	{
		$theUserInput = $aScene->{self::KEY_userinfo};
		//see if session remembers user
		if( isset( $this->director[self::KEY_userinfo] ) && empty($theUserInput) )
		{
			$theAccountId = $this->director[self::KEY_userinfo] ;
			$theAcctInfo = $this->getDirector()->setMyAccountInfo(
					$this->getAccountInfoCache( $dbAccounts, $theAccountId )
			);
			if ( empty($theAcctInfo) )
			{ // Either account info is in weird state, or account is inactive.
				$this->ripTicket() ;
			}
		}
		return ( $this->getDirector()->getMyAccountInfo() ) ;
	}

	/**
	 * Check submitted webform data for account information.
	 * @param AuthOrgs $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info.
	 * @return boolean Returns TRUE if account was found and successfully loaded.
	 */
	protected function checkWebFormForTicket($dbAccounts, $aScene) {
		if (!empty($aScene->{self::KEY_userinfo}) && !empty($aScene->{self::KEY_pwinput}) ) {
			$theUserInput = $aScene->{self::KEY_userinfo};
			$theAuthInput = $aScene->{self::KEY_pwinput};
			if (!empty($theUserInput) && !empty($theAuthInput)) {
				$theAuthRow = null;
				if ($theAccountRow = $dbAccounts->getByName($theUserInput)) {
					$theAuthRow = $this->getAuthByAccountId($theAccountRow['account_id']);
				} else {
					$theAuthRow = $this->getAuthByEmail($theUserInput);
				}
				if (!empty($theAuthRow)) {
					//check pwinput against crypted one
					$pwhash = $theAuthRow['pwhash'];
					if (Strings::hasher($theAuthInput,$pwhash)) {
						//authorized, load account data
						$theAcctInfo = $this->getDirector()->setMyAccountInfo(
								$this->getAccountInfoCache($dbAccounts, $theAuthRow['account_id'])
						);
						if ( !empty($theAcctInfo) ) {
							//if user asked to remember, save a cookie
							if (!empty($aScene->{self::KEY_cookie})) {
								$this->updateCookie($theAuthRow['auth_id'], $theAuthRow['account_id']);
							}
						}
					} else {
						//auth fail!
						$this->getDirector()->setMyAccountInfo();
						//if login failed, move closer to lockout
						$this->updateFailureLockout($dbAccounts, $aScene);
					}
					unset($theAuthRow);
					unset($pwhash);
				} else {
					//user/email not found, consider it a failure
					$this->getDirector()->setMyAccountInfo();
					//if login failed, move closer to lockout
					$this->updateFailureLockout($dbAccounts, $aScene);
				}
			}
			unset($theUserInput);
			unset($theAuthInput);
		}
		unset($aScene->{self::KEY_pwinput});
		unset($_GET[self::KEY_pwinput]);
		unset($_POST[self::KEY_pwinput]);
		unset($_REQUEST[self::KEY_pwinput]);

		return ( $this->getDirector()->getMyAccountInfo()!=null );
	}

	/**
	 * Cookies might remember our user if the session forgot and they have
	 * not tried to login.
	 * @param AuthOrgs $dbAccounts - the accounts model.
	 * @param object $aCookieMonster - an object representing cookie keys and data.
	 * @return boolean Returns TRUE if cookies successfully logged the user in.
	 */
	protected function checkCookiesForTicket($dbAccounts, $aCookieMonster) {
		if (empty($aCookieMonster[self::KEY_userinfo]) || empty($aCookieMonster[self::KEY_token]))
			return false;

		$theAuthId = Strings::strstr_after($aCookieMonster[self::KEY_userinfo], $this->director->app_id.'-');
		if (empty($theAuthId))
			return false;

		$theAuthToken = $aCookieMonster[self::KEY_token];
		try {
			//our cookie mechanism consumes cookie on use and creates a new one
			//  by having rotating cookie tokens, stolen cookies have a limited window
			//  in which to crack them before a new one is generated.
			$theAuthTokenRow = $this->getAndEatCookie($theAuthId, $theAuthToken);
			if (!empty($theAuthTokenRow)) {
				$theAccountId = $theAuthTokenRow['account_id'];
				//authorized, load account data
				$this->getDirector()->setMyAccountInfo(
						$this->getAccountInfoCache($dbAccounts, $theAccountId)
				);
				if (!empty($this->director->account_info)) {
					//bake (create) a new cookie for next time
					$this->updateCookie($theAuthId, $theAccountId);
				}
				unset($theAuthTokenRow);
			}
		} catch (DbException $e) {
			//do not care if getting cookie fails, log it so admin knows about it, though
			$this->debugLog(__METHOD__.' '.$e->getErrorMsg());
		}
		return (!empty($this->director->account_info));
	}

	/**
	 * Descendants may wish to further scrutinize header information before allowing access.
	 * @param HttpAuthHeader $aAuthHeader - the header info.
	 * @param array $aMobileRow - the mobile row data.
	 * @param AccountInfoCache $aUserAccount - the user account data.
	 * @return boolean Returns TRUE if access is allowed.
	 */
	protected function checkHeadersForMobileCircumstances(HttpAuthHeader $aAuthHeader, $aMobileRow, AccountInfoCache $aUserAccount) {
		//update device_name, if different
		$theDeviceName = $aAuthHeader->getDeviceName();
		if (!empty($theDeviceName) && (empty($aMobileRow['name']) || strcmp($aMobileRow['name'],$theDeviceName)!=0) ) {
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
					'mobile_id' => $aMobileRow['mobile_id'],
					'device_name' => $theDeviceName,
					'latitude' => $aAuthHeader->getLatitude(),
					'longitude' => $aAuthHeader->getLongitude(),
			));
			$theSql->startWith('UPDATE')->add($this->tnAuthMobile);
			$this->setAuditFieldsOnUpdate($theSql);
			$theSql->mustAddParam('device_name');
			$theSql->addParam('latitude')->addParam('longitude');
			$theSql->startWhereClause()->mustAddParam('mobile_id')->endWhereClause();
			$theSql->execDML();
		}
		//barring checking circumstances like is GPS outside pre-determined bounds, we authenticated!
		return true;
	}

	/**
	 * HTTP Headers may contain authorization information, check for that information and populate whatever we find
	 * for subsequent auth mechanisms to find and evaluate.
	 * @param AuthOrgs $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info.
	 * @return boolean Returns TRUE if account was found and successfully loaded.
	 */
	protected function checkHeadersForTicket($dbAccounts, $aScene) {
		//PHP has some built in auth vars, check them and use if not empty
		if (!empty($_SERVER['PHP_AUTH_USER']) && !empty($_SERVER['PHP_AUTH_PW'])) {
			$aScene->{self::KEY_userinfo} = $_SERVER['PHP_AUTH_USER'];
			$aScene->{self::KEY_pwinput} = $_SERVER['PHP_AUTH_PW'];
			unset($_SERVER['PHP_AUTH_PW']);
			return $this->checkWebFormForTicket($dbAccounts, $aScene);
		}
		//check for HttpAuth header
		$theAuthHeader = HttpAuthHeader::fromHttpAuthHeader(
				$this->getDirector(), $aScene->HTTP_AUTHORIZATION
		);
		switch ($theAuthHeader->auth_scheme) {
			case 'Basic':
				$aScene->{self::KEY_userinfo} = $theAuthHeader->getHttpAuthBasicAccountName();
				$aScene->{self::KEY_pwinput} = $theAuthHeader->getHttpAuthBasicAccountPw();
				//keeping lightly protected pw in memory can be bad, clear out usage asap.
				$theAuthHeader = null;
				if (!empty($this->HTTP_AUTHORIZATION))
					unset($this->HTTP_AUTHORIZATION);
				else
					unset($_SERVER['HTTP_AUTHORIZATION']);
				return $this->checkWebFormForTicket($dbAccounts, $aScene);
			case 'Broadway':
				//$this->debugLog(__METHOD__.' chkhdr='.$this->debugStr($theAuthHeader));
				if (!empty($theAuthHeader->auth_id) && !empty($theAuthHeader->auth_token)) {
					$this->removeStaleMobileAuthTokens();
					$theAuthTokenRow = $this->getAuthTokenRow($theAuthHeader->auth_id, $theAuthHeader->auth_token);
					//$this->debugLog(__METHOD__.' arow='.$this->debugStr($theAuthTokenRow));
					if (!empty($theAuthTokenRow)) {
						$theAuthMobileRows = $this->getAuthMobilesByAuthId($theAuthHeader->auth_id);
						//$this->debugLog(__METHOD__.' fp='.$theAuthHeader->fingerprints);
						foreach ($theAuthMobileRows as $theMobileRow) {
							//$this->debugLog(__METHOD__.' chk against mobile_id='.$theMobileRow['mobile_id']);
							if (Strings::hasher($theAuthHeader->fingerprints, $theMobileRow['fingerprint_hash'])) {
								//$this->debugLog(__METHOD__.' fmatch?=true');
								$theUserAccount = $this->getAccountInfoCache($dbAccounts, $theAuthTokenRow['account_id']);
								if (!empty($theUserAccount) &&
										$this->checkHeadersForMobileCircumstances($theAuthHeader,
												$theMobileRow, $theUserAccount) )
								{
									//$this->debugLog(__METHOD__.' save to session the mobile_id='.$theMobileRow['mobile_id']);
									//succeeded, save the mobile id in session cache
									$this->director[self::KEY_MobileInfo] = $theMobileRow['mobile_id'];
									//authorized, cache the account data
									$this->getDirector()->setMyAccountInfo( $theUserAccount );
									return true;
								}
							}
							//else $this->debugLog(__METHOD__.' no match against '.$theMobileRow['fingerprint_hash']);
						}
					}//if auth token row !empty
				}
				break;
		}//end switch
		return false;
	}

	/**
	 * If a manual auth was attempted, return the information needed for a lockout token.
	 * @param AuthOrgs $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info.
	 * @return array Returns the information needed for lockout tokens.
	 */
	protected function obtainLockoutTokenInfo($dbAccounts, Scene $aScene) {
		//$this->debugLog(__METHOD__.' v='.$this->debugStr($aScene));
		$theResult = array();
		//was there a login attempt, or are we just a guest browsing the site?
		$theUserInput = trim($aScene->{self::KEY_userinfo});
		$theAuthInput = trim($aScene->{self::KEY_pwinput});
		if (!empty($theUserInput) && !empty($theAuthInput)) {
			//we do indeed have a login attempt that failed
			$theResult['auth_id'] = $theUserInput;
			$theResult['account_id'] = 0;
			$theAuthRow = null;
			if ($theAccountRow = $dbAccounts->getByName($theUserInput)) {
				$theAuthRow = $this->getAuthByAccountId($theAccountRow['account_id']);
			} else {
				$theAuthRow = $this->getAuthByEmail($theUserInput);
			}
			if (!empty($theAuthRow)) {
				$theResult['auth_id'] = $theAuthRow['auth_id'];
				$theResult['account_id'] = $theAuthRow['account_id'];
			}
		}
		return $theResult;
	}

	/**
	 * If a manual auth was attempted, and a lockout status was determined,
	 * this method gets executed.
	 * @param AuthOrgs $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info.
	 */
	protected function onAccountLocked($dbAccounts, Scene $aScene) {
		$aScene->addUserMsg($this->getRes('account/err_pw_failed_account_locked'), $aScene::USER_MSG_ERROR);
	}

	/**
	 * Check to see if manual auth failed so often its locked out.
	 * @param AuthOrgs $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info.
	 * @return boolean Returns TRUE if too many failures locked out the account.
	 */
	protected function checkLockoutForTicket($dbAccounts, Scene $aScene) {
		$bLockedOut = false;
		$theMaxAttempts = ($this->director->isInstalled())
				? intval($this->getConfigSetting('auth/login_fail_attempts'), 10)
				: 0
		;
		if ($theMaxAttempts>0) {
			$theLockoutTokenInfo = $this->obtainLockoutTokenInfo($dbAccounts, $aScene);
			if (!empty($theLockoutTokenInfo)) {
				//once the number of lockout auth tokens >= max attempts, account is locked
				//  account will unlock after tokens expire (currently 1 hour)
				//  note that tokens expire individually, so > 1 hour for all tokens to expire
				$theLockoutTokens = $this->getAuthTokens(
						$theLockoutTokenInfo['auth_id'],
						$theLockoutTokenInfo['account_id'],
						self::TOKEN_PREFIX_LOCKOUT.'%', true
				);
				$bLockedOut = (!empty($theLockoutTokens)) && (count($theLockoutTokens)>=$theMaxAttempts);
				if ($bLockedOut) {
					$this->onAccountLocked($dbAccounts, $aScene);
				}
			}
		}
		return $bLockedOut;
	}

	/**
	 * When a login attempt fails, update our count in case we need to lockout that account.
	 * @param AuthOrgs $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info.
	 */
	protected function updateFailureLockout($dbAccounts, Scene $aScene) {
		//NOTE: code executing here means user is NOT LOGGED IN, but need to see if tried to do so.
		$theMaxAttempts = ($this->director->isInstalled())
				? intval($this->getConfigSetting('auth/login_fail_attempts'), 10)
				: 0
		;
		if ($theMaxAttempts>0) {
			//$this->debugLog(__METHOD__.' '.strval($theMaxAttempts));
			//was there a login attempt, or are we just a guest browsing the site?
			$theLockoutTokenInfo = $this->obtainLockoutTokenInfo($dbAccounts, $aScene);
			//$this->debugLog(__METHOD__.' '.$this->debugStr($theLockoutTokenInfo));
			if (!empty($theLockoutTokenInfo)) {
				//add lockout token
				$theAuthToken = $this->generateAuthToken(
						$theLockoutTokenInfo['auth_id'],
						$theLockoutTokenInfo['account_id'],
						self::TOKEN_PREFIX_LOCKOUT
				);
				//once the number of lockout auth tokens >= max attempts, account is locked
				//  account will unlock after tokens expire (currently 1 hour)
				//  note that tokens expire individually, so > 1 hour for all tokens to expire
			}
		}
	}

	/**
	 * See if we are trying to migrate the Auth model.
	 * @param object $aScene - the Scene object associated with an Actor.
	 * @return boolean Returns TRUE if admitted.
	 * @see BaseModel::checkTicket()
	 */
	protected function checkInstallPwForMigration($aScene)
	{
		$theInstallScene = new \BitsTheater\scenes\Install();
		$theInstallScene->installpw = $aScene->{static::KEY_pwinput};
		$bAuthed = $theInstallScene->checkInstallPw();
		if ( $bAuthed )
		{
			//set my fake titan account info so we can migrate!
			$this->getDirector()->setMyAccountInfo(array(
					'auth_id' => 'ZOMG-n33dz-2-migratez!',
					'account_id' => -1,
					'account_name' => $aScene->{static::KEY_token},
					'groups' => array( $this->getProp('AuthGroups')->getTitanGroupID() ),
			));
		}
		return $bAuthed;
	}

	/**
	 * See if we can validate the api/page request with an account.
	 * @param object $aScene - the Scene object associated with an Actor.
	 * @return boolean Returns TRUE if admitted.
	 * @see BaseModel::checkTicket()
	 */
	public function checkTicket($aScene)
	{
//		$this->logStuff(__METHOD__, ' bOnlyCheckHdrs?=', $aScene->bCheckOnlyHeadersForAuth); //DEBUG
//		try{throw new \Exception();}catch(\Exception $x){$this->logStuff(__METHOD__, ' stk=', $x->getTraceAsString());} //DEBUG
		
		$bAuthorized = false;
		if( $this->director->canConnectDb() )
		try {
			$this->removeStaleAuthLockoutTokens() ;

			$bAuthorizedViaHeaders = false;
			$bAuthorizedViaSession = false;
			$bAuthorizedViaWebForm = false;
			$bAuthorizedViaCookies = false;
			$bCsrfTokenWasBaked = false;

			$bAuthorizedViaHeaders = $this->checkHeadersForTicket($this, $aScene);
//			if ($bAuthorizedViaHeaders) $this->debugLog(__METHOD__.' header auth'); //DEBUG
			$bAuthorized = $bAuthorized || $bAuthorizedViaHeaders;
			if (!$bAuthorized && !$aScene->bCheckOnlyHeadersForAuth)
			{
				$bAuthorizedViaSession = $this->checkSessionForTicket($this, $aScene);
//				if ($bAuthorizedViaSession) $this->debugLog(__METHOD__.' session auth'); //DEBUG
				$bAuthorized = $bAuthorized || $bAuthorizedViaSession;
			}
			if (!$bAuthorized && !$aScene->bCheckOnlyHeadersForAuth)
			{
				$bAuthorizedViaWebForm = $this->checkWebFormForTicket($this, $aScene);
//				if ($bAuthorizedViaWebForm) $this->debugLog(__METHOD__.' webform auth'); //DEBUG
				$bAuthorized = $bAuthorized || $bAuthorizedViaWebForm;
			}
			if (!$bAuthorized && !$aScene->bCheckOnlyHeadersForAuth)
			{
				$bAuthorizedViaCookies = $this->checkCookiesForTicket($this, $_COOKIE);
//				if ($bAuthorizedViaCookies) $this->debugLog(__METHOD__.' cookie auth'); //DEBUG
				$bAuthorized = $bAuthorized || $bAuthorizedViaCookies;
			}

//			$this->logStuff(__METHOD__, ' bAuth=', $bAuthorized); //DEBUG
			if ($bAuthorized)
			{
				if ($bAuthorizedViaSession || $bAuthorizedViaWebForm || $bAuthorizedViaCookies)
				{
//					$this->debugLog(__METHOD__.' setCsrfTokenCookie call.'); //DEBUG
					$bCsrfTokenWasBaked = $this->setCsrfTokenCookie();
				}
				$this->onDetermineAuthAccount($aScene, $this->getDirector()->account_info);
			}
			
		}
		catch ( DbException $dbx )
		{ $bAuthorized = $this->checkInstallPwForMigration($aScene); }
		return $bAuthorized;
	}

	/**
	 * Activates or deactivates an account.
	 * @param integer $aAccountID the account ID.
	 * @param boolean $bActive indicates that the account should be activated
	 *  (true) or deactivated (false).
	 * @since BitsTheater 3.6
	 */
	public function setInvitation( $aAccountID, $bActive )
	{
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith( 'UPDATE ' . $this->tnAuth );
		$this->setAuditFieldsOnUpdate($theSql)
			->mustAddParam( 'is_active', ( $bActive ? 1 : 0 ), PDO::PARAM_INT )
			->startWhereClause()
			->mustAddParam( 'account_id', $aAccountID )
			->endWhereClause()
			;
		try { $theSql->execDML() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}

	/**
	 * Log the current user out and wipe the slate clean.
	 * @see \BitsTheater\models\PropCloset\AuthBase::ripTicket()
	 */
	public function ripTicket() {
		$this->setMySiteCookie(self::KEY_userinfo);
		$this->setMySiteCookie(self::KEY_token);
		$theSql = SqlBuilder::withModel($this);
		try {
			$theAcctInfo = $this->getDirector()->getMyAccountInfo();
			$this->removeTokensFor($theAcctInfo->auth_id, $theAcctInfo->account_id,
					self::TOKEN_PREFIX_COOKIE . '%'
			);
			//if successful, we should remove stale cookies as well
			$this->removeStaleCookies();
		} catch (Exception $e) {
			//do not care if removing cookies fails, log it so admin knows about it, though
			$theSql->logSqlFailure(__METHOD__, $e);
		}
		parent::ripTicket();
	}

	/**
	 * Deletes all tokens associated with the specified account ID.
	 * Caller must ensure that the account ID is not null.
	 * @param integer $aAccountID the account ID
	 * @return $this Returns $this for chaining.
	 * @since BitsTheater 3.6
	 */
	public function deleteFor( $aAccountID )
	{
		$bInTransaction = $this->db->inTransaction() ;
		if( ! $bInTransaction )
			$this->db->beginTransaction() ;

		$theSqlForMobile = SqlBuilder::withModel($this)
			->startWith( 'DELETE FROM ' . $this->tnAuthMobile )
			->startWhereClause()
			->mustAddParam( 'account_id', $aAccountID )
			->endWhereClause()
			;

		$theSqlForTokens = SqlBuilder::withModel($this)
			->startWith( 'DELETE FROM ' . $this->tnAuthTokens )
			->startWhereClause()
			->mustAddParam( 'account_id', $aAccountID )
			->endWhereClause()
			;

		$theSqlForAuth = SqlBuilder::withModel($this)
			->startWith( 'DELETE FROM ' . $this->tnAuth )
			->startWhereClause()
			->mustAddParam( 'account_id', $aAccountID )
			->endWhereClause()
			;

		try
		{
			$theSqlForMobile->execDML() ;
			$theSqlForTokens->execDML() ;
			$theSqlForAuth->execDML() ;
		}
		catch( PDOException $pdox )
		{
			if( ! $bInTransaction )
			{ // Roll back only if we controlled the transaction.
				$this->errorLog( __METHOD__ . 'failed. ' . $pdox->getMessage()) ;
				$this->db->rollBack() ;
			}
			throw new DbException( $pdox, __METHOD__ . ' failed.' ) ;
		}

		if( ! $bInTransaction )
			$this->db->commit() ;
	}

	/**
	 * Deletes all tokens associated with the specified account ID.
	 * @param string $aAuthID - the account ID
	 * @return $this Returns $this for chaining.
	 * @since BitsTheater 4.0.0
	 */
	public function deleteAuthAccount( $aAuthID )
	{
		$theSql = SqlBuilder::withModel($this);
		$theSql->beginTransaction();
		try {
			$theSql->reset()
				->startWith('DELETE FROM')->add($this->tnAuthMobile)
				->startWhereClause()
				->mustAddParam('auth_id', $aAuthID)
				->endWhereClause()
				->execDML()
				;
			$theSql->reset()
				->startWith('DELETE FROM')->add($this->tnAuthTokens)
				->startWhereClause()
				->mustAddParam('auth_id', $aAuthID)
				->endWhereClause()
				->execDML()
				;
			$theSql->reset()
				->startWith('DELETE FROM')->add($this->tnAuthOrgMap)
				->startWhereClause()
				->mustAddParam('auth_id', $aAuthID)
				->endWhereClause()
				->execDML()
				;
			$theSql->reset()
				->startWith('DELETE FROM')->add($this->tnAuthAccounts)
				->startWhereClause()
				->mustAddParam('auth_id', $aAuthID)
				->endWhereClause()
				->execDML()
				;
			$theSql->commitTransaction();
		}
		catch( PDOException $pdox )
		{
			$theSql->rollbackTransaction();
			throw $theSql->newDbException(__METHOD__, $pdox);
		}
	}

	/**
	 * Given the parameters, can a user register with them?
	 * @see \BitsTheater\models\PropCloset\AuthBase::canRegister()
	 */
	public function canRegister($aAcctName, $aEmailAddy) {
		$this->removeStaleRegistrationCapTokens();
		if ( $this->checkRegistrationCap() )
		{ return self::REGISTRATION_CAP_EXCEEDED; }

		$theResult = self::REGISTRATION_SUCCESS;
		if ( $this->getByName($aAcctName) )
		{ $theResult = self::REGISTRATION_NAME_TAKEN; }
		else if ( $this->getAuthByEmail($aEmailAddy) )
		{ $theResult = self::REGISTRATION_EMAIL_TAKEN; }
		return $theResult;
	}

	/**
	 * Sudo-type mechanism where "manager override" needs to take place.
	 * It can also be used as a way to prove the current user is still there.
	 * @param number $aAcctId - the account id of the user to auth.
	 * @param string $aPwInput - the entered pw.
	 * @return boolean Returns TRUE if user/pw matched.
	 */
	public function cudo($aAcctId, $aPwInput) {
		$theAuthData = $this->getAuthByAccountId($aAcctId);
		if (!empty($theAuthData['pwhash'])) {
			return (Strings::hasher($aPwInput,$theAuthData['pwhash']));
		} else {
			return false;
		}
	}

	/**
	 * Return currently logged in person's group memberships.
	 */
	public function belongsToGroups($aAcctId) {
		if (empty($aAcctId))
			return array();
		$dbAuthGroups = $this->getAuthGroupsProp();
		$theResult = $dbAuthGroups->getAcctGroups($aAcctId);
		$this->returnProp($dbAuthGroups);
		return $theResult;
	}

	/**
	 * Check your authority mechanism to determine if a permission is allowed.
	 * @param string $aNamespace - namespace of the permission.
	 * @param string $aPermission - name of the permission.
	 * @param string $acctInfo - (optional) check this account instead of current user.
	 * @return boolean Return TRUE if the permission is granted, FALSE otherwise.
	 */
	public function isPermissionAllowed($aNamespace, $aPermission, $acctInfo=null) {
		if (empty($this->dbPermissions))
			$this->dbPermissions = $this->director->getProp('Permissions'); //cleanup will close this model
		return $this->dbPermissions->isPermissionAllowed($aNamespace, $aPermission, $acctInfo);
	}

	/**
	 * Return the defined permission groups.
	 */
	public function getGroupList() {
		$dbAuthGroups = $this->getAuthGroupsProp();
		$theSql = SqlBuilder::withModel($dbAuthGroups)
			->startWith('SELECT * FROM')->add($dbAuthGroups->tnGroups)
			;
		try
		{ return $theSql->query()->fetchAll(); }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}

	/**
	 * Checks the given account information for membership.
	 * @param AccountInfoCache $aAccountInfo - the account info to check.
	 * @return boolean Returns FALSE if the account info matches a member account.
	 */
	public function isGuestAccount($aAccountInfo) {
		if (!empty($aAccountInfo) && !empty($aAccountInfo->account_id) && !empty($aAccountInfo->groups)) {
			return ( array_search(AuthGroupsDB::UNREG_GROUP_ID, $aAccountInfo->groups, true) !== false );
		} else {
			return true;
		}
	}

	/**
	 * Store device data so that we can determine if user/pw are required again.
	 * @param array $aAuthRow - an array containing the auth row data.
	 * @param $aHttpAuthHeader - the HTTP auth header object
	 * @return array Returns the field data saved.
	 */
	public function registerMobileFingerprints($aAuthRow, HttpAuthHeader $aHttpAuthHeader) {
		if (!empty($aAuthRow) && !empty($aHttpAuthHeader)) {
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
					'device_name' => $aHttpAuthHeader->getDeviceName(),
					'latitude' => $aHttpAuthHeader->getLatitude(),
					'longitude' => $aHttpAuthHeader->getLongitude(),
			));
			$theSql->startWith('INSERT INTO')->add($this->tnAuthMobile);
			$this->setAuditFieldsOnInsert($theSql);
			$theSql->mustAddParam('mobile_id', Strings::createUUID());
			$theSql->mustAddParam('auth_id', $aAuthRow['auth_id']);
			$theSql->mustAddParam('account_id', $aAuthRow['account_id'], PDO::PARAM_INT);
			$theUserToken = Strings::urlSafeRandomChars(64-36-1).':'.Strings::createUUID(); //unique 64char gibberish
			$theSql->mustAddParam('account_token', $theUserToken);
			$theSql->addParam('device_name');
			$theSql->addParam('latitude');
			$theSql->addParam('longitude');

			//do not store the fingerprints as if db is compromised, this might be
			//  considered "sensitive". just keep a hash instead, like a password.
			$theFingerprintHash = Strings::hasher($aHttpAuthHeader->fingerprints);
			$theSql->mustAddParam('fingerprint_hash', $theFingerprintHash);

			$theSql->execDML();

			//secret should remain secret, don't blab it back to caller.
			unset($theSql->myParams['fingerprint_hash']);

			return $theSql->myParams;
		}
	}

	/**
	 * It has been determined that the requestor has made a valid request, generate
	 * a new auth token and return it as well as place it as a cookie with duration of 1 day.
	 * @param number $aAcctId - the account id.
	 * @param string $aAuthId - the auth id.
	 * @param $aHttpAuthHeader - the HTTP auth header object
	 * @return string Returns the auth token generated.
	 */
	protected function generateAuthTokenForMobile($aAcctId, $aAuthId, HttpAuthHeader $aHttpAuthHeader) {
		//ensure we've cleaned house recently
		$this->removeStaleMobileAuthTokens();
		//see if we've already got a token for this device
		$theTokenPrefix = self::TOKEN_PREFIX_MOBILE;
		if (!empty($aHttpAuthHeader) && !empty($aHttpAuthHeader->mobile_id))
			$theTokenPrefix .= $aHttpAuthHeader->mobile_id;
		$theTokenList = $this->getAuthTokens($aAuthId, $aAcctId, $theTokenPrefix . '%', true);
		//if we have a token, return it, else create a new one
		if (!empty($theTokenList))
			$theAuthToken = $theTokenList[0]['token'];
		else
			$theAuthToken = $this->generateAuthToken($aAuthId, $aAcctId, $theTokenPrefix);
		return $theAuthToken;
	}

	/**
	 * Someone entered a user/pw combo correctly from a mobile device, give them tokens!
	 * @param AccountInfoCache $aAcctInfo - successfully logged in account info.
	 * @param $aHttpAuthHeader - the HTTP auth header object
	 * @return array|NULL Returns the tokens needed for ez-auth later.
	 */
	public function requestMobileAuthAfterPwLogin(AccountInfoCache $aAcctInfo, HttpAuthHeader $aHttpAuthHeader) {
		$theResults = null;
		$theAuthRow = $this->getAuthByAccountId($aAcctInfo->account_id);
		if (!empty($theAuthRow) && !empty($aHttpAuthHeader)) {
			$theMobileRow = null;
			//see if they have a mobile auth row already
			$theAuthMobileRows = $this->getAuthMobilesByAccountId($aAcctInfo->account_id);
			if (!empty($theAuthMobileRows)) {
				//see if fingerprints match any of the existing records and return that user_token if so
				foreach ($theAuthMobileRows as $theAuthMobileRow) {
					if (Strings::hasher($aHttpAuthHeader->fingerprints, $theAuthMobileRow['fingerprint_hash'])) {
						$theMobileRow = $theAuthMobileRow;
						break;
					}
				}
			}
			//$this->debugLog('mobile_pwlogin'.' mar='.$this->debugStr($theAuthMobileRow));
			if (empty($theMobileRow)) {
				//first time they logged in via this mobile device, record it
				$theMobileRow = $this->registerMobileFingerprints($theAuthRow, $aHttpAuthHeader);
			}
			if (!empty($theMobileRow)) {
				$theAuthToken = $this->generateAuthTokenForMobile($aAcctInfo->account_id,
						$theAuthRow['auth_id'], $aHttpAuthHeader
				);
				$theResults = array(
						'account_name' => $aAcctInfo->account_name,
						'auth_id' => $theAuthRow['auth_id'],
						'user_token' => $theMobileRow['account_token'],
						'auth_token' => $theAuthToken,
						'api_version_seq' => $this->getRes('website/api_version_seq'),
				);
			}
		}
		//$this->debugLog('mobile_pwlogin'.' r='.$this->debugStr($theResults));
		return $theResults;
	}

	/**
	 * A mobile app is trying to automagically log someone in based on a previously
	 * generated user token and their device fingerprints. If they mostly match, log them in
	 * and generate the proper token cookies.
	 * @param string $aAuthId - the account's auth_id
	 * @param string $aUserToken - the user token previously given by this website
	 * @param $aHttpAuthHeader - the HTTP auth header object
	 * @return array|NULL Returns the tokens needed for ez-auth later.
	 */
	public function requestMobileAuthAutomatedByTokens($aAuthId, $aUserToken, HttpAuthHeader $aHttpAuthHeader) {
		$theResults = null;
		$dbAccounts = $this->getProp('Accounts');
		$theAuthRow = $this->getAuthByAuthId($aAuthId);
		$theAcctRow = (!empty($theAuthRow)) ? $dbAccounts->getAccount($theAuthRow['account_id']) : null;
		if (!empty($theAcctRow) && !empty($theAuthRow) && !empty($aHttpAuthHeader)) {
//			$this->debugLog(__METHOD__.' AH='.$this->debugStr($aHttpAuthHeader)); //DEBUG
			//they must have a mobile auth row already
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
					'account_id' => $theAcctRow['account_id'],
					'account_token' => $aUserToken,
			));
			$theSql->startWith('SELECT * FROM')->add($this->tnAuthMobile);
			$theSql->startWhereClause()->mustAddParam('account_id');
			$theSql->setParamPrefix(' AND ')->mustAddParam('account_token');
			$theSql->endWhereClause();
//			$theSql->logSqlDebug(__METHOD__); //DEBUG
			$theAuthMobileRows = $theSql->query();
			if (!empty($theAuthMobileRows)) {
				//see if fingerprints match any of the existing records and return that user_token if so
				foreach ($theAuthMobileRows as $theAuthMobileRow) {
					if (Strings::hasher($aHttpAuthHeader->fingerprints, $theAuthMobileRow['fingerprint_hash'])) {
//						$this->debugLog(__METHOD__.' \o/'); //DEBUG
						$theAuthToken = $this->generateAuthTokenForMobile($theAcctRow['account_id'],
								$theAuthRow['auth_id'], $aHttpAuthHeader
						);
						$theResults = array(
								'account_name' => $theAcctRow['account_name'],
								'user_token' => $aUserToken,
								'auth_token' => $theAuthToken,
								'api_version_seq' => $this->getRes('website/api_version_seq'),
						);
//						$this->debugLog(__METHOD__.' r='.$this->debugStr($theResults)); //DEBUG
						break;
					}
//					else $this->debugLog(__METHOD__.' :cry:'); //DEBUG
				}
			}
		}
		return $theResults;
	}

	/**
	 * Checks the database to determine whether a password reset request should
	 * be allowed for a requestor who gave the specified email address as a
	 * contact point.
	 * @param string $aEmailAddr the email address
	 * @param mixed $aResetUtils an instance of the AuthPasswordReset costume,
	 *  which could be acted upon here (if changes should persist) or could be
	 *  left null
	 * @return boolean true only if the request should be granted
	 */
	public function isPasswordResetAllowedFor( $aEmailAddr, $aResetUtils=null )
	{
		if( empty($aEmailAddr) ) return false ;

		$this->debugLog( 'Someone requested a password reset for ['
				. $aEmailAddr . '].' ) ;

		$theResetUtils = null ;
		if( isset($aResetUtils) )
			$theResetUtils = &$aResetUtils ;
		else
			$theResetUtils = AuthPasswordReset::withModel($this) ;

		if( !$this->isConnected() ) return false ;

		$theAccountID = $theResetUtils->getAccountIDForEmail( $aEmailAddr ) ;
		if( empty( $theAccountID ) )
		{
			$this->debugLog( 'Password reset requested for [' . $aEmailAddr
					. '] but no account for that email was found.' )
					;
			return false ;
		}

		$theOldTokens = $theResetUtils->getTokens() ;
		if( $theResetUtils->hasRecentToken() )
		{
			$this->debugLog( 'Password reset request denied for ['
					. $aEmailAddr . ']; another recent token already exists.' )
					;
			return false ;
		}

		return true ;
	}

	/**
	 * Writes a new password reset request token into the database.
	 * @param AuthPasswordReset $aResetUtils an instance of the
	 *  AuthPasswordReset costume in which the account ID has already been
	 *  populated
	 * @return boolean true if successful, or an exception otherwise
	 */
	public function generatePasswordRequestFor( AuthPasswordReset $aResetUtils )
	{
		if( ! isset( $aResetUtils ) )
			throw PasswordResetException::toss( $this, 'EMPERORS_NEW_COSTUME' );

		// Might throw exceptions:
		$aResetUtils->generateToken()->deleteOldTokens() ;

		return true ; // and $aResetUtils got updated with more info
	}

	/**
	 * Once the registration cap is reached, subsequent registrations will run
	 * this method.
	 */
	public function onRegistrationCapReached() {
		//nothing to do, yet
	}

	/**
	 * Check to see if manual auth failed so often its locked out.
	 * @param AuthOrgs $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info.
	 * @return boolean Returns TRUE if too many failures locked out the account.
	 */
	protected function checkRegistrationCap() {
		$bLockedOut = false;
		$theMaxAttempts = intval($this->getConfigSetting('auth/max_registrations'), 25);
		if ($theMaxAttempts>0) {
			//once the number of tokens >= max attempts, session is locked
			//  session will unlock after tokens expire (currently 1 hour)
			//  note that tokens expire individually, so > 1 hour for all tokens to expire
			$theLockoutTokens = $this->getAuthTokens(
					$this->getDirector()->app_id,
					0,
					self::TOKEN_PREFIX_REGCAP.'%', true
			);
			$bLockedOut = (!empty($theLockoutTokens)) && (count($theLockoutTokens)>=$theMaxAttempts);
			if ($bLockedOut) {
				$this->onRegistrationCapReached();
			}
		}
		return $bLockedOut;
	}

	/**
	 * Registration has a cap; whenever we register, update our count in case we need
	 * to lockout this session for going over its cap.
	 */
	protected function updateRegistrationCap() {
		$theMaxAttempts = intval($this->getConfigSetting('auth/max_registrations'), 25);
		if ($theMaxAttempts>0) {
			//$this->debugLog(__METHOD__.' '.strval($theMaxAttempts));
			//add token
			$theAuthToken = $this->generateAuthToken(
					$this->getDirector()->app_id,
					0,
					self::TOKEN_PREFIX_REGCAP
			);
			//once the number of tokens >= max attempts, session is locked
			//  session will unlock after tokens expire (currently 1 hour)
			//  note that tokens expire individually, so > 1 hour for all tokens to expire
		}
	}

	/**
	 * Create and store an TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT token mapped
	 * to an account. The token is guaranteed to be universally unique.
	 * @param string $aAuthId - token mapped to auth record by this id.
	 * @param number $aAcctId - the account which will map to this token.
	 * @param string $aHardwareId - the hardware ID of the moblie device.
	 * @return string Return the token generated.
	 * @since BitsTheater 3.6.1
	 */
	public function generateAutoLoginForMobileDevice($aAuthId, $aAcctId, $aHardwareId) {
		$theAuthToken = self::TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT . ':'
			. $aHardwareId . ':'
			. Strings::createUUID()
		;
		$this->insertAuthToken($aAuthId, $aAcctId, $theAuthToken);
		return $theAuthToken;
	}

	/**
	 * Create and store an TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT token mapped
	 * to an account. The token is guaranteed to be universally unique.
	 * @param string $aAuthId - token mapped to auth record by this id.
	 * @param number $aAcctId - the account which will map to this token.
	 * @return string Return the tokens mapped to an account.
	 * @since BitsTheater 3.6.2
	 */
	public function getMobileHardwareIdsForAutoLogin($aAuthId, $aAcctId) {
		$theIds = array();
		$theAuthTokenRows = $this->getAuthTokens( $aAuthId, $aAcctId,
				self::TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT . ':%', true
		);
		if (!empty($theAuthTokenRows)) {
			foreach ($theAuthTokenRows as $theRow) {
				list($thePrefix, $theHardwareId, $theUUID) = explode(':', $theRow['token']);
				$theIds[] = $theHardwareId;
			}
		}
		return $theIds;
	}

	/**
	 * API fingerprints from mobile device. Recommended that
	 * your website mixes their order up, at the very least.
	 * @param string[] $aFingerprints - string array of device info.
	 * @return string[] Return a keyed array of device info.
	 * @since BitsTheater 3.6.1
	 */
	public function parseAuthBroadwayFingerprints($aFingerprints) {
		if (!empty($aFingerprints)) {
			return array(
					'app_signature' => $aFingerprints[0],
					'mobile_id' => $aFingerprints[1],
					'device_id' => $aFingerprints[2],
					'device_locale' => $aFingerprints[3],
					'device_memory' => (is_numeric($aFingerprints[4]) ? $aFingerprints[4] : null),
			);
		} else return array();
	}

	/**
	 * API circumstances from mobile device. Recommended that
	 * your website mixes their order up, at the very least.
	 * @param string[] $aCircumstances - string array of device meta,
	 * such as current GPS, user device name setting, current timestamp, etc.
	 * @return string[] Return a keyed array of device meta.
	 * @since BitsTheater 3.6.1
	 */
	public function parseAuthBroadwayCircumstances($aCircumstances) {
		if (!empty($aCircumstances)) {
			return array(
					'circumstance_ts' => $aCircumstances[0],
					'device_name' => $aCircumstances[1],
					'device_latitude' => (is_float($aCircumstances[2]) ? $aCircumstances[2] : null),
					'device_longitude' => (is_float($aCircumstances[3]) ? $aCircumstances[3] : null),
			);
		} else return array();
	}

	/**
	 * Given the AccountID, update the email associated with it.
	 * @param number $aAcctID - the account_id of the auth account.
	 * @param string $aEmail - the email to use.
	 */
	public function updateEmail( $aAcctID, $aEmail )
	{
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
				'account_id' => $aAcctID,
				'email' => $aEmail,
		));
		$theSql->startWith('UPDATE')->add($this->tnAuthAccounts);
		$this->setAuditFieldsOnUpdate($theSql);
		$theSql->mustAddParam('email');
		$theSql->startWhereClause()->mustAddParam('account_id')->endWhereClause();
		try { $theSql->execDML() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}
	
	/**
	 * Given the AccountID, update the password associated with it.
	 * @param number $aAcctID - the account_id of the auth account.
	 * @param string $aPw - the paintext password to hash-n-store.
	 */
	public function updatePassword( $aAcctID, $aPw )
	{
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
				'account_id' => $aAcctID,
				'pwhash' => Strings::hasher($aPw),
		));
		$theSql->startWith('UPDATE')->add($this->tnAuthAccounts);
		$this->setAuditFieldsOnUpdate($theSql);
		$theSql->mustAddParam('pwhash');
		$theSql->startWhereClause()->mustAddParam('account_id')->endWhereClause();
		try { $theSql->execDML() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}
	
	/**
	 * Convert an array of account_ids to auth_ids.
	 * @param int[] $aAccountIDs - the account_id list.
	 * @return string[] Returns the list of auth_ids.
	 */
	public function cnvAccountIDsToAuthIDs($aAccountIDs)
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT auth_id FROM')->add($this->tnAuthAccounts)
			->startWhereClause()
			->mustAddParam('account_id', $aAccountIDs)
			->endWhereClause()
			;
		try { return $theSql->query()->fetchAll(\PDO::FETCH_COLUMN, 0); }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
		
}//end class

}//end namespace
