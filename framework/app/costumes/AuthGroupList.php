<?php
namespace BitsTheater\costumes;
use BitsTheater\costumes\CursorCloset\AuthGroupList as BaseCostume;
{//namespace begin

class AuthGroupList extends BaseCostume
{
	
	/**
	 * The name of the class that will be used by default to contain items of
	 * the set.
	 * @var string
	 */
	const DEFAULT_ITEM_CLASS = AuthGroup::ITEM_CLASS;
	

}//end class

}//end namespace
