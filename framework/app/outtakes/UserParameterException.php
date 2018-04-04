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
		const ERR_INVALID_ARGUMENT_VALUE = 422 ;

		const MSG_FILTER_TOO_BROAD = 'userParam/errmsg_filter_too_broad' ;
		const MSG_USER_PARAM_TOO_LONG = "userParam/errmsg_param_too_long";
		const MSG_INVALID_ARGUMENT_VALUE = 'userParam/errmsg_invalid_arg_value';
        
		// Condition constants if you like to use them rather than remember strings to use.
		const ACT_FILTER_TOO_BROAD = 'FILTER_TOO_BROAD';
		const ACT_USER_PARAM_TOO_LONG = 'USER_PARAM_TOO_LONG';
		const ACT_INVALID_ARGUMENT_VALUE = 'INVALID_ARGUMENT_VALUE';
	}//end class

}//end namespace
