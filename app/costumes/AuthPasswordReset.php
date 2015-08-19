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
use com\blackmoonit\DbException ;
use com\blackmoonit\database\DbUtils ;
use com\blackmoonit\Strings ;
use \DateTime ;
use \DateTimeZone ;
use \PDO ;
use \Exception ;
{ // begin namespace

/**
 * Provides helper functions for dealing with password reset requests. This
 * costume applies to the AuthBasic model.
 */
class AuthPasswordReset extends BaseCostume
{
	const TOKEN_PREFIX = 'PWRESET' ;
	
	private $model ;
	
	static private $properties = array(
		'myEmailAddr', 'myAccountID', 'myAuthID', 'myTokens', 'myNewToken'
	) ;
	protected $myEmailAddr ;
	protected $myAccountID ;
	protected $myAuthID ;
	protected $myTokens ;
	protected $myNewToken ;
	
	/**
	 * Static function to provide an instance of this costume pre-linked to the
	 * specified AuthBasic model instance.
	 * @param mixed $aModel an instance of the AuthBasic model.
	 * @return \BitsTheater\costumes\AuthPasswordReset
	 *  an instance of this costume, linked to the specified model  
	 */
	static public function withModel( &$aModel )
	{
		$theClassName = get_called_class() ;
		$o = new $theClassName($aModel->director) ;
		return $o->setModel($aModel) ;
	}
	
	/**
	 * Links the costume to an AuthBasic model instance.
	 * @param mixed $aModel an instance of the AuthBasic model
	 * @return \BitsTheater\costumes\AuthPasswordReset
	 *  this costume instance, linked to the specified model
	 */
	public function setModel( &$aModel )
	{ $this->model = $aModel ; return $this ; }
	
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
			->add( ' AND verified IS NOT NULL' )
			->endWhereClause()
			->add( ' ORDER BY _created DESC' )   // Get only the newest account.
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
			throw AuthPasswordResetException::toss( $this->myModel,
					'NO_ACCOUNT_OR_AUTH_ID' ) ;
		
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
			throw AuthPasswordResetException::toss( $this->model,
					'NO_ACCOUNT_OR_AUTH_ID' ) ;
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
//						$theToken['_changed'] ) ;
				$theTokenDate = strtotime( $theToken['_changed'] ) ;
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
			throw AuthPasswordResetException::toss( $this->model,
					'NO_ACCOUNT_ID' ) ;
		if( empty( $this->myAuthID ) )
			throw AuthPasswordResetException::toss( $this->model,
					'NO_AUTH_ID' ) ;
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
		
		throw AuthPasswordResetException::toss( $this->model,
				'TOKEN_GENERATION_FAILED' ) ;
	}
	
	/**
	 * Once a new token is successfully created, this function will delete any
	 * old password request tokens that might be lingering in the database.
	 * @return AuthPasswordReset the costume instance
	 */
	public function deleteOldTokens()
	{
		if( empty($this->myAccountID) && empty($this->myAuthID) )
			throw AuthPasswordResetException::toss( $this->model,
					'NO_ACCOUNT_OR_AUTH_ID' ) ;
		if( empty( $this->myNewToken ) )
			throw AuthPasswordResetException::toss( $this->model,
					'NO_NEW_TOKEN' ) ;
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
			$this->debugLog( 'Failed to delete old pasword reset tokens for ['
					. $this->myAccountID
					. ']. Administrator may need to purge expired tokens '
					. 'manually.'
				) ;
		}
		
		return $this ;
	}
	
	/**
	 * Dispatches the notification email to the user.
	 * @param object $aMailer a MailUtils object that is already configured with
	 *  the host/port/user/pw necessary to send outgoing mail.
	 * @return boolean true if the mail is successfully dispatched
	 */
	public function dispatchEmailToUser( &$aMailer )
	{
		if( $aMailer === null )
			throw AuthPasswordResetException::toss( $this->model,
					'EMAIL_LIBRARY_FAILED' ) ;
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
			throw AuthPasswordResetException::toss( $this->model,
					'EMAIL_DISPATCH_FAILED', $this->myEmailAddr ) ;
		}
	}
	
	/**
	 * Composes the body of the email that will be sent to the user.
	 * @return string the full HTML body of the message
	 */
	protected function composeEmailToUser()
	{
		$theURL = $this->composeReentryURL() ;
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
		$s = 'https://' . $_SERVER['SERVER_NAME']
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
			throw AuthPasswordResetException::toss( $this->model,
					'NO_AUTH_ID' ) ;
		if( empty( $aAuthToken ) )
			throw AuthPasswordResetException::toss( $this->model,
					'NO_NEW_TOKEN' ) ;
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
			throw AuthPasswordResetException::toss( $this->model,
					'NO_ACCOUNT_OR_AUTH_ID' ) ;
		if( empty($this->myNewToken) )
			throw AuthPasswordResetException::toss( $this->model,
					'REENTRY_AUTH_FAILED' ) ;
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
		$this->debugLog( $theSql->mySql ) ;
		try { $theSql->execDML() ; }
		catch( PDOEsception $pdox )
		{
			$this->debugLog( __METHOD__ . ': ' . $pdox->getMessage() ) ;
			throw AuthPasswordResetException::toss( $this->model,
					'DANG_PASSWORD_YOU_SCARY' ) ;
		}
	}
		
} // end AuthPasswordReset class

/**
 * Provides standardized exception codes and text for the password request utils
 * costume.
 */
class AuthPasswordResetException extends BrokenLeg
{
	// Status codes for the exception flavors. Should stay distinct.
	const ERR_NOT_CONNECTED = -4 ;
	const ERR_EMPERORS_NEW_COSTUME = -2 ;
	const ERR_NO_ACCOUNT_ID = -1 ;
	const ERR_NO_AUTH_ID = -1 ;
	const ERR_NO_ACCOUNT_OR_AUTH_ID = -1 ;
	const ERR_TOKEN_GENERATION_FAILED = 1 ;
	const ERR_NO_NEW_TOKEN = 2 ;
	const ERR_EMAIL_LIBRARY_FAILED = 3 ;
	const ERR_EMAIL_DISPATCH_FAILED = 4 ;
	const ERR_REENTRY_AUTH_FAILED = 5 ;
	const ERR_DANG_PASSWORD_YOU_SCARY = 6 ;
	
	// These refer to message resources and can be "overloaded" onto resources.
	const MSG_DEFAULT = 'account/err_fatal' ;
	const MSG_NOT_CONNECTED = 'account/err_not_connected' ;
	const MSG_EMPERORS_NEW_COSTUME = 'account/err_fatal' ;
	const MSG_NO_ACCOUNT_ID = 'account/err_pw_request_failed' ;
	const MSG_NO_AUTH_ID = 'account/err_pw_request_failed' ;
	const MSG_NO_ACCOUNT_OR_AUTH_ID = 'account/err_pw_request_failed' ;
	const MSG_TOKEN_GENERATION_FAILED = 'account/err_pw_request_failed' ;
	const MSG_NO_NEW_TOKEN = 'account/err_pw_request_failed' ;
	const MSG_EMAIL_LIBRARY_FAILED = 'account/err_fatal' ;
	const MSG_EMAIL_DISPATCH_FAILED = 'account/err_email_dispatch_failed' ;
	const MSG_REENTRY_AUTH_FAILED = 'account/msg_pw_request_denied' ;
	const MSG_DANG_PASSWORD_YOU_SCARY = 'account/err_pw_request_failed' ;
	
//	protected $myCondition ;
	
	/**
	 * Provides an instance of the exception using arguments that are relevant
	 * to this costume.
	 * @param object $aContext a BitsTheater object that can provide text
	 *  resources (actor, model, or scene) 
	 * @param string $aCondition a string uniquely identifying the exceptional
	 *  scenario; this must correspond to one of the constants defined within
	 *  the exception class
	 * @param string $aResourceData (optional) any additional data that would be
	 *  passed into a variable substitution in the definition of a text
	 *  resource; if non-empty, then the initial '/' separator is inserted
	 *  automatically here
	 * @return \BitsTheater\costumes\AuthPasswordResetException
	 *  an instance of this exception class, with the appropriate code and
	 *  message set according to the function's arguments
	 */
/*	static public function toss( &$aContext, $aCondition, $aResourceData=null )
	{
		$theCode = self::ERR_DEFAULT ;
		$theCodeID = get_called_class() . '::ERR_' . $aCondition ;
		if( defined( $theCodeID ) )
			$theCode = constant( $theCodeID ) ;
		$theMessage = 'I can\'t even figure out how to throw an exception.' ;
		$theMessageID = get_called_class() . '::MSG_' . $aCondition ;
		if( defined( $theMessageID ) )
		{ // Construct the exception message using translated text resources.
			$theResource = constant($theMessageID) ;
			if( ! empty($aResourceData) )
				$theResource .= '/' . $aResourceData ;
			$theMessage = $aContext->getRes( $theResource ) ;
		}
		$theException =
			new AuthPasswordResetException( $theMessage, $theCode ) ;
		$theException->setCondition($aCondition) ;
		return $theException ;
	}
*/	
	/** accessor for the condition */
/*	public function getCondition()
	{ return $this->myCondition ; }
*/	
	/** mutator for the condition; called by toss() */
/*	protected function setCondition( $aCondition )
	{ $this->myCondition = $aCondition ; return $this ; }
*/	
	/**
	 * Renders the contents of the exception in a way that would be suitable for
	 * a web UI in debug mode.
	 * @return string a string showing the exception's code, message, and
	 *  trigger condition
	 */
/*	public function getDisplayText()
	{
		$theText = '[' . $this->code . ']: ' . $this->message ;
		if( ! empty($this->myCondition) )
			$theText .= ' (' . $this->myCondition . ')' ;
		return $theText ;
	}
*/	
} // end AuthPasswordResetException class

} // end namespace