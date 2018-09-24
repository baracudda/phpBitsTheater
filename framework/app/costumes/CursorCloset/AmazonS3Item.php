<?php
/*
 * Copyright (C) 2017 Blackmoon Info Tech Services
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace BitsTheater\costumes\CursorCloset;
use BitsTheater\costumes\ASimpleCostume as BaseCostume;
use com\blackmoonit\Strings;
{//namespace begin

class AmazonS3ItemOwner extends BaseCostume
{
	/**
	 * The owner ID is a 64-char string.
	 * @var string
	 */
	public $ID;
	/**
	 * The human-readable account name.
	 * @var string
	 */
	public $DisplayName;
}

/**
 * Amazon S3 content item can be used as this object rather than
 * an associative array.
 * @since BitsTheater v4.0.0
 */
class AmazonS3Item extends BaseCostume
{
	/* Observed array keys for Contents items as of Oct-2017:
		(Guzzle\Service\Resource\Model)|O-1|{
		- Name -> "ryan7405-test-123"
		- Prefix -> ""
		- Marker -> ""
		- MaxKeys -> "1000"
		- IsTruncated -> false
		- Contents -> Array(1)|A-2|[
		- - 0 = Array(6)|A-3|[
		- - - Key = "image/12.13.16_fisch_rule_request.csv"
		- - - LastModified = "2017-05-01T21:17:41.000Z"
		- - - ETag = ""fa1cac3e284291db91bb10148110ced3""
		- - - Size = "11938"
		- - - Owner = Array(2)|A-4|[
		- - - - ID = "6fb4ee82dc554eac95450d5e782227b6b71d0caaab386f96ba154dc54f538691"
		- - - - DisplayName = "red.baracudda"
		- - - ]
		- - - StorageClass = "STANDARD"
		- - ]
		- ]
		- RequestId -> "A002E663A4DB34FF"
		}
	*/
	
	/**
	 * Keys are similar to "subfolder/subfolder...n/filename.ext".
	 * @var string
	 */
	public $Key;
	/**
	 * ISO-8601 date-time format.
	 * @var string
	 */
	public $LastModified;
	/**
	 * ETag contents seem like a quoted hash.
	 * @var string
	 */
	public $ETag;
	/**
	 * The size of the file.
	 * @var number
	 */
	public $Size;
	/**
	 * The owner info given along with the file metadata.
	 * @var AmazonS3ItemOwner
	 */
	public $Owner;
	/**
	 * Storage class enum string, typically "STANDARD".
	 * @var string
	 */
	public $StorageClass;
	
	/**
	 * Returns the byte size in human readable short-form.
	 * @return string Returns the size as, for example, "3MB".
	 */
	public function getSemanticSize()
	{
		return Strings::bytesToSemanticSize($this->Size);
	}
	
	/**
	 * Descendants should copy this function to their class as well.
	 * @return string Returns the proper class name to use for the Owner class.
	 */
	protected function getItemOwnerClass()
	{
		return __NAMESPACE__ . '\\AmazonS3ItemOwner';
	}
	
	/**
	 * Copies values into matching property names
	 * based on the array keys or object property names.
	 * @param array|object $aThing - array or object to copy from.
	 */
	protected function copyFrom( $aThing )
	{
		parent::copyFrom($aThing);
		$this->Size = $this->Size+0;
		if ( !is_null($this->Owner) )
		{
			$theClass = $this->getItemOwnerClass();
			$this->Owner = $theClass::fromArray( $this->Owner );
		}
	}
	
}//end class

}//end namespace
	