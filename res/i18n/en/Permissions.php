<?php
namespace BitsTheater\res\en;
use BitsTheater\res\en\CorePermissions as BaseResources;
use BitsTheater\costumes\EnumResEntry;
{//begin namespace

class Permissions extends BaseResources {
	
	public $label_my_namespaces = array(
		'ns_a' => 'My WebApp Right Group A',
		'ns_b' => 'My WebApp Right Group B',
		'ns_c' => 'My WebApp Right Group C',
	);
	public $desc_my_namespaces = array(
		'ns_a' => 'Widget Area 1 Activities',
		'ns_b' => 'ACME Spanner Activities',
		'ns_c' => 'ACME API',
	);
	
	public $label_ns_a = array(
		'view' => 'View A',
		'modify' => 'Modify A',
		'view_more_custom1' => 'View Sub-A Stuff',
		'send_stuff_custom2' => 'Send Message To A',
	);
	public $desc_ns_a = array(
		'view' => 'View the A entries.',
		'modify' => 'Add and modify the A entries.',
		'view_more_custom1' => 'View child entries of A.',
		'send_stuff_custom2' => 'Send a short message to A.',
	);
	
	public $label_ns_b = array(
		'view' => 'Right to View',
		'add' => 'Right to Add',
		'modify' => 'Right to Change',
		'delete' => 'Right to Remove',
		'copy' => 'Right to Copy',
	);
	public $desc_ns_b = array(
		'view' => 'View ACME spanners.',
		'add' => 'Add ACME spanners.',
		'modify' => 'Change ACME spanners.',
		'delete' => 'Remove spanners.',
		'copy' => 'Copy a spanner.',
	);
	
	public $label_ns_c = array(
		'access' => 'Access the Data API',
	);
	public $desc_ns_c = array(
		'access' => 'Allows a user to interact with the cloud data.',
	);
	
	/**
	 * Some resources need to be initialized by running code rather than a static definition.
	 */
	public function setup($aDirector) {
		$this->res_array_merge($this->label_namespace, $this->label_my_namespaces);
		$this->res_array_merge($this->desc_namespace, $this->desc_my_namespaces);
		//parent can handle the rest once "*_namespace" is updated
		parent::setup($aDirector);
	}
			
}//end class

}//end namespace
