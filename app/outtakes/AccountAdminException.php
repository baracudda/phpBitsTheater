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
	const ERR_CANNOT_DELETE_ACTIVE_ACCOUNT = BrokenLeg::HTTP_CONFLICT ;
	const ERR_CANNOT_DELETE_TITAN = BrokenLeg::HTTP_FORBIDDEN ;
	const ERR_CANNOT_DELETE_YOURSELF = BrokenLeg::HTTP_CONFLICT ;
	const ERR_UNIQUE_FIELD_ALREADY_EXISTS = BrokenLeg::HTTP_CONFLICT ;
	const ERR_CANNOT_UPDATE_TO_TITAN = BrokenLeg::HTTP_FORBIDDEN ;

	const MSG_CANNOT_DELETE_ACTIVE_ACCOUNT =
		'account/err_cannot_delete_active_account' ;
	const MSG_CANNOT_DELETE_TITAN = 'account/err_cannot_delete_titan' ;
	const MSG_CANNOT_DELETE_YOURSELF = 'account/err_cannot_delete_yourself' ;
	const MSG_UNIQUE_FIELD_ALREADY_EXISTS =
		'account/err_unique_field_already_exists' ;
	const MSG_CANNOT_UPDATE_TO_TITAN =
		'account/errmsg_cannot_update_to_titan' ;
}

}
