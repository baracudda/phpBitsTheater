<?php
/*
 * Copyright (C) 2014 Blackmoon Info Tech Services
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
namespace BitsTheater\costumes\Wardrobe ;
use BitsTheater\costumes\ABitsCostume as BaseCostume ;
use BitsTheater\costumes\WornByModel ;
use BitsTheater\costumes\SqlBuilder ;
use BitsTheater\outtakes\PasswordResetException ;
use BitsTheater\models\Auth as AuthDB ;
use com\blackmoonit\Strings ;
use PDOException ;
{ // begin namespace

/**
 * Provides helper functions for dealing with password reset requests. This
 * costume applies to the AuthBasic and AuthOrgs model.
 */
class AuthPasswordReset extends BaseCostume
{
	use WornByModel ;
	
	const TOKEN_PREFIX = 'PWRESET' ;
	
	static private $properties = array(
		'myEmailAddr', 'myAccountID', 'myAuthID', 'myTokens', 'myNewToken',
		'myReentryURL'
	) ;
	protected $myEmailAddr ;
	protected $myAccountID ;
	protected $myAuthID ;
	protected $myTokens ;
	protected $myNewToken ;
	protected $myReentryURL = null ;
	
	/** @return AuthDB */
	public function getMyModel()
	{ return $this->getModel(); }
	
	/**
	 * Clears all the data that has been recorded by this costume since it was
	 * created (or last cleared). Useful only if the costume instance is being
	 * used persistently (rather than the costume class being used statically).
	 * @return \BitsTheater\costumes\AuthPasswordReset
	 *  this costume instance, with all residual data removed
	 */
	public function clear()
	{
		foreach( self::$properties as $prop )
			unset( $this->$prop ) ;
		return $this ;
	}
	
	/** Alias for clear(). */
	public function reset() { return $this->clear() ; }
	
	/**
	 * Queries the database to find the account ID linked to the specified email
	 * address, if any. If found, the account ID is bound to the instance's
	 * "myAccountID" property for later use.
	 * @param string $aEmailAddr an email address
	 * @return string
	 *  the most-recently-created account ID corresponding to that email address
	 */
	public function getAccountIDForEmail( $aEmailAddr=null )
	{
		if( ! isset($aEmailAddr) ) return null ;
		
		$theSql = SqlBuilder::withModel($this->model)
			->startWith( 'SELECT account_id, auth_id FROM ' )
			->add( $this->model->tnAuth )
			->startWhereClause()
			->mustAddParam( 'email', $aEmailAddr )
			->add( ' AND verified_ts IS NOT NULL' )
			->endWhereClause()
			// Get only the newest account.
			->applyOrderByList(array(
					'created_ts' => SqlBuilder::ORDER_BY_DESCENDING,
			))
			->add('LIMIT 1')
			;
		$theAuthRecord = $theSql->getTheRow() ;
		$this->myEmailAddr = $aEmailAddr;
		$this->myAccountID = $theAuthRecord['account_id'];
		$this->myAuthID = $theAuthRecord['auth_id'];
		return $this->myAccountID;
	}
	
	/** accessor */
	public function getAccountID()
	{ return $this->myAccountID ; }
	
	/** accessor */
	public function getAuthID()
	{ return $this->myAuthID ; }
	
	/**
	 * Set the account ID to use.
	 * @param number $aAcctID - the account ID to use.
	 * @return $this Return $this for chaining.
	 */
	public function setAccountID($aAcctID)
	{
		$this->myAccountID = $aAcctID;
		return $this;
	}

	/**
	 * Set the auth ID to use.
	 * @param number $aAuthID - the auth ID to use.
	 * @return $this Return $this for chaining.
	 */
	public function setAuthID($aAuthID)
	{
		$this->myAuthID = $aAuthID;
		return $this;
	}

	/**
	 * The object can execute some searches based on EITHER the auth ID or the
	 * account ID; this protected function provides an array that indicates
	 * which column should be used. It prefers account ID over auth ID.
	 * @return array an associative array where ['col'] gives the column to
	 *  search on and 'val' gives the value (either account ID or auth ID)
	 */
	protected function chooseIdentifierForSearch()
	{
		if( empty($this->myAccountID) )
			return array( 'col' => 'auth_id', 'val' => $this->myAuthID ) ;
		else
			return array( 'col' => 'account_id', 'val' => $this->myAccountID ) ;
	}
	
	/**
	 * Queries the database for all existing password reset auth tokens for the
	 * account ID to which this instance is bound. If this function is called
	 * out of sequence, an exception is thrown.
	 * @return array Returns an array of token rows.
	 */
	public function getTokens()
	{
		if( empty($this->myAccountID) && empty($this->myAuthID) )
			throw PasswordResetException::toss( $this, 'NO_ACCOUNT_OR_AUTH_ID' ) ;
		$this->removeStaleResetTokens();
		$this->myTokens = $this->getMyModel()->getAuthTokens( $this->myAuthID,
				$this->myAccountID, static::TOKEN_PREFIX . '%', true
		);
		return $this->myTokens;
	}
	
	/**
	 * Indicates whether the token set contains a token which was created
	 * within the last freshness time period and is linked to the specified
	 * account ID.
	 * @param string $aToken - (OPTIONAL) specific token to search for.
	 * @return boolean Returns TRUE if one of the supplied tokens was
	 *   created within the last freshness time period.
	 */
	public function hasRecentToken( $aToken=null )
	{
		if( empty( $this->myAccountID ) && empty($this->myAuthID) )
			throw PasswordResetException::toss( $this, 'NO_ACCOUNT_OR_AUTH_ID' ) ;
		if( empty( $this->myTokens ) ) return false ;
		
		$theAuthFilter = $this->chooseIdentifierForSearch() ;
		foreach( $this->myTokens as $theTokenRow )
		{
			if ( $theTokenRow[$theAuthFilter['col']] == $theAuthFilter['val'] ) {
				return ( empty($aToken) || ($theTokenRow['token'] == $aToken) );
			}
		}
		return false ;
	}
	
	/**
	 * Creates a new password reset request token in the database, bound to the
	 * specified account ID.
	 * @return AuthPasswordReset the costume instance
	 */
	public function generateToken()
	{
		if( empty( $this->myAccountID ) )
			throw PasswordResetException::toss( $this, 'NO_ACCOUNT_ID' ) ;
		if( empty( $this->myAuthID ) )
			throw PasswordResetException::toss( $this, 'NO_AUTH_ID' ) ;
		$this->myNewToken = AuthDB::generatePrefixedAuthToken( static::TOKEN_PREFIX ) ;
		$theSql = SqlBuilder::withModel($this->model)
			->startWith( 'INSERT INTO ' )->add( $this->model->tnAuthTokens )
			->add( 'SET ' )
			->mustAddParam('created_by', $_SERVER['REMOTE_ADDR'])
			->setParamPrefix( ', ' )
			->mustAddParam('created_ts', $this->model->utc_now())
			->mustAddParam( 'auth_id', $this->myAuthID )
			->mustAddParam( 'account_id', $this->myAccountID )
			->mustAddParam( 'token', $this->myNewToken )
			//->logSqlDebug(__METHOD__) //DEBUG
			;
		try
		{
			//execDML() on parameterized queries ALWAYS returns true,
			//  exceptions are the only means of detecting failure here.
			$theSql->execDML() ;
			return $this ;
		}
		catch( PDOException $pdoe )
		{
			$this->myNewToken = null ;
			throw PasswordResetException::toss( $this, 'TOKEN_GENERATION_FAILED' ) ;
		}
	}
	
	/**
	 * Once a new token is successfully created, this function will delete any
	 * old password request tokens that might be lingering in the database.
	 * @return AuthPasswordReset the costume instance
	 */
	public function deleteOldTokens()
	{
		if( empty($this->myAccountID) && empty($this->myAuthID) )
			throw PasswordResetException::toss( $this, 'NO_ACCOUNT_OR_AUTH_ID' ) ;
		if( empty( $this->myNewToken ) )
			throw PasswordResetException::toss( $this, 'NO_NEW_TOKEN' ) ;
		$dbAuth = $this->getMyModel();
		$theAuthFilter = $this->chooseIdentifierForSearch() ;
		//we need to remove only old pwreset tokens, not all but the new token
		$theSql = SqlBuilder::withModel($dbAuth)
			->startWith( 'DELETE FROM' )->add( $dbAuth->tnAuthTokens )
			->startWhereClause()
			->mustAddParam( $theAuthFilter['col'], $theAuthFilter['val'] )
			->setParamPrefix( ' AND ' )
			->setParamOperator(' LIKE ') //" TOTALLY " ;)
			->mustAddParamForColumn( 'pwreset_tokens', 'token', static::TOKEN_PREFIX . '%' )
			->setParamOperator(SqlBuilder::OPERATOR_NOT_EQUAL)
			->mustAddParamForColumn( 'myNewToken', 'token', $this->myNewToken )
			->endWhereClause()
			;
//		$this->debugLog( $theSql->mySql ) ;
		$isSuccess = false ;
		try
		{
			if( $theSql->execDML() )
			{ unset($this->myTokens) ; $isSuccess = true ; }
		}
		catch( PDOException $pdoe )
		{ ; }
		if( ! $isSuccess )
		{
			$this->errorLog( 'Failed to delete old pasword reset tokens for '
					. 'user. Administrator may need to purge expired tokens '
					. 'manually.'
				) ;
		}
		
		return $this ;
	}
	
	/**
	 * Once a new token is successfully used, this function will delete any
	 * password request tokens that might be lingering in the database.
	 * @return AuthPasswordReset the costume instance
	 */
	public function deleteAllTokens()
	{
		if( empty($this->myAccountID) && empty($this->myAuthID) )
			throw PasswordResetException::toss( $this, 'NO_ACCOUNT_OR_AUTH_ID' ) ;
		$dbAuth = $this->getMyModel();
		$theAuthFilter = $this->chooseIdentifierForSearch() ;
		//we need to remove _some_ of the tokens, not all of them
		$theSql = SqlBuilder::withModel($dbAuth)
			->startWith( 'DELETE FROM' )->add( $dbAuth->tnAuthTokens )
			->startWhereClause()
			->mustAddParam( $theAuthFilter['col'], $theAuthFilter['val'] )
			->setParamPrefix( ' AND (' )
			->setParamOperator(' LIKE ') //" TOTALLY " ;)
			->mustAddParamForColumn( 'pwreset_tokens', 'token', static::TOKEN_PREFIX . '%' )
			->setParamPrefix( ' OR ' )
			->mustAddParamForColumn( 'lockout_tokens', 'token', $dbAuth::TOKEN_PREFIX_LOCKOUT . '%' )
			->add(')')
			->endWhereClause()
			;
		try
		{
			$theSql->execDML();
			unset($this->myTokens) ;
		}
		catch( PDOException $pdoe )
		{
			//do not care if removing tokens fails, log it so admin knows about it, though
			$this->debugLog(__METHOD__ . ' ' . $pdoe->getErrorMsg());
		}
		return $this ;
	}

	/**
	 * Accessor for the "new" token for the most recent request, if any.
	 * @return string the token
	 */
	public function getNewToken()
	{ return $this->myNewToken ; }

	/**
	 * Accessor for pre-defined reentry URL.
	 * @return string the reentry URL, if any is defined
	 */
	public function getReentryURL()
	{ return $this->myReentryURL ; }

	/**
	 * Mutator for pre-defined reentry URL.
	 * @param string $aURL the URL
	 * @return AuthPasswordReset the costume instance
	 */
	public function setReentryURL( $aURL )
	{ $this->myReentryURL = $aURL ; return $this ; }

	/**
	 * Dispatches the notification email to the user.
	 * @param object $aMailer a MailUtils object that is already configured with
	 *  the host/port/user/pw necessary to send outgoing mail.
	 * @return boolean true if the mail is successfully dispatched
	 */
	public function dispatchEmailToUser( &$aMailer )
	{
		if( $aMailer === null )
			throw PasswordResetException::toss( $this, 'EMAIL_LIBRARY_FAILED' );
		$aMailer->addAddress( $this->myEmailAddr ) ;
		$aMailer->Subject =
			$this->model->getRes( 'account/msg_pw_reset_requested' ) ;
		$aMailer->msgHTML( $this->composeEmailToUser() ) ;
		$aMailer->AltBody = $this->composeReentryURL() ;
		if( $aMailer->send() )
		{
			$this->debugLog( 'Password reset email successfully dispatched to ['
					. $this->myEmailAddr . '].' ) ;
			return true ;
		}
		else
		{
			$this->debugLog( 'Password reset email dispatch failed.' ) ;
			throw PasswordResetException::toss( $this,
					'EMAIL_DISPATCH_FAILED', $this->myEmailAddr ) ;
		}
	}
	
	/**
	 * Composes the body of the email that will be sent to the user.
	 * @return string the full HTML body of the message
	 */
	protected function composeEmailToUser()
	{
		$theURL = ( empty($this->myReentryURL) ?
				$this->composeReentryURL() : $this->myReentryURL ) ;
		$s = $this->model->getRes( 'account/email_body_pwd_reset_instr/'
			. $this->myEmailAddr . '/'
			. $this->getRandomCharsFromToken() )
			. '<a href="' . $theURL . '">' . $theURL . '</a>'
			;
		return $s ;
	}
	
	/**
	 * Cracks the "random string of chars" portion of the token into a separate
	 * string. This will be used as the temporary password for the account.
	 * @return string the "random string of chars" portion of the auth token
	 */
	protected function getRandomCharsFromToken()
	{
		$theTokenTokens = explode( ':', $this->myNewToken ) ;
		return $theTokenTokens[1] ;
	}
	
	/**
	 * Composes the reentry URL that will allow the user to access the site and
	 * go directly to the password edit form.
	 * @return string a URL to be shared with the user
	 */
	protected function composeReentryURL()
	{
		$s = SERVER_URL
				. $this->model->director->getSiteUrl('account/password_reset_reentry')
				. '/' . $this->myAuthID . '/' . $this->myNewToken
				;
//		$this->debugLog( 'Created reentry URL [' . $s
//				. '] for email bound for [' . $this->myEmailAddr . '].'
//				);
		return $s ;
	}
	
	public function authenticateForReentry( $aAuthID, $aAuthToken )
	{
		if ( empty( $aAuthID ) ) {
			throw PasswordResetException::toss( $this, 'NO_AUTH_ID' ) ;
		}
		$this->myAuthID = $aAuthID;
		if ( empty( $aAuthToken ) ) {
			throw PasswordResetException::toss( $this, 'NO_NEW_TOKEN' ) ;
		}
		$this->myNewToken = $aAuthToken;
		$this->myTokens = $this->getTokens();
		if ( !$this->hasRecentToken($aAuthToken) ) {
			$x = PasswordResetException::toss($this, 'RESET_REQUEST_NOT_FOUND');
			if ( !empty($this->myEmailAddr) ) {
				$x->putExtra('email', $this->myEmailAddr);
			}
			if ( !empty($this->myAuthID) ) {
				$x->putExtra('auth_id', $this->myAuthID);
			}
			throw $x;
		}
		$this->myAccountID = $this->myTokens[0]['account_id'];
		return $this->getMyModel()->setPasswordResetCreds(
				$this->getMyModel()->getProp('Accounts'), $this
		);
	}
	
	/**
	 * Clobbers the user's password, replacing it with the random characters
	 * that were inserted into the authentication token!
	 */
	public function clobberPassword()
	{
		if( empty($this->myAuthID) && empty($this->myAccountID) )
			throw PasswordResetException::toss( $this, 'NO_ACCOUNT_OR_AUTH_ID' ) ;
		if( empty($this->myNewToken) )
			throw PasswordResetException::toss( $this, 'REENTRY_AUTH_FAILED' ) ;
		$theAuthFilter = $this->chooseIdentifierForSearch() ;
		$theSql = SqlBuilder::withModel( $this->model )
			->startWith( 'UPDATE ' )->add( $this->model->tnAuth )
			->setParamPrefix( ' SET ' )
			->mustAddParam( 'pwhash',
					Strings::hasher( $this->getRandomCharsFromToken() ) )
			->startWhereClause()
			->mustAddParam( $theAuthFilter['col'], $theAuthFilter['val'] )
			->endWhereClause()
			;
//		$this->debugLog( $theSql->mySql ) ;
		try { $theSql->execDML() ; }
		catch( PDOException $pdox )
		{
			$this->errorLog( __METHOD__ . ': ' . $pdox->getMessage() ) ;
			throw PasswordResetException::toss( $this,
					'DANG_PASSWORD_YOU_SCARY' ) ;
		}
	}
	
	/** @return number The number of days a reset token remains fresh. 0=∞ */
	protected function getTokenFreshnessDays()
	{
		return 1;
	}
	
	/**
	 * Delete stale cookie tokens.
	 */
	public function removeStaleResetTokens() {
		$delta = $this->getTokenFreshnessDays();
		if ( !empty($delta) ) {
			$this->getMyModel()->removeStaleTokens(static::TOKEN_PREFIX.'%', $delta.' DAY');
		}
	}

} // end AuthPasswordReset class

} // end namespace
