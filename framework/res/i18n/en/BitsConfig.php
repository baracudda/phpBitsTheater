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
use com\blackmoonit\Strings ;
{//begin namespace

class BitsConfig extends BaseResources {
	public $menu_admin_label = 'Admin';
	public $menu_admin_subtext = 'nuts &amp; bolts';
	public $menu_settings_label = 'Settings';
	public $menu_settings_subtext = '';
	
	public $msg_save_applied = 'Settings saved!';
	public $msg_save_aborted = 'Error encountered, settings not saved.';
	public $errmsg_nothing_to_update = 'No changes detected, nothing updated.';
	
	public $title_settings_page = 'Configuration Settings';
	public $colheader_setting_name = 'Setting';
	public $colheader_setting_value = 'Value';
	public $colheader_setting_desc = 'Description';
	
	public $note_default_setting = 'Use "?" to reset a setting to its default value ("\\?" to save just a question mark).';
	public $msg_loading = 'Loading...';
	
	public $label_namespace = array(
			'site' => 'Site Settings',
			'auth' => 'Authorization',
			'email_out' => 'Outgoing Email',
	);
	public $desc_namespace = array(
			'site' => 'Settings that will determine behavior site-wide.',
			'auth' => 'Determines how identity is discovered.',
			'email_out' => 'Settings for an outgoing email server.'
	);
	
	public $label_site = array(
			'mode' => 'Operating Mode',
			'csrfCookieName' => 'CSRF Cookie Name',
			'csrfHeaderName' => 'CSRF Header Name',
			'mmr' => 'Managed Media Root',
			'maxfilesize' => 'Max File Upload Size',
	);
	public $desc_site = array(
			'mode' => 'Normal is the standard operation mode; Maintenance will refuse connections; Demo/Kiosk mode will favor local resources.',
			'csrfCookieName' => 'Cookie name containing the token used to prevent Cross-Site Request Forgeries.',
			'csrfHeaderName' => 'HTTP header name expected to be populated with the CSRF token.',
			'mmr' => 'Managed media files will be located under the specified server file path (usually located outside www root)',
			'maxfilesize' => 'Maximum allowed size for file uploads; may be different from, but must not be greater than, the server configuration.',
	);
	public $input_site = array(
			'mode' => array(
					'type' => ConfigSettingInfo::INPUT_DROPDOWN,
					'is_editable' => true,
					'default' => 'normal',
					'values' => array(
							'normal' => 'Normal',
							'maintenance' => 'Maintenance',
							'demo' => 'Demo/Kiosk',
					),
			),
			'csrfCookieName' => array(
					'type' => ConfigSettingInfo::INPUT_STRING,
					'is_editable' => true,
					'default' => 'Usher13',
			),
			'csrfHeaderName' =>  array(
					'type' => ConfigSettingInfo::INPUT_STRING,
					'is_editable' => true,
					'default' => 'Usher13',
			),
			'mmr' => array(
					'type' => ConfigSettingInfo::INPUT_STRING,
					'is_editable' => false,
					'default' => '',
			),
			'maxfilesize' => array(
					'type' => ConfigSettingInfo::INPUT_INTEGER,
					'is_editable' => false,
					'default' => '0',  // Initialized below.
			),
	);
	
	public $label_auth = array(
			'register_url' => 'Registration URL',
			'request_pwd_reset_url' => 'Password Reset Request URL',
			'login_url' => 'Login URL',
			'logout_url' => 'Logout URL',
			'cookie_freshness_duration' => 'Cookie Freshness Duration',
			'login_fail_attempts' => 'Login Attempts',
			'max_registrations' => 'Maximum Registrations Allowed',
	);
	public $desc_auth = array(
			'register_url' => 'URL for the registration page.',
			'request_pwd_reset_url' =>
					'URL for the page where a user requests a password reset.',
			'login_url' => 'URL for the login page.',
			'logout_url' => 'URL for the logout page.',
			'cookie_freshness_duration' => 'Login cookies stay valid only so long.',
			'login_fail_attempts' => 'User account locks itself after so many failures in one hour.',
			'max_registrations' => 'In any given span of one hour, limit the number of registrations.',
	);
	public $input_auth = array(
			'register_url' => array(
					'type' => ConfigSettingInfo::INPUT_STRING,
					'default' => 'account/register',
					'is_editable' => false,
			),
			'request_pwd_reset_url' => array(
					'type' => ConfigSettingInfo::INPUT_STRING,
					'default' => 'account/request_password_reset',
					'is_editable' => false
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
			'login_fail_attempts' => array(
					'type' => ConfigSettingInfo::INPUT_INTEGER,
					'default' => 7,
					'is_editable' => true,
			),
			'max_registrations' => array(
					'type' => ConfigSettingInfo::INPUT_INTEGER,
					'default' => 25,
					'is_editable' => true,
			),
	);

	public $label_email_out = array(
			'host' => 'SMTP Host Address',
			'port' => 'SMTP Port',
			'user' => 'User Name',
			'pwd' => 'Password',
			'security' => 'Security',
			'default_from' => 'Default From: Address',
			'actionTestEmail' => 'Send Test Email',
	);
	public $desc_email_out = array(
			'host' => 'IP address of the outgoing mail host',
			'port' => 'Port on the outgoing mail host',
			'user' => 'Name of the user account on the outgoing mail host',
			'pwd' => 'Password when authenticating outgoing messages',
			'security' =>
				'Encryption protocol to use for outgoing messages (SSL or TLS)',
			'default_from' =>
				'Default email address from which messages are sent',
			'actionTestEmail' => 'Send an email as a config test.',
	);
	public $input_email_out = array(
			'host' => array(
					'type' => ConfigSettingInfo::INPUT_STRING,
					'default' => '127.0.0.1',
					'is_editable' => true
			),
			'port' => array(
					'type' => ConfigSettingInfo::INPUT_INTEGER,
					'default' => '25',
					'is_editable' => true
			),
			'user' => array(
					'type' => ConfigSettingInfo::INPUT_STRING,
					'default' => 'do-not-reply@yourdomain.com',
					'is_editable' => true
			),
			'pwd' => array(
					'type' => ConfigSettingInfo::INPUT_PASSWORD,
					'default' => '', //'ThisIsNotARealPassword',
					'is_editable' => true
			),
			'security' => array(
					'type' => ConfigSettingInfo::INPUT_DROPDOWN,
					'default' => 'tls',
					'is_editable' => true,
					'values' => array(
							'tls' => 'TLS',
							'ssl' => 'SSL',
							'' => '(none - not recommended)',
					)
			),
			'default_from' => array(
					'type' => ConfigSettingInfo::INPUT_STRING,
					'default' => 'do-not-reply@yourdomain.com',
					'is_editable' => true
			),
			'actionTestEmail' => array(
					'type' => ConfigSettingInfo::INPUT_ACTION,
					'placeholder' => 'Send Test Email',
					'default' => '/api/config/testSendEmail',
			),
	);
	
	/**
	 * Some resources need to be initialized by running code rather than a
	 * static definition.
	 * Merging Enums with their UI counterparts is common.
	 */
	public function setup($aDirector)
	{
		//define the site/mmr/default before it gets merged by parent::setup()
		$theParentMmrPath = DIRECTORY_SEPARATOR.'var';
		$theVHN = VIRTUAL_HOST_NAME;
		if( !empty($theVHN) )
		{
			$theParentMmrPath = dirname(dirname(BITS_PATH));
			if (empty($theParentMmrPath) || $theParentMmrPath===DIRECTORY_SEPARATOR) {
				$theParentMmrPath = DIRECTORY_SEPARATOR.'var';
			}
		}
		$this->input_site['mmr']['default'] = $theParentMmrPath
				.DIRECTORY_SEPARATOR.'mmr'.DIRECTORY_SEPARATOR.$theVHN.DIRECTORY_SEPARATOR;
		//.../www/myhost/ -> .../mmr/myhost/
		
		$this->input_site['maxfilesize']['default'] = self::getDefaultMaxFileSize() ;
		parent::setup($aDirector) ;         // must go after all initializations
	}
	
	/**
	 * Deduces the actual largest upload size based on the values of several
	 * PHP server configs. The "memory limit" is the largest arena allocated to
	 * a running PHP script. The "upload max filesize" is the largest file
	 * allowed in PHP's temporary file storage. The "post max size" is the
	 * largest allowed POST request. The minimum of these values is the actual
	 * practical ceiling for file uploads.
	 * @return integer the actual, practical maximum, in bytes
	 */
	public static function getDefaultMaxFileSize()
	{
		$theMemoryLimit =
			Strings::semanticSizeToBytes( ini_get('memory_limit') ) ;
		$theMaxUploadSize =
			Strings::semanticSizeToBytes( ini_get('upload_max_filesize') ) ;
		$theMaxPostSize =
			Strings::semanticSizeToBytes( ini_get('post_max_size') ) ;
		$theSmallest = $theMemoryLimit ;
		if( $theMaxUploadSize < $theSmallest )
			$theSmallest = $theMaxUploadSize ;
		if( $theMaxPostSize < $theSmallest )
			$theSmallest = $theMaxPostSize ;
		return $theSmallest ;
	}
	
}//end class

}//end namespace
