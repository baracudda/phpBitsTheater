<?php
namespace BitsTheater\costumes;
use BitsTheater\costumes\CursorCloset\AuthAccountSet as BaseCostume;
use BitsTheater\costumes\AuthAccount;
use Exception;
{//namespace begin

class AuthAccountSet extends BaseCostume
{
	/**
	 * The name of the class that will be used by default to contain items of
	 * the set.
	 * @var string
	 */
	const DEFAULT_ITEM_CLASS = AuthAccount::ITEM_CLASS;

} // end class

} // end namespace
