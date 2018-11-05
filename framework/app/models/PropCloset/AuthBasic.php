<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
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
use BitsTheater\models\Accounts; /* @var $dbAccounts Accounts */
use BitsTheater\models\AuthGroups as AuthGroupsDB; /* @var $dbAuthGroups AuthGroupsDB */
use BitsTheater\Scene;
use BitsTheater\costumes\colspecs\CommonMySql;
use BitsTheater\costumes\IFeatureVersioning;
use BitsTheater\costumes\AccountInfoCache ;
use BitsTheater\costumes\AuthPasswordReset ;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\HttpAuthHeader;
use BitsTheater\costumes\WornForFeatureVersioning;
use BitsTheater\costumes\WornForAuditFields;
use BitsTheater\outtakes\PasswordResetException ;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Strings;
use PDO;
use PDOException;
use Exception;
{//namespace begin

class AuthBasic extends BaseModel implements IFeatureVersioning
{
	use WornForFeatureVersioning, WornForAuditFields;

	/**
	 * Used by meta data mechanism to keep the database up-to-date with the code.
	 * A non-NULL string value here means alter-db-schema needs to be managed.
	 * @var string
	 */
	const FEATURE_ID = 'BitsTheater/AuthBasic';
	const FEATURE_VERSION_SEQ = 8; //always ++ when making db schema changes
	//v08 - added is_active faux-Boolean column
	//v07 - converted AuthMobile table to use standard audit fields
	//v06 - converted AuthTokens table to use standard audit fields
	//v05 - converted Auth table to use standard audit fields
	//v04 - id field added to auth_tokens table
	//v03 - auth_mobile table added
	//v02 - auth_id added to auth table

	const TYPE = 'basic';
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

	public $tnAuth; const TABLE_Auth = 'auth';
	public $tnAuthTokens; const TABLE_AuthTokens = 'auth_tokens';
	public $tnAuthMobile; const TABLE_AuthMobile = 'auth_mobile';

	public $myExistingFeatureVersionNum = self::FEATURE_VERSION_SEQ;

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
	
	
	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		$this->tnAuth = $this->tbl_.self::TABLE_Auth;
		$this->tnAuthTokens = $this->tbl_.self::TABLE_AuthTokens;
		$this->tnAuthMobile = $this->tbl_.self::TABLE_AuthMobile;

		//since login needs to work even while we need to update
		//  the auth schema tables, track our existing version
		//  so code can use the correct field names.
		$this->myExistingFeatureVersionNum = $this->determineExistingFeatureVersion(null);
	}

	/**
	 * Future db schema updates may need to create a temp table of one
	 * of the table definitions in order to update the contained data,
	 * putting schema here and supplying a way to provide a different name
	 * allows this process.
	 * @param string $aTABLEconst - one of the defined table name consts.
	 * @param string $aTableNameToUse - (optional) alternate name to use.
	 */
	protected function getTableDefSql($aTABLEconst, $aTableNameToUse=null) {
		switch($aTABLEconst) {
		case self::TABLE_Auth:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuth;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						"( `auth_id` ".CommonMySQL::TYPE_UUID." NOT NULL PRIMARY KEY".
						", `email` CHAR(255) NOT NULL".		//store as typed, but collate as case-insensitive
						", `account_id` INT NOT NULL".		//link to Accounts
						", `pwhash` CHAR(85) CHARACTER SET ascii NOT NULL COLLATE ascii_bin".	//blowfish hash of pw & its salt
						", `verified_ts` TIMESTAMP NULL DEFAULT NULL". //UTC of when acct was verified
						", `is_active` " . CommonMySQL::TYPE_BOOLEAN_1 .
						", ".CommonMySQL::getAuditFieldsForTableDefSql().
						", UNIQUE KEY IdxEmail (`email`)".
						", INDEX IdxAcctId (`account_id`)".
						") CHARACTER SET utf8 COLLATE utf8_general_ci";
			}//switch dbType
		case self::TABLE_AuthTokens:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthTokens;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						"( `id` INT NOT NULL AUTO_INCREMENT". //strictly for phpMyAdmin ease of use
						", `auth_id` ".CommonMySQL::TYPE_UUID." NOT NULL".
						", `account_id` INT NOT NULL".
						", `token` CHAR(128) NOT NULL".
						", ".CommonMySQL::getAuditFieldsForTableDefSql().
						", PRIMARY KEY (`id`)".
						", INDEX IdxAuthIdToken (`auth_id`, `token`)".
						", INDEX IdxAcctIdToken (`account_id`, `token`)".
						", INDEX IdxAuthToken (`token`, `updated_ts`)".
						") CHARACTER SET ascii COLLATE ascii_bin";
			}//switch dbType
		case self::TABLE_AuthMobile: //added in v3
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthMobile;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						"( `mobile_id` ".CommonMySQL::TYPE_UUID." NOT NULL".
						", `auth_id` ".CommonMySQL::TYPE_UUID." NOT NULL".
						", `account_id` INT NOT NULL".
						", `auth_type` CHAR(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'FULL_ACCESS'".
						", `account_token` CHAR(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'STRANGE_TOKEN'".
						", `device_name` CHAR(64) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL".
						", `latitude` DECIMAL(11,8) DEFAULT NULL".
						", `longitude` DECIMAL(11,8) DEFAULT NULL".
						/* might be considered "sensitive", storing hash instead
						", `device_id` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL".
						", `app_version_name` char(128) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL".
						", `device_memory` BIGINT DEFAULT NULL".
						", `locale` char(8) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL".
						", `app_fingerprint` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL".
						*/
						", `fingerprint_hash` CHAR(85) DEFAULT NULL".
						", ".CommonMySQL::getAuditFieldsForTableDefSql().
						", PRIMARY KEY (`mobile_id`)".
						", KEY `account_id` (`account_id`)".
						", KEY `auth_id` (`auth_id`)".
						") CHARACTER SET ascii COLLATE ascii_bin";
			}//switch dbType

		}//switch TABLE const
	}

	/**
	 * Called during website installation to create whatever the models needs.
	 * Check the database to be sure anything needs to be done and do not assume
	 * a blank database as updates/reinstalls against recovered databases may
	 * occur as well.
	 * @throws DbException
	 */
	public function setupModel() {
        $this->setupTable( self::TABLE_Auth, $this->tnAuth ) ;
        $this->setupTable( self::TABLE_AuthTokens, $this->tnAuthTokens ) ;
        $this->setupTable( self::TABLE_AuthMobile, $this->tnAuthMobile ) ;
	}

	/**
	 * Other models may need to query ours to determine our version number
	 * during Site Update. Without checking SetupDb, determine what version
	 * we may be running as.
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
	public function determineExistingFeatureVersion($aScene) {
		switch ($this->dbType()) {
			//Since this method is now called on every endpoint call, let us
			//  reverse the logic to hopefully cut down the SQL calls to 1
			//  instead of 7.
			case self::DB_TYPE_MYSQL: default:
				if (!$this->exists()) {
					//setupWebsite endpoint will detect the wrong version unless
					//  we check for the non-existance of the main table first
					return self::FEATURE_VERSION_SEQ ;
				} else if( $this->isFieldExists( 'is_active', $this->tnAuth ) )
					return self::FEATURE_VERSION_SEQ ;
				else if ($this->isFieldExists('created_by', $this->tnAuthMobile)) {
					return 7;
				} else if ($this->isFieldExists('created_by', $this->tnAuthTokens)) {
					return 6;
				} else if ($this->isFieldExists('created_by', $this->tnAuth)) {
					return 5;
				} else if ($this->isFieldExists('id', $this->tnAuthTokens)) {
					return 4;
				} else if ($this->exists($this->tnAuthMobile)) {
					return 3;
				} else if ($this->isFieldExists('auth_id', $this->tnAuth)) {
					return 2;
				}
				break;
		}//switch
		return 1;
	}

	/**
	 * Check current feature version and compare it to the
	 * current version, upgrading the db schema as needed.
	 * @param array $aFeatureMetaData - the models current feature metadata.
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
	public function upgradeFeatureVersion($aFeatureMetaData, $aScene) {
		$theSeq = $aFeatureMetaData['version_seq'];
		$this->debugLog('Running '.__METHOD__.' v'.$theSeq.'->v'.self::FEATURE_VERSION_SEQ);

		// This switch block's cases are tests against the current version
		// sequence number. The cases must be implemented sequentially (low to
		// high) and not use break, so that all changes between versions will
		// fall through cumulatively to the end.
		switch (true) {
			case ($theSeq<2):
			{
				//update the cookie table first since its easier and we should empty it
				$tnAuthCookies = $this->tbl_.'auth_cookie'; //v1 table, renamed auth_tokens
				$this->execDML("DROP TABLE IF EXISTS {$tnAuthCookies}");
				$this->execDML("DROP TABLE IF EXISTS {$this->tnAuthTokens}");
				$this->execDML($this->getTableDefSql(self::TABLE_AuthTokens));

				//now update the Auth table... it is a bit trickier.
				//change the default to _changed field (defaulted to 0 rather than current ts)
				$theColDef = "_changed TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP";
				$this->execDML("ALTER TABLE {$this->tnAuth} MODIFY {$theColDef}");
				//remove the primary key
				$this->execDML('ALTER TABLE '.$this->tnAuth.' DROP PRIMARY KEY');
				//add auth_id
				$theColDef = "auth_id CHAR(36) CHARACTER SET ascii NOT NULL DEFAULT 'I_NEED_A_UUID' COLLATE ascii_bin";
				$this->execDML("ALTER TABLE {$this->tnAuth} ADD {$theColDef} FIRST");
				//update all existing records to change default to a UUID()
				$this->execDML("UPDATE {$this->tnAuth} SET auth_id=UUID() WHERE auth_id='I_NEED_A_UUID'");
				//remove default for auth_id
				$theColDef = "auth_id CHAR(36) CHARACTER SET ascii NOT NULL COLLATE ascii_bin";
				$this->execDML("ALTER TABLE {$this->tnAuth} MODIFY {$theColDef}");
				//re-apply primary key
				$this->execDML('ALTER TABLE '.$this->tnAuth.' ADD PRIMARY KEY (auth_id)');
				//put unique key constraint back on email
				$this->execDML('ALTER TABLE '.$this->tnAuth.' ADD UNIQUE KEY (email)');
			}
			case ($theSeq<3):
			{
				if (!$this->isExists($this->tnAuthMobile)) {
					//add new table
					$theSql = $this->getTableDefSql(self::TABLE_AuthMobile);
					$this->execDML($theSql);
					$this->debugLog('v3: '.$this->getRes('install/msg_create_table_x_success/'.$this->tnAuthMobile));
				} else {
					$this->debugLog('v3: ' . $this->tnAuthMobile . ' already exists.');
				}
			}
			case ( $theSeq < 4 ):
			{
				//previous versions may have added the field already, so double check before adding it.
				if (!$this->isFieldExists('id', $this->tnAuthTokens)) {
					$theSql = SqlBuilder::withModel($this);
					$theSql->startWith('ALTER TABLE '.$this->tnAuthTokens);
					$theSql->add('  ADD COLUMN')->add("`id` int NOT NULL AUTO_INCREMENT")->add("FIRST");
					$theSql->add(', ADD')->add("PRIMARY KEY (`id`)");
					$theSql->execDML();
					$this->debugLog('v4: added id to '.$this->tnAuthTokens);
				} else {
					$this->debugLog('v4: id already exists in '.$this->tnAuthTokens);
				}
			}
			case ($theSeq < 5):
			{
				$theSql = SqlBuilder::withModel($this);
				if (!$this->isFieldExists('created_by', $this->tnAuth)) try {
					$theSql->startWith('ALTER TABLE '.$this->tnAuth);
					$theColDef = '`verified_ts` TIMESTAMP NULL DEFAULT NULL';
					$theSql->add('  CHANGE COLUMN verified')->add($theColDef);
					$theSql->add(', DROP COLUMN is_reset');
					$theColDef = CommonMySql::CREATED_TS_SPEC;
					$theSql->add(', CHANGE COLUMN _created')->add($theColDef);
					$theColDef = CommonMySql::UPDATED_TS_SPEC;
					$theSql->add(', CHANGE COLUMN _changed')->add($theColDef);
					$theColDef = CommonMySql::CREATED_BY_SPEC;
					$theSql->add(', ADD COLUMN')->add($theColDef)->add('AFTER verified_ts');
					$theColDef = CommonMySql::UPDATED_BY_SPEC;
					$theSql->add(', ADD COLUMN')->add($theColDef)->add('AFTER created_by');
					$theSql->execDML();
					$this->debugLog('v5: added audit fields to '.$this->tnAuth);
				} catch (Exception $e) {
					throw $theSql->newDbException(__METHOD__, $e);
				} else {
					$this->debugLog('v5: ' . $this->tnAuth . ' already updated.');
				}
			}
			case ($theSeq < 6):
			{
				$theSql = SqlBuilder::withModel($this);
				if (!$this->isFieldExists('created_by', $this->tnAuthTokens)) try {
					$theSql->startWith('ALTER TABLE '.$this->tnAuthTokens);
					$theSql->add('  DROP INDEX `IdxAuthToken`');
					$theColDef = CommonMySql::CREATED_BY_SPEC;
					$theSql->add(', ADD COLUMN')->add($theColDef)->add('AFTER token');
					$theColDef = CommonMySql::UPDATED_BY_SPEC;
					$theSql->add(', ADD COLUMN')->add($theColDef)->add('AFTER created_by');
					$theColDef = CommonMySql::CREATED_TS_SPEC;
					$theSql->add(', ADD COLUMN')->add($theColDef)->add('AFTER updated_by');
					$theColDef = CommonMySql::UPDATED_TS_SPEC;
					$theSql->add(', CHANGE COLUMN _changed')->add($theColDef);
					$theSql->add(', ADD INDEX `IdxAuthToken` (`token`, `updated_ts`)');
					$theSql->execDML();
					$this->debugLog('v6: added audit fields to '.$this->tnAuthTokens);
				} catch (Exception $e) {
					throw $theSql->newDbException(__METHOD__, $e);
				} else {
					$this->debugLog('v6: ' . $this->tnAuthTokens . ' already updated.');
				}
			}
			case ($theSeq < 7):
			{
				$theSql = SqlBuilder::withModel($this);
				if (!$this->isFieldExists('created_by', $this->tnAuthMobile)) try {
					$theSql->startWith('ALTER TABLE '.$this->tnAuthMobile);
					$theColDef = CommonMySql::CREATED_BY_SPEC;
					$theSql->add('  ADD COLUMN')->add($theColDef)->add('AFTER fingerprint_hash');
					$theColDef = CommonMySql::UPDATED_BY_SPEC;
					$theSql->add(', ADD COLUMN')->add($theColDef)->add('AFTER created_by');
					$theColDef = CommonMySql::CREATED_TS_SPEC;
					$theSql->add(', MODIFY')->add($theColDef);
					$theColDef = CommonMySql::UPDATED_TS_SPEC;
					$theSql->add(', MODIFY')->add($theColDef);
					$theSql->execDML();
					$this->debugLog('v7: added audit fields to '.$this->tnAuthMobile);
				} catch (Exception $e) {
					throw $theSql->newDbException(__METHOD__, $e);
				} else {
					$this->debugLog('v7: ' . $this->tnAuthMobile . ' already updated.');
				}
			}
			case( $theSeq < 8 ):
			{
				if( ! $this->isFieldExists( 'is_active', $this->tnAuth ) )
				{
					$theSql = SqlBuilder::withModel($this)
						->startWith( 'ALTER TABLE ' . $this->tnAuth )
						->add( ' ADD COLUMN `is_active` ' )
						->add( CommonMySql::TYPE_BOOLEAN_1 )
						->add( ' AFTER verified_ts' )
						;
					try
					{
						$theSql->execDML() ;
						$this->debugLog( 'v8: Added `is_active` to '
								. $this->tnAuth ) ;
					}
					catch( Exception $x )
					{ throw $theSql->newDbException( __METHOD__, $x ) ; }
				}
				else
				{
					$this->debugLog( 'v8: Table ' . $this->tnAuth
							. ' already had a `is_active` column.' ) ;
				}
			}
		}//switch
	}

	protected function exists($aTableName=null) {
		return parent::exists( empty($aTableName) ? $this->tnAuth : $aTableName );
	}

	public function isEmpty($aTableName=null) {
		return parent::isEmpty( empty($aTableName) ? $this->tnAuth : $aTableName );
	}

	/**
	 * Create an object representing auth account information.
	 * @param array|object $aInitialData - (optional) include this data in the object.
	 * @return AccountInfoCache Returns the object for the Auth model in use.
	 */
	public function createAccountInfoObj( $aInitialData=null )
	{ return AccountInfoCache::fromThing($this, $aInitialData); }
	
	public function getAuthByEmail($aEmail) {
		$theSql = "SELECT * FROM {$this->tnAuth} WHERE email = :email";
		return $this->getTheRow($theSql,array('email'=>$aEmail));
	}

	public function getAuthByAccountId($aAccountId) {
		$theSql = "SELECT * FROM {$this->tnAuth} WHERE account_id=:id";
		return $this->getTheRow($theSql, array('id' => $aAccountId), array('id' => PDO::PARAM_INT));
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
			$dbAccounts = $this->getProp('Accounts');
			//NOTE: since we have a nested query in field list, must add HINT for getQueryTotals()
			$theSql->startWith('SELECT')->add(SqlBuilder::FIELD_LIST_HINT_START);
			$theSql->add('auth.*, acct.account_name');
			//find mapped hardware ids, if any (AuthAccount costume will convert this field into appropriate string)
			$theSql->add(', (')
					->add("SELECT GROUP_CONCAT(`token` SEPARATOR ', ') FROM")->add($this->tnAuthTokens)
					->add('WHERE auth.account_id=account_id')->setParamPrefix(' AND ')
					->setParamOperator(' LIKE ')->mustAddParam('token')->setParamOperator('=')
					->add(') AS hardware_ids')
					;
			//done with fields
			$theSql->add(SqlBuilder::FIELD_LIST_HINT_END);
			//now for rest of query
			$theSql->add('FROM')->add($this->tnAuth)->add('AS auth');
			$theSql->add('JOIN')->add($dbAccounts->tnAccounts)->add('AS acct ON auth.account_id=acct.account_id');
			if (!is_null($aGroupId)) {
				$dbAuthGroups = $this->getProp('AuthGroups');
				$theSql->add('JOIN')->add($dbAuthGroups->tnGroupMap)->add('AS gm ON auth.account_id=gm.account_id');
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
	 * @param Scene $aScene
	 * @param string $aFilter
	 */
	public function getAccountsByFilter( $aScene, $orderByList = null, $aFilter = null )
	{
		$theResultSet = null ;
		$theSql = SqlBuilder::withModel($this)->setSanitizer($aScene)->obtainParamsFrom(array(
				'token' => self::TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT . ':%',
		));
		//query field list
		$dbAccounts = $this->getProp('Accounts');
		//NOTE: since we have a nested query in field list, must add HINT for getQueryTotals()
		$theSql->startWith('SELECT')->add(SqlBuilder::FIELD_LIST_HINT_START);
		$theSql->add('auth.*, acct.account_name');
		//find mapped hardware ids, if any (AuthAccount costume will convert this field into appropriate string)
		$theSql->add(', (')
			->add("SELECT GROUP_CONCAT(`token` SEPARATOR ', ') FROM")->add($this->tnAuthTokens)
			->add('WHERE auth.account_id=account_id')->setParamPrefix(' AND ')
			->setParamOperator(' LIKE ')->mustAddParam('token')->setParamOperator('=')
			->add(') AS hardware_ids');
		//done with fields
		$theSql->add(SqlBuilder::FIELD_LIST_HINT_END);
		//now for rest of query
		$theSql->add('FROM')->add($this->tnAuth)->add('AS auth');
		$theSql->add('JOIN')->add($dbAccounts->tnAccounts)->add('AS acct ON auth.account_id=acct.account_id');
		try
		{
			$theSql->applyOrderByList( $orderByList );
			$ps = $theSql->query() ;
			if( $ps ) $theResultSet = $ps->fetchAll() ;
		}
		catch( PDOException $pdoe )
		{ $this->relayPDOException( __METHOD__, $pdoe, $theSql ) ; }

		return $theResultSet ;
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
	 * @param boolean $bIsTokenFilterForLIKE - (OPTIONAL) indicates whether
	 *   the $aToken param is a literal token value, or a LIKE filter pattern.
	 * @return array the set of tokens, if any are found
	 */
	public function getAuthTokens( $aAuthID=null, $aAccountID=null,
			$aToken=null, $bIsTokenFilterForLIKE=false )
	{
		$theOrderByField = ($this->myExistingFeatureVersionNum>5) ? 'updated_ts' : '_changed';
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
					$theOrderByField => SqlBuilder::ORDER_BY_DESCENDING,
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
		if ($this->myExistingFeatureVersionNum>5)
			$this->setAuditFieldsOnInsert($theSql);
		else
			$theSql->add('SET')->mustAddParam('_changed', $this->utc_now())->setParamPrefix(', ');
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
			$theDuration = (!empty($aDuration)) ? $aDuration
					: $this->getConfigSetting('auth/cookie_freshness_duration');
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
			$theDeltaField = ($this->myExistingFeatureVersionNum>5) ? 'updated_ts' : '_changed';
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
	protected function removeTokensFor($aAuthId, $aAcctId, $aTokenPattern)
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
	 * @param Accounts $dbAcct - the accounts model.
	 * @param integer $aAccountId - the account id.
	 * @return AccountInfoCache|NULL Returns the data if found and active, else NULL.
	 */
	public function getAccountInfoCache(Accounts $dbAccounts, $aAccountId) {
		$theResult = AccountInfoCache::fromArray($dbAccounts->getAccount($aAccountId));
		if( ! empty($theResult) && ! empty( $theResult->account_name ) )
		{
			$theAuthRow = $this->getAuthByAccountId($aAccountId) ;
			$theResult->auth_id = $theAuthRow['auth_id'] ;
			$theResult->email = $theAuthRow['email'] ;
			$theResult->groups = $this->belongsToGroups($aAccountId) ;
			$theResult->is_active =
				( array_key_exists( 'is_active', $theAuthRow ) ?
					((boolean)($theAuthRow['is_active'])) : true ) ;
			return ( $theResult->is_active ) ? $theResult : null;
		}
		else
			return null ;
	}

	/**
	 * Authenticates using only the information that a password reset object
	 * would know upon reentry.
	 * @param Accounts $dbAccounts an Accounts prop
	 * @param AuthPasswordReset $aResetUtils an AuthPasswordReset costume
	 */
	public function setPasswordResetCreds( Accounts $dbAccounts,
			AuthPasswordReset $aResetUtils )
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
		if( empty($theAccountInfo) ) return false ;
		$this->director->account_info = $theAccountInfo ;
		$this->director[self::KEY_userinfo] = $theAccountID ;
		return true ;
	}

	/**
	 * Retrieve the current CSRF token.
	 * @param string $aCsrfTokenName - the name of the token, in case it's useful.
	 * @return string Returns the token.
	 */
	protected function getMyCsrfToken($aCsrfTokenName) {
		if (!empty($this->director->account_info))
		{
			$theTokens = $this->getAuthTokens($this->director->account_info->auth_id,
					$this->director->account_info->account_id,
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
	protected function setMyCsrfToken($aCsrfTokenName, $aCsrfToken=null) {
		$delta = $this->getCookieDurationInDays();
		if (!empty($this->director->account_info) && !empty($delta))
			return $this->generateAuthToken($this->director->account_info->auth_id,
					$this->director->account_info->account_id,
					self::TOKEN_PREFIX_ANTI_CSRF
			);
		else
			return parent::setMyCsrfToken($aCsrfTokenName, $aCsrfToken);
	}

	/**
	 * Removes the current CSRF token in use.
	 * @param string $aCsrfTokenName - the name of the token, in case it's useful.
	 */
	protected function clearMyCsrfToken($aCsrfTokenName) {
		if (!empty($this->director->account_info))
			$this->removeAntiCsrfToken($this->director->account_info->auth_id,
					$this->director->account_info->account_id
			);
		else
			parent::clearMyCsrfToken($aCsrfTokenName);
	}

	/**
	 * Check PHP session data for account information.
	 * @param Accounts $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info;
	 * if account name is non-empty, skip session data check.
	 * @return boolean Returns TRUE if account was found and successfully loaded.
	 */
	protected function checkSessionForTicket(Accounts $dbAccounts, $aScene)
	{
		$theUserInput = $aScene->{self::KEY_userinfo};
		//see if session remembers user
		if( isset( $this->director[self::KEY_userinfo] ) && empty($theUserInput) )
		{
			$theAccountId = $this->director[self::KEY_userinfo] ;
			$this->director->account_info =
				$this->getAccountInfoCache( $dbAccounts, $theAccountId ) ;
			if( empty($this->director->account_info) )
			{ // Either account info is in weird state, or account is inactive.
				$this->ripTicket() ;
			}
		}
		return( ! empty( $this->director->account_info ) ) ;
	}

	/**
	 * Check submitted webform data for account information.
	 * @param Accounts $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info.
	 * @return boolean Returns TRUE if account was found and successfully loaded.
	 */
	protected function checkWebFormForTicket(Accounts $dbAccounts, $aScene) {
		if (!empty($aScene->{self::KEY_userinfo}) && !empty($aScene->{self::KEY_pwinput})) {
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
						$this->director->account_info = $this->getAccountInfoCache($dbAccounts, $theAuthRow['account_id']);
						if (!empty($this->director->account_info)) {
							//data retrieval succeeded, save the account id in session cache
							$this->director[self::KEY_userinfo] = $theAuthRow['account_id'];
							//if user asked to remember, save a cookie
							if (!empty($aScene->{self::KEY_cookie})) {
								$this->updateCookie($theAuthRow['auth_id'], $theAuthRow['account_id']);
							}
						}
					} else {
						//auth fail!
						$this->director->account_info = null;
						//if login failed, move closer to lockout
						$this->updateFailureLockout($dbAccounts, $aScene);
					}
					unset($theAuthRow);
					unset($pwhash);
				} else {
					//user/email not found, consider it a failure
					$this->director->account_info = null;
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

		return (!empty($this->director->account_info));
	}

	/**
	 * Cookies might remember our user if the session forgot and they have
	 * not tried to login.
	 * @param Accounts $dbAccounts - the accounts model.
	 * @param object $aCookieMonster - an object representing cookie keys and data.
	 * @return boolean Returns TRUE if cookies successfully logged the user in.
	 */
	protected function checkCookiesForTicket(Accounts $dbAccounts, $aCookieMonster) {
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
				$this->director->account_info = $this->getAccountInfoCache($dbAccounts, $theAccountId);
				if (!empty($this->director->account_info)) {
					//data retrieval succeeded, save the account id in session cache
					$this->director[self::KEY_userinfo] = $theAccountId;
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
			if ($this->myExistingFeatureVersionNum>6)
				$this->setAuditFieldsOnUpdate($theSql);
			else
				$theSql->add('SET')->mustAddParam('updated_ts', $this->utc_now())->setParamPrefix(', ');
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
	 * @param Accounts $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info.
	 * @return boolean Returns TRUE if account was found and successfully loaded.
	 */
	protected function checkHeadersForTicket(Accounts $dbAccounts, $aScene) {
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
									$this->director->account_info = $theUserAccount;
									//data retrieval succeeded, save the account id in session cache
									$this->director[self::KEY_userinfo] = $theUserAccount->account_id;
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
	 * @param Accounts $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info.
	 * @return array Returns the information needed for lockout tokens.
	 */
	protected function obtainLockoutTokenInfo(Accounts $dbAccounts, Scene $aScene) {
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
	 * @param Accounts $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info.
	 */
	protected function onAccountLocked(Accounts $dbAccounts, Scene $aScene) {
		$aScene->addUserMsg($this->getRes('account/err_pw_failed_account_locked'), $aScene::USER_MSG_ERROR);
	}

	/**
	 * Check to see if manual auth failed so often its locked out.
	 * @param Accounts $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info.
	 * @return boolean Returns TRUE if too many failures locked out the account.
	 */
	protected function checkLockoutForTicket(Accounts $dbAccounts, Scene $aScene) {
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
	 * @param Accounts $dbAccounts - the accounts model.
	 * @param object $aScene - var container object for user/pw info.
	 */
	protected function updateFailureLockout(Accounts $dbAccounts, Scene $aScene) {
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
	 * See if we can validate the api/page request with an account.
	 * @param object $aScene - the Scene object associated with an Actor.
	 * @return boolean Returns TRUE if admitted.
	 * @see BaseModel::checkTicket()
	 */
	public function checkTicket($aScene)
	{
		$bAuthorized = false;
		if( $this->director->canConnectDb() )
		try {
			$this->removeStaleAuthLockoutTokens() ;

			$dbAccounts = $this->getProp('Accounts') ;
			$bAuthorizedViaHeaders = false;
			$bAuthorizedViaSession = false;
			$bAuthorizedViaWebForm = false;
			$bAuthorizedViaCookies = false;
			$bCsrfTokenWasBaked = false;

			$bAuthorizedViaHeaders = $this->checkHeadersForTicket($dbAccounts, $aScene);
//			if ($bAuthorizedViaHeaders) $this->debugLog(__METHOD__.' header auth'); //DEBUG
			$bAuthorized = $bAuthorized || $bAuthorizedViaHeaders;
			if (!$bAuthorized && !$aScene->bCheckOnlyHeadersForAuth)
			{
				$bAuthorizedViaSession = $this->checkSessionForTicket($dbAccounts, $aScene);
//				if ($bAuthorizedViaSession) $this->debugLog(__METHOD__.' session auth'); //DEBUG
				$bAuthorized = $bAuthorized || $bAuthorizedViaSession;
			}
			if (!$bAuthorized && !$aScene->bCheckOnlyHeadersForAuth)
			{
				$bAuthorizedViaWebForm = $this->checkWebFormForTicket($dbAccounts, $aScene);
//				if ($bAuthorizedViaWebForm) $this->debugLog(__METHOD__.' webform auth'); //DEBUG
				$bAuthorized = $bAuthorized || $bAuthorizedViaWebForm;
			}
			if (!$bAuthorized && !$aScene->bCheckOnlyHeadersForAuth)
			{
				$bAuthorizedViaCookies = $this->checkCookiesForTicket($dbAccounts, $_COOKIE);
//				if ($bAuthorizedViaCookies) $this->debugLog(__METHOD__.' cookie auth'); //DEBUG
				$bAuthorized = $bAuthorized || $bAuthorizedViaCookies;
			}

			if ($bAuthorized)
			{
				if ($bAuthorizedViaSession || $bAuthorizedViaWebForm || $bAuthorizedViaCookies)
					$bCsrfTokenWasBaked = $this->setCsrfTokenCookie();
			}
//			else $this->debugLog(__METHOD__.' not authorized'); //DEBUG
			$this->returnProp($dbAccounts);
		}
		catch ( DbException $dbx )
		{ $bAuthorized = false; }
		return $bAuthorized;
	}

	/**
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
			//remove all cookie records for current login (logout means from everywhere but mobile)
			$theSql->obtainParamsFrom(array(
					'account_id' => $this->director->account_info->account_id,
					'token' => self::TOKEN_PREFIX_COOKIE . '%',
			));
			$theSql->startWith('DELETE FROM')->add($this->tnAuthTokens);
			$theSql->startWhereClause()->mustAddParam('account_id', 0, PDO::PARAM_INT);
			$theSql->setParamPrefix(' AND ')->setParamOperator(' LIKE ')->mustAddParam('token');
			$theSql->endWhereClause();
			$theSql->execDML();
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
	 * @return AuthBasic $this
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
	 * Given the parameters, can a user register with them?
	 * @see \BitsTheater\models\PropCloset\AuthBase::canRegister()
	 */
	public function canRegister($aAcctName, $aEmailAddy) {
		$this->removeStaleRegistrationCapTokens();
		if ($this->checkRegistrationCap()) {
			return self::REGISTRATION_CAP_EXCEEDED;
		}

		$dbAccounts = $this->getProp('Accounts');
		$theResult = self::REGISTRATION_SUCCESS;
		if ($dbAccounts->getByName($aAcctName)) {
			$theResult = self::REGISTRATION_NAME_TAKEN;
		} else if ($this->getAuthByEmail($aEmailAddy)) {
			$theResult = self::REGISTRATION_EMAIL_TAKEN;
		}
		$this->returnProp($dbAccounts);
		return $theResult;
	}

	/**
	 * Register an account with our website.
	 * @param array $aUserData - email, account_id, pwinput, verified_timestamp.
	 * @param number|array $aAuthGroups - (optional) auth group membership(s).
	 * @return array Returns account info with ID if succeeds, NULL otherwise.
	 */
	public function registerAccount($aUserData, $aAuthGroups=0) {
		$theSql = SqlBuilder::withModel($this);
		if ($this->isConnected())
		{
			$this->db->beginTransaction() ;
			try {
				$nowAsUTC = $this->utc_now();
				$theVerifiedTS = ($aUserData['verified_timestamp']==='now')
						? $nowAsUTC
						: $aUserData['verified_timestamp']
				;
				//avoids an undefined key in error logs if not supplied
				$theCreatedBy = (!empty($aUserData[self::KEY_userinfo])) ? $aUserData[self::KEY_userinfo] : null;
				$theSql->obtainParamsFrom(array(
						'created_by' => $theCreatedBy,
						'email' => $aUserData['email'],
						'account_id' => intval($aUserData['account_id']),
						'pwhash' => Strings::hasher($aUserData[self::KEY_pwinput]),
						'verified_ts' => $theVerifiedTS,
						'is_active' => (isset($aUserData['account_is_active']))
								? empty($aUserData['account_is_active']) ? 0 : 1
								: null,
				));
				$theSql->startWith('INSERT INTO')->add($this->tnAuth);
				// created_by, created_ts, updated_by, updated_ts
				$this->setAuditFieldsOnInsert($theSql) ;
				$theSql->mustAddParam('auth_id', Strings::createUUID());
				$theSql->mustAddParam('email');
				$theSql->mustAddParam('account_id', 0, PDO::PARAM_INT);
				$theSql->mustAddParam('pwhash');
				$theSql->addParam('verified_ts');
				$theSql->mustAddParam('is_active', 1, \PDO::PARAM_INT);
				$theSql->execDML();
				//group mapping
				$dbAuthGroups = $this->getProp('AuthGroups');
				if (is_array($aAuthGroups)) {
					$dbAuthGroups->addGroupsToAccount(
							$theSql->getParam('account_id'), $aAuthGroups
					);
				} else {
					$dbAuthGroups->addAcctMap(
							$aAuthGroups, $theSql->getParam('account_id')
					);
				}
				$this->returnProp($dbAuthGroups);
				//inc reg cap
				$this->updateRegistrationCap();
				//commit it all
				$this->db->commit();
				//success!
				return $theSql->myParams;
			}
			catch (PDOException $pdoe) {
				$this->db->rollBack();
				throw $theSql->newDbException(__METHOD__, $pdoe);
			}
		}
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
		$dbAuthGroups = $this->getProp('AuthGroups');
		$theResult = $dbAuthGroups->getAcctGroups($aAcctId);
		$this->returnProp($dbAuthGroups);
		return $theResult;
	}

	/**
	 * Check your authority mechanism to determine if a permission is allowed.
	 * @param string $aNamespace - namespace of the permission.
	 * @param string $aPermission - name of the permission.
	 * @param AccountInfoCache $aAcctInfo - (optional) check this account
	 *   instead of current user.
	 * @return boolean Return TRUE if the permission is granted, FALSE otherwise.
	 */
	public function isPermissionAllowed( $aNamespace, $aPermission,
			AccountInfoCache $aAcctInfo=null )
	{
		if (empty($this->dbPermissions))
		{ $this->dbPermissions = $this->getProp('Permissions'); } //cleanup will close this model
		return $this->dbPermissions->isPermissionAllowed($aNamespace, $aPermission, $aAcctInfo);
	}

	/**
	 * Return the defined permission groups.
	 */
	public function getGroupList() {
		$dbAuthGroups = $this->getProp('AuthGroups');
		$theSql = "SELECT * FROM {$dbAuthGroups->tnGroups} ";
		$r = $dbAuthGroups->query($theSql);
		$theResult = $r->fetchAll();
		$this->returnProp($dbAuthGroups);
		return $theResult;
	}

	/**
	 * Checks the given account information for membership.
	 * @param AccountInfoCache $aAcctInfo - the account info to check.
	 * @return boolean Returns FALSE if the account info matches a member account.
	 */
	public function isGuestAccount( AccountInfoCache $aAcctInfo=null ) {
		if ( !empty($aAcctInfo) && !empty($aAcctInfo->account_id) && !empty($aAcctInfo->groups) ) {
			return ( in_array(0, $aAcctInfo->groups, true) );
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
	 * @param Accounts $dbAccounts - the accounts model.
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
		$theSql->startWith('UPDATE')->add($this->tnAuth);
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
		$theSql->startWith('UPDATE')->add($this->tnAuth);
		$this->setAuditFieldsOnUpdate($theSql);
		$theSql->mustAddParam('pwhash');
		$theSql->startWhereClause()->mustAddParam('account_id')->endWhereClause();
		try { $theSql->execDML() ; }
		catch( PDOException $pdox )
		{ throw $theSql->newDbException( __METHOD__, $pdox ) ; }
	}
	
}//end class

}//end namespace
