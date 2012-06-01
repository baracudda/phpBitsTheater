<?php
namespace app\config;
{//begin namespace

final class I18N extends \stdClass {

	const LANG = '%lang%';
	const REGION = '%region%';
	
	const PATH_LANG = '%path_lang%';
	const PATH_REGION = '%path_region%';

	const DEFAULT_LANG = '%default_lang%';
	const DEFAULT_REGION = '%default_region%';
	
	const DEFAULT_PATH_LANG = '%default_path_lang%';
	const DEFAULT_PATH_REGION = '%default_path_region%';
	
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
		elseif (file_exists(GEMS_RES_PATH.$aResourceClass.'.php'))
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
		elseif (file_exists(GEMS_RES_PATH.$aResourceClass.'.php'))
			$theClass = 'res\\'.$aResourceClass;
		else
			$theClass = 'res\\Resources2';
		return $theClass;
	}

}//end class

}//end namespace

