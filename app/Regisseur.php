<?php
/*
 * Copyright (C) 2017 Blackmoon Info Tech Services
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
{//begin namespace

class Regisseur
{
	/**
	 * If $_SERVER['SERVER_NAME'] is empty, use this value: 'localhost'.
	 * @var string
	 */
	const DEFAULT_SERVER_NAME = 'localhost';
	/**
	 * If the installer does not want different configs for each domain pointing to their
	 * website, use this folder name: 'anyhost'.
	 * @var string
	 */
	const CATCH_ALL_HOST_NAME = 'anyhost';
	
	/**
	 * Determine which Regisseur to create for the job.
	 * @return BitsTheater\Regisseur
	 */
	static public function requisition()
	{
		$theAppRegisseurFile = BITS_APP_PATH . 'AppRegisseur.php' ;
		if ( is_file($theAppRegisseurFile) && include_once($theAppRegisseurFile) )
		{
			$theClassName = WEBAPP_NAMESPACE . 'AppRegisseur';
			if ( class_exists($theClassName) )
				return new $theClassName() ;
			$theClassName = BITS_NAMESPACE . 'AppRegisseur';
			if ( class_exists($theClassName) )
				return new $theClassName() ;
		}
		return new Regisseur() ;
	}
	
	/**
	 * Determine if we are executing in CLI mode or not.
	 * @return boolean
	 */
	protected function isRunningUnderCLI()
	{
		return (php_sapi_name() === 'cli' OR defined('STDIN'));
	}

	/**
	 * Define Namespace constants that can be used throughout the site.
	 * @return $this Returns $this for chaining.
	 */
	protected function defineNamespaceConstants()
	{
		define('BITS_NAMESPACE', __NAMESPACE__ . '\\');
		define('BITS_NAMESPACE_RES', BITS_NAMESPACE . 'res\\');
		define('BITS_NAMESPACE_ACTORS', BITS_NAMESPACE . 'actors\\');
		define('BITS_NAMESPACE_CFGS', BITS_NAMESPACE . 'configs\\');
		define('BITS_NAMESPACE_MODELS', BITS_NAMESPACE . 'models\\');
		define('BITS_NAMESPACE_SCENES', BITS_NAMESPACE . 'scenes\\');
		return $this;
	}
	
	/**
	 * Define file path constants that can be used throughout the site.
	 * @return $this Returns $this for chaining.
	 */
	protected function defineFilePathConstants()
	{
		define('¦', DIRECTORY_SEPARATOR);
		define('BITS_ROOT', dirname(__DIR__));
		define('BITS_PATH', BITS_ROOT . ¦);
		define('BITS_LIB_PATH', BITS_PATH . 'lib' . ¦);
		define('BITS_RES_PATH', BITS_PATH . 'res' . ¦);
		define('BITS_APP_PATH', BITS_PATH . 'app' . ¦);
		define('BITS_CONFIG_DIR', (file_exists(BITS_APP_PATH.'configs'))
				? BITS_APP_PATH.'configs'
				: BITS_PATH.'configs'
		);
		define('WEBAPP_PATH', BITS_APP_PATH);
		if ( !empty($_SERVER['SERVER_NAME']))
		{ return $this->defineConfigPath($_SERVER['SERVER_NAME']); }
		else
		{
			$theFolderList = glob(BITS_CONFIG_DIR.¦.'*', GLOB_ONLYDIR);
			foreach ($theFolderList as $theFolderName) {
				if ($theFolderName!='localhost' || count($theFolderList)==1)
					return $this->defineConfigPath($theFolderName);
			}
			return $this->defineConfigPath( static::DEFAULT_SERVER_NAME );
		}
		return $this;
	}

	/**
	 * The website config path is special since it could be one of several.
	 * @param string $aDefaultConfigPath
	 * @return $this Returns $this for chaining.
	 */
	public function defineConfigPath($aDefaultConfigPath)
	{
		$thePossibleFolder = $aDefaultConfigPath;
		//If the webserver's domain is not available as a path in the configs
		//  folder and "anyhost" does exist, use the "anyhost" config folder, else
		//  use the default webserver's domain config path.  This allows for easier
		//  setup as a part of a Docker container which frequently changes its domain.
		define('BITS_CFG_PATH',
				( !file_exists(BITS_CONFIG_DIR.¦.$thePossibleFolder)
						&& file_exists( BITS_CONFIG_DIR.¦.static::CATCH_ALL_HOST_NAME ))
				? BITS_CONFIG_DIR.¦.static::CATCH_ALL_HOST_NAME.¦
				: BITS_CONFIG_DIR.¦.$thePossibleFolder.¦
		);
		return $this;
	}
	
	/**
	 * Define constants related to the webserver and URL requested.
	 * @return $this Returns $this for chaining.
	 */
	protected function defineWebsiteConstants()
	{
		define('BITS_SERVER_NAME', (!empty($_SERVER['SERVER_NAME']))
				? $_SERVER['SERVER_NAME'] : static::DEFAULT_SERVER_NAME;
		);
		define('BITS_SERVER_PORT', (!empty($_SERVER['SERVER_PORT']))
				? $_SERVER['SERVER_PORT'] : 80
		);
		define('SERVER_URL',
				( (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on') ? 'https' : 'http' )
				. '://' . BITS_SERVER_NAME
				. ((BITS_SERVER_PORT==80 || BITS_SERVER_PORT==443) ? '' : ':'.BITS_SERVER_PORT)
		);
		//relative url
		define( 'REQUEST_URL', array_key_exists('url', $_GET) ? $_GET['url'] : '' );
		//non-domain site URL that does not end in a /.
		if (!defined('BITS_URL'))
		{
			if ( !$this->isRunningUnderCLI() )
			{
				$theScriptFolder = dirname($_SERVER['SCRIPT_NAME']);
				$theScriptFolder = ($theScriptFolder!='.' && $theScriptFolder!='/')
						? str_replace(DIRECTORY_SEPARATOR, '/', $theScriptFolder) : '';
			}
			else
				$theScriptFolder = BITS_ROOT;
			define('BITS_URL', $theScriptFolder);
		}
		//Virtual Host folder name, if exists.
		if (!defined('VIRTUAL_HOST_NAME'))
			define('VIRTUAL_HOST_NAME', basename(BITS_URL));
		//Resource URL that does not end in a /.
		define('BITS_RES',BITS_URL.'/res');
		//Library URL that does not end in a /.
		define('BITS_LIB',BITS_URL.'/lib');
		//no need for app url as that is where all the urls normally get routed towards.
		
		//Non-library JavaScript content for the website that does not end in a /.
		define('WEBAPP_JS_URL', BITS_URL.'/app/js');
		
		return $this;
	}

	/**
	 * NOTE: Backwards compatibility with websites that did not have Regisseur class.
	 * If a website wants to define custom constants, load up <code>appdefines.php</code>
	 * @return $this Returns $this for chaining.
	 */
	protected function defineCustomConstants()
	{
		$theCustomDefinesFile = BITS_PATH.'appdefines.php';
		if (file_exists($theCustomDefinesFile)) {
			/*-***********************************************************************
			 * Special constants which may be defined by appdefines.php using the
			 * BitsTheater namespace:
			 *
			 * define('WEBAPP_NAMESPACE','MyWebAppNamespace\\'); //always end with "\\"
			 *
			 *************************************************************************/
			include_once($theCustomDefinesFile);
		}
		//if a custom WEBAPP_NAMESPACE was not defined, ensure the constant has meaning.
		if ( !defined('WEBAPP_NAMESPACE') )
			define('WEBAPP_NAMESPACE', BITS_NAMESPACE);
		return $this;
	}
	
	/**
	 * Define constants needed for website operation.
	 * @return $this Returns $this for chaining.
	 */
	public function defineConstants()
	{
		$this->defineNamespaceConstants();
		$this->defineFilePathConstants();
		$this->defineWebsiteConstants();
		$this->defineCustomConstants();
		return $this;
	}
	
	/**
	 * Generic classname to full file path converter.
	 * @param string $aClassName - the full namespaced class name.
	 * @param string $aNamespace - the namespace to compare against.
	 * @param string $aRootPath - the root path for the namespace.
	 * @return string|NULL Returns the full file path if namespace matches class name.
	 */
	protected function classNameToPath( $aClassName, $aNamespace, $aRootPath )
	{
		if ( Strings::beginsWith( $aClassName, $aNamespace ) )
		{
			//convert namespace format ns\sub-ns\classname into folder paths
			return $aRootPath . str_replace('\\', DIRECTORY_SEPARATOR,
					Strings::strstr_after( $aClassName, $aNamespace )
			) . '.php';
		}
	}
	
	/**
	 * Generic classname to full file path converter.
	 * @param string $aClassName - the full namespaced class name.
	 * @param string $aNamespace - the namespace to compare against.
	 * @return string|NULL Returns the full file path if namespace matches class name.
	 */
	protected function classNameToResPath( $aClassName, $aNamespace )
	{
		if ( Strings::beginsWith( $aClassName, $aNamespace ) )
		{
			//convert namespace format ns\sub-ns\classname into folder paths
			$theClassFile = str_replace( '\\', DIRECTORY_SEPARATOR,
					Strings::strstr_after( $aClassName ,$aNamespace)
			) . '.php';
			//en, de, es, etc. 2 letter language codes get directed to the i18n folder
			if ( $theClassFile{2}==DIRECTORY_SEPARATOR )
				$theClassNamePath = BITS_RES_PATH . 'i18n' . ¦ . $theClassFile;
			else
				$theClassNamePath = BITS_RES_PATH . $theClassFile;
		}
	}
	
	/**
	 * Generic function to include_once() the class file.
	 * @param string $aClassWithFullPath - the full file path.
	 * @return boolean Returns TRUE if file was included.
	 */
	protected function loadClass( $aClassWithFullPath )
	{
		if ( is_file( $aClassWithFullPath ) )
			return include_once( $aClassWithFullPath );
		else
			return false;
	}
	
	/**
	 * Load classes from the <code>[site]/app/*</code> paths.
	 * @param string $aClassName - Class or Interface name to load.
	 */
	protected function appLoaderForBITS( $aClassName )
	{
		return $this->loadClass(
				$this->classNameToPath( $aClassName, BITS_NAMESPACE, BITS_APP_PATH )
		);
	}
	
	/**
	 * Load classes from the <code>[site]/app/*</code> paths.
	 * @param string $aClassName - Class or Interface name to load.
	 */
	protected function appLoaderForWebApp( $aClassName )
	{
		return $this->loadClass(
				$this->classNameToPath( $aClassName, WEBAPP_NAMESPACE, BITS_APP_PATH )
		);
	}
	
	/**
	 * Load classes from the <code>[site]/configs/<domain>/*</code> paths.
	 * NOTE: website domain is included so that live config and localhost sandbox
	 * can coexist and avoids getting overwritten accidentally if checked into a
	 * source code control mechanism.
	 * @param string $aClassName - Class or Interface name to load.
	 */
	protected function configLoader( $aClassName )
	{
		return $this->loadClass(
				$this->classNameToPath( $aClassName, BITS_NAMESPACE_CFGS, BITS_CFG_PATH )
		);
	}
	
	/**
	 * Load BITS_NAMESPACE_RES classes from the <code>[site]/res/*</code> paths.
	 * @param string $aClassName - Class or Interface name to load.
	 */
	protected function resLoaderForBITS( $aClassName )
	{
		return $this->loadClass(
				$this->classNameToResPath( $aClassName, BITS_NAMESPACE_RES )
		);
	}

	/**
	 * Load WEBAPP_NAMESPACE resource classes from the <code>[site]/res/*</code> paths.
	 * @param string $aClassName - Class or Interface name to load.
	 */
	protected function resLoaderForWebApp( $aClassName )
	{
		return $this->loadClass(
				$this->classNameToResPath( $aClassName, WEBAPP_NAMESPACE . 'res\\' )
		);
	}
	
	/**
	 * Register the <code>[site]/res/*</code> class autoloader(s).
	 */
	public function registerResLoaders()
	{
		spl_autoload_register( array($this, 'resLoaderForBITS') );
		spl_autoload_register( array($this, 'resLoaderForWebApp') );
	}
	
	/**
	 * Register the <code>[site]/app/*</code> class autoloader(s).
	 */
	public function registerAppLoaders()
	{
		spl_autoload_register( array($this, 'appLoaderForBITS') );
		spl_autoload_register( array($this, 'appLoaderForWebApp') );
	}
	
	/**
	 * Register the <code>[site]/lib/*</code> class autoloader(s).
	 */
	public function registerLibLoaders()
	{
		require_once( BITS_LIB_PATH .'autoloader.php' );
		//phpmailer autoloader
		require_once( BITS_LIB_PATH . 'phpmailer' . DIRECTORY_SEPARATOR
				. 'PHPMailer' . DIRECTORY_SEPARATOR . 'PHPMailerAutoload.php' ) ;
	}
	
	/**
	 * Register the <code>[site]/configs/<domain>/*</code> class autoloader(s).
	 */
	public function registerConfigLoaders()
	{
		spl_autoload_register( array($this, 'configLoader') );
	}
	
	/**
	 * Register the <code>[site]/app/*</code> class autoloader(s).
	 */
	public function registerCatchAllLoaders()
	{
		spl_autoload_register( array($this, 'loadClass') );
	}
	
	/**
	 * Register the <code>[site]/lib/*</code> class autoloader(s).
	 * @return $this Returns $this for chaining.
	 */
	public function registerClassLoaders()
	{
		//lib/* loaders first (so we can use Strings class easily)
		$this->registerLibLoaders();
		//configs/* loaders next so we can get Settings and DB connections
		$this->registerConfigLoaders();
		//res/* loaders next for strings and resources, and because is subset of app namespace
		$this->registerResLoaders();
		//app/* loaders next for the main website app classes
		$this->registerAppLoaders();
		//miscellaneous loaders
		$this->registerCatchAllLoaders();
		return $this;
	}
	
}//end class

}//end namespace
