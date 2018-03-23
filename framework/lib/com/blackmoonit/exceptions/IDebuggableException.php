<?php
/*
 * Copyright (C) 2013 Blackmoon Info Tech Services
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

namespace com\blackmoonit\exceptions;
{//begin namespace

interface IDebuggableException
{
	function getException();
	function setContextMsg($aMsg);
	function getContextMsg();
	function getErrorMsg();
	function getDebugMsg();
	function getDebugDisplay($aMsg = NULL);
	function getDebugCheck();
	function setDebugCheck($aDebugCheck);
	function getCssFileUrl();
	function setCssFileUrl($aCssFileUrl);
	function getFileRoot();
	function setFileRoot($aFileRoot);
	function debugPrint($aMsg = NULL);
}

}//namespace end
