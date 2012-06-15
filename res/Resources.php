<?php
namespace res;
use com\blackmoonit\AdamEve as BaseResources;
use app\IllegalArgumentException;
{//begin namespace

class Resources extends BaseResources {
	protected $_director;
	
	public function __construct($aDirector) {
		parent::__construct();
		$this->_director = $aDirector;
		$this->setup();
	}

	/**
	 * Descendants want to merge translated labels with their static definitions in ancestor.
	 */
	function res_array_merge(array &$res1, &$res2) {
		if (!is_array($res1)) {
			throw new IllegalArgumentException('res_array_merge requires first param to be an array');
		}
		if (!is_array($res2)) {
			$res1[] = $res2;
		}
		foreach ($res2 as $key => &$value) {
			if (is_string($key)) {
				if (is_array($value) && array_key_exists($key, $res1) && is_array($res1[$key])) {
					$this->res_array_merge($res1[$key],$value);
				} else {
					$res1[$key] = $value;
				}
			} else {
				$res1[] = $value;
			}
		}
	}
	
}//end class

}//end namespace
