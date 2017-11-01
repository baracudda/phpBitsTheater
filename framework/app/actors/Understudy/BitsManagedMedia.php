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

namespace BitsTheater\actors\Understudy;
use BitsTheater\Actor as BaseActor;
use BitsTheater\Scene as MyScene;
	/* @var $v MyScene */
use com\blackmoonit\Strings;
{//namespace begin

class BitsManagedMedia extends BaseActor {

	/**
	 * Given the file ID and possibly MIME type, return the filepath.
	 * @param unknown $aFileId
	 * @param string $aMimeType
	 */
	protected function getFilePathOf($aMimeType=null)
	{
		$thePath = $this->getConfigSetting('site/mmr');
		if (!Strings::endsWith($thePath, DIRECTORY_SEPARATOR))
		{
			$thePath .= DIRECTORY_SEPARATOR;
		}
		$thePath .= (!empty($aMimeType)) ? strstr($aMimeType, '/', true).DIRECTORY_SEPARATOR : '';
		return $thePath;
	}
	
}//end class

}//end namespace

