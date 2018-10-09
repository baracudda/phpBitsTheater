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

namespace BitsTheater\res\en ;
use BitsTheater\Director ;
use BitsTheater\Model ;
use BitsTheater\costumes\AccountPrefSpec ;
use BitsTheater\costumes\AuthOrg ;
use BitsTheater\costumes\IDirected ;
use BitsTheater\costumes\CursorCloset\AuthOrgSet ;
use BitsTheater\models\Auth as AuthModel ;
use BitsTheater\models\Config as ConfigModel ;      // used only to discover org
use BitsTheater\res\AccountPrefs as BaseResources ;
{//begin namespace
	
/**
 * String resources related to account preferences.
 * @since BitsTheater v4.1.0
 */
class BitsAccountPrefs extends BaseResources
{
	public $label_namespaces = array(
			'localization' => 'Regional and Localization Preferences',
			'organization' => 'Organization Preferences',
			'search_results' => 'Search Result Preferences',
		);
	
	public $desc_namespaces = array(
			'localization' => 'Preferences related to regional language and localization.',
			'organization' => 'Preferences for interacting with, and across, organizations.',
			'search_results' => 'Preferences when dealing with search result sets',
		);
	
	public $label_localization = array(
			'time_zone' => 'Time Zone',
		);
	
	public $desc_localization = array(
			'time_zone' => 'Specifies the time zone to be used in date/time displays.',
		);
	
	public $schema_localization = array(
			'time_zone' => array(
					'type' => AccountPrefSpec::TYPE_OPTION_LIST,
					'default_value' => 'UTC'
				),
		);
	
	public $label_organization = array(
			'default_org' => 'Default Organization',
		);
	
	public $desc_organization = array(
			'default_org' => 'Specifies the organization to which sessions should be linked upon initial login.',
		);
	
	public $schema_organization = array(
			'default_org' => array(
					'type' => AccountPrefSpec::TYPE_OPTION_LIST
				)
		);
	
	public $label_search_results = array(
			'page_size' => 'Page Size',
		);
	
	public $desc_search_results = array(
			'page_size' => 'Specifies the number of results that should be returned per "page".',
		);
	
	public $schema_search_results = array(
			'page_size' => array(
					'type' => AccountPrefSpec::TYPE_INTEGER,
					'default_value' => 25,
				),
		);
	
	/**
	 * {@inheritDoc}
	 * @see \BitsTheater\res\Resources::setup()
	 */
	public function setup( Director $aDirector )
	{
		$this->generateListOfTimeZones($aDirector)
			->generateListOfVisibleOrganizations($aDirector)
			;
		parent::setup($aDirector) ;
	}
	
	/**
	 * (TODO) Generates a selectable list of time zones.
	 * @param Director $aContext a context which can provide further resources
	 * @return \BitsTheater\res\en\BitsAccountPrefs $this
	 * @see \BitsTheater\res\en\BitsAccountPrefs::setup()
	 */
	protected function generateListOfTimeZones( Director $aContext )
	{
		// TODO Figure out how to generate a list of time zone labels and values
		return $this ;
	}
	
	/**
	 * Generates a selectable list of organizations to which the
	 * currently-authenticated account belongs.
	 * @param Director $aContext a context which can provide a model
	 * @return \BitsTheater\res\en\BitsAccountPrefs $this
	 * @see \BitsTheater\res\en\BitsAccountPrefs::setup()
	 */
	protected function generateListOfVisibleOrganizations( Director $aContext )
	{
		$dbAuth = $aContext->getProp( AuthModel::MODEL_NAME ) ;
		$theAuthID = $aContext->account_info->auth_id ;
		
		if( empty($theAuthID) ) return $this->disableDefaultOrgList($dbAuth) ;
		
		$theListSpec = array() ;
		$theAuthSet = AuthOrgSet::create($aContext)
			->setPagerEnabled(false)
			->setDataFromPDO( $dbAuth->getOrgsForAuthCursor($theAuthID) )
			;
		while( $theOrg = $theAuthSet->fetch() )
			$theListSpec[$theOrg->org_id] = $theOrg->org_title ;

//		$aContext->debugLog( __METHOD__ . ' [TRACE] list of orgs: ' . json_encode($theListSpec) ) ;
			
		if( empty($theListSpec) )
			return $this->disableDefaultOrgList($dbAuth) ;
		
		$this->schema_organization['default_org']['input_options'] = $theListSpec ;
		
		// Also set the default value to the current org.
		$this->schema_organization['default_org']['default_value'] =
				$this->discoverCurrentOrgID($aContext) ;
		
//		$aContext->debugLog( __METHOD__ . ' [TRACE] pref schema: ' . json_encode($this->schema_organization) ) ;
		return $this ;
	}
	
	/**
	 * Disables the "default org" preference and locks its value as the current
	 * account's organization.
	 * @param Model $aContext a model which can be used to resolve the account's
	 *  current organization
	 * @return \BitsTheater\res\en\BitsAccountPrefs $this
	 * @see \BitsTheater\res\en\BitsAccountPrefs::generateListOfVisibleOrganizations()
	 */
	protected function disableDefaultOrgList( Model $aContext )
	{
		$this->schema_organization['default_org'] = array(
				'type' => AccountPrefSpec::TYPE_STRING,
				'default_value' => $this->discoverCurrentOrgID($aContext),
				'is_editable' => false
			);
		return $this ;
	}
	
	/**
	 * Discovers the ID of the organization in which the current client is
	 * authenticated.
	 * @param IDirected $aContext a context that can provide models
	 * @return string the ID of the current org, or an empty string if the
	 *  client belongs to the root org
	 */
	protected function discoverCurrentOrgID( IDirected $aContext )
	{
		$theOrg = AuthOrg::forModelConnection(
				$aContext->getProp( ConfigModel::MODEL_NAME ) ) ;
		return( empty($theOrg) ? null : $theOrg->org_id ) ;
	}
}
	
}