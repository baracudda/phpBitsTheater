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
	 * Set the list of fields to restrict export to use.
	 * @param string[] $aFieldList - the field list.
	 * @return $this Returns $this for chaining.
	 */
	public function setExportFieldList( $aFieldList )
	{
		parent::setExportFieldList($aFieldList);
		$theFieldList = $this->getExportFieldList();
		if ( !empty($theFieldList) ) {
			if ( in_array('mapped_imei', $theFieldList) &&
					!in_array('hardware_ids', $theFieldList) ) {
				$theFieldList[] = 'hardware_ids';
				$this->setExportFieldList($theFieldList);
			}
			else if ( in_array('hardware_ids', $theFieldList) &&
					!in_array('mapped_imei', $theFieldList) ) {
				$theFieldList[] = 'mapped_imei';
				$this->setExportFieldList($theFieldList);
			}
		}
	}
	
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
		return array_merge(array_diff(parent::getSearchFieldList(), array(
				'created_by', //we do not display this field (yet?)
				'updated_by', //we do not display this field (yet?)
		)), array(
				'mapped_imei',
		));
	}
	
}//end class

}//end namespace
