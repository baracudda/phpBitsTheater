<?php
namespace BitsTheater\res;
use BitsTheater\res\Resources as BaseResources;
{//begin namespace

class Website extends BaseResources {
	public $version_seq = 1;		//build number, inc if db models need updating, override this in descendant
	public $version = 'CHANGE ME';	//displayed version text, override this in descendant
	
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
			'bootstrap-icons' => '<a href="http://glyphicons.com/">Glyphicons</a>',
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

	/**
	 * Overall website feature ID will be assigned the namespace unless a string
	 * is explicity defined as "feature_id".
	 * @return string Returns defined website feature ID or default namespace one.
	 */
	public function getFeatureId() {
		if (!empty($this->feature_id)) {
			return $this->feature_id;
		} else {
			return substr(WEBAPP_NAMESPACE,0,-1).'/version';
		}
	}
	
	/**
	 * SetupDb defines the SeqNum being passed in, this converts it to a more conventional display.
	 * @param number $aSeqNum - SetupDb::FEATURE_VERSION_SEQ value.
	 * @return string Returns the version display fit for human consumption.
	 */
	public function getFrameworkVersion($aSeqNum) {
		switch(true) {
			case ($aSeqNum<1):
				return '2.4.9';
			case ($aSeqNum>=2):
				return '3.0.'.($aSeqNum-2);
		}//switch
	}
	
	/**
	 * Override this function if your website needs to do some updates that are not database related.
	 * @param number $aSeqNum
	 * @return Return true if successfull, else FALSE.
	 */
	public function updateVersion($aSeqNum) {
		return true;
	}
	
}//end class

}//end namespace
