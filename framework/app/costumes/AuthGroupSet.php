<?php
namespace BitsTheater\costumes;
use BitsTheater\costumes\CursorCloset\AuthGroupSet as BaseCostume;
{//namespace begin

class AuthGroupSet extends BaseCostume
{
	/**
	 * The name of the class that will be used by default to contain items of
	 * the set.
	 * @var string
	 */
	const DEFAULT_ITEM_CLASS = AuthGroup::ITEM_CLASS;

}//end class

}//end namespace
