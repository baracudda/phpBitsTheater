<?php
namespace BitsTheater\res\en ;
use BitsTheater\Director ;
use BitsTheater\costumes\AccountPrefSpec ;
use BitsTheater\res\en\BitsAccountPrefs as BaseResources ;
{

class AccountPrefs extends BaseResources
{
	public $label_app_namespaces = array(
			'novice_mode' => 'Novice Mode',
		);
	
	public $desc_app_namespaces = array(
			'novice_mode' => 'Preferences for working in "novice mode".',
		);
	
	public $label_novice_mode = array(
			'enabled' => 'Enabled',
		);
	
	public $desc_novice_mode = array(
			'enabled' => 'Enables or disables novice mode.',
		);
	
	public $schema_novice_mode = array(
			'enabled' => array(
					'type' => AccountPrefSpec::TYPE_BOOLEAN,
					'default_value' => false
				),
		);
	
	/**
	 * {@inheritDoc}
	 * @see \BitsTheater\res\en\BitsAccountPrefs::setup($aDirector)
	 */
	public function setup( Director $aDirector )
	{
		parent::setup($aDirector) ;
	}
}

}