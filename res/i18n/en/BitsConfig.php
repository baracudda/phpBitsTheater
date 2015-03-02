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

namespace BitsTheater\res\en;
use BitsTheater\res\Config as BaseResources;
use BitsTheater\costumes\ConfigSettingInfo;
{//begin namespace

class BitsConfig extends BaseResources {
	public $menu_admin_label = 'Admin';
	public $menu_admin_subtext = 'nuts &amp; bolts';
	public $menu_settings_label = 'Settings';
	public $menu_settings_subtext = '';
	
	public $msg_save_applied = 'Settings saved!';

	public $label_namespace = array(
			'site' => 'Site Settings',
			'auth' => 'Authorization',
	);
	public $desc_namespace = array(
			'site' => 'Settings that will determine behavior site-wide.',
			'auth' => 'Determines how identity is discovered.',
	);
	
	public $label_site = array(
			'mode' => 'Operating Mode',
	);
	public $desc_site = array(
			'mode' => 'Normal is the standard operation mode; Maintenance will refuse connections; Demo/Kiosk mode will favor local resources.',
	);
	public $input_site = array(
			'mode' => array(
					'type' => ConfigSettingInfo::INPUT_DROPDOWN,
					'default' => 'normal',
					'values' => array(
							'normal' => 'Normal',
							'maintenance' => 'Maintenance',
							'demo' => 'Demo/Kiosk',
					),
			),
	);
	
	public $label_auth = array(
			'register_url' => 'Registration URL',
			'login_url' => 'Login URL',
			'logout_url' => 'Logout URL',
			'cookie_freshness_duration' => 'Cookie Freshness Duration'
	);
	public $desc_auth = array(
			'register_url' => 'URL for the registration page.',
			'login_url' => 'URL for the login page.',
			'logout_url' => 'URL for the logout page.',
			'cookie_freshness_duration' => 'Login cookies stay valid only so long.'
	);
	public $input_auth = array(
			'register_url' => array(
					'type' => ConfigSettingInfo::INPUT_STRING,
					'default' => 'account/register',
					'is_editable' => false,
			),
			'login_url' => array(
					'type' => ConfigSettingInfo::INPUT_STRING,
					'default' => 'account/login',
					'is_editable' => false,
			),
			'logout_url' => array(
					'type' => ConfigSettingInfo::INPUT_STRING,
					'default' => 'account/logout',
					'is_editable' => false,
			),
			'cookie_freshness_duration' => array(
					'type' => ConfigSettingInfo::INPUT_DROPDOWN,
					'is_editable' => true,
					'default' => 'duration_1_month',
					'values' => array(
							'duration_0' => 'Do not use cookies!',
							'duration_1_day' => '1 Day',
							'duration_1_week' => '1 Week',
							'duration_1_month' => '1 Month',
							'duration_3_months' => '3 Months',
							'duration_forever' => 'Never go stale (not recommended)',
					),
			),
	);

}//end class

}//end namespace
