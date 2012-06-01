<?php
namespace com\blackmoonit;
{//begin namespace

/**
 * Since PHP still does not support try-finally, emulate it with RAII.<br />
 * Please note that this finally only occurs after the function/method exits.
 * Example useage:
 * function do_something() {
 *     mysql_query("LOCK TABLES mytable WRITE");
 *     $myFinally = new FinallyBlock(function() {
 *         mysql_query("UNLOCK TABLES");
 *     });
 *     try {
 *         // ... do queries here
 *     }
 * }
 */
class FinallyBlock {
	private $callback;

	function __construct($callback) {
		$this->callback = $callback;
	}

	function __destruct() {
		if (is_callable($this->callback)) {
			call_user_func($this->callback);
		}
	}
}//end class

}//namespace