<?php
namespace BitsTheater\outtakes;
use BitsTheater\BrokenLeg ;
{//begin namespace
	
	/**
	 * Provides error responses specific to parameter validation.
	 */
	class UserParameterException extends BrokenLeg
	{
		const ERR_FILTER_TOO_BROAD = 300 ;
		const ERR_USER_PARAM_TOO_LONG = 400;

		const MSG_FILTER_TOO_BROAD = 'userParam/errmsg_filter_too_broad' ;
		const MSG_USER_PARAM_TOO_LONG = "userParam/errmsg_param_too_long";
        
	}//end class

}//end namespace
