<?php
/*
 * Copyright (C) 2018 Blackmoon Info Tech Services
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

namespace BitsTheater\res ;
use com\blackmoonit\Strings ;
use BitsTheater\Director;
use BitsTheater\costumes\AccountPrefSpec ;
use BitsTheater\res\Resources as BaseResources ;
{

/**
 * Defines account preferences that belong to the core library.
 * The <code>AccountPrefs</code> resource overrides this, and is where
 * app-specific account preferences should be defined.
 * @since BitsTheater v4.1.0
 */
class BitsAccountPrefs extends BaseResources
{
	/**
	 * Names the <code>$enum_</code> that is the root of the namespace/key
	 * hierarchy.
	 * @var string
	 */
	const ROOT_ENUMERATION = 'namespaces' ;
	
	/**
	 * Resolves a preference namespace/key, whether given as two separate values
	 * or as a slash-delimited pair.
	 * @param string $aSpace a preference namespace, or a namespace/key combo
	 * @param string $aPrefKey a preference key, or nothing
	 */
	public static function resolvePreferenceName( &$aSpace, &$aPrefKey )
	{
		if( empty($aSpace) )
		{
			Strings::errorLog( __METHOD__ . ' requires at least one string.' ) ;
			return ;
		}
		if( !empty( $aPrefKey ) ) return ; // trivially
		
		$theSpace = $aSpace ;
		$thePrefKey = $aPrefKey ;
		if( empty($aPrefKey) && strpos( $aSpace, '/' ) > 0 )
		{ // $aSpace might be given as 'space/key'; crack the two apart.
			list( $theSpace, $thePrefKey ) = explode( '/', $aSpace, 2 ) ;
		}
		$aSpace = $theSpace ;
		$aPrefKey = $thePrefKey ;
		return ;
	}
	
	/**
	 * Account preference namespaces. Preference keys reside under these.
	 * @var string[]
	 */
	public $enum_namespaces = array(
			'localization',
			'organization',
			'search_results',
		);
	
	/**
	 * Localization preferences.
	 * @var string[]
	 */
	public $enum_localization = array( 'time_zone' ) ;
	
	/**
	 * Organization-related preferences.
	 * @var string[]
	 */
	public $enum_organization = array( 'default_org' ) ;
	
	/**
	 * Preferences related to the display of search results.
	 * @var string[]
	 */
	public $enum_search_results = array(
			'page_size',
//			'default_sort_by',                  example for future consideration
//			'default_sort_order',               example for future consideration
		);
	
	/**
	 * Construct the hierarchy of preference namespaces, keys, and definitions.
	 * @param Director $aDirector the execution context
	 * @return \BitsTheater\res\BitsAccountPrefs $this
	 * @see \BitsTheater\res\Resources::setup()
	 */
	public function setup( Director $aDirector )
	{
		parent::setup($aDirector) ;
		$this->mergePreferenceInfo( self::ROOT_ENUMERATION ) ;
		foreach( $this->{self::ROOT_ENUMERATION} as $theName => $theEntry )
			$this->mergePreferenceInfo( $theName ) ;
		return $this ;
	}
	
	/**
	 * Construct the specification of app preference namespaces, keys, and
	 * definitions from various parts defined here and in the language-specific
	 * resources. The argument supplied here will be matched against a defined
	 * <code>$enum_</code> property. The method's postcondition provides a
	 * class member property with this name, under which the preference
	 * specs are organized in a dictionary.
	 * @param string $aEnumName the name of the root namespace from which to
	 *  begin
	 * @return \BitsTheater\res\BitsAccountPrefs $this
	 */
	public function mergePreferenceInfo( $aEnumName )
	{
		$thePrefTree = $aEnumName ;    // Root enum name becomes a class member.
		$this->{$thePrefTree} = array() ; // Initialize the preference spec dict
		$theEnum = 'enum_' . $aEnumName ;              // Hierarchy definition
		$theLabels = 'label_' . $aEnumName ;           // Preference labels (UI)
		$theDescs = 'desc_' . $aEnumName ;             // Pref descriptions (UI)
		$theSpecs = 'schema_' . $aEnumName ;           // Schematic specs
		if( isset( $this->{$theEnum} ) )
		{ // There is a subordinate enumeration which defines preference keys.
			foreach( $this->{$theEnum} as $theKey )
			{
				$thePref = new AccountPrefSpec( $aEnumName, $theKey ) ;
				if( $this->hasDefinitionKey( $theLabels, $theKey ) )
					$thePref->label = $this->{$theLabels}[$theKey] ;
				if( $this->hasDefinitionKey( $theDescs, $theKey ) )
					$thePref->desc = $this->{$theDescs}[$theKey] ;
				if( $this->hasDefinitionKey( $theSpecs, $theKey ) )
					$thePref->setFromSpec( $this->{$theSpecs}[$theKey] ) ;
				$this->{$thePrefTree}[$theKey] = $thePref ;
			}
			// Free the memory used by the unmerged enumerations.
			unset( $this->{$theEnum} ) ;
			if( isset( $this->{$theLabels} ) ) unset( $this->{$theLabels} ) ;
			if( isset( $this->{$theDescs} ) )  unset( $this->{$theDescs} ) ;
			if( isset( $this->{$theSpecs} ) )  unset( $this->{$theSpecs} ) ;
		}
		return $this ;
	}
	
	/**
	 * Examines whether this resource defines the specified information about a
	 * preference.
	 * @param string $aDefType the class member where we might find the key
	 * @param string $aKey the key we seek
	 * @return boolean <code>true</code> iff such a thing exists
	 */
	protected function hasDefinitionKey( $aDefType, $aKey )
	{
		return( isset( $this->{$aDefType} )
			&&	is_array( $this->{$aDefType} )
			&&	array_key_exists( $aKey, $this->{$aDefType} )
			);
	}
}

}