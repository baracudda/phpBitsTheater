<?php
namespace BitsTheater\costumes ;
{ // begin namespace

/**
 * A set of methods useful when running under CLI mode.
 */
trait WornForCLI
{

	/**
	 * Determine if we are executing in CLI mode or not.
	 * @return boolean
	 */
	public function isRunningUnderCLI()
	{
		return (php_sapi_name() === 'cli' OR defined('STDIN'));
	}

} // end trait

} // end namespace
	