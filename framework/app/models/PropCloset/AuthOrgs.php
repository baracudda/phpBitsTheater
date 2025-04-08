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
use BitsTheater\models\PropCloset\AuthOrgsBase as BaseModel;
use BitsTheater\costumes\colspecs\CommonMySql;
use BitsTheater\costumes\venue\TicketViaAuthHeaderBasic;
use BitsTheater\costumes\venue\TicketViaAuthHeaderBroadway;
use BitsTheater\costumes\venue\TicketViaCookie;
use BitsTheater\costumes\venue\TicketViaMoblieApp;
use BitsTheater\costumes\venue\TicketViaRequest;
use BitsTheater\costumes\venue\TicketViaSession;
use BitsTheater\costumes\venue\TicketViaURL;
use BitsTheater\costumes\AccountInfoCache;
use BitsTheater\costumes\AuthAccount;
use BitsTheater\costumes\IFeatureVersioning;
use BitsTheater\costumes\ISqlSanitizer;
use BitsTheater\costumes\SqlBuilder;
use BitsTheater\Scene;
use com\blackmoonit\exceptions\DbException;
use com\blackmoonit\Strings;
use PDO;
use PDOException;

{//begin namespace

/**
 * AuthOrgs is a special beast where it combines Accounts & Auth tables,
 * and then extends the logic so that each account will also have mobile metadata
 * and auto change what some db connection information.
 * @since BitsTheater v4.0.0
 */
class AuthOrgs extends BaseModel implements IFeatureVersioning
{

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
	 *    Initial schema design.
	 *  </li>
	 *  <li value="2">
	 *    Add Org <span style="font-family:monospace">`dbconn`</span> string field.
	 *  </li>
	 *  <li value="3">
	 *    Add Org <span style="font-family:monospace">`parent_authgroup_id`</span> UUID field.
	 *  </li>
	 *  <li value="4">
	 *    Add "comments" to accounts table.
	 *  </li>
	 *  <li value="5">
	 *    Add disabled_by and _ts to orgs table.
	 *  </li>
	 * </ol>
	 * @var integer
	 */
	const FEATURE_VERSION_SEQ = 5; //always ++ when making db schema changes

	/** @var string The session key used to store current mobile row. */
	const KEY_MobileInfo = 'TicketEnvelope';
	

	public $tnAuthMobile;		const TABLE_AuthMobile = 'auth_mobile';
	/**
	 * A Mobile Auth's token prefix.
	 * @var string
	 */
	const TOKEN_PREFIX_MOBILE = 'mA';
	/**
	 * A mobile hardware ID to account mapping token prefix.
	 * @var string
	 * @since BitsTheater 3.6.1
	 */
	const TOKEN_PREFIX_HARDWARE_ID_TO_ACCOUNT = 'hwid2acct';
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
		$this->tnAuthMobile = $this->tbl_.static::TABLE_AuthMobile;
	}

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
		case static::TABLE_AuthMobile: //added in v3
			$theTableName = (!empty($aTableNameToUse)) ? $aTableNameToUse : $this->tnAuthMobile;
			switch ($this->dbType()) {
			case self::DB_TYPE_MYSQL: default:
				return "CREATE TABLE IF NOT EXISTS {$theTableName} ".
						'( `mobile_id` ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', `auth_id` ' . CommonMySql::TYPE_UUID . ' NOT NULL' .
						', `account_id` INT NULL' .
						', `auth_type` ' . CommonMySql::TYPE_ASCII_CHAR(16) . " NOT NULL DEFAULT {static::MOBILE_AUTH_TYPE_ACTIVE}" .
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
		}//switch TABLE const
	}

	/**
	 * Called during website installation and db re-setupDb feature.
	 * Never assume the database is empty.
	 */
	public function setupModel()
	{
		parent::setupModel();
		$this->setupTable( static::TABLE_AuthMobile, $this->tnAuthMobile ) ;
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
				else if ( !$this->isFieldExists('comments', $this->tnAuthAccounts) )
					return 3;
				else if ( !$this->isFieldExists('disabled_by', $this->tnAuthOrgs) ||
						  !$this->isFieldExists('disabled_ts', $this->tnAuthOrgs)
						)
					return 4;
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
			case ( $theSeq < 4 ): {
				$this->addFieldToTable(4, 'comments', $this->tnAuthAccounts,
						'`comments` VARCHAR(2048) NULL',
						'is_active'
				);
			}
			case ( $theSeq < 5 ):
			{
				$this->addFieldToTable(5, 'disabled_by', $this->tnAuthOrgs,
						'`disabled_by` ' . CommonMySql::ACCOUNT_NAME_SPEC,
				);
				$this->addFieldToTable(5, 'disabled_ts', $this->tnAuthOrgs,
						'`disabled_ts` timestamp NULL DEFAULT NULL',
				);
			}
			case ( $theSeq < 6 ):
			{
				// Next update goes here.
			}
		}//switch
	}

	//=========================================================================
	//===============    From AuthBasic          ==============================
	//=========================================================================

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
			$theAuthIDAlias = $this->tnAuthAccounts . '.auth_id';
			$theIdx = array_search('hardware_ids', $aFieldList);
			if ( $theIdx !== false ) {
				//find mapped hardware ids, if any
				//  NOTE: AuthAccount costume converts this field into the appropriate string
				$aFieldList[$theIdx] = AuthAccount::sqlForHardwareIDs($this, $theAuthIDAlias);
			}
			$theIdx = array_search('lockout_count', $aFieldList);
			if ( $theIdx !== false ) {
				$aFieldList[$theIdx] = AuthAccount::sqlForLockoutCount($this, $theAuthIDAlias);
			}
			$theIdx = array_search('org_ids', $aFieldList);
			if ( $theIdx !== false ) {
				$aFieldList[$theIdx] = AuthAccount::sqlForOrgList($this, $theAuthIDAlias);
			}
			$theIdx = array_search('groups', $aFieldList);
			if ( $theIdx !== false ) {
				$aFieldList[$theIdx] = AuthAccount::sqlForGroupList($this->getAuthGroupsProp(), $theAuthIDAlias);
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
	 * Delete stale mobile auth tokens.
	 */
	public function removeStaleMobileAuthTokens() {
		$this->removeStaleTokens(static::TOKEN_PREFIX_MOBILE.'%', '1 DAY');
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
				TicketViaMoblieApp::class,
				TicketViaAuthHeaderBroadway::class,
				TicketViaAuthHeaderBasic::class,
				TicketViaURL::class,
				TicketViaRequest::class,
				TicketViaSession::class,
				TicketViaCookie::class,
		);
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
		$bWasInTransaction = $this->db->inTransaction() ;
		if( ! $bWasInTransaction )
			$this->db->beginTransaction() ;
		
		$theSqlForMobile = SqlBuilder::withModel($this)
			->startWith( 'DELETE FROM ' . $this->tnAuthMobile )
			->startWhereClause()
			->mustAddParam( 'account_id', $aAccountID )
			->endWhereClause()
			;
		try {
			$theSqlForMobile->execDML() ;
			parent::deleteFor($aAccountID);
		}
		catch( PDOException $pdox ) {
			if( ! $bWasInTransaction )
			{ // Roll back only if we controlled the transaction.
				$this->errorLog( __METHOD__ . 'failed. ' . $pdox->getMessage()) ;
				$this->db->rollBack() ;
			}
			throw new DbException( $pdox, __METHOD__ . ' failed.' ) ;
		}

		if( ! $bWasInTransaction )
			$this->db->commit() ;
	}

	/**
	 * Deletes all tokens, permissions, etc. associated with the specified account ID.
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
			parent::deleteAuthAccount($aAuthID);
			$theSql->commitTransaction();
		}
		catch( PDOException $pdox ) {
			$theSql->rollbackTransaction();
			throw $theSql->newDbException(__METHOD__, $pdox);
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
	 * Get my mobile data, if known.
	 * @return array Returns the mobile data as an array.
	 */
	public function getMyMobileRow()
	{ return TicketViaAuthHeaderBroadway::withAuthDB($this)->getMyMobileRow(); }
	
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
