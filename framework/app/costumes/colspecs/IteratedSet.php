<?php
/*
 * Copyright (C) 2016 Blackmoon Info Tech Services
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
namespace BitsTheater\costumes\colspecs ;
use BitsTheater\costumes\ABitsCostume as BaseCostume ;
use BitsTheater\costumes\IDirected ;
use com\blackmoonit\FinallyBlock ;
use com\blackmoonit\Strings;
use Exception ;
use PDO ;
use PDOStatement ;
{

/**
 * Acts as a container for, and iterator over, a set of data.
 *
 * <pre>
 * $theSet = IteratedSet::create()
 *     ->setItemClass('Foo')
 *     ->setDataFromPDO($pdo)
 *     ;
 * </pre>
 *
 * @since BitsTheater 3.5.3
 */
class IteratedSet extends BaseCostume
{
	/**
	 * The data set to be iterated. Could be a PDOStatement, an array, whatever.
	 * @var mixed
	 */
	public $mDataSet = null ;

	/**
	 * A persistent reference to the last-fetched item in the result set.
	 * If this value is ever equal to `false`, we've reached the end of the set.
	 * @var mixed
	 */
	public $mCurrent = null ;
	
	/**
	 * The count of what has been fetched so far.
	 * @var number
	 */
	public $mFetchedCount = 0;

	/**
	 * The name of the class that will be used by default to contain items of
	 * the set.
	 * @var string
	 */
	const DEFAULT_ITEM_CLASS = '\stdClass' ;

	/**
	 * The name of a class which can contain members of the set.
	 * By default, `\stdClass` will be used. When passing in custom values, use
	 * fully-qualified class names.
	 * @var string
	 */
	public $mItemClass;

	/**
	 * Optional arguments for the constructor of the class that contains a
	 * member of the set.
	 * @var array
	 */
	public $mItemClassArgs = null ;
	
	/**
	 * Optional print out as associative array vs. standard array by using
	 * this field name as the key.
	 * @var string
	 */
	public $mPrintAsJsonObjectWithIdKey = null;
	
	/**
	 * Magic PHP method to limit what var_dump() shows.
	 */
	public function __debugInfo() {
		$vars = parent::__debugInfo();
		if (!empty($vars['mItemClassArgs'])) {
			$theArgs = array();
			foreach ($vars['mItemClassArgs'] as $theThing) {
				if (is_object($theThing)) {
					//let's report the name of the thing rather than its details.
					$theArgs[] = get_class($theThing);
				} else {
					$theArgs[] = $theThing;
				}
			};
			$vars['mItemClassArgs'] = $theArgs;
		}
		return $vars;
	}

	/**
	 * Costume classes know about the Director.
	 * @param IDirected $aContext - used to get the Director object.
	 */
	public function setup(IDirected $aContext) {
		$this->mItemClass = static::DEFAULT_ITEM_CLASS;
		parent::setup($aContext);
	}

	/**
	 * Sets the underlying data set from an already-obtained PDOStatement.
	 * @param PDOStatement $aRowSet the data set
	 * @param string $aItemClass (optional) the name of a class which can
	 *  contain items of the set
	 * @return $this Returns $this for chaining.
	 */
	public function setDataFromPDO( PDOStatement $aRowSet )
	{
		$this->mDataSet = $aRowSet ;
		return $this->setPDOFetchMode() ;
	}

	/**
	 * Sets the name of a class which can contain a member of the set.
	 * @param string $aItemClass the name of a class
	 * @param array $aItemClassArgs (optional) an array of arguments to the
	 *  class's constructor
	 * @return \BitsTheater\costumes\colspecs\IteratedSet $this
	 */
	public function setItemClass( $aItemClass, $aItemClassArgs=null )
	{
		$this->mItemClass = $aItemClass ;
		return $this->setItemClassArgs(...$aItemClassArgs) ;
	}

	/**
	 * Sets the construction arguments for our Item Class.
	 * @param mixed $_ - arguments to pass to the class's constructor.
	 * @return $this Returns $this for chaining.
	 */
	public function setItemClassArgs( ...$args )
	{
		$this->mItemClassArgs = $args ;
		return $this->setPDOFetchMode() ;
	}

	/**
	 * If our data set is already declared as a PDOStatement, this will set the
	 * set's fetch mode such that fetched items will be created as instances of
	 * our item class.
	 * @return IteratedSet $this
	 */
	protected function setPDOFetchMode()
	{
		if( $this->mDataSet instanceof PDOStatement )
		{
			$this->mDataSet->setFetchMode(
					PDO::FETCH_CLASS | PDO::FETCH_PROPS_LATE,
					$this->mItemClass, $this->mItemClassArgs ) ;
		}
		return $this ;
	}

	/**
	 * Creates an iterated set based on an already-obtained PDOStatement.
	 * @param IDirected $aContext the context in which to create the object
	 * @return $this Returns a new instance
	 */
	public static function create( IDirected $aContext )
	{
		$theClassName = get_called_class() ;
		return new $theClassName($aContext) ;
	}
	
	/**
	 * Event called after fetching from $this->mDataSet.
	 * Sets $this->mCurrent and updates $this->mFetchedCount.
	 * @param object $aRow - the fetched data.
	 * @return object Returns the row data fetched.
	 */
	protected function onFetch($aRow)
	{
		$this->mCurrent = $aRow ;
		if ($aRow !== false)
			$this->mFetchedCount += 1 ;
		return $this->mCurrent ;
	}

	/**
	 * Fetches the next item in the data set. The iterator's `current` field
	 * will also contain the object that is fetched.
	 * @return object|boolean - the next item in the set, or `false` if anything
	 *  goes wrong, or if we're off the end of the set.
	 */
	public function fetch()
	{
		if( $this->mDataSet instanceof PDOStatement ) {
			$theRow = $this->mDataSet->fetch();
			if ( is_object($theRow) && method_exists($theRow, 'onFetch') )
			{ $theRow->onFetch(); }
			return $this->onFetch( $theRow );
		}
		else // We haven't implemented any other ways of behaving yet.
			return $this->onFetch( false ) ;
	}

	/**
	 * Prints the entire data set to the output stream, item by item as array.
	 * @param string $aEncodeOptions options for `json_encode()`
	 * @return IteratedSet $this
	 */
	protected function printAsJsonArray( $aEncodeOptions=null )
	{
		$this->mCurrent = null ;
		$this->mFetchedCount = 0 ;
		print( '[' ) ;
		$theFinalEnclosure = new FinallyBlock(function() {
			print( ']' ) ;
		});
		try
		{
			$theSeparator = '' ;
			for( $theItem = $this->fetch() ; $theItem !== false ; $theItem = $this->fetch() )
			{
				print( $theSeparator ) ;
				$theSeparator = ',' ;
				if( method_exists( $theItem, 'printAsJson' ) )
					$theItem->printAsJson( $aEncodeOptions ) ;
				else if( method_exists( $theItem, 'toJson' ) )
					print( $theItem->toJson($aEncodeOptions) ) ;
				else
					print( json_encode( $theItem, $aEncodeOptions ) ) ;
			}
		}
		catch( Exception $x )
		{
			Strings::errorLog( __METHOD__ . ' failed: ' . $x->getMessage() );
			throw $x ;
		}

		return $this ;
	}

	/**
	 * Prints the entire data set to the output stream, item by item as object.
	 * @param string $aItemIdFieldName - the Item Object's field name to use for the ID.
	 * @param string $aEncodeOptions - options for `json_encode()`
	 * @return IteratedSet $this
	 */
	protected function printAsJsonObjectWithIdKey( $aItemIdFieldName, $aEncodeOptions=null )
	{
		$this->mCurrent = null ;
		$this->mFetchedCount = 0 ;
		print( '{' ) ;
		$theFinalEnclosure = new FinallyBlock(function() {
			print( '}' ) ;
		});
		try
		{
			$theSeparator = '' ;
			for( $theItem = $this->fetch() ; $theItem !== false ; $theItem = $this->fetch() )
			{
				print( $theSeparator ) ;
				$theSeparator = ',' ;
				print ( '"' . $theItem->{$aItemIdFieldName} . '":');
				if( method_exists( $theItem, 'printAsJson' ) )
					$theItem->printAsJson( $aEncodeOptions ) ;
				else if( method_exists( $theItem, 'toJson' ) )
					print( $theItem->toJson($aEncodeOptions) ) ;
				else
					print( json_encode( $theItem, $aEncodeOptions ) ) ;
			}
		}
		catch( Exception $x )
		{
			Strings::errorLog( __METHOD__ . ' failed: ' . $x->getMessage() );
			throw $x ;
		}
		return $this ;
	}

	/**
	 * Prints the entire data set to the output stream, item by item.
	 * @param string $aEncodeOptions options for `json_encode()`
	 * @return IteratedSet $this
	 */
	public function printAsJson( $aEncodeOptions=null )
	{
		if( ! empty($this->mDataSet) )
			if ( empty($this->mPrintAsJsonObjectWithIdKey ) )
				$this->printAsJsonArray($aEncodeOptions);
			else
				$this->printAsJsonObjectWithIdKey( $this->mPrintAsJsonObjectWithIdKey, $aEncodeOptions );
		return $this ;
	}

} // end class

} // end namespace
