<?php

namespace BitsTheater\costumes;
use BitsTheater\costumes\Wardrobe\ILongRunning202Task as BaseInterface;
use BitsTheater\BrokenLeg;
use BitsTheater\Scene;
{//begin namespace

/**
 * Sometimes the thing we want to do might take several minutes to complete.
 * A costume that implements these methods will work with the results_as_202 view.
 */
interface ILongRunning202Task extends BaseInterface
{
	/**
	 * Task is finally finished, but there was an error.
	 * @param Scene $v - the Scene object in use.
	 * @throws BrokenLeg with appropriate information.
	 */
	function throwCompletedTaskError( Scene $v );
	
}//end interface

}//end namespace
