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
use BitsTheater\models\SetupDb as MetaModel;
use BitsTheater\models\Accounts; /* @var $dbAccounts Accounts */
use BitsTheater\Scene;
use BitsTheater\costumes\IFeatureVersioning;
use BitsTheater\costumes\AuthPasswordReset ;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\costumes\HttpAuthHeader;
use BitsTheater\outtakes\PasswordResetException ;
use com\blackmoonit\database\FinallyCursor;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Strings;
use com\blackmoonit\Arrays;
use PDO;
use PDOException;
use Exception;
use BitsTheater\costumes\WornForFeatureVersioning;
{//namespace begin

class AuthBasic extends BaseModel implements IFeatureVersioning
{
	use WornForFeatureVersioning;

	/**
	 * Used by meta data mechanism to keep the database up-to-date with the code.
	 * A non-NULL string value here means alter-db-schema needs to be managed.
	 * @var string
	 */
	const FEATURE_ID = 'BitsTheater/AuthBasic';
	const FEATURE_VERSION_SEQ = 4; //always ++ when making db schema changes

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

	/**
	 * @var Config
	 */
	protected $dbConfig = null;

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

	public function setupAfterDbConnected() {
		parent::setupAfterDbConnected();
		if ($this->director->canConnectDb()) {
			$this->dbConfig = $this->getProp('Config');
		}
		$this->tnAuth = $this->tbl_.self::TABLE_Auth;
		$this->tnAuthTokens = $this->tbl_.self::TABLE_AuthTokens;
		$this->tnAuthMobile = $this->tbl_.self::TABLE_AuthMobile;
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
						"( auth_id CHAR(36) CHARACTER SET ascii NOT NULL COLLATE ascii_bin PRIMARY KEY".
						", email NCHAR(255) NOT NULL".		//store as typed, but collate as case-insensitive
						", account_id INT NOT NULL".		//link to Accounts
						", pwhash CHAR(85) CHARACTER SET ascii NOT NULL COLLATE ascii_bin".	//blowfish hash of pw & its salt
						", verified DATETIME".				//UTC when acct was verified
						", is_reset INT".					//force pw reset in effect since this unix timestamp (if set)
						", _created TIMESTAMP NOT NULL DEFAULT '0000-00-00 00:00:00'".
						", _changed TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP".
						", UNIQUE KEY IdxEmail (email)".
						", INDEX IdxAcctId (account_id)".
						") CHARACTER SET utf8 COLLATE utf8_general_ci";
			}//switch dbType
		case self::TABLE_AuthTokens:
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthTokens;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						"( `id` int NOT NULL AUTO_INCREMENT". //strictly for phpMyAdmin ease of use
						", auth_id CHAR(36) NOT NULL".
						", account_id INT NOT NULL".
						", token CHAR(128) NOT NULL".
						", _changed TIMESTAMP ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP".
						", PRIMARY KEY (`id`)".
						", INDEX IdxAuthIdToken (auth_id, token)".
						", INDEX IdxAcctIdToken (account_id, token)".
						", INDEX IdxAuthToken (token, _changed)".
						") CHARACTER SET ascii COLLATE ascii_bin";
			}//switch dbType
		case self::TABLE_AuthMobile: //added in v3
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthMobile;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						"( `mobile_id` char(36) NOT NULL".
						", `auth_id` CHAR(36) NOT NULL".
						", `account_id` int NOT NULL".
						", `auth_type` char(16) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'FULL_ACCESS'".
						", `account_token` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL DEFAULT 'STRANGE_TOKEN'".
						", `device_name` char(64) CHARACTER SET utf8 COLLATE utf8_general_ci DEFAULT NULL".
						", `latitude` decimal(11,8) DEFAULT NULL".
						", `longitude` decimal(11,8) DEFAULT NULL".
						/* might be considered "sensitive", storing hash instead
						", `device_id` char(64) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL".
						", `app_version_name` char(128) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL".
						", `device_memory` BIGINT DEFAULT NULL".
						", `locale` char(8) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL".
						", `app_fingerprint` char(36) CHARACTER SET ascii COLLATE ascii_bin DEFAULT NULL".
						*/
						", `fingerprint_hash` char(85) DEFAULT NULL".
						", `created_ts` timestamp NOT NULL DEFAULT '0000-00-00 00:00:00'".
						", `updated_ts` timestamp ON UPDATE CURRENT_TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP".
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
			case self::DB_TYPE_MYSQL: default:
				if (!$this->isFieldExists('auth_id', $this->tnAuth)) {
					return 1;
				} else if (!$this->exists($this->tnAuthMobile)) {
					return 2;
				} else if (!$this->isFieldExists('id', $this->tnAuthTokens)) {
					return 3;
				}
				break;
		}//switch
		return self::FEATURE_VERSION_SEQ;
	}

	/**
	 * Check current feature version and compare it to the
	 * current version, upgrading the db schema as needed.
	 * @param array $aFeatureMetaData - the models current feature metadata.
	 * @param Scene $aScene - (optional) extra data may be supplied
	 */
	public function upgradeFeatureVersion($aFeatureMetaData, $aScene) {
		$theSeq = $aFeatureMetaData['version_seq'];
		switch (true) {
		//cases should always be lo->hi, never use break; so all changes are done in order.
			case ($theSeq<2):
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
			case ($theSeq<3):
				//add new table
				$theSql = $this->getTableDefSql(self::TABLE_AuthMobile);
				$this->execDML($theSql);
				$this->debugLog('v3: '.$this->getRes('install/msg_create_table_x_success/'.$this->tnAuthMobile));
			case ( $theSeq < 4 ):
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
		}//switch
	}

	protected function exists($aTableName=null) {
		return parent::exists( empty($aTableName) ? $this->tnAuth : $aTableName );
	}

	public function isEmpty($aTableName=null) {
		return parent::isEmpty( empty($aTableName) ? $this->tnAuth : $aTableName );
	}

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
		if( ! $this->isConnected() )
			throw AuthPasswordResetException::toss( $this, 'NOT_CONNECTED' ) ;

		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT * FROM ' )->add( $this->tnAuthTokens )
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

		$theSql->endWhereClause()
			->applyOrderByList(array('_changed' => SqlBuilder::ORDER_BY_DESCENDING))
			;
//		$this->debugLog( $theSql->mySql ) ;
		try
		{
			$theSet = $theSql->query() ;
			if (!empty($theSet))
				return $theSet->fetchAll() ;
		}
		catch( PDOException $pdoe )
		{
			$this->debugLog( __METHOD__ . ' DB query failed: '
					. $pdoe->getMessage() ) ;
		}
		return null ;
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
		//save in token table
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
				'auth_id' => $aAuthId,
				'account_id' => $aAcctId,
				'token' => $theAuthToken,
		));
		$nowAsUTC = $this->utc_now();
		$theSql->startWith('INSERT INTO')->add($this->tnAuthTokens);
		$theSql->add('SET')->mustAddParam('_changed', $nowAsUTC)->setParamPrefix(', ');
		$theSql->mustAddParam('auth_id');
		$theSql->mustAddParam('account_id');
		$theSql->mustAddParam('token');
		$theSql->execDML();
		return $theAuthToken;
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
			. Strings::urlSafeRandomChars(64-36-2-strlen($aPrefix))
			. ':'
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
		return Strings::urlSafeRandomChars(64-36-2-strlen($aSuffix))
			. ':'
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
			$theDuration = (!empty($aDuration)) ? $aDuration : $this->dbConfig['auth/cookie_freshness_duration'];
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
		} catch (DbException $e) {
			//do not care if setting cookies fails, log it so admin knows about it, though
			$this->debugLog(__METHOD__.' '.$e->getErrorMsg());
		}
	}

	/**
	 * Delete stale cookie tokens.
	 */
	protected function removeStaleCookies() {
		try {
			$delta = $this->getCookieDurationInDays();
			if (!empty($delta)) {
				$thePrefix = self::TOKEN_PREFIX_COOKIE;
				$theSql = 'DELETE FROM '.$this->tnAuthTokens;
				$theSql .= " WHERE token LIKE '{$thePrefix}%' AND _changed < (NOW() - INTERVAL {$delta} DAY)";
				$this->execDML($theSql);
			}
		} catch (DbException $e) {
			//do not care if removing stale cookies fails, log it so admin knows about it, though
			$this->debugLog(__METHOD__.' '.$e->getErrorMsg());
		}
	}

	/**
	 * Delete stale mobile auth tokens.
	 */
	protected function removeStaleMobileAuthTokens() {
		try {
			$delta = 1;
			if (!empty($delta)) {
				$thePrefix = self::TOKEN_PREFIX_MOBILE;
				$theSql = 'DELETE FROM '.$this->tnAuthTokens;
				$theSql .= " WHERE token LIKE '{$thePrefix}%' AND _changed < (NOW() - INTERVAL {$delta} DAY)";
				$this->execDML($theSql);
			}
		} catch (DbException $e) {
			//do not care if removing stale tokens fails, log it so admin knows about it, though
			$this->debugLog(__METHOD__.' '.$e->getErrorMsg());
		}
	}

	/**
	 * Delete stale auth lockout tokens.
	 */
	protected function removeStaleAuthLockoutTokens() {
		try {
			$delta = 1;
			if ($this->director->isInstalled() && !empty($delta)) {
				$thePrefix = self::TOKEN_PREFIX_LOCKOUT;
				$theSql = 'DELETE FROM '.$this->tnAuthTokens;
				$theSql .= " WHERE token LIKE '{$thePrefix}%' AND _changed < (NOW() - INTERVAL {$delta} HOUR)";
				$this->execDML($theSql);
			}
		} catch (DbException $e) {
			//do not care if removing stale tokens fails, log it so admin knows about it, though
			$this->debugLog(__METHOD__.' '.$e->getErrorMsg());
		}
	}

	/**
	 * Delete stale auth lockout tokens.
	 */
	protected function removeStaleRegistrationCapTokens() {
		try {
			$delta = 1;
			if (!empty($delta)) {
				$thePrefix = self::TOKEN_PREFIX_REGCAP;
				$theSql = 'DELETE FROM '.$this->tnAuthTokens;
				$theSql .= " WHERE token LIKE '{$thePrefix}%' AND _changed < (NOW() - INTERVAL {$delta} HOUR)";
				$this->execDML($theSql);
			}
		} catch (DbException $e) {
			//do not care if removing stale tokens fails, log it so admin knows about it, though
			$this->debugLog(__METHOD__.' '.$e->getErrorMsg());
		}
	}

	/**
	 * Delete stale Anti CSRF tokens.
	 */
	protected function removeStaleAntiCsrfTokens() {
		try {
			$delta = $this->getCookieDurationInDays();
			if (!empty($delta)) {
				$thePrefix = self::TOKEN_PREFIX_ANTI_CSRF;
				$theSql = 'DELETE FROM '.$this->tnAuthTokens;
				$theSql .= " WHERE token LIKE '{$thePrefix}%' AND _changed < (NOW() - INTERVAL {$delta} DAY)";
				$this->execDML($theSql);
			}
		} catch (DbException $e) {
			//do not care if removing stale tokens fails, log it so admin knows about it, though
			$this->debugLog(__METHOD__.' '.$e->getErrorMsg());
		}
	}

	/**
	 * Delete a specific set of anti-CSRF tokens for a user.
	 * @param string $aAuthId - the user's auth_id.
	 * @param number $aAcctId - the user's account_id.
	 */
	protected function removeAntiCsrfToken($aAuthId, $aAcctId) {
		try {
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
					'auth_id' => $aAuthId,
					'account_id' => $aAcctId,
					'token' => self::TOKEN_PREFIX_ANTI_CSRF.'%',
			));
			$theSql->startWith('DELETE FROM')->add($this->tnAuthTokens);
			$theSql->startWhereClause()->mustAddParam('auth_id');
			$theSql->setParamPrefix(' AND ')->mustAddParam('account_id');
			$theSql->setParamOperator(' LIKE ')->mustAddParam('token');
			$theSql->endWhereClause();
			$theSql->execDML();
		} catch (DbException $e) {
			//do not care if removing token fails, log it so admin knows about it, though
			$this->debugLog(__METHOD__.' '.$e->getErrorMsg());
		}
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
		if (!empty($theAuthTokenRow)) {
			//consume this particular cookie
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
					'auth_id' => $aAuthId,
					'token' => $aAuthToken,
			));
			$theSql->startWith('DELETE FROM')->add($this->tnAuthTokens);
			$theSql->startWhereClause()->mustAddParam('auth_id');
			$theSql->setParamPrefix(' AND ')->mustAddParam('token');
			$theSql->endWhereClause();
			$theSql->execDML();
		}
		return $theAuthTokenRow;
	}

	/**
	 * Loads all the appropriate data about an account for caching purposes.
	 * @param Accounts $dbAcct - the accounts model.
	 * @param integer $aAccountId - the account id.
	 * @return AccountInfoCache|NULL Returns the data if found, else NULL.
	 */
	public function getAccountInfoCache(Accounts $dbAccounts, $aAccountId) {
		$theResult = AccountInfoCache::fromArray($dbAccounts->getAccount($aAccountId));
		if (!empty($theResult) && !empty($theResult->account_name)) {
			$theAuthRow = $this->getAuthByAccountId($aAccountId);
			$theResult->auth_id = $theAuthRow['auth_id'];
			$theResult->email = $theAuthRow['email'];
			$theResult->groups = $this->belongsToGroups($aAccountId);
			return $theResult;
		} else {
			return null;
		}
	}

	/**
	 * Authenticates using only the information that a password reset object
	 * would know upon reentry.
	 * @param Accounts $dbAccounts an Accounts prop
	 * @param AuthPasswordReset $aResetUtils an AuthPasswordReset costume
	 */
	public function setPasswordResetCreds( Accounts &$dbAccounts,
			AuthPasswordReset &$aResetUtils )
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
	protected function checkSessionForTicket(Accounts $dbAccounts, $aScene) {
		$theUserInput = $aScene->{self::KEY_userinfo};
		//see if session remembers user
		if (isset($this->director[self::KEY_userinfo]) && empty($theUserInput)) {
			$theAccountId = $this->director[self::KEY_userinfo];
			$this->director->account_info = $this->getAccountInfoCache($dbAccounts, $theAccountId);
			if (empty($this->director->account_info)) {
				//something seriously wrong if session data had values, but failed to load
				$this->ripTicket();
			}
		}
		return (!empty($this->director->account_info));
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
		$theAuthHeader = new HttpAuthHeader($aScene->HTTP_AUTHORIZATION);
		switch ($theAuthHeader->auth_scheme) {
			case 'Basic':
				$aScene->{self::KEY_userinfo} = $theAuthHeader->username;
				$aScene->{self::KEY_pwinput} = $theAuthHeader->pw_input;
				unset($this->HTTP_AUTHORIZATION); //keeping lightly protected pw in memory can be bad.
				return $this->checkWebFormForTicket($dbAccounts, $aScene);
			case 'Broadway':
				//$this->debugLog(__METHOD__.' chkhdr='.$this->debugStr($theAuthHeader));
				if (!empty($theAuthHeader->auth_id) && !empty($theAuthHeader->auth_token)) {
					$this->removeStaleMobileAuthTokens();
					$theAuthTokenRow = $this->getAuthTokenRow($theAuthHeader->auth_id, $theAuthHeader->auth_token);
					//$this->debugLog(__METHOD__.' arow='.$this->debugStr($theAuthTokenRow));
					if (!empty($theAuthTokenRow)) {
						$theAuthMobileRows = $this->getAuthMobilesByAuthId($theAuthHeader->auth_id);
						foreach ($theAuthMobileRows as $theMobileRow) {
							$theFingerprintStr = $theAuthHeader->fingerprints;
							//$this->debugLog(__METHOD__.' fstr1='.$theFingerprintStr);
							if (Strings::hasher($theFingerprintStr, $theMobileRow['fingerprint_hash'])) {
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
	 * Check various mechanisms for authentication.
	 * @see \BitsTheater\models\PropCloset\AuthBase::checkTicket()
	 */
	public function checkTicket($aScene) {
		if ($this->director->canConnectDb()) {
			$this->removeStaleAuthLockoutTokens();
			$dbAccounts = $this->getProp('Accounts');
			$bAuthorized = false;
			$bAuthorizedViaHeaders = false;
			$bAuthorizedViaSession = false;
			$bAuthorizedViaWebForm = false;
			$bAuthorizedViaCookies = false;
			$bCsrfTokenWasBaked = false;
			
			$bAuthorizedViaHeaders = $this->checkHeadersForTicket($dbAccounts, $aScene);
			//if ($bAuthorizedViaHeaders) $this->debugLog(__METHOD__.' header auth');
			$bAuthorized = $bAuthorized || $bAuthorizedViaHeaders;
			if (!$bAuthorized && !$aScene->bCheckOnlyHeadersForAuth)
			{
				$bAuthorizedViaSession = $this->checkSessionForTicket($dbAccounts, $aScene);
				//if ($bAuthorizedViaSession) $this->debugLog(__METHOD__.' session auth');
				$bAuthorized = $bAuthorized || $bAuthorizedViaSession;
			}
			if (!$bAuthorized && !$aScene->bCheckOnlyHeadersForAuth)
			{
				$bAuthorizedViaWebForm = $this->checkWebFormForTicket($dbAccounts, $aScene);
				//if ($bAuthorizedViaWebForm) $this->debugLog(__METHOD__.' webform auth');
				$bAuthorized = $bAuthorized || $bAuthorizedViaWebForm;
			}
			if (!$bAuthorized && !$aScene->bCheckOnlyHeadersForAuth)
			{
				$bAuthorizedViaCookies = $this->checkCookiesForTicket($dbAccounts, $_COOKIE);
				//if ($bAuthorizedViaCookies) $this->debugLog(__METHOD__.' cookie auth');
				$bAuthorized = $bAuthorized || $bAuthorizedViaCookies;
			}
			if (!$bAuthorized && !$aScene->bCheckOnlyHeadersForAuth)
				parent::checkTicket($aScene);

			if ($bAuthorized)
			{
				if ($bAuthorizedViaSession || $bAuthorizedViaWebForm || $bAuthorizedViaCookies)
					$bCsrfTokenWasBaked = $this->setCsrfTokenCookie();
			}
			//else $this->debugLog(__METHOD__.' not authorized');
			$this->returnProp($dbAccounts);
		}
	}

	/**
	 * Log the current user out and wipe the slate clean.
	 * @see \BitsTheater\models\PropCloset\AuthBase::ripTicket()
	 */
	public function ripTicket() {
		try {
			$this->setMySiteCookie(self::KEY_userinfo);
			$this->setMySiteCookie(self::KEY_token);

			$this->removeStaleCookies();

			//remove all cookie records for current login (logout means from everywhere but mobile)
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
					'account_id' => $this->director->account_info->account_id,
					'token' => self::TOKEN_PREFIX_COOKIE . '%',
			));
			$theSql->startWith('DELETE FROM')->add($this->tnAuthTokens);
			$theSql->startWhereClause()->mustAddParam('account_id', 0, PDO::PARAM_INT);
			$theSql->setParamPrefix(' AND ')->setParamOperator(' LIKE ')->mustAddParam('token');
			$theSql->endWhereClause();
			$theSql->execDML();
		} catch (DbException $e) {
			//do not care if removing cookies fails, log it so admin knows about it, though
			$this->debugLog(__METHOD__.' '.$e->getErrorMsg());
		}
		parent::ripTicket();
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
	 * @param number $aDefaultGroup - (optional) default group membership.
	 * @return boolean Returns TRUE if succeeded, FALSE otherwise.
	 */
	public function registerAccount($aUserData, $aDefaultGroup=0) {
		if ($this->isEmpty()) {
			$aDefaultGroup = 1;
		}
		$nowAsUTC = $this->utc_now();
		$theVerifiedTS = ($aUserData['verified_timestamp']==='now')
				? $nowAsUTC
				: $aUserData['verified_timestamp']
		;
		$theSql = SqlBuilder::withModel($this)->obtainParamsFrom(array(
				'auth_id' => Strings::createUUID(),
				'email' => $aUserData['email'],
				'account_id' => $aUserData['account_id'],
				'pwhash' => Strings::hasher($aUserData[self::KEY_pwinput]),
				'verified' => $theVerifiedTS,
				'_created' => $nowAsUTC,
				'_changed' => $nowAsUTC,
		));
		$theSql->startWith('INSERT INTO')->add($this->tnAuth);
		$theSql->add('SET')->mustAddParam('_created')->setParamPrefix(', ');
		$theSql->mustAddParam('_changed');
		$theSql->mustAddParam('auth_id');
		$theSql->mustAddParam('email');
		$theSql->mustAddParam('account_id', 0, PDO::PARAM_STR);
		$theSql->mustAddParam('pwhash');
		$theSql->addParam('verified');
		if ($theSql->execDML()) {
			$dbGroupMap = $this->getProp('AuthGroups');
			$dbGroupMap->addAcctMap($aDefaultGroup,$aUserData['account_id']);
			$this->returnProp($dbGroupMap);
			$this->updateRegistrationCap();
			return true;
		} else {
			return false;
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
		$dbAuthGroups = $this->getProp('AuthGroups');
		$theSql = "SELECT * FROM {$dbAuthGroups->tnGroups} ";
		$r = $dbAuthGroups->query($theSql);
		$theResult = $r->fetchAll();
		$this->returnProp($dbAuthGroups);
		return $theResult;
	}

	/**
	 * Checks the given account information for membership.
	 * @param AccountInfoCache $aAccountInfo - the account info to check.
	 * @return boolean Returns FALSE if the account info matches a member account.
	 */
	public function isGuestAccount($aAccountInfo) {
		if (!empty($aAccountInfo) && !empty($aAccountInfo->account_id) && !empty($aAccountInfo->groups)) {
			return ( array_search(0, $aAccountInfo->groups, true) !== false );
		} else {
			return true;
		}
	}

	/**
	 * Standard mechanism to convert the fingerprint array to a string so it can be
	 * hashed or matched against a prior hash. This should match how the mobile app
	 * will be creating the string inside the http auth header.
	 * @param string[] $aFingerprints - the fingerprint array
	 * @return string Returns the array converted to a string.
	 */
	protected function cnvFingerprintArrayToString($aFingerprints) {
		return '['.implode(', ', $aFingerprints).']';
	}

	/**
	 * Store device data so that we can determine if user/pw are required again.
	 * @param array $aAuthRow - an array containing the auth row data.
	 * @param $aFingerprints - the device's information to store
	 * @param $aCircumstances - mobile device circumstances (gps, timestamp, etc.)
	 * @return array Returns the field data saved.
	 */
	public function registerMobileFingerprints($aAuthRow, $aFingerprints, $aCircumstances) {
		if (!empty($aAuthRow) && !empty($aFingerprints)) {
			$nowAsUTC = $this->utc_now();
			unset($aCircumstances->created_ts); //do not want outside influence on created_ts value.
			unset($aCircumstances->updated_ts); //do not want outside influence on updated_ts value.
			unset($aCircumstances->mobile_id); //do not want outside influence on mobile_id value.
			$theSql = SqlBuilder::withModel($this)->obtainParamsFrom($aCircumstances);
			$theSql->startWith('INSERT INTO')->add($this->tnAuthMobile);
			$theSql->add('SET')->mustAddParam('created_ts', $nowAsUTC)->setParamPrefix(', ');
			$theSql->mustAddParam('updated_ts', $nowAsUTC);
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
			//$this->debugLog(__METHOD__.' aid='.$aAuthRow['account_id'].' fp='.$this->debugStr($aFingerprints));
			/*
			$theSql->addParam('device_id');
			$theSql->addParam('app_version_name');
			$theSql->addParam('device_memory');
			$theSql->addParam('locale');
			$theSql->addParam('app_fingerprint');
			*/
			//mimics Java's Arrays.toString(arr) so we do not have to parse the auth header value
			$theFingerprintStr = $this->cnvFingerprintArrayToString($aFingerprints);
			$theFingerprintHash = Strings::hasher($theFingerprintStr);
			$theSql->mustAddParam('fingerprint_hash', $theFingerprintHash);
			//$theSql->mustAddParam('fingerprint_str', $theFingerprintStr);

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
	 * @return string Returns the auth token generated.
	 */
	protected function generateAuthTokenForMobile($aAcctId, $aAuthId) {
		//generate a token with "mA" so we can tell them apart from cookie tokens
		$theUserToken = $this->director->app_id.'-'.$aAuthId;
		$theAuthToken = $this->generateAuthToken($aAuthId, $aAcctId, self::TOKEN_PREFIX_MOBILE);
		/* do not give mobile the auth token in a cookie!
		$theStaleTime = time()+($this->getCookieDurationInDays('duration_1_day')*(60*60*24));
		$this->setMySiteCookie(self::KEY_userinfo, $theUserToken, $theStaleTime);
		$this->setMySiteCookie(self::KEY_token, $theAuthToken, $theStaleTime);
		*/
		return $theAuthToken;
	}

	/**
	 * Someone entered a user/pw combo correctly from a mobile device, give them tokens!
	 * @param AccountInfoCache $aAcctInfo - successfully logged in account info.
	 * @param $aFingerprints - mobile device info
	 * @param $aCircumstances - mobile device circumstances (gps, timestamp, etc.)
	 * @return array|NULL Returns the tokens needed for ez-auth later.
	 */
	public function requestMobileAuthAfterPwLogin(AccountInfoCache $aAcctInfo, $aFingerprints, $aCircumstances) {
		$theResults = null;
		$theAuthRow = $this->getAuthByAccountId($aAcctInfo->account_id);
		if (!empty($theAuthRow) && !empty($aFingerprints)) {
			$theMobileRow = null;
			//see if they have a mobile auth row already
			$theAuthMobileRows = $this->getAuthMobilesByAccountId($aAcctInfo->account_id);
			if (!empty($theAuthMobileRows)) {
				$theFingerprintStr = $this->cnvFingerprintArrayToString($aFingerprints);
				//see if fingerprints match any of the existing records and return that user_token if so
				foreach ($theAuthMobileRows as $theAuthMobileRow) {
					if (Strings::hasher($theFingerprintStr, $theAuthMobileRow['fingerprint_hash'])) {
						$theMobileRow = $theAuthMobileRow;
						break;
					}
				}
			}
			//$this->debugLog('mobile_pwlogin'.' mar='.$this->debugStr($theAuthMobileRow));
			if (empty($theMobileRow)) {
				//first time they logged in via this mobile device, record it
				$theMobileRow = $this->registerMobileFingerprints($theAuthRow, $aFingerprints, $aCircumstances);
			}
			if (!empty($theMobileRow)) {
				$this->removeStaleMobileAuthTokens();
				$theAuthToken = $this->generateAuthTokenForMobile($aAcctInfo->account_id, $theAuthRow['auth_id']);
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
	 * @param $aFingerprints - mobile device info
	 * @param $aCircumstances - mobile device circumstances (gps, timestamp, etc.)
	 * @return array|NULL Returns the tokens needed for ez-auth later.
	 */
	public function requestMobileAuthAutomatedByTokens($aAuthId, $aUserToken, $aFingerprints, $aCircumstances) {
		$theResults = null;
		$dbAccounts = $this->getProp('Accounts');
		$theAuthRow = $this->getAuthByAuthId($aAuthId);
		$theAcctRow = (!empty($theAuthRow)) ? $dbAccounts->getAccount($theAuthRow['account_id']) : null;
		if (!empty($theAcctRow) && !empty($theAuthRow) && !empty($aFingerprints)) {
			//$this->debugLog(__METHOD__.' c='.$this->debugStr($aCircumstances));
			//they must have a mobile auth row already
			$theAuthMobileRows = $this->getAuthMobilesByAccountId($theAcctRow['account_id']);
			if (!empty($theAuthMobileRows)) {
				$theFingerprintStr = $this->cnvFingerprintArrayToString($aFingerprints);
				//see if fingerprints match any of the existing records and return that user_token if so
				foreach ($theAuthMobileRows as $theAuthMobileRow) {
					if (Strings::hasher($theFingerprintStr, $theAuthMobileRow['fingerprint_hash'])) {
						$theUserToken = $theAuthMobileRow['account_token'];
						break;
					}
				}
				//$this->debugLog(__METHOD__.' ut='.$theUserToken.' param='.$aUserToken);
				//if the user_token we found equals the one passed in as param, then authentication SUCCESS
				if (!empty($theUserToken) && $theUserToken===$aUserToken) {
					//$this->debugLog(__METHOD__.' \o/');
					$theAuthToken = $this->generateAuthTokenForMobile($theAcctRow['account_id'], $theAuthRow['auth_id']);
					$theResults = array(
							'account_name' => $theAcctRow['account_name'],
							'user_token' => $theUserToken,
							'auth_token' => $theAuthToken,
							'api_version_seq' => $this->getRes('website/api_version_seq'),
					);
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
	public function isPasswordResetAllowedFor( $aEmailAddr, &$aResetUtils=null )
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
	public function generatePasswordRequestFor( AuthPasswordReset &$aResetUtils )
	{
		if( ! $this->isConnected() )
			throw BrokenLeg::toss( $this, 'DB_CONNECTION_FAILED' ) ;
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

}//end class

}//end namespace
