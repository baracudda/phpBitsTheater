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
	const ACT_CANNOT_DELETE_YOURSELF = 'CANNOT_DELETE_YOURSELF' ;
	/**
	 * Thrown when a client attempts to create an organization but the "short
	 * name" for the org (used to create its database) fails validation.
	 * @var string
	 * @since BitsTheater [NEXT]
	 * @see \BitsTheater\actors\Understudy\AuthOrgAccount::ajajCreateOrg()
	 */
	const ACT_INVALID_ORG_SHORT_NAME = 'INVALID_ORG_SHORT_NAME' ;
	/**
	 * Thrown when a user's input for a password change is shorter than the
	 * minimum length set in the instance's configs.
	 * @var string
	 * @since BitsTheater [NEXT]
	 * @see \BitsTheater\outtakes\AccountAdminException::ERR_PASSWORD_MINIMUM_LENGTH
	 */
	const ACT_PASSWORD_MINIMUM_LENGTH = 'PASSWORD_MINIMUM_LENGTH' ;
	const ACT_UNIQUE_FIELD_ALREADY_EXISTS = 'UNIQUE_FIELD_ALREADY_EXISTS' ;
	
	const ERR_CANNOT_DELETE_ACTIVE_ACCOUNT = BrokenLeg::HTTP_CONFLICT ;
	const ERR_CANNOT_DELETE_YOURSELF = BrokenLeg::HTTP_CONFLICT ;
	/**
	 * Thrown when a client attempts to create an organization but the "short
	 * name" for the org (used to create its database) fails validation.
	 * @var integer
	 * @since BitsTheater [NEXT]
	 * @see \BitsTheater\actors\Understudy\AuthOrgAccount::ajajCreateOrg()
	 */
	const ERR_INVALID_ORG_SHORT_NAME = BrokenLeg::HTTP_UNPROCESSABLE_ENTITY ;
	/**
	 * Thrown when a user's input for a password change is shorter than the
	 * minimum length set in the instance's configs.
	 * @var integer
	 * @since BitsTheater [NEXT]
	 * @see \BitsTheater\outtakes\AccountAdminException::ACT_PASSWORD_MINIMUM_LENGTH
	 * @see \BitsTheater\outtakes\AccountAdminException::MSG_PASSWORD_MINIMUM_LENGTH
	 */
	const ERR_PASSWORD_MINIMUM_LENGTH = BrokenLeg::HTTP_BAD_REQUEST ;
	const ERR_UNIQUE_FIELD_ALREADY_EXISTS = BrokenLeg::HTTP_CONFLICT ;
	
	const MSG_CANNOT_DELETE_ACTIVE_ACCOUNT =
		'account/err_cannot_delete_active_account' ;
	const MSG_CANNOT_DELETE_YOURSELF = 'account/err_cannot_delete_yourself' ;
	/**
	 * Thrown when a client attempts to create an organization but the "short
	 * name" for the org (used to create its database) fails validation.
	 * @var string
	 * @since BitsTheater [NEXT]
	 * @see \BitsTheater\actors\Understudy\AuthOrgAccount::ajajCreateOrg()
	 */
	const MSG_INVALID_ORG_SHORT_NAME =
		'account/errmsg_invalid_org_short_name' ;
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
	const MSG_UNIQUE_FIELD_ALREADY_EXISTS =
		'account/err_unique_field_already_exists' ;
}

}
