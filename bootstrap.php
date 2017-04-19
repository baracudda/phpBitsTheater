<?php
/*
 * Copyright (C) 2012 Blackmoon Info Tech Services
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

namespace BitsTheater;
{//namespace begin

/**********************************
 * define required CONSTANTS
 **********************************/

//namespaces
define('BITS_NAMESPACE',__NAMESPACE__.'\\');
define('BITS_NAMESPACE_RES',BITS_NAMESPACE.'res\\');
define('BITS_NAMESPACE_ACTORS',BITS_NAMESPACE.'actors\\');
define('BITS_NAMESPACE_CFGS',BITS_NAMESPACE.'configs\\');
define('BITS_NAMESPACE_MODELS',BITS_NAMESPACE.'models\\');
define('BITS_NAMESPACE_SCENES',BITS_NAMESPACE.'scenes\\');

//paths
define('¦',DIRECTORY_SEPARATOR);
define('BITS_ROOT',dirname(__FILE__));
define('BITS_PATH',BITS_ROOT.¦);
define('BITS_LIB_PATH',BITS_PATH.'lib'.¦);
define('BITS_RES_PATH',BITS_PATH.'res'.¦);
define('BITS_APP_PATH',BITS_PATH.'app'.¦);
/**
 * If the webserver's domain is not available as a path in the configs
 * folder and "anyhost" does exist, use the "anyhost" config folder, else
 * use the default webserver's domain config path.  This allows for easier
 * setup as a part of a Docker container which frequently changes its domain.
 * @var string
 */
define('BITS_SERVER_NAME', (!empty($_SERVER['SERVER_NAME'])) ? $_SERVER['SERVER_NAME'] : 'localhost');
define('BITS_CONFIG_DIR', (file_exists(BITS_APP_PATH.'configs'))
		? BITS_APP_PATH.'configs'
		: BITS_PATH.'configs'
);
define('BITS_CFG_PATH', (!file_exists(BITS_CONFIG_DIR.¦.BITS_SERVER_NAME) && file_exists(BITS_CONFIG_DIR.¦.'anyhost'))
	? BITS_CONFIG_DIR.¦.'anyhost'.¦
	: BITS_CONFIG_DIR.¦.BITS_SERVER_NAME.¦
);
define('WEBAPP_PATH', BITS_APP_PATH);

//domain url
define('BITS_SERVER_PORT', (!empty($_SERVER['SERVER_PORT'])) ? $_SERVER['SERVER_PORT'] : 80);
define('SERVER_URL',((array_key_exists('HTTPS',$_SERVER) && $_SERVER['HTTPS']=='on')?'https':'http').'://'.BITS_SERVER_NAME.
		((BITS_SERVER_PORT==80 || BITS_SERVER_PORT==443) ? '' : ':'.BITS_SERVER_PORT));
//relative urls
/**
 * Current, site-relative URL.
 * @var string
 */
define('REQUEST_URL', array_key_exists('url',$_GET)?$_GET['url']:'');

/**
 * Site URL that does not end in a /.
 * @var string
 */
if (!defined('BITS_URL')) {
	$theScriptFolder = dirname($_SERVER['SCRIPT_NAME']);
	define('BITS_URL', ($theScriptFolder!='.' && $theScriptFolder!='/') ? str_replace(DIRECTORY_SEPARATOR,'/',$theScriptFolder) : '' );
}

/**
 * Virtual Host folder name, if exists.
 */
if (!defined('VIRTUAL_HOST_NAME'))
define('VIRTUAL_HOST_NAME', (strlen(BITS_URL)>0 && count($urlsegs=explode('/', BITS_URL))>1) ? $urlsegs[1] : '');

/**
 * Resource URL that does not end in a /.
 * @var string
 */
define('BITS_RES',BITS_URL.'/res');

/**
 * Library URL that does not end in a /.
 * @var string
 */
define('BITS_LIB',BITS_URL.'/lib');

//no need for app url as that is where all the urls normally get routed towards.

/**
 * Non-library JavaScript content for the website that does not end in a /.
 * @var string
 */
define('WEBAPP_JS_URL', BITS_URL.'/app/js');

$theCustomDefinesFile = BITS_PATH.'appdefines.php';
if (file_exists($theCustomDefinesFile)) {
	/*************************************************************************
	 * Special constants which may be defined by appdefines.php using the
	* BitsTheater namespace:
	*
	* define('WEBAPP_NAMESPACE','MyWebAppNamespace\\'); //always end with "\\"
	*
	*************************************************************************/
	include_once($theCustomDefinesFile);
	unset($theCustomDefinesFile);
}
if ( !defined('WEBAPP_NAMESPACE') )
	define('WEBAPP_NAMESPACE', BITS_NAMESPACE);

/**********************************
 * load required modules
 **********************************/
//lib autoloader first
require_once(BITS_LIB_PATH .'autoloader.php');
//master autoloader
require_once(BITS_PATH .'autoloader.php');
//phpmailer autoloader
require_once( BITS_LIB_PATH . 'phpmailer' . DIRECTORY_SEPARATOR
		. 'PHPMailer' . DIRECTORY_SEPARATOR . 'PHPMailerAutoload.php' ) ;

}//end namespace
