<?php
namespace BitsTheater\outtakes ;
use BitsTheater\BrokenLeg ;
{//begin namespace

/**
 * Provides error responses specific to user group permission management.
 */
class RightsException
extends BrokenLeg
{

	const ERR_CANNOT_MODIFY_TITAN = 403 ;
	const ERR_CANNOT_COPY_FROM_TITAN = 403 ;
	const ERR_CANNOT_COPY_TO_TITAN = 403 ;
	const ERR_GROUP_NOT_FOUND = 404 ;

	const MSG_CANNOT_MODIFY_TITAN = 'auth_groups/errmsg_cannot_modify_titan' ;
	const MSG_CANNOT_COPY_FROM_TITAN =
		'auth_groups/errmsg_cannot_copy_from_titan' ;
	const MSG_CANNOT_COPY_TO_TITAN =
		'auth_groups/errmsg_cannot_copy_to_titan' ;
	const MSG_GROUP_NOT_FOUND = 'auth_groups/errmsg_group_not_found' ;

	const ACT_CANNOT_MODIFY_TITAN = 'CANNOT_MODIFY_TITAN' ;
	const ACT_CANNOT_COPY_FROM_TITAN = 'CANNOT_COPY_FROM_TITAN' ;
	const ACT_CANNOT_COPY_TO_TITAN = 'ACT_CANNOT_COPY_TO_TITAN' ;
	const ACT_GROUP_NOT_FOUND = 'ACT_GROUP_NOT_FOUND' ;

} // end class RightsException

} // end namespace BitsTheater\outtakes
