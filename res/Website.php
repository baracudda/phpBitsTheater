<?php
namespace BitsTheater\res;
use BitsTheater\res\Resources as BaseResources;
{//begin namespace

class Website extends BaseResources {
	public $js_load_list; //defined in setup()
	public $css_load_list; //defined in setup()

	public $header_meta_title = 'BitsTheater';
	public $header_title = 'BitsTheater Microframework';
	public $header_subtitle = 'An ity-bity framework.';
	
	public $menu_home_label = 'Home';
	public $menu_home_subtext = '';
	
	public $list_patrons_html = array(
			'prime_investor' => '<a href="http://www.blackmoonit.com/">Blackmoon Info Tech Services</a>',
	);
	public $list_patrons_glue = ' &nbsp; | &nbsp; ';
	
	public $list_credits_html = array(
			'framework' => '<a target="_blank" href="https://github.com/baracudda/phpBitsTheater">BitsTheater framework by BITS</a>',
			'menu' => '<a target="_blank" href="http://apycom.com/">jQuery Menu by Apycom</a>',
			'icons' => '<a target="_blank" href="http://tango.freedesktop.org/Tango_Desktop_Project">Some icons by the Tango Project</a>',
	);
	public $list_credits_glue = ' &nbsp; | &nbsp; ';
	
	
	/**
	 * Some resources need to be initialized by running code rather than a static definition.
	 * @see \BitsTheater\res\Resources::setup()
	 */
	public function setup($aDirector) {
		parent::setup($aDirector);

		//NULL path means use default lib path
		$this->js_load_list = array(
				//minification from http://www.jsmini.com/ using Basic and no jquery included.
				'com/blackmoonit/jBits/jbits_mini.js' => null,
				//  !-remove the below space and comment out the above line to debug un-minified JS code
				/* * /
				'com/blackmoonit/jBits/BasicObj.js' => null,
				'com/blackmoonit/jBits/AjaxDataUpdater.js' => null,
				/* end of jBits JS */
		);
		
		//NULL path means use default lib path path
		$this->css_load_list = array(
				'bits.css' => BITS_RES.'/style',
		);
	}
	
}//end class

}//end namespace
