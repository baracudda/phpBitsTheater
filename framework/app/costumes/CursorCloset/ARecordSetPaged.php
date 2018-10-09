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

namespace BitsTheater\costumes\CursorCloset;
use BitsTheater\costumes\WornForPagerManagement;
{//namespace begin

/**
 * Acts as a container for, and iterator over, a set of paged records.
 * Created a new class to descend from in case existing ARecordSet descendants
 * already implement WornForPagerManagement trait. Reason is that trait defines
 * some properties and if a descendant also defines that trait, PHP throws
 * an error about having properties already defined in it. By creating a new
 * class to descend from, classes can choose whether or not they inherit
 * the trait and therefore we avoid breaking existing code.
 *
 * <pre>
 * $theSet = RecordSet::create($this->getDirector())
 *     ->setDataFromPDO($pdo)
 *     ;
 * </pre>
 *
 * @since BitsTheater v4.1.0
 */
abstract class ARecordSetPaged extends ARecordSet
{
	use WornForPagerManagement {
		setPagerEnabled as protected traitSetPagerEnabled;
		setPagerTotalRowCount as protected traitSetPagerTotalRowCount;
	}
	
	/**
	 * Enable/disable the pager.
	 * @param boolean $aEnabled - desired state of pager.
	 * @return $this Returns $this for chaining.
	 */
	public function setPagerEnabled( $aEnabled )
	{
		$this->traitSetPagerEnabled($aEnabled);
		if ( !$this->isPagerEnabled() )
			$this->total_count = null;
		return $this;
	}
	
	/**
	 * Set the query total regardless of paging.
	 * @param number $aTotalRowCount - the total.
	 * @return $this Returns $this for chaining.
	 */
	public function setPagerTotalRowCount( $aTotalRowCount )
	{
		$this->traitSetPagerTotalRowCount($aTotalRowCount);
		$this->total_count = $this->getPagerTotalRowCount();
		return $this;
	}
	
}//end class

}//end namespace
