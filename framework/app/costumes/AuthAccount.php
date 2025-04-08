<?php
namespace BitsTheater\costumes;
use BitsTheater\costumes\CursorCloset\AuthOrgsBase as BaseCostume;
{//namespace begin

class AuthAccount extends BaseCostume
{
	/** @var string My fully qualified classname. */
	const ITEM_CLASS = __CLASS__;

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
	 * What fields are text searchable?
	 * @return string[] Returns the list of searchable fields.
	 */
	static public function getSearchFieldList()
	{
		return array_diff(parent::getSearchFieldList(), array(
				'created_by', //we do not display this field (yet?)
				'updated_by', //we do not display this field (yet?)
		));
	}
	
}//end class

}//end namespace
