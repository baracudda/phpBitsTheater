<?php
namespace BitsTheater\res;
use BitsTheater\res\BitsPermissions as BaseResources;
{//begin namespace

class Permissions extends BaseResources {

	public $enum_my_namespaces = array(
			'ns_a',
			'ns_b',
			'ns_c',
	);
	
	public $enum_ns_a = array(
			'view',
			'modify',
			'view_more_custom1',
			'send_stuff_custom2',
	);
	
	public $enum_ns_b = array(
			'view',
			'add',
			'modify',
			'delete',
			'copy',
	);
	
	public $enum_ns_c = array(
			'access',
	);
	
	/**
	 * Some resources need to be initialized by running code rather than a static definition.
	 */
	public function setup($aDirector) {
		$this->res_array_merge($this->enum_namespace, $this->enum_my_namespaces);
		//parent can handle the rest once "enum_namespace" is updated
		parent::setup($aDirector);
	}
	
}//end class

}//end namespace
