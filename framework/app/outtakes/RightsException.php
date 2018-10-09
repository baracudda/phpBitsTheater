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
	const ACT_GROUP_NOT_FOUND = 'ACT_GROUP_NOT_FOUND' ;
	const ERR_GROUP_NOT_FOUND = BrokenLeg::HTTP_NOT_FOUND ;
	const MSG_GROUP_NOT_FOUND = 'auth_groups/errmsg_group_not_found' ;

	/**
	 * Throw when trying to find a group that has site administration privileges
	 * but none seem to exist.
	 * @var string
	 */
	const ACT_WILD_WEST = 'WILD_WEST' ;
	const ERR_WILD_WEST = BrokenLeg::HTTP_NOT_IMPLEMENTED ;
	const MSG_WILD_WEST = 'auth_groups/errmsg_wild_west' ;

} // end class RightsException

} // end namespace BitsTheater\outtakes
