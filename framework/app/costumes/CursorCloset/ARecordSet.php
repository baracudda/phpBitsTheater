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
use BitsTheater\costumes\IDirected;
use BitsTheater\costumes\WornByModel;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a set of records.
 *
 * <pre>
 * $theSet = RecordSet::withContextAndColumns($this, $theFieldList)
 *     ->setDataFromPDO($pdo)
 *     ;
 * </pre>
 *
 * @since BitsTheater 3.8.0
 */
abstract class ARecordSet extends BaseCostume
{
	use WornByModel;
	
	/**
	 * Not used, currently. Future feature.
	 * @var mixed
	 */
	public $filter = null;
	/**
	 * Legacy variable for total query count (not returned result set).
	 * New code should refer to properties of WornForPagerManagement trait.
	 * <br><code>WornForPagerManagement::mPagerTotalRowCount</code>
	 * <br><code>WornForPagerManagement::getPagerTotalRowCount()</code>
	 * @var integer
	 */
	public $total_count = 0;
	
	/**
	 * Return the Model class or name to use in a getProp() call.
	 * @return string
	 */
	protected function getModelClassToUse()
	{ return static::MY_MODEL_CLASS; }
	
	/**
	 * Get this record set's model to use given a context and org_id.
	 * @param IDirected $aContext - the context to get models with.
	 * @param string $aOrgID - the org_id to connect this model to.
	 * @return \BitsTheater\Model Returns the model to use.
	 */
	static public function getMyModelToUse( IDirected $aContext, $aOrgID=null )
	{ return $aContext->getProp(static::MY_MODEL_CLASS, $aOrgID); }
	
	/**
	 * Costume classes know about the Director via IDirected context.
	 * @param IDirected $aContext - used to get the Director object.
	 */
	public function setup(IDirected $aContext) {
		$this->setModel( static::getMyModelToUse($aContext) );
		$this->mItemClassArgs = array( $this->getModel() );
		parent::setup($aContext);
	}
	
	/**
	 * ARecord descendants need at least a model and an export field list.
	 * Helper factory method designed to promote using the defined model
	 * with the ARecordSet descendant and an export field/column name list.
	 * @param IDirected $aContext - used to get the Director object.
	 * @param string[] $aExportFieldList - fields we intend to export.
	 * @return $this Returns $this for chaining.
	 */
	static public function withContextAndColumns( IDirected $aContext, $aExportFieldList=null )
	{
		$theClassName = get_called_class();
		$o = new $theClassName($aContext); //which will run self::setup()
		//once we have an object, set our default item class constructor args.
		$o->setItemClassArgs($o->getModel(), $aExportFieldList);
		return $o;
	}
	
	/**
	 * ARecord descendants need at least a model and an export field list.
	 * Helper factory method designed to promote using the defined model
	 * with the ARecordSet descendant, but with an explicit org to connect
	 * to and an export field/column name list.
	 * @param IDirected $aContext - used to get the Director object.
	 * @param string $aOrgID - the org ID used to connect the model.
	 * @param string[] $aExportFieldList - fields we intend to export.
	 * @return $this Returns $this for chaining.
	 */
	static public function withOrgIDAndColumns( IDirected $aContext, $aOrgID, $aExportFieldList=null )
	{
		$theClassName = get_called_class();
		$o = new $theClassName();
		$o->setDirector($aContext->getDirector());
		$o->setModel(static::getMyModelToUse($aContext, $aOrgID));
		//once we have an object, set our default item class constructor args.
		$o->setItemClass(static::DEFAULT_ITEM_CLASS, $o->getModel(), $aExportFieldList);
		return $o;
	}
	
	/**
	 * ARecord descendants need at least a model and an export field list.
	 * Helper factory method designed to use an explicit org to connect
	 * to and an export field/column name list.
	 * @param IDirected $aContext - used to get the Director object.
	 * @param \BitsTheater\Model $dbToUse - the model to use.
	 * @param string[] $aExportFieldList - fields we intend to export.
	 * @return $this Returns $this for chaining.
	 */
	static public function withModelAndColumns( $dbToUse, $aExportFieldList=null )
	{
		if ( empty($dbToUse) ) throw new \InvalidArgumentException('$dbToUse is empty');
		$theClassName = get_called_class();
		$o = new $theClassName();
		$o->setDirector($dbToUse->getDirector());
		$o->setModel($dbToUse);
		//once we have an object, set our default item class constructor args.
		$o->setItemClass(static::DEFAULT_ITEM_CLASS, array($o->getModel(), $aExportFieldList));
		return $o;
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
	 * @param int $aEncodeOptions options for `json_encode()`
	 */
	protected function printExtraJsonProperties( int $aEncodeOptions=0 ) {
		//nothing to do, by default
	}
	
	/**
	 * Prints the entire data set to the output stream, item by item.
	 * @param int $aEncodeOptions - (optional) options for `json_encode()`
	 * @return $this Returns $this for chaining.
	 * @throws \Exception if output goes awry.
	 */
	public function printAsJson( int $aEncodeOptions=0 ): self
	{
		print('{');
		try
		{
			print( '"filter":"' . $this->filter . '"' );
			if ( isset($this->total_count) ) //can be NULL if not using a pager
				print( ',"' . $this->getJsonTotalCountName() . '":' . $this->total_count );
			print( ',"' . $this->getJsonPropertyName() . '":' );
			parent::printAsJson( $aEncodeOptions );
			$this->printExtraJsonProperties( $aEncodeOptions );
		}
		catch( \Exception $x )
		{
			$this->errorLog( __METHOD__ . ' failed: ' . $x->getMessage() );
			throw $x ;
		}
		finally {
			print(',"count":' . $this->mFetchedCount);
			print('}');
		}
		return $this ;
	}
	
}//end class

}//end namespace
