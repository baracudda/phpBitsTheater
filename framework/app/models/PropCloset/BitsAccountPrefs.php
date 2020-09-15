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

namespace BitsTheater\models\PropCloset ;
use PDOException ;
use BitsTheater\BrokenLeg ;
use BitsTheater\Model as BaseModel ;
use BitsTheater\costumes\AccountPrefSpec ;
use BitsTheater\costumes\IFeatureVersioning ;
use BitsTheater\costumes\SqlBuilder ;
use BitsTheater\costumes\WornForAuditFields ;
use BitsTheater\costumes\WornForFeatureVersioning ;
use BitsTheater\costumes\colspecs\CommonMySql ;
use BitsTheater\outtakes\AccountAdminException ;
use BitsTheater\res\AccountPrefs as PrefsResource ;
{

/**
 * Similar to <code>BitsConfig</code>, this is the base class for account-level
 * preferences.
 * @since BitsTheater v4.1.0
 */
class BitsAccountPrefs extends BaseModel
implements IFeatureVersioning
{ use WornForAuditFields, WornForFeatureVersioning ;

	const FEATURE_ID = 'BitsTheater/AccountPrefs' ;
	
	/**
	 * <ol type="1">
	 * <li>Original version, based on <code>BitsConfig</code>.</li>
	 * </ol>
	 * @var integer
	 */
	const FEATURE_VERSION_SEQ = 1 ;
	
	/**
	 * Append DB name to table prefix. Allows us to work across multiple DBs.
	 * @var string
	 */
	const TABLE_PREFIX_INCLUDES_DB_NAME = true ;
	
	//// Model Setup (IFeatureVersioning) //////////////////////////////////////
	
	public $tnAccountPrefs ;
	const TABLE_AccountPrefs = 'account_prefs' ;
	
	/**
	 * Establishes the names of tables managed by this model.
	 * {@inheritDoc}
	 */
	public function setupAfterDbConnected()
	{
		parent::setupAfterDbConnected() ;
		$this->tnAccountPrefs = $this->tbl_ . self::TABLE_AccountPrefs ;
	}
	
	/**
	 * Gets the SQL statement to create a table managed by this model.
	 * {@inheritDoc}
	 * @param string $aTableConst the table identifier constant defined above
	 * @param string $aAltTableName (optional) a custom table name
	 * @return string the SQL to create the table
	 */
	protected function getTableDefSql( $aTableConst, $aAltTableName=null )
	{
		switch( $aTableConst )
		{
			case self::TABLE_AccountPrefs:
				return $this->getAccountPrefsTableDef($aAltTableName) ;
			default:
				return '' ;
		}
	}
	
	/**
	 * Composes the SQL statement that creates the preferences table.
	 * @param string $aAltTableName (optional) a custom table name
	 * @return string the SQL to create the table
	 */
	protected function getAccountPrefsTableDef( $aAltTableName=null )
	{
		$theName = ( !empty($aAltTableName) ?
						$aAltTableName : $this->tnAccountPrefs ) ;
		switch( $this->dbType() )
		{
			case self::DB_TYPE_MYSQL:
			default:
				$theSql = 'CREATE TABLE IF NOT EXISTS ' . $theName
					. ' ('
					. ' `auth_id` '
							. CommonMySql::TYPE_UUID . ' NOT NULL, '
					. ' `namespace` CHAR(64) NOT NULL, '
					. ' `pref_key` CHAR(64) NOT NULL, '
					. ' `pref_value` NVARCHAR(256) NULL DEFAULT NULL, '
					. CommonMySql::getAuditFieldsForTableDefSql() . ', '
					. ' PRIMARY KEY ( auth_id, namespace, pref_key )'
					. ' ) ' . CommonMySql::TABLE_SPEC_FOR_UNICODE
					;
		}
		return $theSql ;
	}
	
	/**
	 * Setup all the tables managed by this model.
	 * {@inheritDoc}
	 */
	public function setupModel()
	{
		$this->setupTable( self::TABLE_AccountPrefs, $this->tnAccountPrefs ) ;
	}
	
	/**
	 * Guesses what version of the feature is actually installed.
	 * {@inheritDoc}
	 * @param object $aContext whatever additional info might be relevant
	 * @return integer the current feature version number
	 */
	public function determineExistingFeatureVersion( $aContext )
	{
		switch( $this->dbType() )
		{
			case self::DB_TYPE_MYSQL:
			default:
				if(!( $this->exists( $this->tnAccountPrefs ) ))
					return 0 ; // Table has never been constructed.
		}
		return self::FEATURE_VERSION_SEQ ;
	}
	
	/**
	 * {@inheritDoc}
	 * @see \BitsTheater\costumes\IFeatureVersioning::upgradeFeatureVersion()
	 */
	public function upgradeFeatureVersion( $aFeatureData, $aScene )
	{
		$theSeq = $aFeatureData['version_seq'] ;
		$this->debugLog( 'Running ' . __METHOD__ . ' v' . $theSeq
				. ' -> v' . self::FEATURE_VERSION_SEQ ) ;
		switch(true) // Match against Boolean tests in cases.
		{ // Cases ordered low->high, without breaks, so that all fall through.
			case( $theSeq < 1 ):
			{
				$this->setupModel() ;
				$this->debugLog( __METHOD__
						. ' [v1]: Initial setup complete.' ) ;
			} break ; // Well, OK, THIS one has a break, but the rest don't!
//			case( $theSeq < 2 ):
//			{ // Do whatever the first upgrade to this model is
//			}
//			etc.
		}
	}
	
	//// Working with the preference definition resources //////////////////////
	
	/**
	 * Fetches the <i>definition</i> of a preference.
	 * @param string $aSpace the preference's namespace
	 * @param string $aPrefKey the preference's name
	 * @return AccountPrefSpec the definition of the preference, or a
	 *  simple <code>false</code> if some problem occurs
	 * @throws AccountAdminException if something goes wrong
	 */
	public function getPreferenceDefinition( $aSpace, $aPrefKey )
	{
		PrefsResource::resolvePreferenceName( $aSpace, $aPrefKey ) ;
		if( empty($aSpace) || empty($aPrefKey) )
		{
			throw AccountAdminException::toss( $this,
					AccountAdminException::ACT_NO_PREFERENCE_SPECIFIED ) ;
		}
		try
		{
			$theNamespaceDef = $this->getRes('account_prefs', $aSpace);
			if( array_key_exists( $aPrefKey, $theNamespaceDef ) )
			{ // This should already be stored as an AccountPrefSpec object.
//				$this->debugLog( __METHOD__ . ' [TRACE] Pref spec: ' . json_encode( $theNamespaceDef[$aPrefKey] ) ) ;
				return $theNamespaceDef[$aPrefKey] ;
			}
		}
		catch( \Exception $x )
		{
			$this->logErrors(__METHOD__, $x);
			throw AccountAdminException::toss( $this,
					AccountAdminException::ACT_NO_SUCH_PREFERENCE,
					array( $aSpace, $aPrefKey )
				);
		}
	}
	
	/**
	 * Shorthand to get the preference definition corresponding to a row of the
	 * preferences table in the database.
	 * @param array $aRow a row from the database
	 * @return AccountPrefSpec the preference definition object
	 */
	public function getPreferenceDefinitionFor( $aRow )
	{
		return $this->getPreferenceDefinition(
				$aRow['namespace'], $aRow['pref_key'] ) ;
	}
	
	/**
	 * Gets the default value of a preference.
	 * May be called directly, but is also used by <code>getPreference()</code>
	 * when an account has no preference of its own provisioned.
	 * @param string $aSpace the preference's namespace
	 * @param string $aPrefKey the preference's name
	 * @return boolean|integer|string the preference's default value
	 */
	public function getDefaultValueFor( $aSpace, $aPrefKey )
	{
		try
		{
			return $this->getPreferenceDefinition($aSpace,$aPrefKey)
				->default_value ;
		}
		catch( AccountAdminException $aax )
		{
			$this->errorLog( __METHOD__ . ' failed: ' . $aax->getMessage() ) ;
			throw $aax ;
		}
	}
	
	/**
	 * Ensures that a given value is returned in the type that we expect.
	 * @param string $aSpace the preference namespace
	 * @param string $aKey the preference key
	 * @param boolean|integer|string $aValue the coerced value
	 */
	protected function coercePreferenceValue( $aSpace, $aKey, $aValue )
	{
		$theSpec = $this->getPreferenceDefinition( $aSpace, $aKey ) ;
		return AccountPrefSpec::coerceValue( $theSpec, $aValue ) ;
	}
	
	/**
	 * Returns a simple array of all preference namespaces.
	 * @return string[] the names of all the preference namespaces
	 */
	public function getPreferenceSpaceKeys()
	{
		$theSpaceDict = $this->getRes( 'account_prefs/'
								. PrefsResource::ROOT_ENUMERATION ) ;
		return array_keys($theSpaceDict) ;
	}
	
	/**
	 * Fills out a map of configuration settings and their default values. This
	 * may then be filled out with a specific account's profile.
	 * @return array a hierarchical map of profile preferences
	 */
	public function getBasePreferenceProfile()
	{
		$theProfile = array() ;
		$theSpaceKeys = $this->getPreferenceSpaceKeys() ;
		foreach( $theSpaceKeys as $theSpaceKey )
		{ // Extract the preference names and default values for each namespace.
			$thePrefs = array() ;
			$theSpaceDef = $this->getRes( 'account_prefs/' . $theSpaceKey ) ;
			foreach( $theSpaceDef as $thePrefName => $thePrefSpec )
			{ // Parse the default value out of each preference definition.
				$thePrefs[$thePrefName] = AccountPrefSpec::coerceValue(
						$thePrefSpec, $thePrefSpec->default_value ) ;
			}
			$theProfile[$theSpaceKey] = $thePrefs ;
		}
//		$this->debugLog( __METHOD__ . ' [TRACE] Default profile: ' . json_encode($theProfile) ) ;
		return $theProfile ;
	}
	
	//// Marshalling preferences to/from the DB ////////////////////////////////
	
	/**
	 * The database methods in this class all exhibit the same error-handling
	 * behavior. However, it's factored out here to a method, in case the app's
	 * override of this class wants to do something different.
	 * @param PDOException $aException the caught failure
	 * @param string $aMethod the name of the method that caught the failure
	 * @param string[] $aInputs (optional:empty set) the input parameters
	 * @param SqlBuilder $aFailed (optional:null) the SqlBuilder whose execution
	 *  caused the failure; this is ignored in the core implementation
	 * @throws \com\blackmoonit\exceptions\DbException an encapsulation of the
	 *  exception; an override may choose to return something instead
	 */
	protected function handleDatabaseException( PDOException $aException, $aMethod, array $aInputs=array(), SqlBuilder $aFailed=null )
	{
		$theException = BrokenLeg::pratfall( BrokenLeg::ACT_DB_EXCEPTION,
				BrokenLeg::HTTP_INTERNAL_SERVER_ERROR,
				$aMethod . ' failed: ' . $aException->getMessage()
			);
		if( !empty($aInputs) )
			$theException->putExtras($aInputs) ;
		throw $theException ;
	}
	
	/**
	 * Fetches a row from the database, representing a specific preference for a
	 * user.
	 * @param string $aAuthID the account ID
	 * @param string $aSpace the preference namespace
	 * @param string $aPrefKey the preference key
	 * @return NULL|array the database row as a dictionary, or null if no such
	 *  row is found
	 */
	protected function getPreferenceRow( $aAuthID, $aSpace, $aPrefKey )
	{
		if( empty($aAuthID) || empty($aSpace) || empty($aPrefKey) )
		{
			throw AccountAdminException::toss( $this,
					AccountAdminException::ACT_NO_PREFERENCE_SPECIFIED ) ;
		}
		
		if( ! $this->exists( $this->tnAccountPrefs ) )
		{ // Oops, there's no table yet. Fake it with the default value.
			return array(
					'namespace' => $aSpace,
					'pref_key' => $aPrefKey,
					'pref_value' => $this->getDefaultValueFor($aSpace,$aPrefKey)
				);
		}

		
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT * FROM ' . $this->tnAccountPrefs )
			->startWhereClause()
			->mustAddParam( 'auth_id', $aAuthID )
			->setParamPrefix( ' AND ' )
			->mustAddParam( 'namespace', $aSpace )
			->mustAddParam( 'pref_key', $aPrefKey )
			->endWhereClause()
//			->logSqlDebug( __METHOD__, ' [TRACE] ' )
			;
		try { return $theSql->getTheRow() ; }
		catch( PDOException $pdox )
		{
			$this->handleDatabaseException( $pdox, __METHOD__,
					array( 'auth_id' => $aAuthID,
						'namespace' => $aSpace,
						'pref_key' => $aPrefKey ),
					$theSql
				);
			return null ; // in case the overridden handler doesn't throw
		}
	}
	
	/**
	 * Fetches one preference value.
	 * @param string $aAuthID the auth ID of the account
	 * @param string $aSpace the preference's namespace
	 * @param string $aPrefKey the preference's name
	 * @return boolean|integer|string the preference value
	 */
	public function getPreference( $aAuthID, $aSpace, $aPrefKey )
	{
		$theRow = $this->getPreferenceRow( $aAuthID, $aSpace, $aPrefKey ) ;
//		$this->debugLog( __METHOD__ . ' [TRACE] preference row serial: ' . json_encode($theRow) ) ;
		if( !empty($theRow) )
		{
			return $this->coercePreferenceValue( $aSpace, $aPrefKey,
					$theRow['pref_value'] ) ;
		}
		else
			return $this->getDefaultValueFor( $aSpace, $aPrefKey ) ;
	}
	
	/**
	 * Writes the value of a preference to the database, coercing it to the
	 * appropriate type if necessary.
	 * @param string $aAuthID the account ID
	 * @param string $aSpace the preference namespace
	 * @param string $aKey the preference key
	 * @param string $aValue the value
	 * @return array dictionary indicating the outcome
	 */
	public function setPreference( $aAuthID, $aSpace, $aKey, $aValue )
	{
		$theResult = array(
				'status' => BrokenLeg::HTTP_OK,
				'value' => $aValue
			);
		if( empty($aAuthID) || empty($aSpace) || empty($aKey) )
		{
			throw AccountAdminException::toss( $this,
					AccountAdminException::ACT_NO_PREFERENCE_SPECIFIED ) ;
		}
		$theSpec = $this->getPreferenceDefinition( $aSpace, $aKey ) ;
		$theValue = AccountPrefSpec::coerceValue( $theSpec, $aValue ) ;
		$theResult['value'] = $theValue ; // to be returned later
		$theRow = $this->getPreferenceRow( $aAuthID, $aSpace, $aKey ) ;
		$theSql = SqlBuilder::withModel($this) ;
		if( empty($theRow) )
		{ // This preference, for this account, is not set; default is in use.
			$theSql->startWith( 'INSERT INTO ' . $this->tnAccountPrefs ) ;
			$this->setAuditFieldsOnInsert($theSql) ;
			$theSql->mustAddParam( 'auth_id', $aAuthID )
				->mustAddParam( 'namespace', $aSpace )
				->mustAddParam( 'pref_key', $aKey )
				->mustAddParam( 'pref_value', $theValue )
//				->logSqlDebug( __METHOD__, ' [TRACE] ' )
				;
			$theResult['status'] = BrokenLeg::HTTP_CREATED ;
		}
		else if( $theValue == $theRow['pref_value'] )
		{ // The preference is already set to the given value; don't bother.
			return $theResult ; // trivially
		}
		else
		{ // We're overwriting a previous non-default value for this preference.
			$theSql->startWith( 'UPDATE ' . $this->tnAccountPrefs ) ;
			$this->setAuditFieldsOnUpdate($theSql) ;
			$theSql->mustAddParam( 'pref_value', $theValue )
				->startWhereClause()
				->mustAddParam( 'auth_id', $aAuthID )
				->setParamPrefix( ' AND ' )
				->mustAddParam( 'namespace', $aSpace )
				->mustAddParam( 'pref_key', $aKey )
//				->logSqlDebug( __METHOD__, ' [TRACE] ' )
				;
		}
		try { $theSql->execDML() ; return $theResult ; }
		catch( PDOException $pdox )
		{
			$this->handleDatabaseException( $pdox, __METHOD__,
					array( 'auth_id' => $aAuthID,
						'namespace' => $aSpace,
						'pref_key' => $aKey,
						'pref_value' => $aValue ),
					$theSql
				);
			// Continue to provide results in case the override doesn't throw.
			$theResult['status'] = BrokenLeg::HTTP_INTERNAL_SERVER_ERROR ;
			return $theResult ;
		}
	}
	
	/**
	 * Deletes the account's preference, effectively resetting it to the default
	 * value.
	 * @param string $aAuthID the account ID
	 * @param string $aSpace the preference namespace
	 * @param string $aKey the preference key
	 * @return boolean indicates success/failure; in the interest of
	 *  idempotence, a trivial deletion (where no such row exists) is considered
	 *  a success
	 */
	public function resetPreference( $aAuthID, $aSpace, $aKey )
	{
		if( empty($aAuthID) || empty($aSpace) || empty($aKey) )
		{
			throw AccountAdminException::toss( $this,
					AccountAdminException::ACT_NO_PREFERENCE_SPECIFIED ) ;
		}
		if( ! $this->exists( $this->tnAccountPrefs ) )
		{ // The table doesn't even exist yet. Now THAT'S successful deletion.
			return true ;
		}
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'DELETE FROM ' . $this->tnAccountPrefs )
			->startWhereClause()
			->mustAddParam( 'auth_id', $aAuthID )
			->setParamPrefix( ' AND ' )
			->mustAddParam( 'namespace', $aSpace )
			->mustAddParam( 'pref_key', $aKey )
//			->logSqlDebug( __METHOD__, ' [TRACE] ' )
			;
		try { $theSql->execDML() ; return true ; }
		catch( PDOException $pdox )
		{
			$this->handleDatabaseException( $pdox, __METHOD__,
					array( 'auth_id' => $aAuthID,
						'namespace' => $aSpace,
						'pref_key' => $aKey ),
					$theSql
				);
			return false ; // in case the overridden handler doesn't throw
		}
	}
	
	/**
	 * Gets all the preferences associated with the account.
	 * @param string $aAuthID the account ID
	 * @return array an array of row dictionaries
	 */
	public function getPreferencesFor( $aAuthID )
	{
		if( ! $this->exists( $this->tnAccountPrefs ) )
			return array() ; // trivially
		
		$theSql = SqlBuilder::withModel($this)
			->startWith( 'SELECT * FROM ' . $this->tnAccountPrefs )
			->startWhereClause()
			->mustAddParam( 'auth_id', $aAuthID )
			->endWhereClause()
//			->logSqlDebug( __METHOD__, ' [TRACE] ' )
			;
		try { return $theSql->query()->fetchAll() ; }
		catch( PDOException $pdox )
		{ $this->handleDatabaseException( $pdox, __METHOD__, null, $theSql ) ; }
	}
	
	/**
	 * Gets a map of all preferences for an account. Where the account has no
	 * explicit preference value, the preference's default value is supplied.
	 * @param string $aAuthID the account ID
	 * @return array a map of preferences to values (set or default)
	 */
	public function getPreferenceProfileFor( $aAuthID )
	{
		$theProfile = $this->getBasePreferenceProfile() ;
		$thePrefRows = $this->getPreferencesFor($aAuthID) ;
		foreach( $thePrefRows as $thePrefRow )
		{ // Blindly add the key/value, or overwrite the default that's there.
			$theSpec = $this->getPreferenceDefinitionFor($thePrefRow) ;
			$theCoercedValue = AccountPrefSpec::coerceValue(
					$theSpec, $thePrefRow['pref_value'] ) ;
			$theProfile[$thePrefRow['namespace']][$thePrefRow['pref_key']] =
					$theCoercedValue ;
		}
//		$this->debugLog( __METHOD__ . ' [TRACE] Resolved preferences for [' . $aAuthID . ']: ' . json_encode($theProfile) ) ;
		return $theProfile ;
	}
}

}