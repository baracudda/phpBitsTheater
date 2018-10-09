<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
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

namespace com\blackmoonit;
{//begin namespace

/**
 * Since PHP still does not support try-finally, emulate it with RAII.<br />
 * All exceptions will be eaten, if you want to throw one, return the $e var
 * (try-catch: return new \Exception();)
 * Please note that this finally only occurs after the function/method exits.
 * Example useage:<pre>
 * function do_something() {
 *     mysql_query("LOCK TABLES mytable WRITE");
 *     $myFinally = new FinallyBlock(function($obj,$param2) {
 *         mysql_query("UNLOCK TABLES");
 *         $obj->finish($param2);
 *     },$anObject,$theParam2);
 *     try {
 *         // ... do queries here
 *     }
 * }</pre>
 */
class FinallyBlock {
	private $callback;
	private $args = array();

	function __construct($callback) {
		$this->callback = $callback;
		$this->args = func_get_args();
		array_shift($this->args);
	}

	function __destruct() {
		$eErr = null;
		if (is_callable($this->callback)) try {
			$eErr = call_user_func_array($this->callback, $this->args);
		} catch (\Exception $e) {
			//eat all exceptions
		}
		if (!empty($eErr)) {
			throw $eErr;
		}
	}
	
	/**
	 * Update the arguments given during object construction so that when
	 * the FinallyBlock does execute, it uses these updated parameters.
	 */
	public function updateArgs() {
		$this->args = func_get_args();
	}
	
	/**
	 * Construct a closeCursor FinallyBlock for PDOStatements.
	 * @param \PDOStatement $aPdoStatement - the PDOStatement to close.
	 * @return $this Returns the new object created.
	 */
	static public function forDbCursor(&$aPdoStatement) {
		return new self(__NAMESPACE__ .'\FinallyBlock::closeCursor',$aPdoStatement);
	}
	
	/**
	 * Closes a PDOStatement.
	 * @param \PDOStatement $aPdoStatement - the PDOStatement to close.
	 */
	static public function closeCursor(&$aPdoStatement=null) {
		if ($aPdoStatement) {
			$aPdoStatement->closeCursor();
		}
	}
	
}//end class

}//namespace
