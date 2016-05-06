<?php
/*
 * Copyright (C) 2015 Blackmoon Info Tech Services
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *      http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace BitsTheater\res;
use BitsTheater\res\Resources as BaseResources;
{//begin namespace

class BitsWebsite extends BaseResources {
	/**
	 * Your website's build number.
	 * This should only ever increase with each release you make.
	 * Override this value in your descendant class.
	 * @var number
	 */
	public $version_seq = 0;
	/**
	 * The displayed version text.
	 * Override this value in your descendant class.
	 * @var string
	 */
	public $version = 'CHANGE ME';
	/**
	 * Your API version number, inc if Actor methods change to force mobile apps to update.
	 * This should only ever increase with each release you make.
	 * Override this value in your descendant class.
	 * @var number
	 */
	public $api_version_seq = 0;
	
	/**
	 * CSS files to load with every webpage.
	 * @var array
	 */
	public $css_load_list; //defined in setup()
	/**
	 * JavaScript libraries to load with every webpage.
	 * @var array
	 */
	public $js_libs_load_list; //defined in setup()
	/**
	 * Custom JavaScript files to load with every webpage.
	 * @var array
	 */
	public $js_load_list; //defined in setup()

	/**
	 * Not displayed on the page, but browsers may display in title bar or tab.
	 * Overridden in the i18n classes.
	 * @var string
	 */
	public $header_meta_title = 'BitsTheater';
	/**
	 * Title of website is shown promiently.
	 * Overridden in the i18n classes.
	 * @var string
	 */
	public $header_title = 'BitsTheater Microframework';
	/**
	 * Subtitle of website is shown less promiently, usually underneath the {@link BitsWebsite::header_title}.
	 * Overridden in the i18n classes.
	 * @var string
	 */
	public $header_subtitle = 'An ity-bity framework.';
	/**
	 * Icon to display near the {@link BitsWebsite::$header_title}.
	 * @var image
	 */
	public $site_logo = 'site_logo.png';
	/**
	 * The home menu item label.
	 * Overridden in the i18n classes.
	 * @var string
	 */
	public $menu_home_label = 'Home';
	/**
	 * The home menu item subtext (appears under label, if at all).
	 * Overridden in the i18n classes.
	 * @var string
	 */
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
	 * Magic PHP method to limit what var_dump() shows.
	 */
	public function __debugInfo() {
		$vars = parent::__debugInfo();
		unset($vars['css_load_list']);
		unset($vars['js_libs_load_list']);
		unset($vars['js_load_list']);
		unset($vars['list_patrons_html']);
		unset($vars['list_patrons_glue']);
		unset($vars['list_credits_html']);
		unset($vars['list_credits_glue']);
		return $vars;
	}
	
	/**
	 * Some resources need to be initialized by running code rather than a static definition.
	 * @see \BitsTheater\res\Resources::setup()
	 */
	public function setup($aDirector) {
		parent::setup($aDirector);
		
		//default page tab label is virtual host, but can be static or whatever you desire.
		if (VIRTUAL_HOST_NAME) {
			$this->header_meta_title = VIRTUAL_HOST_NAME;
		}
		
		//NULL path means use default lib path path
		$this->css_load_list = array(
				'bootstrap/css/bootstrap.css' => null,
				'apycom/menu.css' => null,
				'bits.css' => BITS_RES.'/style',
		);
		// external libs
		$this->js_libs_load_list = array(
				'jquery/jquery.min.js',
				'bootstrap/js/bootstrap.min.js', //bootstrap needs to be after jQuery
				'bootbox/bootbox.js',
				
				//apycom menu (needs to be after jQuery, else use the jquery sublib)
				//'apycom/jquery.js', //do not need if already using jQuery
				'apycom/menu.js',
		
				//minification from http://www.jsmini.com/ using Basic and no jquery included.
				'com/blackmoonit/jBits/jbits_mini.js',
				//  !-remove the below space and comment out the above line to debug un-minified JS code
				/* * /
				'com/blackmoonit/jBits/BasicObj.js',
				'com/blackmoonit/jBits/AjaxDataUpdater.js',
				/* end of jBits JS */
		);
		
		//NULL path means use default lib path
		$this->js_load_list = array(
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
			return substr(WEBAPP_NAMESPACE,0,-1).'/website';
		}
	}
	
	/**
	 * SetupDb defines the SeqNum being passed in, this converts it to a more conventional display.
	 * @param number $aSeqNum - SetupDb::FEATURE_VERSION_SEQ value.
	 * @return string Returns the version display fit for human consumption.
	 */
	public function getFrameworkVersion($aSeqNum) {
		switch(true) {
			case ($aSeqNum<2):
				return '2.4.9';
			case ($aSeqNum<3):
				return '3.0.0';
			case ($aSeqNum<6):
				return '3.1.'.($aSeqNum-3);
			case ($aSeqNum<7):
				return '3.2.'.($aSeqNum-6);
			default:
				return '3.4.5';
		}//switch
	}
	
	/**
	 * Override this function if your website needs to do some updates that are not database related.
	 * Throw an exception if your update did not succeed.
	 * @param number $aSeqNum - the version sequence number (<= what is defined in your overriden Website class).
	 * @throws Exception on failure.
	 */
	public function updateVersion($aSeqNum) {
		//NO NEED TO CALL PARENT! (this class)
		try {
			switch (true) {
			//cases should always be lo->hi, never use break; so all changes are done in order.
			case ($theSeq<2):
				//do your stuff here
			}//end switch
		} catch (Exception $e) {
			//throw expection if your update code fails (logging it would be a good idea, too).
			$this->debugLog(__METHOD__.' '.$e->getMessage());
			throw $e;
		}
	}
	
	/**
	 * Base the site_logo file to use on the VIRTUAL_HOST_NAME constant.
	 * @return string Returns the "src" attribute value for an "img" HTML tag.
	 */
	public function site_logo_src() {
		return $this->imgsrc($this->site_logo);
	}

}//end class

}//end namespace
