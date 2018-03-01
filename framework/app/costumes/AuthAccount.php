<?php
namespace BitsTheater\costumes;
use BitsTheater\costumes\CursorCloset\AuthAcct4Orgs as BaseCostume;
{//namespace begin

class AuthAccount extends BaseCostume
{
	/** @var string My fully qualified classname. */
	const ITEM_CLASS = __CLASS__;

	//add the possibly mapped IMEI
	public $mapped_imei;
	
	/**
	 * Construct the standard object with all data fields worth exporting defined.
	 * @return object Returns a standard object with the properties to export defined.
	 */
	protected function constructExportObject()
	{
		$o = parent::constructExportObject();
		if (property_exists($o, 'hardware_ids'))
			$o->mapped_imei = $o->hardware_ids;
		return $o;
	}
	
}//end class
	
}//end namespace
