<?php

namespace BitsTheater\costumes;
{//namespace begin

/**
 * Amazon S3 content item can be used as this object rather than
 * an associative array.
 * @since BitsTheater v4.0.0
 */
class AmazonS3Item extends \BitsTheater\costumes\CursorCloset\AmazonS3Item
{
	
	/**
	 * Descendants should copy this function to their class as well.
	 * @return string Returns the proper class name to use for the Owner class.
	 */
	protected function getItemOwnerClass()
	{
		return __NAMESPACE__ . '\\AmazonS3ItemOwner';
	}
	
}

class AmazonS3ItemOwner extends \BitsTheater\costumes\CursorCloset\AmazonS3ItemOwner
{
	//nothing to extend, yet
}

}//end namespace
