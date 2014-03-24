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

/*
 * You are highly encouraged to find/replace this namespace with your own. (search for *.php and *.tpl)
 */
namespace com\blackmoonit\bits_theater;
{//namespace begin

/**********************************
 * define required CONSTANTS
 **********************************/
//namespaces
define('BITS_NAMESPACE_BASE',__NAMESPACE__);
define('BITS_NAMESPACE_APP',BITS_NAMESPACE_BASE.'\\app\\');
define('BITS_NAMESPACE_RES',BITS_NAMESPACE_BASE.'\\res\\');
define('BITS_NAMESPACE_ACTOR',BITS_NAMESPACE_APP.'actor\\');
define('BITS_NAMESPACE_CFG',BITS_NAMESPACE_APP.'config\\');
define('BITS_NAMESPACE_MODEL',BITS_NAMESPACE_APP.'model\\');
define('BITS_NAMESPACE_SCENE',BITS_NAMESPACE_APP.'scene\\');

//paths
define('¦',DIRECTORY_SEPARATOR);
define('BITS_ROOT',dirname(__FILE__));
define('BITS_PATH',BITS_ROOT.¦);
define('BITS_LIB_PATH',BITS_PATH.'lib'.¦);
define('BITS_RES_PATH',BITS_PATH.'res'.¦);
define('BITS_APP_PATH',BITS_PATH.'app'.¦);
define('BITS_CFG_PATH',BITS_APP_PATH.'config'.¦.$_SERVER["SERVER_NAME"].¦);
define('WEBAPP_PATH', BITS_APP_PATH);

//domain url
define('SERVER_URL',((array_key_exists('HTTPS',$_SERVER) && $_SERVER['HTTPS']=='on')?'https':'http').'://'.$_SERVER['SERVER_NAME'].
		(($_SERVER["SERVER_PORT"]=="80" || $_SERVER["SERVER_PORT"]=="443")?'':':'.$_SERVER["SERVER_PORT"]).'/');
//relative urls
define('REQUEST_URL',array_key_exists('url',$_GET)?$_GET['url']:'');
define('BITS_URL',dirname($_SERVER['SCRIPT_NAME']));
define('BITS_RES',BITS_URL.'/res');
define('BITS_LIB',BITS_URL.'/lib');
//no need for app url as that is where all the urls normally get routed towards.
define('WEBAPP_JS_URL', 'app/js');



/**********************************
 * load required modules
 **********************************/
//lib autoloader first
require_once(BITS_LIB_PATH.'autoloader.php');
//master autoloader
require_once(BITS_PATH.'autoloader.php');
//now let us get on with it
require_once('router.php');

}//end namespace
