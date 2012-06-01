<?php
namespace app\config;
{//begin namespace

final class I18N extends \stdClass {

	const LANG = 'en';
	const REGION = 'US';
	
	const PATH_LANG = '/srv/www/mydomain.com/res/i18n/en/';
	const PATH_REGION = '/srv/www/mydomain.com/res/i18n/en/US/';

	const DEFAULT_LANG = 'en';
	const DEFAULT_REGION = 'US';
	
	const DEFAULT_PATH_LANG = '/srv/www/mydomain.com/res/i18n/en/';
	const DEFAULT_PATH_REGION = '/srv/www/mydomain.com/res/i18n/en/US/';
	
	static public function findClassNamespace($aResourceClass) {
		/*
		$theClass = 'res\\i18n\\'.self::LANG.'\\'.self::REGION.'\\'.$aResourceClass;
		if (class_exists($theClass)) {
			return $theClass;
		} else {
			$theClass = 'res\\i18n\\'.self::LANG.'\\'.$aResourceClass;
			if (class_exists($theClass)) {
				return $theClass;
			} else {
				return 'res\\'.$aResourceClass;
			}
		}
		*/
		if (file_exists(self::PATH_REGION.$aResourceClass.'.php'))
			$theClass = 'res\\'.self::LANG.'\\'.self::REGION.'\\'.$aResourceClass;
		elseif (file_exists(self::PATH_LANG.$aResourceClass.'.php'))
			$theClass = 'res\\'.self::LANG.'\\'.$aResourceClass;
		elseif (file_exists(BITS_RES_PATH.$aResourceClass.'.php'))
			$theClass = 'res\\'.$aResourceClass;
		else
			$theClass = 'res\\Resources';
		return $theClass;
	}

	static public function findDefaultClassNamespace($aResourceClass) {
		/*
		$theClass = 'res\\'.self::DEFAULT_LANG.'\\'.self::DEFAULT_REGION.'\\'.$aResourceClass;
		if (class_exists($theClass)) {
			return $theClass;
		} else {
			$theClass = 'res\\'.self::DEFAULT_LANG.'\\'.$aResourceClass;
			if (class_exists($theClass)) {
				return $theClass;
			} else {
				return 'res\\'.$aResourceClass;
			}
		}
		*/
		if (file_exists(self::DEFAULT_PATH_REGION.$aResourceClass.'.php'))
			$theClass = 'res\\'.self::DEFAULT_LANG.'\\'.self::DEFAULT_REGION.'\\'.$aResourceClass;
		elseif (file_exists(self::DEFAULT_PATH_LANG.$aResourceClass.'.php'))
			$theClass = 'res\\'.self::DEFAULT_LANG.'\\'.$aResourceClass;
		elseif (file_exists(BITS_RES_PATH.$aResourceClass.'.php'))
			$theClass = 'res\\'.$aResourceClass;
		else
			$theClass = 'res\\Resources2';
		return $theClass;
	}

}//end class

}//end namespace

