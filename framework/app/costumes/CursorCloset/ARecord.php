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
use BitsTheater\costumes\WornForExportData;
use BitsTheater\Model as MyModel;
use BitsTheater\costumes\colspecs\CommonMySql;
use com\blackmoonit\Strings;
{//namespace begin

/**
 * PDO statements can fetch data directly into this class.
 * @since BitsTheater 3.8.0
 */
class ARecord extends BaseCostume
{
	use WornForExportData;
	
	/**
	 * Static helper function to fetch a single instance of the record-wrapper
	 * class without using <code>ARecordSet</code>. Intended as a replacement
	 * for the <code>$aSqlBuilder->getTheRow()</code> pattern, such that the
	 * return value is an instance of the record class, and not just an array.
	 * @param \PDOStatement $aStmt the statement from which the data is to be
	 *  fetched
	 * @param MyModel|NULL $aModel (optional:null) a model instance
	 *   to be provided to the record class's constructor
	 * @param array|NULL $aFieldList (optional:null) the list of fields to be
	 *  exported, to be provided ot the record class's constructor
	 * @return ARecord|boolean An instance of the record wrapper class, or
	 *  <code>false</code> on failure (as <code>aPDOStatement::fetch()</code>)
	 * @since BitsTheater v4.0.0
	 * @see \BitsTheater\costumes\colspecs\IteratedSet::fetch()
	 */
	public static function fetchInstanceFromStatement( \PDOStatement $aStmt,
			$aModel=null, $aFieldList=null )
	{
		$theClassName = get_called_class() ;
		$aStmt->setFetchMode( \PDO::FETCH_CLASS | \PDO::FETCH_PROPS_LATE,
				$theClassName, array( $aModel, $aFieldList ) ) ;
		$o = $aStmt->fetch();
		if ( method_exists($o, 'onFetch') ) {
			$o->onFetch();
		}
		return $o;
	}
	
	/**
	 * Static helper function to create an instance of the record-wrapper
	 * class based on row data already retrieved and possibly causing
	 * additional data to be retrieved based on the field list passed in
	 * (such as loading extra mapping information or additional properties
	 * from additional tables).
	 * @param array|object $aRow - row data already fetched.
	 * @param MyModel|NULL $aModel - (OPTIONAL) the model instance.
	 * @param string[]|NULL $aFieldList - (OPTIONAL) the list of fields to be
	 *   exported.
	 * @return $this Returns the newly created instance.
	 * @since BitsTheater [NEXT]
	 */
	public static function fetchInstanceFromRow( $aRow,
			$aModel=null, $aFieldList=null )
	{
		$theClassName = get_called_class();
		$o = new $theClassName($aModel, $aFieldList);
		$o->setDataFrom($aRow);
		if ( method_exists($o, 'onFetch') ) {
			$o->onFetch();
		}
		return $o;
	}
	
	/**
	 * The model I need to access to.
	 * @var MyModel
	 */
	protected $dbModel = null;
	
	/**
	 * Accessor.
	 * @return MyModel Returns the model object.
	 */
	public function getModel()
	{ return $this->dbModel ; }
	
	/**
	 * Binds the costume instance to an instance of a model.
	 * @param MyModel $aModel - the model to bind
	 * @return $this Returns the updated costume
	 */
	public function setModel( $aModel )
	{
		//cannot use WornByModel due to constructors passing NULL to this
		//  function and it expects constructor to require Director as
		//  first param, which this set of classes do not have.
		//  This method accepts NULL and legacy code takes advantage of that
		//  so we cannot just force the param to type hint, HOWEVER, if you
		//  pass in a non-null value, it better by a proper model reference
		//  and that we WILL test and report a coding error with stack trace
		//  so it can easily be found and fixed.
		if ( !empty($aModel) && !($aModel instanceof MyModel) ) {
			Strings::errorLog(__METHOD__, ' [', get_called_class(),
					'] object created with wrong ref to a model.'
			);
			Strings::errorLog(Strings::getStackTrace());
		}
		$this->dbModel = $aModel;
		return $this;
	}
	
	/**
	 * Returns the "context" &mdash; the director from this record's model
	 * @return \BitsTheater\Director|NULL the context, or null if not set up yet
	 * @since BitsTheater v4.2.1
	 */
	public function getContext()
	{
		if( !empty( $this->getModel() ) )
			return $this->getModel()->getDirector() ;
		else return null ;
	}
	
	/**
	 * Static builder method to return an instance of the costume pre-bound
	 * to a model instance.
	 * @param MyModel $aModel the model instance to bind
	 * @param string[] $aFieldList - (OPTIONAL) the field list to return.
	 * @return static Returns an instance of the costume
	 */
	public static function withModel( MyModel $aModel, $aFieldList=null )
	{
		$theClassName = get_called_class() ;
		return new $theClassName($aModel, $aFieldList);
	}
	
	/**
	 * Constructor for an ARecord entails a Model reference and a fieldset
	 * to return, both of which are optional.
	 * @param MyModel $aDbModel - the db model to use.
	 * @param string[] $aFieldList - the field list to return.
	 */
	public function __construct($aDbModel=null, $aFieldList=null)
	{ $this->setModel($aDbModel)->setExportFieldList($aFieldList); }
	
	/**
	 * Construct the standard object with all data fields worth exporting defined.
	 * @return object Returns a standard object with the properties to export defined.
	 */
	protected function constructExportObject()
	{
		$o = (object) call_user_func('get_object_vars', $this);
		$theModel = $this->getModel();
		if ( empty($theModel) ) {
			//PHP < 7, cannot catch "Call to a member function on NULL"
			Strings::errorLog(__METHOD__, ' [', get_called_class(),
					'] object created without a ref to a model.'
			);
			Strings::errorLog(Strings::getStackTrace());
		}
		try {
			//convert all `*_ts` timestamp fields into ISO format
			switch ($theModel->dbType()) {
				case $theModel::DB_TYPE_MYSQL:
					$o = CommonMySql::deepConvertSQLTimestampsToISOFormat($o);
					break;
			}//switch
		}
		catch(\Exception $x) {
			if ( empty($theModel) ) {
				//fires if PHP 7+
				Strings::errorLog(__METHOD__, ' [', get_called_class(),
						'] object created without a ref to a model.'
				);
				Strings::errorLog(Strings::getStackTrace());
			}
			else {
				Strings::errorLog(__METHOD__, ' ', $x);
			}
		}
		return $o ;
	}
	
	/* define a 'onFetch()' method with no return value, if needed.
	/**
	 * Ensure we setup our class correctly after a fetch operation.
	 * /
	public function onFetch()
	{
		
	}
	*/

}//end class

}//end namespace
