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
use BitsTheater\costumes\venue\IWillCall;
use BitsTheater\costumes\venue\TicketViaAuthHeaderBasic;
use BitsTheater\costumes\venue\TicketViaAuthMigration;
use BitsTheater\costumes\venue\TicketViaCookie;
use BitsTheater\costumes\venue\TicketViaRequest;
use BitsTheater\costumes\venue\TicketViaSession;
use BitsTheater\costumes\venue\TicketViaURL;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\costumes\AuthAccount;
use BitsTheater\costumes\AuthOrg;
use BitsTheater\costumes\AuthPasswordReset;
use BitsTheater\costumes\DbAdmin;
use BitsTheater\costumes\DbConnInfo;
use BitsTheater\costumes\IDirected;
use BitsTheater\costumes\IFeatureVersioning;
use BitsTheater\costumes\ISqlSanitizer;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\WornByIDirectedForValidation;
use BitsTheater\costumes\WornForAuditFields;
use BitsTheater\costumes\WornForFeatureVersioning;
use BitsTheater\models\AccountPrefs ;                          // 3.2.15 (#3288)
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
	 *  <li value="3">
	 *   Add Org <span style="font-family:monospace">`parent_authgroup_id`</span> UUID field.
	 *  </li>
	 * </ol>
	 * @var integer
	 */
	const FEATURE_VERSION_SEQ = 3; //always ++ when making db schema changes

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
	/** @var string The session key used to store current mobile row. */
	const KEY_MobileInfo = 'TicketEnvelope';
	/** @var string The cookie used to store the user's current org ID. */
	const KEY_org_token = 'SeatingSection';
	

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
	/**
	 * A long process is working.
	 * @var string
	 * @since BitsTheater [NEXT]
	 */
	const TOKEN_PREFIX_LONG_PROCESS_INPROGRESS = 'iP';
	/**
	 * Sometimes we need an actual value besides NULL to mean the
	 * root org, use this value.
	 * @var string
	 * @since BitsTheater 4.2.2
	 */
	const ORG_ID_4_ROOT = '__ROOT-ORG-ID__';
	/**
	 * Flag used to detect when a mobile device should be active.
	 * @var string
	 */
	const MOBILE_AUTH_TYPE_ACTIVE = 'FULL_ACCESS';
	/**
	 * Flag used to detect when a mobile device should be denied.
	 * @var string
	 */
	const MOBILE_AUTH_TYPE_INACTIVE = 'DENIED';
	/**
	 * Flag used to detect when a mobile device should be re-paired
	 * with the server using IMEI or some such value instead of matching
	 * the fingerprint hash.
	 * @var string
	 */
	const MOBILE_AUTH_TYPE_RESET = '__RESET__';

	public function setupAfterDbConnected()
	{
		parent::setupAfterDbConnected();
		$this->tnAuthAccounts = $this->tbl_.static::TABLE_AuthAccounts;
		$this->tnAuthTokens = $this->tbl_.static::TABLE_AuthTokens;
		$this->tnAuthMobile = $this->tbl_.static::TABLE_AuthMobile;
		$this->tnAuthOrgs = $this->tbl_.static::TABLE_AuthOrgs;
		$this->tnAuthOrgMap = $this->tbl_.static::TABLE_AuthOrgMap;
		//backwards compatible aliases
		$this->tnAccounts = $this->tbl_.static::TABLE_Accounts;
		$this->tnAuth = $this->tbl_.static::TABLE_Auth;
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
		case static::TABLE_AuthAccounts:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthAccounts;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} " .
						'( auth_id ' . CommonMySql::TYPE_UUID . " NOT NULL COMMENT 'cross-db ID'" .
						", account_id INT NOT NULL AUTO_INCREMENT COMMENT 'user-friendly ID'" .
						', account_name VARCHAR(60) NOT NULL' .
						', email VARCHAR(255) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL' .
						', pwhash ' . CommonMySql::TYPE_ASCII_CHAR(85) . ' NOT NULL' . //blowfish hash of pw & its salt
						', external_id ' . CommonMySql::TYPE_UUID . ' NULL' .
						', verified_ts TIMESTAMP NULL' . //useless until email verification implemented
						', last_seen_ts TIMESTAMP NULL' .
						', is_active ' . CommonMySql::TYPE_BOOLEAN_1 .
						', ' . CommonMySql::getAuditFieldsForTableDefSql() .
						', PRIMARY KEY (auth_id)' .
						', UNIQUE KEY (account_id)' .
						', UNIQUE KEY (account_name)' .
						', UNIQUE KEY (email)' .
						', KEY (external_id)' .
						') ' . CommonMySql::TABLE_SPEC_FOR_UNICODE;
			}//switch dbType
		case static::TABLE_AuthTokens:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthTokens;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						'( `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY' . //strictly for phpMyAdmin ease of use
						', `auth_id` ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', `account_id` INT NULL' .
						', `token` ' . CommonMySql::TYPE_ASCII_CHAR(128) . ' NOT NULL' .
						', ' . CommonMySql::getAuditFieldsForTableDefSql() .
						', INDEX IdxAuthIdToken (`auth_id`, `token`)' .
						', INDEX IdxAcctIdToken (`account_id`, `token`)' .
						', INDEX IdxAuthToken (`token`, `updated_ts`)' .
						')';
			}//switch dbType
		case static::TABLE_AuthMobile: //added in v3
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthMobile;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						'( `mobile_id` ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', `auth_id` ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', `account_id` INT NULL' .
						', `auth_type` ' . CommonMySql::TYPE_ASCII_CHAR(16) . " NOT NULL DEFAULT 'FULL_ACCESS'" .
						', `account_token` ' . CommonMySql::TYPE_ASCII_CHAR(64) . " NOT NULL DEFAULT 'STRANGE_TOKEN'" .
						', `device_name` VARCHAR(64) NULL' .
						', `latitude` DECIMAL(11,8) NULL' .
						', `longitude` DECIMAL(11,8) NULL' .
						', `fingerprint_hash` ' . CommonMySql::TYPE_ASCII_CHAR(85) . ' NULL' .
						', ' . CommonMySql::getAuditFieldsForTableDefSql() .
						', PRIMARY KEY (`mobile_id`)' .
						', KEY (`auth_id`)' .
						', KEY (`account_id`)' .
						')';
			}//switch dbType
		case static::TABLE_AuthOrgs:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthOrgs;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} " .
						'( org_id ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', org_name VARCHAR(60) NOT NULL' . " COMMENT 'e.g. acmelabs'" .
						', org_title VARCHAR(200) NULL' . " COMMENT 'e.g. Acme Labs, LLC'" .
						', org_desc VARCHAR(2048) NULL' .
						', parent_org_id ' . CommonMySql::TYPE_UUID . ' NULL' .
						', parent_authgroup_id ' . CommonMySql::TYPE_UUID . ' NULL' .
						', dbconn VARCHAR(1020) NULL' .
						', ' . CommonMySql::getAuditFieldsForTableDefSql() .
						', PRIMARY KEY (org_id)' .
						', KEY (parent_org_id)' .
						', KEY (parent_authgroup_id)' .
						', UNIQUE KEY (org_name)' .
						') ' . CommonMySql::TABLE_SPEC_FOR_UNICODE;
			}//switch dbType
		case static::TABLE_AuthOrgMap:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthOrgMap;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} " .
						'( auth_id ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', org_id ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', ' . CommonMySql::getAuditFieldsForTableDefSql() .
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
		$this->setupTable( static::TABLE_AuthAccounts, $this->tnAuthAccounts ) ;
		$this->setupTable( static::TABLE_AuthTokens, $this->tnAuthTokens ) ;
		$this->setupTable( static::TABLE_AuthMobile, $this->tnAuthMobile ) ;
		$this->setupTable( static::TABLE_AuthOrgs, $this->tnAuthOrgs ) ;
		$this->setupTable( static::TABLE_AuthOrgMap, $this->tnAuthOrgMap ) ;
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
				else if ( !$this->isFieldExists('parent_authgroup_id', $this->tnAuthOrgs) )
					return 2;
		}//switch
		return static::FEATURE_VERSION_SEQ ;
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
		$this->logStuff('Running ', __METHOD__, ' v'.$theSeq.'->v'.static::FEATURE_VERSION_SEQ);

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
				$this->addFieldToTable(3, 'parent_authgroup_id', $this->tnAuthOrgs,
						'parent_authgroup_id ' . CommonMySql::TYPE_UUID . ' NULL',
						'parent_org_id'
				);
				$theSql = 'ALTER TABLE ' . $this->tnAuthOrgs . ' ADD KEY (parent_authgroup_id)';
				$this->execDML($theSql);
				$this->logStuff('[v3] added index for parent_authgroup_id.');
			}
			case ( $theSeq < 4 ):
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
		SqlBuilder::withModel($dbOldAccounts)
			->startWith('DROP TABLE')->add($dbOldAccounts->tnAccounts)
			->execDML()
			;
		$this->logStuff(' dropped old table ', $dbOldAccounts->tnAccounts);
		SqlBuilder::withModel($dbOldAuthBasic)
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
	 * Check the account information to ensure it passes muster.
	 * @param SqlBuilder $aSqlBuilder - the builder object to check.
	 */
	protected function checkNewAuthAccountInfo( SqlBuilder $aSql )
	{
		//ensure email is not blank
		$theEmail = trim($aSql->getParamValue('email'));
		$this->checkIsNotEmpty('email', $theEmail);
		$aSql->setParamValue('email', $theEmail);
		
		//ensure account_name is not blank
		$theAcctName = trim($aSql->getParamValue('account_name'));
		if ( empty($theAcctName) ) {
			$theAcctName = trim($aSql->getParamValue(static::KEY_userinfo));
		}
		$this->checkIsNotEmpty('account_name', $theAcctName);
		$aSql->setParamValue('account_name', $theAcctName);
		//see if user is registering or if an admin is creating
		if ( $this->isGuest() ) {
			$aSql->setParamValueIfEmpty('created_by', $theAcctName);
		}
		
		// ensure pwhash|pwInput is not empty
		$thePwHash = $aSql->getParamValue('pwhash');
		if ( empty($thePwHash) ) {
			// see if we have pwInput, convert to hash if so
			$thePwInput = trim($aSql->getParamValue(static::KEY_pwinput));
			$this->checkIsNotEmpty(static::KEY_pwinput, $thePwInput);
			$thePwHash = Strings::hasher($thePwInput);
			$aSql->setParamValue('pwhash', $thePwHash);
		}
		$this->checkIsNotEmpty('encoded password data', $thePwHash);
		
		//some params have alternate/legacy names.
		$aSql->setParamValueIfEmpty('verified_ts',
				$aSql->getParamValue('verified_timestamp')
		);
		
		//ensure account ID is an actual integer value and not 0.
		$aSql->setParamValueIfEmpty('account_id',
			Strings::toInt($aSql->getParamValue('account_id'))
		);
		//ensure account_id is not already taken
		$theAcctID = $aSql->getParamValue('account_id');
		if ( isset($theAcctID) ) {
			$thePossibleAcct = $this->getAccount($theAcctID);
			if ( !empty($thePossibleAcct) ) {
				throw AccountAdminException::toss($this,
						AccountAdminException::ACT_UNIQUE_FIELD_ALREADY_EXISTS,
						'account_id'
				);
			}
		}
		
		//ensure account_is_active is respected, if defined
		$bAccountIsActive = $aSql->getParamValue('account_is_active');
		if ( !isset($bAccountIsActive) ) {
			$bAccountIsActive = $aSql->getParamValue('is_active');
		}
		$bIsActive = filter_var($bAccountIsActive,
				FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE
		);
		$aSql->setParamValueIfEmpty('is_active', ($bIsActive) ? 1 : 0);
	}
	
	/**
	 * Insert an auth account record.
	 * @param array|object $aDataObject - the (usually Scene) object containing
	 *   the data to be used.
	 * @return array Returns the data posted to the database.
	 */
	public function addAuthAccount($aDataObject)
	{
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aDataObject);
		$this->checkNewAuthAccountInfo($theSql);
		$theSql->startWith('INSERT INTO')->add($this->tnAuthAccounts);
		$this->setAuditFieldsOnInsert($theSql);
		if ( strtolower(trim($theSql->getParam('verified_ts')))=='now' ) {
			$theSql->setParamValue('verified_ts',
					$theSql->getParamValue('created_ts')
			);
		}
		$theSql->setParamValueIfEmpty('auth_id', Strings::createUUID())
			->addParam('auth_id')
			->addParam('account_name')
			->addParam('email')
			->addParam('pwhash')
			->addParam('verified_ts')
			//->addParam('last_seen_ts') we're just now creating it!
			->setParamType(\PDO::PARAM_INT)
			->addParam('account_id')
			->addParam('is_active')
			->addParam('external_id')
			//->logSqlDebug(__METHOD__)
			;
		try
		{
			$theSql->execDML();
			return $this->getAuthByAuthId($theSql->getParamValue('auth_id'));
		}
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}

	/**
	 * Register an account with our website.
	 * @param array|object $aUserData - account information.
	 * @param number|array $aAuthGroups - (optional) auth group membership(s).
	 * @return array Returns account info with ID if succeeds, NULL otherwise.
	 * @throws DbException
	 */
	public function registerAccount( $aUserData, $aAuthGroups=null )
	{
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aUserData);
		$theSql->beginTransaction();
		try {
			$dbAuthGroups = $this->getAuthGroupsProp();
			// now add our new auth account
			$theResult = $this->addAuthAccount($aUserData);
			// perform any group mapping
			if ( is_array($aAuthGroups) ) {
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
			$theOrgList = $theSql->getParamValue('org_ids');
			//always set current org at a minimum, if not Root
			if ( empty($theOrgList) ) {
				$theCurrOrgID = $this->getCurrentOrgID();
				if ( !empty($theCurrOrgID) ) {
					$theOrgList = array($theCurrOrgID);
				}
			}
			if ( is_array($theOrgList) && !empty($theOrgList) )
			{
				$this->addOrgsToAuth(
						$theResult['auth_id'], $theOrgList
				);
				$theResult['org_ids'] = $theOrgList;
			}
			//inc reg cap
			$this->updateRegistrationCap();
			//commit it all
			$theSql->commitTransaction();
			//success!
			return $theResult;
		}
		catch (PDOException $pdox) {
			$this->errorLog( __METHOD__ . ' failed: ' . $pdox->getMessage());
			$theSql->rollbackTransaction();
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
	 * See if we have a valid org defined for the current user, using a default if
	 * not and set the account information to use it as the current org.
	 * @param object $aScene - the Scene object in use that holds client input.
	 * @param AccountInfoCache $aAuthAccount - (optional) the logged in auth account.
	 * @return $this Returns $this for chaining.
	 */
	public function checkForDefaultOrg( $aScene, AccountInfoCache $aAuthAccount=null )
	{
		if ( !empty($aAuthAccount) && !$this->isEmpty($this->tnAuthOrgMap) &&
				empty($aAuthAccount->mSeatingSection)
		) {
			//see if we have transcend rights, which only exist in Root org
			$bCanTranscend = $this->isAllowed( 'auth_orgs', 'transcend' );
			//reset loaded rights so when we swap to another database, we reload them
			$aAuthAccount->rights = null;
			$aAuthAccount->groups = null;
			//see if we have an org cookie to use
			$theDefaultOrgID = $this->getOrgIDFromAuthCookie();
			if ( empty($theDefaultOrgID) ) {
				//check the user preference for what org we default to on login
				$dbPrefs = $this->getProp( AccountPrefs::MODEL_NAME ) ;
				$theDefaultOrgID = $dbPrefs->getPreference(
						$aAuthAccount->auth_id, 'organization', 'default_org'
				);
				$this->returnProp($dbPrefs);
			}
			if ( !empty($theDefaultOrgID) && $theDefaultOrgID != static::ORG_ID_4_ROOT ) {
				if ( $bCanTranscend || $this->isAccountMappedToOrg($aAuthAccount->auth_id, $theDefaultOrgID) ) {
					$theOrgRow = $this->getOrganization($theDefaultOrgID);
					$aAuthAccount->setSeatingSection($theOrgRow);
					$this->logStuff($aAuthAccount->account_name,
							' logging in to ORG_ID [', $theDefaultOrgID, ']',
							', "', $theOrgRow['org_name'], '/', $theOrgRow['org_title'], '"'
					);
					return $this;
				}
			}
			// No user preference found or not allowed to default to selected org.
			//   Grab the first one that is authorized.
			$theOrgRow = $this->getOrgsForAuthCursor( $aAuthAccount->auth_id )->fetch() ;
			if( !empty($theOrgRow) && isset( $theOrgRow['dbconn'] ) )
			{ // We found something, so pick it.
				$aAuthAccount->setSeatingSection($theOrgRow);
				$this->logStuff($aAuthAccount->account_name,
						' logging in to ORG_ID [', $theOrgRow['org_id'], ']',
						', "', $theOrgRow['org_name'], '/', $theOrgRow['org_title'], '"'
				);
				return $this;
			}
		}
		return $this;
	}
	
	/**
	 * See if we need to swap to a differnt org other than Root.
	 * @param object $aScene - the Scene object in use that holds client input.
	 * @param AccountInfoCache $aAuthAccount - (optional) the logged in auth account.
	 */
	public function swapToCurrentOrg( $aScene, AccountInfoCache $aAuthAccount=null )
	{
		if ( !empty($aAuthAccount) && !empty($aAuthAccount->mSeatingSection) ) {
			$theOrgRow = $this->getOrganization($aAuthAccount->mSeatingSection->org_id) ;
			//$this->logStuff(__METHOD__, ' got org=', $theOrgRow); //DEBUG
			if ( !empty($theOrgRow) && isset( $theOrgRow['dbconn'] ) ) {
				$this->setCurrentOrg($theOrgRow);
				//$this->logStuff(__METHOD__, ' setting org to ', $theOrgRow['org_id']); return; //DEBUG
			}
		}
		//$this->logStuff(__METHOD__, ' setting org to Root'); //DEBUG
	}
	
	/**
	 * Event to be called immediately upon determining when a account is "logged in".
	 * @param object $aScene - the Scene object in use that holds client input.
	 * @param AccountInfoCache $aAuthAccount - (optional) the logged in auth account.
	 */
	public function onDetermineAuthAccount( $aScene, AccountInfoCache $aAuthAccount=null )
	{
		if ( !empty($aAuthAccount) && //ensure we have a valid auth & account_id
				!empty($aAuthAccount->auth_id) && $aAuthAccount->account_id > 0 )
		{
			$this->getDirector()->setMyAccountInfo($aAuthAccount);
			//update last login info
			$aAuthAccount->last_seen_dt = new \DateTime('now', new \DateTimeZone('UTC'));
			$aAuthAccount->last_seen_ts = $this->getDateTimeAsDbTimestampFormat(
					$aAuthAccount->last_seen_dt
			);
			$this->updateAuthAccount((object)array(
					'auth_id' => $aAuthAccount->auth_id,
					'last_seen_ts' => $aAuthAccount->last_seen_ts,
			));
		}
	}
	
	/**
	 * Event to be called once an account is officially "logged in", after
	 * onDetermineAuthAccount() and the specific venue's onTicketAccepted() method.
	 * @param object $aScene - the Scene object in use that holds client input.
	 * @param AccountInfoCache $aAuthAccount - (optional) the logged in auth account.
	 */
	public function onSeatTicketHolder( $aScene, AccountInfoCache $aAuthAccount=null )
	{
		//determine what org to use
		$this->swapToCurrentOrg($aScene, $aAuthAccount);
		//if groups is still empty, ensure we have tried to load them
		if ( !empty($aAuthAccount) && empty($aAuthAccount->groups) ) {
			$aAuthAccount->loadGroupsList();
		}
	}
	
	/**
	 * Indicates whether the given account ID has been mapped into the given
	 * org ID.
	 * @param string $aAuthID - an account ID
	 * @param string $aOrgID - an organization ID, NULL is Root as well as the
	 *   ORG_ID_4_ROOT const.
	 * @return boolean Returns TRUE if the account is mapped to that org.
	 */
	public function isAccountMappedToOrg( $aAuthID, $aOrgID=null )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT COUNT(*) AS theCount FROM ' )
			->add( $this->tnAuthOrgMap )
			->startWhereClause()
			->mustAddParam( 'auth_id', $aAuthID )
			;
		if ( !empty($aOrgID) && $aOrgID != static::ORG_ID_4_ROOT ) {
			//only non-Root orgs are mapped to an account
			$theSql->setParamPrefix( ' AND ' )
				->mustAddParam( 'org_id', $aOrgID )
				;
		}
		$theSql->endWhereClause()
			//->logSqlDebug( __METHOD__, ' [TRACE] ' )
			;
		try
		{
			$theResult = $theSql->getTheRow() ;
			if ( !empty($aOrgID) && $aOrgID != static::ORG_ID_4_ROOT ) {
				//if we are checking for a specific org, count needs to be >0
				return !empty($theResult['theCount']);
			}
			else {
				//if we are checking for Root, count needs to be =0
				return empty($theResult['theCount']);
			}
		}
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}
	
	/**
	 * Get the current org ID being viewed.
	 * @return string|NULL Returns the org_id.
	 */
	public function getCurrentOrgID()
	{
		$theOrg = static::getCurrentOrg($this);
		if ( !empty($theOrg) ) {
			return $theOrg->org_id;
		}
		else {
			return null;
		}
	}
	
	/**
	 * Set the current org to be viewed.
	 * @param array $aOrgRow - the org data to use.
	 * @throws DbException if fail to swap to the new org database connection.
	 * @return $this Returns $this for chaining.
	 */
	public function setCurrentOrg( $aOrgRow=null )
	{
		$theAcctInfo = $this->getDirector()->getMyAccountInfo();
		if ( empty($theAcctInfo) ) return; //trivial
		//$this->logStuff(__METHOD__, ' switch2org=', $aOrgRow); //DEBUG
		if ( !empty($aOrgRow) && !empty($aOrgRow['dbconn']) ) {
			$theOrg = AuthOrg::getInstance($this, $aOrgRow);
			$theOrgID = $theOrg->org_id;
		}
		else {
			$theOrg = null;
			$theOrgID = null;
		}
		$this->getDirector()->setPropDefaultOrg($theOrgID);
		//ensure we store the current org
		$theAcctInfo->setSeatingSection($theOrg);
		// (#6288) Need to reload groups as well, since we may have switched orgs
		$theAcctInfo->loadGroupsList();
		if( $this->isAccountInSessionCache() )
		{ // Ensure that the session's account cache is really updated.
			$this->saveAccountToSessionCache($theAcctInfo);
		}
		// Ensure that the if cookies are used, we save org info, too.
		return $this->updateCookieForOrg($theOrg);
	}
	
	/**
	 * Set the Org in use by just its ID.
	 * @param string $aOrgID - the org_id to switch to.
	 * @throws DbException if fail to swap to the new org database connection.
	 * @return $this Returns $this for chaining.
	 */
	public function setCurrentOrgByID( $aOrgID )
	{
		//get our org data - do not use the AuthOrg costume as we need
		//  the dbconn info which the costume does not provide (security
		//  precaution against accidentally exporting back to a client).
		if ( !empty($aOrgID) && $aOrgID != static::ORG_ID_4_ROOT )
		{ $theOrg = $this->getOrganization($aOrgID); }
		else
		{ $theOrg = null; }
		$this->setCurrentOrg($theOrg);
		return $this;
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

	public function getAuthByName( $aName )
	{ return $this->getByName($aName); }

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
	 * Get a cursor to all orgs.
	 * @param string|string[] $aFieldList - (optional) which fields to return, default is all of them.
	 * @return \PDOStatement Returns the query.
	 */
	public function getOrgsCursor( $aFieldList=null )
	{
		$theSql = SqlBuilder::withModel( $this );
		$theSql->startWith('SELECT')->addFieldList($aFieldList)
			->add('FROM')->add($this->tnAuthOrgs)
			;
		try
		{ return $theSql->query(); }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * For a given organization <i>name</i>, returns the record from the DB.
	 * Note that the <code>org_name</code> column is defined as
	 * <code>UNIQUE</code> in the DB schema, so this should not be a dangerous
	 * search.
	 * @param string $aName the organization name
	 * @param string[] $aFieldList (optional) subset of fields to return;
	 *  defaults to all fields
	 * @return boolean|NULL|array the database row as an associative array if
	 *  the org is found, or <code>null</code> if not found, or
	 *  <code>false</code> under certain error conditions.
	 * @since BitsTheater v4.1.0
	 */
	public function getOrgByName( $aName=null, $aFieldList=null )
	{
		if( $aName === null )
		{
			$this->errorLog( __METHOD__ . ' was called with no org name.' ) ;
			return false ;
		}
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT' )->addFieldList( $aFieldList )
			->add( 'FROM' )->add( $this->tnAuthOrgs )
			->startWhereClause()
			->mustAddParam( 'org_name', $aName )
			->endWhereClause()
//			->logSqlDebug( __METHOD__, ' [TRACE] ' )
			;
		try { return $theSql->getTheRow() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
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
	public function getOrganizationsToDisplay(ISqlSanitizer $aSqlSanitizer=null,
			SqlBuilder $aFilter=null, $aFieldList=null)
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
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
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
			->addParam('parent_authgroup_id')
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
			->addParamIfDefined('parent_authgroup_id')
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
			// Optionally restrict delete to list of org IDs
			->setParamPrefix(' AND ')
			->setParamValueIfEmpty('org_id', $aOrgIDs)
			->addParam('org_id')
			->endWhereClause()
			//->logSqlDebug( __METHOD__, ' [DEBUG] ' )
			;
		try { $theSql->execDML(); }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}
	
	/**
	 * For a given organization id, returns all of the immediate child orgs.
	 * @param string|string[] $aOrgId - a single ID or an array of several IDs.
	 * @param string|string[] $aFieldList - (optional) which fields to return, default is all of them.
	 * @param SqlBuilder $aFilter - (optional) specifies restrictions on data to return
	 * @param array $aSortList - (optional) sort the results: keys are the fields => values are
	 *    'ASC'|true or 'DESC'|false with null='ASC'.
	 * @return array Returns all rows as an array.
	 */
	public function getOrgChildrenForOrgCursor( $aOrgID, $aFieldList=null,
			$aFilter=null, $aSortList=null )
	{
		$theSql = SqlBuilder::withModel($this)
			->startWith('SELECT')->addFieldList($aFieldList)
			->add('FROM')->add($this->tnAuthOrgs)
			->startWhereClause()
			->mustAddParam('parent_org_id', $aOrgID)
			->setParamPrefix(' AND ')
			->applyFilter($aFilter)
			->endWhereClause()
			;
		if ( !empty($aSortList) )
		{ $theSql->applySortList($aSortList); }
		try
		{ return $theSql->query(); }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * For a given organization id, returns all child orgs.
	 * @param string|string[] $aOrgId - a single ID or an array of several IDs.
	 * @return string[] Returns all IDs as an array of strings.
	 */
	public function getOrgAndAllChildrenIDs( $aOrgID )
	{
		$theResultSet = array($aOrgID);
		$theSql = SqlBuilder::withModel($this)
			->startWith('SELECT org_id')
			->add('FROM')->add($this->tnAuthOrgs)
			->startWhereClause()
			->mustAddParam('parent_org_id', $aOrgID)
			->endWhereClause()
			;
		try
		{
			$theOrgList = $theSql->query()->fetchAll(\PDO::FETCH_COLUMN);
			foreach($theOrgList as $theOrgID) {
				$theResultSet = array_merge($theResultSet,
						$this->getOrgAndAllChildrenIDs($theOrgID)
				);
			}
		}
		catch( PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
		return $theResultSet;
	}
	
	/**
	 * Get which org we are currently "in".
	 * @param IDirected $aContext - the context to use.
	 * @return AuthOrg|null Returns the active org row data.
	 *   If there is no active org, NULL is returned.
	 */
	static public function getCurrentOrg( IDirected $aContext )
	{
		if ( !empty($aContext->getDirector()->account_info) ) {
			return $aContext->getDirector()->account_info->mSeatingSection;
		}
		else {
			return null;
		}
	}
	
	/**
	 * @return void|AccountInfoCache Returns the account in session cache.
	 */
	public function loadAccountFromSessionCache()
	{
		if ( !$this->isAccountInSessionCache() ) return; //trivial
		$theAuthRow = json_decode($this->getDirector()[static::KEY_userinfo]);
		if ( !empty($theAuthRow) ) {
			return $this->createAccountInfoObj($theAuthRow);
		}
	}
	
	/**
	 * Save the given account to the PHP session cache.
	 * @param AccountInfoCache $aAcctInfo - the auth account to save.
	 */
	public function saveAccountToSessionCache( AccountInfoCache $aAcctInfo=null )
	{
		//save ticket short term cache
		if ( !empty($aAcctInfo) ) {
			$this->getDirector()[static::KEY_userinfo] = $aAcctInfo->toJson();
		}
		else {
			unset($this->getDirector()[static::KEY_userinfo]);
		}
	}
	
	/** @return boolean Returns TRUE if an account is in session cache. */
	public function isAccountInSessionCache()
	{
		return (
				!empty($this->getDirector()[static::KEY_userinfo]) &&
				!is_numeric($this->getDirector()[static::KEY_userinfo])
				);
	}
	
	/**
	 * Check for our auth token(s) and return TRUE if they exist.
	 * @param string[] $aCookieJar - (OPTIONAL) the string array holding the
	 *   cookies to check. Defaults to using PHP's $_COOKIE global variable.
	 * @return boolean Returns TRUE if the cookies exist.
	 */
	public function isAccountInCookieJar( $aCookieJar=null )
	{
		if ( empty($aCookieJar) ) {
			$aCookieJar = &$_COOKIE;
		}
		return ( !empty($aCookieJar[static::KEY_userinfo]) &&
				!empty($aCookieJar[static::KEY_token]) );
	}
	
	
	//=========================================================================
	//===============    From AuthBasic          ==============================
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
	
	/**
	 * Retrieve a specific token for a given auth ID, if it exists.
	 * @param string $aAuthId - the Auth ID.
	 * @param string $aAuthToken - the token (LIKE is not used).
	 * @return string[]|null Returns the data row.
	 */
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
		$theSql = "SELECT * FROM {$this->tnAuthMobile} WHERE auth_id=:id ORDER BY created_ts DESC";
		$ps = $this->query($theSql, array('id' => $aAuthId));
		if (!empty($ps)) {
			return $ps->fetchAll();
		}
	}

	/**
	 * Show the auth accounts for display (pager and such).
	 * @param ISqlSanitizer $aSqlSanitizer - the SQL sanitizer obj being used.
	 * @param SqlBuilder $aFilter - (optional) Specifies restrictions on
	 *   data to return; effectively populating a WHERE filter for the query.
	 * @param string[]|NULL $aFieldList - (optional) String list representing
	 *   which columns to return. Leaving this argument blank defaults to
	 *   returning all table column fields.
	 * @throws DBException
	 * @return \PDOStatement Returns the query result.
	 */
	public function getAuthAccountsToDisplay(ISqlSanitizer $aSqlSanitizer=null,
			 SqlBuilder $aFilter=null, $aFieldList=null)
	{
		if ( empty($aFieldList) ) {
			$aFieldList = array(
					'*',
					'hardware_ids',
			);
		}
		if ( is_array($aFieldList) ) {
			$theIdx = array_search('hardware_ids', $aFieldList);
			if ( $theIdx !== false ) {
				//find mapped hardware ids, if any
				//  NOTE: AuthAccount costume converts this field into the appropriate string
				$aFieldList[$theIdx] = AuthAccount::sqlForHardwareIDs($this,
						$this->tnAuthAccounts . '.auth_id'
				);
			}
		}
		//restrict results to current org and its children
		$theOrgIDList = null;
		$theCurrOrgID = $this->getCurrentOrgID();
		if ( !empty($theCurrOrgID) ) {
			$theOrgIDList = $this->getOrgAndAllChildrenIDs($theCurrOrgID);
		}
		$theSql = SqlBuilder::withModel($this)->setSanitizer($aSqlSanitizer);
		//query field list NOTE: since we may have a nested query in
		//  the field list, must add HINT for getQueryTotals()
		$theSql->startWith('SELECT')
			->add(SqlBuilder::FIELD_LIST_HINT_START)
			->addFieldList($aFieldList)
			->add(SqlBuilder::FIELD_LIST_HINT_END)
			;
		$theSql->add('FROM')->add($this->tnAuthAccounts);
		//regardless of what is "passed in" via SqlSanitizer object, force
		//  results to be restricted to "curr org or its sub-orgs"
		$theOrgParamKey = uniqid('param_');
		$theSql->startWhereClause();
		if ( !empty($theOrgIDList) ) {
			$theSubQuery = SqlBuilder::withModel($this)
				->add('SELECT DISTINCT auth_id FROM')->add($this->tnAuthOrgMap)
				->startWhereClause()
				->setParamValueIfEmpty($theOrgParamKey, $theOrgIDList)
				->addParamForColumn($theOrgParamKey, 'org_id')
				->endWhereClause()
				;
			$theSql->addSubQueryForColumn($theSubQuery, 'auth_id');
			$theSql->setParamPrefix(' AND ');
		}
		$theSql->applyFilter($aFilter);
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
	 * Gets the set of account auth_ids.
	 * @param ISqlSanitizer $aSqlSanitizer - the SQL sanitizer obj being used.
	 * @param string|string[] $aGroupIDorList - (OPTIONAL) a group_id or list
	 *   of IDs.
	 * @return \PDOStatement - the iterable result of the SELECT query
	 * @throws DbException if something goes wrong
	 */
	public function getAccountsToDisplay( ISqlSanitizer $aSanitizer=null,
			$aGroupIDorList=null )
	{
		$theFilter = null;
		if ( !empty($aGroupIDorList) )
		{
			//force results to be restricted to those from group_id
			$dbAuthGroups = $this->getAuthGroupsProp();
			$theParamKey = uniqid('param_');
			$theSubQuery = SqlBuilder::withModel($dbAuthGroups)
				->add('SELECT DISTINCT auth_id FROM')
				->add($dbAuthGroups->tnGroupMap)
				->startWhereClause()
				->setParamValueIfEmpty($theParamKey, $aGroupIDorList)
				->addParamForColumn($theParamKey, 'group_id')
				->endWhereClause()
				;
			$theFilter = SqlBuilder::withModel($this)
				->startFilter()
				->addSubQueryForColumn($theSubQuery, 'auth_id')
				;
		}
		return $this->getAuthAccountsToDisplay($aSanitizer, $theFilter);
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
	 * @param string $aAuthID - an auth_id to include as a selection
	 *   criterion, if any
	 * @param integer $aAccountID - an account_id to include as a selection
	 *   criterion, if any
	 * @param string $aToken - a specific token value, or a LIKE search filter
	 *   pattern to limit the format of the tokens that are returned.
	 *   Use SQL "LIKE" syntax for the latter.
	 * @param boolean $bIsTokenFilterForLIKE - (OPTIONAL:false) indicates whether
	 *   the $aToken param is a literal token value, or a LIKE filter pattern.
	 * @return array the set of tokens, if any are found
	 */
	public function getAuthTokens( $aAuthID=null, $aAccountID=null,
			$aToken=null, $bIsTokenFilterForLIKE=false )
	{
		// token is a search pattern
		$theTokenOperator = ( $bIsTokenFilterForLIKE ) ? ' LIKE ' : '=';
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT * FROM' )->add( $this->tnAuthTokens )
			//since all params are optional, ensure we have a valid base
			//  WHERE clause that works even if nothing gets added.
			->add('WHERE 1')->startWhereClause()
			//potentially filter by auth_id column
			->setParamPrefix(' AND ')
			->setParamValueIfEmpty('auth_id', $aAuthID)
			->addParam( 'auth_id' )
			//potentially filter by account_id column
			->setParamPrefix(' AND ')
			->setParamValueIfEmpty('account_id', $aAccountID)
			->addParam( 'account_id' )
			//potentially filter by token column
			->setParamPrefix(' AND ')
			->setParamOperator($theTokenOperator)
			->setParamValueIfEmpty('token', $aToken)
			->addParam( 'token' )
			->setParamOperator('=') //ensure we reset back to '='
			// future columns can be added here
			->endWhereClause()
			->applyOrderByList(array(
					'updated_ts' => SqlBuilder::ORDER_BY_DESCENDING,
			))
			//->logSqlDebug(__METHOD__) //DEBUG
			;
		try
		{ return $theSql->query()->fetchAll(); }
		catch( PDOException $pdox )
		{ $theSql->logSqlFailure(__METHOD__, $pdox); }
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
		$theAuthToken = static::generatePrefixedAuthToken( $aTweak ) ;
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
			. static::generateAuthTokenPadding($aPrefix)
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
		return static::generateAuthTokenPadding($aSuffix)
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
	 * @param AccountInfoCache $aAcctInfo - the auth info.
	 * @return $this Returns $this for chaining.
	 */
	public function updateCookie( AccountInfoCache $aAcctInfo )
	{
		try {
			$theUserToken = $this->createUserInfoTokenForAuthCookie(
					$aAcctInfo->auth_id
			);
			$theAuthToken = $this->createNonceTokenForAuthCookie(
					$aAcctInfo->auth_id, $aAcctInfo->account_id
			);
			$theStaleTime = $this->getCookieStaleTimestamp();
			$this->setMySiteCookie(static::KEY_userinfo, $theUserToken, $theStaleTime);
			$this->setMySiteCookie(static::KEY_token, $theAuthToken, $theStaleTime);
		}
		catch ( \Exception $x ) {
			//do not care if setting cookies fails, log it so admin knows about it, though
			$this->logErrors(__METHOD__, ' ', $x->getErrorMsg());
		}
		return $this;
	}
	
	/**
	 * Return the auth ID token encoded however we wish to be saved in our
	 * KEY_userinfo cookie.
	 * @param string $aAuthID - the auth ID to encode.
	 * @return string Returns the encoded token to use.
	 */
	public function createUserInfoTokenForAuthCookie( $aAuthID )
	{
		return $aAuthID;
	}

	/**
	 * Decoded and return the auth ID encoded in our KEY_userinfo cookie.
	 * @param string[] $aCookieJar - (OPTIONAL) the string array holding the
	 *   cookie to decode. Defaults to using PHP's $_COOKIE global variable.
	 * @return string|NULL Returns the Auth ID, if found and decoded properly.
	 */
	public function getAuthIDFromAuthCookieUserInfoToken( $aCookieJar=null )
	{
		if ( empty($aCookieJar) ) {
			$aCookieJar = &$_COOKIE;
		}
		if ( !empty($aCookieJar[static::KEY_userinfo]) ) {
			return $aCookieJar[static::KEY_userinfo];
		}
	}

	/**
	 * Return the nonce token encoded however we wish to be saved in our
	 * KEY_token cookie. A nonce is an arbitrary value that can be used just
	 * once in a cryptographic communication. It is similar in spirit to a
	 * nonce word, hence the name. It is used to ensure that old communications
	 * cannot be reused in replay attacks.
	 * @param string $aAuthID - the auth ID used to track the nonce.
	 * @param int $aAcctID - the account num used to track the nonce.
	 * @return string Returns the encoded token to use.
	 */
	public function createNonceTokenForAuthCookie( $aAuthID, $aAcctID )
	{
		return $this->generateAuthToken($aAuthID, $aAcctID, static::TOKEN_PREFIX_COOKIE);
	}
	
	/**
	 * Decoded and return the nonce encoded in our KEY_token cookie.
	 * @param string[] $aCookieJar - (OPTIONAL) the string array holding the
	 *   cookie to decode. Defaults to using PHP's $_COOKIE global variable.
	 * @return string|NULL Returns the Auth Nonce, if found and decoded properly.
	 */
	public function getAuthNonceFromCookieJar( $aCookieJar=null )
	{
		if ( empty($aCookieJar) ) {
			$aCookieJar = &$_COOKIE;
		}
		if ( !empty($aCookieJar[static::KEY_token]) ) {
			return $aCookieJar[static::KEY_token];
		}
	}
	
	/**
	 * Create the cookie which will be used the next session during re-auth.
	 * @param AuthOrg $aAuthOrg - the org in use.
	 * @return $this Returns $this for chaining.
	 */
	public function updateCookieForOrg( AuthOrg $aAuthOrg=null )
	{
		if ( $this->isAccountInCookieJar() ) try {
			$theOrgToken = $this->createOrgTokenForAuthCookie($aAuthOrg);
			$theStaleTime = $this->getCookieStaleTimestamp();
			$this->setMySiteCookie(static::KEY_org_token, $theOrgToken, $theStaleTime);
		}
		catch ( \Exception $x ) {
			//do not care if setting cookies fails, log it so admin knows about it, though
			$this->logErrors(__METHOD__, ' ', $x->getErrorMsg());
		}
		return $this;
	}
	
	/**
	 * Return the org ID token encoded however we wish to be saved in our
	 * KEY_org_token cookie.
	 * @param AuthOrg $aAuthOrg - the org in use.
	 * @return string Returns the encoded token to use.
	 */
	public function createOrgTokenForAuthCookie( AuthOrg $aAuthOrg=null )
	{
		return ( !empty($aAuthOrg) ) ? $aAuthOrg->org_id : static::ORG_ID_4_ROOT;
	}

	/**
	 * Decoded and return the org ID encoded in our KEY_org_token cookie.
	 * @param string[] $aCookieJar - (OPTIONAL) the string array holding the
	 *   cookie to decode. Defaults to using PHP's $_COOKIE global variable.
	 * @return string|NULL Returns the org ID, if found and decoded properly.
	 */
	public function getOrgIDFromAuthCookie( $aCookieJar=null )
	{
		if ( empty($aCookieJar) ) {
			$aCookieJar = &$_COOKIE;
		}
		if ( !empty($aCookieJar[static::KEY_org_token]) ) {
			return $aCookieJar[static::KEY_org_token];
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
			//$theSql->logSqlDebug(__METHOD__); //DEBUG
			$theSql->execDML();
		} catch (Exception $e) {
			//do not care if removing stale tokens fails, log it so admin knows about it, though
			$theSql->logSqlFailure(__METHOD__, $e);
		}
	}

	/**
	 * Delete stale cookie tokens.
	 */
	public function removeStaleCookies() {
		$delta = $this->getCookieDurationInDays();
		if (!empty($delta)) {
			$this->removeStaleTokens(static::TOKEN_PREFIX_COOKIE.'%', $delta.' DAY');
		}
	}

	/**
	 * Delete stale mobile auth tokens.
	 */
	public function removeStaleMobileAuthTokens() {
		$this->removeStaleTokens(static::TOKEN_PREFIX_MOBILE.'%', '1 DAY');
	}

	/**
	 * Delete stale auth lockout tokens.
	 */
	public function removeStaleAuthLockoutTokens() {
		if ($this->director->isInstalled()) {
			$this->removeStaleTokens(static::TOKEN_PREFIX_LOCKOUT.'%', '1 HOUR');
		}
	}

	/**
	 * Delete stale registration cap tokens.
	 */
	public function removeStaleRegistrationCapTokens() {
		$this->removeStaleTokens(static::TOKEN_PREFIX_REGCAP.'%', '1 HOUR');
	}

	/**
	 * Delete stale Anti CSRF tokens.
	 */
	public function removeStaleAntiCsrfTokens() {
		$delta = $this->getCookieDurationInDays();
		if (!empty($delta)) {
			$this->removeStaleTokens(static::TOKEN_PREFIX_ANTI_CSRF.'%', $delta.' DAY');
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
			$theSql->setParamPrefix(' AND ')->addParam('account_id');
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
	public function removeAntiCsrfToken($aAuthId, $aAcctId) {
		$this->removeTokensFor($aAuthId, $aAcctId, static::TOKEN_PREFIX_ANTI_CSRF.'%');
	}

	/**
	 * Returns the auth cookie nonce token row, if it existed, and deletes it.
	 * @param string $aAuthId - the auth ID.
	 * @param string $aAuthToken - the nonce token to look for.
	 * @return string[]|NULL Returns the nonce token row data, if found.
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
					static::TOKEN_PREFIX_ANTI_CSRF.'%', true
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
					static::TOKEN_PREFIX_ANTI_CSRF
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
	 * Update the mobile record.
	 * @param object $aCircumstanceData - the circumstance data.
	 * @return array Returns the data updated.
	 */
	public function updateMobileCircumstances( $aCircumstanceData )
	{
		if ( !empty($aCircumstanceData) ) {
			$theSql = SqlBuilder::withModel($this)
				->obtainParamsFrom($aCircumstanceData)
				->startWith('UPDATE')->add($this->tnAuthMobile)
			;
			$this->setAuditFieldsOnUpdate($theSql)
				->addParamIfDefined('device_name')
				->addParamIfDefined('latitude')
				->addParamIfDefined('longitude')
				->startWhereClause()
				->mustAddParam('mobile_id')
				->endWhereClause()
			;
			return $theSql->execDMLandGetParams();
		}
	}
	
	/**
	 * The checkTicket() method executes with every page/endpoint request.
	 * Sometimes we do not wish to execute routines more frequently than
	 * necessary, so this method executes roughly every other minute even
	 * if the user pokes the server many times in between.
	 * @param Scene $aScene - the scene being used.
	 */
	protected function onCheckTicketPollInterval( Scene $aScene )
	{
		//only check for stale cookies every other minute
		$this->removeStaleAuthLockoutTokens() ;
	}

	/**
	 * Check a venue for ticket information (auth account).
	 * @param object $aScene - the Scene object associated with an Actor.
	 * @param IWillCall $aVenue - the mechanism to check.
	 */
	protected function checkVenueForTicket( Scene $aScene, IWillCall $aVenue )
	{
		//$this->logStuff(__FUNCTION__, ' class(v)=', get_class($aVenue)); //DEBUG
		$theAuthAccount = $aVenue->checkForTicket($aScene);
		if ( !empty($theAuthAccount) ) {
			if ( $theAuthAccount->is_active ) {
				//$this->logStuff(__FUNCTION__, ' determined=', $theAuthAccount); //DEBUG
				$this->onDetermineAuthAccount($aScene, $theAuthAccount);
				$aVenue->onTicketAccepted($aScene, $theAuthAccount);
				$this->onSeatTicketHolder($aScene, $theAuthAccount);
				//$this->logStuff(__FUNCTION__, ' accepted v=', $aVenue, ' a=', $theAuthAccount); //DEBUG
			}
			else {
				$aVenue->onTicketRejected($aScene, $theAuthAccount);
				//$this->logStuff(__FUNCTION__, ' v=', $aVenue, ' rejected=', $theAuthAccount); //DEBUG
			}
		}
		return $theAuthAccount;
	}
	
	/**
	 * What auth venues are applicable for us to check? Return the list of
	 * classes that we will use, they must all implement IWillCall.
	 * @param Scene $aScene - the parameters given to us, in case that affects
	 *   what venues we wish to try.
	 * @return string[] Returns the list of IWillCall classes to use for auth.
	 * @since BitsTheater v4.1.0
	 */
	protected function getVenuesToCheckForTickets( $aScene )
	{
		return array(
				TicketViaAuthHeaderBasic::class,
				TicketViaURL::class,
				TicketViaRequest::class,
				TicketViaSession::class,
				TicketViaCookie::class,
		);
	}

	/**
	 * See if we can validate the api/page request with an account.
	 * @param object $aScene - the Scene object associated with an Actor.
	 * @return boolean Returns TRUE if admitted.
	 * @see BaseModel::checkTicket()
	 */
	public function checkTicket( $aScene )
	{
		$theAuthAccount = null;
		if ( $this->getDirector()->canConnectDb() ) try {
			//some routines should only check every other minute
			$theLastPollCheck = $this->getDirector()['check_ticket_poll_ts'];
			if ( empty($theLastPollCheck) || ((time() - $theLastPollCheck) > 100) ) {
				$this->onCheckTicketPollInterval($aScene);
				$this->getDirector()['check_ticket_poll_ts'] = time();
			}
			$theVenueList = $this->getVenuesToCheckForTickets($aScene);
			//$this->logStuff(__METHOD__, ' venues=', $theVenueList); //DEBUG
			foreach( $theVenueList as $theVenueClass ) {
				$theAuthAccount = $this->checkVenueForTicket($aScene,
						$theVenueClass::withAuthDB($this)
				);
				//$this->logStuff(__METHOD__, ' venue=', $theVenueClass, ' determined=', $theAuthAccount); //DEBUG
				if ( !empty($theAuthAccount) ) break;
			}
			//if ( empty($theAuthAccount) )
			//{ $this->logStuff(__METHOD__, ' account not found cs=', Strings::getStackTrace()); } //DEBUG
		}
		catch ( DbException $dbx )
		{
			$theAuthAccount = $this->checkVenueForTicket($aScene,
					TicketViaAuthMigration::withAuthDB($this)
			);
		}
		return ( !empty($theAuthAccount) && $theAuthAccount->is_active );
	}
	
	/**
	 * Check a specific venue for ticket information (auth account).
	 * @param object $aScene - the Scene object associated with an Actor.
	 * @param IWillCall $aVenue - the mechanism to check.
	 * @return boolean Returns TRUE if ticket is active.
	 */
	public function checkTicketVia( Scene $aScene, IWillCall $aVenue )
	{
		$theAuthAccount = $this->checkVenueForTicket($aScene, $aVenue);
		return ( !empty($theAuthAccount) && $theAuthAccount->is_active );
	}
	
	/**
	 * Activates or deactivates an account.
	 * @param boolean $bActive indicates that the account should be activated
	 *  (true) or deactivated (false).
	 * @param string $aAuthID - the auth_id of the account.
	 * @param integer $aAcctID - (OPTIONAL) the account_id of the account.
	 * @since BitsTheater 4.3.1
	 */
	public function setAuthIsActive( $bActive, $aAuthID, $aAcctID=null )
	{
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith( 'UPDATE ' . $this->tnAuthAccounts );
		$this->setAuditFieldsOnUpdate($theSql)
			->mustAddParam( 'is_active', ( $bActive ? 1 : 0 ), PDO::PARAM_INT )
			->startWhereClause()
			->mustAddParam( 'auth_id', $aAuthID )
			->endWhereClause()
			//->logSqlDebug(__METHOD__, '[TRACE]')
			;
		try {
			$theSql->execDML();
			$this->updateMobileAuthForActiveFlag($aAuthID, $bActive);
			//if we successfully toggle their active status,
			//  clear out their status tokens
			$this->removeAntiCsrfToken($aAuthID, $aAcctID);
			$this->removeTokensFor($aAuthID, $aAcctID,
					static::TOKEN_PREFIX_COOKIE . '%'
			);
			$this->removeTokensFor($aAuthID, $aAcctID,
					static::TOKEN_PREFIX_LOCKOUT . '%'
			);
			//yes, this is not specific to a particular account, but needed
			//   _some_ way to clear this out in case its needed.
			//TODO when create an endpoint for this specific need, rip this out.
			$this->removeTokensFor($this->getDirector()->app_id, 0,
					static::TOKEN_PREFIX_REGCAP . '%'
			);
		}
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}

	/**
	 * Activates or deactivates an account. Alias for setAuthIsActive().
	 * @param AccountInfoCache $aAcctInfo - the account info to toggle activation.
	 * @param boolean $bActive indicates that the account should be activated
	 *  (true) or deactivated (false).
	 * @since BitsTheater 3.6
	 * @see AuthOrgs::setAuthIsActive()
	 */
	public function setInvitation( AccountInfoCache $aAcctInfo, $bActive )
	{ return $this->setAuthIsActive($bActive, $aAcctInfo->auth_id, $aAcctInfo->account_id); }

	/**
	 * Log the current user out and wipe the slate clean.
	 * @see \BitsTheater\models\PropCloset\AuthBase::ripTicket()
	 */
	public function ripTicket()
	{
		$theAcctInfo = $this->getDirector()->getMyAccountInfo();
		if ( !empty($theAcctInfo) ) {
			//we basically have to run through all the venues asking them to
			//  remove any cached info they may have set to determine auth
			$theVenueList = $this->getVenuesToCheckForTickets(null);
			foreach( $theVenueList as $theVenueClass ) {
				$theVenue = $theVenueClass::withAuthDB($this);
				$theVenue->ripTicket($theAcctInfo);
			}
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
		{ return static::REGISTRATION_CAP_EXCEEDED; }

		$theResult = static::REGISTRATION_SUCCESS;
		if ( $this->getByName($aAcctName) )
		{ $theResult = static::REGISTRATION_NAME_TAKEN; }
		else if ( $this->getAuthByEmail($aEmailAddy) )
		{ $theResult = static::REGISTRATION_EMAIL_TAKEN; }
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
	 * Check the authorization mechanism to determine if permission is allowed.
	 * @param string $aNamespace - namespace of the permission.
	 * @param string $aPermission - name of the permission.
	 * @param AccountInfoCache $aAcctInfo - (optional) check this account
	 *   instead of current user.
	 * @return boolean Return TRUE if the permission is granted, else FALSE.
	 */
	public function isPermissionAllowed( $aNamespace, $aPermission,
			AccountInfoCache $aAcctInfo=null )
	{
		if ( empty($this->dbPermissions) )
		{ $this->dbPermissions = $this->getProp(AuthGroupsDB::MODEL_NAME); }
		return $this->dbPermissions->isPermissionAllowed($aNamespace,
				$aPermission, $aAcctInfo
		);
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
	 * @param AccountInfoCache $aAcctInfo - the account info to check.
	 * @return boolean Returns FALSE if the account info matches a member
	 *   account that does NOT contain the guest authgroup ID.
	 */
	public function isGuestAccount( AccountInfoCache $aAcctInfo=null )
	{
		if ( !empty($aAcctInfo) && !empty($aAcctInfo->auth_id) && !empty($aAcctInfo->groups)) {
			return ( in_array(AuthGroupsDB::UNREG_GROUP_ID, $aAcctInfo->groups, true) );
		} else {
			return true;
		}
	}

	/**
	 * Store device data so that we can determine if user/pw are required again.
	 * @param AccountInfoCache $aAcctInfo - successfully mapped an account.
	 * @param string $aFingerprints - the fingerprints to register.
	 * @return array Returns the field data saved.
	 */
	public function registerMobileFingerprints( AccountInfoCache $aAcctInfo, $aFingerprints )
	{
		if ( empty($aAcctInfo) ) return; //trivial
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('INSERT INTO')->add($this->tnAuthMobile);
		$this->setAuditFieldsOnInsert($theSql);
		$theSql->mustAddParam('mobile_id', Strings::createUUID());
		$theSql->mustAddParam('auth_id', $aAcctInfo->auth_id);
		$theSql->mustAddParam('account_id', $aAcctInfo->account_id, PDO::PARAM_INT);
		$theUserToken = Strings::urlSafeRandomChars(64-36-1).':'.Strings::createUUID(); //unique 64char gibberish
		$theSql->mustAddParam('account_token', $theUserToken);
		
		//do not store the fingerprints as if db is compromised, this might be
		//  considered "sensitive". just keep a hash instead, like a password.
		$theFingerprintHash = Strings::hasher($aFingerprints);
		$theSql->mustAddParam('fingerprint_hash', $theFingerprintHash);
		
		$theResults = $theSql->execDMLandGetParams();
		//secret should remain secret, don't blab it back to caller.
		unset($theResults['fingerprint_hash']);
		
		return $theResults;
	}

	/**
	 * It has been determined that the requestor has made a valid request, generate
	 * a new auth token and return it as well as place it as a cookie with duration of 1 day.
	 * @param number $aAcctId - the account id.
	 * @param string $aAuthId - the auth id.
	 * @param string $aMobileID - the mobile ID.
	 * @return string Returns the auth token generated.
	 */
	public function generateAuthTokenForMobile($aAcctId, $aAuthId, $aMobileID) {
		//ensure we've cleaned house recently
		$this->removeStaleMobileAuthTokens();
		//see if we've already got a token for this device
		$theTokenPrefix = static::TOKEN_PREFIX_MOBILE;
		if ( !empty($aMobileID) )
			$theTokenPrefix .= $aMobileID;
		$theTokenList = $this->getAuthTokens($aAuthId, $aAcctId, $theTokenPrefix . '%', true);
		//if we have a token, return it, else create a new one
		if (!empty($theTokenList))
			$theAuthToken = $theTokenList[0]['token'];
		else
			$theAuthToken = $this->generateAuthToken($aAuthId, $aAcctId, $theTokenPrefix);
		return $theAuthToken;
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
			$theResetUtils = $aResetUtils ;
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

		$theResetUtils->getTokens() ;
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
					static::TOKEN_PREFIX_REGCAP.'%', true
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
			$this->generateAuthToken(
					$this->getDirector()->app_id,
					0,
					static::TOKEN_PREFIX_REGCAP
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
		$theAuthToken = static::TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT . ':'
			. $aHardwareId . ':'
			. Strings::createUUID()
		;
		$this->insertAuthToken($aAuthId, $aAcctId, $theAuthToken);
		return $theAuthToken;
	}

	/**
	 * Retrieve any TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT tokens mapped
	 * to the account.
	 * @param string $aAuthId - the auth_id of the account.
	 * @param number $aAcctId - the account_id of the account.
	 * @return string Return the tokens mapped to an account.
	 * @since BitsTheater 3.6.2
	 */
	public function getMobileHardwareIdsForAutoLogin($aAuthId, $aAcctId) {
		$theIds = array();
		$theAuthTokenRows = $this->getAuthTokens( $aAuthId, $aAcctId,
				static::TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT . ':%', true
		);
		if (!empty($theAuthTokenRows)) {
			foreach ($theAuthTokenRows as $theRow) {
				list($thePrefix, $theHardwareId, $theUUID) = explode(':', $theRow['token']);
				if ( !empty($thePrefix) && !empty($theHardwareId) && !empty($theUUID) ) {
					$theIds[] = $theHardwareId;
				}
			}
		}
		return $theIds;
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
		try {
			$theSql->beginTransaction();
			$theSql->execDML() ;
			$theResetUtils = AuthPasswordReset::withModel($this) ;
			$theResetUtils->setAccountID($aAcctID)->deleteAllTokens();
			$theSql->commitTransaction();
		}
		catch( PDOException $pdox )
		{
			$theSql->rollbackTransaction();
			throw $theSql->newDbException( __METHOD__, $pdox ) ;
		}
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
	
	/**
	 * Get my mobile data, if known.
	 * @return array Returns the mobile data as an array.
	 */
	public function getMyMobileRow()
	{ return null; }
		
	/**
	 * Insert the task token into the table.
	 * @param string $aTaskID - token mapped to a task by this id.
	 * @param string $aToken - the token.
	 * @return array Returns the data inserted.
	 * @since BitsTheater [NEXT]
	 */
	public function insertTaskToken( $aTaskID, $aToken )
	{ return $this->insertAuthToken($aTaskID, 0, $aToken); }

	/**
	 * Update the task token with "amount left to do" integer.
	 * @param string $aTaskID - token mapped to a task by this id.
	 * @param string $aToken - the task token.
	 * @param integer $aTaskAmount - the int to store.
	 * @return array Returns the data parameters used in update query.
	 * @since BitsTheater [NEXT]
	 */
	public function updateTask( $aTaskID, $aToken, $aTaskAmount )
	{
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('UPDATE')->add($this->tnAuthTokens);
		$this->setAuditFieldsOnUpdate($theSql);
		$theSql->mustAddParam('account_id', $aTaskAmount, PDO::PARAM_INT)
			->startWhereClause()
			->mustAddParam('auth_id', $aTaskID)
			->setParamPrefix(' AND ')
			->mustAddParam('token', $aToken)
			->endWhereClause()
			//->logSqlDebug(__METHOD__)
			;
		try { return $theSql->execDMLandGetParams(); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Retrieve the "amount remaining" integer for a specific task.
	 * @param string $aTaskID - token mapped to a task by this id.
	 * @param string $aToken - the task token.
	 * @return int|false Returns the stored task amount or FALSE if done.
	 */
	public function getTaskAmountRemaining( $aTaskID, $aToken )
	{
		$theSql = SqlBuilder::withModel($this);
		$theSql->startWith('SELECT account_id')
			->add('FROM')->add($this->tnAuthTokens)
			->startWhereClause()
			->mustAddParam('auth_id', $aTaskID)
			->setParamPrefix(' AND ')
			->mustAddParam('token', $aToken)
			->endWhereClause()
			//->logSqlDebug(__METHOD__)
			;
		try { return $theSql->query()->fetch(\PDO::FETCH_COLUMN); }
		catch (PDOException $pdox)
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Delete the task token from the table.
	 * @param string $aTaskID - token mapped to a task by this id.
	 * @param string $aToken - the token.
	 * @since BitsTheater [NEXT]
	 */
	public function removeTaskToken( $aTaskID, $aToken )
	{ $this->removeTokensFor($aTaskID, null, $aToken); }

	/**
	 * Mark the mobile records for an auth account "reset-able".
	 * @param string $aAuthID - the auth ID.
	 * @return string[] Returns the data updated.
	 */
	public function updateMobileAuthForActiveFlag( $aAuthID, $bActive )
	{
		if ( empty($aAuthID) ) return; //trivial
		$theFlag = ( $bActive ) ? static::MOBILE_AUTH_TYPE_RESET : static::MOBILE_AUTH_TYPE_INACTIVE;
		$theSql = SqlBuilder::withModel($this)
			->startWith('UPDATE')->add($this->tnAuthMobile)
		;
		$this->setAuditFieldsOnUpdate($theSql)
			->mustAddParam('auth_type', $theFlag)
			->startWhereClause()
			->mustAddParam('auth_id', $aAuthID)
			->endWhereClause()
		;
		try { return $theSql->execDMLandGetParams(); }
		catch ( \PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
	}
	
	/**
	 * Reset device data due to factory reset of device.
	 * @param string $aMobileID - the record ID to update.
	 * @param string $aFingerprints - the fingerprints to register.
	 * @return string[] Returns the updated row.
	 */
	public function resetMobileFingerprints( $aMobileID, $aFingerprints )
	{
		if ( empty($aMobileID) ) return; //trivial
		//do not store the fingerprints as if db is compromised, this might be
		//  considered "sensitive". just keep a hash instead, like a password.
		$theFingerprintHash = Strings::hasher($aFingerprints);
		$theSql = SqlBuilder::withModel($this)
			->startWith('UPDATE')->add($this->tnAuthMobile)
		;
		$this->setAuditFieldsOnUpdate($theSql)
			->mustAddParam('auth_type', static::MOBILE_AUTH_TYPE_ACTIVE)
			->mustAddParam('fingerprint_hash', $theFingerprintHash)
			->startWhereClause()
			->mustAddParam('mobile_id', $aMobileID)
			->endWhereClause()
		;
		try { $theSql->execDML(); }
		catch ( \PDOException $pdox )
		{ throw $theSql->newDbException(__METHOD__, $pdox); }
		return $this->getAuthMobileRow($aMobileID);
	}

}//end class

}//end namespace
