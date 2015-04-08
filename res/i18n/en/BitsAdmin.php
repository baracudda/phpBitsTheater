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
use BitsTheater\res\Resources as BaseResources;
{//begin namespace

class BitsAdmin extends BaseResources {

	public $menu_website_status_label = 'Website Status';
	public $menu_website_status_subtext = '';
	
	public $title_websiteStatus = 'Website Status';
	
	public $colheader_feature = 'Feature';
	public $colheader_curr_version = 'Version In Use';
	public $colheader_new_version = 'Version Should Be';
	public $colheader_update = 'Update?';
	
	public $btn_label_update = '<span class="glyphicon glyphicon-wrench"></span> Update!';
	public $btn_label_uptodate = '<span class="glyphicon glyphicon-ok"></span> Already up to date';
	public $btn_label_resetup_db = '<span class="glyphicon glyphicon-asterisk"></span> Create Missing Tables';
	
	public $msg_warning_backup_db = "UPDATING COULD RESULT IN DATA LOSS IF A PROBLEM ARISES!<br>\nBACKUP YOUR DATABASE BEFORE UPDATING!";
	public $msg_update_success = 'Update successful!';
	public $msg_copy_cfg_fail = "Cannot copy over [cfg]/%s. Please make it writeable and try again.";
	
	public $dialog_update_warning_title = 'WARNING!';
	public $dialog_update_warning_msg = '<p class="lead">Are you SURE you want to update this feature?</p><p class="label-warning">Have you backed up the database?</p>';
	public $dialog_update_warning_btn_cancel = 'Cancel';
	public $dialog_update_warning_btn_update = 'Yes, I am sure; Update already!';
	
	public $field_value_unknown_version = 'Unknown';
	
}//end class

}//end namespace
