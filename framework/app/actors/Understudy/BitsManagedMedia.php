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
use BitsTheater\BrokenLeg;
use BitsTheater\costumes\APIResponse ;
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

	/**
	 * Provide an easy endpoint link for the UI to obtain a file as long as you
	 * are logged into the system and requested file exists in
	 * <code>%mmr%/res/*</code> path.
	 * NOTE: no "check-in" endpoint as we do not want unregulated uploads!
	 * $param string N-args as Path Segments with the last one being the file to download.
	 */
	public function ajajCheckout( $aSegmentsAsPathToFile=null )
	{
		if( $this->isGuest() )
		{ throw BrokenLeg::toss( $this, BrokenLeg::ACT_PERMISSION_DENIED ); }

		$v =& $this->scene;
		$theFilePath = null  ;
		
		if( func_num_args() > 0 )
		{ // Find the file.
			$theFileToGet = implode( DIRECTORY_SEPARATOR, func_get_args() ) ;
			//result = %MMR_PATH%/res/[path/to/file/to/]get.txt
			$theFilePath = $this->getFilePathOf('res/files') . $theFileToGet ;
		}
		
		if( empty($theFilePath) )
		{ // No file path to resolve.
			throw BrokenLeg::toss( $this,
					BrokenLeg::ACT_MISSING_VALUE, '(filename)' ) ;
		}
		if( !file_exists($theFilePath) )
		{ // Can't find the specified file in the MMR arena.
			throw BrokenLeg::toss( $this,
					BrokenLeg::ACT_FILE_NOT_FOUND, $theFilePath ) ;
		}
		
		// At this point, we have the file, and could render it back.
		// Now, find out some other things about it.
		
		$theContentType = mime_content_type($theFilePath) ;
		
		$theAcceptedTypes = $this->distillAcceptHeader((
				!empty($_SERVER['HTTP_ACCEPT']) ?
					$_SERVER['HTTP_ACCEPT'] : $v->mimetype
			));
		if( !empty($theAcceptedTypes) )
		{ // The client cares about the file's type, so validate it.
			if( ! $this->validateFileType( $theAcceptedTypes, $theContentType ) )
			{
				throw BrokenLeg::toss( $this, BrokenLeg::ACT_NOT_ACCEPTABLE,
					array( $theContentType, json_encode($theAcceptedTypes) ) ) ;
			}
		}
		
		// We now know we'll be allowed to serve the file back.
		$this->viewToRender('results_as_mimetype') ;
		$v->results = $theFilePath ; // Is converted to file by the view.
		$v->mimetype = $theContentType ; // Used by 'results_as_mimetype' view.
		
		if( !empty( $v->content_disposition ) )
		{ // Sanitize and echo the Content-Disposition that was requested.
			$v->content_disposition = strtolower( str_replace(
					' ', '', $v->content_disposition ) ) ;
		}
			
//		$this->logStuff( __METHOD__, ' [DEBUG] file=', $v->results ) ;
	}
	
	/**
	 * A simplistic algorithm to distill the Accept header from the HTTP request
	 * down to a list of MIME types. This can be searched for the type of the
	 * sought file.
	 * @param string $aHeader the Accept header from the request
	 * @return NULL|array an array of allowed types (might be empty), or null
	 *  if the input is empty
	 */
	protected function distillAcceptHeader( $aHeader )
	{
		if( empty($aHeader) ) return null ; // trivially
		$theAccepted = array() ;
		$theTypeSpecs = explode( ',', $aHeader ) ;
		foreach( $theTypeSpecs as $theSpec )
		{ // Capture the first token of each spec (the type itself).
			$theParsed = explode( ';', $theSpec ) ;
			array_push( $theAccepted,
					strtolower( str_replace( ' ', '', $theParsed[0] )) ) ;
		}
		return $theAccepted ;
	}
	
	/**
	 * An algorithm for matching a file's type against an array of accepted
	 * types.
	 * @param string[] $aAcceptedTypes the list of accepted types
	 * @param string $aFileType the file's type
	 * @return boolean true iff a match is found in the accepted types
	 */
	protected function validateFileType( array $aAcceptedTypes, $aFileType )
	{
		foreach( $aAcceptedTypes as $theAcceptedType )
		{ // Try to match the file type against any item in the accepted list.
			if( strcmp( $theAcceptedType, $aFileType ) == 0 )
				return true ; // Found an exact match.
			if( substr( $theAcceptedType, -1 ) == '*' )
			{ // The accepted subtype is a wildcard.
				$theAcceptedTop = // the accepted type up to the first slash
					substr( $theAcceptedType, 0, strpos( $theAcceptedType, '/' ) ) ;
				$theFileTop = // the file's type up to the first slash
					substr( $aFileType, 0, strpos( $aFileType, '/' ) ) ;
				if( strcmp( $theAcceptedTop, $theFileTop ) == 0 )
					return true ; // Found a wildcard-included match.
			}
		}
		
		return false ; // The algorithm found no matches.
	}
	
}//end class

}//end namespace

