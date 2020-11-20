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
	 * Sometimes you need to debug protected properties as well as public.
	 * @return array Returns the public/protected fields for debugging.
	 * /
	public function __debugInfo() {
		$theResult = array();
		$o = new \ReflectionClass($this);
		$theList = $o->getProperties(\ReflectionProperty::IS_PUBLIC | \ReflectionProperty::IS_PROTECTED);
		foreach( $theList as $theProp ) {
			if ( !in_array($theProp->name, array(
					'dbAuth', 'dbAuthGroups', 'dbModel', '$mapInfoFields', 'mapInfoFields'
			)) ) {
				$theResult[$theProp->name] = $this->{$theProp->name};
			}
		}
		return $theResult;
	}
	/**/
	
	/**
	 * Return the list of fields to restrict export to use given a list
	 * of fields and shorthand meta names or flags.
	 * @param string[] $aMetaFieldList - the field/meta name list.
	 * @return string[] Returns the export field name list to use.
	 */
	static public function getExportFieldListUsingShorthand( $aMetaFieldList )
	{
		$theFieldList = parent::getExportFieldListUsingShorthand($aMetaFieldList);
		if ( !empty($theFieldList) ) {
			//ensure both fields exists in our export list as they are aliases of one another
			if ( in_array('mapped_imei', $theFieldList) && !in_array('hardware_ids', $theFieldList) ) {
				$theFieldList[] = 'hardware_ids';
			}
			if ( in_array('hardware_ids', $theFieldList) && !in_array('mapped_imei', $theFieldList) ) {
				$theFieldList[] = 'mapped_imei';
			}
		}
		return $theFieldList;
	}
	
	/**
	 * Construct the standard object with all data fields worth exporting defined.
	 * @return object Returns a standard object with the properties to export defined.
	 */
	protected function constructExportObject()
	{
		$o = parent::constructExportObject();
		if ( isset($o->hardware_ids) ) {
			$o->mapped_imei = $o->hardware_ids;
		}
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
