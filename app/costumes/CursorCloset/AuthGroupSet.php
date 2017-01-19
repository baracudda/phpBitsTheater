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
use BitsTheater\costumes\colspecs\IteratedSet as BaseCostume;
use BitsTheater\models\AuthGroups as MyModel;
use BitsTheater\Director;
use com\blackmoonit\FinallyBlock;
use Exception;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a set of auth groups.
 *
 * <pre>
 * $theSet = AuthGroupSet::create($this->getDirector())
 *     ->setDataFromPDO($pdo)
 *     ;
 * </pre>
 */
class AuthGroupSet extends BaseCostume
{
	public $filter = null;
	public $total_count = 0;
	
	/**
	 * The model I need to access to.
	 * @var MyModel
	 */
	protected $dbModel = null;

	/**
	 * The name of the class that will be used by default to contain items of
	 * the set.
	 * @var string
	 */
	const DEFAULT_ITEM_CLASS = 'AuthGroup';

	/**
	 * Costume classes know about the Director.
	 * @param Director $aDirector - site director object
	 */
	public function setup(Director $aDirector) {
		$this->dbModel = $aDirector->getProp(MyModel::MODEL_NAME);
		$this->mItemClassArgs = array($this->dbModel);
		parent::setup($aDirector);
	}
	
	/**
	 * Prints the entire data set to the output stream, item by item.
	 * @param string $aEncodeOptions options for `json_encode()`
	 * @return IteratedSet $this
	 */
	public function printAsJson( $aEncodeOptions=null )
	{
		print( '{' ) ;
		$theFinalEnclosure = new FinallyBlock(function($me) {
			print( ',"count":' . $me->mFetchedCount );
			print( '}' ) ;
		}, $this);
		try
		{
			print( '"filter":"' . $this->filter . '"');
			print( ',"total_count":' . $this->total_count );
			print( ',"authgroups":');
			parent::printAsJson( $aEncodeOptions );
		}
		catch( Exception $x )
		{
			$this->debugLog( __METHOD__ . ' failed: ' . $x->getMessage() );
			throw $x ;
		}
		return $this ;
	}

}//end class

}//end namespace
