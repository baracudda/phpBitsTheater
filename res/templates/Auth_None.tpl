<?php

namespace com\blackmoonit\bits_theater\app\model;
use com\blackmoonit\Strings;
{//namespace begin

class Auth extends AuthBase {
	const TYPE = 'None';  //skip all authentication methods
	const ALLOW_REGISTRATION = false;

	public function isAllowed($aNamespace, $aPermission, $acctInfo=null) {
		return TRUE;
	}
	
	public function getGroupList() {
		return array();
	}

}//end class

}//end namespace
