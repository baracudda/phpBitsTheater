<?php

namespace BitsTheater\res\en;
use BitsTheater\res\Resources as BaseResources;

class UserParam extends BaseResources
{
	public $errmsg_filter_too_broad =
		'Filter value [%s] is too broad to affect the result set.' ;
    public $errmsg_invalid_arg_value =
    	'Value [%2$s] is invalid for parameter [%1$s].' ;
    public $errmsg_param_too_long = 'Parameter [%s] is too long.' ;
}