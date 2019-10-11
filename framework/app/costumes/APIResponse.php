<?php
namespace BitsTheater\costumes;
use BitsTheater\costumes\Wardrobe\APIResponse as BaseCostume;
{//namespace begin

/**
 * Standard API response object to use as $v->results when returning a response.
 * This will also be used in the case that BrokenLeg exception is caught by
 * the framework just before rendering the response as JSON so that errors will
 * also use this object to return the error response as well.
 */
class APIResponse extends BaseCostume
{
	//nothing to override, yet.
	
}//end class

}//end namespace
