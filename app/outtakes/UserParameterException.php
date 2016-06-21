<?php
namespace BitsTheater\outtakes;
use BitsTheater\BrokenLeg ;
{//begin namespace
	
	/**
	 * Provides error responses specific to parameter validation.
	 */
	class UserParameterException extends BrokenLeg
	{
		const ERR_USER_PARAM_TOO_LONG = 400;
		const MSG_USER_PARAM_TOO_LONG = "userParam/errmsg_param_too_long";
        
	}//end class

}//end namespace
