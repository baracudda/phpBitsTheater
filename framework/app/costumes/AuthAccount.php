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
	
	/**
	 * Event called after fetching data from db and setting all our properties.
	 */
	public function onFetch()
	{
		parent::onFetch();
		if ( !empty($this->hardware_ids) && empty($this->mapped_imei) )
		{ $this->mapped_imei = $this->hardware_ids; }
	}
	
	/**
	 * What fields are text searchable?
	 * @return string[] Returns the list of searchable fields.
	 */
	static public function getSearchFieldList()
	{
		return array_merge(parent::getSearchFieldList(), array(
				'mapped_imei',
		));
	}
	
}//end class

}//end namespace
