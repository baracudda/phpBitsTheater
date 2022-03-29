<?php
namespace BitsTheater\outtakes ;
use BitsTheater\BrokenLeg ;
{

/**
 * Provides standardized exception codes and text for the password request utils
 * costume.
 */
class PasswordResetException
extends BrokenLeg
{
	const ERR_DANG_PASSWORD_YOU_SCARY = 500 ;
	const ERR_EMAIL_DISPATCH_FAILED = 500 ;
	const ERR_EMAIL_LIBRARY_FAILED = 500 ;
	const ERR_EMPERORS_NEW_COSTUME = 500 ;
	const ERR_NO_ACCOUNT_ID = 401 ;
	const ERR_NO_AUTH_ID = 401 ;
	const ERR_NO_ACCOUNT_OR_AUTH_ID = 401 ;
	const ERR_NO_NEW_TOKEN = 500 ;
	const ERR_REENTRY_AUTH_FAILED = 403 ;
	const ERR_REQUEST_DENIED = 403 ;
	const ERR_TOKEN_GENERATION_FAILED = 500 ;
	const ERR_RESET_REQUEST_NOT_FOUND = self::ERR_ENTITY_NOT_FOUND ;
	
	const MSG_DANG_PASSWORD_YOU_SCARY = 'account/err_pw_request_failed' ;
	const MSG_EMAIL_DISPATCH_FAILED = 'account/err_email_dispatch_failed' ;
	const MSG_EMAIL_LIBRARY_FAILED = 'account/err_fatal' ;
	const MSG_EMPERORS_NEW_COSTUME = 'account/err_fatal' ;
	const MSG_NO_ACCOUNT_ID = 'account/err_pw_request_failed' ;
	const MSG_NO_AUTH_ID = 'account/err_pw_request_failed' ;
	const MSG_NO_ACCOUNT_OR_AUTH_ID = 'account/err_pw_request_failed' ;
	const MSG_NO_NEW_TOKEN = 'account/err_pw_request_failed' ;
	const MSG_REENTRY_AUTH_FAILED = 'account/msg_pw_request_denied' ;
	const MSG_REQUEST_DENIED = 'account/msg_pw_request_denied' ;
	const MSG_TOKEN_GENERATION_FAILED = 'account/err_pw_request_failed' ;
	const MSG_RESET_REQUEST_NOT_FOUND = 'account/err_pw_request_not_found';
	
	const ACT_NO_ACCOUNT_OR_AUTH_ID = 'NO_ACCOUNT_OR_AUTH_ID';

} // end class PasswordResetException

} // end namespace BitsTheater\outtakes
