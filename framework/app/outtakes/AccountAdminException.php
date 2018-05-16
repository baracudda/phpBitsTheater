<?php
namespace BitsTheater\outtakes ;
use BitsTheater\BrokenLeg ;
{

/**
 * Provides error responses related to account administration.
 * @since BitsTheater 3.6
 */
class AccountAdminException
extends BrokenLeg
{
	const ACT_CANNOT_DELETE_ACTIVE_ACCOUNT = 'CANNOT_DELETE_ACTIVE_ACCOUNT' ;
	const ACT_CANNOT_DELETE_TITAN = 'CANNOT_DELETE_TITAN' ;
	const ACT_CANNOT_DELETE_YOURSELF = 'CANNOT_DELETE_YOURSELF' ;
	const ACT_UNIQUE_FIELD_ALREADY_EXISTS = 'UNIQUE_FIELD_ALREADY_EXISTS' ;
	const ACT_CANNOT_UPDATE_TO_TITAN = 'CANNOT_UPDATE_TO_TITAN' ;
	const ACT_CANNOT_CREATE_TITAN_ACCOUNT = 'CANNOT_CREATE_TITAN_ACCOUNT' ;
	/**
	 * Thrown when a user's input for a password change is shorter than the
	 * minimum length set in the instance's configs.
	 * @var string
	 * @since BitsTheater [NEXT]
	 * @see \BitsTheater\outtakes\AccountAdminException::ERR_PASSWORD_MINIMUM_LENGTH
	 */
	const ACT_PASSWORD_MINIMUM_LENGTH = 'PASSWORD_MINIMUM_LENGTH' ;
	
	const ERR_CANNOT_DELETE_ACTIVE_ACCOUNT = BrokenLeg::HTTP_CONFLICT ;
	const ERR_CANNOT_DELETE_TITAN = BrokenLeg::HTTP_FORBIDDEN ;
	const ERR_CANNOT_DELETE_YOURSELF = BrokenLeg::HTTP_CONFLICT ;
	const ERR_UNIQUE_FIELD_ALREADY_EXISTS = BrokenLeg::HTTP_CONFLICT ;
	const ERR_CANNOT_UPDATE_TO_TITAN = BrokenLeg::HTTP_FORBIDDEN ;
	const ERR_CANNOT_CREATE_TITAN_ACCOUNT = BrokenLeg::HTTP_FORBIDDEN ;
	/**
	 * Thrown when a user's input for a password change is shorter than the
	 * minimum length set in the instance's configs.
	 * @var integer
	 * @since BitsTheater [NEXT]
	 * @see \BitsTheater\outtakes\AccountAdminException::ACT_PASSWORD_MINIMUM_LENGTH
	 * @see \BitsTheater\outtakes\AccountAdminException::MSG_PASSWORD_MINIMUM_LENGTH
	 */
	const ERR_PASSWORD_MINIMUM_LENGTH = BrokenLeg::HTTP_BAD_REQUEST ;
	
	const MSG_CANNOT_DELETE_ACTIVE_ACCOUNT =
		'account/err_cannot_delete_active_account' ;
	const MSG_CANNOT_DELETE_TITAN = 'account/err_cannot_delete_titan' ;
	const MSG_CANNOT_DELETE_YOURSELF = 'account/err_cannot_delete_yourself' ;
	const MSG_UNIQUE_FIELD_ALREADY_EXISTS =
		'account/err_unique_field_already_exists' ;
	const MSG_CANNOT_UPDATE_TO_TITAN =
		'account/errmsg_cannot_update_to_titan' ;
	const MSG_CANNOT_CREATE_TITAN_ACCOUNT =
		'account/errmsg_cannot_create_account_titan' ;
	/**
	 * Thrown when a user's input for a password change is shorter than the
	 * minimum length set in the instance's configs.
	 * @var string
	 * @since BitsTheater [NEXT]
	 * @see \BitsTheater\outtakes\AccountAdminException::ERR_PASSWORD_MINIMUM_LENGTH
	 * @see \BitsTheater\res\en\BitsAccount::$errmsg_password_minimum_length
	 */
	const MSG_PASSWORD_MINIMUM_LENGTH =
		'account/errmsg_password_minimum_length' ;
}

}
