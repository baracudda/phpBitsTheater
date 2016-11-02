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
namespace BitsTheater\costumes ;
use BitsTheater\BrokenLeg ;
use BitsTheater\costumes\ABitsCostume as BaseCostume ;
use BitsTheater\costumes\SqlBuilder ;
use BitsTheater\models\PropCloset\AuthBasic ;
use BitsTheater\outtakes\PasswordResetException ;
use com\blackmoonit\DbException ;
use com\blackmoonit\database\DbUtils ;
use com\blackmoonit\Strings ;
use DateTime ;
use DateTimeZone ;
use PDO ;
use PDOException ;
use Exception ;
{ // begin namespace

/**
 * Provides helper functions for dealing with password reset requests. This
 * costume applies to the AuthBasic model.
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
		$theAccountID = $theAuthRecord['account_id'] ;
		$theAuthID = $theAuthRecord['auth_id'] ;
		$this->setDataFrom(array(
				'myEmailAddr' => $aEmailAddr,
				'myAccountID' => $theAccountID,
				'myAuthID' => $theAuthID
				)) ;
		return $theAccountID ;
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
	 * @return BitsTheater\costumes\AuthPasswordReset
	 */
	public function setAccountID($aAcctID)
	{
		$this->myAccountID = $aAcctID;
		return $this;
	}

	/**
	 * Set the auth ID to use.
	 * @param number $aAuthID - the auth ID to use.
	 * @return BitsTheater\costumes\AuthPasswordReset
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
	 * @return mixed an array of results
	 */
	public function getTokens()
	{
		if( empty($this->myAccountID) && empty($this->myAuthID) )
			throw PasswordResetException::toss( $this, 'NO_ACCOUNT_OR_AUTH_ID' ) ;
		
		$theTokens = $this->model->getAuthTokens( $this->myAuthID,
			$this->myAccountID, self::TOKEN_PREFIX . '%', true ) ;
		$this->setDataFrom(array( 'myTokens' => $theTokens )) ;
		return $theTokens ;
	}
	
	/**
	 * Indicates whether the token set contains a token which was created
	 * within the last 24 hours and is linked to the specified account ID.
	 * @return boolean true if one of the supplied tokens was created or updated
	 *  within the last 24 hours 
	 */
	public function hasRecentToken()
	{
		if( empty( $this->myAccountID ) && empty($this->myAuthID) )
			throw PasswordResetException::toss( $this, 'NO_ACCOUNT_OR_AUTH_ID' ) ;
		if( empty( $this->myTokens ) ) return false ;
		
		/*
		 * Should use DateTime, etc, but trying to do date comparisons with
		 * those classes was like pulling teeth. So instead, we use the MUCH
		 * simpler strtotime() function. However, we may need to alter this
		 * code in preparation for the 2038 timestamp rollover, depending on
		 * the state of the architecture for the server and PHP.
		 */
//		$theNow = new DateTime( 'now', new DateTimeZone('UTC') ) ;
		$theNow = strtotime('now') ;
		$theExpirationInterval = 60 * 60 * 24 ;              // a day of seconds		
		$theAuthFilter = $this->chooseIdentifierForSearch() ;
		foreach( $this->myTokens as $theToken )
		{
			if( $theToken[$theAuthFilter['col']] == $theAuthFilter['val'] )
			{
//				$theTokenDate = DateTime::createFromFormat(
//						DbUtils::DATETIME_FORMAT_DEF_STD,
//						$theToken['updated_ts'] ) ;
				$theTokenDate = strtotime( $theToken['updated_ts'] ) ;
				if( $theNow < $theTokenDate + $theExpirationInterval )
					return true ;
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
		$theToken = AuthBasic::generatePrefixedAuthToken( self::TOKEN_PREFIX ) ;
		$theSql = SqlBuilder::withModel($this->model)
			->startWith( 'INSERT INTO ' )->add( $this->model->tnAuthTokens )
			->add( 'SET ' )
			->mustAddParam( 'auth_id', $this->myAuthID )
			->setParamPrefix( ', ' )
			->mustAddParam( 'account_id', $this->myAccountID )
			->mustAddParam( 'token', $theToken )
			;
//		$this->debugLog( $theSql->mySql ) ;
		try
		{
			if( $theSql->execDML() )
			{
				$this->setDataFrom(array( 'myNewToken' => $theToken )) ;
				return $this ;
			}
			else
			{ $this->myNewToken = null ; }
		}
		catch( PDOException $pdoe )
		{ $this->myNewToken = null ; }
		
		throw PasswordResetException::toss( $this, 'TOKEN_GENERATION_FAILED' ) ;
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
		$theAuthFilter = $this->chooseIdentifierForSearch() ;
		$theSql = SqlBuilder::withModel($this->model)
			->startWith( 'DELETE FROM ' )->add( $this->model->tnAuthTokens )
			->startWhereClause()
			->mustAddParam( $theAuthFilter['col'], $theAuthFilter['val'] )
			->setParamPrefix( ' AND ' )
			->setParamOperator('<>')->mustAddParam( 'token', $this->myNewToken )
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
			$this->debugLog( 'Failed to delete old pasword reset tokens for '
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
		if( empty( $this->myNewToken ) )
			throw PasswordResetException::toss( $this, 'NO_NEW_TOKEN' ) ;
		$theAuthFilter = $this->chooseIdentifierForSearch() ;
		$theSql = SqlBuilder::withModel($this->model)
			->startWith( 'DELETE FROM ' )->add( $this->model->tnAuthTokens )
			->startWhereClause()
			->mustAddParam( $theAuthFilter['col'], $theAuthFilter['val'] )
			->endWhereClause()
			;
		try
		{
			$theSql->execDML();
			unset($this->myTokens) ;
			unset($this->myNewToken) ;
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
	
	public function authenticateForReentry( &$aAuthID, &$aAuthToken )
	{
		if( empty( $aAuthID ) )
			throw PasswordResetException::toss( $this, 'NO_AUTH_ID' ) ;
		if( empty( $aAuthToken ) )
			throw PasswordResetException::toss( $this, 'NO_NEW_TOKEN' ) ;
		$this->setDataFrom(array(
				'myAuthID' => $aAuthID,
				'myNewToken' => $aAuthToken
			)) ;
		$theTokens = $this->model->getAuthTokens( $aAuthID, null, $aAuthToken );
		if( empty($theTokens) ) return false ;
		$this->setDataFrom(array(
				'myAccountID' => $theTokens[0]['account_id'],
				'myTokens' => $theTokens
			)) ;
		if( ! $this->hasRecentToken() ) return false ; // but leave the old one
		
		return $this->model->setPasswordResetCreds(
				$this->model->getProp('Accounts'), $this ) ;
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
			$this->debugLog( __METHOD__ . ': ' . $pdox->getMessage() ) ;
			throw PasswordResetException::toss( $this,
					'DANG_PASSWORD_YOU_SCARY' ) ;
		}
	}
		
} // end AuthPasswordReset class

} // end namespace