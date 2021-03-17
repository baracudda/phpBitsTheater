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

namespace BitsTheater\costumes\CursorCloset;
use BitsTheater\costumes\CursorCloset\ARecord as BaseCostume;
use BitsTheater\models\Auth as MyModel;
use com\blackmoonit\Strings;
{//namespace begin

class AuthMobile extends BaseCostume
{
	/** @var string Fully qualified classname. */
	const ITEM_CLASS = __CLASS__;
	
	//common audit fields
	public $created_by;
	public $updated_by;
	public $created_ts;
	public $updated_ts;
	
	/** @var string Record ID. */
	public $mobile_id;
	/** @var string Link to account by auth_id. */
	public $auth_id;
	/** @var integer Link to account by account_id. */
	public $account_id;
	/** @var string Android accounts require a type, ie. 'FULL_ACCESS'. */
	public $auth_type;
	/** @var string User Token created and stored in place of the password on device. */
	public $account_token;
	/** @var string The device name as stored on the device itself. */
	public $device_name;
	/** @var float The reported GPS latitude of the device. */
	public $latitude;
	/** @var float The reported GPS longitude of the device. */
	public $longitude;
	/** @var string The hash of the device fingerprint. */
	public $fingerprint_hash;
	
	
	/** @var string[] List of fields excluded from export by default. */
	static protected $RESTRICTED_EXPORT_FIELD_LIST = array(
			'fingerprint_hash',
	);
	
	/** @return MyModel Returns my model to use. */
	public function getMyModel( $aOrgID=null )
	{ return $this->getProp(MyModel::MODEL_NAME, $aOrgID); }

	/**
	 * Construct the standard object with all data fields worth exporting defined.
	 * @return object Returns a standard object with the properties to export defined.
	 */
	protected function constructExportObject()
	{
		$o = parent::constructExportObject();
		$o->account_id = Strings::toInt($o->account_id);
		$o->latitude = ( isset($o->latitude) ) ? floatval($o->latitude) : null;
		$o->longitude = ( isset($o->longitude) ) ? floatval($o->longitude) : null;
		return $o;
	}
		
}//end class
	
}//end namespace
