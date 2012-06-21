<?php
namespace com\blackmoonit;
{//begin namespace

/**
 * Since PHP still does not support try-finally, emulate it with RAII.<br />
 * All exceptions will be eaten, if you want to throw one, return the $e var (try-catch: return new \Exception();)
 * Please note that this finally only occurs after the function/method exits.
 * Example useage:
 * function do_something() {
 *     mysql_query("LOCK TABLES mytable WRITE");
 *     $myFinally = new FinallyBlock(function($obj,$param2) {
 *         mysql_query("UNLOCK TABLES");
 *         $obj->finish($param2);
 *     },$anObject,$theParam2);
 *     try {
 *         // ... do queries here
 *     }
 * }
 */
class FinallyBlock {
	private $callback;
	private $args = array();

	function __construct($callback) {
		$this->callback = $callback;
		$numArgs = func_num_args();
		for ($i=1; $i<$numArgs; $i++) {
			$this->args[] = func_get_arg($i);
		}
	}

	function __destruct() {
		$eErr = null;
		if (is_callable($this->callback)) try {
			$eErr = call_user_func_array($this->callback,$this->args);
		} catch (\Exception $e) {
			//eat all exceptions
		}
		if (!empty($eErr)) {
			throw $eErr;
		}
	}
}//end class

}//namespace