<?php
namespace BitsTheater\res;
use BitsTheater\res\BitsWebsite as BaseResources;
{//begin namespace

class Website extends BaseResources
{
	public $feature_id = WEBAPP_NAMESPACE . ': website'; //DO NOT TRANSLATE!
	public $version_seq = 1;	//build number, inc'ing by 10 in dev, 1 in hot-fixes
	public $version = '1.0.0';	//displayed version text
	public $api_version_seq = 1;    //api version number, inc if Actor methods change to force other apps to update
	
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
		
		//NULL path means use default lib path
		$this->res_array_merge($this->js_load_list, array(
				//minification from http://www.jsmini.com/ using Basic and no jquery included.
				'webapp_mini.js' => WEBAPP_JS_URL,
				//  !-remove the below space and comment out the above line to debug un-minified JS code
				/* * /
				'webapp.js' => WEBAPP_JS_URL,
				'BitsRightGroups.js' => WEBAPP_JS_URL,
				'BitsAuthBasicAccounts.js' => WEBAPP_JS_URL,
				//'AnotherFile.js' => WEBAPP_JS_URL,
				/* end of webapp JS */
		));

		//$this->res_array_merge($this->list_credits_html, $this->list_credits_html_more);
	}

	/**
	 * Override this function if your website needs to do some updates that are not database related.
	 * Throw an exception if your update did not succeed.
	 * @param number $aSeqNum - the version sequence number (<= what is defined in your overriden Website class).
	 * @throws \Exception on failure.
	 */
	public function updateVersion($aSeqNum) {
		try {
			//nothing to do, yet
		} catch (\Exception $e) {
			//throw exception if your update code fails (logging it would be a good idea, too).
			$this->errorLog(__METHOD__.' '.$e->getMessage());
			throw $e;
		}
	}

}//end class

}//end namespace
