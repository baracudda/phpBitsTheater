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

namespace BitsTheater\costumes;
use BitsTheater\costumes\ABitsCostume as BaseCostume;
{//namespace begin

class FileUploadInfo extends BaseCostume {
	/**
	 * The filename (no path info).
	 * @var string
	 */
	public $name;
	/**
	 * The MIME type of the file.
	 * @var string
	 */
	public $type;
	/**
	 * The error/status code of the file upload.
	 * One of the UPLOAD_ERR_* PHP constants.
	 * @var number
	 */
	public $error;
	/**
	 * The file data is stored in this temp file (full path info).
	 * @var string
	 */
	public $tmp_name;
	
	/**
	 * Check the error var for OK status or not.
	 * @return boolean Returns TRUE if all uploaded OK.
	 */
	public function isUploadStatusOk() {
		return ($this->error==UPLOAD_ERR_OK);
	}
	
	/**
	 * Return the error message based on status.
	 * @return string Returns the error/status message.
	 */
	public function getErrorMessage() {
		switch ($this->error) {
			case UPLOAD_ERR_INI_SIZE:
				return 'Error: file size exceeds server permitted size.';
			case UPLOAD_ERR_FORM_SIZE:
				return 'Error: form size too large.';
			case UPLOAD_ERR_PARTIAL:
				return 'Error: Parital upload.';
			case UPLOAD_ERR_NO_FILE:
				return 'Error: No File.';
			case UPLOAD_ERR_NO_TMP_DIR:
				return 'Error: No Temp Folder.';
			case UPLOAD_ERR_CANT_WRITE:
				return 'Error: Cannot write.';
			case  UPLOAD_ERR_EXTENSION:
				return 'Error: File extension not supported.';
			case UPLOAD_ERR_OK: default:
				return 'OK';
		}
	}
	
	/**
	 * Return the temp file saved as an input stream.
	 * @return resource The file data as a stream.
	 */
	public function getInputStream() {
		return fopen($this->tmp_name, 'rb');
	}
	
}//end class
	
}//end namespace
