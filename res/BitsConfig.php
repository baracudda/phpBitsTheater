<?php
namespace BitsTheater\res;
use BitsTheater\res\Resources as BaseResources;
{//begin namespace

class BitsConfig extends BaseResources {

	public $enum_namespace = array(
			'site',
			'auth',
	);
			
	public $enum_site = array(
			'mode',
	);
	
	public $enum_auth = array(
			'register_url',
			'login_url',
			'logout_url',
	);
	
	/**
	 * Some resources need to be initialized by running code rather than a static definition.
	 * Merging Enums with their UI counterparts is common.
	 */
	public function setup($aDirector) {
		parent::setup($aDirector);

		$this->mergeEnumEntryInfo('namespace');
		foreach ($this->namespace as $theEnumName => $theEnumEntry) {
			$this->mergeConfigEntryInfo($theEnumName);
		}
	}
	
}//end class

}//end namespace
