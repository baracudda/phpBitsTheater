<?php
namespace BitsTheater\res\en;
use BitsTheater\res\Website as BaseResources;
{//begin namespace

class Website extends BaseResources {
	//public $feature_id = 'my namespace: website';
	public $version_seq = 1;	//build number, inc'ing by 10 in dev, 1 in hot-fixes
	public $version = '1.0.0';	//displayed version text
	
	//public $header_meta_title = 'mywebsite tab label';
	//public $header_title = 'My Web Site';
	//public $header_subtitle = 'where dreams come true';
	
	public $list_patrons_html = array(
			'prime_investor' => '<a href="http://www.example.com/">My Company, LLC.</a>',
	);
	
	//public $list_credits_html_more = array(
	//);
	
	/**
	 * Some resources need to be initialized by running code rather than a static definition.
	 * @see \BitsTheater\res\Website::setup()
	 */
	public function setup($aDirector) {
		parent::setup($aDirector);

		//default page tab label is virtual host, but can be static or whatever you desire.
		if (!empty(VIRTUAL_HOST_NAME))
			$this->header_meta_title = VIRTUAL_HOST_NAME;

		//NULL path means use default lib path
		$this->res_array_merge($this->js_load_list, array(
				//minification from http://www.jsmini.com/ using Basic and no jquery included.
				'webapp_mini.js' => WEBAPP_JS_URL,
				//  !-remove the below space and comment out the above line to debug un-minified JS code
				/* * /
				'webapp.js' => WEBAPP_JS_URL,
				'BitsRightGroups.js' => WEBAPP_JS_URL,
				//'AnotherFile.js' => WEBAPP_JS_URL,
				/* end of webapp JS */
		));
		
		//NULL path means use default lib path path
		$this->res_array_merge($this->css_load_list, array(
				'joka.css' => BITS_RES.'/style',
		));
		
		//$this->res_array_merge($this->list_credits_html, $this->list_credits_html_more);
	}

	/**
	 * Override this function if your website needs to do some updates that are not database related.
	 * Throw an exception if your update did not succeed.
	 * @param number $aSeqNum - the version sequence number (<= what is defined in your overriden Website class).
	 * @throws Exception on failure.
	 */
	public function updateVersion($aSeqNum) {
		try {
			//nothing to do, yet
		} catch (Exception $e) {
			$this->debugLog($e->getMessage());
		}
	}

}//end class

}//end namespace
