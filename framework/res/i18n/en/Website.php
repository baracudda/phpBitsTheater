<?php
namespace BitsTheater\res\en;
use BitsTheater\res\Website as BaseResources;
use com\blackmoonit\Strings;
{//begin namespace

class Website extends BaseResources {
	
	public $header_title = 'My Web Site';
	public $header_subtitle = 'where dreams come true';
	
	public $menu_home_label = 'Home';
	public $menu_home_subtext = '';
	
	/**
	 * Some resources need to be initialized by running code rather than a static definition.
	 * @see \BitsTheater\res\Website::setup()
	 */
	public function setup($aDirector) {
		parent::setup($aDirector);
		
		if (empty($this->header_meta_title)) {
			$this->header_meta_title = 'KEVIN';
		}
	}

}//end class

}//end namespace
