<?php
/*
 * Copyright (C) 2018 Blackmoon Info Tech Services
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

namespace BitsTheater\costumes\Wardrobe;
{//namespace begin

/**
 * Large Database result sets should be broken up with a pager so that data
 * can be retrieved in chunks.
 * @since BitsTheater [NEXT]
 */
trait WornForPagerManagement
{
	/** @var boolean If TRUE, the pager is enabled and should be respected. */
	protected $mPagerEnabled = true;
	
	/**
	 * Check to see if the pager is enabled.
	 * @return boolean - returns the state of the pager.
	 */
	public function isPagerEnabled()
	{ return $this->mPagerEnabled; }
	
	/**
	 * Enable/disable the pager.
	 * @param boolean $aEnabled - desired state of pager.
	 * @return $this Returns $this for chaining.
	 */
	public function setPagerEnabled( $aEnabled )
	{
		$this->mPagerEnabled = filter_var($aEnabled, FILTER_VALIDATE_BOOLEAN);
		return $this;
	}
	
	/** @var int Maximum pager size. */
	protected $mPagerSizeMax = 1000;

	/**
	 * @return number Returns the maximum pager size.
	 */
	public function getPagerSizeMax()
	{ return $this->mPagerSizeMax; }
	
	/**
	 * Set the maximum page size.
	 * @param int $aSize - the new max size.
	 * @return $this Returns $this for chaining.
	 */
	public function setPagerSizeMax( $aSize )
	{
		// protect against negative numbers/overflow
		$this->mPagerSizeMax = max($aSize, 1);
		return $this;
	}
	
	/** @var int The pager page size. */
	protected $mPagerSize = 25;
	
	/** @return number Returns the pager page size. */
	public function getPagerSize()
	{ return $this->mPagerSize; }
	
	/**
	 * Set the pager page size.
	 * @param int $aSize - the new page size.
	 * @return $this Returns $this for chaining.
	 */
	public function setPagerSize( $aSize )
	{
		$this->mPagerSize = min(
				max($aSize, 1), $this->getPagerSizeMax()
		);
		return $this;
	}
	
	/** @var int Total count for query if paging was absent. */
	protected $mPagerTotalRowCount = 0;

	/**
	 * @return number Returns the total count for query
	 *   regardless of paging.
	 */
	public function getPagerTotalRowCount()
	{ return $this->mPagerTotalRowCount; }
	
	/**
	 * Set the query total regardless of paging.
	 * @param number $aTotalRowCount - the total.
	 * @return $this Returns $this for chaining.
	 */
	public function setPagerTotalRowCount( $aTotalRowCount )
	{
		// protect against negative numbers/overflow
		$this->mPagerTotalRowCount = max($aTotalRowCount, 0);
		return $this;
	}
	
	/** @var int The max num pages to handle. */
	protected $mPagerMaxNumPages = 100000;
	
	/**
	 * @return number Returns the maximum number of pages.
	 */
	public function getPagerMaxNumPages()
	{ return $this->mPagerMaxNumPages; }
	
	/**
	 * Set the maximum number of pages to handle.
	 * @param int $aSize - the new max size.
	 * @return $this Returns $this for chaining.
	 */
	public function setPagerMaxNumPages( $aSize )
	{
		// protect against negative numbers/overflow
		$this->mPagerMaxNumPages = max($aSize, 1);
		return $this;
	}
	
	/** @var int The current page number. */
	protected $mPagerCurrentPage = 1;
	
	/**
	 * @return int Returns the current pager page.
	 */
	public function getPagerCurrentPage()
	{ return $this->mPagerCurrentPage; }
	
	/**
	 * Sets the current page.
	 * @param int $aPageNum - the current page num.
	 * @return $this Returns $this for chaining.
	 */
	public function setPagerCurrentPage( $aPageNum )
	{
		if ( !empty($aPageNum) && is_numeric($aPageNum) )
			$this->mPagerCurrentPage = max($aPageNum, 1);
		return $this;
	}
	
	/**
	 * Get the defined maximum row count for a page in a pager.
	 * @return int Returns the max number of rows shown for a single pager page;
	 *   guaranteed to be >=1 unless the pager is disabled, which returns 0.
	 */
	public function getPagerPageSize()
	{
		if ( !$this->isPagerEnabled() )
			return 0;
		else
			return $this->getPagerSize();
	}
	
	/**
	 * @return int Return the SQL query offset based on pager page size and
	 *   current page.
	 */
	public function getPagerQueryOffset()
	{ return ($this->getPagerCurrentPage()-1) * $this->getPagerPageSize(); }
	
	/**
	 * Initialize our data based on vars found in a Scene.
	 * @param object $aScene - the scene object we are pulling data from.
	 * @return $this Returns $this for chaining.
	 */
	public function setupPagerDataFromUserData( $aScene )
	{
		// see if we have a defined page size
		if ( !empty($aScene->query_limit) && is_numeric($aScene->query_limit) )
		{ $this->setPagerPageSize($aScene->query_limit); }
		else if ( !empty($aScene->pagesz ) && is_numeric( $aScene->pagesz) )
		{ $this->setPagerPageSize($aScene->pagesz); }
		// see if we have a defined current page, explicit or via offset
		if ( $this->getPagerSize() > 0 ) {
			if ( !empty($aScene->query_offset) && is_numeric($aScene->query_offset) )
				$this->setPagerCurrentPage(
						($aScene->query_offset / $this->getPagerSize()) + 1
				);
		}
		if ( !empty($aScene->page) && is_numeric($aScene->page) )
		{ $this->setPagerCurrentPage($aScene->page); }
		return $this;
	}
	
}//end class

}//end namespace
