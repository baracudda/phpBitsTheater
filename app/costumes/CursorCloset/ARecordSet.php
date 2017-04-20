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
use BitsTheater\costumes\WornByModel;
use BitsTheater\Director;
use com\blackmoonit\FinallyBlock;
use Exception;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a set of records.
 *
 * <pre>
 * $theSet = RecordSet::create($this->getDirector())
 *     ->setDataFromPDO($pdo)
 *     ;
 * </pre>
 *
 * @since BitsTheater 3.7.1
 */
abstract class ARecordSet extends BaseCostume
{
	use WornByModel;
	
	public $filter = null;
	public $total_count = 0;
	
	/**
	 * Return the Model class or name to use in a getProp() call.
	 * @return class|string
	 * @see Director::getProp()
	 */
	abstract protected function getModelClassToUse();
	
	/**
	 * Costume classes know about the Director.
	 * @param Director $aDirector - site director object
	 */
	public function setup(Director $aDirector) {
		$this->setModel( $aDirector->getProp( $this->getModelClassToUse() ) );
		$this->mItemClassArgs = array( $this->getModel() );
		parent::setup($aDirector);
	}
	
	/**
	 * Return the property name the JSON export should use for the total count.
	 * @return string "total_count" is used unless overridden by a descendant.
	 */
	protected function getJsonTotalCountName() {
		return 'total_count';
	}
	
	/**
	 * Return the property name the JSON export should use for the array of records.
	 * @return string "records" is used unless overridden by a descendant.
	 */
	protected function getJsonPropertyName() {
		return 'records';
	}
	
	/**
	 * print() out extra properties besides the set of records here, if any.
	 * @param string $aEncodeOptions options for `json_encode()`
	 */
	protected function printExtraJsonProperties( $aEncodeOptions ) {
		//nothing to do, by default
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
			print( '"filter":"' . $this->filter . '"' );
			if ( isset($this->total_count) ) //can be NULL if not using a pager
				print( ',"' . $this->getJsonTotalCountName() . '":' . $this->total_count );
			print( ',"' . $this->getJsonPropertyName() . '":' );
			parent::printAsJson( $aEncodeOptions );
			$this->printExtraJsonProperties( $aEncodeOptions );
		}
		catch( Exception $x )
		{
			$this->errorLog( __METHOD__ . ' failed: ' . $x->getMessage() );
			throw $x ;
		}
		return $this ;
	}

}//end class

}//end namespace
