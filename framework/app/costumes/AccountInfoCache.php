<?php

namespace BitsTheater\costumes ;
use BitsTheater\costumes\Wardrobe\CacheForAuthAccountInfo as BaseCostume ;
{//begin namespace

/**
 * An object representation of non-sensitive account information the Director
 * caches about the current user so that calls to the DB are kept to a minimum
 * for frequently accessed information like the current user's ID or name.
 */
class AccountInfoCache extends BaseCostume
{
	//nothing to override, yet

}//end class

}//end namespace
